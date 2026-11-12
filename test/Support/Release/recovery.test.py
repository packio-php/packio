#!/usr/bin/env python3
"""Run an isolated backup, restore, and supported upgrade rehearsal."""

import argparse
import base64
from datetime import datetime, timezone
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import signal
import subprocess
import sys
import tarfile
import tempfile
import time
import uuid
import urllib.error
import urllib.parse
import urllib.request

sys.dont_write_bytecode = True
import product_fixture
from product_fixture import FixtureError, Run, prepare_dataset, public_report, report_image_ids
from product_fixture import fresh_login, start_fixture, stop_fixture, verify_private_install


def parser():
    value = argparse.ArgumentParser(description=__doc__)
    value.add_argument("--baseline-ref", required=True)
    value.add_argument("--app-image", required=True)
    value.add_argument("--worker-image", required=True)
    value.add_argument("--ssr-image", required=True)
    value.add_argument("--expected-sha", required=True)
    value.add_argument("--report", required=True)
    return value


def validate_backup_manifest(manifest):
    required = {"format", "source_sha", "database_sha256", "archives_sha256", "key_sha256", "config_sha256"}
    if not isinstance(manifest, dict) or set(manifest) != required:
        raise FixtureError("backup manifest fields are invalid")
    if manifest["format"] != 1:
        raise FixtureError("backup manifest format is unsupported")
    if not isinstance(manifest["source_sha"], str) or len(manifest["source_sha"]) != 40 or any(
        char not in "0123456789abcdef" for char in manifest["source_sha"]
    ):
        raise FixtureError("backup source identity is invalid")
    for key in ("database_sha256", "archives_sha256", "key_sha256", "config_sha256"):
        value = manifest[key]
        if not isinstance(value, str) or len(value) != 64 or any(
            char not in "0123456789abcdef" for char in value
        ):
            raise FixtureError("backup component digest is invalid")
    return True


def validate_backup_set(manifest, paths):
    validate_backup_manifest(manifest)
    expected_names = {"database", "archives", "key", "config"}
    if not isinstance(paths, dict) or set(paths) != expected_names:
        raise FixtureError("backup component paths are invalid")
    digest_keys = {"database": "database_sha256", "archives": "archives_sha256",
                   "key": "key_sha256", "config": "config_sha256"}
    for name, path in paths.items():
        path = Path(path)
        if path.is_symlink() or not path.is_file():
            raise FixtureError(f"backup {name} is missing or unsafe")
        digest = hashlib.sha256()
        with path.open("rb") as stream:
            for chunk in iter(lambda: stream.read(1024 * 1024), b""):
                digest.update(chunk)
        if digest.hexdigest() != manifest[digest_keys[name]]:
            raise FixtureError(f"backup {name} integrity check failed")
    return True


def build_baseline_images(baseline_ref, run, workspace, owned_refs, candidate_sha=None):
    """Build stock baseline targets from a clean disposable clone of the requested ref."""
    workspace = Path(workspace).resolve()
    workspace.mkdir(mode=0o700, parents=True, exist_ok=True)
    source = workspace / "source"
    recipe = workspace / "recipe"
    recipe.mkdir(mode=0o700)
    git_env = product_fixture._fixture_git_environment()
    run.command(("git", "clone", "--no-checkout", str(product_fixture.ROOT), str(source)),
                label="clone isolated baseline source", env=git_env)
    resolved = run.command(("git", "rev-parse", "--verify", f"{baseline_ref}^{{commit}}"),
                           label="resolve baseline ref in this repository", cwd=source, env=git_env).stdout.strip()
    if not re.fullmatch(r"[0-9a-f]{40}", resolved):
        raise FixtureError("baseline ref does not resolve to a commit")
    if resolved == candidate_sha:
        raise FixtureError("baseline and candidate must be different commits")
    run.command(("git", "checkout", "--detach", resolved), label="checkout isolated baseline", cwd=source,
                env=git_env)
    status = run.command(("git", "status", "--porcelain"), label="verify clean baseline clone", cwd=source,
                         env=git_env).stdout
    if status:
        raise FixtureError("baseline clone is not clean")
    stock_dockerfile = run.command(("git", "show", f"{resolved}:.docker/Dockerfile.roadrunner"),
                                   label="read stock baseline Dockerfile", cwd=source, env=git_env).stdout
    stock_ignore = run.command(("git", "show", f"{resolved}:.docker/Dockerfile.roadrunner.dockerignore"),
                               label="read stock baseline Docker ignore", cwd=source, env=git_env).stdout
    dockerfile = recipe / "Dockerfile.roadrunner"
    dockerfile.write_text(stock_dockerfile)
    ignore = recipe / "Dockerfile.roadrunner.dockerignore"
    ignore.write_text(stock_ignore)
    if dockerfile.read_bytes() != (source / ".docker/Dockerfile.roadrunner").read_bytes():
        raise FixtureError("external baseline Dockerfile differs from stock bytes")
    stock_dockerfile_bytes = stock_dockerfile.encode("utf-8")
    stock_ignore_bytes = stock_ignore.encode("utf-8")
    external_dockerfile_bytes = dockerfile.read_bytes()
    external_ignore_bytes = ignore.read_bytes()
    provenance = {
        "source_sha": resolved,
        "stock_dockerfile_sha256": hashlib.sha256(stock_dockerfile_bytes).hexdigest(),
        "stock_ignore_sha256": hashlib.sha256(stock_ignore_bytes).hexdigest(),
        "external_dockerfile_sha256": hashlib.sha256(external_dockerfile_bytes).hexdigest(),
        "external_ignore_sha256": hashlib.sha256(external_ignore_bytes).hexdigest(),
        "external_recipe_sha256": hashlib.sha256(external_dockerfile_bytes + b"\0" + external_ignore_bytes).hexdigest(),
    }

    suffix = uuid.uuid4().hex
    refs = {"app": f"packio-o3-baseline-{suffix}-app:local",
            "worker": f"packio-o3-baseline-{suffix}-worker:local",
            "ssr": f"packio-o3-baseline-{suffix}-ssr:local"}
    # The caller owns attempted tags before the first build can fail or be interrupted.
    owned_refs.update(refs)
    targets = {"app": "prod", "worker": "prod-worker", "ssr": "prod-ssr"}
    images = {}
    for role, target in targets.items():
        run.command(("docker", "build", "--provenance=true", "--file", str(dockerfile),
                     "--target", target, "--tag", refs[role], str(source)),
                    label=f"build clean baseline {role}", timeout=run.remaining())
        image_id = run.command(("docker", "image", "inspect", "--format", "{{.Id}}", refs[role]),
                               label=f"resolve baseline {role} image").stdout.strip()
        if not re.fullmatch(r"sha256:[0-9a-f]{64}", image_id):
            raise FixtureError(f"baseline {role} image identity is invalid")
        images[role] = image_id
    return refs, images, provenance


def _baseline_fixture_ids(images):
    roles = {"app", "worker", "ssr"}
    if (not isinstance(images, dict) or set(images) != roles
            or any(not isinstance(images[role], str)
                   or not re.fullmatch(r"sha256:[0-9a-f]{64}", images[role]) for role in roles)):
        raise FixtureError("baseline fixture image identity is invalid")
    return {role: images[role] for role in ("app", "worker", "ssr")}


def _run_cleanup_command(args, *, env=None):
    try:
        result = subprocess.run(args, env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
                                check=False, timeout=30)
    except (OSError, subprocess.TimeoutExpired):
        return False
    return result.returncode == 0


