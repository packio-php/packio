#!/usr/bin/env python3
"""Shared E1 fixture lifecycle and safe subprocess boundary."""

import hashlib
import base64
import importlib.util
import http.client
import http.cookies
import json
import os
from pathlib import Path
import re
import secrets
import shutil
import signal
import socket
import ssl
import subprocess
import tempfile
import threading
import time
from contextlib import contextmanager
from urllib.parse import urlencode
import uuid


ROOT = Path(__file__).resolve().parents[3]
RELEASE_DIR = ROOT / "test" / "Support" / "Release"
FIXTURES_DIR = RELEASE_DIR / "fixtures"
COMPOSE_PROD = ROOT / "compose.prod.yaml"
COMPOSE_PRODUCT = RELEASE_DIR / "compose.product.yaml"
IMAGE_HARNESS = RELEASE_DIR / "image.test.py"
DEFAULT_TIMEOUT = 600
SAFE_REPORT_KEYS = frozenset({"candidate_sha", "images", "results", "fixture_sha256", "duration_seconds",
                              "private_install", "source_snapshot", "image_provenance"})
IMAGE_ROLES = ("app", "worker", "ssr")
BROWSER_IMAGE = "mcr.microsoft.com/playwright:v1.62.1-noble"
BROWSER_PHASES = frozenset({"identity", "stopped-worker-control", "admin-and-org",
                            "failed-provider-source", "private-source", "resync", "tls-negative-control"})
BROWSER_STEPS = frozenset({"startup", "registration_ssr_response", "registration_form_fill",
                           "registration_submit", "registration_redirect", "registration_ssr",
                           "authentication_mail", "email_verification",
                           "ordinary_user_admin_denial", "stopped_worker_registration",
                           "stopped_worker_mail_detector", "admin_access_after_role_change",
                           "organization_creation", "organization_page_render", "organization_form_fill",
                           "organization_submit", "organization_redirect", "organization_props",
                           "provider_failure_source_creation",
                           "source_page_goto", "source_page_hydration", "add_source_click",
                           "generic_git_select", "repository_url_fill", "composer_json_path_fill",
                           "visibility_select", "connect_response_wait_click", "sources_route_wait",
                           "persisted_source_wait", "source_entry_check", "source_browser_errors_assert",
                           "source_browser_close",
                           "private_source_creation", "worker_recreation_resync", "browser_connect",
                           "worker_recreation_resync_http_status", "worker_recreation_resync_redirect",
                           "worker_recreation_resync_flash", "worker_recreation_resync_final_route",
                           "worker_recreation_resync_browser_errors", "browser_close",
                           "browser_tls_negative_control"})
BROWSER_RESYNC_DIAGNOSTIC_STEPS = {
    "worker_recreation_resync_http_status": "http_status",
    "worker_recreation_resync_redirect": "redirect_route_match",
    "worker_recreation_resync_flash": "flash_visible",
    "worker_recreation_resync_final_route": "final_route_match",
    "worker_recreation_resync_browser_errors": "browser_error_count",
}
BROWSER_RESYNC_DIAGNOSTIC_FIELDS = frozenset(BROWSER_RESYNC_DIAGNOSTIC_STEPS.values())
BROWSER_SOURCE_DIAGNOSTIC_STEPS = frozenset({
    "source_page_goto", "source_page_hydration", "add_source_click", "generic_git_select",
    "repository_url_fill", "composer_json_path_fill", "visibility_select",
    "connect_response_wait_click", "sources_route_wait", "persisted_source_wait",
    "source_entry_check", "source_browser_errors_assert", "source_browser_close",
})
BROWSER_SOURCE_DIAGNOSTIC_FIELDS = frozenset({
    "http_status", "sources_route_match", "form_invalid_control_present", "browser_error_count",
})
BROWSER_ERROR_TYPES = frozenset({"AssertionError", "Error", "MissingAuthMailError", "RangeError",
                                 "ReferenceError", "SyntaxError", "TimeoutError", "TypeError",
                                 "UnknownError"})


class FixtureError(RuntimeError):
    """A required E1 prerequisite failed or the fixture violated its contract."""


class FixtureCleanupError(FixtureError):
    """Owned fixture resources could not be completely removed."""

    def __init__(self, diagnostics, *, primary_message=None):
        if isinstance(diagnostics, str):
            diagnostics = (diagnostics,)
        self.diagnostics = tuple(diagnostics)
        self.primary_message = primary_message
        cleanup_message = "owned fixture cleanup failed: " + "; ".join(self.diagnostics)
        super().__init__(f"{primary_message}; {cleanup_message}" if primary_message else cleanup_message)


class Run:
    """Run bounded commands without retaining potentially secret output in errors."""

    def __init__(self, timeout=DEFAULT_TIMEOUT, *, start=None):
        self.deadline = (time.monotonic() if start is None else start) + timeout

    def remaining(self):
        remaining = self.deadline - time.monotonic()
        if remaining <= 0:
            raise FixtureError("fixture timed out")
        return remaining

    def command(self, args, *, label, cwd=None, env=None, input=None, check=True, timeout=None):
        budget = self.remaining()
        if timeout is not None:
            budget = min(budget, timeout)
        try:
            result = subprocess.run(args, cwd=cwd, env=env, input=input,
                                   stdout=subprocess.PIPE, stderr=subprocess.STDOUT,
                                   text=True, check=False, timeout=budget)
        except FileNotFoundError as error:
            raise FixtureError(f"{label} requires an available executable") from error
        except subprocess.TimeoutExpired as error:
            raise FixtureError(f"{label} timed out") from error
        if check and result.returncode:
            raise FixtureError(f"{label} failed (exit {result.returncode})")
        return result

    def require_tools(self):
        if not shutil.which("docker"):
            raise FixtureError("Docker is required for the product fixture")
        result = self.command(("docker", "compose", "version"), label="Docker Compose availability", check=False)
        if result.returncode:
            raise FixtureError("Docker Compose is required for the product fixture")
        result = self.command(("docker", "version", "--format", "{{.Server.Version}}"),
                              label="Docker daemon availability", check=False)
        if result.returncode or not result.stdout.strip():
            raise FixtureError("Docker daemon is required for the product fixture")


def _new_fixture_run(timeout=570):
    if isinstance(timeout, bool) or not isinstance(timeout, int) or not 1 <= timeout <= 2400:
        raise FixtureError("fixture timeout is invalid")
    return Run(timeout=timeout)


@contextmanager
def _request_deadline(run):
    """Interrupt a blocking HTTP operation at the shared fixture deadline."""
    if not hasattr(signal, "setitimer") or threading.current_thread() is not threading.main_thread():
        raise FixtureError("fixture HTTP deadline requires the main thread")
    remaining = run.remaining()
    previous_handler = signal.getsignal(signal.SIGALRM)
    previous_delay, previous_interval = signal.getitimer(signal.ITIMER_REAL)
    started = time.monotonic()

    def expired(_signum, _frame):
        raise FixtureError("fixture timed out")

    signal.signal(signal.SIGALRM, expired)
    delay = min(remaining, previous_delay) if previous_delay > 0 else remaining
    signal.setitimer(signal.ITIMER_REAL, delay)
    try:
        yield
    finally:
        elapsed = time.monotonic() - started
        signal.setitimer(signal.ITIMER_REAL, 0)
        signal.signal(signal.SIGALRM, previous_handler)
        if previous_delay > 0:
            signal.setitimer(signal.ITIMER_REAL, max(0.000001, previous_delay - elapsed), previous_interval)


def poll_until(run, label, probe, *, interval=0.5):
    """Poll a private probe until it returns a truthy value or the shared deadline expires."""
    while True:
        value = probe()
        if value:
            return value
        try:
            remaining = run.remaining()
        except FixtureError as error:
            raise FixtureError(f"{label} timed out") from error
        time.sleep(min(interval, remaining))


DATASET_KEYS = frozenset({"user_id", "organization_id", "source_id", "package_name",
                          "package_version", "registry_host", "token_path", "archive_sha256"})


def validate_dataset(context, dataset):
    """Reject incomplete IDs and ensure a reported archive digest matches fixture bytes."""
    if not isinstance(dataset, dict) or set(dataset) != DATASET_KEYS:
        raise FixtureError("product dataset is incomplete")
    for key in ("user_id", "organization_id", "source_id"):
        if not re.fullmatch(r"[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}", str(dataset[key])):
            raise FixtureError("product dataset contains an invalid resource ID")
    if dataset["package_name"] != "packio-fixture/private-package" or dataset["package_version"] != "1.0.0":
        raise FixtureError("product dataset package identity is invalid")
    if not re.fullmatch(r"[a-z0-9.-]+(?::[0-9]+)?", str(dataset["registry_host"])):
        raise FixtureError("product dataset registry host is invalid")
    private_dir = Path(context["private_dir"]).resolve()
    token_path = Path(dataset["token_path"]).resolve()
    if not token_path.is_relative_to(private_dir) or token_path.exists():
        raise FixtureError("product dataset token path is not a private future path")
    archive = Path(context.get("_stored_archive_path", ""))
    if not archive.is_relative_to(private_dir):
        raise FixtureError("stored package archive path is outside the private fixture directory")
    actual = hashlib.sha256(archive.read_bytes()).hexdigest() if archive.is_file() else ""
    if not re.fullmatch(r"[0-9a-f]{64}", str(dataset["archive_sha256"])) or actual != dataset["archive_sha256"]:
        raise FixtureError("stored package archive hash does not match the fixture archive")
    return dataset


class _LocalTLSConnection(http.client.HTTPSConnection):
    """Connect to the fixture port while retaining the requested Host and TLS SNI."""

    def connect(self):
        raw = socket.create_connection(("127.0.0.1", self.port), self.timeout)
        try:
            self.sock = self._context.wrap_socket(raw, server_hostname=self.host)
        except BaseException:
            raw.close()
            raise


def _fixture_request(context, host, path, *, headers=None, body=None, label):
    run = context["_run"]
    with _request_deadline(run):
        tls = ssl.create_default_context(cafile=str(Path(context["private_dir"]) / "root-ca.pem"))
        connection = _LocalTLSConnection(host, context["https_port"], timeout=min(20, run.remaining()), context=tls)
        try:
            connection.timeout = min(20, run.remaining())
            connection.request("GET" if body is None else "POST", path, body=body, headers=headers or {})
            response = connection.getresponse()
            body_parts = []
            size = 0
            while True:
                part = response.read1(65536)
                if not part:
                    break
                size += len(part)
                if size > 16 * 1024 * 1024:
                    raise FixtureError(f"{label} response is too large")
                body_parts.append(part)
            run.remaining()
            return response.status, response.getheaders(), b"".join(body_parts)
        except (OSError, ssl.SSLError, http.client.HTTPException) as error:
            raise FixtureError(f"{label} request failed") from error
        finally:
            connection.close()


def _session_cookies(private_dir):
    try:
        state = json.loads((Path(private_dir) / "verified-user.json").read_text())
        cookies = state["cookies"]
        result = {cookie["name"]: cookie["value"] for cookie in cookies
                  if cookie.get("domain", "").lstrip(".").endswith("fixture.test")
                  and cookie.get("secure") is True}
    except (OSError, KeyError, TypeError, json.JSONDecodeError) as error:
        raise FixtureError("private install session state is unavailable") from error
    if not result:
        raise FixtureError("private install session state is unavailable")
    return result


