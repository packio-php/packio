#!/usr/bin/env python3
"""Regression coverage for the release image harness CLI and orchestration."""

import importlib.util
import hashlib
import io
import json
from pathlib import Path
import sys
import tarfile
import tempfile
import unittest
from unittest import mock


HARNESS_PATH = Path(__file__).with_name("image.test.py")
sys.dont_write_bytecode = True
SPEC = importlib.util.spec_from_file_location("release_image_harness", HARNESS_PATH)
image = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(image)


class FakeDocker:
    """Fake Docker's subprocess boundary while leaving harness orchestration real."""

    def __init__(self, metadata=None, canary=False, provenance_sha="0123456789abcdef0123456789abcdef01234567",
                 include_attestation=True, corrupt_provenance=False, provenance_target=None,
                 statement_subject_mismatch=False, manifest_subject_mismatch=False, fail_boot=False,
                 ssr_tree_overrides=None, script_mismatch=False, layer_path="app/clean.txt"):
        self.calls = []
        self.metadata = metadata or {}
        self.canary = canary
        self.layer_path = layer_path
        self.provenance_sha = provenance_sha
        self.include_attestation = include_attestation
        self.corrupt_provenance = corrupt_provenance
        self.provenance_target = provenance_target
        self.statement_subject_mismatch = statement_subject_mismatch
        self.manifest_subject_mismatch = manifest_subject_mismatch
        self.fail_boot = fail_boot
        self.recreated = False
        default_tree = {"data/ssr/ssr.mjs": b"render-module",
                        "data/ssr/assets/locale.json": b"{\"locale\":\"en\"}"}
        self.ssr_trees = {name: dict(default_tree) for name in ("app", "worker", "ssr")}
        for name, tree in (ssr_tree_overrides or {}).items():
            self.ssr_trees[name] = tree
        self.ssr_scripts = {
            "entrypoint-ssr.sh": b"#!/bin/sh\nexec node /app/data/ssr/ssr.mjs\n",
            "healthcheck-ssr.sh": b"#!/bin/sh\nnode /app/data/ssr/healthcheck.mjs\n",
        }
        self.worker_scripts = dict(self.ssr_scripts)
        if script_mismatch:
            self.ssr_scripts["entrypoint-ssr.sh"] = b"#!/bin/sh\nexec node /different.mjs\n"
        self.archives = {}
        self.images = {}
        for name in ("app", "worker", "ssr"):
            details, archive = self.image_artifact(name)
            for reference in (f"{name}:mutable", f"{name}-id", details["Id"]):
                self.images[reference] = details
                self.archives[reference] = archive

    def image_artifact(self, name):
        target = {"app": "prod", "worker": "prod-worker", "ssr": "prod-ssr"}[name]
        blobs = {}

        def add_blob(value, media_type):
            encoded = json.dumps(value, sort_keys=True, separators=(",", ":")).encode()
            digest = "sha256:" + hashlib.sha256(encoded).hexdigest()
            blobs[f"blobs/sha256/{digest.split(':', 1)[1]}"] = encoded
            return {"mediaType": media_type, "digest": digest, "size": len(encoded)}

        runtime_manifest = {"schemaVersion": 2, "mediaType": "application/vnd.oci.image.manifest.v1+json",
                            "config": {"mediaType": "application/vnd.oci.image.config.v1+json",
                                       "digest": "sha256:" + "1" * 64, "size": 2}, "layers": []}
        runtime_descriptor = add_blob(runtime_manifest, runtime_manifest["mediaType"])
        runtime_digest = runtime_descriptor["digest"]
        statement = {"_type": "https://in-toto.io/Statement/v1",
                     "predicateType": "https://slsa.dev/provenance/v1",
                     "subject": [{"name": f"pkg:docker/packio@{target}",
                                  "digest": {"sha256": "0" * 64 if self.statement_subject_mismatch
                                             else runtime_digest.split(":", 1)[1]}}],
                     "predicate": {"buildDefinition": {
                         "resolvedDependencies": [{"uri": f"pkg:docker/base-{name}@1",
                                                   "digest": {"sha256": hashlib.sha256(name.encode()).hexdigest()}}],
                         "externalParameters": {"request": {"root": {"request": {"args": {
                             "target": self.provenance_target or target,
                             "vcs:revision": self.provenance_sha}}}}}}}}
        statement_bytes = json.dumps(statement, sort_keys=True, separators=(",", ":")).encode()
        statement_digest = "sha256:" + hashlib.sha256(statement_bytes).hexdigest()
        statement_descriptor = {"mediaType": "application/vnd.in-toto+json", "digest": statement_digest,
                                "size": len(statement_bytes),
                                "annotations": {"in-toto.io/predicate-type": "https://slsa.dev/provenance/v1"}}
        config_bytes = b"{}"
        config_digest = "sha256:" + hashlib.sha256(config_bytes).hexdigest()
        attestation_manifest = {"schemaVersion": 2,
                                "mediaType": "application/vnd.oci.image.manifest.v1+json",
                                "artifactType": "application/vnd.docker.attestation.manifest.v1+json",
                                "config": {"mediaType": "application/vnd.oci.empty.v1+json",
                                           "digest": config_digest, "size": len(config_bytes), "data": "e30="},
                                "layers": [statement_descriptor],
                                "subject": {"mediaType": runtime_manifest["mediaType"],
                                            "digest": "sha256:" + "0" * 64 if self.manifest_subject_mismatch
                                            else runtime_digest,
                                            "size": runtime_descriptor["size"]}}
        attestation_descriptor = None
        if self.include_attestation:
            attestation_descriptor = add_blob(attestation_manifest, attestation_manifest["mediaType"])
            attestation_descriptor["annotations"] = {
                "vnd.docker.reference.digest": runtime_digest,
                "vnd.docker.reference.type": "attestation-manifest",
            }
            blobs[f"blobs/sha256/{statement_digest.split(':', 1)[1]}"] = statement_bytes
            blobs[f"blobs/sha256/{config_digest.split(':', 1)[1]}"] = config_bytes
            if self.corrupt_provenance:
                blobs[f"blobs/sha256/{statement_digest.split(':', 1)[1]}"] += b"tampered"
        child_index = {"schemaVersion": 2, "mediaType": "application/vnd.oci.image.index.v1+json",
                       "manifests": [dict(runtime_descriptor, platform={"os": "linux", "architecture": "amd64"})]}
        if attestation_descriptor:
            child_index["manifests"].append(attestation_descriptor)
        child_descriptor = add_blob(child_index, child_index["mediaType"])
        details = {"Id": f"sha256:{name * 8}", "Descriptor": {"digest": child_descriptor["digest"]},
                   "Os": "linux", "Architecture": "amd64", "RootFS": {"Layers": [f"{name}-layer"]},
                   "Config": {"Labels": {"org.opencontainers.image.base.digest": f"sha256:base-{name}"}}}

        archive = io.BytesIO()
        with tarfile.open(fileobj=archive, mode="w") as exported:
            manifest = json.dumps([{"Layers": ["layer.tar"]}]).encode()
            self.add_tar_file(exported, "manifest.json", manifest)
            self.add_tar_file(exported, "index.json", json.dumps({"schemaVersion": 2,
                               "manifests": [child_descriptor]}).encode())
            self.add_tar_file(exported, "oci-layout", b'{"imageLayoutVersion":"1.0.0"}')
            layer = io.BytesIO()
            with tarfile.open(fileobj=layer, mode="w") as contents:
                payload = image.MARKER if self.canary else b"clean"
                self.add_tar_file(contents, self.layer_path, payload)
            self.add_tar_file(exported, "layer.tar", layer.getvalue())
            for path, data in blobs.items():
                self.add_tar_file(exported, path, data)
        return details, archive.getvalue()

    @staticmethod
    def add_tar_file(archive, name, data):
        entry = tarfile.TarInfo(name)
        entry.size = len(data)
        archive.addfile(entry, io.BytesIO(data))

    def __call__(self, command, **kwargs):
        self.calls.append(command)
        if command[:3] == ("docker", "image", "inspect"):
            reference = command[-1]
            if reference not in self.images:
                return mock.Mock(returncode=1, stdout=b"no such image\n")
            return mock.Mock(returncode=0, stdout=(json.dumps(self.images[reference]) + "\n").encode())
        if command[:2] == ("docker", "save"):
            archive = Path(command[command.index("-o") + 1])
            archive.write_bytes(self.archives.get(command[-1], self.archives["ssr-id"]))
            return mock.Mock(returncode=0, stdout=b"")
        if command[:2] == ("docker", "run"):
            image_ref = command[command.index("--entrypoint") + 2]
            if command[-1] == "/app/data/build/release.json":
                value = self.metadata.get(image_ref, {"version": "test", "sha": "0123456789ab", "dirty": False})
                if value is None:
                    return mock.Mock(returncode=1, stdout=b"missing release metadata\n")
                return mock.Mock(returncode=0, stdout=(json.dumps(value) + "\n").encode())
            if "-cf" in command and "-" in command:
                image_name = next(name for name in ("app", "worker", "ssr") if name * 8 in image_ref)
                if "data/ssr" in command:
                    members = self.ssr_trees[image_name]
                else:
                    scripts = self.ssr_scripts if image_name == "ssr" else self.worker_scripts
                    members = {f"usr/local/bin/{name}": content for name, content in scripts.items()}
                return mock.Mock(returncode=0, stdout=self.tar_bytes(members), stderr=b"")
            return mock.Mock(returncode=0, stdout=b"")
        if command[:2] == ("docker", "compose"):
            if "ps" in command:
                if "--all" in command:
                    suffix = "partial" if self.fail_boot else "current"
                    services = ("app", "postgres") if self.fail_boot else ("app", "workers", "ssr", "postgres")
                    return mock.Mock(returncode=0, stdout=("".join(f"{suffix}-{service}\n" for service in services)).encode())
                service = command[-1]
                return mock.Mock(returncode=0, stdout=(f"initial-{service}\n").encode())
            if self.fail_boot and "up" in command and "--wait" in command and "--force-recreate" not in command:
                return mock.Mock(returncode=1, stdout=b"partial startup failed\n")
            if "--force-recreate" in command:
                self.recreated = True
            return mock.Mock(returncode=0, stdout=b"")
        if command[:2] == ("docker", "inspect"):
            return mock.Mock(returncode=0, stdout=b"")
        if command[:2] in (("docker", "tag"), ("docker", "image")):
            return mock.Mock(returncode=0, stdout=b"")
        if command[:2] == ("docker", "rm"):
            return mock.Mock(returncode=0, stdout=b"")
        raise AssertionError(f"unexpected subprocess command: {command}")

    @staticmethod
    def tar_bytes(members):
        result = io.BytesIO()
        with tarfile.open(fileobj=result, mode="w") as archive:
            for name, content in members.items():
                entry = tarfile.TarInfo(name)
                if content is None:
                    entry.type = tarfile.DIRTYPE
                    archive.addfile(entry)
                else:
                    entry.size = len(content)
                    archive.addfile(entry, io.BytesIO(content))
        return result.getvalue()

