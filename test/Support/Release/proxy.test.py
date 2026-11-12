#!/usr/bin/env python3
"""Exercise the production Caddy edge against disposable Packio containers."""

import argparse
import http.client
import json
import os
from pathlib import Path
import re
import secrets
import socket
import ssl
import subprocess
import sys
import tempfile
import time
from urllib.parse import urlencode

ROOT = Path(__file__).resolve().parents[3]
APP_IMAGE = os.environ.get("PACKIO_PROXY_APP_IMAGE", "packio-release-011-prod:local")
SSR_IMAGE = os.environ.get("PACKIO_PROXY_SSR_IMAGE", "packio-release-011-prod-ssr:local")
POSTGRES_IMAGE = "postgres:17-alpine"
CADDY_IMAGE = "caddy:2.11.2-alpine"
PROXY_EXAMPLE = ROOT / "docs/examples/reverse-proxy/Caddyfile"

# Keep the browser contract with the real HTTP fixture, without another test dependency.
BROWSER_CHECK = r"""
import assert from 'node:assert/strict';
import { pathToFileURL } from 'node:url';
import path from 'node:path';

// Run with the existing locked Playwright package and host Node 24.21.0.
// Browser binaries run in the approved matched non-root remote container.
const { chromium } = await import(pathToFileURL(path.join(process.cwd(), 'node_modules/playwright/index.mjs')));
const [endpoint, appUrl] = process.argv.slice(2);
assert(endpoint && appUrl, 'Supply the owned browser endpoint and fixture HTTPS URL');
const browser = await chromium.connect(endpoint);
const context = await browser.newContext({ ignoreHTTPSErrors: true });
// Only the disposable fixture certificate is ignored here; the HTTP harness
// verifies its certificate chain and app/wildcard SNI independently.
try {
  await context.addInitScript(() => {
    window.packioCspViolations = [];
    document.addEventListener('securitypolicyviolation', event => {
      window.packioCspViolations.push({ disposition: event.disposition, directive: event.effectiveDirective, blocked: event.blockedURI });
    });
  });
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  const response = await page.goto(`${appUrl}/auth/login`, { waitUntil: 'domcontentloaded' });
  assert.equal(response.status(), 200);
  const headers = response.headers();
  assert(headers['content-security-policy-report-only']);
  assert.equal(headers['content-security-policy'], undefined);
  const initialHtml = await response.text();
  assert.match(initialHtml, /<input\b[^>]*type="email"/i, 'Initial HTTP response must contain the real SSR login form');
  await page.locator('input[type="email"]').waitFor();
  const turnstileScript = page.locator('#cloudflare-turnstile-script');
  await turnstileScript.waitFor({ state: 'attached', timeout: 30000 });
  assert.match(await turnstileScript.getAttribute('src'), /^https:\/\/challenges\.cloudflare\.com\/turnstile\/v0\/api\.js/);
  await page.waitForFunction(() => {
    const field = document.querySelector('input[name="cf-turnstile-response"]');
    return field && field.value.length > 0;
  }, null, { timeout: 60000 });
  assert(page.frames().some(frame => frame.url().startsWith('https://challenges.cloudflare.com/')), 'Real Turnstile iframe must load');
  const violations = await page.evaluate(() => window.packioCspViolations);
  assert.deepEqual(violations, [], 'Report-only policy must accommodate this SSR/hydration/Turnstile flow');
  assert.deepEqual(errors, [], 'Page must hydrate without runtime errors');
  // A real Inertia navigation exercises hydrated code, without replaying login.
  await page.locator('a[href*="/auth/register"]').first().click();
  await page.waitForURL(url => url.pathname === '/auth/register');
  await page.locator('input[type="email"]').waitFor();
  assert.deepEqual(errors, [], 'Hydrated auth navigation must remain functional');
  console.log('PASS: real SSR form, hydration/Inertia navigation and public-key Turnstile script/iframe/token under CSP report-only; no CSP violations/runtime errors.');
} finally {
  await context.close();
  await browser.close();
}
"""


class SetupError(RuntimeError):
    """A fixture could not start or a required local input was unavailable."""


class AssertionFailure(AssertionError):
    """A running fixture violated the edge contract."""


class ExpectedRed(AssertionFailure):
    """The unsafe baseline reached the app and failed its header assertion."""


def run(args, *, label, check=True, **kwargs):
    result = subprocess.run(args, stdout=subprocess.PIPE, stderr=subprocess.STDOUT,
                            text=True, check=False, **kwargs)
    if check and result.returncode:
        # Docker arguments include disposable secrets; never include command
        # lines or raw container output in durable or terminal diagnostics.
        raise SetupError(f"{label} failed (exit {result.returncode})")
    return result


def docker(*args, label, check=True):
    return run(("docker", *args), label=label, check=check)


def free_port():
    with socket.socket() as sock:
        sock.bind(("127.0.0.1", 0))
        return sock.getsockname()[1]


def wait_for(label, predicate, timeout=120):
    deadline = time.monotonic() + timeout
    while time.monotonic() < deadline:
        if predicate():
            return
        time.sleep(1)
    raise SetupError(f"timed out waiting for {label}")