def _update_session_cookies(cookies, headers):
    for key, value in headers:
        if key.lower() != "set-cookie":
            continue
        parsed = http.cookies.SimpleCookie()
        try:
            parsed.load(value)
        except http.cookies.CookieError:
            continue
        for name, morsel in parsed.items():
            if morsel["max-age"] == "0" or not morsel.value:
                cookies.pop(name, None)
            else:
                cookies[name] = morsel.value


def fresh_login(context, email, password, expected_user_id):
    """Create a new authenticated session from restored credentials and persist only its cookies."""
    if (not isinstance(email, str) or "@" not in email or not isinstance(password, str) or not password
            or not re.fullmatch(r"[0-9a-f-]{36}", str(expected_user_id))):
        raise FixtureError("restored login identity is invalid")
    private = Path(context["private_dir"])
    cookies = {}
    _, csrf = _app_json(context, cookies, "/auth/login")
    _app_post(context, cookies, "/auth/login", {"email": email, "password": password}, csrf,
              "restored user login")
    props, _ = _app_json(context, cookies, "/organizations")
    user = props.get("auth", {}).get("user", {})
    if user.get("id") != expected_user_id:
        raise FixtureError("restored user login did not establish the expected account")
    state = {"cookies": [{"name": name, "value": value, "domain": "app.fixture.test",
                          "path": "/", "expires": -1, "httpOnly": True,
                          "secure": True, "sameSite": "Lax"}
                         for name, value in sorted(cookies.items())]}
    target = private / "verified-user.json"
    target.write_text(json.dumps(state, sort_keys=True, separators=(",", ":")))
    target.chmod(0o600)
    return True


def _app_json(context, cookies, path):
    request_headers = {
        "Host": f"app.fixture.test:{context['https_port']}",
        "Cookie": "; ".join(f"{name}={value}" for name, value in cookies.items()),
        "Accept": "application/json", "X-Inertia": "true",
    }
    version = context.get("_inertia_version")
    if version:
        request_headers["X-Inertia-Version"] = version
    status, headers, body = _fixture_request(context, "app.fixture.test", path, headers=request_headers,
                                             label="token session page")
    _update_session_cookies(cookies, headers)
    if status == 409 and not version:
        current_version = next((value for name, value in headers if name.lower() == "x-inertia-version"), "")
        has_location = any(name.lower() == "x-inertia-location" and value for name, value in headers)
        candidate_sha = context.get("candidate_sha", "")
        if (re.fullmatch(r"[0-9a-f]{7,40}", current_version)
                and re.fullmatch(r"[0-9a-f]{40}", candidate_sha)
                and candidate_sha.startswith(current_version) and has_location):
            context["_inertia_version"] = current_version
            retry_headers = {**request_headers, "X-Inertia-Version": current_version,
                             "Cookie": "; ".join(f"{name}={value}" for name, value in cookies.items())}
            status, headers, body = _fixture_request(context, "app.fixture.test", path,
                                                     headers=retry_headers, label="token session page")
            _update_session_cookies(cookies, headers)
    if status != 200:
        raise FixtureError("token session page failed")
    try:
        value = json.loads(body)
        props = value["props"]
        csrf = props["csrf"]["token"]
    except (KeyError, TypeError, json.JSONDecodeError) as error:
        raise FixtureError("token session page properties are invalid") from error
    if not isinstance(csrf, str) or not csrf:
        raise FixtureError("token session CSRF value is unavailable")
    return props, csrf


def _app_post(context, cookies, path, form, csrf, label):
    body = urlencode(form)
    status, headers, _ = _fixture_request(context, "app.fixture.test", path, headers={
        "Host": f"app.fixture.test:{context['https_port']}",
        "Cookie": "; ".join(f"{name}={value}" for name, value in cookies.items()),
        "Origin": context["app_url"], "Referer": context["app_url"] + path,
        "X-CSRF-Token": csrf, "Content-Type": "application/x-www-form-urlencoded",
        "Accept": "text/html",
    }, body=body, label=label)
    _update_session_cookies(cookies, headers)
    if status != 302:
        raise FixtureError(f"{label} failed")


def _session_registry_token(context, cookies, organization_slug, token_name):
    path = f"/organization/{organization_slug}/tokens"
    _, csrf = _app_json(context, cookies, path)
    _app_post(context, cookies, path, {"name": token_name}, csrf, "registry token issue")
    props, _ = _app_json(context, cookies, path)
    token = next((item for item in props.get("tokens", []) if item.get("name") == token_name), None)
    if (not isinstance(token, dict) or not re.fullmatch(r"[0-9a-f-]{36}", str(token.get("id", "")))
            or not isinstance(token.get("plain_token"), str) or not token["plain_token"]):
        raise FixtureError("registry token properties are unavailable")
    return token["id"], token["plain_token"]


def _registry_response(context, dataset, path, token=None):
    headers = {"Host": dataset["registry_host"]}
    if token:
        value = base64.b64encode(f"token:{token}".encode()).decode("ascii")
        headers["Authorization"] = f"Basic {value}"
    return _fixture_request(context, dataset["registry_host"].split(":", 1)[0], path,
                            headers=headers, label="private registry endpoint")


def _assert_private_denial(context, dataset, path, expected_status, token=None):
    status, _, body = _registry_response(context, dataset, path, token)
    if status != expected_status or b"PK\x03\x04" in body or dataset["package_name"].encode() in body:
        raise FixtureError("private registry denial contract failed")


def _composer_case(context, dataset, label, token=None, *, auth_host=None, expected_success):
    private = Path(context["private_dir"])
    case_dir = private / f"composer-{label}"
    case_dir.mkdir(mode=0o700)
    project = case_dir / "project"
    home = case_dir / "home"
    project.mkdir(mode=0o700)
    home.mkdir(mode=0o700)
    if (case_dir / "cache").exists():
        raise FixtureError(f"Composer {label} cache was not empty")
    host = dataset["registry_host"]
    repository = {"type": "composer", "url": f"https://{host}/private/packages.json"}
    manifest = {"name": "packio-fixture/install-check", "repositories": [repository, {"packagist.org": False}],
                "require": {dataset["package_name"]: dataset["package_version"]}, "minimum-stability": "stable"}
    (project / "composer.json").write_text(json.dumps(manifest, separators=(",", ":")))
    if token:
        key = auth_host or host
        auth = {"http-basic": {key: {"username": "token", "password": token}}}
        auth_path = home / "auth.json"
        auth_path.write_text(json.dumps(auth, separators=(",", ":")))
        auth_path.chmod(0o600)
        if auth_path.stat().st_mode & 0o777 != 0o600:
            raise FixtureError(f"Composer {label} auth file permissions are invalid")
    image = context["ancillary_images"]["composer"]
    name = f"{context['project']}-composer-{label}"
    script = ("set +e; printf 'PACKIO_CLIENT=started\\n'; "
              "composer install --no-interaction --prefer-dist --no-progress --no-ansi; "
              "composer_status=$?; printf 'PACKIO_COMPOSER_EXIT=%s\\n' \"$composer_status\"; "
              "if [ \"$composer_status\" -eq 0 ]; then "
              "if probe=$(php -r 'require \"vendor/autoload.php\"; echo \\PackioFixture\\Probe::value();'); "
              "then probe_status=0; else probe_status=$?; fi; "
              "printf 'PACKIO_PROBE_STATUS=%s\\n' \"$probe_status\"; "
              "printf 'PACKIO_PROBE_VALUE=%s\\n' \"$probe\"; fi; "
              "printf 'PACKIO_CLIENT=complete\\n'; exit 0")
    result = context["_run"].command(("docker", "run", "--rm", "--pull=never", "--name", name,
                                      "--user", f"{os.getuid()}:{os.getgid()}",
                                      "--label", f"com.docker.compose.project={context['project']}",
                                      "--network", "host", "--add-host",
                                      f"{host.split(':', 1)[0]}:127.0.0.1", "--volume", f"{case_dir}:/run/case",
                                      "--volume", f"{private / 'root-ca.pem'}:/run/fixture/root-ca.pem:ro",
                                      "--workdir", "/run/case/project", "--env", "COMPOSER_HOME=/run/case/home",
                                      "--env", "COMPOSER_CACHE_DIR=/run/case/cache",
                                      "--env", "COMPOSER_CAFILE=/run/fixture/root-ca.pem", "--entrypoint", "/bin/sh",
                                      image, "-ec", script), label=f"Composer {label} case", check=False)
    if result.returncode in {125, 126, 127} or result.returncode < 0 or result.returncode >= 128:
        raise FixtureError(f"Composer {label} Docker client failed")
    lines = result.stdout.splitlines()
    client_markers = [line for line in lines if line in {"PACKIO_CLIENT=started", "PACKIO_CLIENT=complete"}]
    composer_markers = [line for line in lines if re.fullmatch(r"PACKIO_COMPOSER_EXIT=(?:0|[1-9][0-9]{0,2})", line)]
    if (client_markers != ["PACKIO_CLIENT=started", "PACKIO_CLIENT=complete"]
            or len(composer_markers) != 1):
        raise FixtureError(f"Composer {label} Docker client failed")
    composer_status = int(composer_markers[0].split("=", 1)[1])
    if composer_status > 255 or composer_status in {125, 126, 127} or composer_status >= 128:
        raise FixtureError(f"Composer {label} client status is invalid")
    if not expected_success and composer_status == 0:
        raise FixtureError(f"Composer {label} unexpectedly installed a private package")
    if result.returncode != 0:
        raise FixtureError(f"Composer {label} Docker client failed")
    probe_statuses = [line for line in lines if re.fullmatch(r"PACKIO_PROBE_STATUS=(?:0|[1-9][0-9]{0,2})", line)]
    probe_values = [line.removeprefix("PACKIO_PROBE_VALUE=") for line in lines
                    if line.startswith("PACKIO_PROBE_VALUE=")]
    if expected_success:
        if composer_status != 0:
            raise FixtureError(f"Composer {label} install failed")
        if probe_statuses != ["PACKIO_PROBE_STATUS=0"] or probe_values != ["fixture-1.0.0"]:
            raise FixtureError(f"Composer {label} package probe failed")
        try:
            lock = json.loads((project / "composer.lock").read_text())
            package = next(item for item in lock["packages"] if item["name"] == dataset["package_name"])
            reference = package["source"]["reference"]
            version = package["version"]
            dist_url = package["dist"]["url"]
            dist_reference = package["dist"]["reference"]
            installed = json.loads((project / "vendor/composer/installed.json").read_text())
            installed_package = next(item for item in installed["packages"] if item["name"] == dataset["package_name"])
        except (OSError, KeyError, StopIteration, TypeError, json.JSONDecodeError) as error:
            raise FixtureError(f"Composer {label} source reference is unavailable") from error
        if (version != dataset["package_version"] or reference != context["source_reference"]
                or dist_reference != context["source_reference"] or dist_url != context["dist_url"]
                or installed_package.get("version") != dataset["package_version"]
                or installed_package.get("installation-source") != "dist"):
            raise FixtureError(f"Composer {label} lock metadata does not match the fixture tag")
        members = {relative: hashlib.sha256((project / "vendor/packio-fixture/private-package" / relative).read_bytes()).hexdigest()
                   for relative in ("composer.json", "src/Probe.php")}
        if members != context["source_member_sha256"]:
            raise FixtureError(f"Composer {label} installed file hashes do not match fixture source")
    else:
        if probe_statuses or probe_values:
            raise FixtureError(f"Composer {label} client status is invalid")
        expected_http = "403" if label == "foreign-org" else "401"
        expected_url = f"https://{host}/private/packages.json"
        if (expected_url not in result.stdout
                or not re.search(rf"HTTP(?:/\d(?:\.\d)?)?\s+{expected_http}(?:\D|$)", result.stdout,
                                 re.IGNORECASE)):
            raise FixtureError(f"Composer {label} auth refusal was not proven")


