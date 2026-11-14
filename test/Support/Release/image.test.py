#!/usr/bin/env python3
"""Build release targets from a disposable tracked-source context and inspect every exported layer."""

import io
import argparse
import base64
import gzip
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import posixpath
import re
import shutil
import socket
import subprocess
import tarfile
import tempfile
import uuid

ROOT = Path(__file__).resolve().parents[3]
MARKER = b"PACKIO_RELEASE_IMAGE_CANARY_011"
TARGETS = ("prod", "prod-worker", "prod-ssr")
FORBIDDEN = (
    "/app/.git/", "/app/.env", "/app/local-plans/", "/app/vendor/bin/phpunit",
    "/app/data/sessions/", "/app/data/logs/", "/app/data/rate-limits/",
    "/app/data/db/sqlite.db", "/app/data/package-archives/",
    "/app/config/development.config.php", "/app/config/autoload/local.php",
    "/app/config/autoload/development.local.php",
    "/app/node_modules/", "/app/test/", "/app/tools/",
    "/usr/local/bin/php-cgi", "/usr/local/bin/phpdbg", "/usr/local/bin/phpize",
    "/usr/local/bin/php-config", "/usr/local/bin/pear", "/usr/local/bin/peardev",
    "/usr/local/bin/pecl", "/usr/local/include/",
    "/app/bin/create-release-tag.php", "/app/bin/measure-step", "/app/bin/smoke-runtime",
    "/app/bin/with-postgres-test", "/app/bin/prepare-release.py", "/app/bin/prepare-notice-sources.py",
)
FORBIDDEN_BY_TARGET = {
    "prod": ("/app/data/ssr/",),
    "prod-worker": ("/app/data/ssr/",),
    # /opt/ holds only the yarn install (/opt/yarn-v1.22.22/), which the matcher cannot name by prefix.
    "prod-ssr": ("/usr/local/lib/node_modules/", "/opt/"),
}
# Uncompressed layer bytes, all layers including the base image.
MAX_LAYER_BYTES = {"prod": 430 * 1024 * 1024, "prod-worker": 530 * 1024 * 1024,
                   "prod-ssr": 165 * 1024 * 1024}


def run(*args, cwd=None, output=None, binary=False):
    result = subprocess.run(args, cwd=cwd, stdout=output or subprocess.PIPE,
                            stderr=subprocess.PIPE if binary else subprocess.STDOUT, check=False)
    if result.returncode:
        if binary:
            detail = result.stderr.decode(errors="replace") or result.stdout.decode(errors="replace")
            raise RuntimeError(f"{' '.join(args)} failed:\n{detail}")
        if output is not None:
            output.flush()
            lines = Path(output.name).read_text(errors="replace").splitlines()
            raise RuntimeError(f"{' '.join(args)} failed:\n" + "\n".join(lines[-50:]))
        raise RuntimeError(result.stdout.decode(errors="replace"))
    if binary:
        if result.stderr:
            raise RuntimeError(f"{' '.join(args)} wrote unexpected stderr: {result.stderr.decode(errors='replace')}")
        return result.stdout
    return result.stdout.decode(errors="replace") if output is None else ""


def make_context(destination):
    shutil.copytree(ROOT / ".git", destination / ".git", symlinks=True)
    tracked = subprocess.check_output(("git", "ls-files", "-z"), cwd=ROOT).split(b"\0")
    for raw_path in tracked:
        if not raw_path:
            continue
        relative = Path(os.fsdecode(raw_path))
        source = ROOT / relative
        target = destination / relative
        target.parent.mkdir(parents=True, exist_ok=True)
        if source.is_symlink():
            target.symlink_to(os.readlink(source))
        elif source.is_file():
            shutil.copy2(source, target)
    # Include newly added Docker entrypoints while this change is still
    # uncommitted. After commit they are covered by the tracked file list.
    for relative in (Path('.docker/run-worker.sh'), Path('.docker/warmup-object-mapper.sh')):
        source = ROOT / relative
        target = destination / relative
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(source, target)
    for relative in (
        ".env.prod.local", "config/development.config.php",
        "config/autoload/local.php", "config/autoload/development.local.php",
        "data/sessions/session", "data/logs/app.log",
        "data/rate-limits/limit", "data/db/sqlite.db",
        "data/package-archives/private.zip", "data/build/release.json",
        "data/ssr/ssr.mjs", "public/build/manifest.json",
        "data/coverage/report.xml", "src/local-private.zip",
        "test-results/result.json", "playwright-report/report.html",
    ):
        path = destination / relative
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_bytes(MARKER)
    (destination / "local-plans").mkdir()
    (destination / "local-plans" / "private.md").write_bytes(MARKER)
    (destination / "vendor").mkdir()
    (destination / "vendor" / "autoload.php").write_bytes(MARKER)
    (destination / "node_modules").mkdir()
    (destination / "node_modules" / "private.txt").write_bytes(MARKER)