def _remove_owned_resource(kind, name):
    patterns = {
        "image": r"packio-o3-baseline-[0-9a-f]{32}-(?:app|worker|ssr):local",
        "container": r"packio-o3-foreign-sentinel-[0-9a-f]{32}-(?:writer|verifier)",
        "volume": r"packio-o3-foreign-sentinel-[0-9a-f]{32}",
    }
    if kind not in patterns or not re.fullmatch(patterns[kind], name):
        return False
    listing = ["docker", kind, "ls", "--quiet"]
    if kind == "image":
        listing.extend(("--filter", f"reference={name}"))
    else:
        if kind == "container":
            listing.append("--all")
        listing.extend(("--filter", f"name=^{('/' if kind == 'container' else '')}{name}$"))
    try:
        found = subprocess.run(listing, stdout=subprocess.PIPE, stderr=subprocess.DEVNULL,
                               text=True, check=False, timeout=30)
    except (OSError, subprocess.TimeoutExpired):
        return False
    if found.returncode:
        return False
    if not found.stdout.strip():
        return True
    if len(found.stdout.strip().splitlines()) != 1:
        return False
    args = ("docker", kind, "rm", "--force", name) if kind == "container" else ("docker", kind, "rm", name)
    return _run_cleanup_command(args)


def _rebind_registry_host(dataset, context):
    if not isinstance(dataset, dict) or not isinstance(dataset.get("registry_host"), str):
        raise FixtureError("restored registry host is invalid")
    host = re.fullmatch(r"(?P<slug>[a-z0-9][a-z0-9-]{0,62})\.registry\.fixture\.test(?::[0-9]{1,5})?",
                        dataset["registry_host"])
    base = re.fullmatch(r"registry\.fixture\.test:(?P<port>[0-9]{1,5})",
                        str(context.get("registry_base_domain", "")))
    if not host or not base or not 1 <= int(base.group("port")) <= 65535:
        raise FixtureError("restored registry host is invalid")
    rebound = dict(dataset)
    rebound["registry_host"] = f"{host.group('slug')}.{context['registry_base_domain']}"
    return rebound


def _verify_reset_data_volumes(context):
    check = product_fixture._empty_paths_check(include_archives=False)
    compose = product_fixture._compose(context["project"])
    context["_run"].command(
        (*compose, "run", "--rm", "--no-deps", "--entrypoint", "/bin/sh", "app", "-ec", check),
        label="verify restored fixture has no stale cache, limiter, or session files",
        cwd=product_fixture.ROOT, env=context["_compose_env"])


def _check_registry_token_decryption(context, config, *, expected):
    recovery_token = config.get("recovery_token")
    if (not isinstance(recovery_token, dict)
            or set(recovery_token) != {"id", "value", "organization_slug"}
            or not re.fullmatch(r"[0-9a-f-]{36}", str(recovery_token.get("id", "")))
            or not isinstance(recovery_token.get("value"), str) or not recovery_token["value"]
            or not isinstance(recovery_token.get("organization_slug"), str)):
        raise FixtureError("protected recovery credential identity is invalid")
    script = (
        "require 'vendor/autoload.php'; $c=require 'config/container.php'; "
        "$d=json_decode(stream_get_contents(STDIN), true); "
        "try { $r=$c->get(\\Packio\\Identity\\Domain\\Repository\\RegistryAccessTokenRepositoryInterface::class); "
        "$e=$r->findById($d['id']); if (!$e) { echo 'TOKEN_MISSING'; exit(0); } "
        "$s=$c->get(\\Packio\\Identity\\Application\\Security\\RegistryAccessTokenSecrets::class)"
        "->decryptToken($e); if (null === $s) { echo 'DECRYPT_REJECTED'; } "
        "elseif (is_string($s) && hash_equals($d['value'], $s)) { echo 'MATCH'; } else { echo 'MISMATCH'; } "
        "} catch (\\Throwable $e) { echo 'DECRYPT_ERROR'; }"
    )
    compose = product_fixture._compose(context["project"])
    result = context["_run"].command(
        (*compose, "exec", "-T", "app", "php", "-r", script),
        label="verify stored encrypted registry credential with fixture key",
        cwd=product_fixture.ROOT, env=context["_compose_env"],
        input=json.dumps({"id": recovery_token["id"], "value": recovery_token["value"]}), check=False)
    expected_output = "MATCH" if expected == "match" else "DECRYPT_REJECTED"
    if result.returncode or result.stdout.strip() != expected_output:
        raise FixtureError("stored encrypted credential did not match the expected key outcome")
    return True


