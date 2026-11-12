#!/usr/bin/env python3
"""Validate saved candidate evidence and assemble a local release manifest."""

import argparse
import gzip
import hashlib
import importlib.util
import json
import os
from pathlib import Path, PurePosixPath
import re
import sys
import tarfile
import tempfile

sys.dont_write_bytecode = True

ROLES = ("app", "worker", "ssr")
PREVIEW_CHECKS = {
    "backend", "tools", "frontend", "images", "cold_worker", "services", "proxy", "product",
    "audits", "history", "notices", "onboarding", "backup_runbook",
}
STABLE_CHECKS = {"readiness", "limiter", "recovery"}
CHECK_RESULTS = {
    "backend": {"composer_check", "concurrency"},
    "tools": {"tools"},
    "frontend": {"npm_check", "ssr_build", "browser"},
    "images": {"result"},
    "cold_worker": {"cold_worker"},
    "services": {"services"},
    "proxy": {"proxy"},
    "product": {"result"},
    "audits": {"root", "tools", "frontend"},
    "history": {"history"},
    "notices": {"notices"},
    "onboarding": {"onboarding"},
    "backup_runbook": {"backup_runbook"},
    "readiness": {"readiness"},
    "limiter": {"limiter"},
    "recovery": {"recovery"},
}
PRODUCT_RESULTS = {
    "candidate_images", "services", "empty_state", "migrations_seed", "tls_trust", "generic_git",
    "composer_client", "browser_tls_control", "registration_mail", "email_verification", "admin_transition",
    "organization_source", "stopped_worker_control", "provider_failure_control", "private_archive",
    "worker_recreation", "private_install",
}
PRIVATE_RESULTS = {"install", "repeat_install", "anonymous", "foreign_org", "revoked", "wrong_auth_host"}
GITHUB_CHECKS = {"backend", "tools", "frontend", "images", "cold_worker", "services", "proxy", "product",
                 "audits", "history", "onboarding"}
GITHUB_JOBS = {
    "backend (base)": {"backend (base)", "backend (base)"},
    "backend (concurrency)": {"backend (concurrency)"},
    "frontend": {"frontend", "Frontend"},
    "secret-scan": {"secret-scan", "Scan checkout and selected publication history"},
    "runtime": {"Runtime images and private Composer product"},
}
SEMVER = re.compile(
    r"^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)"
    r"(?:-([0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*))?"
    r"(?:\+([0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*))?$"
)
SHA = re.compile(r"[0-9a-f]{40}\Z")
HASH = re.compile(r"sha256:[0-9a-f]{64}\Z")
HEX = re.compile(r"[0-9a-f]{64}\Z")
METADATA_SHA = re.compile(r"[0-9a-f]{7,40}\Z")
ROOT = Path(__file__).resolve().parents[1]
_image_spec = importlib.util.spec_from_file_location(
    "packio_release_image_verifier", ROOT / "test/Support/Release/image.test.py")
_image_verifier = importlib.util.module_from_spec(_image_spec)
_image_spec.loader.exec_module(_image_verifier)


def fail(message):
    raise ValueError(message)


def strict_object(pairs):
    value = {}
    for key, item in pairs:
        if key in value:
            fail(f"duplicate JSON key: {key}")
        value[key] = item
    return value


def read_json(data, label):
    try:
        return json.loads(data, object_pairs_hook=strict_object)
    except (UnicodeDecodeError, json.JSONDecodeError) as error:
        fail(f"invalid JSON in {label}: {error}")


def sha256_file(path):
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def digest_text(value, label):
    if not isinstance(value, str) or not HEX.fullmatch(value):
        fail(f"{label} must be a lowercase SHA-256 digest")
    return value


def image_digest(value, label):
    if not isinstance(value, str) or not HASH.fullmatch(value):
        fail(f"{label} must be a sha256 digest")
    return value


def safe_file(root, relative, label):
    if not isinstance(relative, str) or not relative or "\\" in relative:
        fail(f"{label} path must be a non-empty relative POSIX path")
    path = PurePosixPath(relative)
    if path.is_absolute() or any(part in ("", ".", "..") for part in path.parts):
        fail(f"unsafe {label} path: {relative}")
    current = root
    for part in path.parts:
        current = current / part
        try:
            if current.is_symlink():
                fail(f"symlink in {label} path: {relative}")
        except OSError as error:
            fail(f"cannot inspect {label} path: {error}")
    try:
        resolved = current.resolve(strict=True)
    except OSError as error:
        fail(f"missing {label}: {relative}")
    if root.resolve() not in resolved.parents or not resolved.is_file():
        fail(f"{label} is not a regular file inside evidence directory: {relative}")
    return resolved