def verify_private_install(context, dataset):
    """Verify supported token controls, direct private bytes, and isolated Composer clients."""
    dataset = validate_dataset(context, dataset)
    run = context.get("_run")
    if not isinstance(run, Run) or not re.fullmatch(r"packio-e1-[0-9a-f]{32}", context.get("project", "")):
        raise FixtureError("private install fixture context is invalid")
    private = Path(context["private_dir"])
    source = FIXTURES_DIR / "private-package"
    members = {relative: hashlib.sha256((source / relative).read_bytes()).hexdigest()
               for relative in ("composer.json", "src/Probe.php")}
    aggregate = hashlib.sha256("".join(f"{name}:{members[name]}\n" for name in sorted(members)).encode()).hexdigest()
    reference = run.command(("git", "rev-parse", "refs/tags/1.0.0^{}"), label="resolve fixture source tag",
                            cwd=private / "working-package", env=_fixture_git_environment()).stdout.strip()
    if not re.fullmatch(r"[0-9a-f]{40}", reference):
        raise FixtureError("fixture source tag reference is invalid")
    context["source_reference"] = reference
    context["source_member_sha256"] = members

    cookies = _session_cookies(private)
    primary_slug = dataset["registry_host"].split(".", 1)[0]
    foreign_org_id = None
    _, csrf = _app_json(context, cookies, "/organizations")
    foreign_name = f"E3 tenant {uuid.uuid4().hex}"
    _app_post(context, cookies, "/organizations", {"name": foreign_name}, csrf, "foreign organization create")
    try:
        org_props, _ = _app_json(context, cookies, "/organizations")
        foreign = next(item for item in org_props.get("organizations", []) if item.get("name") == foreign_name)
        foreign_org_id, foreign_slug = foreign["id"], foreign["slug"]
    except (KeyError, StopIteration, TypeError) as error:
        raise FixtureError("foreign organization properties are unavailable") from error
    if foreign_org_id == dataset["organization_id"] or not re.fullmatch(r"[0-9a-f-]{36}", foreign_org_id):
        raise FixtureError("foreign organization identity is invalid")

    suffix = uuid.uuid4().hex[:12]
    token_id, token = _session_registry_token(context, cookies, primary_slug, f"E3 install {suffix}")
    _, foreign_token = _session_registry_token(context, cookies, foreign_slug, f"E3 foreign {suffix}")
    meta_path = f"/p2/{dataset['package_name']}"
    status, _, body = _registry_response(context, dataset, meta_path, token)
    try:
        metadata = json.loads(body)
        release = metadata["packages"][dataset["package_name"]][0]
        dist_url = release["dist"]["url"]
    except (KeyError, IndexError, TypeError, json.JSONDecodeError) as error:
        raise FixtureError("private registry metadata properties are invalid") from error
    if status != 200 or release.get("version") != dataset["package_version"] or not isinstance(dist_url, str):
        raise FixtureError("private registry metadata failed")
    status, _, body = _registry_response(context, dataset, "/private/packages.json", token)
    try:
        available = json.loads(body)["available-packages"]
    except (KeyError, TypeError, json.JSONDecodeError) as error:
        raise FixtureError("private registry package list properties are invalid") from error
    if status != 200 or dataset["package_name"] not in available:
        raise FixtureError("private registry package list failed")
    expected_prefix = f"https://{dataset['registry_host']}/"
    if not dist_url.startswith(expected_prefix):
        raise FixtureError("private registry dist host does not match the organization")
    dist_path = "/" + dist_url.split("/", 3)[3]
    context["dist_url"] = dist_url
    status, _, archive = _registry_response(context, dataset, dist_path, token)
    actual_archive_hash = hashlib.sha256(archive).hexdigest()
    if status != 200 or actual_archive_hash != dataset["archive_sha256"] or actual_archive_hash != context["fixture_sha256"]:
        raise FixtureError("private registry dist bytes do not match the fixture archive")

    _assert_private_denial(context, dataset, meta_path, 401)
    _assert_private_denial(context, dataset, dist_path, 401)
    _assert_private_denial(context, dataset, meta_path, 403, foreign_token)
    _assert_private_denial(context, dataset, dist_path, 403, foreign_token)

    for label, auth in (("install", token), ("repeat_install", token)):
        _composer_case(context, dataset, label, auth, expected_success=True)
    _composer_case(context, dataset, "anonymous", expected_success=False)
    _composer_case(context, dataset, "foreign-org", foreign_token, expected_success=False)

    _, csrf = _app_json(context, cookies, f"/organization/{primary_slug}/tokens")
    _app_post(context, cookies, f"/organization/{primary_slug}/tokens/{token_id}/revoke", {}, csrf,
              "registry token revoke")
    _assert_private_denial(context, dataset, meta_path, 401, token)
    _assert_private_denial(context, dataset, dist_path, 401, token)
    _composer_case(context, dataset, "revoked", token, expected_success=False)
    valid_wrong_host_token_id, valid_wrong_host_token = _session_registry_token(
        context, cookies, primary_slug, f"E3 hostname {uuid.uuid4().hex[:12]}")
    for path in (meta_path, dist_path):
        status, _, body = _registry_response(context, dataset, path, valid_wrong_host_token)
        if status != 200 or (path == dist_path and hashlib.sha256(body).hexdigest() != dataset["archive_sha256"]):
            raise FixtureError("wrong-auth-host control token is not valid")
    _composer_case(context, dataset, "wrong-auth-host", valid_wrong_host_token, auth_host="wrong-auth.fixture.test",
                   expected_success=False)
    context["_recovery_registry_token"] = {
        "id": valid_wrong_host_token_id,
        "value": valid_wrong_host_token,
        "organization_slug": primary_slug,
    }

    result = {
        "organization_id": dataset["organization_id"], "source_id": dataset["source_id"],
        "source_reference": reference, "source_sha256": aggregate,
        "archive_sha256": dataset["archive_sha256"], "member_sha256": members,
        "results": {key: "passed" for key in
                    ("install", "repeat_install", "anonymous", "foreign_org", "revoked", "wrong_auth_host")},
    }
    context.setdefault("results", {})["private_install"] = "passed"
    context["private_install"] = result
    return result

def validate_candidate_sha(expected_sha, actual_sha):
    if not re.fullmatch(r"[0-9a-f]{40}", expected_sha or ""):
        raise FixtureError("expected source SHA must be a full lowercase Git SHA")
    if actual_sha != expected_sha:
        raise FixtureError("image source SHA does not match the requested candidate")


def validate_image_metadata(metadata, expected_sha, image):
    harness = _image_harness()
    try:
        harness.check_metadata(metadata, expected_sha, image)
    except AssertionError as error:
        raise FixtureError(f"{image} image metadata does not match the requested candidate") from error


def validate_o1_test_snapshot(test_snapshot, expected_sha, image_refs):
    """Bind a disposable dirty O1 image set to its immutable build evidence and runtime tree."""
    return _validate_test_snapshot(test_snapshot, expected_sha, image_refs,
                                   "disposable_local_O1_test_snapshot", include_r1_notices=False)


def validate_r1_test_snapshot(test_snapshot, expected_sha, image_refs):
    """Bind R1's dirty test images to the exact source snapshot and notice files."""
    return _validate_test_snapshot(test_snapshot, expected_sha, image_refs,
                                   "disposable_local_R1_test_snapshot", include_r1_notices=True)


def _validate_test_snapshot(test_snapshot, expected_sha, image_refs, expected_kind, *, include_r1_notices):
    try:
        if not isinstance(test_snapshot, dict) or set(test_snapshot) != {"identity", "build"}:
            raise ValueError
        identity_path = Path(test_snapshot["identity"]).resolve(strict=True)
        build_path = Path(test_snapshot["build"]).resolve(strict=True)
        identity = json.loads(identity_path.read_text())
        build = json.loads(build_path.read_text())
        if build.get("kind") != expected_kind or build.get("identity") != identity_path.name:
            raise ValueError
        base = build["base_head"]
        if (not re.fullmatch(r"[0-9a-f]{40}", base) or expected_sha != base
                or identity.get("base_head") != base):
            raise ValueError
        current_head = subprocess.run(("git", "rev-parse", "HEAD"), cwd=ROOT, check=True,
                                      capture_output=True, text=True).stdout.strip()
        if current_head != base:
            raise ValueError
        patch_path = (identity_path.parent / identity["patch"]).resolve(strict=True)
        if not patch_path.is_relative_to(identity_path.parent):
            raise ValueError
        patch_bytes = patch_path.read_bytes()
        patch_hash = hashlib.sha256(patch_bytes).hexdigest()
        if (patch_hash != identity.get("patch_sha256") or patch_hash != build.get("patch_sha256")
                or patch_hash != identity.get("full_diff_sha256")):
            raise ValueError
        files = identity["files"]
        if not isinstance(files, dict) or not files:
            raise ValueError
        manifest_paths = set()
        for relative, digest in files.items():
            path = Path(relative)
            if (path.is_absolute() or ".." in path.parts or not relative or "\\" in relative
                    or not re.fullmatch(r"[0-9a-f]{64}", str(digest))):
                raise ValueError
            manifest_paths.add(relative)
        numstat = subprocess.run(("git", "apply", "--numstat", "-z", str(patch_path)), cwd=ROOT,
                                 check=True, capture_output=True).stdout
        patch_paths = set()
        for entry in numstat.split(b"\0"):
            if not entry:
                continue
            _, _, relative = entry.split(b"\t", 2)
            patch_paths.add(relative.decode("utf-8"))
        if patch_paths != manifest_paths:
            raise ValueError

        context_root = Path(build["context"]).resolve(strict=True)
        if not context_root.is_dir():
            raise ValueError
        for relative, digest in files.items():
            frozen = context_root / relative
            frozen_resolved = frozen.resolve(strict=True)
            if (not frozen_resolved.is_relative_to(context_root) or not frozen_resolved.is_file()
                    or hashlib.sha256(frozen_resolved.read_bytes()).hexdigest() != digest):
                raise ValueError

        listed = subprocess.run(("git", "ls-files", "--cached", "--others", "--exclude-standard", "-z"),
                                cwd=ROOT, check=True, capture_output=True).stdout
        runtime_paths = set()
        top_files = {".dockerignore", "Dockerfile", "Dockerfile.prod", "compose.prod.yaml", "composer.json",
                     "composer.lock", "package.json", "package-lock.json", "vite.config.js", "vite.config.ts",
                     "vite.config.mjs", "svelte.config.js", "svelte.config.ts", "svelte.config.mjs",
                     "tsconfig.json", "postcss.config.js", "postcss.config.cjs", "postcss.config.mjs",
                     "tailwind.config.js", "tailwind.config.cjs", "tailwind.config.mjs"}
        if include_r1_notices:
            top_files.update(("LICENSE", "THIRD_PARTY_NOTICES.md", "third-party-sources.json"))
        runtime_roots = ("src/", "config/", "bin/", "templates/", "resources/", ".docker/", "public/",
                         "data/db/cycle/")
        for raw in listed.split(b"\0"):
            if not raw:
                continue
            relative = raw.decode("utf-8")
            if relative in top_files or relative.startswith(runtime_roots):
                current = ROOT / relative
                if current.is_symlink():
                    raise ValueError
                resolved = current.resolve(strict=True)
                if not resolved.is_relative_to(ROOT) or not resolved.is_file():
                    raise ValueError
                frozen = context_root / relative
                frozen_resolved = frozen.resolve(strict=True)
                if not frozen_resolved.is_relative_to(context_root) or not frozen_resolved.is_file():
                    raise ValueError
                current_hash = hashlib.sha256(resolved.read_bytes()).hexdigest()
                frozen_hash = hashlib.sha256(frozen_resolved.read_bytes()).hexdigest()
                if current_hash != frozen_hash:
                    raise ValueError
                runtime_paths.add((relative, current_hash))
        if include_r1_notices and not {"LICENSE", "THIRD_PARTY_NOTICES.md", "third-party-sources.json"}.issubset(
                {path for path, _ in runtime_paths}):
            raise ValueError
        if not runtime_paths:
            raise ValueError
        runtime_fingerprint = hashlib.sha256(b"".join(
            path.encode() + b"\0" + digest.encode() + b"\n"
            for path, digest in sorted(runtime_paths)
        )).hexdigest()

        roles = ("app", "worker", "ssr")
        if set(build.get("images", {})) != set(roles) or set(build.get("targets", {})) != set(roles):
            raise ValueError
        expected_targets = {"app": "prod", "worker": "prod-worker", "ssr": "prod-ssr"}
        images = build["images"]
        tags = build["tags"]
        if set(image_refs) != set(roles) or set(tags) != set(roles):
            raise ValueError
        for role in roles:
            if (not re.fullmatch(r"sha256:[0-9a-f]{64}", images[role])
                    or build["targets"][role] != expected_targets[role]
                    or image_refs[role] != images[role]
                    or not isinstance(tags[role], str) or not tags[role]
                    or build["builds"][role].get("exit") != 0):
                raise ValueError
        return {"kind": expected_kind, "dirty": True, "base_head": base, "patch_sha256": patch_hash, "images": images,
                "runtime_fingerprint": runtime_fingerprint, "runtime_file_count": len(runtime_paths)}
    except (KeyError, OSError, ValueError, TypeError, json.JSONDecodeError,
            subprocess.SubprocessError, UnicodeDecodeError) as error:
        label = "O1 test snapshot" if expected_kind == "disposable_local_O1_test_snapshot" else "R1 test snapshot"
        raise FixtureError(f"{label} evidence or runtime binding is invalid") from error