def _sha256(path):
    digest = hashlib.sha256()
    with Path(path).open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def capture_backup(context, dataset, destination):
    """Stop writers, capture a PostgreSQL custom dump and the exact archive volume."""
    destination = Path(destination).resolve()
    destination.mkdir(mode=0o700, parents=True)
    protected = destination.parent / "protected-configuration"
    protected.mkdir(mode=0o700)
    run = context["_run"]
    env = context["_compose_env"]
    compose = product_fixture._compose(context["project"])
    run.command((*compose, "stop", "workers", "app", "ssr"), label="quiesce baseline writers and traffic",
                cwd=product_fixture.ROOT, env=env)
    running_jobs = run.command((*compose, "exec", "-T", "postgres", "psql", "-U", "packio", "-d", "packio",
                                "-Atqc", "SELECT count(*) FROM jobs WHERE status='running'"),
                               label="verify no active jobs remain before backup", cwd=product_fixture.ROOT,
                               env=env).stdout.strip()
    if running_jobs != "0":
        raise FixtureError("backup was blocked by active jobs")
    retry_id = context.get("_system_mail_job_id", "")
    if not re.fullmatch(r"[0-9a-f-]{36}", retry_id):
        raise FixtureError("backup mail retry identity is unavailable")
    retry_state = product_fixture._sql(context,
        "SELECT status || ':' || attempt_count FROM jobs WHERE uuid='" + retry_id + "'", run)
    if retry_state != f"pending:{context.get('_system_mail_retry_attempt')}":
        raise FixtureError("backup was blocked because the queued mail retry changed")
    encoded_dump = run.command((*compose, "exec", "-T", "postgres", "sh", "-ec",
                                'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" '
                                "--format=custom --no-owner --no-acl > /tmp/o3.dump && "
                                "base64 /tmp/o3.dump | tr -d '\\n'"),
                               label="capture PostgreSQL recovery dump", cwd=product_fixture.ROOT,
                               env=env).stdout.strip()
    try:
        dump = base64.b64decode(encoded_dump, validate=True)
    except (ValueError, base64.binascii.Error) as error:
        raise FixtureError("PostgreSQL recovery dump encoding is invalid") from error
    if not dump.startswith(b"PGDMP"):
        raise FixtureError("PostgreSQL recovery dump format is invalid")
    run.command((*compose, "exec", "-T", "postgres", "pg_restore", "--list", "/tmp/o3.dump"),
                label="validate PostgreSQL recovery dump inventory", cwd=product_fixture.ROOT, env=env)
    paths = {"database": destination / "database.dump", "archives": destination / "archives.tar",
             "key": protected / "app-key", "config": protected / "config.json"}
    paths["database"].write_bytes(dump)
    paths["database"].chmod(0o600)

    app_id = run.command((*compose, "ps", "--all", "-q", "app"), label="resolve stopped baseline app",
                         cwd=product_fixture.ROOT, env=env).stdout.strip()
    if not re.fullmatch(r"[0-9a-f]{12,64}", app_id):
        raise FixtureError("stopped baseline app identity is invalid")
    details = json.loads(run.command(("docker", "inspect", app_id), label="inspect baseline archive mount").stdout)
    if len(details) != 1 or details[0].get("Config", {}).get("Labels", {}).get(
            "com.docker.compose.project") != context["project"]:
        raise FixtureError("baseline app ownership could not be proven")
    archive_mounts = [mount for mount in details[0].get("Mounts", [])
                      if mount.get("Destination") == "/app/data/package-archives" and mount.get("Type") == "volume"]
    if len(archive_mounts) != 1 or not re.fullmatch(r"[A-Za-z0-9_.-]+", archive_mounts[0].get("Name", "")):
        raise FixtureError("baseline package archive volume could not be identified")
    archive_volume = archive_mounts[0]["Name"]
    archive_encoded = run.command((
        "docker", "run", "--rm", "--network", "none", "--read-only", "--user", "0",
        "--name", f"{context['project']}-archive-snapshot",
        "--label", f"com.docker.compose.project={context['project']}",
        "--mount", f"type=volume,source={archive_volume},target=/archives,readonly",
        "--entrypoint", "/bin/sh", context["images"]["app"], "-ec",
        "tar -cpf - -C /archives . | base64 | tr -d '\\n'",
    ), label="snapshot stopped package archive volume").stdout.strip()
    try:
        archive_bytes = base64.b64decode(archive_encoded, validate=True)
        with tarfile.open(fileobj=__import__("io").BytesIO(archive_bytes), mode="r:") as archive:
            for member in archive.getmembers():
                name = Path(member.name)
                if (name.is_absolute() or ".." in name.parts or not (member.isfile() or member.isdir())
                        or member.issym() or member.islnk()):
                    raise FixtureError("package archive snapshot contains an unsafe entry")
    except (ValueError, base64.binascii.Error, tarfile.TarError) as error:
        raise FixtureError("package archive snapshot is invalid") from error
    paths["archives"].write_bytes(archive_bytes)
    paths["archives"].chmod(0o600)

    private = Path(context["private_dir"])
    paths["key"].write_text(context["private_key"])
    paths["key"].chmod(0o600)
    schema_tables = run.command((*compose, "exec", "-T", "postgres", "psql", "-U", "packio",
                                 "-d", "packio", "-Atqc",
                                 "SELECT table_name FROM information_schema.tables WHERE table_schema='public' ORDER BY table_name"),
                                label="record backup schema identity", cwd=product_fixture.ROOT,
                                env=env).stdout.strip().splitlines()
    migrations = run.command((*compose, "exec", "-T", "postgres", "psql", "-U", "packio", "-d", "packio",
                              "-Atqc", "SELECT migration || ':' || COALESCE(time_executed::text,'') "
                              "FROM migrations ORDER BY migration"),
                             label="record applied migration identity", cwd=product_fixture.ROOT,
                             env=env).stdout.strip().splitlines()
    job_counts = run.command((*compose, "exec", "-T", "postgres", "psql", "-U", "packio", "-d", "packio",
                              "-Atqc", "SELECT status || ':' || count(*) FROM jobs GROUP BY status ORDER BY status"),
                             label="record backup job state", cwd=product_fixture.ROOT,
                             env=env).stdout.strip().splitlines()
    postgres_version = run.command((*compose, "exec", "-T", "postgres", "psql", "-U", "packio", "-d", "packio",
                                    "-Atqc", "SHOW server_version"),
                                   label="record backup PostgreSQL version", cwd=product_fixture.ROOT,
                                   env=env).stdout.strip()
    if not re.fullmatch(r"[0-9]+\.[0-9]+(?:\.[0-9]+)?(?: [^\r\n]+)?", postgres_version):
        raise FixtureError("backup PostgreSQL version identity is invalid")
    try:
        fixture_identity = json.loads((private / "flow-state.json").read_text())
        user_email, user_id = fixture_identity["email"], fixture_identity["user_id"]
    except (OSError, KeyError, TypeError, json.JSONDecodeError) as error:
        raise FixtureError("backup restored-user identity is unavailable") from error
    config_data = {
        "backup_set_id": uuid.uuid4().hex,
        "source_sha": context["candidate_sha"],
        "image_ids": context["image_report"]["images"],
        "schema_tables": schema_tables,
        "migrations": migrations,
        "job_status_counts": job_counts,
        "jobs_sha256": _job_state_digest(context),
        "postgres_version": postgres_version,
        "system_mail_job_id": context.get("_system_mail_job_id"),
        "system_mail_attempt_count": context.get("_system_mail_retry_attempt"),
        "recovery_token": context.get("_recovery_registry_token"),
        "deletion_state": context.get("_deletion_state"),
        "user_email": user_email,
        "user_id": user_id,
        "local_config_b64": base64.b64encode((private / "local.php").read_bytes()).decode("ascii"),
        "dataset": {key: value for key, value in dataset.items() if key != "token_path"},
    }
    paths["config"].write_text(json.dumps(config_data, sort_keys=True, separators=(",", ":")))
    paths["config"].chmod(0o600)
    stored_package = Path(context.get("_stored_archive_path", ""))
    if not stored_package.is_file() or stored_package.is_symlink():
        raise FixtureError("baseline stored package bytes are unavailable")
    shutil.copyfile(stored_package, destination / "stored-package.zip")
    (destination / "stored-package.zip").chmod(0o600)
    manifest = {
        "format": 1,
        "source_sha": context["candidate_sha"],
        "database_sha256": _sha256(paths["database"]),
        "archives_sha256": _sha256(paths["archives"]),
        "key_sha256": _sha256(paths["key"]),
        "config_sha256": _sha256(paths["config"]),
    }
    validate_backup_set(manifest, paths)
    manifest_path = protected / "manifest.json"
    manifest_path.write_text(json.dumps(manifest, sort_keys=True, separators=(",", ":")))
    manifest_path.chmod(0o600)
    return manifest, paths


def queue_deletion_lifecycle(context):
    """Create a real pending organization-deletion job while the fixture worker is stopped."""
    run = context["_run"]
    private = Path(context["private_dir"])
    cookies = product_fixture._session_cookies(private)
    props, csrf = product_fixture._app_json(context, cookies, "/organizations")
    name = f"O3 deletion {uuid.uuid4().hex[:12]}"
    product_fixture._app_post(context, cookies, "/organizations", {"name": name}, csrf,
                              "create disposable deletion lifecycle")
    props, csrf = product_fixture._app_json(context, cookies, "/organizations")
    try:
        org = next(item for item in props.get("organizations", []) if item.get("name") == name)
        organization_id, slug = org["id"], org["slug"]
    except (KeyError, StopIteration, TypeError) as error:
        raise FixtureError("disposable deletion organization was not created") from error
    if not re.fullmatch(r"[0-9a-f-]{36}", str(organization_id)) or not isinstance(slug, str):
        raise FixtureError("disposable deletion organization identity is invalid")
    _, csrf = product_fixture._app_json(context, cookies, f"/organization/{slug}/settings")
    product_fixture._app_post(context, cookies, f"/organization/{slug}/settings/delete",
                              {"confirmationName": name}, csrf, "queue organization deletion lifecycle")
    organization = product_fixture._sql(
        context,
        "SELECT status || '|' || deletion_run_id FROM organizations WHERE uuid='" + organization_id + "'",
        run).split("|", 1)
    if len(organization) != 2 or organization[0] != "deleting" or not re.fullmatch(
            r"[0-9a-f-]{36}", organization[1]):
        raise FixtureError("organization deletion lifecycle was not persisted")
    job_row = product_fixture._sql(
        context,
        "SELECT uuid::text || '|' || status FROM jobs WHERE type='organization.delete' "
        "AND organization_id='" + organization_id + "' ORDER BY created_at DESC LIMIT 1", run)
    if "|" not in job_row:
        raise FixtureError("organization deletion job was not persisted")
    job_id, status = job_row.split("|", 1)
    if status != "pending" or not re.fullmatch(r"[0-9a-f-]{36}", job_id):
        raise FixtureError("organization deletion job is not pending")
    context["_deletion_state"] = {"organization_id": organization_id,
                                  "deletion_run_id": organization[1], "job_id": job_id}
    return context["_deletion_state"]