def check_layers(image, archive, target, provenance=None):
    forbidden_paths = FORBIDDEN + FORBIDDEN_BY_TARGET[target]
    size = 0
    run("docker", "save", "-o", str(archive), image)
    with tarfile.open(archive) as exported:
        manifest = json.load(exported.extractfile("manifest.json"))[0]
        for layer_name in manifest["Layers"]:
            layer_bytes = exported.extractfile(layer_name).read()
            if layer_bytes[:2] == b"\x1f\x8b":
                layer_bytes = gzip.decompress(layer_bytes)
            size += len(layer_bytes)
            with tarfile.open(fileobj=io.BytesIO(layer_bytes)) as layer:
                for member in layer:
                    path = "/" + member.name.lstrip("./")
                    if path.startswith("/app/.env") or any(
                        path == forbidden or (forbidden.endswith("/") and path.startswith(forbidden))
                        for forbidden in forbidden_paths
                    ):
                        raise AssertionError(f"{image}: forbidden path in {layer_name}: {path}")
                    if member.isfile():
                        content = layer.extractfile(member).read()
                        if MARKER in content:
                            raise AssertionError(f"{image}: canary in {layer_name}: {path}")
    if size > MAX_LAYER_BYTES[target]:
        raise AssertionError(f"{image}: {size} bytes exceeds ceiling {MAX_LAYER_BYTES[target]}")
    if provenance:
        return verify_provenance(archive, image, *provenance)


def read_oci_json(exported, descriptor):
    digest = descriptor.get("digest", "")
    match = re.fullmatch(r"sha256:([0-9a-f]{64})", digest)
    if not match:
        raise AssertionError(f"invalid OCI JSON descriptor digest: {digest}")
    payload = exported.extractfile(f"blobs/sha256/{match.group(1)}")
    if payload is None:
        raise AssertionError(f"missing OCI JSON blob: {digest}")
    payload = payload.read()
    if hashlib.sha256(payload).hexdigest() != match.group(1):
        raise AssertionError(f"OCI JSON blob digest mismatch: {digest}")
    if descriptor.get("size") != len(payload):
        raise AssertionError(f"OCI JSON blob size mismatch: {digest}")
    try:
        return json.loads(payload)
    except json.JSONDecodeError as error:
        raise AssertionError(f"invalid JSON in OCI blob {digest}: {error}") from error