def require_service(service, state):
    if state not in ("running", "healthy"):
        raise FixtureError(f"required {service} service did not become ready")


def require_services(states):
    for service in ("app", "workers", "ssr", "postgres", "mailpit", "provider", "tls"):
        require_service(service, states.get(service))


def assert_safe_mounts(mounts, *, source_root, allowed_roots):
    """Allow only explicitly named test fixture/config and private-run mounts."""
    source_root = Path(source_root).resolve()
    allowed = tuple(Path(path).resolve() for path in allowed_roots)
    for mount in mounts:
        if mount.get("type") != "bind":
            continue
        source = Path(mount.get("source", "")).resolve()
        if source == source_root or source_root in source.parents:
            if not any(source == root or root in source.parents for root in allowed):
                raise FixtureError("application source mount is forbidden")
        elif not any(source == root or root in source.parents for root in allowed):
            raise FixtureError("fixture bind mount is outside the approved fixture/config paths")
    return True


def public_report(context):
    """Construct a report from a fixed allowlist; never serialize private context."""
    report = {key: context[key] for key in SAFE_REPORT_KEYS if key in context}
    if "candidate_sha" in report and not re.fullmatch(r"[0-9a-f]{40}", report["candidate_sha"]):
        raise FixtureError("public report candidate SHA is invalid")
    if "fixture_sha256" in report and not re.fullmatch(r"[0-9a-f]{64}", report["fixture_sha256"]):
        raise FixtureError("public report fixture digest is invalid")
    images = report.get("images", {})
    if not isinstance(images, dict):
        raise FixtureError("public report image IDs are invalid")
    for role, image_id in images.items():
        if role not in {"app", "worker", "ssr", "caddy", "composer", "mailpit", "postgres"} or not re.fullmatch(r"sha256:[0-9a-f]{64}", image_id or ""):
            raise FixtureError("public report image IDs are invalid")
    snapshot = report.get("source_snapshot")
    if snapshot is not None:
        expected = {"kind", "dirty", "base_head", "patch_sha256", "runtime_fingerprint",
                    "runtime_file_count", "images"}
        if (not isinstance(snapshot, dict) or set(snapshot) != expected
                or snapshot.get("kind") != "disposable_local_R1_test_snapshot"
                or snapshot.get("dirty") is not True
                or snapshot.get("base_head") != report.get("candidate_sha")
                or not re.fullmatch(r"[0-9a-f]{64}", str(snapshot.get("patch_sha256")))
                or not re.fullmatch(r"[0-9a-f]{64}", str(snapshot.get("runtime_fingerprint")))
                or not isinstance(snapshot.get("runtime_file_count"), int)
                or snapshot["runtime_file_count"] < 1
                or snapshot.get("images") != {key: value for key, value in images.items()
                                               if key in IMAGE_ROLES}):
            raise FixtureError("public report source snapshot is invalid")
        provenance = report.get("image_provenance")
        if not isinstance(provenance, dict) or set(provenance) != set(IMAGE_ROLES):
            raise FixtureError("public report image provenance is invalid")
        for role in IMAGE_ROLES:
            item = provenance[role]
            if (not isinstance(item, dict) or item.get("id") != snapshot["images"][role]
                    or item.get("source_sha") != snapshot["base_head"]
                    or not re.fullmatch(r"sha256:[0-9a-f]{64}", str(item.get("runtime_manifest_digest")))
                    or not isinstance(item.get("base_digests"), list)
                    or any(not re.fullmatch(r"sha256:[0-9a-f]{64}", str(value))
                           for value in item["base_digests"])):
                raise FixtureError("public report image provenance is invalid")
    elif "image_provenance" in report:
        raise FixtureError("public report image provenance requires an explicit R1 source snapshot")
    results = report.get("results", {})
    allowed_results = {"passed", "failed", "fresh_project_volumes"}
    allowed_scenarios = {"startup", "fixture", "candidate_images", "services", "empty_state",
                         "migrations_seed", "tls_trust", "browser_tls_control", "generic_git", "composer_client",
                         "registration_mail", "email_verification", "admin_transition", "organization_source",
                         "stopped_worker_control", "provider_failure_control", "private_archive", "worker_recreation",
                         "private_install"}
    if (not isinstance(results, dict) or any(key not in allowed_scenarios for key in results)
            or any(value not in allowed_results for value in results.values())):
        raise FixtureError("public report results are invalid")
    duration = report.get("duration_seconds")
    if duration is not None and (not isinstance(duration, (int, float)) or duration < 0):
        raise FixtureError("public report duration is invalid")
    private_install = report.get("private_install")
    if private_install is not None:
        expected = {"organization_id", "source_id", "source_reference", "source_sha256", "archive_sha256",
                    "member_sha256", "results"}
        if not isinstance(private_install, dict) or set(private_install) != expected:
            raise FixtureError("private install report is invalid")
        for key in ("organization_id", "source_id"):
            if not re.fullmatch(r"[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}", str(private_install[key])):
                raise FixtureError("private install report is invalid")
        for key, pattern in (("source_reference", r"[0-9a-f]{40}"), ("source_sha256", r"[0-9a-f]{64}"),
                             ("archive_sha256", r"[0-9a-f]{64}")):
            if not re.fullmatch(pattern, str(private_install[key])):
                raise FixtureError("private install report is invalid")
        members = private_install["member_sha256"]
        if (not isinstance(members, dict) or set(members) != {"composer.json", "src/Probe.php"}
                or any(not re.fullmatch(r"[0-9a-f]{64}", str(value)) for value in members.values())):
            raise FixtureError("private install report is invalid")
        scenarios = {"install", "repeat_install", "anonymous", "foreign_org", "revoked", "wrong_auth_host"}
        if (not isinstance(private_install["results"], dict) or set(private_install["results"]) != scenarios
                or any(value not in {"passed", "failed"} for value in private_install["results"].values())):
            raise FixtureError("private install report is invalid")
    return report



def stop_fixture(context):
    """Remove exact owned IDs, within the original run's cleanup budget."""
    project = context.get("project", "")
    if not re.fullmatch(r"packio-e1-[0-9a-f]{32}", project):
        raise FixtureError("invalid fixture ownership identity")
    cleanup_deadline = min(time.monotonic() + 30, context.get("_hard_deadline", float("inf")))
    failures = []

    def operation(args):
        if args[:2] == ("ps", "-aq"):
            return "docker ps -aq"
        if args[0] == "inspect":
            return "docker inspect"
        if args[0] in {"volume", "network"}:
            return f"docker {args[0]} {args[1]}"
        if args[:2] == ("rm", "-f"):
            return "docker rm -f"
        return "docker cleanup"

    def stderr_category(stderr):
        value = (stderr or "").lower()
        if "permission denied" in value:
            return "permission_denied"
        if "cannot connect to the docker daemon" in value or "is the docker daemon running" in value:
            return "daemon_unavailable"
        if "resource is still in use" in value or "has active endpoints" in value:
            return "resource_in_use"
        if "no such" in value or "not found" in value:
            return "resource_missing"
        return "unknown"

    def failed(args, reason):
        if len(failures) < 8:
            failures.append(f"{operation(args)}: {reason}")

    def command(args, *, listing=False):
        remaining = cleanup_deadline - time.monotonic()
        if remaining <= 0:
            failed(args, "timeout")
            return None
        try:
            # Removing several writable layers can outlast one inspection; keep the shared deadline.
            timeout = remaining if args[:2] == ("rm", "-f") else min(5, remaining)
            result = subprocess.run(("docker", *args), stdout=subprocess.PIPE, stderr=subprocess.PIPE,
                                    text=True, check=False, timeout=timeout)
            if result.returncode:
                failed(args, f"exit {result.returncode} (stderr: {stderr_category(result.stderr)})")
                return None
            if listing:
                return result.stdout.splitlines()
            return json.loads(result.stdout) if result.stdout.strip() else None
        except subprocess.TimeoutExpired:
            failed(args, "timeout")
            return None
        except json.JSONDecodeError:
            failed(args, "invalid JSON")
            return None
        except OSError:
            failed(args, "unavailable")
            return None

    try:
        if context.get("_resources_started", True):
            ids = command(("ps", "-aq", "--filter", f"label=com.docker.compose.project={project}"), listing=True) or []
            owned = []
            for resource_id in ids:
                if not re.fullmatch(r"[0-9a-f]{12,64}", resource_id):
                    continue
                details = command(("inspect", resource_id))
                if details and len(details) == 1 and details[0].get("Config", {}).get("Labels", {}).get("com.docker.compose.project") == project:
                    owned.append(details[0]["Id"])
            if owned:
                command(("rm", "-f", *owned), listing=True)
            for kind in ("volume", "network"):
                ids = command((kind, "ls", "-q", "--filter", f"label=com.docker.compose.project={project}"), listing=True) or []
                owned = []
                for resource_id in ids:
                    details = command((kind, "inspect", resource_id))
                    if not details or len(details) != 1:
                        continue
                    labels = details[0].get("Labels", {})
                    if labels.get("com.docker.compose.project") == project and (
                        kind != "volume" or labels.get("com.docker.compose.volume") in {
                            "postgres-data", "sessions", "rate-limits", "package-archives"
                        }
                    ):
                        owned.append(details[0].get("Id") if kind == "network" else details[0]["Name"])
                if owned:
                    command((kind, "rm", *owned), listing=True)
    finally:
        private_dir = context.get("private_dir")
        if private_dir:
            try:
                shutil.rmtree(private_dir)
            except OSError:
                if len(failures) < 8:
                    failures.append("private directory cleanup: unavailable")
            else:
                context["private_dir"] = None
    if failures:
        raise FixtureCleanupError(failures)