def _verify_deletion_lifecycle(context, expected):
    organization_id = expected.get("organization_id", "") if isinstance(expected, dict) else ""
    job_id = expected.get("job_id", "") if isinstance(expected, dict) else ""
    if not re.fullmatch(r"[0-9a-f-]{36}", organization_id) or not re.fullmatch(r"[0-9a-f-]{36}", job_id):
        raise FixtureError("backup deletion lifecycle identity is invalid")
    organization = product_fixture._sql(
        context,
        "SELECT status || '|' || deletion_run_id FROM organizations WHERE uuid='" + organization_id + "'",
        context["_run"]).split("|", 1)
    job = product_fixture._sql(
        context, "SELECT uuid::text || '|' || status FROM jobs WHERE uuid='" + job_id + "'",
        context["_run"]).split("|", 1)
    if (len(organization) != 2 or organization != ["deleting", expected.get("deletion_run_id")]
            or job != [job_id, "pending"]):
        raise FixtureError("restored organization deletion lifecycle differs from backup")


def _create_post_backup_write(context):
    private = Path(context["private_dir"])
    cookies = product_fixture._session_cookies(private)
    _, csrf = product_fixture._app_json(context, cookies, "/organizations")
    name = f"O3 rollback write {uuid.uuid4().hex}"
    product_fixture._app_post(context, cookies, "/organizations", {"name": name}, csrf,
                              "create post-backup rollback write")
    count = product_fixture._sql(
        context, "SELECT count(*) FROM organizations WHERE name='" + name + "'", context["_run"])
    if count != "1":
        raise FixtureError("post-backup write was not persisted before rollback")
    return name


def queue_encrypted_system_mail(context):
    """Leave one real encrypted password-reset mail job pending for the backup snapshot."""
    flow = json.loads((Path(context["private_dir"]) / "flow-state.json").read_text())
    email = flow.get("email")
    if not isinstance(email, str) or not re.fullmatch(r"e2-[0-9a-f-]+@example\.invalid", email):
        raise FixtureError("fixture mail identity is invalid")
    run = context["_run"]
    compose = product_fixture._compose(context["project"])
    env = context["_compose_env"]
    def idle_queue():
        rows = product_fixture._sql(context,
            "SELECT status || ':' || count(*) FROM jobs WHERE type='identity.auth_mail' "
            "GROUP BY status ORDER BY status", run).splitlines()
        return rows if not any(row.startswith(("pending:", "running:")) for row in rows) else None

    product_fixture.poll_until(run, "system mail queue idle before recovery test", idle_queue, interval=0.5)
    run.command((*compose, "stop", "workers"), label="stop system mail consumers for recovery test",
                cwd=product_fixture.ROOT, env=env)
    php = ("require 'vendor/autoload.php'; $c = require 'config/container.php'; "
           f"$c->get(\\Packio\\Identity\\Application\\Port\\AuthMailQueuePort::class)->passwordReset({json.dumps(email)}, null, null);")
    run.command((*compose, "exec", "-T", "app", "php", "-r", php),
                label="queue protected system mail for backup", cwd=product_fixture.ROOT, env=env)
    job_states = product_fixture._sql(context,
        "SELECT status || ':' || count(*) FROM jobs WHERE type='identity.auth_mail' "
        "GROUP BY status ORDER BY status", run).splitlines()
    pending_count = sum(int(row.split(":", 1)[1]) for row in job_states if row.startswith("pending:"))
    running_count = sum(int(row.split(":", 1)[1]) for row in job_states if row.startswith("running:"))
    if pending_count != 1 or running_count != 0:
        raise FixtureError("stopped system mail consumer did not preserve a pending retryable job")
    job_id = run.command((*compose, "exec", "-T", "postgres", "psql", "-U", "packio", "-d", "packio",
                          "-Atqc", "SELECT uuid::text FROM jobs WHERE type='identity.auth_mail' "
                          "AND status='pending' ORDER BY created_at DESC LIMIT 1"),
                         label="identify queued system mail job", cwd=product_fixture.ROOT,
                         env=env).stdout.strip()
    if not re.fullmatch(r"[0-9a-f-]{36}", job_id):
        raise FixtureError("queued system mail job identity is unavailable")
    run.command((*compose, "stop", "mailpit"), label="stop mail transport for recovery retry control",
                cwd=product_fixture.ROOT, env=env)
    queued = product_fixture._sql(context,
        "SELECT count(*) FROM jobs WHERE status='running' OR (status='pending' AND uuid::text <> '"
        + job_id + "')", run)
    if queued != "0":
        raise FixtureError("mail retry control requires an otherwise idle stopped queue")
    run.command((*compose, "exec", "-T", "app", "php", "vendor/bin/laminas", "worker:run",
                 "--max-jobs", "1", "--stop-when-empty", "--timeout", "1", "--memory-limit", "512M",
                 "--worker-id", f"{context['project']}-retry-control"),
                label="run one native mail delivery attempt with consumers stopped",
                cwd=product_fixture.ROOT, env=env, timeout=45)
    state = product_fixture._sql(context,
        "SELECT status || ':' || attempt_count FROM jobs WHERE uuid='" + job_id + "'", run)
    match = re.fullmatch(r"pending:([1-9][0-9]*)", state)
    running = product_fixture._sql(context, "SELECT count(*) FROM jobs WHERE status='running'", run)
    if not match or running != "0":
        raise FixtureError("native one-job control did not preserve a pending mail retry")
    attempts = int(match.group(1))
    run.command((*compose, "start", "mailpit"), label="restore mail transport after retry control",
                cwd=product_fixture.ROOT, env=env)
    context["_system_mail_retry_attempt"] = attempts
    context["_system_mail_job_id"] = job_id
    context["_system_mail_email"] = email
    return job_id


def _restored_mail_messages(context, email):
    run = context["_run"]
    query = urllib.parse.urlencode({"query": f"to:{email}"})
    request = urllib.request.Request(f"{context['mailpit_url']}/api/v1/search?{query}",
                                     headers={"Accept": "application/json"})
    try:
        with product_fixture._request_deadline(run):
            with urllib.request.urlopen(request, timeout=min(10, run.remaining())) as response:
                return json.loads(response.read())
    except (OSError, urllib.error.URLError, json.JSONDecodeError) as error:
        raise FixtureError("restored system mail could not be verified") from error


def verify_restored_system_mail(context, config):
    """Run the restored queue consumer and verify Mailpit received the real reset message."""
    job_id = config.get("system_mail_job_id")
    email = config.get("user_email")
    if not re.fullmatch(r"[0-9a-f-]{36}", str(job_id or "")) or not isinstance(email, str):
        raise FixtureError("restored system mail identity is invalid")
    if (isinstance(config.get("system_mail_attempt_count"), bool)
            or not isinstance(config.get("system_mail_attempt_count"), int)
            or config["system_mail_attempt_count"] < 1):
        raise FixtureError("restored mail retry evidence is missing")
    run = context["_run"]
    compose = product_fixture._compose(context["project"])
    env = context["_compose_env"]
    run.command((*compose, "up", "--detach", "--no-build", "--pull", "never", "--wait",
                 "--wait-timeout", "120", "workers"), label="recover restored system mail consumer",
                cwd=product_fixture.ROOT, env=env)

    def completed():
        value = run.command((*compose, "exec", "-T", "postgres", "psql", "-U", "packio", "-d", "packio",
                             "-Atqc", f"SELECT status FROM jobs WHERE uuid='{job_id}'"),
                            label="check restored system mail job", cwd=product_fixture.ROOT, env=env).stdout.strip()
        return value == "completed"

    product_fixture.poll_until(run, "restored encrypted system mail job", completed, interval=0.5)
    messages = _restored_mail_messages(context, email)
    items = messages.get("messages", []) if isinstance(messages, dict) else []
    if not any(email.lower() in json.dumps(message.get("To", [])).lower() for message in items):
        raise FixtureError("restored system mail was not delivered to the fixture inbox")
    run.command((*compose, "stop", "workers"), label="stop restored mail consumer after check",
                cwd=product_fixture.ROOT, env=env)
    return True


