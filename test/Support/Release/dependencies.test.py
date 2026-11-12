#!/usr/bin/env python3
"""Check production Composer requirements in an isolated runtime image."""

import argparse
import os
from pathlib import Path
import re
import shutil
import subprocess
import tempfile
import time
import uuid

ROOT = Path(__file__).resolve().parents[3]
DEFAULT_RUNTIME = "packio-s4-runtime-20261009:local"
POSTGRES_IMAGE = "postgres:17-alpine"
SYNTHETIC_SECRETS = (
    "dependency-fixture-only",
    "0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef",
)


class FixtureError(RuntimeError):
    """A disposable production dependency check could not complete."""


def run(args, *, label, check=True, **kwargs):
    result = subprocess.run(args, stdout=subprocess.PIPE, stderr=subprocess.STDOUT,
                            text=True, check=False, **kwargs)
    if check and result.returncode:
        detail = result.stdout or "(no output)"
        for secret in SYNTHETIC_SECRETS:
            detail = detail.replace(secret, "[redacted]")
        detail = re.sub(
            r"(?i)(DATABASE_PASS|POSTGRES_PASSWORD|APP_SECRET_ENCRYPTION_KEY)(\s*[=:]\s*)\S+",
            r"\1\2[redacted]",
            detail,
        )
        lines = [line[:300] for line in detail.splitlines()[-12:]]
        raise FixtureError(f"{label} failed (exit {result.returncode}):\n" + "\n".join(lines))
    return result


def docker(*args, label, check=True):
    return run(("docker", *args), label=label, check=check)


def make_context(destination):
    excluded_parts = {".git", "vendor", "node_modules", "local-plans", "test-results"}
    excluded_prefixes = (
        "data/build/", "data/cache/", "data/logs/", "data/rate-limits/",
        "data/sessions/", "data/package-archives/", "data/coverage/",
    )
    tracked = subprocess.check_output(("git", "ls-files", "-z"), cwd=ROOT).split(b"\0")
    for raw_path in tracked:
        if not raw_path:
            continue
        relative = Path(os.fsdecode(raw_path))
        name = relative.as_posix()
        if any(part in excluded_parts for part in relative.parts):
            continue
        if name.startswith((".env", "config/autoload/local", "config/autoload/development.local")):
            continue
        if name == "config/development.config.php":
            continue
        if any(name.startswith(prefix) for prefix in excluded_prefixes):
            continue
        if name == "data/db/sqlite.db" or name.startswith("data/db/sqlite.db-"):
            continue
        source = ROOT / relative
        if not source.is_file():
            continue
        target = destination / relative
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(source, target)


def build(context, dockerfile, target, image, runtime_image):
    run(("docker", "build", "--build-arg", f"RUNTIME_IMAGE={runtime_image}",
         "--target", target, "-f", str(dockerfile), "-t", image, str(context)),
        label=f"build {target}")
    print(f"PASS: build {target} image", flush=True)


def assert_dependencies(image, no_sodium_image):
    check = r'''set -eu
php -r 'exit(extension_loaded("sodium") ? 0 : 1);'
composer check-platform-reqs --lock --no-dev
php -r '$i=json_decode(file_get_contents("vendor/composer/installed.json"), true, 512, JSON_THROW_ON_ERROR); if (!array_key_exists("dev", $i) || true === $i["dev"] || [] !== ($i["dev-package-names"] ?? null)) { fwrite(STDERR, "development packages found\n"); exit(1); }'
test ! -e vendor/bin/phpunit
test ! -d vendor/phpunit
'''
    docker("run", "--rm", "--entrypoint", "sh", image, "-ec", check,
           label="check production dependencies")
    print("PASS: sodium is loaded; Composer platform requirements and no-dev guards pass", flush=True)

    missing = r'''set -eu
php -r 'exit(extension_loaded("sodium") ? 1 : 0);'
if composer check-platform-reqs --lock --no-dev >/tmp/platform-check.log 2>&1; then
  echo 'Composer accepted a runtime without ext-sodium' >&2
  exit 1
fi
if ! grep -F 'ext-sodium' /tmp/platform-check.log >/dev/null; then
  cat /tmp/platform-check.log >&2
  exit 1
fi
'''
    docker("run", "--rm", "--entrypoint", "sh", no_sodium_image, "-ec", missing,
           label="verify missing sodium is rejected")
    print("PASS: Composer rejects the runtime when ext-sodium is disabled", flush=True)


def wait_for_postgres(container):
    deadline = time.monotonic() + 60
    while time.monotonic() < deadline:
        if docker("exec", container, "pg_isready", "-q", "-U", "packio",
                  label="wait for disposable PostgreSQL", check=False).returncode == 0:
            return
        time.sleep(0.5)
    raise FixtureError("disposable PostgreSQL did not become ready")


