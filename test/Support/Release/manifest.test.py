#!/usr/bin/env python3
"""Regression tests for the local, non-publishing release bundle assembler."""

import hashlib
import ast
import importlib.util
import io
import json
import base64
import copy
import os
from pathlib import Path
import re
import shutil
import shlex
import subprocess
import sys
import tarfile
import tempfile
import textwrap
import threading
import urllib
import urllib.request
import unittest
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from unittest.mock import patch

sys.dont_write_bytecode = True

ROOT = Path(__file__).resolve().parents[3]
ASSEMBLER = ROOT / "bin" / "prepare-release.py"
WORKFLOW = ROOT / ".github/workflows/release.yml"
ROLES = ("app", "worker", "ssr")
SHA = "a" * 40
SUPPORT = "b" * 40
VERSION = "1.2.3-rc.1"
BASE_DIGESTS = ["sha256:" + "c" * 64]
PREVIEW_CHECKS = {
    "backend", "tools", "frontend", "images", "cold_worker", "services", "proxy", "product",
    "audits", "history", "notices", "onboarding", "backup_runbook",
}
STABLE_CHECKS = {"readiness", "limiter", "recovery"}
PRODUCT_RESULTS = {
    "candidate_images", "services", "empty_state", "migrations_seed", "tls_trust", "generic_git",
    "composer_client", "browser_tls_control", "registration_mail", "email_verification", "admin_transition",
    "organization_source", "stopped_worker_control", "provider_failure_control", "private_archive",
    "worker_recreation", "private_install",
}
PRIVATE_RESULTS = {"install", "repeat_install", "anonymous", "foreign_org", "revoked", "wrong_auth_host"}
CHECK_RESULTS = {
    "backend": {"composer_check", "concurrency"}, "tools": {"tools"},
    "frontend": {"npm_check", "ssr_build", "browser"}, "images": {"result"},
    "cold_worker": {"cold_worker"}, "services": {"services"}, "proxy": {"proxy"},
    "product": {"result"}, "audits": {"root", "tools", "frontend"}, "history": {"history"},
    "notices": {"notices"}, "onboarding": {"onboarding"}, "backup_runbook": {"backup_runbook"},
    "readiness": {"readiness"}, "limiter": {"limiter"}, "recovery": {"recovery"},
}


def digest(data):
    return hashlib.sha256(data).hexdigest()


def json_bytes(value):
    return (json.dumps(value, sort_keys=True, separators=(",", ":")) + "\n").encode()


def workflow_step_run(step_name, workflow=WORKFLOW):
    lines = Path(workflow).read_text(encoding="utf-8").splitlines()
    marker = f"      - name: {step_name}"
    try:
        start = lines.index(marker)
    except ValueError as error:
        raise AssertionError(f"missing release workflow step {step_name}") from error
    end = next((index for index in range(start + 1, len(lines))
                if lines[index].startswith("      - ")
                or re.match(r"^ {4}[A-Za-z_][A-Za-z0-9_-]*:", lines[index])), len(lines))
    step = lines[start:end]
    try:
        run_start = step.index("        run: |") + 1
    except ValueError as error:
        raise AssertionError(f"workflow step {step_name} has no inline run block") from error
    body = []
    for line in step[run_start:]:
        if line.startswith("          "):
            body.append(line[10:])
        elif not line:
            body.append("")
        else:
            break
    return "\n".join(body).rstrip() + "\n"


def verify_workflow_step_run(step_name):
    lines = (ROOT / ".github/workflows/verify.yml").read_text(encoding="utf-8").splitlines()
    marker = f"      - name: {step_name}"
    start = lines.index(marker)
    end = next((index for index in range(start + 1, len(lines))
                if lines[index].startswith("      - ")
                or re.match(r"^ {4}[A-Za-z_][A-Za-z0-9_-]*:", lines[index])), len(lines))
    step = lines[start:end]
    run_start = step.index("        run: |") + 1
    body = []
    for line in step[run_start:]:
        if line.startswith("          "):
            body.append(line[10:])
        elif not line:
            body.append("")
        else:
            break
    return "\n".join(body).rstrip() + "\n"


def workflow_python(step_name):
    run = workflow_step_run(step_name)
    marker = "python3 - <<'PY'\n"
    if marker not in run:
        raise AssertionError(f"workflow step {step_name} has no expected Python heredoc")
    code = run.split(marker, 1)[1].split("\nPY", 1)[0]
    return textwrap.dedent(code)


def assert_bundle_complete(test, output):
    manifest_path = output / "release-manifest.json"
    manifest = json.loads(manifest_path.read_text())
    references = {}

    def collect(value):
        if isinstance(value, dict):
            if isinstance(value.get("path"), str) and isinstance(value.get("sha256"), str):
                if value["path"].startswith("evidence/"):
                    references[value["path"]] = value["sha256"]
            for item in value.values():
                collect(item)
        elif isinstance(value, list):
            for item in value:
                collect(item)

    collect(manifest)
    for relative, expected in references.items():
        payload = (output / relative).read_bytes()
        test.assertEqual(digest(payload), expected, relative)
    sums = (output / "SHA256SUMS").read_text().splitlines()
    listed = {line.split("  ", 1)[1]: line.split("  ", 1)[0] for line in sums}
    expected = {**references, "release-manifest.json": digest(manifest_path.read_bytes())}
    test.assertEqual(listed, expected)


def oci_export(role, version, label_overrides=None):
    """Create a small synthetic OCI export; it is never presented as release evidence."""
    layer = b"synthetic layer for fixture only\n"
    layer_digest = digest(layer)
    labels = {
        "org.opencontainers.image.source": "https://github.com/packio-php/packio",
        "org.opencontainers.image.revision": SHA,
        "org.opencontainers.image.version": version,
        "org.opencontainers.image.licenses": "MIT",
    }
    labels.update(label_overrides or {})
    config = json_bytes({"os": "linux", "architecture": "amd64",
                        "config": {"Labels": labels},
                        "rootfs": {"type": "layers", "diff_ids": ["sha256:" + layer_digest]}})
    config_digest = digest(config)
    manifest = json_bytes({
        "schemaVersion": 2,
        "mediaType": "application/vnd.oci.image.manifest.v1+json",
        "config": {"mediaType": "application/vnd.oci.image.config.v1+json",
                    "digest": "sha256:" + config_digest, "size": len(config)},
        "layers": [{"mediaType": "application/vnd.oci.image.layer.v1.tar",
                    "digest": "sha256:" + layer_digest, "size": len(layer)}],
    })
    manifest_digest = digest(manifest)
    target = {"app": "prod", "worker": "prod-worker", "ssr": "prod-ssr"}[role]
    base_digest = "c" * 64
    statement = json_bytes({
        "predicateType": "https://slsa.dev/provenance/v1",
        "subject": [{"digest": {"sha256": manifest_digest}}],
        "predicate": {"buildDefinition": {
            "resolvedDependencies": [{"uri": "pkg:docker/fixture-base@1",
                                      "digest": {"sha256": base_digest}}],
            "externalParameters": {"request": {"root": {"request": {"args": {
                "target": target, "vcs:revision": SHA}}}}}}},
    })
    statement_digest = digest(statement)
    empty_config = b"{}"
    empty_config_digest = digest(empty_config)
    attestation = json_bytes({
        "schemaVersion": 2, "mediaType": "application/vnd.oci.image.manifest.v1+json",
        "artifactType": "application/vnd.docker.attestation.manifest.v1+json",
        "config": {"mediaType": "application/vnd.oci.empty.v1+json",
                   "digest": "sha256:" + empty_config_digest, "size": len(empty_config),
                   "data": base64.b64encode(empty_config).decode()},
        "layers": [{"mediaType": "application/vnd.in-toto+json",
                    "digest": "sha256:" + statement_digest, "size": len(statement)}],
        "subject": {"mediaType": "application/vnd.oci.image.manifest.v1+json",
                    "digest": "sha256:" + manifest_digest, "size": len(manifest)},
    })
    attestation_digest = digest(attestation)
    child_index = json_bytes({
        "schemaVersion": 2,
        "mediaType": "application/vnd.oci.image.index.v1+json",
        "manifests": [{"mediaType": "application/vnd.oci.image.manifest.v1+json",
                       "digest": "sha256:" + manifest_digest, "size": len(manifest),
                       "platform": {"os": "linux", "architecture": "amd64"}},
                      {"mediaType": "application/vnd.oci.image.manifest.v1+json",
                       "digest": "sha256:" + attestation_digest, "size": len(attestation),
                       "annotations": {"vnd.docker.reference.type": "attestation-manifest",
                                       "vnd.docker.reference.digest": "sha256:" + manifest_digest}}],
    })
    root_digest = digest(child_index)
    root = json_bytes({
        "schemaVersion": 2,
        "mediaType": "application/vnd.oci.image.index.v1+json",
        "manifests": [{"mediaType": "application/vnd.oci.image.index.v1+json",
                       "digest": "sha256:" + root_digest, "size": len(child_index)}],
    })
    out = io.BytesIO()
    with tarfile.open(fileobj=out, mode="w") as archive:
        for name, body in (
            ("oci-layout", json_bytes({"imageLayoutVersion": "1.0.0"})),
            ("index.json", root),
            (f"blobs/sha256/{root_digest}", child_index),
            (f"blobs/sha256/{manifest_digest}", manifest),
            (f"blobs/sha256/{config_digest}", config),
            (f"blobs/sha256/{layer_digest}", layer),
            (f"blobs/sha256/{attestation_digest}", attestation),
            (f"blobs/sha256/{empty_config_digest}", empty_config),
            (f"blobs/sha256/{statement_digest}", statement),
        ):
            info = tarfile.TarInfo(name)
            info.size = len(body)
            archive.addfile(info, io.BytesIO(body))
    return (out.getvalue(), "sha256:" + root_digest, "sha256:" + config_digest,
            ["sha256:" + layer_digest], "sha256:" + manifest_digest)