def _archive_member_matches(path, expected_sha):
    matches = 0
    with tarfile.open(path, "r:") as archive:
        for member in archive.getmembers():
            if not member.isfile():
                continue
            stream = archive.extractfile(member)
            if stream is None:
                raise FixtureError("package archive snapshot member is unreadable")
            digest = hashlib.sha256()
            for chunk in iter(lambda: stream.read(1024 * 1024), b""):
                digest.update(chunk)
            if digest.hexdigest() == expected_sha:
                matches += 1
    return matches == 1


def _copy_restored_archive(context, backup_dir, expected_sha):
    source = Path(backup_dir) / "stored-package.zip"
    if source.is_symlink() or not source.is_file() or _sha256(source) != expected_sha:
        raise FixtureError("backup private package bytes are missing or mismatched")
    target = Path(context["private_dir"]) / "restored-package.zip"
    if target.exists() or target.is_symlink():
        raise FixtureError("restored private package path already exists")
    shutil.copyfile(source, target)
    target.chmod(0o600)
    context["_stored_archive_path"] = str(target)
    return target


def restore_backup(context, manifest, paths, backup_dir, *, start_app=True):
    """Restore the verified dump and archives into this fixture's own project volumes."""
    validate_backup_set(manifest, paths)
    run = context["_run"]
    env = context["_compose_env"]
    compose = product_fixture._compose(context["project"])
    run.command((*compose, "stop", "workers", "app"), label="close restored fixture writers",
                cwd=product_fixture.ROOT, env=env)
    app_id = run.command((*compose, "ps", "--all", "-q", "app"), label="resolve restore app container",
                         cwd=product_fixture.ROOT, env=env).stdout.strip()
    if not re.fullmatch(r"[0-9a-f]{12,64}", app_id):
        raise FixtureError("restore app container identity is invalid")
    details = json.loads(run.command(("docker", "inspect", app_id), label="inspect restore volume ownership").stdout)
    if len(details) != 1 or details[0].get("Config", {}).get("Labels", {}).get(
            "com.docker.compose.project") != context["project"]:
        raise FixtureError("restore project ownership could not be proven")
    archive_mounts = [mount for mount in details[0].get("Mounts", [])
                      if mount.get("Destination") == "/app/data/package-archives" and mount.get("Type") == "volume"]
    if len(archive_mounts) != 1:
        raise FixtureError("restore archive volume could not be identified")
    volume = archive_mounts[0].get("Name", "")
    if not re.fullmatch(r"[A-Za-z0-9_.-]+", volume):
        raise FixtureError("restore archive volume identity is invalid")

    encoded_dump = base64.b64encode(Path(paths["database"]).read_bytes()).decode("ascii")
    restore_sql = ('dropdb -U "$POSTGRES_USER" "$POSTGRES_DB" && '
                   'createdb -U "$POSTGRES_USER" -O "$POSTGRES_USER" "$POSTGRES_DB" && '
                   'base64 -d > /tmp/o3.dump && '
                   'pg_restore --exit-on-error --no-owner --no-acl '
                   '-U "$POSTGRES_USER" -d "$POSTGRES_DB" /tmp/o3.dump')
    run.command((*compose, "exec", "-T", "postgres", "sh", "-ec", restore_sql),
                label="restore PostgreSQL backup", cwd=product_fixture.ROOT, env=env,
                input=encoded_dump)
    run.command(("docker", "run", "--rm", "--network", "none", "--user", "0",
                 "--name", f"{context['project']}-archive-restore",
                 "--label", f"com.docker.compose.project={context['project']}",
                 "--mount", f"type=volume,source={volume},target=/archives",
                 "--mount", f"type=bind,source={Path(paths['archives']).resolve()},target=/backup/archives.tar,readonly",
                 "--entrypoint", "/bin/sh", context["images"]["app"], "-ec",
                 "find /archives -mindepth 1 -maxdepth 1 -exec rm -rf -- {} + && "
                 "tar -xpf /backup/archives.tar -C /archives"),
                label="restore private package archive volume")
    _verify_reset_data_volumes(context)
    config = json.loads(Path(paths["config"]).read_text())
    dataset = config.get("dataset")
    if (not isinstance(dataset, dict) or not isinstance(config.get("schema_tables"), list)
            or not isinstance(config.get("migrations"), list)
            or not isinstance(config.get("job_status_counts"), list)
            or not re.fullmatch(r"[0-9a-f]{64}", str(config.get("jobs_sha256", "")))
            or not re.fullmatch(r"[0-9a-f-]{36}", str(config.get("system_mail_job_id", "")))
            or not isinstance(config.get("deletion_state"), dict)
            or not isinstance(config.get("recovery_token"), dict)
            or not re.fullmatch(r"[0-9a-f-]{36}", str(config.get("user_id", "")))
            or not isinstance(config.get("user_email"), str)
            or not isinstance(config.get("local_config_b64"), str)):
        raise FixtureError("backup dataset identity is missing")
    dataset = _rebind_registry_host(dataset, context)
    dataset["token_path"] = str(Path(context["private_dir"]) / "registry-token.json")
    _copy_restored_archive(context, backup_dir, dataset.get("archive_sha256"))
    context["dataset"] = dataset
    context["_restored_user"] = {"email": config["user_email"], "id": config["user_id"]}
    context["_recovery_registry_token"] = config["recovery_token"]
    actual_tables = run.command((*compose, "exec", "-T", "postgres", "psql", "-U", "packio", "-d", "packio",
                                 "-Atqc", "SELECT table_name FROM information_schema.tables "
                                 "WHERE table_schema='public' ORDER BY table_name"),
                                label="verify restored schema identity", cwd=product_fixture.ROOT,
                                env=env).stdout.strip().splitlines()
    actual_migrations = run.command((*compose, "exec", "-T", "postgres", "psql", "-U", "packio", "-d", "packio",
                                     "-Atqc", "SELECT migration || ':' || COALESCE(time_executed::text,'') "
                                     "FROM migrations ORDER BY migration"),
                                    label="verify restored migration identity", cwd=product_fixture.ROOT,
                                    env=env).stdout.strip().splitlines()
    actual_jobs = run.command((*compose, "exec", "-T", "postgres", "psql", "-U", "packio", "-d", "packio",
                               "-Atqc", "SELECT status || ':' || count(*) FROM jobs GROUP BY status ORDER BY status"),
                              label="verify restored job state", cwd=product_fixture.ROOT,
                              env=env).stdout.strip().splitlines()
    if (actual_tables != config["schema_tables"] or actual_migrations != config["migrations"]
            or actual_jobs != config["job_status_counts"]
            or _job_state_digest(context) != config["jobs_sha256"]):
        raise FixtureError("restored database schema or migration identity differs from the backup")
    _verify_deletion_lifecycle(context, config["deletion_state"])
    try:
        local_config = base64.b64decode(config["local_config_b64"], validate=True).decode("utf-8")
    except (ValueError, UnicodeDecodeError) as error:
        raise FixtureError("protected deployment configuration is invalid") from error
    config_pattern = re.compile(
        r"\A<\?php\ndeclare\(strict_types=1\);\nreturn \['outbound_http' => \['endpoints' => \["
        r"(?P<endpoint>'https://[^']+') => \['(?P<address>[0-9a-fA-F:.]+)'\]\]\]\];\n\Z"
    )
    match = config_pattern.fullmatch(local_config)
    if not match or match.group("endpoint") != repr(f"https://{context['provider_host']}"):
        raise FixtureError("protected deployment configuration is invalid")
    # Preserve the fixture's exact endpoint allowlist and adapt only its isolated provider IP.
    product_fixture._write_local_config(Path(context["private_dir"]) / "local.php",
                                        context["provider_host"], context["provider_ip"])
    if not _archive_member_matches(paths["archives"], dataset["archive_sha256"]):
        raise FixtureError("restored archive snapshot does not contain the exact fixture package")
    if start_app:
        run.command((*compose, "up", "--detach", "--no-build", "--pull", "never", "--wait",
                     "--wait-timeout", "240", "app", "ssr"),
                    label="start restored app with traffic gate closed", cwd=product_fixture.ROOT, env=env)
        context["resolved_images"] = product_fixture._resolved_image_ids(
            run.command((*compose, "ps", "-q"), label="resolve restored service containers",
                        cwd=product_fixture.ROOT, env=env).stdout.strip().splitlines(), run)
        if context["resolved_images"].get("app") != context["image_report"]["images"]["app"]["id"]:
            raise FixtureError("restored app does not match its verified image")
        fresh_login(context, config["user_email"], "Fixture!Password2026", config["user_id"])
    return dataset