def referenced_file(root, reference, label):
    if not isinstance(reference, dict) or set(reference) != {"path", "sha256"}:
        fail(f"{label} must contain only path and sha256")
    path = safe_file(root, reference["path"], label)
    expected = digest_text(reference["sha256"], f"{label} sha256")
    if sha256_file(path) != expected:
        fail(f"{label} checksum mismatch: {reference['path']}")
    return path


def read_blob(root, reference, label):
    path = referenced_file(root, reference, label)
    return read_json(path.read_bytes(), label)


def digest_stream(stream):
    digest = hashlib.sha256()
    for chunk in iter(lambda: stream.read(1024 * 1024), b""):
        digest.update(chunk)
    return digest.hexdigest()


def archive_members(archive, label):
    members = {}
    for member in archive.getmembers():
        name = member.name
        path = PurePosixPath(name)
        if (path.is_absolute() or "\\" in name or any(part in ("", ".", "..") for part in path.parts)
                or name in members):
            fail(f"unsafe or duplicate path in {label}: {name}")
        if not (member.isfile() or member.isdir()):
            fail(f"non-regular entry in {label}: {name}")
        members[name.rstrip("/")] = member
    return members


def tar_bytes(archive, member, label):
    stream = archive.extractfile(member)
    if stream is None:
        fail(f"missing archive payload: {label}")
    return stream.read()


def validate_source_archive(root, record, candidate_sha, version):
    if not isinstance(record, dict) or set(record) != {"path", "sha256", "candidate_sha"}:
        fail("source_archive must contain path, sha256 and candidate_sha")
    if record["candidate_sha"] != candidate_sha:
        fail("source archive candidate SHA differs")
    path = safe_file(root, record["path"], "source archive")
    if sha256_file(path) != digest_text(record["sha256"], "source archive sha256"):
        fail("source archive checksum mismatch")
    try:
        archive = tarfile.open(path, "r:*")
    except (OSError, tarfile.TarError) as error:
        fail(f"invalid source archive: {error}")
    with archive:
        members = archive_members(archive, "source archive")
        if (any(name == root_name or name.startswith(root_name + "/")
                for name in members for root_name in (".git", "local-plans", ".data", "data/coverage"))
                or ".env" in members):
            fail("source archive contains repository history, local plans, runtime data or secrets")
        required = {"LICENSE", "THIRD_PARTY_NOTICES.md", "third-party-sources.json", "data/build/release.json"}
        if not required.issubset(members):
            fail("source archive is missing required license, notices or release metadata")
        for name in required:
            if not members[name].isfile() or members[name].size == 0:
                fail(f"source archive has empty or invalid {name}")
        metadata = read_json(tar_bytes(archive, members["data/build/release.json"], "release metadata"),
                             "source release metadata")
        if (not isinstance(metadata, dict) or metadata.get("dirty") is not False
                or not isinstance(metadata.get("sha"), str) or not METADATA_SHA.fullmatch(metadata["sha"])
                or not candidate_sha.startswith(metadata["sha"])):
            fail("source archive metadata is dirty or belongs to another candidate")
        if metadata.get("version") != version or not isinstance(metadata.get("version"), str) or not SEMVER.fullmatch(metadata["version"]):
            fail("source archive version differs from requested candidate version")
        source_manifest = read_json(tar_bytes(archive, members["third-party-sources.json"], "source manifest"),
                                    "source manifest")
        sources = source_manifest.get("sources") if isinstance(source_manifest, dict) else None
        if source_manifest.get("schema") != 1 or not isinstance(sources, list) or not sources:
            fail("source archive third-party source manifest is empty or invalid")
        for source in sources:
            if not isinstance(source, dict) or not isinstance(source.get("path"), str):
                fail("source archive third-party source entry is invalid")
            source_path = source["path"]
            member = members.get(source_path)
            expected_size = source.get("size")
            expected_sha = source.get("sha256")
            if (member is None or not member.isfile() or type(expected_size) is not int
                    or member.size != expected_size or not isinstance(expected_sha, str)
                    or not HEX.fullmatch(expected_sha)):
                fail(f"source archive is missing pinned source {source_path}")
            stream = archive.extractfile(member)
            if stream is None or digest_stream(stream) != expected_sha:
                fail(f"source archive pinned source checksum mismatch: {source_path}")
        return {"path": record["path"], "sha256": record["sha256"],
                "metadata_version": metadata["version"]}


def read_oci_blob(archive, descriptor, label):
    digest = image_digest(descriptor.get("digest"), f"{label} digest")
    member = archive.getmember(f"blobs/sha256/{digest.split(':', 1)[1]}")
    if (not member.isfile() or type(descriptor.get("size")) is not int
            or member.size != descriptor.get("size")):
        fail(f"invalid OCI blob size for {label}")
    stream = archive.extractfile(member)
    if stream is None:
        fail(f"missing OCI blob for {label}")
    raw = stream.read()
    if hashlib.sha256(raw).hexdigest() != digest.split(":", 1)[1]:
        fail(f"OCI blob digest mismatch for {label}")
    return raw