def _compose(project, *args):
    return ("docker", "compose", "--project-name", project,
            "--file", str(COMPOSE_PROD), "--file", str(COMPOSE_PRODUCT), *args)


def prepare_dataset(context):
    """Exercise registration, mail verification, admin transition and real source jobs."""
    run = context["_run"]
    private = Path(context["private_dir"])
    browser_image = run.command(("docker", "image", "inspect", "--format", "{{.Id}}", BROWSER_IMAGE),
                                label="inspect matching Playwright browser image", check=False)
    if browser_image.returncode or not re.fullmatch(r"sha256:[0-9a-f]{64}", browser_image.stdout.strip()):
        raise FixtureError("matching Playwright 1.62.1 browser image is required")
    if not shutil.which("node") or not re.match(r"v24\.21\.0$", run.command(("node", "--version"), label="check Node version").stdout.strip()):
        raise FixtureError("Node 24.21.0 is required for product browser flow")
    module_path = ROOT / "node_modules" / "playwright"
    if not module_path.is_dir():
        raise FixtureError("installed Playwright 1.62.1 package is required")
    script = RELEASE_DIR / "product-browser.spec.ts"
    if not script.is_file():
        raise FixtureError("product browser scenario is missing")
    parser_test = run.command(("node", str(script), "--mail-parser-test"),
                              label="check synthetic auth mail parser", cwd=ROOT)
    if parser_test.stdout.strip() != "PASS: synthetic auth mail parser cases":
        raise FixtureError("synthetic auth mail parser self-check failed")
    browser_image_id = browser_image.stdout.strip()
    browser_env = {**os.environ,
                   "PRODUCT_APP_URL": context["app_url"], "PRODUCT_MAILPIT_URL": context["mailpit_url"],
                   "PRODUCT_PRIVATE_DIR": str(private), "PRODUCT_PROVIDER_URL": context["provider_url"],
                   }

    def start_browser(*, trusted):
        suffix = "trusted" if trusted else "untrusted"
        name = f"{context['project']}-browser-{suffix}"
        port = _free_port()
        args = ["docker", "run", "--detach", "--init", "--user", "pwuser", "--name", name,
                "--label", f"com.docker.compose.project={context['project']}", "--network", "host",
                "--add-host", "app.fixture.test:127.0.0.1",
                "--volume", f"{ROOT / 'node_modules'}:/runner/node_modules:ro"]
        if trusted:
            args.extend(("--volume", f"{private / 'browser-policies.json'}:/run/fixture/browser-policies.json:ro",
                         "--volume", f"{private / 'root-ca.pem'}:/run/fixture/root-ca.pem:ro",
                         "--env", "PLAYWRIGHT_FIREFOX_POLICIES_JSON=/run/fixture/browser-policies.json"))
        args.extend(("--workdir", "/runner", browser_image_id, "node", "/runner/node_modules/playwright/cli.js",
                     "run-server", "--host", "127.0.0.1", "--port", str(port)))
        browser_id = run.command(tuple(args), label=f"start owned {suffix} Firefox browser").stdout.strip()
        if not re.fullmatch(r"[0-9a-f]{12,64}", browser_id):
            raise FixtureError(f"owned {suffix} Firefox browser did not start")
        context["browser_id"] = browser_id
        context["browser_endpoint"] = f"ws://127.0.0.1:{port}/"
        browser_env["PRODUCT_BROWSER_ENDPOINT"] = context["browser_endpoint"]
        poll_until(run, f"{suffix} Firefox browser readiness", lambda: _browser_ready(port), interval=0.2)

    def remove_browser():
        browser_id = context.get("browser_id")
        if not isinstance(browser_id, str) or not re.fullmatch(r"[0-9a-f]{12,64}", browser_id):
            raise FixtureError("owned Firefox browser identity is invalid")
        run.command(("docker", "rm", "--force", browser_id), label="remove exact owned untrusted Firefox browser")
        context.pop("browser_id", None)

    def browser(phase):
        if phase not in BROWSER_PHASES:
            raise FixtureError("unknown product browser phase")
        failure_path = private / "browser-failure.json"
        failure_path.unlink(missing_ok=True)
        try:
            run.command(("node", str(script), phase), label=f"product browser {phase}", cwd=ROOT, env=browser_env)
        except FixtureError:
            diagnostic = _read_browser_failure(failure_path, phase)
            if diagnostic is None:
                raise
            step, error_type, *safe_values = diagnostic
            detail = f" {json.dumps(safe_values[0], sort_keys=True)}" if safe_values else ""
            raise FixtureError(f"product browser {phase} failed at {step} ({error_type}){detail}") from None

    start_browser(trusted=False)
    browser("tls-negative-control")
    context.setdefault("results", {})["browser_tls_control"] = "passed"
    remove_browser()
    start_browser(trusted=True)

    # The first account proves the positive worker->Mailpit path and verification UI.
    pending_auth_job = _latest_job(context, run)
    browser("identity")
    auth_job = poll_until(run, "completed authentication mail job",
                          lambda: _completed_auth_job(context, run), interval=0.5)
    if pending_auth_job and auth_job[0] == pending_auth_job[0]:
        raise FixtureError("authentication mail job did not complete for registration")
    state_path = private / "flow-state.json"
    try:
        flow = json.loads(state_path.read_text())
        user_email = flow["email"]
    except (OSError, KeyError, json.JSONDecodeError) as error:
        raise FixtureError("product browser identity state is incomplete") from error

    # A second real registration while the owned worker is stopped must time out without mail.
    run.command(_compose(context["project"], "stop", "workers"), label="stop owned worker for mail negative control", cwd=ROOT, env=context["_compose_env"])
    pending_auth_job = auth_job
    browser("stopped-worker-control")
    stopped_job = poll_until(run, "queued authentication mail job while worker stopped",
                             lambda: _latest_job(context, run), interval=0.5)
    if stopped_job[0] == pending_auth_job[0] or stopped_job[1] == "completed":
        raise FixtureError("stopped-worker control did not leave authentication mail queued")
    run.command(_compose(context["project"], "up", "--detach", "--no-build", "--pull", "never", "--wait",
                         "--wait-timeout", "120", "workers"), label="restore owned worker after mail negative control",
                cwd=ROOT, env=context["_compose_env"])

    run.command(_compose(context["project"], "exec", "-T", "app", "php", "vendor/bin/laminas", "user:make-admin", user_email),
                label="grant admin role using existing CLI", cwd=ROOT, env=context["_compose_env"])
    browser("admin-and-org")
    browser("failed-provider-source")
    failure_flow = json.loads((private / "failure-state.json").read_text())
    failed_source_id = failure_flow["source_id"]
    failed_status = poll_until(run, "provider-failed source sync job", lambda: _sync_status(context, failed_source_id, run), interval=1)
    if failed_status != "failed":
        raise FixtureError("provider failure control did not reach a failed sync job")

    browser("private-source")
    try:
        dataset = json.loads((private / "dataset.json").read_text())
    except (OSError, json.JSONDecodeError) as error:
        raise FixtureError("product browser dataset is incomplete") from error
    dataset["token_path"] = str(private / "registry-token.json")
    initial_sync = poll_until(run, "private source sync job", lambda: _sync_job(context, dataset["source_id"], run), interval=1)
    if initial_sync[1] != "completed":
        raise FixtureError("private source sync did not complete")
    package_count = _sql(context, "SELECT count(*) FROM packages p JOIN releases r ON r.organization_id=p.organization_id AND r.package_name=p.name WHERE p.source_id='" + dataset["source_id"] + "' AND p.name='packio-fixture/private-package' AND p.visibility='private' AND r.version='1.0.0'", run)
    if package_count != "1":
        raise FixtureError("private package metadata does not contain version 1.0.0")
    archive_status = poll_until(run, "private archive storage job",
                                lambda: _archive_status(context, dataset["organization_id"], run), interval=1)
    if archive_status != "completed":
        raise FixtureError("private archive storage job failed")
    dataset["archive_sha256"] = _stored_archive_digest(context, dataset["organization_id"], run)
    if dataset["archive_sha256"] != context["fixture_sha256"]:
        raise FixtureError("stored package archive does not match the exact fixture tag bytes")
    dataset = validate_dataset(context, dataset)
    if _source_visibility(context, dataset["source_id"], run) != "private":
        raise FixtureError("product source visibility is not private")

    context["dataset"] = dataset
    context["results"].update({"registration_mail": "passed", "email_verification": "passed",
                               "admin_transition": "passed", "organization_source": "passed",
                               "stopped_worker_control": "passed", "provider_failure_control": "passed",
                               "private_archive": "passed"})

    # A fresh worker container starts with no compiled mapper cache; then the actual UI resync
    # queues another job through the real session/CSRF-protected route.
    run.command(_compose(context["project"], "rm", "--stop", "--force", "workers"),
                label="remove owned worker for cold-cache recreation", cwd=ROOT, env=context["_compose_env"])
    run.command(_compose(context["project"], "create", "--no-build", "workers"),
                label="create fresh owned worker before warmup", cwd=ROOT, env=context["_compose_env"])
    worker_id = run.command(_compose(context["project"], "ps", "--all", "-q", "workers"),
                            label="resolve fresh worker before warmup", cwd=ROOT, env=context["_compose_env"]).stdout.strip()
    if not re.fullmatch(r"[0-9a-f]{12,64}", worker_id):
        raise FixtureError("fresh worker container is unavailable before warmup")
    cache_copy = private / "recreated-worker-cache"
    run.command(("docker", "cp", f"{worker_id}:/app/data/cache/.", str(cache_copy)),
                label="inspect fresh worker cache before warmup")
    if not _mapper_cache_is_empty(cache_copy):
        raise FixtureError("fresh worker mapper cache was not empty before warmup")
    run.command(_compose(context["project"], "up", "--detach", "--no-build", "--pull", "never", "--wait",
                         "--wait-timeout", "120", "workers"), label="start recreated worker", cwd=ROOT, env=context["_compose_env"])
    started_worker_id = run.command(_compose(context["project"], "ps", "-q", "workers"),
                                    label="confirm recreated worker identity", cwd=ROOT, env=context["_compose_env"]).stdout.strip()
    if started_worker_id != worker_id:
        raise FixtureError("worker warmup did not use the inspected fresh container")
    browser("resync")
    next_sync = poll_until(run, "recreated-worker source sync", lambda: _sync_job(context, dataset["source_id"], run, exclude=initial_sync[0]), interval=1)
    if next_sync[1] != "completed":
        raise FixtureError("recreated worker did not complete the next source sync")
    context["results"]["worker_recreation"] = "passed"
    context["dataset"] = dataset
    return dataset