def verify_provenance(archive, image_id, descriptor_digests, platform, expected_sha, expected_target):
    with tarfile.open(archive) as exported:
        try:
            root_index = json.load(exported.extractfile("index.json"))
        except (KeyError, TypeError, json.JSONDecodeError) as error:
            raise AssertionError(f"{image_id}: OCI index is missing or invalid") from error
        candidates = {value for value in descriptor_digests if isinstance(value, str)}
        roots = [descriptor for descriptor in root_index.get("manifests", [])
                 if descriptor.get("digest") in candidates]
        if not roots:
            raise AssertionError(f"{image_id}: saved OCI index is not bound to the resolved image ID")

        for root in roots:
            index = read_oci_json(exported, root)
            manifests = index.get("manifests", []) or root_index.get("manifests", [])
            runtime = [descriptor for descriptor in manifests
                       if descriptor.get("annotations", {}).get("vnd.docker.reference.type") != "attestation-manifest"
                       and descriptor.get("platform", {}).get("os") == platform["os"]
                       and descriptor.get("platform", {}).get("architecture") == platform["architecture"]]
            if not runtime and root.get("mediaType", "").endswith("manifest.v1+json"):
                runtime = [root]
            for image_descriptor in runtime:
                read_oci_json(exported, image_descriptor)
                for attestation_descriptor in manifests:
                    annotations = attestation_descriptor.get("annotations", {})
                    if annotations.get("vnd.docker.reference.type") != "attestation-manifest":
                        continue
                    if annotations.get("vnd.docker.reference.digest") != image_descriptor.get("digest"):
                        continue
                    attestation = read_oci_json(exported, attestation_descriptor)
                    subject = attestation.get("subject", {})
                    if subject.get("digest") != image_descriptor.get("digest"):
                        continue
                    config = attestation.get("config", {})
                    if config.get("data"):
                        config_data = base64.b64decode(config["data"], validate=True)
                        config_digest = config.get("digest", "")
                        if (config_digest != "sha256:" + hashlib.sha256(config_data).hexdigest()
                                or config.get("size") != len(config_data)):
                            raise AssertionError(f"{image_id}: attestation config digest mismatch")
                    for layer in attestation.get("layers", []):
                        if layer.get("mediaType") != "application/vnd.in-toto+json":
                            continue
                        statement = read_oci_json(exported, layer)
                        if statement.get("predicateType") != "https://slsa.dev/provenance/v1":
                            continue
                        build = statement.get("predicate", {}).get("buildDefinition", {})
                        parameters = build.get("externalParameters", {})
                        request = parameters.get("request", {}).get("root", {}).get("request", {})
                        arguments = request.get("args", {})
                        if arguments.get("target") != expected_target:
                            raise AssertionError(f"{image_id}: provenance target differs from {expected_target}")
                        if arguments.get("vcs:revision") != expected_sha:
                            raise AssertionError(f"{image_id}: provenance source SHA differs from {expected_sha}")
                        statement_subjects = statement.get("subject", [])
                        if not any(subject.get("digest", {}).get("sha256") == image_descriptor["digest"].split(":", 1)[1]
                                   for subject in statement_subjects):
                            raise AssertionError(f"{image_id}: provenance subject does not match image manifest")
                        dependencies = build.get("resolvedDependencies", [])
                        base_digests = sorted({
                            f"sha256:{dependency['digest']['sha256']}"
                            for dependency in dependencies
                            if dependency.get("uri", "").startswith("pkg:docker/")
                            and re.fullmatch(r"[0-9a-f]{64}", dependency.get("digest", {}).get("sha256", ""))
                        })
                        return {"source_sha": arguments["vcs:revision"], "base_digests": base_digests,
                                "runtime_manifest_digest": image_descriptor["digest"]}
        raise AssertionError(f"{image_id}: linked SLSA provenance for {expected_target} is missing")


def check_app(image, sha, *, dirty_snapshot=False):
    script = (
        "test \"$(id -u)\" != 0 && test -f vendor/autoload.php && test -f public/build/.vite/manifest.json "
        "&& test -f public/api-doc/assets/swagger-ui.css "
        "&& test -f data/ssr/ssr.mjs "
        "&& test -f data/db/cycle/seeds/RuntimeSettingsCatalogSeed.php "
        "&& test -d data/db/cycle/migrations "
        "&& php -r 'require \"vendor/autoload.php\"; exit(class_exists(\"Packio\\\\Application\\\\Release\\\\AppReleaseVersion\") ? 0 : 1);' "
        "&& php -m | grep -qi pdo_pgsql && php -m | grep -qi sodium "
        "&& test -w data/cache && test -w data/package-archives"
    )
    run("docker", "run", "--pull=never", "--rm", "--entrypoint", "sh", image, "-ec", script)
    metadata = read_image_metadata(image)
    (check_snapshot_metadata if dirty_snapshot else check_metadata)(metadata, sha, image)
    if MARKER.decode() in json.dumps(metadata):
        raise AssertionError("stale release metadata was packaged")
    print(f"{image}: {metadata['version']} ({metadata['sha']})")
    return metadata