def validate_oci_export(path, image, role, candidate_sha, version):
    try:
        archive = tarfile.open(path, "r:*")
    except (OSError, tarfile.TarError) as error:
        fail(f"invalid OCI export for {role}: {error}")
    with archive:
        members = archive_members(archive, f"{role} OCI export")
        if "index.json" not in members or "oci-layout" not in members:
            fail(f"{role} export is not an OCI layout")
        root = read_json(tar_bytes(archive, members["index.json"], "OCI root index"), "OCI root index")
        roots = [item for item in root.get("manifests", []) if item.get("digest") == image["local_id"]]
        if len(roots) != 1:
            fail(f"{role} export does not uniquely bind local image ID")
        root_descriptor = roots[0]
        root_payload = read_oci_blob(archive, root_descriptor, f"{role} root descriptor")
        if "index" in root_descriptor.get("mediaType", ""):
            nested = read_json(root_payload, f"{role} image index")
            runtime = [item for item in nested.get("manifests", [])
                       if item.get("platform", {}).get("os") == "linux"
                       and item.get("platform", {}).get("architecture") == "amd64"
                       and item.get("annotations", {}).get("vnd.docker.reference.type") != "attestation-manifest"]
        else:
            runtime = [root_descriptor]
        if len(runtime) != 1:
            fail(f"{role} export must contain exactly one linux/amd64 runtime image")
        runtime_descriptor = runtime[0]
        manifest_raw = root_payload if runtime_descriptor is root_descriptor else read_oci_blob(
            archive, runtime_descriptor, f"{role} runtime manifest")
        manifest = read_json(manifest_raw, f"{role} runtime manifest")
        config_descriptor = manifest.get("config", {})
        config_raw = read_oci_blob(archive, config_descriptor, f"{role} config")
        config = read_json(config_raw, f"{role} image config")
        if config.get("os") != "linux" or config.get("architecture") != "amd64":
            fail(f"{role} export is not a linux/amd64 image")
        labels = config.get("config", {}).get("Labels")
        expected_labels = {
            "org.opencontainers.image.source": "https://github.com/packio-php/packio",
            "org.opencontainers.image.revision": candidate_sha,
            "org.opencontainers.image.version": version,
            "org.opencontainers.image.licenses": "MIT",
        }
        if not isinstance(labels, dict) or any(labels.get(key) != value for key, value in expected_labels.items()):
            fail(f"{role} OCI config labels do not match repository, candidate, version and license")
        config_digest = image_digest(config_descriptor.get("digest"), f"{role} config digest")
        layers = manifest.get("layers")
        diff_ids = config.get("rootfs", {}).get("diff_ids")
        if not isinstance(layers, list) or not layers or not isinstance(diff_ids, list) or len(diff_ids) != len(layers):
            fail(f"{role} export layer metadata is incomplete")
        layer_digests = []
        for number, descriptor in enumerate(layers):
            layer_digest = image_digest(descriptor.get("digest"), f"{role} layer digest")
            member = archive.getmember(f"blobs/sha256/{layer_digest.split(':', 1)[1]}")
            if not member.isfile() or member.size != descriptor.get("size"):
                fail(f"invalid {role} layer size")
            stream = archive.extractfile(member)
            if stream is None:
                fail(f"missing {role} layer")
            if "+gzip" in descriptor.get("mediaType", ""):
                content_stream = gzip.GzipFile(fileobj=stream)
            else:
                content_stream = stream
            raw_hash = hashlib.sha256()
            diff_hash = hashlib.sha256()
            while chunk := content_stream.read(1024 * 1024):
                diff_hash.update(chunk)
            if content_stream is not stream:
                stream = archive.extractfile(member)
                while chunk := stream.read(1024 * 1024):
                    raw_hash.update(chunk)
            else:
                # For uncompressed OCI layers, descriptor digest and diff ID cover the same bytes.
                raw_hash = diff_hash
            if raw_hash.hexdigest() != layer_digest.split(":", 1)[1] or diff_hash.hexdigest() != diff_ids[number].removeprefix("sha256:"):
                fail(f"{role} layer digest or rootfs diff ID mismatch")
            layer_digests.append(layer_digest)
        if config_digest != image["config_digest"]:
            fail(f"{role} config digest differs from evidence index")
        declared_layers = image.get("layer_digests")
        if declared_layers != layer_digests:
            fail(f"{role} ordered layer digests differ from evidence index")
        target = {"app": "prod", "worker": "prod-worker", "ssr": "prod-ssr"}[role]
        try:
            provenance = _image_verifier.verify_provenance(
                path, image["local_id"], (image["local_id"],),
                {"os": "linux", "architecture": "amd64"}, candidate_sha, target)
        except (AssertionError, KeyError, TypeError, ValueError) as error:
            fail(f"{role} exported provenance does not match candidate: {error}")
        if (provenance["runtime_manifest_digest"] != runtime_descriptor.get("digest")
                or provenance["base_digests"] != image["base_digests"]):
            fail(f"{role} exported provenance differs from D3 base or runtime manifest evidence")
        return {"config_digest": config_digest, "layer_digests": layer_digests,
                "runtime_manifest_digest": runtime_descriptor["digest"]}