class BundleFixture:
    def __init__(self, root, level="preview", image_label_override=None):
        self.root = Path(root)
        self.version = "1.2.3" if level == "stable" else VERSION
        self.evidence = self.root / "evidence"
        self.output = self.root / "out"
        self.evidence.mkdir()
        self.output.mkdir()
        self.reports = self.evidence / "reports"
        self.reports.mkdir()
        self.images = {}
        self.runtime_manifest_digests = {}
        for role in ROLES:
            archive, local_id, config_digest, layers, runtime_manifest_digest = oci_export(
                role, self.version, image_label_override if role == "app" else None)
            path = self.evidence / "exports" / f"{role}.oci.tar"
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_bytes(archive)
            self.images[role] = {
                "local_id": local_id,
                "config_digest": config_digest,
                "export": {"path": f"exports/{role}.oci.tar", "sha256": digest(archive)},
                "base_digests": BASE_DIGESTS,
                "layer_digests": layers,
            }
            self.runtime_manifest_digests[role] = runtime_manifest_digest
        self.image_ids = {role: image["local_id"] for role, image in self.images.items()}

        for check in sorted(PREVIEW_CHECKS | (STABLE_CHECKS if level == "stable" else set())):
            record = {
                "candidate_sha": SHA,
                "image_ids": self.image_ids,
                "results": {key: "passed" for key in CHECK_RESULTS[check]},
                "exit_code": 0,
                "commands": [{"argv": ["fixture-command", check], "exit_code": 0,
                              "log": self._write_log(check)}],
            }
            if check == "product":
                record["producer_report"] = self._write_report("product-producer", {
                    "candidate_sha": SHA,
                    "images": {**self.image_ids, "caddy": "sha256:" + "4" * 64,
                               "composer": "sha256:" + "5" * 64, "mailpit": "sha256:" + "6" * 64,
                               "postgres": "sha256:" + "7" * 64},
                    "results": {key: ("fresh_project_volumes" if key == "empty_state" else "passed")
                                for key in PRODUCT_RESULTS},
                    "private_install": {"organization_id": "12345678-1234-1234-1234-123456789abc",
                                        "source_id": "abcdefab-cdef-abcd-efab-cdefabcdefab",
                                        "source_reference": SHA, "source_sha256": "8" * 64,
                                        "archive_sha256": "9" * 64,
                                        "member_sha256": {"composer.json": "a" * 64, "src/Probe.php": "b" * 64},
                                        "results": {key: "passed" for key in PRIVATE_RESULTS}},
                })
            if check == "images":
                record["producer_report"] = self._write_report("images-producer", {
                    "expected_sha": SHA, "source_sha": SHA,
                    "images": {role: {"id": self.images[role]["local_id"],
                                      "metadata_sha": None if role == "ssr" else SHA,
                                      "metadata_embeds_sha": role != "ssr", "layers_checked": 1,
                                      "layers_result": "passed", "source_sha": SHA,
                                      "base_digests": BASE_DIGESTS,
                                      "runtime_manifest_digest": self.runtime_manifest_digests[role]}
                               for role in ROLES},
                    "ssr_parity": {"result": "passed"}, "startup": {"result": "passed"},
                })
            report_path = self._write_report(check, record)
            record["report"] = report_path
            self.checks[check] = record

        onboarding = self.checks["onboarding"]
        onboarding["producer_report"] = self.checks["product"]["producer_report"]
        onboarding_report_path = self.evidence / onboarding["report"]["path"]
        onboarding_report = json.loads(onboarding_report_path.read_text())
        onboarding_report["producer_report"] = onboarding["producer_report"]
        onboarding_report_path.write_bytes(json_bytes(onboarding_report))
        onboarding["report"]["sha256"] = digest(onboarding_report_path.read_bytes())

        self.source = self.evidence / "source.tar.gz"
        source_payload = b"synthetic pinned third-party source\n"
        source_manifest = {"schema": 1, "sources": [{"package": "fixture", "version": "1.0",
                          "path": "third-party-sources/fixture-1.0.tar", "size": len(source_payload),
                          "sha256": digest(source_payload)}]}
        with tarfile.open(self.source, "w:gz") as archive:
            self._tar_file(archive, "LICENSE", b"MIT license fixture\n")
            self._tar_file(archive, "THIRD_PARTY_NOTICES.md", b"notices fixture\n")
            self._tar_file(archive, "third-party-sources.json", json_bytes(source_manifest))
            self._tar_file(archive, "third-party-sources/fixture-1.0.tar", source_payload)
            self._tar_file(archive, "data/build/release.json",
                           json_bytes({"version": self.version, "sha": SHA[:12], "dirty": False}))
        notices = self.checks["notices"]
        notices_report_path = self.evidence / notices["report"]["path"]
        notices_report = json.loads(notices_report_path.read_text())
        notices_report["source_archive_sha256"] = digest(self.source.read_bytes())
        notices_report_path.write_bytes(json_bytes(notices_report))
        notices["report"]["sha256"] = digest(notices_report_path.read_bytes())
        index = {
            "schema_version": 1, "candidate_sha": SHA, "support_revision": SUPPORT,
            "version": self.version, "level": level, "images": self.images, "checks": self.checks,
            "source_archive": {"path": "source.tar.gz", "sha256": digest(self.source.read_bytes()),
                               "candidate_sha": SHA},
        }
        if level == "stable":
            bindings = {"candidate_sha": SHA, "image_ids": self.image_ids,
                        "source_archive_sha256": index["source_archive"]["sha256"]}
            documents = {}
            for role in ROLES:
                doc = {"spdxVersion": "SPDX-2.3", "dataLicense": "CC0-1.0", "SPDXID": "SPDXRef-DOCUMENT",
                       "name": f"Packio {role} synthetic fixture", "documentNamespace": f"urn:uuid:{role}",
                       "creationInfo": {"creators": ["Tool: synthetic-fixture"], "created": "2026-01-01T00:00:00Z"},
                       "packages": [{"name": "synthetic-fixture-component", "SPDXID": "SPDXRef-Package"}]}
                documents[role] = self._write_report(f"sbom-{role}", doc)
            index["sbom"] = {**bindings, "documents": documents}
            index["provenance"] = {**bindings, "source_sha": SHA}
        self.index_path = self.evidence / "release-evidence.json"
        self.index_path.write_bytes(json_bytes(index))

    @property
    def checks(self):
        if not hasattr(self, "_checks"):
            self._checks = {}
        return self._checks

    def _tar_file(self, archive, name, body):
        info = tarfile.TarInfo(name)
        info.size = len(body)
        archive.addfile(info, io.BytesIO(body))

    def _write_log(self, name):
        body = (f"fixture only: {name}\n").encode()
        path = self.reports / f"{name}.log"
        path.write_bytes(body)
        return {"path": f"reports/{path.name}", "sha256": digest(body)}

    def _write_blob(self, name, body):
        path = self.reports / name
        path.write_bytes(body)
        return {"path": f"reports/{name}", "sha256": digest(body)}

    def _write_report(self, name, value):
        body = json_bytes(value)
        path = self.reports / f"{name}.json"
        path.write_bytes(body)
        return {"path": f"reports/{path.name}", "sha256": digest(body)}

    def write_index(self, value):
        self.index_path.write_bytes(json_bytes(value))

    def use_github_provider(self, check):
        artifact = b"synthetic GitHub artifact ZIP bytes; fixture only\n"
        artifact_path = self.evidence / "verified-runtime-images.zip"
        artifact_path.write_bytes(artifact)
        api = {
            "run": {"id": 123, "repository": {"full_name": "packio-php/packio"},
                    "path": ".github/workflows/verify.yml", "head_sha": SHA,
                    "status": "completed", "conclusion": "success", "run_attempt": 1},
            "jobs": {"jobs": [
                {"name": name, "status": "completed", "conclusion": "success"}
                for name in ("backend (base)", "backend (concurrency)", "Frontend", "secret-scan",
                             "Runtime images and private Composer product")]},
            "artifacts": {"artifacts": [{"name": "verified-runtime-images", "expired": False,
                                           "digest": "sha256:" + digest(artifact)}]},
        }
        api_ref = self._write_report("github-api", api)
        index = self.index()
        record = index["checks"][check]
        report_path = self.evidence / record["report"]["path"]
        report = json.loads(report_path.read_text())
        for key in ("commands", "exit_code"):
            record.pop(key)
            report.pop(key)
        record["provider"] = "github_actions"
        report["provider"] = "github_actions"
        record["github"] = {"run_id": 123, "api_response": api_ref}
        if check in ("images", "product", "onboarding"):
            record["github"]["artifact"] = {
                "name": "verified-runtime-images",
                "download": {"path": "verified-runtime-images.zip", "sha256": digest(artifact)},
            }
        report_path.write_bytes(json_bytes(report))
        record["report"]["sha256"] = digest(report_path.read_bytes())
        self.write_index(index)

    def index(self):
        return json.loads(self.index_path.read_text())

    def run(self, level=None, version=None):
        return subprocess.run(
            [sys.executable, str(ASSEMBLER), "--level", level or self.index()["level"],
             "--candidate-sha", SHA, "--version", version or self.index()["version"],
             "--evidence-dir", str(self.evidence), "--output-dir", str(self.output)],
            capture_output=True, text=True, check=False,
        )

    def assert_rejected(self, test, *, level=None, version=VERSION):
        result = self.run(level, version)
        test.assertNotEqual(result.returncode, 0, result.stdout)
        test.assertIn("release preparation failed:", result.stderr)
        return result