def read_image_metadata(image):
    return json.loads(run("docker", "run", "--pull=never", "--rm", "--entrypoint", "cat", image,
                          "/app/data/build/release.json"))


def check_metadata(metadata, expected_sha, image):
    metadata_sha = metadata.get("sha") if isinstance(metadata, dict) else None
    if not isinstance(metadata_sha, str) or len(metadata_sha) < 7 or not re.fullmatch(r"[0-9a-f]+", metadata_sha):
        raise AssertionError(f"{image}: release metadata is missing a valid SHA prefix")
    if not expected_sha.startswith(metadata_sha):
        raise AssertionError(f"{image}: metadata SHA {metadata_sha} differs from {expected_sha}")
    if metadata.get("dirty") is not False:
        raise AssertionError(f"{image}: release metadata must set dirty=false")


def check_snapshot_metadata(metadata, expected_sha, image):
    metadata_sha = metadata.get("sha") if isinstance(metadata, dict) else None
    if (not isinstance(metadata_sha, str) or not re.fullmatch(r"[0-9a-f]{7,40}", metadata_sha)
            or not expected_sha.startswith(metadata_sha) or metadata.get("dirty") is not True):
        raise AssertionError(f"{image}: dirty test snapshot metadata does not match the requested source")


def validate_r1_snapshot(source_identity, build_record, expected_sha, image_ids):
    fixture_path = ROOT / "test" / "Support" / "Release" / "product_fixture.py"
    spec = importlib.util.spec_from_file_location("release_product_fixture_snapshot", fixture_path)
    if spec is None or spec.loader is None:
        raise ValueError("R1 snapshot verification helper is unavailable")
    fixture = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(fixture)
    return fixture.validate_r1_test_snapshot(
        {"identity": source_identity, "build": build_record}, expected_sha, image_ids)


def run_names():
    suffix = uuid.uuid4().hex[:12]
    return {"suffix": suffix, "project": f"packio-release-{suffix}", "tag": f"release-{suffix}"}


def remove_aliases(aliases):
    for alias in aliases:
        try:
            run("docker", "image", "rm", alias)
        except RuntimeError:
            # An alias may already be gone after a failed or partial compose run.
            pass


def resolve_image(reference):
    details = json.loads(run("docker", "image", "inspect", "--format", "{{json .}}", reference))
    image_id = details["Id"]
    labels = details.get("Config", {}).get("Labels") or {}
    base_digests = sorted({value for key, value in labels.items()
                           if "base" in key.lower() and "digest" in key.lower() and value})
    descriptor_digests = [image_id]
    descriptor_digest = details.get("Descriptor", {}).get("digest")
    if descriptor_digest:
        descriptor_digests.append(descriptor_digest)
    return {"id": image_id, "descriptor_digests": descriptor_digests,
            "platform": {"os": details.get("Os", "linux"),
                         "architecture": details.get("Architecture", "amd64")},
            "layers": details.get("RootFS", {}).get("Layers", []),
            "base_digests": base_digests}


def check_ssr(image):
    run("docker", "run", "--pull=never", "--rm", "--entrypoint", "sh", image,
        "-ec", 'test "$(id -u)" != 0 && test -f /app/data/ssr/ssr.mjs')


def archive_manifest(payload):
    entries = {}
    with tarfile.open(fileobj=io.BytesIO(payload), mode="r:*") as archive:
        for member in archive:
            name = member.name
            while name.startswith("./"):
                name = name[2:]
            name = name.rstrip("/")
            if not name or name == ".":
                continue
            if posixpath.isabs(name) or ".." in Path(name).parts:
                raise AssertionError(f"SSR archive contains unsafe path: {member.name}")
            if name in entries:
                raise AssertionError(f"SSR archive repeats path: {name}")
            if member.isdir():
                entries[name] = {"type": "directory"}
            elif member.isfile():
                data = archive.extractfile(member).read()
                entries[name] = {"type": "file", "sha256": hashlib.sha256(data).hexdigest()}
            elif member.issym():
                entries[name] = {"type": "symlink", "target": member.linkname}
            elif member.islnk():
                entries[name] = {"type": "hardlink", "target": member.linkname}
            else:
                raise AssertionError(f"SSR archive contains unsupported entry type: {name}")
    return entries