def validate_check(root, name, record, candidate_sha, image_ids, source_sha):
    if not isinstance(record, dict):
        fail(f"check {name} is not an execution record")
    common = {"candidate_sha", "image_ids", "results", "report"}
    if record.get("provider") == "github_actions":
        required = common | {"provider", "github"}
        allowed = required | {"producer_report"}
        if name not in GITHUB_CHECKS or not required.issubset(record) or set(record) - allowed:
            fail(f"check {name} has an invalid GitHub execution record shape")
    else:
        required = common | {"commands", "exit_code"}
        allowed = required | {"producer_report"}
        if "provider" in record or not required.issubset(record) or set(record) - allowed:
            fail(f"check {name} has an invalid local execution record shape")
    if record["candidate_sha"] != candidate_sha or record["image_ids"] != image_ids:
        fail(f"check {name} is bound to a different candidate or image set")
    results = record["results"]
    required_results = CHECK_RESULTS[name]
    if not isinstance(results, dict) or any(results.get(key) != "passed" for key in required_results):
        fail(f"check {name} is missing a required passed result")
    report = read_blob(root, record["report"], f"check {name} report")
    for key in ("candidate_sha", "image_ids", "results"):
        if report.get(key) != record.get(key):
            fail(f"check {name} report does not match its execution record")
    if name == "notices" and report.get("source_archive_sha256") != source_sha:
        fail("notices report is not cryptographically bound to the source archive")
    if record.get("provider") == "github_actions":
        if report.get("provider") != "github_actions":
            fail(f"check {name} report does not identify its GitHub provider")
        validate_github_provider(root, name, record["github"], candidate_sha)
    else:
        if report.get("commands") != record.get("commands"):
            fail(f"check {name} report does not match its command records")
        if type(record["exit_code"]) is not int or record["exit_code"] != 0:
            fail(f"check {name} did not exit successfully")
        commands = record["commands"]
        if not isinstance(commands, list) or not commands:
            fail(f"check {name} has no command execution records")
        for command in commands:
            if (not isinstance(command, dict) or not isinstance(command.get("argv"), list)
                    or not command["argv"] or any(not isinstance(value, str) or not value for value in command["argv"])
                    or type(command.get("exit_code")) is not int or command["exit_code"] != 0):
                fail(f"check {name} contains a failed or invalid command")
            log_path = referenced_file(root, command.get("log"), f"check {name} command log")
            if not log_path.is_file():
                fail(f"check {name} command log is not a regular file")
    if name in ("images", "product", "onboarding"):
        producer = read_blob(root, record.get("producer_report"), f"check {name} producer report")
        if (report.get("producer_report") != record.get("producer_report")
                or producer.get("source_snapshot") is not None):
            fail(f"check {name} producer evidence is missing or uses a dirty snapshot")
        return producer
    return report


