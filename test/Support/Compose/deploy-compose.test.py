#!/usr/bin/env python3
"""Check the user-facing Compose files in deploy/ (no images are pulled)."""
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import time
import unittest
import uuid

ROOT = Path(__file__).resolve().parents[3]
DOCKER = shutil.which("docker") is not None \
    and subprocess.run(["docker", "compose", "version"], capture_output=True).returncode == 0

LOCAL = "compose.yaml:compose.local.yaml"
LOCAL_SSR = LOCAL + ":compose.ssr.yaml"
SERVER = "compose.yaml"
SERVER_SSR = "compose.yaml:compose.ssr.yaml"
CADDY = "compose.yaml:compose.caddy.yaml"
CADDY_SSR = CADDY + ":compose.ssr.yaml"
CADDY_IMAGE = "caddy:2.11.2-alpine"
CADDY_ENV = ["-e", "PACKIO_DOMAIN=example.test", "-e", "PACKIO_REGISTRY_DOMAIN=example.test"]
SERVER_ENV = {
    "PACKIO_DOMAIN": "packio.example.com",
    "DATABASE_PASS": "db-pass",
    "APP_SECRET_ENCRYPTION_KEY": "base64:dGVzdA==",
    "MAIL_DSN": "smtp://mail.example.com:25",
}