def _sql(context, query, run):
    result = run.command(_compose(context["project"], "exec", "-T", "postgres", "psql", "-U", "packio", "-d", "packio", "-Atqc", query),
                         label="read product fixture state", cwd=ROOT, env=context["_compose_env"])
    return result.stdout.strip()


def _browser_ready(port):
    try:
        with socket.create_connection(("127.0.0.1", port), timeout=0.5):
            return True
    except OSError:
        return False


def _read_browser_failure(path, phase):
    if phase not in BROWSER_PHASES:
        return None
    try:
        diagnostic = json.loads(Path(path).read_text())
    except (OSError, json.JSONDecodeError):
        return None
    expected_fields = {"phase", "step", "error_type"}
    if (not isinstance(diagnostic, dict) or set(diagnostic) not in
            (expected_fields, expected_fields | {"resync"}, expected_fields | {"source"})
            or not all(isinstance(diagnostic[key], str) for key in expected_fields)
            or diagnostic["phase"] != phase
            or diagnostic["step"] not in BROWSER_STEPS
            or diagnostic["error_type"] not in BROWSER_ERROR_TYPES):
        return None
    if "resync" not in diagnostic:
        if diagnostic["step"] in BROWSER_RESYNC_DIAGNOSTIC_STEPS and phase != "resync":
            return None
        if "source" in diagnostic:
            values = diagnostic["source"]
            required = BROWSER_SOURCE_DIAGNOSTIC_FIELDS - {"http_status"}
            if (phase not in {"failed-provider-source", "private-source"}
                    or diagnostic["step"] not in BROWSER_SOURCE_DIAGNOSTIC_STEPS
                    or not isinstance(values, dict)
                    or not required.issubset(values)
                    or not set(values).issubset(BROWSER_SOURCE_DIAGNOSTIC_FIELDS)
                    or ("http_status" in values and (type(values["http_status"]) is not int
                                                       or not 100 <= values["http_status"] <= 599))
                    or any(type(values[key]) is not bool for key in
                           ("sources_route_match", "form_invalid_control_present"))
                    or type(values["browser_error_count"]) is not int
                    or values["browser_error_count"] < 0):
                return None
            return diagnostic["step"], diagnostic["error_type"], values
        return diagnostic["step"], diagnostic["error_type"]
    values = diagnostic["resync"]
    if (phase != "resync" or diagnostic["step"] not in BROWSER_RESYNC_DIAGNOSTIC_STEPS
            or not isinstance(values, dict) or not values
            or not set(values).issubset(BROWSER_RESYNC_DIAGNOSTIC_FIELDS)
            or BROWSER_RESYNC_DIAGNOSTIC_STEPS[diagnostic["step"]] not in values):
        return None
    if ("http_status" in values and (type(values["http_status"]) is not int
                                      or not 100 <= values["http_status"] <= 599)
            or any(key in values and type(values[key]) is not bool
                   for key in ("redirect_route_match", "flash_visible", "final_route_match"))
            or ("browser_error_count" in values and
                (type(values["browser_error_count"]) is not int or values["browser_error_count"] < 0))):
        return None
    return diagnostic["step"], diagnostic["error_type"], values


def _sync_status(context, source_id, run):
    result = _sync_job(context, source_id, run)
    return result[1] if result else None


def _sync_job(context, source_id, run, *, exclude=None):
    if not re.fullmatch(r"[0-9a-f-]{36}", source_id):
        raise FixtureError("source sync identifier is invalid")
    row = _sql(context, "SELECT uuid::text || '|' || status FROM jobs WHERE type='source.sync' AND payload->>'source_id'='" + source_id + "' ORDER BY created_at DESC LIMIT 1", run)
    if not row or "|" not in row:
        return None
    job_id, status = row.split("|", 1)
    if job_id == exclude or status not in ("completed", "failed"):
        return None
    return job_id, status


def _source_visibility(context, source_id, run):
    return _sql(context, "SELECT visibility FROM sources WHERE uuid='" + source_id + "'", run)


def _archive_status(context, organization_id, run):
    if not re.fullmatch(r"[0-9a-f-]{36}", organization_id):
        raise FixtureError("organization identifier is invalid")
    status = _sql(context, "SELECT status FROM jobs WHERE type='store.release.archive' AND organization_id='" + organization_id + "' AND payload->>'package_name'='packio-fixture/private-package' AND payload->>'version'='1.0.0' ORDER BY created_at DESC LIMIT 1", run)
    return status if status in ("completed", "failed") else None


def _latest_job(context, run):
    row = _sql(context, "SELECT uuid::text || '|' || status FROM jobs WHERE type='identity.auth_mail' ORDER BY created_at DESC LIMIT 1", run)
    if not row or "|" not in row:
        return None
    return tuple(row.split("|", 1))


def _completed_auth_job(context, run):
    job = _latest_job(context, run)
    return job if job and job[1] == "completed" else None


def _stored_archive_digest(context, organization_id, run):
    if not re.fullmatch(r"[0-9a-f-]{36}", organization_id):
        raise FixtureError("organization identifier is invalid")
    container = _compose(context["project"], "ps", "-q", "app")
    app_id = run.command(container, label="resolve product app container", cwd=ROOT, env=context["_compose_env"]).stdout.strip()
    if not re.fullmatch(r"[0-9a-f]{12,64}", app_id):
        raise FixtureError("product app container is unavailable for archive check")
    exact_archive_path = f"/app/data/package-archives/*/org/{organization_id}/dist/packio-fixture/private-package/1.0.0_*.zip"
    listing = run.command(("docker", "exec", app_id, "sh", "-ec",
                           f"find /app/data/package-archives -type f -path '{exact_archive_path}' -print -quit"),
                          label="locate stored private archive")
    archive_path = listing.stdout.strip()
    if not archive_path.startswith("/app/data/package-archives/"):
        raise FixtureError("private package archive was not stored")
    destination = Path(context["private_dir"]) / "stored-package.zip"
    run.command(("docker", "cp", f"{app_id}:{archive_path}", str(destination)), label="copy private archive for hash check")
    context["_stored_archive_path"] = str(destination)
    return hashlib.sha256(destination.read_bytes()).hexdigest()


def _mapper_cache_is_empty(cache_root):
    mapper_cache = Path(cache_root) / "object-mapper" / "v8"
    return not mapper_cache.exists() or not any(path.is_file() for path in mapper_cache.rglob("*"))


def _project_env(context, *, app_image, worker_image, ssr_image, app_port):
    return {
        **os.environ,
        "APP_IMAGE": app_image,
        "WORKER_IMAGE": worker_image,
        "SSR_IMAGE": ssr_image,
        "PRODUCT_HTTP_PORT": str(app_port),
        "PRODUCT_PRIVATE_KEY": context["private_key"],
        "PRODUCT_DATABASE_PASS": context["database_pass"],
        "PRODUCT_APP_URL": context["app_url"],
        "PRODUCT_REGISTRY_BASE_DOMAIN": context["registry_base_domain"],
        "DATABASE_PASS": context["database_pass"],
        "DATABASE_NAME": "packio",
        "DATABASE_USER": "packio",
        "APP_SECRET_ENCRYPTION_KEY": context["private_key"],
        "APP_URL": context["app_url"],
        "PRODUCT_PROVIDER_IP": context["provider_ip"],
        "PRODUCT_PROVIDER_HOST": context["provider_host"],
        "PRODUCT_MAILPIT_API_PORT": str(context["mailpit_api_port"]),
        "PRODUCT_LOCAL_CONFIG": str(Path(context["private_dir"]) / "local.php"),
        "PRODUCT_CA_CERT": str(Path(context["private_dir"]) / "root-ca.pem"),
        "PRODUCT_CA_INI": str(Path(context["private_dir"]) / "fixture-ca.ini"),
        "PRODUCT_TLS_CERT": str(Path(context["private_dir"]) / "server-cert.pem"),
        "PRODUCT_TLS_KEY": str(Path(context["private_dir"]) / "server-key.pem"),
        "PRODUCT_PROVIDER_DATA": str(Path(context["private_dir"]) / "provider"),
        "PRODUCT_EDGE_CADDYFILE": str(FIXTURES_DIR / "caddy" / "edge.Caddyfile"),
        "PRODUCT_PROVIDER_CADDYFILE": str(FIXTURES_DIR / "provider" / "Caddyfile"),
        "CADDY_IMAGE": context["ancillary_images"]["caddy"],
        "MAILPIT_IMAGE": context["ancillary_images"]["mailpit"],
        "POSTGRES_IMAGE": context["ancillary_images"]["postgres"],
        "COMPOSER_IMAGE": context["ancillary_images"]["composer"],
    }