def validate_github_provider(root, name, provider, candidate_sha):
    if not isinstance(provider, dict):
        fail(f"check {name} GitHub provider record is invalid")
    required = {"run_id", "api_response"}
    expected = required | ({"artifact"} if name in ("images", "product", "onboarding") else set())
    if set(provider) != expected or type(provider.get("run_id")) is not int or provider["run_id"] <= 0:
        fail(f"check {name} GitHub provider record has an invalid shape")
    api = read_blob(root, provider["api_response"], f"check {name} GitHub API snapshot")
    if not isinstance(api, dict) or set(api) != {"run", "jobs", "artifacts"}:
        fail(f"check {name} GitHub API snapshot is incomplete")
    run = api["run"]
    workflow_path = run.get("path") if isinstance(run, dict) else None
    valid_workflow_path = (workflow_path == ".github/workflows/verify.yml"
                           or isinstance(workflow_path, str) and any(
                               workflow_path.startswith(".github/workflows/verify.yml@" + ref)
                               for ref in ("refs/heads/", "refs/tags/")))
    if (not isinstance(run, dict) or run.get("id") != provider["run_id"]
            or run.get("repository", {}).get("full_name") != "packio-php/packio"
            or not valid_workflow_path
            or run.get("head_sha") != candidate_sha or run.get("status") != "completed"
            or run.get("conclusion") != "success" or type(run.get("run_attempt")) is not int
            or run["run_attempt"] < 1):
        fail(f"check {name} GitHub run is incomplete, unsuccessful or bound to another candidate")
    jobs = api["jobs"].get("jobs") if isinstance(api["jobs"], dict) else None
    if not isinstance(jobs, list):
        fail(f"check {name} GitHub jobs response is invalid")
    completed = {}
    for job in jobs:
        if not isinstance(job, dict) or not isinstance(job.get("name"), str):
            fail(f"check {name} GitHub jobs response contains an invalid job")
        if job["name"] in completed:
            fail(f"check {name} GitHub jobs response contains duplicate jobs")
        completed[job["name"]] = job.get("status") == "completed" and job.get("conclusion") == "success"
    for key, aliases in GITHUB_JOBS.items():
        matches = aliases & completed.keys()
        if len(matches) != 1 or not completed[next(iter(matches))]:
            fail(f"check {name} requires successful GitHub job {key}")
    if name in ("images", "product", "onboarding"):
        artifact = provider["artifact"]
        if (not isinstance(artifact, dict) or set(artifact) != {"name", "download"}
                or artifact.get("name") != "verified-runtime-images"):
            fail(f"check {name} must bind the verified-runtime-images artifact")
        download = artifact.get("download")
        downloaded = referenced_file(root, download, f"check {name} downloaded runtime artifact")
        artifacts = api["artifacts"].get("artifacts") if isinstance(api["artifacts"], dict) else None
        if not isinstance(artifacts, list):
            fail(f"check {name} GitHub artifacts response is invalid")
        matches = [item for item in artifacts if isinstance(item, dict)
                   and item.get("name") == "verified-runtime-images"]
        if (len(matches) != 1 or matches[0].get("expired") is not False
                or matches[0].get("digest") != "sha256:" + sha256_file(downloaded)):
            fail(f"check {name} downloaded artifact does not match the unexpired GitHub artifact")


def validate_images(report, images, candidate_sha, image_ids):
    if (not isinstance(report, dict) or report.get("expected_sha") != candidate_sha
            or report.get("source_sha") != candidate_sha or set(report.get("images", {})) != set(ROLES)):
        fail("D3 image report does not match candidate SHA and exact image set")
    output = {}
    for role in ROLES:
        item = report["images"][role]
        if (not isinstance(item, dict) or item.get("id") != image_ids[role]
                or item.get("source_sha") != candidate_sha or item.get("layers_result") != "passed"
                or type(item.get("layers_checked")) is not int or item["layers_checked"] < 1
                or item.get("base_digests") != images[role]["base_digests"]
                or item.get("runtime_manifest_digest") != images[role]["runtime_manifest_digest"]):
            fail(f"D3 image report does not validate {role} image")
        if role in ("app", "worker"):
            metadata_sha = item.get("metadata_sha")
            if (item.get("metadata_embeds_sha") is not True or not isinstance(metadata_sha, str)
                    or not METADATA_SHA.fullmatch(metadata_sha) or not candidate_sha.startswith(metadata_sha)):
                fail(f"D3 image metadata does not match {role} candidate")
        elif item.get("metadata_embeds_sha") is not False or item.get("metadata_sha") is not None:
            fail("SSR image unexpectedly claims application release metadata")
        output[role] = {"runtime_manifest_digest": item["runtime_manifest_digest"],
                        "base_digests": item["base_digests"]}
    if report.get("ssr_parity", {}).get("result") != "passed" or report.get("startup", {}).get("result") != "passed":
        fail("D3 image report is missing startup or SSR parity success")
    return output


def validate_product(report, candidate_sha, image_ids):
    if (not isinstance(report, dict) or report.get("candidate_sha") != candidate_sha
            or report.get("source_snapshot") is not None):
        fail("product report does not match the clean candidate")
    images = report.get("images")
    if not isinstance(images, dict) or any(images.get(role) != image_ids[role] for role in ROLES):
        fail("product report image IDs differ from candidate images")
    results = report.get("results")
    if (not isinstance(results, dict) or set(results) != PRODUCT_RESULTS
            or any(value != "passed" for key, value in results.items() if key != "empty_state")
            or results.get("empty_state") != "fresh_project_volumes"):
        fail("product report is missing one or more passed product scenarios")
    install = report.get("private_install")
    if not isinstance(install, dict) or set(install) != {
        "organization_id", "source_id", "source_reference", "source_sha256", "archive_sha256",
        "member_sha256", "results",
    }:
        fail("product report private Composer install evidence is incomplete")
    if (not isinstance(install.get("source_reference"), str)
            or not SHA.fullmatch(install["source_reference"])
            or not isinstance(install.get("source_sha256"), str) or not HEX.fullmatch(install["source_sha256"])
            or not isinstance(install.get("archive_sha256"), str) or not HEX.fullmatch(install["archive_sha256"])
            or install.get("results") != {key: "passed" for key in PRIVATE_RESULTS}):
        fail("product report private Composer install checks did not all pass")
    members = install.get("member_sha256")
    if (not isinstance(members, dict) or set(members) != {"composer.json", "src/Probe.php"}
            or any(not isinstance(value, str) or not HEX.fullmatch(value) for value in members.values())):
        fail("product report private Composer archive hashes are invalid")