class ImageHarnessTest(unittest.TestCase):
    def test_verify_only_requires_all_inputs_without_running_subprocesses(self):
        with mock.patch.object(image.subprocess, "run") as run:
            with self.assertRaises(SystemExit) as error:
                image.main(["--verify-only", "--app-image", "app"])
        self.assertNotEqual(error.exception.code, 0)
        run.assert_not_called()

    def test_r1_snapshot_flags_are_paired_before_running_subprocesses(self):
        with mock.patch.object(image.subprocess, "run") as run:
            with self.assertRaises(SystemExit) as error:
                image.main(["--verify-only", "--app-image", "app", "--worker-image", "worker",
                            "--ssr-image", "ssr", "--expected-sha", "0" * 40, "--report", "report.json",
                            "--source-identity", "identity.json"])
        self.assertNotEqual(error.exception.code, 0)
        run.assert_not_called()

    def test_r1_snapshot_mode_accepts_dirty_metadata_and_reports_explicit_source_binding(self):
        expected = "0123456789abcdef0123456789abcdef01234567"
        fake_docker = FakeDocker()
        ids = {role: fake_docker.images[f"{role}:mutable"]["Id"]
               for role in ("app", "worker", "ssr")}
        proof = {"kind": "disposable_local_R1_test_snapshot", "dirty": True,
                 "base_head": expected, "patch_sha256": "d" * 64,
                 "runtime_fingerprint": "e" * 64, "runtime_file_count": 4, "images": ids}
        fake_docker.metadata = {ids[role]: {"version": "test", "sha": expected[:12], "dirty": True}
                                for role in ("app", "worker")}
        with tempfile.TemporaryDirectory(prefix="image-harness-test-") as temporary:
            report = Path(temporary) / "report.json"
            with mock.patch.object(image, "validate_r1_snapshot", return_value=proof) as validate, \
                 mock.patch.object(image.subprocess, "run", side_effect=fake_docker):
                image.main(["--verify-only", "--app-image", "app:mutable", "--worker-image", "worker:mutable",
                            "--ssr-image", "ssr:mutable", "--expected-sha", expected, "--report", str(report),
                            "--source-identity", "identity.json", "--build-record", "build.json"])
            result = json.loads(report.read_text())
        validate.assert_called_once_with("identity.json", "build.json", expected,
                                         {role: fake_docker.images[f"{role}:mutable"]["Id"]
                                          for role in ("app", "worker", "ssr")})
        self.assertEqual(result["source_snapshot"], proof)
        self.assertTrue(result["source_snapshot"]["dirty"])

    def test_verify_only_resolves_ids_checks_layers_metadata_and_reports(self):
        expected = "0123456789abcdef0123456789abcdef01234567"
        fake_docker = FakeDocker()
        with tempfile.TemporaryDirectory(prefix="image-harness-test-") as temporary:
            report = Path(temporary) / "report.json"
            with mock.patch.object(image.subprocess, "run", side_effect=fake_docker):
                image.main(["--verify-only", "--app-image", "app:mutable", "--worker-image", "worker:mutable",
                            "--ssr-image", "ssr:mutable", "--expected-sha", expected, "--report", str(report)])
            result = json.loads(report.read_text())
        self.assertEqual(result["expected_sha"], expected)
        self.assertEqual(result["images"]["app"]["id"], "sha256:appappappappappappappapp")
        self.assertEqual(result["images"]["ssr"]["id"], "sha256:ssrssrssrssrssrssrssrssr")
        self.assertEqual(result["images"]["ssr"]["metadata_sha"], None)
        self.assertFalse(result["images"]["ssr"]["metadata_embeds_sha"])
        self.assertEqual(result["images"]["app"]["base_digests"],
                         ["sha256:" + hashlib.sha256(b"app").hexdigest()])
        self.assertEqual(result["source_sha"], expected)
        self.assertEqual(result["images"]["ssr"]["source_sha"], expected)
        self.assertEqual(result["ssr_parity"]["result"], "passed")
        self.assertEqual(result["ssr_parity"]["tree_digests"]["app"],
                         result["ssr_parity"]["tree_digests"]["ssr"])
        self.assertEqual(result["startup"]["result"], "passed")
        self.assertTrue(all(call[:2] not in (("docker", "build"), ("docker", "pull")) for call in fake_docker.calls))
        self.assertTrue(all(call[:2] == ("docker", "run") and "--pull=never" in call
                            for call in fake_docker.calls if call[:2] == ("docker", "run")))

    def test_missing_mismatched_and_dirty_metadata_fail_before_layer_export(self):
        expected = "0123456789abcdef0123456789abcdef01234567"
        cases = (
            {"sha256:appappappappappappappapp": None},
            {"sha256:appappappappappappappapp": {"version": "test", "sha": "abcdef0", "dirty": False}},
            {"sha256:appappappappappappappapp": {"version": "test", "sha": expected, "dirty": True}},
            {"sha256:appappappappappappappapp": {"version": "test", "dirty": False}},
        )
        for metadata in cases:
            with self.subTest(metadata=metadata), tempfile.TemporaryDirectory(prefix="image-harness-test-") as temporary:
                fake_docker = FakeDocker(metadata=metadata)
                report = Path(temporary) / "report.json"
                with mock.patch.object(image.subprocess, "run", side_effect=fake_docker):
                    with self.assertRaises((AssertionError, RuntimeError)):
                        image.verify_only("app:mutable", "worker:mutable", "ssr:mutable", expected, report)
                self.assertFalse(any(call[:2] == ("docker", "save") for call in fake_docker.calls))
                self.assertFalse(report.exists())

    def test_missing_docker_fails_and_removes_stale_report(self):
        with tempfile.TemporaryDirectory(prefix="image-harness-test-") as temporary:
            report = Path(temporary) / "report.json"
            report.write_text('{"result":"passed"}\n')
            with mock.patch.object(image.subprocess, "run", side_effect=FileNotFoundError("docker")):
                with self.assertRaises(FileNotFoundError):
                    image.main(["--verify-only", "--app-image", "app", "--worker-image", "worker",
                                "--ssr-image", "ssr", "--expected-sha", "0123456789abcdef0123456789abcdef01234567",
                                "--report", str(report)])
            self.assertFalse(report.exists())

    def test_unique_run_resources_and_cleanup_are_scoped_to_owned_aliases(self):
        first = image.run_names()
        second = image.run_names()
        self.assertNotEqual(first["suffix"], second["suffix"])
        self.assertNotEqual(first["project"], second["project"])
        self.assertNotEqual(first["tag"], second["tag"])
        with tempfile.TemporaryDirectory(prefix="image-harness-test-") as temporary:
            fake_docker = FakeDocker()
            context = Path(temporary)
            (context / ".env.prod.local").write_text("test\n")
            with mock.patch.object(image.subprocess, "run", side_effect=fake_docker):
                image.check_boot(context, {"prod": "app-id", "prod-worker": "worker-id", "prod-ssr": "ssr-id"})
            added = {call[-1] for call in fake_docker.calls if call[:2] == ("docker", "tag")}
            removed = {call[-1] for call in fake_docker.calls if call[:3] == ("docker", "image", "rm")}
            self.assertEqual(added, removed)
            self.assertEqual(len(added), 3)
            removed_ids = {value for call in fake_docker.calls if call[:2] == ("docker", "rm") for value in call[3:]}
            self.assertEqual(removed_ids, {"current-app", "current-workers", "current-ssr", "current-postgres"})
            self.assertFalse(any(value.startswith("initial-") for value in removed_ids))
            self.assertTrue(fake_docker.recreated)
            self.assertTrue(any(call[:2] == ("docker", "compose") and "down" in call
                                for call in fake_docker.calls))

    def test_partial_boot_cleanup_removes_only_containers_in_own_compose_project(self):
        with tempfile.TemporaryDirectory(prefix="image-harness-test-") as temporary:
            fake_docker = FakeDocker(fail_boot=True)
            context = Path(temporary)
            (context / ".env.prod.local").write_text("test\n")
            with mock.patch.object(image.subprocess, "run", side_effect=fake_docker):
                with self.assertRaises(RuntimeError):
                    image.check_boot(context, {"prod": "app-id", "prod-worker": "worker-id", "prod-ssr": "ssr-id"})
            removed_ids = {value for call in fake_docker.calls if call[:2] == ("docker", "rm") for value in call[3:]}
            self.assertEqual(removed_ids, {"partial-app", "partial-postgres"})
            self.assertTrue(any(call[:2] == ("docker", "compose") and "down" in call
                                for call in fake_docker.calls))

    def test_dirty_matching_metadata_anchors_fail_before_ssr_parity(self):
        dirty = {"version": "test", "sha": "0123456789ab", "dirty": True}
        fake_docker = FakeDocker(
            metadata={"sha256:appappappappappappappapp": dirty,
                      "sha256:workerworkerworkerworkerworkerworkerworkerworker": dirty},
            ssr_tree_overrides={name: {"data/ssr/ssr.mjs": b"same-dirty-output"}
                                for name in ("app", "worker", "ssr")},
        )
        with tempfile.TemporaryDirectory(prefix="image-harness-test-") as temporary:
            report = Path(temporary) / "report.json"
            with mock.patch.object(image.subprocess, "run", side_effect=fake_docker):
                with self.assertRaises(AssertionError):
                    image.verify_only("app:mutable", "worker:mutable", "ssr:mutable",
                                      "0123456789abcdef0123456789abcdef01234567", report)
            self.assertFalse(any(call[:2] == ("docker", "run") and "-cf" in call
                                 for call in fake_docker.calls))
            self.assertFalse(report.exists())

    def test_ssr_tree_paths_content_and_runtime_scripts_must_match_clean_anchors(self):
        baseline = {"data/ssr/ssr.mjs": b"render-module",
                    "data/ssr/assets/locale.json": b"{\"locale\":\"en\"}"}
        cases = (
            {"ssr_tree_overrides": {"app": {**baseline, "data/ssr/ssr.mjs": b"different"}}},
            {"ssr_tree_overrides": {"ssr": {**baseline, "data/ssr/ssr.mjs": b"dirty-SSR"}}},
            {"ssr_tree_overrides": {"worker": {"data/ssr/assets/locale.json": b"{\"locale\":\"en\"}"}}},
            {"ssr_tree_overrides": {"ssr": {**baseline, "data/ssr/extra.mjs": b"extra"}}},
            {"script_mismatch": True},
        )
        for options in cases:
            with self.subTest(options=options), tempfile.TemporaryDirectory(prefix="image-harness-test-") as temporary:
                fake_docker = FakeDocker(**options)
                report = Path(temporary) / "report.json"
                with mock.patch.object(image.subprocess, "run", side_effect=fake_docker):
                    with self.assertRaises(AssertionError):
                        image.main(["--verify-only", "--app-image", "app:mutable", "--worker-image", "worker:mutable",
                                    "--ssr-image", "ssr:mutable", "--expected-sha",
                                    "0123456789abcdef0123456789abcdef01234567", "--report", str(report)])
                self.assertFalse(report.exists())

    def test_layer_checker_rejects_canary_in_synthetic_tar(self):
        with tempfile.TemporaryDirectory(prefix="image-harness-test-") as temporary:
            fake_docker = FakeDocker(canary=True)
            with mock.patch.object(image.subprocess, "run", side_effect=fake_docker):
                with self.assertRaises(AssertionError):
                    image.check_layers("synthetic-id", Path(temporary) / "canary.tar", "prod")

    def test_layer_checker_applies_per_target_forbidden_paths_and_size_ceiling(self):
        cases = (
            ("usr/local/bin/php-cgi", "prod", True),
            ("app/data/ssr/ssr.mjs", "prod-worker", True),
            ("app/data/ssr/ssr.mjs", "prod-ssr", False),
            ("opt/yarn-v1.22.22/bin/yarn", "prod-ssr", True),
            ("opt/yarn-v1.22.22/bin/yarn", "prod", False),
        )
        for path, target, rejected in cases:
            with self.subTest(path=path, target=target), tempfile.TemporaryDirectory(prefix="image-harness-test-") as temporary:
                with mock.patch.object(image.subprocess, "run", side_effect=FakeDocker(layer_path=path)):
                    if rejected:
                        with self.assertRaisesRegex(AssertionError, "forbidden path"):
                            image.check_layers("synthetic-id", Path(temporary) / "a.tar", target)
                    else:
                        image.check_layers("synthetic-id", Path(temporary) / "a.tar", target)
        with tempfile.TemporaryDirectory(prefix="image-harness-test-") as temporary:
            with mock.patch.object(image.subprocess, "run", side_effect=FakeDocker()), \
                    mock.patch.dict(image.MAX_LAYER_BYTES, {"prod": 1}):
                with self.assertRaisesRegex(AssertionError, "exceeds ceiling 1"):
                    image.check_layers("synthetic-id", Path(temporary) / "a.tar", "prod")

    def test_provenance_is_required_and_digest_sha_and_target_must_match(self):
        expected = "0123456789abcdef0123456789abcdef01234567"
        cases = (
            {"include_attestation": False},
            {"provenance_sha": "abcdef0123456789abcdef0123456789abcdef01"},
            {"corrupt_provenance": True},
            {"provenance_target": "wrong-target"},
            {"statement_subject_mismatch": True},
            {"manifest_subject_mismatch": True},
        )
        for options in cases:
            with self.subTest(options=options), tempfile.TemporaryDirectory(prefix="image-harness-test-") as temporary:
                fake_docker = FakeDocker(**options)
                report = Path(temporary) / "report.json"
                with mock.patch.object(image.subprocess, "run", side_effect=fake_docker):
                    with self.assertRaises((AssertionError, RuntimeError, ValueError)):
                        image.main(["--verify-only", "--app-image", "app:mutable", "--worker-image", "worker:mutable",
                                    "--ssr-image", "ssr:mutable", "--expected-sha", expected,
                                    "--report", str(report)])
                self.assertFalse(report.exists())


if __name__ == "__main__":
    unittest.main()