def start_fixture(app_image, worker_image, ssr_image, expected_sha, *, test_snapshot=None,
                  test_snapshot_kind="disposable_local_O1_test_snapshot", private_key=None, timeout=570):
    validate_candidate_sha(expected_sha, expected_sha)
    if private_key is not None and not re.fullmatch(r"[0-9a-f]{64}", private_key):
        raise FixtureError("fixture restore key is invalid")
    image_refs = {"app": app_image, "worker": worker_image, "ssr": ssr_image}
    snapshot_validator = {"disposable_local_O1_test_snapshot": validate_o1_test_snapshot,
                         "disposable_local_R1_test_snapshot": validate_r1_test_snapshot}.get(test_snapshot_kind)
    if test_snapshot is not None and snapshot_validator is None:
        raise FixtureError("test snapshot kind is invalid")
    snapshot_proof = (snapshot_validator(test_snapshot, expected_sha, image_refs)
                      if test_snapshot is not None else None)
    run = _new_fixture_run(timeout)
    context = {
        "project": f"packio-e1-{uuid.uuid4().hex}",
        "resource_ids": [],
        "private_dir": None,
        "images": {"app": app_image, "worker": worker_image, "ssr": ssr_image},
        "candidate_sha": expected_sha,
        "test_snapshot_proof": snapshot_proof,
        "private_key": private_key or secrets.token_hex(32),
        "database_pass": secrets.token_urlsafe(24),
        "provider_ip": "127.0.0.1",
        "provider_host": "provider.fixture.test",
        "mailpit_api_port": 0,
        "https_port": None,
        "ancillary_images": {},
        "fixture_sha256": None,
        "_secrets": [],
        "_resources_started": False,
        "_hard_deadline": run.deadline + 30,
        "_run": run,
    }
    context["_secrets"] = [context["private_key"], context["database_pass"]]
    try:
        run.require_tools()
        for path in (COMPOSE_PROD, COMPOSE_PRODUCT, IMAGE_HARNESS):
            if not path.is_file():
                raise FixtureError("required product fixture configuration is missing")
        context["private_dir"] = tempfile.mkdtemp(prefix=f"{context['project']}-")
        Path(context["private_dir"]).chmod(0o700)
        for name, reference in (("caddy", "caddy:2.11.2-alpine"), ("mailpit", "axllent/mailpit"),
                                ("postgres", "postgres:17-alpine"), ("composer", "composer:2.8")):
            result = run.command(("docker", "image", "inspect", "--format", "{{.Id}}", reference),
                                 label=f"inspect required {name} image", check=False)
            image_id = result.stdout.strip()
            if result.returncode or not re.fullmatch(r"sha256:[0-9a-f]{64}", image_id):
                raise FixtureError(f"required {name} fixture image is unavailable")
            context["ancillary_images"][name] = image_id
        context["_resources_started"] = True
        composer = run.command(("docker", "run", "--rm", "--pull=never", "--name", f"{context['project']}-composer",
                                "--label", f"com.docker.compose.project={context['project']}",
                                context["ancillary_images"]["composer"], "--version"),
                               label="check Composer 2.8 fixture client")
        if not re.search(r"Composer version 2\.8\.", composer.stdout):
            raise FixtureError("Composer 2.8 fixture client is required")
        context["https_port"] = _free_port()
        context["http_port"] = _free_port()
        context["mailpit_api_port"] = _free_port()
        context["app_url"] = f"https://app.fixture.test:{context['https_port']}"
        context["registry_base_domain"] = f"registry.fixture.test:{context['https_port']}"
        context["provider_url"] = "https://provider.fixture.test"
        context["mailpit_url"] = f"http://127.0.0.1:{context['mailpit_api_port']}"
        _create_test_certificate(run, Path(context["private_dir"]))
        context["provider_repo"] = _create_fixture_repository(Path(context["private_dir"]), run)
        context["fixture_sha256"] = _make_dist(Path(context["private_dir"]), run)
        env = _project_env(context, app_image=app_image, worker_image=worker_image,
                           ssr_image=ssr_image, app_port=context["https_port"])
        context["_compose_env"] = env

        context["image_report"] = _verify_candidate_images(run, context, expected_sha)

        # Verify the resolved Compose model contains no checkout mounts before
        # creating anything. Allowed binds are limited to named fixture config.
        config = run.command(_compose(context["project"], "config", "--format", "json"),
                             label="validate product Compose configuration", cwd=ROOT, env=env)
        model = json.loads(config.stdout)
        mounts = [mount for service in model.get("services", {}).values()
                  for mount in service.get("volumes", []) if isinstance(mount, dict)]
        private = Path(context["private_dir"])
        assert_safe_mounts(mounts, source_root=ROOT,
                           allowed_roots=(FIXTURES_DIR, private))
        for service in ("app", "workers", "ssr", "postgres", "mailpit", "provider", "tls"):
            if service not in model.get("services", {}):
                raise FixtureError(f"required {service} fixture service is missing")
        for service in ("app", "workers", "ssr"):
            configured = model["services"][service].get("image")
            expected = context["images"][{"app": "app", "workers": "worker", "ssr": "ssr"}[service]]
            if configured != expected or "build" in model["services"][service]:
                raise FixtureError(f"{service} must use its supplied immutable image without a build")

        # Start only local dependencies first so the exact proxy endpoint IP
        # can be approved in the test-only local.php file before app startup.
        run.command(_compose(context["project"], "up", "--detach", "--no-build", "--pull", "never",
                             "--wait", "--wait-timeout", "120", "postgres", "mailpit", "provider", "tls"),
                    label="start isolated product fixture dependencies", cwd=ROOT, env=env)
        tls_ip = _service_ip(context["project"], "tls", run, env)
        context["provider_ip"] = tls_ip
        env["PRODUCT_TRUSTED_PROXIES"] = tls_ip
        _write_local_config(Path(context["private_dir"]) / "local.php", context["provider_host"], tls_ip)
        context["provider_url"] = "https://provider.fixture.test/git/private-package"

        # Fresh project volumes must be empty before app bootstrap runs.
        _assert_empty_database(context, run, env)
        for service in ("app", "workers"):
            run.command(_compose(context["project"], "run", "--rm", "--no-deps", "--name",
                                 f"{context['project']}-{service}-preflight", "--entrypoint", "/bin/sh",
                                 service, "-ec", _empty_paths_check()),
                        label=f"check clean {service} cache and archives", cwd=ROOT, env=env)

        # The product runtime is started only after those empty-state checks.
        run.command(_compose(context["project"], "up", "--detach", "--no-build", "--pull", "never",
                             "--wait", "--wait-timeout", "240", "app", "workers", "ssr"),
                    label="start immutable product app and worker", cwd=ROOT, env=env)
        context["resource_ids"] = _owned_container_ids(context["project"], run)
        if not context["resource_ids"]:
            raise FixtureError("fixture did not create owned Compose containers")
        validate_fixture(context, run=run)
        _assert_seeded_database(context, run, env)
        _assert_generic_git_provider(context, run, env)
        context["resolved_images"] = _resolved_image_ids(context["resource_ids"], run)
        expected_ids = {role: context["image_report"]["images"][role]["id"] for role in IMAGE_ROLES}
        if context["resolved_images"] != expected_ids:
            raise FixtureError("started product services do not match the verified image IDs")
        context["results"] = {"candidate_images": "passed", "services": "passed",
                               "empty_state": "fresh_project_volumes", "migrations_seed": "passed",
                               "tls_trust": "passed", "generic_git": "passed", "composer_client": "passed"}
        return context
    except BaseException as primary:
        try:
            stop_fixture(context)
        except BaseException as cleanup:
            primary_message = (str(primary) if isinstance(primary, FixtureError)
                               else "unexpected fixture startup failure")
            diagnostics = (cleanup.diagnostics if isinstance(cleanup, FixtureCleanupError)
                           else ("cleanup diagnostic unavailable",))
            raise FixtureCleanupError(diagnostics, primary_message=primary_message) from None
        raise


def _free_port():
    import socket
    with socket.socket() as listener:
        listener.bind(("127.0.0.1", 0))
        return listener.getsockname()[1]


def _create_test_certificate(run, destination):
    root_config = destination / "root-ca.cnf"
    root_config.write_text("""[req]\ndistinguished_name=dn\nx509_extensions=v3_ca\nprompt=no\n[dn]\nCN=Packio E2 fixture root\n[v3_ca]\nbasicConstraints=critical,CA:TRUE,pathlen:0\nkeyUsage=critical,keyCertSign,cRLSign\nsubjectKeyIdentifier=hash\n""")
    run.command(("openssl", "req", "-x509", "-newkey", "rsa:2048", "-nodes", "-days", "1",
                 "-keyout", str(destination / "root-ca-key.pem"), "-out", str(destination / "root-ca.pem"),
                 "-config", str(root_config)), label="create disposable fixture root CA")
    leaf_config = destination / "server-leaf.cnf"
    leaf_config.write_text("""[v3_leaf]\nbasicConstraints=critical,CA:FALSE\nkeyUsage=critical,digitalSignature,keyEncipherment\nextendedKeyUsage=serverAuth\nsubjectAltName=DNS:app.fixture.test,DNS:*.registry.fixture.test,DNS:provider.fixture.test\nsubjectKeyIdentifier=hash\nauthorityKeyIdentifier=keyid,issuer\n""")
    run.command(("openssl", "req", "-new", "-newkey", "rsa:2048", "-nodes", "-subj", "/CN=app.fixture.test",
                 "-keyout", str(destination / "server-key.pem"), "-out", str(destination / "server.csr")),
                label="create disposable fixture TLS leaf request")
    run.command(("openssl", "x509", "-req", "-in", str(destination / "server.csr"),
                 "-CA", str(destination / "root-ca.pem"), "-CAkey", str(destination / "root-ca-key.pem"),
                 "-CAcreateserial", "-days", "1", "-extfile", str(leaf_config), "-extensions", "v3_leaf",
                 "-out", str(destination / "server-cert.pem")), label="sign fixture TLS leaf certificate")
    (destination / "root-ca.pem").chmod(0o644)
    (destination / "server-cert.pem").chmod(0o644)
    policy = destination / "browser-policies.json"
    policy.write_text(json.dumps({"policies": {"Certificates": {"Install": ["/run/fixture/root-ca.pem"]}}}))
    policy.chmod(0o644)
    (destination / "fixture-ca.ini").write_text(
        "curl.cainfo=/run/fixture/root-ca.pem\nopenssl.cafile=/run/fixture/root-ca.pem\n")


def _image_harness():
    spec = importlib.util.spec_from_file_location("release_image_harness", IMAGE_HARNESS)
    if spec is None or spec.loader is None:
        raise FixtureError("existing image verification helpers are unavailable")
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def _verify_candidate_images(run, context, expected_sha):
    harness = _image_harness()
    targets = {"app": (context["images"]["app"], "prod"),
               "worker": (context["images"]["worker"], "prod-worker"),
               "ssr": (context["images"]["ssr"], "prod-ssr")}
    report = {}
    for role, (reference, target) in targets.items():
        inspected = run.command(("docker", "image", "inspect", "--format", "{{json .}}", reference),
                                label=f"resolve {role} image")
        details = json.loads(inspected.stdout)
        image_id = details.get("Id", "")
        if not re.fullmatch(r"sha256:[0-9a-f]{64}", image_id):
            raise FixtureError(f"could not resolve immutable {role} image ID")
        snapshot = context.get("test_snapshot_proof")
        if snapshot is not None and image_id != snapshot["images"][role]:
            raise FixtureError(f"{role} image ID does not match the {snapshot['kind']} snapshot")
        descriptor_digests = [image_id]
        descriptor = details.get("Descriptor", {}).get("digest")
        if descriptor:
            descriptor_digests.append(descriptor)
        info = {"id": image_id, "descriptor_digests": descriptor_digests,
                "platform": {"os": details.get("Os", "linux"),
                              "architecture": details.get("Architecture", "amd64")}}
        metadata = None
        if role in ("app", "worker"):
            result = run.command(("docker", "run", "--pull=never", "--rm", "--name",
                                  f"{context['project']}-{role}-metadata",
                                  "--label", f"com.docker.compose.project={context['project']}",
                                  "--entrypoint", "cat", image_id, "/app/data/build/release.json"),
                                 label=f"read {role} release metadata")
            metadata = json.loads(result.stdout)
            if snapshot is None:
                validate_image_metadata(metadata, expected_sha, role)
            else:
                metadata_sha = metadata.get("sha") if isinstance(metadata, dict) else None
                if (not isinstance(metadata_sha, str) or not re.fullmatch(r"[0-9a-f]{7,40}", metadata_sha)
                        or not expected_sha.startswith(metadata_sha) or metadata.get("dirty") is not True):
                    raise FixtureError(f"{role} image metadata does not match the {snapshot['kind']} snapshot")
        archive = Path(context["private_dir"]) / f"{role}.tar"
        run.command(("docker", "image", "save", "--output", str(archive), image_id),
                    label=f"export {role} OCI provenance")
        if not archive.is_file():
            raise FixtureError(f"could not save {role} image provenance")
        try:
            provenance = harness.verify_provenance(
                archive, image_id, descriptor_digests, info["platform"], expected_sha, target)
        except AssertionError as error:
            raise FixtureError(f"{role} image provenance does not match the requested candidate") from error
        info["source_sha"] = provenance["source_sha"]
        if info["source_sha"] != expected_sha:
            raise FixtureError(f"{role} image provenance source SHA does not match the requested candidate")
        report[role] = {"id": image_id, "source_sha": info["source_sha"],
                        "metadata_sha": metadata.get("sha") if metadata else None,
                        "metadata_embeds_sha": metadata is not None,
                        "base_digests": provenance["base_digests"],
                        "runtime_manifest_digest": provenance["runtime_manifest_digest"]}
        context["images"][role] = image_id
    return {"expected_sha": expected_sha, "source_sha": expected_sha, "images": report}