def validate_sbom(root, record, candidate_sha, image_ids, source_sha):
    if (not isinstance(record, dict) or record.get("candidate_sha") != candidate_sha
            or record.get("image_ids") != image_ids or record.get("source_archive_sha256") != source_sha
            or set(record) != {"candidate_sha", "image_ids", "source_archive_sha256", "documents"}):
        fail("SBOM wrapper does not bind the exact candidate images and source archive")
    documents = record.get("documents")
    if not isinstance(documents, dict) or set(documents) != set(ROLES):
        fail("stable SBOM evidence must contain one document for each candidate image")
    output = {}
    for role, reference in documents.items():
        value = read_blob(root, reference, f"{role} SBOM")
        spdx = (value.get("spdxVersion") == "SPDX-2.3" and isinstance(value.get("packages"), list)
                and bool(value["packages"]))
        cyclonedx = (value.get("bomFormat") == "CycloneDX" and value.get("specVersion") in ("1.4", "1.5", "1.6")
                     and isinstance(value.get("components"), list) and bool(value["components"]))
        if not spdx and not cyclonedx:
            fail(f"{role} SBOM is not a recognized non-empty SPDX or CycloneDX inventory")
        output[role] = {"path": reference["path"], "sha256": reference["sha256"]}
    return {"image_ids": image_ids, "source_archive_sha256": source_sha, "documents": output}