@unittest.skipUnless(DOCKER, "docker compose is not available")
class DeployComposeTest(unittest.TestCase):
    def setUp(self):
        tmp = tempfile.TemporaryDirectory()
        self.addCleanup(tmp.cleanup)
        self.dir = Path(tmp.name) / "deploy"
        shutil.copytree(ROOT / "deploy", self.dir)

    def compose(self, compose_file, values=None, *args):
        """Run `docker compose config` with a fresh .env; values=None copies .env.local.example."""
        if values is None:
            shutil.copy(self.dir / ".env.local.example", self.dir / ".env")
        else:
            (self.dir / ".env").write_text(f"COMPOSE_FILE={compose_file}\n"
                                           + "".join(f"{k}={v}\n" for k, v in values.items()))
        env = {k: v for k, v in os.environ.items()
               if not k.startswith(("PACKIO_", "MAIL_", "COMPOSE_", "APP_", "DATABASE_", "HTTP_"))}
        env["COMPOSE_FILE"] = compose_file
        return subprocess.run(["docker", "compose", "config", *args], cwd=self.dir, env=env,
                              capture_output=True, text=True)

    def model(self, compose_file, values=None):
        result = self.compose(compose_file, values, "--format", "json")
        self.assertEqual(result.returncode, 0, result.stderr)
        return json.loads(result.stdout)["services"]

    def test_local_variants_are_valid(self):
        for files in (LOCAL, LOCAL_SSR):
            result = self.compose(files, None, "--quiet")
            self.assertEqual(result.returncode, 0, f"{files}: {result.stderr}")

    def test_server_variants_are_valid(self):
        for files in (SERVER, SERVER_SSR):
            result = self.compose(files, SERVER_ENV, "--quiet")
            self.assertEqual(result.returncode, 0, f"{files}: {result.stderr}")

    def test_missing_required_variable_is_named(self):
        for name in SERVER_ENV:
            result = self.compose(SERVER, {**SERVER_ENV, name: ""}, "--quiet")
            self.assertNotEqual(result.returncode, 0, name)
            self.assertIn(name, result.stderr)

    def test_domain_is_required_even_when_app_url_is_set(self):
        result = self.compose(SERVER, {**SERVER_ENV, "PACKIO_DOMAIN": "", "APP_URL": "https://x.example.com"}, "--quiet")
        self.assertNotEqual(result.returncode, 0)
        self.assertIn("PACKIO_DOMAIN", result.stderr)

    def test_compose_file_is_read_from_dotenv(self):
        shutil.copy(self.dir / ".env.local.example", self.dir / ".env")
        env = {k: v for k, v in os.environ.items() if not k.startswith(("PACKIO_", "MAIL_", "COMPOSE_"))}
        result = subprocess.run(["docker", "compose", "config", "--services"], cwd=self.dir, env=env,
                                capture_output=True, text=True)
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(set(result.stdout.split()), {"app", "workers", "postgres", "mailpit"})

    def test_ssr_is_off_by_default(self):
        services = self.model(SERVER, SERVER_ENV)
        self.assertEqual(services["app"]["environment"]["INERTIA_SSR_ENABLED"], "false")
        self.assertNotIn("ssr", services)

    def test_ssr_overlay_enables_ssr(self):
        app = self.model(SERVER_SSR, SERVER_ENV)["app"]
        self.assertEqual(app["environment"]["INERTIA_SSR_ENABLED"], "true")
        self.assertEqual(app["environment"]["INERTIA_SSR_URL"], "http://ssr:13714/render")
        self.assertEqual(app["depends_on"]["ssr"]["condition"], "service_healthy")

    def test_images_share_one_version_tag(self):
        services = self.model(SERVER_SSR, {**SERVER_ENV, "PACKIO_VERSION": "9.9.9"})
        self.assertEqual({n: services[n]["image"] for n in ("app", "workers", "ssr")}, {
            "app": "ghcr.io/packio-php/packio:9.9.9",
            "workers": "ghcr.io/packio-php/packio-worker:9.9.9",
            "ssr": "ghcr.io/packio-php/packio-ssr:9.9.9",
        })

    def test_version_defaults_to_latest(self):
        self.assertEqual(self.model(SERVER, SERVER_ENV)["app"]["image"], "ghcr.io/packio-php/packio:latest")

    def test_server_domain_drives_the_app_settings(self):
        env = self.model(SERVER, SERVER_ENV)["app"]["environment"]
        self.assertEqual(env["APP_URL"], "https://packio.example.com")
        self.assertEqual(env["BRAND_DOMAIN"], "packio.example.com")
        self.assertEqual(env["MAIL_FROM_ADDRESS"], "noreply@packio.example.com")
        self.assertEqual(env["APP_PRODUCTION"], "true")

    def test_local_variant_uses_http_and_mailpit(self):
        services = self.model(LOCAL)
        for name in ("app", "workers"):
            env = services[name]["environment"]
            self.assertEqual(env["APP_PRODUCTION"], "false", name)
            self.assertEqual(env["APP_URL"], "http://packio.localhost:8080", name)
            self.assertEqual(env["MAIL_DSN"], "smtp://mailpit:1025", name)
            self.assertEqual(env["BRAND_DOMAIN"], "packio.localhost", name)
        self.assertIn("mailpit", services)

    def test_nothing_is_built_and_http_stays_on_loopback(self):
        for files, values in ((SERVER, SERVER_ENV), (SERVER_SSR, SERVER_ENV), (LOCAL, None), (LOCAL_SSR, None)):
            services = self.model(files, values)
            for name, service in services.items():
                self.assertNotIn("build", service, f"{files}/{name}")
                for port in service.get("ports", []):
                    self.assertEqual(port["host_ip"], "127.0.0.1", f"{files}/{name}")
            self.assertEqual(services["app"]["ports"][0]["published"], "8080")

    def test_caddy_overlay_is_valid_and_trusts_only_the_proxy(self):
        for files in (CADDY, CADDY_SSR):
            result = self.compose(files, SERVER_ENV, "--quiet")
            self.assertEqual(result.returncode, 0, f"{files}: {result.stderr}")
            services = self.model(files, SERVER_ENV)
            self.assertEqual(services["app"]["environment"]["TRUSTED_PROXIES"], "172.30.255.2")
            self.assertEqual(set(services["caddy"]["networks"]), {"edge"})
            self.assertEqual(services["caddy"]["networks"]["edge"]["ipv4_address"], "172.30.255.2")
            self.assertEqual(services["caddy"]["environment"]["PACKIO_REGISTRY_DOMAIN"], "packio.example.com")

    def test_caddy_registry_domain_can_be_overridden(self):
        env = {**SERVER_ENV, "BRAND_REGISTRY_BASE_DOMAIN": "reg.example.com"}
        self.assertEqual(self.model(CADDY, env)["caddy"]["environment"]["PACKIO_REGISTRY_DOMAIN"], "reg.example.com")

    def test_caddy_acme_email_is_optional(self):
        env = lambda values: self.model(CADDY, {**SERVER_ENV, **values})["caddy"]["environment"]["ACME_EMAIL_OPTION"]
        self.assertEqual(env({}), "")
        self.assertEqual(env({"ACME_EMAIL": "ops@example.com"}), "email ops@example.com")

    def test_caddyfile_is_valid_with_and_without_acme_email(self):
        for extra in ([], ["-e", "ACME_EMAIL_OPTION=email ops@example.test"]):
            result = subprocess.run(["docker", "run", "--rm", *CADDY_ENV, *extra, "-v", f"{ROOT / 'deploy' / 'Caddyfile'}:/etc/caddy/Caddyfile:ro",
                                     CADDY_IMAGE, "caddy", "validate", "--config", "/etc/caddy/Caddyfile"],
                                    capture_output=True, text=True)
            self.assertEqual(result.returncode, 0, f"{extra}: {result.stderr}")

    def test_caddy_issues_certificates_only_for_registry_subdomains(self):
        name = "packio-caddy-test-" + uuid.uuid4().hex[:8]
        try:
            run = subprocess.run(["docker", "run", "-d", "--name", name, "--network", "none", *CADDY_ENV,
                                  "-v", f"{ROOT / 'deploy' / 'Caddyfile'}:/etc/caddy/Caddyfile:ro", CADDY_IMAGE],
                                 capture_output=True, text=True)
            self.assertEqual(run.returncode, 0, run.stderr)

            def status(domain):
                out = subprocess.run(["docker", "exec", name, "wget", "-S", "-O", "/dev/null",
                                      f"http://127.0.0.1:9123/?domain={domain}"], capture_output=True, text=True)
                for line in out.stderr.splitlines():
                    if line.strip().startswith("HTTP/"):
                        return line.split()[1]
                return "none"

            for _ in range(30):
                if status("acme.example.test") != "none":
                    break
                time.sleep(1)
            self.assertEqual(status("acme.example.test"), "200")
            for domain in ("example.test", "a.b.example.test", "evil.example",
                           "acme.example.test.evil.example", "acmeXexample.test", "-a.example.test", ""):
                self.assertNotEqual(status(domain), "200", domain)
        finally:
            subprocess.run(["docker", "rm", "-f", name], capture_output=True)


if __name__ == "__main__":
    unittest.main()