def _write_local_config(path, host, address):
    path.write_text("<?php\ndeclare(strict_types=1);\nreturn ['outbound_http' => ['endpoints' => ["
                    + repr(f"https://{host}") + " => [" + repr(address) + "]]]];\n")
    # The exact-origin map contains no credentials; image UID may differ from the host UID.
    path.chmod(0o644)


def _fixture_git_environment():
    # Git variables can redirect repository writes even when cwd is private.
    environment = {key: value for key, value in os.environ.items() if not key.startswith("GIT_")}
    environment.update({"GIT_CONFIG_NOSYSTEM": "1", "GIT_CONFIG_SYSTEM": "/dev/null",
                        "GIT_CONFIG_GLOBAL": "/dev/null", "GIT_TERMINAL_PROMPT": "0",
                        "TZ": "UTC",
                        "GIT_AUTHOR_NAME": "E1 Fixture", "GIT_AUTHOR_EMAIL": "e1@example.invalid",
                        "GIT_COMMITTER_NAME": "E1 Fixture", "GIT_COMMITTER_EMAIL": "e1@example.invalid",
                        "GIT_AUTHOR_DATE": "2000-01-01T00:00:00Z", "GIT_COMMITTER_DATE": "2000-01-01T00:00:00Z"})
    return environment


def _create_fixture_repository(private_dir, run):
    private_dir = private_dir.resolve()
    repository = private_dir / "provider" / "private-package"
    working = private_dir / "working-package"
    if not repository.is_relative_to(private_dir) or not working.is_relative_to(private_dir):
        raise FixtureError("fixture repository escaped its private run directory")
    shutil.copytree(FIXTURES_DIR / "private-package", working)
    git_env = _fixture_git_environment()
    run.command(("git", "init", "--initial-branch=main"), label="initialize private fixture repository", cwd=working, env=git_env)
    run.command(("git", "add", "composer.json", "src/Probe.php"), label="add private fixture package files", cwd=working, env=git_env)
    run.command(("git", "commit", "--message", "fixture package 1.0.0"), label="create private fixture revision", cwd=working, env=git_env)
    run.command(("git", "tag", "1.0.0"), label="tag private fixture package 1.0.0", cwd=working, env=git_env)
    repository.parent.mkdir(parents=True, exist_ok=True)
    run.command(("git", "clone", "--bare", str(working), str(repository)),
                label="prepare private generic-Git provider repository", cwd=private_dir, env=git_env)
    run.command(("git", "--git-dir", str(repository), "update-server-info"),
                label="prepare private generic-Git HTTP metadata", cwd=private_dir, env=git_env)
    for reference in ("main", "1.0.0"):
        raw = repository / "raw" / reference
        raw.mkdir(parents=True, exist_ok=True)
        shutil.copy2(FIXTURES_DIR / "private-package" / "composer.json", raw / "composer.json")
    return repository


def _make_dist(private_dir, run):
    archive_path = private_dir / "private-package-1.0.0.zip"
    run.command(("git", "archive", "--format=zip", "--output", str(archive_path), "1.0.0"),
                label="create deterministic tagged package dist", cwd=private_dir / "working-package",
                env=_fixture_git_environment())
    return hashlib.sha256(archive_path.read_bytes()).hexdigest()


def _empty_paths_check(*, include_archives=True):
    paths = ["/app/data/cache", "/app/data/sessions", "/app/data/rate-limits"]
    if include_archives:
        paths.append("/app/data/package-archives")
    checks = [f"(test ! -d {path} || test -z \"$(find {path} -mindepth 1 ! -type d -print -quit)\")"
              for path in paths]
    return " && ".join(checks)


def _service_ip(project, service, run, env):
    output = run.command(_compose(project, "ps", "-q", service), label=f"resolve {service} container",
                         cwd=ROOT, env=env).stdout.strip().splitlines()
    if len(output) != 1 or not re.fullmatch(r"[0-9a-f]{12,64}", output[0]):
        raise FixtureError(f"required {service} service did not start")
    inspected = run.command(("docker", "inspect", "--format",
                             "{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}", output[0]),
                            label=f"resolve {service} address")
    try:
        return str(__import__("ipaddress").ip_address(inspected.stdout.strip()))
    except ValueError as error:
        raise FixtureError(f"required {service} address is unavailable") from error


def _assert_empty_database(context, run, env):
    command = _compose(context["project"], "exec", "-T", "postgres", "psql", "-U", "packio", "-d", "packio",
                       "-Atqc", "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public'")

    def connected_and_empty():
        result = run.command(command, label="check clean PostgreSQL before migrations",
                             cwd=ROOT, env=env, check=False)
        # The stock image briefly restarts its temporary initialization server.
        # Retry only native psql's startup connection error, within this Run's deadline.
        if (result.returncode == 2 and result.stdout.startswith("psql: error: connection to server ")
                and any(reason in result.stdout for reason in (
                    "failed: No such file or directory", "failed: Connection refused",
                    "the database system is starting up", "the database system is shutting down",
                    "the database system is in recovery mode"))):
            return False
        if result.returncode:
            raise FixtureError(f"check clean PostgreSQL before migrations failed (exit {result.returncode})")
        if result.stdout.strip() != "0":
            raise FixtureError("fixture database was not empty before bootstrap")
        return True

    poll_until(run, "clean PostgreSQL connection before migrations", connected_and_empty, interval=0.2)


def _assert_seeded_database(context, run, env):
    migration = run.command(_compose(context["project"], "exec", "-T", "postgres", "psql", "-U", "packio",
                                    "-d", "packio", "-Atqc",
                                    "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'users'"),
                           label="verify database migrations", cwd=ROOT, env=env)
    seed = run.command(_compose(context["project"], "exec", "-T", "postgres", "psql", "-U", "packio",
                               "-d", "packio", "-Atqc",
                               "SELECT count(*) FROM project_settings WHERE setting_key = 'source.providers.generic_git.enabled'"),
                       label="verify runtime settings seed", cwd=ROOT, env=env)
    if migration.stdout.strip() != "1" or seed.stdout.strip() != "1":
        raise FixtureError("application migrations or runtime settings seed did not complete")


def _assert_generic_git_provider(context, run, env):
    url = "https://provider.fixture.test/git/private-package"
    php = (
        "require 'vendor/autoload.php'; "
        "$container = require 'config/container.php'; "
        "$fetcher = $container->get(\\Packio\\Publication\\Infrastructure\\GenericGit\\GenericGitComposerMetadataFetcherInterface::class); "
        f"$url = {json.dumps(url)}; "
        "$credentials = new \\Packio\\Publication\\Infrastructure\\GenericGit\\GenericGitAccessCredentials($url); "
        "$metadata = $fetcher->fetch($url, '1.0.0', $credentials); "
        "if (($metadata->composer['name'] ?? null) !== 'packio-fixture/private-package' "
        "|| ($metadata->composer['version'] ?? null) !== '1.0.0' || $metadata->refType !== 'tag') { exit(23); } "
        "echo 'fixture-provider-ok';"
    )
    result = run.command(_compose(context["project"], "exec", "-T", "workers", "php", "-r", php),
                         label="verify Generic Git source via trusted HTTPS", cwd=ROOT, env=env)
    if result.stdout.strip() != "fixture-provider-ok":
        raise FixtureError("Generic Git HTTPS fixture did not return the tagged package metadata")


def _check_trusted_tls(context):
    run = context.get("_run") or Run(timeout=30)
    run.remaining()
    trust = ssl.create_default_context(cafile=str(Path(context["private_dir"]) / "root-ca.pem"))
    endpoints = (("app.fixture.test", "/health", 200),
                 ("org.registry.fixture.test", "/packages.json", 404),
                 ("provider.fixture.test", "/git/private-package/info/refs", 200))
    for host, path, expected_status in endpoints:
        try:
            raw = socket.create_connection(("127.0.0.1", context["https_port"]), timeout=min(8, run.remaining()))
            with raw:
                raw.settimeout(min(8, run.remaining()))
                with trust.wrap_socket(raw, server_hostname=host) as tls:
                    tls.settimeout(min(8, run.remaining()))
                    tls.sendall(f"GET {path} HTTP/1.1\r\nHost: {host}:{context['https_port']}\r\nConnection: close\r\n\r\n".encode())
                    tls.settimeout(min(8, run.remaining()))
                    status = tls.recv(256).split(b"\r\n", 1)[0].split()
                    if len(status) < 2 or status[1] != str(expected_status).encode():
                        raise FixtureError(f"{host} HTTPS endpoint returned an unexpected status")
                    run.remaining()
        except (OSError, ssl.SSLError) as error:
            raise FixtureError("fixture root or wildcard registry TLS trust check failed") from error


def _owned_container_ids(project, run):
    result = run.command(("docker", "ps", "-aq", "--filter", f"label=com.docker.compose.project={project}"),
                         label="list owned Compose containers", check=False)
    if result.returncode:
        return []
    values = [line.strip() for line in result.stdout.splitlines() if line.strip()]
    return [item for item in values if re.fullmatch(r"[0-9a-f]{12,64}", item)]


def _compose_states(context, run, env):
    output = run.command(_compose(context["project"], "ps", "--format", "json"),
                         label="read product fixture service state", cwd=ROOT, env=env).stdout
    try:
        records = json.loads(output)
    except json.JSONDecodeError:
        records = [json.loads(line) for line in output.splitlines() if line.strip()]
    result = {}
    for record in records:
        service = record.get("Service") or record.get("Name", "").rsplit("-", 1)[-1]
        health = str(record.get("Health") or "").lower()
        state = str(record.get("State") or "").lower()
        result[service] = "healthy" if health == "healthy" else (health if health else ("running" if state == "running" else state))
    return result


def validate_fixture(context, *, run=None):
    """Fail unless all required fixture services and local HTTPS routes remain ready."""
    run = run or Run(timeout=30)
    env = context.get("_compose_env")
    if not env:
        raise FixtureError("fixture context is missing its private Compose environment")
    require_services(_compose_states(context, run, env))
    _check_trusted_tls(context)
    return True


def _resolved_image_ids(container_ids, run):
    resolved = {}
    for container_id in container_ids:
        result = run.command(("docker", "inspect", container_id), label="inspect started fixture container", check=False)
        if result.returncode:
            continue
        details = json.loads(result.stdout)
        if len(details) != 1:
            continue
        value = details[0]
        service = value.get("Config", {}).get("Labels", {}).get("com.docker.compose.service")
        if service in {"app", "workers", "ssr"}:
            resolved[{"app": "app", "workers": "worker", "ssr": "ssr"}[service]] = value.get("Image")
    return resolved


def report_image_ids(context):
    result = dict(context.get("resolved_images", {}))
    result.update(context.get("ancillary_images", {}))
    return result