def validate_index(index, args, evidence_root):
    if not isinstance(index, dict) or index.get("schema_version") != 1:
        fail("unsupported release evidence schema")
    if (index.get("candidate_sha") != args.candidate_sha or not SHA.fullmatch(args.candidate_sha)
            or not isinstance(index.get("support_revision"), str) or not SHA.fullmatch(index["support_revision"])):
        fail("candidate SHA or support revision is invalid")
    if index.get("version") != args.version or not isinstance(args.version, str) or not SEMVER.fullmatch(args.version):
        fail("requested version does not match a valid semantic version in evidence")
    level = args.level
    if level == "stable" and SEMVER.fullmatch(args.version).group(4) is not None:
        fail("stable release version cannot contain a prerelease component")
    if index.get("level") != level:
        fail("requested release level differs from evidence")
    required_checks = PREVIEW_CHECKS | (STABLE_CHECKS if level == "stable" else set())
    checks = index.get("checks")
    if not isinstance(checks, dict) or set(checks) != required_checks:
        fail("release evidence is missing required checks or contains unknown check IDs")
    images = index.get("images")
    if not isinstance(images, dict) or set(images) != set(ROLES):
        fail("release evidence must contain exactly app, worker and ssr images")
    image_ids = {}
    image_details = {}
    for role in ROLES:
        image = images[role]
        expected_fields = {"local_id", "config_digest", "export", "base_digests", "layer_digests"}
        if not isinstance(image, dict) or set(image) != expected_fields:
            fail(f"{role} image record has an invalid shape")
        image_ids[role] = image_digest(image["local_id"], f"{role} local ID")
        image_digest(image["config_digest"], f"{role} config digest")
        bases = image["base_digests"]
        if not isinstance(bases, list) or not bases or any(not isinstance(item, str) or not HASH.fullmatch(item) for item in bases):
            fail(f"{role} base image digests are invalid")
        export_path = referenced_file(evidence_root, image["export"], f"{role} image export")
        details = validate_oci_export(export_path, image, role, args.candidate_sha, args.version)
        image["runtime_manifest_digest"] = details["runtime_manifest_digest"]
        image_details[role] = details
    archive = validate_source_archive(evidence_root, index.get("source_archive"), args.candidate_sha, args.version)
    reports = {}
    for name, record in checks.items():
        reports[name] = validate_check(evidence_root, name, record, args.candidate_sha, image_ids,
                                       archive["sha256"])
    image_report = validate_images(reports["images"], images, args.candidate_sha, image_ids)
    product_report = reports["product"]
    validate_product(product_report, args.candidate_sha, image_ids)
    validate_product(reports["onboarding"], args.candidate_sha, image_ids)
    check_evidence = {}
    for name, record in checks.items():
        evidence = {"report": bundle_reference(record["report"]), "candidate_sha": args.candidate_sha,
                    "image_ids": image_ids, "results": record["results"]}
        if "producer_report" in record:
            evidence["producer_report"] = bundle_reference(record["producer_report"])
        if record.get("provider") == "github_actions":
            evidence["provider"] = "github_actions"
            github = record["github"]
            evidence["github"] = {"run_id": github["run_id"],
                                   "api_response": bundle_reference(github["api_response"])}
            if "artifact" in github:
                evidence["github"]["artifact"] = {
                    "name": github["artifact"]["name"],
                    "download": bundle_reference(github["artifact"]["download"]),
                }
        else:
            evidence["commands"] = [
                {"argv": command["argv"], "exit_code": command["exit_code"],
                 "log": bundle_reference(command["log"])}
                for command in record["commands"]
            ]
        check_evidence[name] = evidence
    output = {
        "schema_version": 1,
        "candidate_sha": args.candidate_sha,
        "support_revision": index["support_revision"],
        "version": args.version,
        "level": level,
        "images": {
            role: {
                "local_id": image_ids[role],
                "config_digest": image_details[role]["config_digest"],
                "runtime_manifest_digest": image_details[role]["runtime_manifest_digest"],
                "layer_digests": image_details[role]["layer_digests"],
                "base_digests": images[role]["base_digests"],
                "export": bundle_reference(images[role]["export"]),
                "registry_digest": None,
            }
            for role in ROLES
        },
        "checks": check_evidence,
        "source_archive": {**archive, "path": "evidence/" + archive["path"]},
        "source_sha256": archive["sha256"],
    }
    if level == "stable":
        output["sbom"] = validate_sbom(evidence_root, index.get("sbom"),
                                        args.candidate_sha, image_ids, archive["sha256"])
        output["sbom"]["documents"] = {
            role: bundle_reference(reference)
            for role, reference in output["sbom"]["documents"].items()
        }
        provenance = index.get("provenance")
        if (not isinstance(provenance, dict) or set(provenance) != {
                "candidate_sha", "image_ids", "source_archive_sha256", "source_sha"}
                or provenance.get("candidate_sha") != args.candidate_sha
                or provenance.get("image_ids") != image_ids
                or provenance.get("source_archive_sha256") != archive["sha256"]
                or provenance.get("source_sha") != args.candidate_sha):
            fail("provenance index does not bind all three candidate images")
        output["provenance"] = {
            "source_sha": args.candidate_sha, "image_ids": image_ids,
            "source_archive_sha256": archive["sha256"],
            "images": {role: {"runtime_manifest_digest": image_details[role]["runtime_manifest_digest"],
                               "base_digests": images[role]["base_digests"]}
                       for role in ROLES},
        }
    return output


def bundle_reference(reference):
    return {"path": "evidence/" + reference["path"], "sha256": reference["sha256"]}


def evidence_references(index):
    references = [index["source_archive"]]
    references.extend(image["export"] for image in index["images"].values())
    for record in index["checks"].values():
        references.append(record["report"])
        if "producer_report" in record:
            references.append(record["producer_report"])
        references.extend(command["log"] for command in record.get("commands", []))
        if record.get("provider") == "github_actions":
            references.append(record["github"]["api_response"])
            if "artifact" in record["github"]:
                references.append(record["github"]["artifact"]["download"])
    if "sbom" in index:
        references.extend(index["sbom"]["documents"].values())
    unique = {}
    for reference in references:
        path = reference["path"]
        previous = unique.get(path)
        if previous is not None and previous != reference["sha256"]:
            fail(f"conflicting immutable evidence refs for {path}")
        unique[path] = reference["sha256"]
    return unique