def _is_expected_migration_failure(result):
    return (result.returncode == 1
            and "Running database migrations (attempt 1/1)..." in result.stdout
            and "Database migrations failed after 1 attempts." in result.stdout
            and "Database migrations completed." not in result.stdout)


def _ready(context):
    status, _, _ = product_fixture._fixture_request(
        context, "app.fixture.test", "/ready", label="check restored readiness")
    return status


def _ready_or_closed(context):
    try:
        return _ready(context)
    except FixtureError:
        return None


def _job_state_digest(context):
    value = product_fixture._sql(context,
        "SELECT COALESCE(json_agg(json_build_array(uuid::text,organization_id,type,payload,status,"
        "priority,scheduled_at,started_at,completed_at,attempt_count,max_attempts,idempotency_key,"
        "worker_id) ORDER BY uuid)::text,'[]') FROM jobs", context["_run"])
    return hashlib.sha256(value.encode("utf-8")).hexdigest()


def _job_status_counts(context):
    compose = product_fixture._compose(context["project"])
    return context["_run"].command(
        (*compose, "exec", "-T", "postgres", "psql", "-U", "packio", "-d", "packio",
         "-Atqc", "SELECT status || ':' || count(*) FROM jobs GROUP BY status ORDER BY status"),
        label="read restored job state", cwd=product_fixture.ROOT,
        env=context["_compose_env"]).stdout.strip().splitlines()


def _write_report(path, report):
    required = {"schema", "baseline_ref", "baseline_sha", "candidate_sha", "image_refs", "images", "hashes",
                "postgres_version", "started_at", "results", "duration_seconds", "provenance"}
    if set(report) != required:
        raise FixtureError("recovery report shape is invalid")
    # The baseline SHA is empty only when the rehearsal failed before the baseline ref was resolved.
    if (not re.fullmatch(r"[0-9a-f]{40}", report["candidate_sha"])
            or not re.fullmatch(r"(?:[0-9a-f]{40})?", str(report["baseline_sha"]))
            or report["baseline_sha"] == report["candidate_sha"]
            or (report["results"].get("baseline_product") == "passed" and not report["baseline_sha"])):
        raise FixtureError("recovery report source identity is invalid")
    roles = {"app", "worker", "ssr"}
    if (not isinstance(report["images"], dict)
            or set(report["images"]) != {"baseline", "candidate"}
            or any(not isinstance(group, dict) or not set(group).issubset(roles)
                   for group in report["images"].values())
            or any(not re.fullmatch(r"sha256:[0-9a-f]{64}", image_id)
                   for group in report["images"].values() for image_id in group.values())):
        raise FixtureError("recovery report image identity is invalid")
    if (not isinstance(report["image_refs"], dict)
            or set(report["image_refs"]) != {"baseline", "candidate"}
            or any(not isinstance(group, dict) or not set(group).issubset(roles)
                   for group in report["image_refs"].values())
            or any(not isinstance(reference, str) or len(reference) > 256
                   or not re.fullmatch(r"[A-Za-z0-9][A-Za-z0-9._:/-]*", reference)
                   for group in report["image_refs"].values() for reference in group.values())):
        raise FixtureError("recovery report image reference is invalid")
    if (report["results"].get("baseline_product") == "passed"
            and set(report["images"]["baseline"]) != roles):
        raise FixtureError("successful baseline report lacks complete image identity")
    if (report["results"].get("candidate_readiness") == "passed"
            and set(report["images"]["candidate"]) != roles):
        raise FixtureError("successful candidate report lacks complete image identity")
    provenance = report["provenance"]
    if provenance is not None:
        provenance_keys = {"source_sha", "stock_dockerfile_sha256", "stock_ignore_sha256",
                           "external_dockerfile_sha256", "external_ignore_sha256",
                           "external_recipe_sha256"}
        if not isinstance(provenance, dict) or set(provenance) != provenance_keys:
            raise FixtureError("recovery baseline provenance is invalid")
        if (not report["baseline_sha"] or provenance["source_sha"] != report["baseline_sha"]
                or any(not re.fullmatch(r"[0-9a-f]{64}", str(provenance.get(key, "")))
                       for key in provenance_keys - {"source_sha"})
                or provenance["external_dockerfile_sha256"] != provenance["stock_dockerfile_sha256"]
                or provenance["external_ignore_sha256"] != provenance["stock_ignore_sha256"]):
            raise FixtureError("recovery baseline provenance is invalid")
    elif report["results"].get("baseline_product") == "passed":
        raise FixtureError("successful baseline report lacks build provenance")
    safe_hashes = {"database_sha256", "archives_sha256", "schema_identity_sha256", "jobs_sha256"}
    if (not isinstance(report["hashes"], dict) or not set(report["hashes"]).issubset(safe_hashes)
            or any(not re.fullmatch(r"[0-9a-f]{64}", digest) for digest in report["hashes"].values())):
        raise FixtureError("recovery report hash is invalid")
    if not isinstance(report["results"], dict) or any(value not in {"passed", "failed"}
                                                        for value in report["results"].values()):
        raise FixtureError("recovery report result is invalid")
    if (report["postgres_version"] and not re.fullmatch(r"[0-9]+\.[0-9]+(?:\.[0-9]+)?(?: [^\r\n]+)?",
                                                         report["postgres_version"])):
        raise FixtureError("recovery report PostgreSQL version is invalid")
    try:
        datetime.fromisoformat(report["started_at"].replace("Z", "+00:00"))
    except (AttributeError, TypeError, ValueError) as error:
        raise FixtureError("recovery report timestamp is invalid") from error
    target = Path(path)
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(json.dumps(report, sort_keys=True, indent=2) + "\n")
    target.chmod(0o600)


def _candidate_report_image_ids(context):
    images = report_image_ids(context)
    return {role: images[role] for role in ("app", "worker", "ssr")}


def _wrong_fixture_key(original):
    # Numeric-only values become integers in this version's native env() parser.
    return ("b" if original == "a" * 64 else "a") * 64