def manifest_digest(entries):
    encoded = json.dumps(entries, sort_keys=True, separators=(",", ":")).encode()
    return hashlib.sha256(encoded).hexdigest()


def capture_ssr_tree(image):
    archive = run("docker", "run", "--pull=never", "--rm", "--entrypoint", "tar", image,
                  "-cf", "-", "-C", "/app", "data/ssr", binary=True)
    return archive_manifest(archive)


def capture_ssr_scripts(image):
    archive = run("docker", "run", "--pull=never", "--rm", "--entrypoint", "tar", image,
                  "-cf", "-", "-C", "/", "usr/local/bin/entrypoint-ssr.sh",
                  "usr/local/bin/healthcheck-ssr.sh", binary=True)
    return archive_manifest(archive)


def check_ssr_parity(images):
    trees = {name: capture_ssr_tree(image) for name, image in images.items()}
    anchor = trees["app"]
    for name in ("worker", "ssr"):
        if trees[name] != anchor:
            differing = sorted(path for path in set(anchor) | set(trees[name])
                               if anchor.get(path) != trees[name].get(path))
            raise AssertionError(f"SSR tree in {name} differs from clean app: {', '.join(differing[:10])}")

    scripts = {name: capture_ssr_scripts(images[name]) for name in ("worker", "ssr")}
    if scripts["worker"] != scripts["ssr"]:
        differing = sorted(path for path in set(scripts["worker"]) | set(scripts["ssr"])
                           if scripts["worker"].get(path) != scripts["ssr"].get(path))
        raise AssertionError(f"SSR runtime scripts differ from clean worker: {', '.join(differing)}")
    return {
        "result": "passed",
        "tree_sha256": manifest_digest(trees["ssr"]),
        "tree_digests": {name: manifest_digest(tree) for name, tree in trees.items()},
        "script_sha256": manifest_digest(scripts["ssr"]),
        "script_digests": {name: manifest_digest(value) for name, value in scripts.items()},
    }


def verify_only(app_ref, worker_ref, ssr_ref, expected_sha, report, *, source_identity=None, build_record=None):
    destination = Path(report)
    destination.unlink(missing_ok=True)
    if not re.fullmatch(r"[0-9a-f]{40}", expected_sha):
        raise ValueError("expected SHA must be a full 40-character lowercase Git SHA")

    references = {"app": app_ref, "worker": worker_ref, "ssr": ssr_ref}
    resolved = {name: resolve_image(reference) for name, reference in references.items()}
    snapshot_proof = None
    if source_identity or build_record:
        if not source_identity or not build_record:
            raise ValueError("R1 snapshot verification requires both --source-identity and --build-record")
        snapshot_proof = validate_r1_snapshot(
            source_identity, build_record, expected_sha,
            {name: resolved[name]["id"] for name in ("app", "worker", "ssr")})
    images = {}
    with tempfile.TemporaryDirectory(prefix="packio-release-verify-") as temporary:
        temp = Path(temporary)
        metadata = {}
        for name in ("app", "worker"):
            metadata[name] = read_image_metadata(resolved[name]["id"])
            (check_snapshot_metadata if snapshot_proof else check_metadata)(metadata[name], expected_sha, name)
        check_app(resolved["app"]["id"], expected_sha, dirty_snapshot=snapshot_proof is not None)
        check_app(resolved["worker"]["id"], expected_sha, dirty_snapshot=snapshot_proof is not None)
        check_ssr(resolved["ssr"]["id"])
        ssr_parity = check_ssr_parity({name: resolved[name]["id"] for name in ("app", "worker", "ssr")})

        targets = {"app": "prod", "worker": "prod-worker", "ssr": "prod-ssr"}
        for name, image in resolved.items():
            image["provenance"] = check_layers(
                image["id"], temp / f"{name}.tar", targets[name],
                (image["descriptor_digests"], image["platform"], expected_sha, targets[name]),
            )
            image["layers_checked"] = len(image["layers"])

        context = temp / "context"
        context.mkdir()
        shutil.copy2(ROOT / "compose.prod.yaml", context / "compose.prod.yaml")
        runtime_images = {"prod": resolved["app"]["id"],
                          "prod-worker": resolved["worker"]["id"],
                          "prod-ssr": resolved["ssr"]["id"]}
        startup = check_boot(context, runtime_images)

        for name, image in resolved.items():
            images[name] = {
                "reference": references[name],
                "id": image["id"],
                "metadata_sha": metadata[name]["sha"] if name in metadata else None,
                "metadata_embeds_sha": name in metadata,
                "layers_checked": image["layers_checked"],
                "layers_result": "passed",
                "source_sha": image["provenance"]["source_sha"],
                "base_digests": image["provenance"]["base_digests"],
                "runtime_manifest_digest": image["provenance"]["runtime_manifest_digest"],
            }

    result = {"expected_sha": expected_sha, "source_sha": expected_sha, "images": images,
              "ssr_parity": ssr_parity,
              "startup": {"result": "passed", "services": startup}}
    if snapshot_proof is not None:
        result["source_snapshot"] = snapshot_proof
    write_report(destination, result)
    print(f"Verified local images for {expected_sha}; report: {destination}")