def port_accepts(port):
    try:
        socket.create_connection(("127.0.0.1", port), timeout=1).close()
        return True
    except OSError:
        return False


def inspect_state(name):
    result = docker("inspect", "--format", "{{.State.Status}} {{if .State.Health}}{{.State.Health.Status}}{{end}}", name,
                    label=f"inspect {name}", check=False)
    return result.stdout.strip() if result.returncode == 0 else ""


def wait_container(name, health=False, timeout=180):
    def ready():
        state = inspect_state(name)
        return ("running healthy" if health else "running") in state

    wait_for(f"container {name}", ready, timeout)


def decode_peer_from_proc_tcp(name, target_port, *, exclude=()):
    """Read an established socket peer from the app's own network namespace."""
    php = r'''$port = (int) $argv[1];
foreach (['/proc/net/tcp', '/proc/net/tcp6'] as $path) {
    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach (array_slice($lines, 1) as $line) {
        $fields = preg_split('/\s+/', trim($line));
        if (count($fields) < 4 || '01' !== $fields[3]) { continue; }
        [, $localHexPort] = explode(':', $fields[1]);
        [$address] = explode(':', $fields[2]);
        if (hexdec($localHexPort) !== $port) { continue; }
        if (strlen($address) === 8) {
            $packed = pack('V', hexdec($address));
        } else {
            $packed = hex2bin($address);
            if (false !== $packed && 16 === strlen($packed)) {
                $packed = implode('', array_map('strrev', str_split($packed, 4)));
                $packed = hex2bin($packed);
            }
        }
        if (false !== $packed) { echo inet_ntop($packed), "\n"; }
    }
}'''
    result = docker("exec", name, "php", "-r", php, str(target_port), label="read app socket peer", check=False)
    if result.returncode:
        raise SetupError("could not inspect app network sockets")
    peers = sorted({line.strip() for line in result.stdout.splitlines() if line.strip() and line.strip() not in exclude})
    if len(peers) != 1:
        raise SetupError("expected one established proxy peer in app socket table")
    socket.inet_pton(socket.AF_INET if "." in peers[0] else socket.AF_INET6, peers[0])
    return peers[0]


class DirectTLSConnection(http.client.HTTPSConnection):
    """Connect to loopback while retaining the requested TLS SNI and Host."""

    def connect(self):
        raw = socket.create_connection(("127.0.0.1", self.port), self.timeout, self.source_address)
        self.sock = self._context.wrap_socket(raw, server_hostname=self.host)


def parse_page(body):
    match = re.search(r'<script\b(?=[^>]*\bdata-page="app")(?=[^>]*\btype="application/json")[^>]*>(.*?)</script>', body, re.S)
    if not match:
        raise AssertionFailure("response did not contain an Inertia page")
    try:
        return json.loads(match.group(1))
    except json.JSONDecodeError as error:
        raise AssertionFailure("response contained invalid Inertia page data") from error


def cookie_from(headers, current=""):
    cookies = [value.split(";", 1)[0] for value in headers.get_all("Set-Cookie", [])]
    if not cookies:
        return current
    return "; ".join(cookies)


def assert_edge_headers(headers):
    hsts = headers.get("Strict-Transport-Security", "")
    assert_contract(bool(re.search(r"(?:^|;\s*)max-age=\d+", hsts, re.I)), "missing HSTS max-age")
    assert_contract(headers.get("X-Content-Type-Options", "").lower() == "nosniff", "missing nosniff header")
    frame = headers.get("X-Frame-Options", "").upper()
    csp = headers.get("Content-Security-Policy", "")
    assert_contract(frame in ("DENY", "SAMEORIGIN") or "frame-ancestors" in csp.lower(), "missing frame policy")
    assert_contract(bool(headers.get("Content-Security-Policy-Report-Only")), "missing CSP report-only policy")
    assert_contract(not csp, "CSP enforcement is enabled before browser validation")


def assert_contract(condition, message):
    if not condition:
        raise AssertionFailure(message)