def check_runtime(image, network, postgres, application):
    environment = {
        "APP_PRODUCTION": "true",
        "APP_DEBUG": "false",
        "DEFAULT_DB_CONNECTION": "postgres",
        "DATABASE_HOST": postgres,
        "DATABASE_PORT": "5432",
        "DATABASE_NAME": "packio",
        "DATABASE_USER": "packio",
        "DATABASE_PASS": "dependency-fixture-only",
        "APP_SECRET_ENCRYPTION_KEY": "0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef",
        "LOG_CHANNEL": "stderr",
    }
    env = tuple(argument for key, value in environment.items() for argument in ("-e", f"{key}={value}"))
    docker("run", "-d", "--name", application, "--network", network, *env,
           "--entrypoint", "sleep", image, "infinity", label="start isolated application container")
    print("PASS: isolated production application container started", flush=True)
    for command, label in (
        # Match production startup: migrate the fresh DB before resolving all CLI services.
        (("vendor/bin/laminas", "cycle:migration:run", "-n"), "production migrations"),
        (("vendor/bin/laminas", "list"), "production console boot"),
        (("vendor/bin/laminas", "object-mapper:warmup"), "object mapper warmup"),
        (("vendor/bin/laminas", "routing-attributes:cache:warmup"), "route cache warmup"),
    ):
        docker("exec", application, "php", *command, label=label)
        print(f"PASS: {label}", flush=True)
    docker("exec", application, "sh", "-ec", "test -s data/cache/mezzio-routing-attributes.php",
           label="verify warmed routing attributes cache")
    print("PASS: warmed routing attributes cache is present", flush=True)


def resource_exists(kind, name):
    if kind == "container":
        args = ("inspect", "--type=container", "--format", "{{.Id}}", name)
    elif kind == "network":
        args = ("network", "inspect", "--format", "{{.Id}}", name)
    else:
        args = ("image", "inspect", "--format", "{{.Id}}", name)
    result = docker(*args, label=f"inspect fixture {kind}", check=False)
    if result.returncode == 0:
        return True
    if "no such" in result.stdout.lower() or "not found" in result.stdout.lower():
        return False
    raise FixtureError(f"Could not inspect fixture {kind} (exit {result.returncode})")


def cleanup(application, postgres, network, app_image, no_sodium_image):
    for name in (application, postgres):
        if resource_exists("container", name):
            docker("rm", "-f", name, label=f"remove fixture container {name}")
        if resource_exists("container", name):
            raise FixtureError(f"fixture container remains after cleanup: {name}")
    if resource_exists("network", network):
        docker("network", "rm", network, label="remove fixture network")
    if resource_exists("network", network):
        raise FixtureError(f"fixture network remains after cleanup: {network}")
    for image in (no_sodium_image, app_image):
        if resource_exists("image", image):
            docker("image", "rm", image, label="remove fixture image")
        if resource_exists("image", image):
            raise FixtureError(f"fixture image remains after cleanup: {image}")
    print("PASS: all fixture containers, network, and derived images are absent", flush=True)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--runtime-image", default=os.environ.get("PACKIO_S4_RUNTIME_IMAGE", DEFAULT_RUNTIME))
    args = parser.parse_args()

    suffix = f"{os.getpid()}-{uuid.uuid4().hex[:8]}"
    prefix = f"packio-s4-dependencies-{suffix}"
    app_image, no_sodium_image = prefix + ":app", prefix + ":no-sodium"
    network, postgres, application = prefix + "-net", prefix + "-postgres", prefix + "-app"
    try:
        with tempfile.TemporaryDirectory(prefix="packio-s4-dependencies-") as temporary:
            temporary = Path(temporary)
            context = temporary / "context"
            context.mkdir()
            make_context(context)
            dockerfile = temporary / "Dockerfile"
            dockerfile.write_text('''ARG RUNTIME_IMAGE
FROM ${RUNTIME_IMAGE} AS app
USER root
RUN apt-get update && apt-get install -y --no-install-recommends unzip && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer
COPY --chown=app:app . /app
USER app
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --no-progress
FROM app AS no-sodium
USER root
RUN rm -f /usr/local/etc/php/conf.d/*sodium*.ini && php -r 'exit(extension_loaded("sodium") ? 1 : 0);'
USER app
''')
            build(context, dockerfile, "app", app_image, args.runtime_image)
            build(context, dockerfile, "no-sodium", no_sodium_image, args.runtime_image)
            assert_dependencies(app_image, no_sodium_image)
            docker("network", "create", "--internal", network, label="create isolated test network")
            print("PASS: private internal test network created", flush=True)
            docker("run", "-d", "--name", postgres, "--network", network,
                   "--tmpfs", "/var/lib/postgresql/data:rw,nosuid,size=128m",
                   "-e", "POSTGRES_DB=packio", "-e", "POSTGRES_USER=packio",
                   "-e", "POSTGRES_PASSWORD=dependency-fixture-only", POSTGRES_IMAGE,
                   label="start disposable PostgreSQL")
            print("PASS: disposable PostgreSQL container started", flush=True)
            wait_for_postgres(postgres)
            print("PASS: disposable PostgreSQL is ready", flush=True)
            check_runtime(app_image, network, postgres, application)
    except BaseException as error:
        try:
            cleanup(application, postgres, network, app_image, no_sodium_image)
        except BaseException as cleanup_error:
            raise FixtureError(f"{error}; cleanup also failed: {cleanup_error}") from error
        raise
    else:
        cleanup(application, postgres, network, app_image, no_sodium_image)
    print("PASS: sodium platform guard and no-dev production boot verified in disposable containers.")


if __name__ == "__main__":
    try:
        main()
    except (FixtureError, subprocess.CalledProcessError) as error:
        raise SystemExit(str(error)) from error