def main(argv=None):
    args = parser().parse_args(argv)
    product_fixture.validate_candidate_sha(args.expected_sha, args.expected_sha)
    report_path = Path(args.report)
    report_path.unlink(missing_ok=True)
    started = time.monotonic()
    result_names = ("baseline_product", "backup", "negative_controls", "restored_baseline",
                    "system_mail_recovery", "system_mail_retry", "encrypted_credentials",
                    "wrong_key_decryption", "deletion_lifecycle", "upgrade_failure_gate",
                    "candidate_job_recovery",
                    "candidate_upgrade", "candidate_readiness",
                    "private_install", "rollback", "new_writes_policy", "cleanup", "foreign_sentinel")
    results = {name: "failed" for name in result_names}
    baseline_images = {}
    candidate_ids = {}
    candidate_refs = {"app": args.app_image, "worker": args.worker_image, "ssr": args.ssr_image}
    manifest = None
    contexts = []
    image_refs = {}
    baseline_provenance = None
    baseline_sha = ""
    post_backup_write = None
    sentinel = f"packio-o3-foreign-sentinel-{uuid.uuid4().hex}"
    sentinel_created = False
    sentinel_attempted = False
    sentinel_verifier_image = None
    error = None
    cleanup_failed = False
    final_hashes = {}
    postgres_version = ""
    started_at = datetime.now(timezone.utc).isoformat()
    run = Run(timeout=3600)
    temporary = tempfile.TemporaryDirectory(prefix="packio-o3-recovery-")
    workspace = Path(temporary.name)
    workspace.chmod(0o700)
    backup_dir = workspace / "backup-set"
    previous_term_handler = signal.getsignal(signal.SIGTERM)
    previous_int_handler = signal.getsignal(signal.SIGINT)

    def interrupted(_signum, _frame):
        raise FixtureError("recovery rehearsal interrupted")

    def interrupted_cleanup(_signum, _frame):
        nonlocal error
        if error is None:
            error = FixtureError("recovery rehearsal interrupted during cleanup")

    signal.signal(signal.SIGTERM, interrupted)
    try:
        image_refs, baseline_images, baseline_provenance = build_baseline_images(
            args.baseline_ref, run, workspace / "build", image_refs, args.expected_sha)
        baseline_sha = baseline_provenance["source_sha"] if baseline_provenance else ""
        baseline_fixture_images = _baseline_fixture_ids(baseline_images)
        sentinel_attempted = True
        sentinel_id = run.command(("docker", "volume", "create", sentinel),
                                  label="create foreign sentinel volume").stdout.strip()
        if sentinel_id != sentinel:
            raise FixtureError("foreign sentinel identity is invalid")
        sentinel_created = True
        run.command(("docker", "run", "--rm", "--network", "none", "--user", "0",
                     "--name", f"{sentinel}-writer",
                     "--volume", f"{sentinel}:/sentinel",
                     "--entrypoint", "/bin/sh", baseline_fixture_images["app"], "-ec",
                     "printf foreign-control > /sentinel/control"), label="write foreign sentinel control")
        baseline_context = start_fixture(baseline_fixture_images["app"], baseline_fixture_images["worker"],
                                         baseline_fixture_images["ssr"], baseline_sha, timeout=1200)
        contexts.append(baseline_context)
        sentinel_verifier_image = baseline_context["ancillary_images"]["postgres"]
        dataset = prepare_dataset(baseline_context)
        verify_private_install(baseline_context, dataset)
        results["baseline_product"] = "passed"
        queue_encrypted_system_mail(baseline_context)
        results["system_mail_retry"] = "passed"
        queue_deletion_lifecycle(baseline_context)
        manifest, backup_paths = capture_backup(baseline_context, dataset, backup_dir)
        final_hashes = {"database_sha256": manifest["database_sha256"],
                        "archives_sha256": manifest["archives_sha256"]}
        backup_config = json.loads(Path(backup_paths["config"]).read_text())
        postgres_version = backup_config["postgres_version"]
        schema_identity = json.dumps({"tables": backup_config["schema_tables"],
                                      "migrations": backup_config["migrations"]},
                                     sort_keys=True, separators=(",", ":")).encode()
        final_hashes["schema_identity_sha256"] = hashlib.sha256(schema_identity).hexdigest()
        final_hashes["jobs_sha256"] = backup_config["jobs_sha256"]
        results["backup"] = "passed"

        wrong_key = workspace / "wrong-key.bin"
        wrong_key.write_bytes(b"0" * 64)
        try:
            validate_backup_set(manifest, {**backup_paths, "key": wrong_key})
            wrong_key_rejected = False
        except FixtureError:
            wrong_key_rejected = True
        missing_key = workspace / "missing-app-key"
        try:
            validate_backup_set(manifest, {**backup_paths, "key": missing_key})
            missing_key_rejected = False
        except FixtureError:
            missing_key_rejected = True
        missing_archive = workspace / "missing-archives.tar"
        try:
            validate_backup_set(manifest, {**backup_paths, "archives": missing_archive})
            missing_archive_rejected = False
        except FixtureError:
            missing_archive_rejected = True
        wrong_archive = workspace / "wrong-archives.tar"
        wrong_archive.write_bytes(b"wrong archive control")
        try:
            validate_backup_set(manifest, {**backup_paths, "archives": wrong_archive})
            wrong_archive_rejected = False
        except FixtureError:
            wrong_archive_rejected = True
        if (not wrong_key_rejected or not missing_key_rejected
                or not missing_archive_rejected or not wrong_archive_rejected):
            raise FixtureError("backup negative controls did not reject invalid components")
        results["negative_controls"] = "passed"
        stop_fixture(baseline_context)
        contexts.remove(baseline_context)

        key = Path(backup_paths["key"]).read_text()
        restore_context = start_fixture(baseline_fixture_images["app"], baseline_fixture_images["worker"],
                                        baseline_fixture_images["ssr"], baseline_sha, private_key=key,
                                        timeout=900)
        contexts.append(restore_context)
        restored_dataset = restore_backup(restore_context, manifest, backup_paths, backup_dir)
        _check_registry_token_decryption(restore_context, backup_config, expected="match")
        results["encrypted_credentials"] = "passed"
        verify_private_install(restore_context, restored_dataset)
        status, _, _ = product_fixture._fixture_request(
            restore_context, "app.fixture.test", "/auth/login", label="check restored baseline route")
        if status != 200:
            raise FixtureError("restored baseline route did not respond")
        backup_config = json.loads(Path(backup_paths["config"]).read_text())
        results["deletion_lifecycle"] = "passed"
        verify_restored_system_mail(restore_context, backup_config)
        results["system_mail_recovery"] = "passed"
        results["restored_baseline"] = "passed"
        stop_fixture(restore_context)
        contexts.remove(restore_context)

        candidate_context = start_fixture(args.app_image, args.worker_image, args.ssr_image,
                                          args.expected_sha, private_key=key, timeout=900)
        contexts.append(candidate_context)
        candidate_ids = _candidate_report_image_ids(candidate_context)
        restore_backup(candidate_context, manifest, backup_paths, backup_dir, start_app=False)
        compose = product_fixture._compose(candidate_context["project"])
        env = candidate_context["_compose_env"]
        failed_start = candidate_context["_run"].command(
            (*compose, "run", "--rm", "--no-deps", "-e", "DATABASE_HOST=o3-invalid-db.invalid",
             "-e", "MIGRATION_ATTEMPTS=1", "-e", "MIGRATION_RETRY_SECONDS=0", "app"),
            label="exercise closed candidate migration failure gate", cwd=product_fixture.ROOT,
            env=env, check=False, timeout=90)
        if (not _is_expected_migration_failure(failed_start) or _ready_or_closed(candidate_context) == 200
                or _job_state_digest(candidate_context) != backup_config["jobs_sha256"]
                or _job_status_counts(candidate_context) != json.loads(Path(backup_paths["config"]).read_text())[
                    "job_status_counts"]):
            raise FixtureError("candidate failure control did not keep traffic closed")
        results["upgrade_failure_gate"] = "passed"
        candidate_context["_run"].command(
            (*compose, "up", "--detach", "--no-build", "--pull", "never", "--wait",
             "--wait-timeout", "240", "app"), label="start candidate against restored baseline data",
            cwd=product_fixture.ROOT, env=env)
        if _ready(candidate_context) != 200:
            raise FixtureError("candidate readiness failed after restored-data upgrade")
        results["candidate_readiness"] = "passed"
        restored_user = candidate_context.get("_restored_user", {})
        fresh_login(candidate_context, restored_user.get("email"), "Fixture!Password2026",
                    restored_user.get("id"))
        _check_registry_token_decryption(candidate_context,
                                         json.loads(Path(backup_paths["config"]).read_text()),
                                         expected="match")
        product_fixture._assert_seeded_database(candidate_context, candidate_context["_run"], env)
        baseline_migrations = json.loads(Path(backup_paths["config"]).read_text())["migrations"]
        baseline_tables = json.loads(Path(backup_paths["config"]).read_text())["schema_tables"]
        upgraded_tables = candidate_context["_run"].command(
            (*compose, "exec", "-T", "postgres", "psql", "-U", "packio", "-d", "packio", "-Atqc",
             "SELECT table_name FROM information_schema.tables WHERE table_schema='public' ORDER BY table_name"),
            label="verify candidate schema after restored-data migration", cwd=product_fixture.ROOT,
            env=env).stdout.strip().splitlines()
        upgraded_migrations = candidate_context["_run"].command(
            (*compose, "exec", "-T", "postgres", "psql", "-U", "packio", "-d", "packio", "-Atqc",
             "SELECT migration || ':' || COALESCE(time_executed::text,'') FROM migrations ORDER BY migration"),
            label="verify candidate applied restored migrations", cwd=product_fixture.ROOT,
            env=env).stdout.strip().splitlines()
        if (not set(baseline_migrations).issubset(set(upgraded_migrations))
                or not set(baseline_tables).issubset(set(upgraded_tables))):
            raise FixtureError("candidate migration did not preserve the restored migration history")
        _verify_deletion_lifecycle(candidate_context,
                                   json.loads(Path(backup_paths["config"]).read_text())["deletion_state"])
        if (_job_status_counts(candidate_context) != backup_config["job_status_counts"]
                or _job_state_digest(candidate_context) != backup_config["jobs_sha256"]):
            raise FixtureError("candidate upgrade changed the preserved job state")
        verify_private_install(candidate_context, candidate_context["dataset"])
        results["private_install"] = "passed"
        verify_restored_system_mail(candidate_context, backup_config)
        results["candidate_job_recovery"] = "passed"
        results["candidate_upgrade"] = "passed"
        post_backup_write = _create_post_backup_write(candidate_context)
        candidate_context["_run"].command((*compose, "stop", "app", "workers"),
                                           label="keep candidate traffic and consumers closed",
                                           cwd=product_fixture.ROOT, env=env)
        stop_fixture(candidate_context)
        contexts.remove(candidate_context)

        rollback_context = start_fixture(baseline_fixture_images["app"], baseline_fixture_images["worker"],
                                          baseline_fixture_images["ssr"], baseline_sha, private_key=key,
                                          timeout=900)
        contexts.append(rollback_context)
        rollback_dataset = restore_backup(rollback_context, manifest, backup_paths, backup_dir)
        _check_registry_token_decryption(rollback_context,
                                         json.loads(Path(backup_paths["config"]).read_text()),
                                         expected="match")
        status, _, _ = product_fixture._fixture_request(
            rollback_context, "app.fixture.test", "/auth/login", label="check restored rollback route")
        if status != 200:
            raise FixtureError("full-backup rollback route did not respond")
        remaining_write = product_fixture._sql(
            rollback_context, "SELECT count(*) FROM organizations WHERE name='" + post_backup_write + "'",
            rollback_context["_run"])
        if remaining_write != "0":
            raise FixtureError("rollback retained an application write made after the backup")
        verify_private_install(rollback_context, rollback_dataset)
        results["rollback"] = "passed"
        results["new_writes_policy"] = "passed"
        stop_fixture(rollback_context)
        contexts.remove(rollback_context)

        wrong_key_context = start_fixture(
            baseline_fixture_images["app"], baseline_fixture_images["worker"],
            baseline_fixture_images["ssr"], baseline_sha, private_key=_wrong_fixture_key(key), timeout=900)
        contexts.append(wrong_key_context)
        wrong_key_dataset = restore_backup(wrong_key_context, manifest, backup_paths, backup_dir)
        _check_registry_token_decryption(wrong_key_context,
                                         json.loads(Path(backup_paths["config"]).read_text()),
                                         expected="reject")
        results["wrong_key_decryption"] = "passed"
        stop_fixture(wrong_key_context)
        contexts.remove(wrong_key_context)

    except Exception as caught:
        error = caught if isinstance(caught, FixtureError) else FixtureError("unexpected recovery rehearsal failure")
        cleanup_failed = isinstance(caught, product_fixture.FixtureCleanupError)
    finally:
        # A stop request during cleanup must still leave only the owned resources removed.
        signal.signal(signal.SIGTERM, interrupted_cleanup)
        signal.signal(signal.SIGINT, interrupted_cleanup)
        for context in reversed(contexts):
            try:
                stop_fixture(context)
            except Exception:
                cleanup_failed = True
        for reference in image_refs.values():
            if not _remove_owned_resource("image", reference):
                cleanup_failed = True
        if sentinel_created and sentinel_verifier_image:
            try:
                check = subprocess.run(
                    ("docker", "run", "--rm", "--network", "none", "--volume", f"{sentinel}:/sentinel:ro",
                     "--name", f"{sentinel}-verifier",
                     "--entrypoint", "/bin/sh", sentinel_verifier_image, "-ec", "cat /sentinel/control"),
                    stdout=subprocess.PIPE, stderr=subprocess.DEVNULL, text=True, check=False, timeout=30)
                if check.returncode or check.stdout != "foreign-control":
                    cleanup_failed = True
                    if error is None:
                        error = FixtureError("foreign sentinel changed during owned cleanup")
                else:
                    results["foreign_sentinel"] = "passed"
            except (OSError, subprocess.TimeoutExpired):
                cleanup_failed = True
                if error is None:
                    error = FixtureError("foreign sentinel could not be verified after owned cleanup")
        if sentinel_attempted:
            for suffix in ("writer", "verifier"):
                if not _remove_owned_resource("container", f"{sentinel}-{suffix}"):
                    cleanup_failed = True
            if not _remove_owned_resource("volume", sentinel):
                cleanup_failed = True
        try:
            temporary.cleanup()
        except OSError:
            cleanup_failed = True
        if cleanup_failed:
            results["cleanup"] = "failed"
            if error is None:
                error = FixtureError("owned recovery resources could not be cleaned up")
        else:
            results["cleanup"] = "passed"
        if error is None and any(value != "passed" for value in results.values()):
            error = FixtureError("recovery rehearsal did not complete every required control")
        report = {
            "schema": 1, "baseline_ref": args.baseline_ref, "baseline_sha": baseline_sha,
            "candidate_sha": args.expected_sha,
            "image_refs": {"baseline": image_refs, "candidate": candidate_refs},
            "images": {"baseline": baseline_images, "candidate": candidate_ids},
            "provenance": baseline_provenance,
            "hashes": final_hashes, "postgres_version": postgres_version,
            "started_at": started_at, "results": results,
            "duration_seconds": round(time.monotonic() - started, 3),
        }
        if error:
            print(f"O3 recovery rehearsal failed: {error}", file=sys.stderr)
        try:
            _write_report(report_path, report)
        finally:
            signal.signal(signal.SIGTERM, previous_term_handler)
            signal.signal(signal.SIGINT, previous_int_handler)
    if error:
        return 1
    print("O3 backup, restore, and upgrade rehearsal passed")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