class Fixture:
    def __init__(self, *, baseline=False):
        self.baseline = baseline
        self.caddy_user = f"{os.getuid()}:{os.getgid()}"
        suffix = secrets.token_hex(5)
        self.prefix = f"packio-proxy-{os.getpid()}-{suffix}"
        self.network = self.prefix + "-net"
        self.volumes = [self.prefix + "-pg", self.prefix + "-sessions", self.prefix + "-limits"]
        self.pg = self.prefix + "-pg"
        self.ssr = self.prefix + "-ssr"
        self.app = self.prefix + "-app"
        self.caddy = self.prefix + "-caddy"
        self.probe = self.prefix + "-probe"
        self.containers = [self.pg, self.ssr, self.app, self.caddy, self.probe]
        self.http_port = free_port()
        self.https_port = free_port()
        self.app_domain = "packio.localhost"
        self.registry_domain = "packio.localhost"
        self.registry_host = "org.packio.localhost"
        self.db_password = secrets.token_urlsafe(24)
        self.secret_key = secrets.token_hex(32)
        self.temp = tempfile.TemporaryDirectory(prefix="packio-proxy-")
        self.path = Path(self.temp.name)
        self.path.chmod(0o755)
        self.ca = ssl.create_default_context(cafile=str(self.path / "test-ca.crt")) if (self.path / "test-ca.crt").exists() else None
        self.measure_port = free_port()

    def start(self):
        if os.getuid() == 0:
            raise SetupError("run the proxy fixture as an ordinary non-root user")
        if not self.baseline and not PROXY_EXAMPLE.is_file():
            raise SetupError("production Caddy example is not available yet")
        for image in (APP_IMAGE, SSR_IMAGE, POSTGRES_IMAGE, CADDY_IMAGE):
            result = docker("image", "inspect", image, label=f"inspect image {image}", check=False)
            if result.returncode:
                raise SetupError(f"required image is unavailable: {image}")
        self.validate_fixture_caddyfiles()
        docker("network", "create", "--label", "packio.proxy.test=true", self.network,
               label="create isolated production-style bridge")
        for volume in self.volumes:
            docker("volume", "create", "--label", "packio.proxy.test=true", volume, label="create fixture volume")
        self.start_services()
        self.measure_peer()
        self.restart_app_with_trust()
        self.create_registry_org()
        self.write_caddyfile()
        self.prepare_tls()
        self.start_caddy()
        self.assert_private_ports()

    def start_services(self):
        docker("run", "-d", "--name", self.pg, "--network", self.network,
               "--label", "packio.proxy.test=true", "-e", "POSTGRES_DB=packio",
               "-e", "POSTGRES_USER=packio", "-e", f"POSTGRES_PASSWORD={self.db_password}",
               "-v", f"{self.volumes[0]}:/var/lib/postgresql/data", "--health-cmd",
               "pg_isready -U packio -d packio", "--health-interval", "2s", "--health-timeout", "2s",
               "--health-retries", "40", POSTGRES_IMAGE, label="start PostgreSQL")
        wait_container(self.pg, health=True)
        docker("run", "-d", "--name", self.ssr, "--network", self.network,
               "--label", "packio.proxy.test=true", "-e", "INERTIA_SSR_HOST=0.0.0.0",
               "-e", "INERTIA_SSR_PORT=13714", SSR_IMAGE, label="start SSR")
        wait_for("SSR service", lambda: inspect_state(self.ssr).startswith("running"), 60)
        self.start_app(trust="")
        wait_container(self.app)
        wait_for("Packio application", self.app_healthy, 180)

    def app_healthy(self):
        result = docker("exec", self.app, "curl", "-fsS", "http://127.0.0.1/health", label="check app health", check=False)
        return result.returncode == 0

    def assert_private_ports(self):
        for name in (self.pg, self.ssr):
            value = docker("inspect", "--format", "{{json .NetworkSettings.Ports}}", name,
                           label="inspect private service ports").stdout.strip()
            bindings = json.loads(value)
            assert_contract(not any(bindings.values()), f"private service {name.rsplit('-', 1)[-1]} has a host port")
        value = docker("inspect", "--format", "{{json .NetworkSettings.Ports}}", self.app,
                       label="inspect app port binding").stdout.strip()
        bindings = json.loads(value)
        published = [binding for values in bindings.values() for binding in (values or [])]
        assert_contract(len(published) == 1 and published[0]["HostIp"] == "127.0.0.1",
                        "app HTTP port is not published only on loopback")
        assert_contract(bool(bindings.get("80/tcp")) and not any(values for key, values in bindings.items() if key != "80/tcp"),
                        "application RPC or another non-HTTP port is published")
        print("Private services: PostgreSQL/SSR/RPC unpublished; app HTTP loopback only")

    def start_app(self, trust, *, turnstile=False):
        env = [
            "-e", "APP_DEBUG=false", "-e", "APP_PRODUCTION=true",
            "-e", f"APP_URL=https://{self.app_domain}:{self.https_port}", "-e", f"BRAND_DOMAIN={self.app_domain}:{self.https_port}",
            "-e", f"BRAND_REGISTRY_BASE_DOMAIN={self.registry_domain}:{self.https_port}",
            "-e", f"CORS_ALLOWED_ORIGINS=https://{self.app_domain}:{self.https_port}",
            "-e", "DEFAULT_DB_CONNECTION=postgres", "-e", "DATABASE_HOST=" + self.pg,
            "-e", "DATABASE_PORT=5432", "-e", "DATABASE_NAME=packio", "-e", "DATABASE_USER=packio",
            "-e", f"DATABASE_PASS={self.db_password}", "-e", f"APP_SECRET_ENCRYPTION_KEY={self.secret_key}",
            "-e", f"TRUSTED_PROXIES={trust}", "-e", "RUN_MIGRATIONS=true",
            "-e", "INERTIA_SSR_ENABLED=true", "-e", f"INERTIA_SSR_URL=http://{self.ssr}:13714/render",
            "-e", "RATE_LIMIT_LOGIN_MAX=2", "-e", "RATE_LIMIT_LOGIN_DECAY=900",
            "-e", f"TURNSTILE_ENABLED={str(turnstile).lower()}",
            "-e", "TURNSTILE_SITE_KEY=1x00000000000000000000AA",
            "-e", "TURNSTILE_SECRET_KEY=1x0000000000000000000000000000000AA",
            "-e", "LOG_CHANNEL=stderr", "-e", "LOG_LEVEL=error",
            "-e", "RR_NUM_WORKERS=1", "-p", f"127.0.0.1:{self.http_port}:80",
            "-v", f"{self.volumes[1]}:/app/data/sessions", "-v", f"{self.volumes[2]}:/app/data/rate-limits",
        ]
        docker("run", "-d", "--name", self.app, "--network", self.network, "--network-alias", "packio-app",
               "--label", "packio.proxy.test=true", *env, APP_IMAGE, label="start Packio application")

    def measure_peer(self):
        # A completed health request leaves Caddy's upstream keep-alive socket
        # available for actual peer measurement in the app network namespace.
        config = self.path / "peer-probe.Caddyfile"
        config.write_text(self.peer_caddyfile())
        docker("run", "-d", "--name", self.caddy, "--network", "host", "--user", self.caddy_user,
               "--label", "packio.proxy.test=true", "-v", f"{config}:/etc/caddy/Caddyfile:ro",
               CADDY_IMAGE, "caddy", "run", "--config", "/etc/caddy/Caddyfile", "--adapter", "caddyfile",
               label="start peer measurement Caddy")
        deadline = time.monotonic() + 10
        while time.monotonic() < deadline:
            state = inspect_state(self.caddy)
            if state.startswith("running"):
                break
            if state.startswith(("exited", "dead")):
                logs = docker("logs", "--tail", "20", self.caddy, label="read peer Caddy startup diagnostics", check=False)
                diagnostic = " | ".join(line.strip() for line in logs.stdout.splitlines() if line.strip())
                diagnostic = diagnostic.replace(self.app_domain, "<app-host>")
                raise SetupError(f"peer measurement Caddy exited during startup: {diagnostic[:700]}")
            time.sleep(0.1)
        else:
            raise SetupError("peer measurement Caddy did not enter running state")
        # "running" comes before Caddy binds its listener.
        wait_for("peer measurement Caddy listener", lambda: port_accepts(self.measure_port), 30)
        # A completed health request leaves Caddy's real upstream keep-alive
        # connection open; an incomplete body may be buffered before dialing.
        client = http.client.HTTPConnection("127.0.0.1", self.measure_port, timeout=10)
        try:
            client.request("GET", "/health", headers={"Host": self.app_domain})
            response = client.getresponse()
            response.read()
            if response.status != 200:
                logs = docker("logs", "--tail", "12", self.caddy, label="read owned peer Caddy diagnostic", check=False)
                details = " | ".join(logs.stdout.splitlines())[-1800:]
                ports = docker("inspect", "--format", "{{json .NetworkSettings.Ports}}", self.app, label="inspect actual app publication", check=False).stdout.strip()
                details += f"; actual app ports={ports}"
                raise SetupError(f"peer measurement edge returned HTTP {response.status}: {details}")
            peers = decode_peer_from_proc_tcp(self.app, 80)
        finally:
            client.close()
        self.trusted_peer = peers
        print(f"Measured Caddy peer: {peers}; exact TRUSTED_PROXIES, no wildcard", flush=True)
        docker("rm", "-f", self.caddy, label="remove peer measurement Caddy")
        self.caddy = self.prefix + "-caddy"

    def restart_app_with_trust(self):
        docker("rm", "-f", self.app, label="recreate app with measured proxy trust")
        self.start_app(trust=self.trusted_peer)
        wait_container(self.app)
        wait_for("Packio application", self.app_healthy, 180)

    def create_registry_org(self):
        script = r'''require 'vendor/autoload.php';
$container = require 'config/container.php';
$api = $container->get(\Packio\Organization\Api\OrganizationApiFacadeInterface::class);
$org = $api->registerOrganization('proxy-test-' . bin2hex(random_bytes(5)));
$org = $api->updateOrganization($org->id, $org->name, $org->slug, true);
echo json_encode(['slug' => $org->slug], JSON_THROW_ON_ERROR);'''
        result = docker("exec", self.app, "php", "-r", script, label="create disposable registry organization")
        try:
            self.organization_slug = json.loads(result.stdout)["slug"]
        except (json.JSONDecodeError, KeyError) as error:
            raise SetupError("could not create disposable registry organization") from error
        self.registry_host = f"{self.organization_slug}.{self.registry_domain}"

    def write_caddyfile(self):
        path = self.path / "Caddyfile"
        if self.baseline:
            path.write_text(self.baseline_caddyfile())
            self.caddyfile = path
            return
        source = PROXY_EXAMPLE.read_text()
        envs = {
            "PACKIO_APP_DOMAIN": self.app_domain,
            "PACKIO_REGISTRY_DOMAIN": self.registry_domain,
            "PACKIO_HTTP_UPSTREAM": f"127.0.0.1:{self.http_port}",
            "PACKIO_TLS_CERT": "/tls/test.crt",
            "PACKIO_TLS_KEY": "/tls/test.key",
            "PACKIO_HTTPS_PORT": str(self.https_port),
            "PACKIO_EDGE_BIND": "127.0.0.1",
        }
        # Caddy expands environment substitutions at runtime. Require every
        # example input needed by this fixture to be present in the file.
        for name in ("PACKIO_APP_DOMAIN", "PACKIO_REGISTRY_DOMAIN", "PACKIO_HTTP_UPSTREAM",
                     "PACKIO_TLS_CERT", "PACKIO_TLS_KEY"):
            if re.search(rf"\{{\${name}(?::[^}}]*)?\}}", source) is None:
                raise SetupError(f"Caddy example does not consume {name}")
        # The HTTPS listener can be high-port in the fixture while production
        # remains 443; the example exposes this one environment input.
        if "PACKIO_HTTPS_PORT" not in source:
            raise SetupError("Caddy example must expose PACKIO_HTTPS_PORT for isolated local TLS")
        self.caddyfile = path
        path.write_text(source)
        self.caddy_env = envs

    def peer_caddyfile(self):
        return (
            "{\n admin off\n auto_https disable_redirects\n}\n" +
            f"http://{self.app_domain}:{self.measure_port} {{\n bind 127.0.0.1\n"
            f" reverse_proxy 127.0.0.1:{self.http_port}\n"
            "}\n"
        )

    def baseline_caddyfile(self):
        return (
            "{\n admin off\n auto_https disable_redirects\n}\n" +
            f"https://{self.app_domain}:{self.https_port} {{\n bind 127.0.0.1\n"
            " tls /tls/test.crt /tls/test.key\n"
            f" reverse_proxy 127.0.0.1:{self.http_port}\n"
            "}\n"
        )

    def validate_fixture_caddyfiles(self):
        configs = {
            "peer-probe.Caddyfile": self.peer_caddyfile(),
            "unsafe-baseline.Caddyfile": self.baseline_caddyfile(),
        }
        if not self.baseline:
            self.write_caddyfile()
            configs["production.Caddyfile"] = self.caddyfile.read_text()
        for filename, content in configs.items():
            path = self.path / filename
            path.write_text(content)
            path.chmod(0o644)
            env_args = []
            if filename == "production.Caddyfile":
                for key, value in self.caddy_env.items():
                    env_args.extend(("-e", f"{key}={value}"))
            result = docker(
                "run", "--rm", "--network", "none", "--user", self.caddy_user,
                "-v", f"{path}:/tmp/Caddyfile:ro", *env_args, CADDY_IMAGE,
                "caddy", "adapt", "--config", "/tmp/Caddyfile", "--adapter", "caddyfile",
                label=f"validate {filename}", check=False,
            )
            if result.returncode:
                detail = result.stdout.strip().splitlines()[-1:] or ["unknown Caddyfile error"]
                raise SetupError(f"Caddy config syntax failed for {filename}: {detail[0][:300]}")

    def start_caddy(self):
        args = ["run", "-d", "--name", self.caddy, "--network", "host", "--user", self.caddy_user,
                "--label", "packio.proxy.test=true", "-v", f"{self.caddyfile}:/etc/caddy/Caddyfile:ro",
                "-v", f"{self.path}:/tls:ro"]
        for key, value in getattr(self, "caddy_env", {}).items():
            args.extend(("-e", f"{key}={value}"))
        args.extend((CADDY_IMAGE, "caddy", "run", "--config", "/etc/caddy/Caddyfile", "--adapter", "caddyfile"))
        docker(*args, label="start production Caddy example")
        wait_for("production Caddy", lambda: inspect_state(self.caddy).startswith("running"), 30)

    def prepare_tls(self):
        cert = self.path / "test.crt"
        key = self.path / "test.key"
        san = f"DNS:{self.app_domain},DNS:*.{self.registry_domain}"
        run(("openssl", "req", "-x509", "-newkey", "rsa:2048", "-nodes", "-days", "1",
             "-subj", "/CN=Packio proxy fixture", "-addext", f"subjectAltName={san}",
             "-keyout", str(key), "-out", str(cert)), label="create disposable test TLS certificate")
        # Caddy uses the invoking non-root UID/GID and can read its private key.
        cert.chmod(0o644)
        key.chmod(0o600)
        self.ca = ssl.create_default_context(cafile=str(cert))

    def request(self, host, path, *, method="GET", headers=None, body=None, context=None, port=None):
        connection = DirectTLSConnection(host, port or self.https_port, timeout=10,
                                         context=context or self.ca)
        authority = host if self.https_port == 443 else f"{host}:{self.https_port}"
        request_headers = [("Host", authority), ("Connection", "close")]
        if headers:
            request_headers.extend(headers.items() if isinstance(headers, dict) else headers)
        try:
            connection.putrequest(method, path, skip_host=True, skip_accept_encoding=True)
            for key, value in request_headers:
                connection.putheader(key, value)
            if body is not None and not any(key.lower() == "content-length" for key, _ in request_headers):
                connection.putheader("Content-Length", str(len(body.encode())))
            connection.endheaders(body.encode() if isinstance(body, str) else body)
            response = connection.getresponse()
            payload = response.read().decode("utf-8", errors="replace")
            return response.status, response.headers, payload
        except (OSError, http.client.HTTPException) as error:
            raise SetupError("HTTPS request to Caddy failed") from error
        finally:
            connection.close()

    def direct_request(self, method, path, headers=None, body=None):
        """Send an unproxied HTTP request from a distinct bridge-network peer."""
        docker("run", "-d", "--name", self.probe, "--network", self.network,
               "--label", "packio.proxy.test=true", "--entrypoint", "sleep", APP_IMAGE, "120",
               label="start direct HTTP probe")
        try:
            target = docker("inspect", "--format", "{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}", self.app,
                            label="inspect app network address").stdout.strip()
            probe_script = r'''$headers = json_decode($argv[5], true) ?: [];
$lines = [];
foreach ($headers as $key => $value) { $lines[] = $key . ': ' . $value; }
$context = stream_context_create(['http' => [
    'method' => $argv[2], 'header' => implode("\r\n", $lines), 'content' => $argv[4],
    'ignore_errors' => true, 'timeout' => 10,
]]);
$body = @file_get_contents('http://' . $argv[1] . ':80' . $argv[3], false, $context);
$responseHeaders = $http_response_header ?? [];
$status = 0;
foreach ($responseHeaders as $header) {
    if (preg_match('/^HTTP\/\S+ (\d+)/', $header, $match)) { $status = (int) $match[1]; }
}
echo json_encode([$status, $responseHeaders, $body ?: '']);'''
            result = docker("exec", self.probe, "php", "-r", probe_script, target, method, path,
                            body or "", json.dumps(headers or {}), label="send direct HTTP request", check=False)
            if result.returncode:
                raise SetupError("direct HTTP probe failed")
            return json.loads(result.stdout)
        finally:
            docker("rm", "-f", self.probe, label="remove direct HTTP probe", check=False)

    def run_contract(self):
        status, edge, body = self.request(self.app_domain, "/auth/login")
        assert_contract(status == 200, "login page did not return 200 through TLS edge")
        assert_edge_headers(edge)
        page = parse_page(body)
        assert_contract(bool(re.search(r"<div[^>]+id=\"app\"[^>]*>\s*<", body)), "SSR page root was empty")
        props = page.get("props", {})
        registry = self.request(self.registry_host, "/packages.json", headers={
            "X-Forwarded-Proto": "http", "X-Forwarded-Host": "evil.example",
            "X-Forwarded-Port": "81", "Forwarded": "proto=http;host=evil.example",
        })
        assert_contract(registry[0] == 200, "registry route was unavailable through wildcard TLS host")
        registry_data = json.loads(registry[2])
        urls = json.dumps(registry_data)
        expected_registry = f"https://{self.registry_host}:{self.https_port}/p2/%package%"
        assert_contract(expected_registry in urls, "registry bootstrap did not use configured registry host")
        assert_contract("evil.example" not in urls and "http://" not in urls,
                        "registry bootstrap contains an untrusted or non-HTTPS URL")
        print("Wildcard TLS registry: configured HTTPS metadata URL passed")

        cookie = cookie_from(edge)
        csrf = props.get("csrf", {}).get("token")
        assert_contract(bool(cookie) and bool(csrf), "login page did not establish session and CSRF token")
        secure_cookie = any("; Secure" in value for value in edge.get_all("Set-Cookie", []))
        assert_contract(secure_cookie, "HTTPS login session cookie was not Secure")
        assert_contract(any("httponly" in value.lower() and "samesite=lax" in value.lower()
                            for value in edge.get_all("Set-Cookie", [])),
                        "login session cookie lacks HttpOnly or SameSite=Lax")
        for origin, expected in ((f"http://{self.app_domain}:{self.https_port}", 419),
                                 (f"https://{self.app_domain}:{self.https_port + 1}", 403),
                                 ("https://evil.example", 403)):
            status, rejected, _ = self.request(self.app_domain, "/auth/login", method="POST", headers={
                "Origin": origin, "X-Forwarded-Proto": "https", "X-Forwarded-Host": "evil.example",
                "X-Forwarded-Port": "443", "Forwarded": "proto=https;host=evil.example",
                "Cookie": cookie, "X-CSRF-Token": csrf, "Content-Type": "application/x-www-form-urlencoded",
            }, body=urlencode({"email": "origin-control@invalid.example", "password": secrets.token_urlsafe(18)}))
            print(f"Negative HTTPS origin control {origin}: status {status}")
            assert_contract(status == expected, "mismatched origin passed the production CORS/CSRF boundary")
            cookie = cookie_from(rejected, cookie)
            # CORS handles disallowed Origin before CSRF. A Referer-only request
            # reaches the CSRF origin resolver and must independently reject all
            # three mismatches with a valid token, despite hostile proxy headers.
            status, rejected, _ = self.request(self.app_domain, "/auth/login", method="POST", headers={
                "Referer": origin + "/auth/login", "X-Forwarded-Proto": "https",
                "X-Forwarded-Host": "evil.example", "X-Forwarded-Port": "443",
                "Cookie": cookie, "X-CSRF-Token": csrf, "Content-Type": "application/x-www-form-urlencoded",
            }, body=urlencode({"email": "referer-control@invalid.example", "password": secrets.token_urlsafe(18)}))
            assert_contract(status == 419, "mismatched scheme/port/cross-site Referer passed trusted HTTPS CSRF")
            cookie = cookie_from(rejected, cookie)
        print("HTTPS session cookie flags and hostile scheme/port/cross-site CSRF rejection passed")

        for attempt in (1, 2):
            marker = f"proxy-invalid-{secrets.token_hex(6)}@invalid.example"
            hostile = [
                ("X-Forwarded-For", f"198.51.100.{20 + attempt}"),
                ("X-Forwarded-For", f"203.0.113.{21 + attempt}"),
                ("X-Forwarded-Proto", "http"), ("X-Forwarded-Host", "evil.example"),
                ("X-Forwarded-Port", "81"),
                ("Forwarded", f"for=198.51.100.{22 + attempt};proto=http;host=evil.example"),
                ("Forwarded", f"for=198.51.100.{32 + attempt};proto=http;host=evil.example"),
                ("CF-Connecting-IP", f"192.0.2.{23 + attempt}"),
                ("CF-Connecting-IP", f"192.0.2.{33 + attempt}"),
                ("X-Real-IP", f"192.0.2.{24 + attempt}"),
                ("X-Real-IP", f"192.0.2.{34 + attempt}"),
                ("Origin", f"https://{self.app_domain}:{self.https_port}"),
                ("Referer", f"https://{self.app_domain}:{self.https_port}/auth/login"),
                ("X-CSRF-Token", csrf), ("Cookie", cookie),
                ("Content-Type", "application/x-www-form-urlencoded"), ("Accept", "text/html"),
            ]
            body_data = urlencode({"email": marker, "password": secrets.token_urlsafe(18)})
            status, headers, _ = self.request(self.app_domain, "/auth/login", method="POST",
                                              headers=hostile, body=body_data)
            assert_contract(status == 302 and headers.get("Location") == "/auth/login",
                            f"invalid credential attempt {attempt} did not reach login redirect")
            cookie = cookie_from(headers, cookie)
            status, headers, body = self.request(self.app_domain, "/auth/login", headers={"Cookie": cookie})
            page = parse_page(body)
            assert_contract(page.get("props", {}).get("errors", {}).get("email") == ["Invalid credentials."],
                            f"invalid credential attempt {attempt} did not reach authentication")
            assert_contract(page.get("props", {}).get("old", {}).get("email") == marker,
                            f"invalid credential attempt {attempt} did not preserve its disposable marker")
            cookie = cookie_from(headers, cookie)
            csrf = page.get("props", {}).get("csrf", {}).get("token", csrf)
        print("Trusted HTTPS CSRF accepted both hostile-header credential attempts")

        blocked_marker = f"proxy-blocked-{secrets.token_hex(6)}@invalid.example"
        hostile = [
            ("X-Forwarded-For", "203.0.113.99"), ("X-Forwarded-For", "198.51.100.98"),
            ("X-Forwarded-Proto", "https"), ("X-Forwarded-Host", "evil.example"),
            ("X-Forwarded-Port", "443"), ("Forwarded", "for=203.0.113.99;proto=https;host=evil.example"),
            ("CF-Connecting-IP", "192.0.2.99"), ("X-Real-IP", "192.0.2.98"),
            ("Origin", f"https://{self.app_domain}:{self.https_port}"),
            ("X-CSRF-Token", csrf), ("Cookie", cookie),
            ("Content-Type", "application/x-www-form-urlencoded"), ("Accept", "text/html"),
        ]
        status, headers, _ = self.request(
            self.app_domain, "/auth/login", method="POST", headers=hostile,
            body=urlencode({"email": blocked_marker, "password": secrets.token_urlsafe(18)}),
        )
        assert_contract(status == 302 and headers.get("Location") == "/auth/login", "third login attempt was not throttled")
        assert_contract(headers.get("Retry-After") == "900", "throttled login response did not include Retry-After: 900")
        cookie = cookie_from(headers, cookie)
        status, _, body = self.request(self.app_domain, "/auth/login", headers={"Cookie": cookie})
        page = parse_page(body)
        blocked_props = page.get("props", {})
        assert_contract(bool(page.get("flash", {}).get("error")), "throttled attempt did not deliver flash error")
        assert_contract(not blocked_props.get("errors"), "throttled attempt reached credential authentication")
        assert_contract(not blocked_props.get("old"),
                        "throttled attempt preserved submitted input after quota exhaustion")
        print("Hostile identity prefixes: first two reached auth; third 302/Location/Retry-After/flash, no auth marker")

        self.check_direct_untrusted()
        print("Caddy edge: TLS, headers, SSR, origin/CSRF, registry host, and login quota passed")

    def check_direct_untrusted(self):
        self.measure_direct_peer()
        headers = {"Host": self.app_domain, "Accept": "text/html"}
        status, response_headers, body = self.direct_request("GET", "/auth/login", headers)
        assert_contract(status == 200, "direct untrusted HTTP peer could not load login page")
        page = parse_page(body)
        cookies = [header.split(":", 1)[1].strip() for header in response_headers
                   if header.lower().startswith("set-cookie:")]
        cookie = "; ".join(value.split(";", 1)[0] for value in cookies)
        csrf = page.get("props", {}).get("csrf", {}).get("token")
        assert_contract(bool(cookie) and bool(csrf), "direct HTTP page did not establish CSRF session")
        marker = f"direct-peer-{secrets.token_hex(6)}@invalid.example"
        payload = urlencode({"email": marker, "password": secrets.token_urlsafe(18)})
        status, _, body = self.direct_request("POST", "/auth/login", {
            "Host": self.app_domain,
            "Origin": f"https://{self.app_domain}:{self.https_port}",
            "X-Forwarded-Proto": "https",
            "X-Forwarded-Host": self.app_domain,
            "X-Forwarded-Port": str(self.https_port),
            "X-Forwarded-For": "198.51.100.50",
            "CF-Connecting-IP": "192.0.2.50",
            "X-Real-IP": "192.0.2.50",
            "Cookie": cookie,
            "X-CSRF-Token": csrf,
            "Content-Type": "application/x-www-form-urlencoded",
        }, payload)
        assert_contract(status == 419, "untrusted direct HTTP peer used forged forwarded headers to pass HTTPS CSRF origin")
        print("Direct distinct untrusted peer: forged HTTPS forwarding rejected with 419")

    def measure_direct_peer(self):
        docker("run", "-d", "--name", self.probe, "--network", self.network,
               "--label", "packio.proxy.test=true", "--entrypoint", "sleep", APP_IMAGE, "30",
               label="start direct peer measurement")
        try:
            target = docker("inspect", "--format", "{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}", self.app,
                            label="inspect app address for direct peer").stdout.strip()
            script = r'''$socket = fsockopen($argv[1], 80, $errorCode, $errorMessage, 3);
if (false === $socket) { exit(2); }
fwrite($socket, "POST /auth/login HTTP/1.1\r\nHost: packio.localhost\r\nContent-Length: 1000000\r\nConnection: keep-alive\r\n\r\n");
sleep(8);
fclose($socket);'''
            docker("exec", "-d", self.probe, "php", "-r", script, target,
                   label="open direct app socket")
            deadline = time.monotonic() + 5
            direct_peer = None
            while time.monotonic() < deadline:
                try:
                    direct_peer = decode_peer_from_proc_tcp(self.app, 80, exclude=(self.trusted_peer,))
                    break
                except SetupError:
                    time.sleep(0.1)
            if direct_peer is None:
                raise SetupError("direct probe did not establish a measurable app socket")
            probe_ip = docker("inspect", "--format", "{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}", self.probe,
                               label="inspect direct probe address").stdout.strip()
            assert_contract(direct_peer == probe_ip, "direct app socket peer differs from the owned probe container")
            assert_contract(direct_peer != self.trusted_peer, "direct app peer unexpectedly matches trusted Caddy peer")
        finally:
            docker("rm", "-f", self.probe, label="remove direct peer measurement", check=False)

    def check_browser(self, endpoint, node):
        version = run((node, "--version"), label="check host Node version").stdout.strip()
        if version != "v24.21.0":
            raise SetupError("browser runner requires host Node 24.21.0")
        docker("rm", "-f", self.app, label="restart owned app with public Turnstile test keys")
        self.start_app(self.trusted_peer, turnstile=True)
        wait_container(self.app)
        wait_for("browser Packio application", self.app_healthy, 180)
        script = self.path / "browser-check.mjs"
        script.write_text(BROWSER_CHECK)
        result = run((node, str(script), endpoint, f"https://{self.app_domain}:{self.https_port}"),
                     label="real SSR/Turnstile browser contract", cwd=ROOT, check=False)
        if result.returncode:
            # This runner only GETs disposable auth pages and never prints tokens,
            # cookies, passwords, or page props. Its errors identify failed checks.
            print(result.stdout[-2500:])
            raise AssertionFailure("real SSR/hydration/Turnstile report-only browser contract failed")
        print(result.stdout.strip())

    def cleanup(self):
        for name in self.containers:
            docker("rm", "-f", name, label="remove owned fixture container", check=False)
        for volume in self.volumes:
            docker("volume", "rm", "-f", volume, label="remove owned fixture volume", check=False)
        docker("network", "rm", self.network, label="remove owned fixture network", check=False)
        self.temp.cleanup()