def write_report(destination, result):
    destination = Path(destination)
    destination.parent.mkdir(parents=True, exist_ok=True)
    temporary_report = destination.with_name(f".{destination.name}.{uuid.uuid4().hex}.tmp")
    try:
        temporary_report.write_text(json.dumps(result, indent=2) + "\n")
        temporary_report.replace(destination)
    finally:
        temporary_report.unlink(missing_ok=True)


def check_boot(context, images):
    with socket.socket() as listener:
        listener.bind(("127.0.0.1", 0))
        port = listener.getsockname()[1]
    names = run_names()
    tag = names["tag"]
    project = names["project"]
    aliases = []
    environment = context / ".env.prod.local"
    environment.write_text(
        f"PACKIO_IMAGE_TAG={tag}\nHTTP_PORT={port}\nAPP_URL=http://localhost:{port}\n"
        "DATABASE_PASS=release-test-password\n"
        "APP_SECRET_ENCRYPTION_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef\n"
    )
    compose = ("docker", "compose", "-p", project, "--env-file", str(environment),
               "-f", str(context / "compose.prod.yaml"))
    try:
        for target, image in images.items():
            service = {"prod": "roadrunner", "prod-worker": "worker", "prod-ssr": "ssr"}[target]
            alias = f"packio-{service}:{tag}"
            run("docker", "tag", image, alias)
            aliases.append(alias)
        run(*compose, "up", "-d", "--no-build", "--pull", "never", "--wait", "--wait-timeout", "300", cwd=context)
        services = []
        for service in ("app", "workers", "ssr", "postgres"):
            container_id = run(*compose, "ps", "-q", service, cwd=context).strip()
            if not container_id:
                raise AssertionError(f"{service} did not start")
            mounts = run("docker", "inspect", "--format", "{{range .Mounts}}{{if eq .Type \"bind\"}}bind{{end}}{{end}}", container_id)
            if "bind" in mounts:
                raise AssertionError(f"{service} has a host bind mount")
            services.append(service)
        run(*compose, "stop", "-t", "5", "app", "workers", "ssr", cwd=context)
        run(*compose, "up", "-d", "--no-build", "--pull", "never", "--force-recreate", "app", "workers", "ssr", cwd=context)
        run(*compose, "up", "-d", "--no-build", "--pull", "never", "--wait", "--wait-timeout", "300", cwd=context)
        print("Compose: fresh-volume boot and recreation passed")
        return services
    except Exception:
        print(run(*compose, "logs", "--tail", "80", cwd=context))
        raise
    finally:
        try:
            container_ids = set(run(*compose, "ps", "--all", "-q", cwd=context).splitlines())
            if container_ids:
                run("docker", "rm", "-f", *sorted(container_ids))
        finally:
            try:
                run(*compose, "down", "-v", "--remove-orphans", "--timeout", "5", cwd=context)
            finally:
                remove_aliases(aliases)