def validate_promotion(manifest, registry_response):
    """Validate caller-supplied registry manifest bytes; this function never contacts a registry."""
    if not isinstance(registry_response, dict) or set(registry_response) != set(ROLES):
        raise ValueError("simulated registry response must contain exactly the three images")
    images = manifest.get("images") if isinstance(manifest, dict) else None
    if not isinstance(images, dict) or set(images) != set(ROLES):
        raise ValueError("release manifest must contain exactly the three images")
    existing = [images[role].get("registry_digest") for role in ROLES]
    if any(value is not None for value in existing) and any(value is None for value in existing):
        raise ValueError("release manifest contains partial prior promotion state")
    for role in ROLES:
        local = images[role]
        remote = registry_response[role]
        if (not isinstance(remote, dict) or set(remote) != {"registry_digest", "manifest_bytes"}
                or not isinstance(remote.get("manifest_bytes"), bytes)
                or not HASH.fullmatch(str(remote.get("registry_digest", "")))
                or "sha256:" + hashlib.sha256(remote["manifest_bytes"]).hexdigest() != remote["registry_digest"]):
            raise ValueError(f"simulated registry image differs from saved {role} artifact")
        if local.get("registry_digest") is not None and remote["registry_digest"] != local["registry_digest"]:
            raise ValueError(f"simulated registry digest changes immutable {role} promotion")
        try:
            registry_manifest = read_json(remote["manifest_bytes"], f"simulated {role} registry manifest")
        except ValueError as error:
            raise ValueError(f"simulated {role} registry manifest is invalid: {error}") from error
        config = registry_manifest.get("config") if isinstance(registry_manifest, dict) else None
        config_digest = config.get("digest") if isinstance(config, dict) else None
        layers = registry_manifest.get("layers") if isinstance(registry_manifest, dict) else None
        if (not isinstance(registry_manifest, dict) or registry_manifest.get("schemaVersion") != 2
                or not isinstance(config_digest, str)
                or not isinstance(layers, list)
                or config_digest != local.get("config_digest")
                or [item.get("digest") for item in layers if isinstance(item, dict)] != local.get("layer_digests")
                or len(layers) != len(local.get("layer_digests", []))):
            raise ValueError(f"simulated registry manifest differs from saved {role} config or ordered layers")
    return True


def write_immutable_file(path, data, label):
    path.parent.mkdir(parents=True, exist_ok=True)
    current = path.parent
    while current != current.parent:
        if current.is_symlink() or not current.is_dir():
            fail(f"unsafe output directory for {label}")
        current = current.parent
    if path.exists() or path.is_symlink():
        if path.is_symlink() or not path.is_file() or path.read_bytes() != data:
            fail(f"immutable {label} already exists with different content")
        return
    temporary = None
    try:
        with tempfile.NamedTemporaryFile(dir=path.parent, prefix="." + path.name + ".", delete=False) as stream:
            temporary = Path(stream.name)
            stream.write(data)
            stream.flush()
            os.fsync(stream.fileno())
        try:
            os.link(temporary, path)
        except FileExistsError:
            if path.is_symlink() or not path.is_file() or path.read_bytes() != data:
                fail(f"immutable {label} already exists with different content")
        directory = os.open(path.parent, os.O_RDONLY)
        try:
            os.fsync(directory)
        finally:
            os.close(directory)
    finally:
        if temporary is not None:
            temporary.unlink(missing_ok=True)


def write_manifest(output_dir, evidence_root, manifest, references):
    data = (json.dumps(manifest, indent=2, sort_keys=True) + "\n").encode()
    for relative, expected in sorted(references.items()):
        source = safe_file(evidence_root, relative, "bundle evidence")
        payload = source.read_bytes()
        if hashlib.sha256(payload).hexdigest() != expected:
            fail(f"bundle evidence checksum changed during assembly: {relative}")
        destination = output_dir / "evidence" / relative
        write_immutable_file(destination, payload, f"bundle evidence {relative}")
    write_immutable_file(output_dir / "release-manifest.json", data, "release-manifest.json")
    checksums = "".join(
        f"{digest_text(expected, 'evidence sha256')}  evidence/{relative}\n"
        for relative, expected in sorted(references.items())
    )
    checksums += f"{hashlib.sha256(data).hexdigest()}  release-manifest.json\n"
    write_immutable_file(output_dir / "SHA256SUMS", checksums.encode(), "SHA256SUMS")


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--level", choices=("preview", "stable"), required=True)
    parser.add_argument("--candidate-sha", required=True)
    parser.add_argument("--version", required=True)
    parser.add_argument("--evidence-dir", type=Path, required=True)
    parser.add_argument("--output-dir", type=Path, required=True)
    args = parser.parse_args(argv)
    try:
        if not args.evidence_dir.is_dir() or args.evidence_dir.is_symlink():
            fail("evidence directory must be a real directory")
        if not args.output_dir.is_dir() or args.output_dir.is_symlink():
            fail("output directory must already exist and must not be a symlink")
        index_path = safe_file(args.evidence_dir, "release-evidence.json", "release evidence index")
        index = read_json(index_path.read_bytes(), "release evidence index")
        manifest = validate_index(index, args, args.evidence_dir)
        write_manifest(args.output_dir, args.evidence_dir, manifest, evidence_references(index))
    except (OSError, KeyError, TypeError, ValueError, tarfile.TarError) as error:
        print(f"release preparation failed: {error}", file=sys.stderr)
        return 1
    print(f"Validated local {args.level} draft for {args.version} at {args.candidate_sha}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