def baseline_red(fixture):
    status, headers, body = fixture.request(fixture.app_domain, "/auth/login")
    if status != 200:
        raise SetupError("unsafe Caddy baseline could not reach the running Packio app")
    try:
        assert_edge_headers(headers)
    except AssertionFailure as error:
        raise ExpectedRed(f"unsafe Caddy baseline failed after a real app response: {error}") from error
    raise AssertionFailure("unsafe Caddy baseline unexpectedly satisfied edge header contract")


def main():
    sys.stdout.reconfigure(line_buffering=True)
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--baseline", action="store_true", help="prove missing edge headers fail against an unsafe Caddy baseline")
    parser.add_argument("--browser-endpoint", help="approved matching Playwright 1.62.1 remote server")
    parser.add_argument("--node", default="node", help="existing host Node 24.21.0 executable for browser runner")
    args = parser.parse_args()
    fixture = Fixture(baseline=args.baseline)
    try:
        fixture.start()
        if args.baseline:
            baseline_red(fixture)
        else:
            fixture.run_contract()
            if args.browser_endpoint:
                fixture.check_browser(args.browser_endpoint, args.node)
    except ExpectedRed as error:
        print(f"RED confirmed: {error}")
        return 3
    except AssertionFailure as error:
        print(f"Assertion failed: {error}")
        return 1
    except SetupError as error:
        print(f"Setup failed: {error}")
        return 2
    finally:
        fixture.cleanup()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