def build_local(report=None):
    if report:
        Path(report).unlink(missing_ok=True)
    sha = run("git", "rev-parse", "HEAD", cwd=ROOT).strip()
    with tempfile.TemporaryDirectory(prefix="packio-release-image-") as temporary:
        temp = Path(temporary)
        context = temp / "context"
        context.mkdir()
        make_context(context)
        images = {}
        metadata = {}
        provenance = {}
        run("docker", "pull", "postgres:17-alpine")
        names = run_names()
        for target in TARGETS:
            image = f"packio-release-{names['suffix']}-{target}:local"
            images[target] = image
            with (temp / f"build-{target}.log").open("wb") as log:
                run("docker", "build", "--provenance=true", "-f", ".docker/Dockerfile.roadrunner",
                    "--target", target, "-t", image, ".", cwd=context, output=log)
            resolved_image = resolve_image(image)
            provenance[target] = check_layers(
                resolved_image["id"], temp / f"{target}.tar", target,
                (resolved_image["descriptor_digests"], resolved_image["platform"], sha, target),
            )
            if target != "prod-ssr":
                metadata[target] = check_app(image, sha)
            else:
                check_ssr(image)
            print(f"{target}: clean layers and required artifacts")
        startup = check_boot(context, images)
        ssr_parity = check_ssr_parity({
            "app": images["prod"], "worker": images["prod-worker"], "ssr": images["prod-ssr"],
        })
        if report:
            resolved = {name: resolve_image(reference) for name, reference in images.items()}
            report_images = {}
            for target, image in resolved.items():
                name = {"prod": "app", "prod-worker": "worker", "prod-ssr": "ssr"}[target]
                embedded = metadata.get(target)
                report_images[name] = {
                    "reference": images[target], "id": image["id"],
                    "metadata_sha": embedded["sha"] if embedded else None,
                    "metadata_embeds_sha": embedded is not None,
                    "layers_checked": len(image["layers"]), "layers_result": "passed",
                    "source_sha": provenance[target]["source_sha"],
                    "base_digests": provenance[target]["base_digests"],
                    "runtime_manifest_digest": provenance[target]["runtime_manifest_digest"],
                }
            write_report(Path(report), {
                "expected_sha": sha, "source_sha": sha, "images": report_images,
                "ssr_parity": ssr_parity,
                "startup": {"result": "passed", "services": startup},
            })


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--verify-only", action="store_true", help="verify already built local images")
    parser.add_argument("--app-image")
    parser.add_argument("--worker-image")
    parser.add_argument("--ssr-image")
    parser.add_argument("--expected-sha")
    parser.add_argument("--report")
    parser.add_argument("--source-identity", help="R1 dirty test snapshot identity JSON")
    parser.add_argument("--build-record", help="R1 dirty test snapshot build record JSON")
    args = parser.parse_args(argv)
    supplied = any((args.app_image, args.worker_image, args.ssr_image, args.expected_sha,
                    args.source_identity, args.build_record))
    if not args.verify_only:
        if supplied:
            parser.error("image references require --verify-only")
        return build_local(args.report)
    missing = [name for name in ("app_image", "worker_image", "ssr_image", "expected_sha", "report")
               if not getattr(args, name)]
    if missing:
        parser.error("--verify-only requires --app-image, --worker-image, --ssr-image, --expected-sha, and --report")
    if bool(args.source_identity) != bool(args.build_record):
        parser.error("--source-identity and --build-record must be supplied together")
    return verify_only(args.app_image, args.worker_image, args.ssr_image, args.expected_sha, args.report,
                       source_identity=args.source_identity, build_record=args.build_record)


if __name__ == "__main__":
    try:
        main()
    except Exception as error:
        raise SystemExit(f"release image verification failed: {error}") from error