def detach_onto_new_commit(checkout):
    """Return (default_sha, selected_sha): a fresh commit that no branch or tag of the clone points to."""
    default_sha = subprocess.check_output(["git", "-C", str(checkout), "rev-parse", "HEAD"], text=True).strip()
    subprocess.run(["git", "-C", str(checkout), "checkout", "--detach"], check=True,
                   stdout=subprocess.DEVNULL, stderr=subprocess.PIPE, text=True)
    subprocess.run(["git", "-C", str(checkout), "-c", "user.name=fixture", "-c", "user.email=fixture@example.test",
                    "commit", "--allow-empty", "-m", "fixture: selected ref"], check=True,
                   stdout=subprocess.DEVNULL, stderr=subprocess.PIPE, text=True)
    selected_sha = subprocess.check_output(["git", "-C", str(checkout), "rev-parse", "HEAD"], text=True).strip()
    return default_sha, selected_sha

class ReleaseManifestTests(unittest.TestCase):
    def fixture(self, level="preview", image_label_override=None):
        temporary = tempfile.TemporaryDirectory()
        self.addCleanup(temporary.cleanup)
        return BundleFixture(temporary.name, level, image_label_override)

    def test_complete_preview_creates_local_draft_with_three_bound_images(self):
        fixture = self.fixture()
        result = fixture.run()
        self.assertEqual(result.returncode, 0, result.stderr)
        manifest = json.loads((fixture.output / "release-manifest.json").read_text())
        self.assertEqual(manifest["candidate_sha"], SHA)
        self.assertEqual(set(manifest["images"]), set(ROLES))
        self.assertTrue(all(image["registry_digest"] is None for image in manifest["images"].values()))
        self.assertNotIn("complete", manifest)
        self.assertNotIn("latest", manifest)
        for image in manifest["images"].values():
            reference = image["export"]
            self.assertTrue((fixture.output / reference["path"]).is_file())
        self.assertTrue((fixture.output / manifest["source_archive"]["path"]).is_file())
        checksums = (fixture.output / "SHA256SUMS").read_text()
        self.assertIn(digest((fixture.output / "release-manifest.json").read_bytes()), checksums)
        self.assertIn(manifest["source_archive"]["sha256"], checksums)
        assert_bundle_complete(self, fixture.output)

    def test_github_provider_requires_successful_candidate_run_and_bound_runtime_artifact(self):
        fixture = self.fixture()
        fixture.use_github_provider("images")
        result = fixture.run()
        self.assertEqual(result.returncode, 0, result.stderr)
        assert_bundle_complete(self, fixture.output)

        for mutate in (
            lambda api: api["run"].update(head_sha="c" * 40),
            lambda api: api["run"].update(conclusion="failure"),
            lambda api: api["jobs"]["jobs"].pop(),
            lambda api: api["artifacts"]["artifacts"][0].update(expired=True),
        ):
            fixture = self.fixture()
            fixture.use_github_provider("images")
            index = fixture.index()
            ref = index["checks"]["images"]["github"]["api_response"]
            path = fixture.evidence / ref["path"]
            api = json.loads(path.read_text())
            mutate(api)
            body = json_bytes(api)
            path.write_bytes(body)
            ref["sha256"] = digest(body)
            fixture.write_index(index)
            fixture.assert_rejected(self)

        fixture = self.fixture()
        fixture.use_github_provider("images")
        index = fixture.index()
        download = index["checks"]["images"]["github"]["artifact"]["download"]
        download["sha256"] = "0" * 64
        fixture.write_index(index)
        fixture.assert_rejected(self)

    def test_actual_workflow_notice_producer_binds_archive_and_passes_assembler(self):
        script = workflow_python("Prepare complete local preview manifest")
        tree = ast.parse(script)
        function = next(node for node in tree.body if isinstance(node, ast.FunctionDef)
                        and node.name == "record_local")
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            (root / "logs").mkdir()
            for name in ("notices.log", "notices-source.log"):
                (root / "logs" / name).write_text("passed\n", encoding="utf-8")

            def file_digest(path):
                return hashlib.sha256(path.read_bytes()).hexdigest()

            def write_json(relative, value):
                data = json_bytes(value)
                target = root / relative
                target.parent.mkdir(parents=True, exist_ok=True)
                target.write_bytes(data)
                return {"path": relative, "sha256": hashlib.sha256(data).hexdigest()}

            scope = {"root": root, "sha": SHA,
                     "ids": {role: "sha256:" + role for role in ROLES},
                     "digest": file_digest, "write_json": write_json, "checks": {}}
            exec(compile(ast.Module(body=[function], type_ignores=[]),
                         "release.yml:record_local", "exec"), scope)
            source_sha = "b" * 64
            scope["record_local"]("notices", {"notices": "passed"}, [
                (["python3", "test/Support/Release/notices.test.py"], "notices.log"),
                (["python3", "test/Support/Release/notices.test.py", "--source-archive-only"],
                 "notices-source.log"),
            ], {"source_archive_sha256": source_sha})
            spec = importlib.util.spec_from_file_location("packio_manifest_assembler", ASSEMBLER)
            assembler = importlib.util.module_from_spec(spec)
            spec.loader.exec_module(assembler)
            report = json.loads((root / "reports/notices.json").read_text(encoding="utf-8"))
            self.assertEqual(report["source_archive_sha256"], source_sha)
            assembler.validate_check(root, "notices", scope["checks"]["notices"], SHA,
                                     scope["ids"], source_sha)

    def test_actual_workflow_redirect_blocks_second_host_and_strips_auth(self):
        tree = ast.parse(workflow_python("Download the bound verification artifact"))
        redirect = next(node for node in tree.body if isinstance(node, ast.ClassDef)
                        and node.name == "SafeArtifactRedirect")
        scope = {"urllib": urllib, "re": re}
        exec(compile(ast.Module(body=[redirect], type_ignores=[]),
                     "release.yml:SafeArtifactRedirect", "exec"), scope)
        handler = scope["SafeArtifactRedirect"]()
        request = urllib.request.Request("https://api.github.com/artifact",
                                         headers={"Authorization": "Bearer fake-canary"})
        allowed = handler.redirect_request(request, None, 302, "Found", {},
                                           "https://objects.githubusercontent.com/artifact.zip")
        self.assertNotIn("Authorization", allowed.headers)
        self.assertNotIn("Authorization", allowed.unredirected_hdrs)
        api_auth = []
        storage_requests = []

        class Api(BaseHTTPRequestHandler):
            def do_GET(self):
                api_auth.append(self.headers.get("Authorization"))
                self.send_response(302)
                self.send_header("Location", f"http://127.0.0.2:{api.server_port}/canary")
                self.end_headers()

            def log_message(self, *_):
                pass

        class Storage(BaseHTTPRequestHandler):
            def do_GET(self):
                storage_requests.append(self.headers.get("Authorization"))
                self.send_response(200)
                self.end_headers()

            def log_message(self, *_):
                pass

        api = ThreadingHTTPServer(("127.0.0.1", 0), Api)
        storage = ThreadingHTTPServer(("127.0.0.2", api.server_port), Storage)
        threads = [threading.Thread(target=server.serve_forever, daemon=True)
                   for server in (api, storage)]
        for thread in threads:
            thread.start()
        try:
            opener = urllib.request.build_opener(urllib.request.ProxyHandler({}),
                                                 scope["SafeArtifactRedirect"]())
            local_request = urllib.request.Request(
                f"http://127.0.0.1:{api.server_port}/artifact",
                headers={"Authorization": "Bearer fake-canary"})
            with self.assertRaises(SystemExit):
                opener.open(local_request, timeout=3)
            self.assertEqual(api_auth, ["Bearer fake-canary"])
            self.assertEqual(storage_requests, [])
        finally:
            for server in (api, storage):
                server.shutdown()
                server.server_close()
            for thread in threads:
                thread.join(timeout=3)

    def test_runtime_job_runs_only_for_manual_runs_and_release_tags(self):
        workflow = (ROOT / ".github/workflows/verify.yml").read_text(encoding="utf-8")
        triggers = workflow.split("\npermissions:", 1)[0]
        self.assertIn("  push:\n    branches: [main]\n    tags: ['v*']\n", triggers)
        self.assertIn("  workflow_dispatch:\n", triggers)
        job = workflow.split("\n  runtime:\n", 1)[1].split("\n    steps:", 1)[0]
        self.assertIn("    if: github.event_name == 'workflow_dispatch' || startsWith(github.ref, 'refs/tags/v')\n", job)
        self.assertEqual(workflow.count("\n    if: "), 1, "every other job runs for each event")

    def test_manual_workflow_resolves_nondefault_tag_once(self):
        script = workflow_step_run("Resolve the selected ref once and bind it to verification")
        self.assertEqual(script.count("git rev-parse --verify 'HEAD^{commit}'"), 1)
        with tempfile.TemporaryDirectory() as directory:
            checkout = Path(directory) / "checkout"
            subprocess.run(["git", "clone", "--no-hardlinks", str(ROOT), str(checkout)],
                           check=True, stdout=subprocess.DEVNULL, stderr=subprocess.PIPE, text=True)
            default_sha, selected_sha = detach_onto_new_commit(checkout)
            self.assertNotEqual(selected_sha, default_sha)
            output = Path(directory) / "github-output"
            env = {"PATH": os.environ.get("PATH", ""), "EXPECTED_SHA": selected_sha,
                   "GITHUB_OUTPUT": str(output)}
            subprocess.run(["bash", "-euo", "pipefail", "-c", script], cwd=checkout,
                           env=env, check=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
            self.assertEqual(output.read_text(encoding="utf-8").strip(), "sha=" + selected_sha)

    def test_runtime_workflow_builds_all_images_with_native_candidate_labels(self):
        script = verify_workflow_step_run("Require Docker and verify production images, layers and startup")
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            checkout = root / "checkout"
            subprocess.run(["git", "clone", "--no-hardlinks", str(ROOT), str(checkout)],
                           check=True, stdout=subprocess.DEVNULL, stderr=subprocess.PIPE, text=True)
            default_sha, selected_sha = detach_onto_new_commit(checkout)
            self.assertNotEqual(selected_sha, default_sha)
            (checkout / "vendor").symlink_to(ROOT / "vendor", target_is_directory=True)
            with (checkout / ".git/info/exclude").open("a", encoding="utf-8") as excluded:
                excluded.write("\n/vendor\n")
            metadata_path = root / "expected-release.json"
            subprocess.run(["php", "bin/generate-release-metadata.php", "--output=" + str(metadata_path)],
                           cwd=checkout, check=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)

            bindir = root / "bin"
            bindir.mkdir()
            docker_log = root / "docker.log"
            docker = bindir / "docker"
            docker.write_text(textwrap.dedent("""\
                #!/bin/bash
                set -eu
                printf '%s\\n' "$*" >> "$DOCKER_LOG"
                if [ "$1" = build ]; then
                  shift
                  target= iidfile= metadatafile=
                  while [ "$#" -gt 0 ]; do
                    case "$1" in
                      --target) target="$2"; shift 2 ;;
                      --iidfile) iidfile="$2"; shift 2 ;;
                      --metadata-file) metadatafile="$2"; shift 2 ;;
                      *) shift ;;
                    esac
                  done
                  case "$target" in
                    prod) image_id=sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa ;;
                    prod-worker) image_id=sha256:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb ;;
                    prod-ssr) image_id=sha256:cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc ;;
                  esac
                  printf '%s\\n' "$image_id" > "$iidfile"
                  printf '{"containerimage.digest":"sha256:%064d"}\\n' 1 > "$metadatafile"
                elif [ "$1" = image ] && [ "$2" = inspect ]; then
                  printf 'inspect %s\\n' "$3" >> "$DOCKER_LOG"
                  [ "${DOCKER_COLLISION:-}" = "$3" ]
                elif [ "$1" = save ]; then
                  while [ "$#" -gt 1 ]; do
                    if [ "$2" = -o ]; then : > "$3"; break; fi
                    shift
                  done
                fi
                exit 0
                """), encoding="utf-8")
            docker.chmod(0o755)
            python = bindir / "python3"
            python.write_text("#!/bin/sh\nprintf '%s\\n' \"$*\" >> \"$PYTHON_LOG\"\nexit 0\n", encoding="utf-8")
            python.chmod(0o755)
            git_log = root / "git.log"
            git = bindir / "git"
            git.write_text(f"#!/bin/sh\nprintf '%s\\n' \"$*\" >> \"$GIT_LOG\"\nexec {shutil.which('git')} \"$@\"\n",
                           encoding="utf-8")
            git.chmod(0o755)
            env = {"PATH": f"{bindir}:{os.environ['PATH']}", "RUNNER_TEMP": str(root),
                   "DOCKER_LOG": str(docker_log), "PYTHON_LOG": str(root / "python.log"),
                   "GIT_LOG": str(git_log), "GITHUB_ENV": str(root / "github-env"),
                   "GITHUB_RUN_ID": "314159", "GITHUB_RUN_ATTEMPT": "2", "DOCKER_COLLISION": ""}
            result = subprocess.run(["bash", "-euo", "pipefail", "-c", script], cwd=checkout,
                                    env=env, text=True, capture_output=True)
            self.assertEqual(result.returncode, 0, result.stderr)
            builds = [line for line in docker_log.read_text().splitlines() if line.startswith("build ")]
            self.assertEqual(len(builds), 3)
            metadata = json.loads(metadata_path.read_text())
            expected = {
                "org.opencontainers.image.source=https://github.com/packio-php/packio",
                f"org.opencontainers.image.revision={selected_sha}",
                f"org.opencontainers.image.version={metadata['version']}",
                "org.opencontainers.image.licenses=MIT",
            }
            for build in builds:
                for label in expected:
                    self.assertIn("--label " + shlex.quote(label), build)
                target = next(target for target in ("prod", "prod-worker", "prod-ssr")
                              if "--target " + target + " " in build)
                self.assertIn("--iidfile data/coverage/images/" + target + ".id", build)
                self.assertIn("--metadata-file data/coverage/images/" + target + ".build.json", build)
                self.assertTrue((checkout / "data/coverage/images" / (target + ".id")).is_file())
                self.assertTrue((checkout / "data/coverage/images" / (target + ".build.json")).is_file())
                docker_version = metadata["version"].replace("+", "-")
                self.assertIn("--tag packio-local-" + target + "-314159-2:" + docker_version, build)
            self.assertEqual(sum("rev-parse --verify HEAD^{commit}" in line for line in git_log.read_text().splitlines()), 1)
            self.assertIn("PACKIO_CANDIDATE_SHA=" + selected_sha,
                          (root / "github-env").read_text())
            save = next(line for line in docker_log.read_text().splitlines() if line.startswith("save "))
            for image_id in ("sha256:" + "a" * 64, "sha256:" + "b" * 64, "sha256:" + "c" * 64):
                self.assertIn(image_id, save)
            python_calls = (root / "python.log").read_text().splitlines()
            self.assertTrue(any("--expected-sha " + selected_sha in call for call in python_calls))
            product_env = {**env, "PACKIO_CANDIDATE_SHA": selected_sha}
            product = subprocess.run(
                ["bash", "-euo", "pipefail", "-c",
                 verify_workflow_step_run("Require private Composer install and tenant isolation on the same immutable images")],
                cwd=checkout, env=product_env, text=True, capture_output=True)
            self.assertEqual(product.returncode, 0, product.stderr)
            self.assertTrue(any("product.test.py" in call and "--expected-sha " + selected_sha in call
                                for call in (root / "python.log").read_text().splitlines()))

            image_ids_before_collision = {
                target: (checkout / "data/coverage/images" / (target + ".id")).read_bytes()
                for target in ("prod", "prod-worker", "prod-ssr")
            }
            docker_log.write_text("", encoding="utf-8")
            collision_tag = f"packio-local-prod-worker-314159-2:{metadata['version'].replace('+', '-')}"
            collision_env = {**env, "DOCKER_COLLISION": collision_tag}
            collision = subprocess.run(["bash", "-euo", "pipefail", "-c", script], cwd=checkout,
                                       env=collision_env, text=True, capture_output=True)
            self.assertNotEqual(collision.returncode, 0, "existing local reference must stop the run")
            collision_calls = docker_log.read_text().splitlines()
            self.assertTrue(any(line == "inspect " + collision_tag for line in collision_calls))
            self.assertFalse(any(line.startswith("build ") for line in collision_calls),
                             "all local refs must be checked before any image build")
            self.assertEqual(image_ids_before_collision, {
                target: (checkout / "data/coverage/images" / (target + ".id")).read_bytes()
                for target in ("prod", "prod-worker", "prod-ssr")
            })

    def test_release_source_step_reuses_clean_metadata_from_imported_app(self):
        script = workflow_step_run(
            "Create source archive and verify notices against the imported images",
            ROOT / ".github/workflows/release.yml")
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            checkout = root / "checkout"
            subprocess.run(["git", "clone", "--no-hardlinks", str(ROOT), str(checkout)],
                           check=True, stdout=subprocess.DEVNULL, stderr=subprocess.PIPE, text=True)
            _default_sha, selected_sha = detach_onto_new_commit(checkout)
            (checkout / "vendor").symlink_to(ROOT / "vendor", target_is_directory=True)
            with (checkout / ".git/info/exclude").open("a", encoding="utf-8") as excluded:
                excluded.write("\n/vendor\n")
            image_metadata = root / "image-release.json"
            subprocess.run(["php", "bin/generate-release-metadata.php", "--output=" + str(image_metadata)],
                           cwd=checkout, check=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
            clean_release = json.loads(image_metadata.read_text())
            self.assertIs(clean_release["dirty"], False)
            self.assertTrue(selected_sha.startswith(clean_release["sha"]))
            evidence = checkout / "evidence/runtime"
            evidence.mkdir(parents=True)
            (evidence / "prod.id").write_text("sha256:" + "a" * 64 + "\n", encoding="utf-8")

            archive = root / "git-archive.tar"
            with tarfile.open(archive, "w") as result:
                body = b"Synthetic fixture runbook.\n"
                info = tarfile.TarInfo("docs/operations/backup-restore.md")
                info.size = len(body)
                info.mode = 0o644
                result.addfile(info, io.BytesIO(body))

            bindir = root / "bin"
            bindir.mkdir()
            composer = bindir / "composer"
            composer.write_text("#!/bin/sh\nprintf 'composer invoked\\n' >> \"$COMPOSER_LOG\"\nexit 0\n",
                                encoding="utf-8")
            composer.chmod(0o755)
            docker = bindir / "docker"
            docker.write_text("#!/bin/sh\nprintf '%s\\n' \"$*\" >> \"$DOCKER_LOG\"\nif [ \"$1\" = run ]; then cat \"$IMAGE_METADATA\"; fi\nexit 0\n",
                              encoding="utf-8")
            docker.chmod(0o755)
            git = bindir / "git"
            git.write_text(
                f"#!/bin/sh\nif [ \"$1\" = archive ]; then cat \"$GIT_ARCHIVE_TAR\"; else exec {shutil.which('git')} \"$@\"; fi\n",
                encoding="utf-8")
            git.chmod(0o755)
            python = bindir / "python3"
            python.write_text(textwrap.dedent(f"""\
                #!/bin/sh
                case "$1" in
                  bin/prepare-notice-sources.py)
                    mkdir -p "$3"
                    printf 'Synthetic fixture notice\\n' > "$3/fixture.txt"
                    exit 0
                    ;;
                  test/Support/Release/notices.test.py) exit 0 ;;
                  *) exec {shutil.which('python3')} "$@" ;;
                esac
                """), encoding="utf-8")
            python.chmod(0o755)
            env = {"PATH": f"{bindir}:{os.environ['PATH']}", "RUNNER_TEMP": str(root),
                   "CANDIDATE_SHA": selected_sha, "GITHUB_OUTPUT": str(root / "github-output"),
                   "IMAGE_METADATA": str(image_metadata), "GIT_ARCHIVE_TAR": str(archive),
                   "DOCKER_LOG": str(root / "docker.log"), "COMPOSER_LOG": str(root / "composer.log")}

            def run_source_step():
                return subprocess.run(["bash", "-euo", "pipefail", "-c", script], cwd=checkout,
                                      env=env, text=True, capture_output=True)

            result = run_source_step()
            self.assertEqual(result.returncode, 0, result.stderr)
            source_archive = checkout / "evidence/output/source.tar.gz"
            self.assertTrue(source_archive.is_file())
            with tarfile.open(source_archive, "r:gz") as packaged:
                packaged_release = json.load(packaged.extractfile("data/build/release.json"))
            self.assertEqual(packaged_release, clean_release)
            self.assertIn("version=" + clean_release["version"] + "\n",
                          (root / "github-output").read_text())
            docker_call = (root / "docker.log").read_text().strip()
            self.assertEqual(docker_call, "run --pull=never --rm --network none --read-only "
                             "--entrypoint cat sha256:" + "a" * 64 + " /app/data/build/release.json")
            self.assertFalse((root / "composer.log").exists())

            invalid_metadata = []
            dirty = copy.deepcopy(clean_release)
            dirty["dirty"] = True
            invalid_metadata.append(dirty)
            wrong_sha = copy.deepcopy(clean_release)
            wrong_sha["sha"] = "deadbee"
            invalid_metadata.append(wrong_sha)
            invalid_version = copy.deepcopy(clean_release)
            invalid_version["version"] = "not-a-version"
            invalid_metadata.append(invalid_version)
            for invalid in invalid_metadata:
                image_metadata.write_text(json.dumps(invalid), encoding="utf-8")
                source_archive.unlink(missing_ok=True)
                rejected = run_source_step()
                self.assertNotEqual(rejected.returncode, 0, "invalid image metadata must fail")
                self.assertFalse(source_archive.exists(), "metadata must be rejected before source archive creation")

    def test_release_upload_tree_contains_only_checksums_and_bundle_files(self):
        fixture = self.fixture()
        source = fixture.evidence / "output/source.tar.gz"
        source.parent.mkdir(parents=True)
        source.write_bytes(fixture.source.read_bytes())
        index = fixture.index()
        index["source_archive"]["path"] = "output/source.tar.gz"
        index["source_archive"]["sha256"] = digest(source.read_bytes())
        notices = index["checks"]["notices"]
        notices_report_path = fixture.evidence / notices["report"]["path"]
        notices_report = json.loads(notices_report_path.read_text())
        notices_report["source_archive_sha256"] = index["source_archive"]["sha256"]
        notices_report_path.write_bytes(json_bytes(notices_report))
        notices["report"]["sha256"] = digest(notices_report_path.read_bytes())
        fixture.write_index(index)
        (fixture.evidence / "output/source-base.tar").write_bytes(b"workflow intermediate tar fixture\n")
        artifact = fixture.evidence / "verified-runtime-images.zip"
        artifact.write_bytes(b"synthetic artifact fixture\n")
        (fixture.root / "bin").symlink_to(ROOT / "bin", target_is_directory=True)

        step = workflow_step_run("Prepare complete local preview manifest")
        prepare_start = re.search(r"(?m)^mkdir -p [^\n]+\n(?=python3 bin/prepare-release\.py)", step)
        self.assertIsNotNone(prepare_start)
        tail = step[prepare_start.start():]
        release_lines = (ROOT / ".github/workflows/release.yml").read_text(encoding="utf-8").splitlines()
        upload_start = next(i for i, line in enumerate(release_lines)
                            if line == "      - uses: actions/upload-artifact@v4"
                            and i > release_lines.index("      - name: Prepare complete local preview manifest"))
        upload_end = next((i for i in range(upload_start + 1, len(release_lines))
                           if release_lines[i].startswith("      - ")), len(release_lines))
        upload_path = next(re.match(r"          path: (.+)", line).group(1)
                           for line in release_lines[upload_start:upload_end]
                           if re.match(r"          path: (.+)", line))
        env = {**os.environ, "CANDIDATE_SHA": SHA, "VERSION": VERSION,
               "DOCKERHUB_NAMESPACE": "sirix777",
               "GITHUB_STEP_SUMMARY": str(fixture.root / "summary.md")}
        result = subprocess.run(["bash", "-euo", "pipefail", "-c", tail], cwd=fixture.root,
                                env=env, text=True, capture_output=True)
        self.assertEqual(result.returncode, 0, result.stderr)
        uploaded = fixture.root / upload_path.rstrip("/")
        checksums = (uploaded / "SHA256SUMS").read_text(encoding="utf-8").splitlines()
        listed = {line.split("  ", 1)[1] for line in checksums}
        actual = {path.relative_to(uploaded).as_posix() for path in uploaded.rglob("*") if path.is_file()}
        self.assertEqual(actual, listed | {"SHA256SUMS"})
        artifact_sha = digest(artifact.read_bytes())
        summary = fixture.root / "summary.md"
        digest_matches = f"Runtime artifact SHA-256: `{artifact_sha}`" in summary.read_text()

        artifact.unlink()
        summary.write_text("", encoding="utf-8")
        missing = subprocess.run(["bash", "-euo", "pipefail", "-c", tail], cwd=fixture.root,
                                 env=env, text=True, capture_output=True)
        missing_artifact_fails = missing.returncode != 0
        missing_digest_not_reported = "Runtime artifact SHA-256:" not in summary.read_text()
        self.assertEqual((digest_matches, missing_artifact_fails, missing_digest_not_reported),
                         (True, True, True))

    def test_actual_preflight_uses_official_environment_shape_and_blocks_unapproved_actors(self):
        script = workflow_python("Check the requested ref and repository settings")
        sha = "a" * 40
        runtime_steps = (
            "Require Docker and verify production images, layers and startup",
            "Verify real production SSR and proxy browser contract",
            "Require private Composer install and tenant isolation",
        )
        jobs = [
            {"name": "backend (base)", "status": "completed", "conclusion": "success",
             "steps": [{"name": name, "status": "completed", "conclusion": "success"} for name in
                       ("composer audit --locked", "Validate and audit locked tools",
                        "composer check && composer openapi-check")]},
            {"name": "backend (concurrency)", "status": "completed", "conclusion": "success",
             "steps": [{"name": "composer test:concurrency", "status": "completed", "conclusion": "success"}]},
            {"name": "frontend", "status": "completed", "conclusion": "success",
             "steps": [{"name": name, "status": "completed", "conclusion": "success"}
                       for name in ("npm audit && npm ls", "npm run check")]},
            {"name": "Scan checkout and selected publication history", "status": "completed",
             "conclusion": "success", "steps": [{"name": "Verify frozen refs and scan with pinned native Gitleaks",
                                                  "status": "completed", "conclusion": "success"}]},
            {"name": "Runtime images and private Composer product", "status": "completed",
             "conclusion": "success", "steps": [{"name": name, "status": "completed", "conclusion": "success"}
                                                  for name in runtime_steps]},
        ]
        api_responses = [
            {"id": 42, "login": "release-owner"},
            {"can_admins_bypass": False, "protection_rules": [{"type": "required_reviewers",
             "prevent_self_review": False, "reviewers": [{"type": "User", "reviewer": {
                 "id": 42, "login": "release-owner"}}]}]},
            {"id": 1234, "run_attempt": 1, "repository": {"full_name": "packio-php/packio"},
             "head_repository": {"full_name": "packio-php/packio"}, "event": "workflow_dispatch",
             "status": "completed", "conclusion": "success", "path": ".github/workflows/verify.yml",
             "head_sha": sha},
            {"jobs": jobs},
            {"artifacts": [{"id": 89, "name": "verified-runtime-images", "expired": False,
                             "digest": "sha256:" + "b" * 64}]},
        ]
        env = {"GH_REPOSITORY": "packio-php/packio", "GH_OWNER": "release-owner",
               "GH_ACTOR": "release-owner", "GH_TRIGGERING_ACTOR": "release-owner",
               "GH_RUN_ID": "1234", "DOCKERHUB_NAMESPACE": "sirix777",
               "VERSION_POLICY": "native-semver", "GH_API_URL": "https://api.github.com",
               "GH_TOKEN": "fake-canary", "RUNNER_TEMP": "", "GITHUB_OUTPUT": ""}

        class Response:
            def __init__(self, value):
                self.payload = json.dumps(value).encode()

            def __enter__(self):
                return self

            def __exit__(self, *_):
                return False

            def read(self, *_):
                return self.payload

        def run_preflight(snapshot, changes=None):
            with tempfile.TemporaryDirectory() as directory:
                output = Path(directory) / "github-output"
                env_values = {**env, **(changes or {}), "RUNNER_TEMP": directory,
                              "GITHUB_OUTPUT": str(output)}
                responses = copy.deepcopy(snapshot)

                def api_response(request, timeout=30):
                    self.assertEqual(request.get_header("Authorization"), "Bearer fake-canary")
                    self.assertGreaterEqual(timeout, 1)
                    return Response(responses.pop(0))

                with patch.dict(os.environ, env_values, clear=True), \
                     patch("urllib.request.urlopen", side_effect=api_response):
                    exec(compile(script, "release.yml:preflight-fixture", "exec"), {})
                    self.assertEqual(responses, [])
                    return output.read_text(encoding="utf-8")

        output = run_preflight(api_responses)
        self.assertIn("candidate_sha=" + sha, output)

        for actor_key in ("GH_ACTOR", "GH_TRIGGERING_ACTOR"):
            with patch.dict(os.environ, {**env, actor_key: "someone-else"}, clear=True), \
                 patch("urllib.request.urlopen") as urlopen:
                with self.assertRaisesRegex(SystemExit, "only RELEASE_OWNER_LOGIN"):
                    exec(compile(script, "release.yml:foreign-actor-fixture", "exec"), {})
                urlopen.assert_not_called()

        for field, value, error in (
            ("prevent_self_review", True, "allow its sole owner"),
            ("can_admins_bypass", True, "disable administrator bypass"),
        ):
            invalid = copy.deepcopy(api_responses)
            if field == "prevent_self_review":
                invalid[1]["protection_rules"][0][field] = value
            else:
                invalid[1][field] = value
            with self.assertRaisesRegex(SystemExit, error):
                run_preflight(invalid)

    def test_stable_requires_stable_gates_and_bound_sbom_provenance(self):
        fixture = self.fixture("stable")
        result = fixture.run()
        self.assertEqual(result.returncode, 0, result.stderr)
        manifest = json.loads((fixture.output / "release-manifest.json").read_text())
        self.assertEqual(manifest["level"], "stable")
        self.assertEqual(manifest["provenance"]["image_ids"], fixture.image_ids)

    def test_missing_or_skipped_required_check_is_rejected(self):
        for mutate in (lambda value: value["checks"].pop("notices"),
                       lambda value: value["checks"]["notices"].update(results={"result": "skipped"})):
            fixture = self.fixture()
            index = fixture.index()
            mutate(index)
            fixture.write_index(index)
            fixture.assert_rejected(self)
            self.assertFalse((fixture.output / "release-manifest.json").exists())

    def test_failed_check_exit_and_false_boolean_are_rejected(self):
        for mutate in (lambda value: value["checks"]["history"].update(exit_code=1),
                       lambda value: value["checks"]["history"].update(exit_code=True),
                       lambda value: value["checks"]["history"].update(candidate_sha="c" * 40),
                       lambda value: value["checks"]["history"]["report"].update(sha256="0" * 64)):
            fixture = self.fixture()
            index = fixture.index()
            mutate(index)
            fixture.write_index(index)
            fixture.assert_rejected(self)

    def test_candidate_sha_and_image_set_are_exact(self):
        for mutate in (lambda value: value.update(candidate_sha="c" * 40),
                       lambda value: value["images"].pop("ssr"),
                       lambda value: value["checks"]["services"].update(
                           image_ids={"app": value["images"]["app"]["local_id"]})):
            fixture = self.fixture()
            index = fixture.index()
            mutate(index)
            fixture.write_index(index)
            fixture.assert_rejected(self)

    def test_actual_d3_report_and_export_content_must_match_index(self):
        fixture = self.fixture()
        index = fixture.index()
        image_report = index["checks"]["images"]["producer_report"]
        report = json.loads((fixture.evidence / image_report["path"]).read_text())
        report["images"]["worker"]["id"] = "sha256:" + "f" * 64
        body = json_bytes(report)
        (fixture.evidence / image_report["path"]).write_bytes(body)
        image_report["sha256"] = digest(body)
        fixture.write_index(index)
        self.assertNotEqual(fixture.run().returncode, 0)

        fixture = self.fixture()
        export = fixture.evidence / fixture.index()["images"]["app"]["export"]["path"]
        export.write_bytes(export.read_bytes() + b"changed")
        fixture.assert_rejected(self)

    def test_native_seven_character_metadata_revision_prefix_is_accepted(self):
        fixture = self.fixture()
        index = fixture.index()
        reference = index["checks"]["images"]["producer_report"]
        report_path = fixture.evidence / reference["path"]
        report = json.loads(report_path.read_text())
        for role in ("app", "worker"):
            report["images"][role]["metadata_sha"] = SHA[:7]
        report_path.write_bytes(json_bytes(report))
        reference["sha256"] = digest(report_path.read_bytes())
        check_report_ref = index["checks"]["images"]["report"]
        check_report_path = fixture.evidence / check_report_ref["path"]
        check_report = json.loads(check_report_path.read_text())
        check_report["producer_report"] = reference
        check_report_path.write_bytes(json_bytes(check_report))
        check_report_ref["sha256"] = digest(check_report_path.read_bytes())

        source_path = fixture.evidence / "source.tar.gz"
        files = {}
        with tarfile.open(source_path, "r:gz") as archive:
            for member in archive:
                files[member.name] = archive.extractfile(member).read()
        metadata = json.loads(files["data/build/release.json"])
        metadata["sha"] = SHA[:7]
        files["data/build/release.json"] = json_bytes(metadata)
        with tarfile.open(source_path, "w:gz") as archive:
            for name, body in files.items():
                fixture._tar_file(archive, name, body)
        index["source_archive"]["sha256"] = digest(source_path.read_bytes())
        notices = index["checks"]["notices"]
        notices_report_path = fixture.evidence / notices["report"]["path"]
        notices_report = json.loads(notices_report_path.read_text())
        notices_report["source_archive_sha256"] = index["source_archive"]["sha256"]
        notices_report_path.write_bytes(json_bytes(notices_report))
        notices["report"]["sha256"] = digest(notices_report_path.read_bytes())
        fixture.write_index(index)
        result = fixture.run()
        self.assertEqual(result.returncode, 0, result.stderr)

    def test_dirty_or_mismatched_source_archive_is_rejected(self):
        fixture = self.fixture()
        index = fixture.index()
        index["source_archive"]["sha256"] = "0" * 64
        fixture.write_index(index)
        fixture.assert_rejected(self)
        fixture = self.fixture()
        source = fixture.evidence / "source.tar.gz"
        files = {}
        with tarfile.open(source, "r:gz") as archive:
            for member in archive:
                files[member.name] = archive.extractfile(member).read()
        metadata = json.loads(files["data/build/release.json"])
        metadata["dirty"] = True
        files["data/build/release.json"] = json_bytes(metadata)
        with tarfile.open(source, "w:gz") as archive:
            for name, body in files.items():
                fixture._tar_file(archive, name, body)
        index = fixture.index()
        index["source_archive"]["sha256"] = digest(source.read_bytes())
        fixture.write_index(index)
        fixture.assert_rejected(self)

    def test_source_archive_version_must_match_requested_version(self):
        fixture = self.fixture()
        source = fixture.evidence / "source.tar.gz"
        files = {}
        with tarfile.open(source, "r:gz") as archive:
            for member in archive:
                files[member.name] = archive.extractfile(member).read()
        metadata = json.loads(files["data/build/release.json"])
        metadata["version"] = "9.9.9"
        files["data/build/release.json"] = json_bytes(metadata)
        with tarfile.open(source, "w:gz") as archive:
            for name, body in files.items():
                fixture._tar_file(archive, name, body)
        index = fixture.index()
        index["source_archive"]["sha256"] = digest(source.read_bytes())
        notices = index["checks"]["notices"]
        report_path = fixture.evidence / notices["report"]["path"]
        report = json.loads(report_path.read_text())
        report["source_archive_sha256"] = index["source_archive"]["sha256"]
        report_path.write_bytes(json_bytes(report))
        notices["report"]["sha256"] = digest(report_path.read_bytes())
        fixture.write_index(index)
        fixture.assert_rejected(self)

    def test_paths_symlinks_duplicate_json_and_existing_version_are_rejected(self):
        fixture = self.fixture()
        index = fixture.index()
        index["checks"]["notices"]["report"]["path"] = "../../outside.json"
        fixture.write_index(index)
        fixture.assert_rejected(self)

        fixture = self.fixture()
        link = fixture.evidence / "reports" / "linked.log"
        link.symlink_to(fixture.evidence / "reports" / "notices.log")
        index = fixture.index()
        index["checks"]["notices"]["commands"][0]["log"]["path"] = "reports/linked.log"
        fixture.write_index(index)
        fixture.assert_rejected(self)

        fixture = self.fixture()
        raw = fixture.index_path.read_text()
        fixture.index_path.write_text(raw.replace('"schema_version":1,', '"schema_version":1,"schema_version":1,', 1))
        fixture.assert_rejected(self)

        fixture = self.fixture()
        self.assertEqual(fixture.run().returncode, 0)
        first = (fixture.output / "release-manifest.json").read_bytes()
        self.assertEqual(fixture.run().returncode, 0)
        self.assertEqual((fixture.output / "release-manifest.json").read_bytes(), first)
        changed = fixture.index()
        changed["support_revision"] = "c" * 40
        fixture.write_index(changed)
        fixture.assert_rejected(self)
        self.assertEqual((fixture.output / "release-manifest.json").read_bytes(), first)

        fixture = self.fixture()
        self.assertEqual(fixture.run().returncode, 0)
        manifest = json.loads((fixture.output / "release-manifest.json").read_text())
        ref = manifest["source_archive"]["path"]
        (fixture.output / ref).write_bytes(b"tampered")
        fixture.assert_rejected(self)

    def test_product_report_requires_all_real_scenarios_and_clean_source(self):
        for mutate in (
            lambda report: report["results"].update(worker_recreation="failed"),
            lambda report: report["private_install"]["results"].update(revoked="skipped"),
            lambda report: report.update(source_snapshot={"dirty": True}),
            lambda report: report["images"].update(app="sha256:" + "f" * 64),
        ):
            fixture = self.fixture()
            index = fixture.index()
            ref = index["checks"]["product"]["producer_report"]
            path = fixture.evidence / ref["path"]
            report = json.loads(path.read_text())
            mutate(report)
            body = json_bytes(report)
            path.write_bytes(body)
            ref["sha256"] = digest(body)
            fixture.write_index(index)
            fixture.assert_rejected(self)

    def test_stable_sbom_and_provenance_bind_the_same_candidate_artifacts(self):
        for document in ("sbom", "provenance"):
            fixture = self.fixture("stable")
            index = fixture.index()
            if document == "provenance":
                index[document]["source_sha"] = "c" * 40
            else:
                path = fixture.evidence / index[document]["documents"]["worker"]["path"]
                payload = json.loads(path.read_text())
                payload["packages"] = []
                body = json_bytes(payload)
                path.write_bytes(body)
                index[document]["documents"]["worker"]["sha256"] = digest(body)
            fixture.write_index(index)
            fixture.assert_rejected(self)

    def test_oci_labels_must_bind_repository_candidate_version_and_license(self):
        for label, value in (("org.opencontainers.image.source", "https://example.invalid/other"),
                             ("org.opencontainers.image.revision", "c" * 40),
                             ("org.opencontainers.image.version", "1.2.4"),
                             ("org.opencontainers.image.licenses", "GPL-3.0")):
            fixture = self.fixture(image_label_override={label: value})
            fixture.assert_rejected(self)

    def test_simulated_registry_response_must_preserve_configs_and_layers(self):
        fixture = self.fixture()
        result = fixture.run()
        self.assertEqual(result.returncode, 0, result.stderr)
        spec = importlib.util.spec_from_file_location("prepare_release", ASSEMBLER)
        module = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(module)
        manifest = json.loads((fixture.output / "release-manifest.json").read_text())
        def registry_body(role, layers=None, config=None):
            return json_bytes({"schemaVersion": 2, "mediaType": "application/vnd.oci.image.manifest.v1+json",
                               "annotations": {"org.packio.test.role": role},
                               "config": {"digest": config or manifest["images"][role]["config_digest"]},
                               "layers": [{"digest": digest_value} for digest_value in
                                          (layers or manifest["images"][role]["layer_digests"])]})

        def registry_entry(body):
            return {"registry_digest": "sha256:" + digest(body), "manifest_bytes": body}

        response = {role: registry_entry(registry_body(role)) for role in ROLES}
        self.assertTrue(module.validate_promotion(manifest, response))
        changed = {**response, "worker": registry_entry(registry_body("worker", ["sha256:" + "8" * 64]))}
        with self.assertRaises(ValueError):
            module.validate_promotion(manifest, changed)
        bad_digest = {**response, "app": {**response["app"], "registry_digest": "sha256:" + "f" * 64}}
        with self.assertRaises(ValueError):
            module.validate_promotion(manifest, bad_digest)
        partial = json.loads(json.dumps(manifest))
        partial["images"]["app"]["registry_digest"] = response["app"]["registry_digest"]
        with self.assertRaises(ValueError):
            module.validate_promotion(partial, response)
        promoted = json.loads(json.dumps(manifest))
        for role in ROLES:
            promoted["images"][role]["registry_digest"] = response[role]["registry_digest"]
        self.assertTrue(module.validate_promotion(promoted, response))
        changed_retry = {**response, "app": registry_entry(registry_body("app", ["sha256:" + "8" * 64]))}
        with self.assertRaises(ValueError):
            module.validate_promotion(promoted, changed_retry)


if __name__ == "__main__":
    unittest.main(verbosity=2)
