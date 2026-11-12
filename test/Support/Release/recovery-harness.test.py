#!/usr/bin/env python3
"""Regression tests for recovery rehearsal argument and evidence contracts."""

import importlib.util
import hashlib
import io
import http.server
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import time
import threading
import unittest
from unittest import mock
from datetime import datetime, timezone
from contextlib import redirect_stderr


ROOT = Path(__file__).resolve().parents[3]
RELEASE_DIR = ROOT / "test" / "Support" / "Release"
sys.dont_write_bytecode = True
sys.path.insert(0, str(RELEASE_DIR))
import product_fixture

SPEC = importlib.util.spec_from_file_location("release_recovery_harness", RELEASE_DIR / "recovery.test.py")
recovery = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(recovery)


BASE = "c" * 40

class RecoveryHarnessTest(unittest.TestCase):
    def test_restored_mail_http_read_has_absolute_deadline_and_accepts_normal_response(self):
        payload = {"messages": [{"To": [{"Address": "deadline@example.invalid"}]}]}
        body = json.dumps(payload).encode()
        for slow in (False, True):
            class Handler(http.server.BaseHTTPRequestHandler):
                def log_message(self, *_args):
                    pass

                def do_GET(self):
                    self.send_response(200)
                    self.send_header("Content-Length", str(len(body)))
                    self.end_headers()
                    try:
                        for chunk in (bytes((value,)) for value in body) if slow else (body,):
                            self.wfile.write(chunk)
                            self.wfile.flush()
                            if slow:
                                time.sleep(0.015)
                    except (BrokenPipeError, ConnectionResetError):
                        pass

            with http.server.ThreadingHTTPServer(("127.0.0.1", 0), Handler) as server:
                thread = threading.Thread(target=server.serve_forever, kwargs={"poll_interval": 0.01}, daemon=True)
                thread.start()
                context = {"mailpit_url": f"http://127.0.0.1:{server.server_port}",
                           "_run": product_fixture.Run(timeout=0.15 if slow else 2)}
                started = time.monotonic()
                try:
                    if slow:
                        with self.assertRaisesRegex(product_fixture.FixtureError, "timed out"):
                            recovery._restored_mail_messages(context, "deadline@example.invalid")
                        self.assertLess(time.monotonic() - started, 0.6)
                    else:
                        self.assertEqual(recovery._restored_mail_messages(context, "deadline@example.invalid"), payload)
                finally:
                    server.shutdown()
                    thread.join(timeout=1)

    def test_cleanup_rejects_every_resource_outside_owned_uuid_names(self):
        with mock.patch.object(recovery.subprocess, "run") as command:
            for kind, name in (("image", "retained-image:local"), ("image", "sha256:" + "a" * 64),
                               ("volume", "packio_postgres-test-data"), ("container", "foreign"),
                               ("volume", ""), ("network", "packio-o3-foreign-sentinel-" + "a" * 32)):
                self.assertFalse(recovery._remove_owned_resource(kind, name))
            command.assert_not_called()

    def test_fixture_cleanup_failure_cannot_be_reported_as_success(self):
        with tempfile.TemporaryDirectory() as temp:
            path = Path(temp) / "failure.json"
            args = ["--baseline-ref", "v0.1.0", "--app-image", "app", "--worker-image", "worker",
                    "--ssr-image", "ssr", "--expected-sha", "a" * 40, "--report", str(path)]
            with mock.patch.object(recovery, "build_baseline_images", return_value=({}, {}, None)):
                with mock.patch.object(recovery, "_baseline_fixture_ids",
                                       side_effect=product_fixture.FixtureCleanupError("owned cleanup failed")):
                    with redirect_stderr(io.StringIO()):
                        self.assertEqual(recovery.main(args), 1)
            self.assertEqual(json.loads(path.read_text())["results"]["cleanup"], "failed")
            context = {"project": "packio-e1-" + "a" * 32, "private_dir": str(Path(temp) / "private"),
                       "_resources_started": False}
            with mock.patch.object(product_fixture.shutil, "rmtree", side_effect=PermissionError):
                with self.assertRaises(product_fixture.FixtureCleanupError):
                    product_fixture.stop_fixture(context)
            self.assertIsNotNone(context["private_dir"])

    def test_cleanup_failure_or_stop_keeps_the_failure_artifact_and_signal_handlers(self):
        import signal

        for failure in ("filesystem", "stop"):
            with tempfile.TemporaryDirectory() as temp:
                path = Path(temp) / "failure.json"
                workspace = Path(temp) / "workspace"
                workspace.mkdir()

                class Workspace:
                    name = str(workspace)

                    def cleanup(self):
                        if failure == "filesystem":
                            raise PermissionError("controlled cleanup failure")
                        os.kill(os.getpid(), signal.SIGTERM)
                        workspace.rmdir()

                args = ["--baseline-ref", "v0.1.0", "--app-image", "app", "--worker-image", "worker",
                        "--ssr-image", "ssr", "--expected-sha", "a" * 40, "--report", str(path)]
                handlers = {sig: signal.getsignal(sig) for sig in (signal.SIGTERM, signal.SIGINT)}
                with mock.patch.object(recovery.tempfile, "TemporaryDirectory", return_value=Workspace()):
                    with mock.patch.object(recovery, "build_baseline_images",
                                           side_effect=product_fixture.FixtureError("controlled original failure")):
                        stderr = io.StringIO()
                        with redirect_stderr(stderr):
                            self.assertEqual(recovery.main(args), 1)
                saved = json.loads(path.read_text())
                self.assertEqual(saved["results"]["cleanup"], "failed" if failure == "filesystem" else "passed")
                self.assertIn("controlled original failure", stderr.getvalue())
                self.assertEqual({sig: signal.getsignal(sig) for sig in handlers}, handlers)

    def test_wrong_key_control_is_distinct_and_survives_native_env_string_parsing(self):
        for original in ("a" * 64, "b" * 64, "c" * 64):
            wrong = recovery._wrong_fixture_key(original)
            self.assertNotEqual(wrong, original)
            self.assertRegex(wrong, r"^[0-9a-f]{64}$")
            self.assertFalse(wrong.isdecimal(), "native env() converts numeric keys to integers")

    def test_failure_report_is_safe_and_report_error_preserves_original_failure(self):
        with tempfile.TemporaryDirectory() as temp:
            report_path = Path(temp) / "failure.json"
            args = ["--baseline-ref", "v0.1.0", "--app-image", "sha256:" + "1" * 64,
                    "--worker-image", "sha256:" + "2" * 64, "--ssr-image", "sha256:" + "3" * 64,
                    "--expected-sha", "a" * 40, "--report", str(report_path)]
            with mock.patch.object(recovery, "build_baseline_images",
                                   side_effect=product_fixture.FixtureError("controlled original failure")):
                stderr = io.StringIO()
                with redirect_stderr(stderr):
                    self.assertEqual(recovery.main(args), 1)
                saved = json.loads(report_path.read_text())
                self.assertEqual(saved["results"]["baseline_product"], "failed")
                self.assertEqual(saved["results"]["cleanup"], "passed")
                self.assertEqual(saved["images"], {"baseline": {}, "candidate": {}})
                self.assertIn("controlled original failure", stderr.getvalue())
                stderr = io.StringIO()
                with mock.patch.object(recovery, "_write_report",
                                       side_effect=product_fixture.FixtureError("controlled report error")):
                    with redirect_stderr(stderr):
                        with self.assertRaisesRegex(product_fixture.FixtureError, "controlled report error"):
                            recovery.main(args)
                self.assertIn("controlled original failure", stderr.getvalue())

    def test_empty_database_waits_for_connection_but_never_accepts_existing_schema(self):
        def exercise(results):
            run = product_fixture.Run(timeout=2)
            responses = iter(results)

            def query(args, *, check=True, **_kwargs):
                status, output = next(responses)
                if check and status:
                    raise product_fixture.FixtureError("query failed")
                return subprocess.CompletedProcess(args, status, output)

            with mock.patch.object(run, "command", side_effect=query) as commands:
                product_fixture._assert_empty_database({"project": "owned-control"}, run, {})
                return commands.call_count

        unavailable = ('psql: error: connection to server at "127.0.0.1", port 5432 failed: '
                       'Connection refused\nIs the server running?\n')
        self.assertEqual(exercise(((2, unavailable), (0, "0\n"))), 2)
        for status, output in ((0, "1\n"), (125, "Docker failed"), (1, "SQL failed"),
                               (2, "Docker connection failed"),
                               (2, 'psql: error: connection to server failed: password authentication failed')):
            with self.assertRaises(product_fixture.FixtureError):
                exercise(((status, output),))
        with self.assertRaisesRegex(product_fixture.FixtureError, "not empty"):
            exercise(((2, unavailable), (0, "1\n")))
        run = product_fixture.Run(timeout=0.02)
        deadline = run.deadline
        with mock.patch.object(run, "command", return_value=subprocess.CompletedProcess([], 2, unavailable)):
            with self.assertRaisesRegex(product_fixture.FixtureError, "timed out"):
                product_fixture._assert_empty_database({"project": "owned-control"}, run, {})
        self.assertEqual(run.deadline, deadline)

    def test_failed_migration_requires_native_execution_not_docker_or_mount_failure(self):
        markers = ("Running database migrations (attempt 1/1)...\n"
                   "Database migrations failed after 1 attempts.")
        for status, output, accepted in ((1, markers, True), (125, markers, False),
                                         (1, "container mount failed", False), (0, markers, False),
                                         (1, markers + "\nDatabase migrations completed.", False)):
            result = subprocess.run(("sh", "-c", 'printf "%s\\n" "$1"; exit "$2"',
                                     "control", output, str(status)), capture_output=True, text=True)
            self.assertEqual(recovery._is_expected_migration_failure(result), accepted)

    def test_restore_stages_verified_archive_inside_target_private_directory(self):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            backup, private = root / "backup", root / "private"
            backup.mkdir()
            private.mkdir(mode=0o700)
            original = backup / "stored-package.zip"
            original.write_bytes(b"independent stored archive fixture")
            digest = hashlib.sha256(original.read_bytes()).hexdigest()
            context = {"private_dir": str(private)}
            staged = recovery._copy_restored_archive(context, backup, digest)
            dataset = {"user_id": "11111111-1111-1111-1111-111111111111",
                       "organization_id": "22222222-2222-2222-2222-222222222222",
                       "source_id": "33333333-3333-3333-3333-333333333333",
                       "package_name": "packio-fixture/private-package", "package_version": "1.0.0",
                       "registry_host": "owned.registry.fixture.test:443",
                       "token_path": str(private / "future-token.json"), "archive_sha256": digest}
            self.assertEqual(product_fixture.validate_dataset(context, dataset), dataset)
            self.assertEqual(staged.read_bytes(), original.read_bytes())
            self.assertEqual(staged.stat().st_mode & 0o777, 0o600)
            with self.assertRaises(product_fixture.FixtureError):
                recovery._copy_restored_archive({"private_dir": str(private)}, backup, "0" * 64)

    def test_sigterm_during_build_preserves_failure_report_and_cleanup(self):
        with tempfile.TemporaryDirectory() as temp:
            report = Path(temp) / "failure.json"
            child = "\n".join((
                "import importlib.util, os, signal, sys",
                f"sys.path.insert(0, {str(RELEASE_DIR)!r})",
                f"spec=importlib.util.spec_from_file_location('recovery', {str(RELEASE_DIR / 'recovery.test.py')!r})",
                "module=importlib.util.module_from_spec(spec); spec.loader.exec_module(module)",
                "def interrupted_build(*args): os.kill(os.getpid(), signal.SIGTERM)",
                "module.build_baseline_images=interrupted_build",
                f"raise SystemExit(module.main(['--baseline-ref','v0.1.0','--app-image','app','--worker-image','worker','--ssr-image','ssr','--expected-sha',{'a' * 40!r},'--report',{str(report)!r}]))",
            ))
            result = subprocess.run((sys.executable, "-c", child), capture_output=True, timeout=10,
                                    env={**os.environ, "PYTHONDONTWRITEBYTECODE": "1"})
            self.assertEqual(result.returncode, 1, "SIGTERM must enter the failure cleanup path")
            saved = json.loads(report.read_text())
            self.assertEqual(saved["results"]["cleanup"], "passed")
            self.assertEqual(saved["results"]["baseline_product"], "failed")

    def test_baseline_fixture_inputs_use_exact_image_ids_not_tags(self):
        ids = {role: "sha256:" + value * 64 for role, value in zip("app worker ssr".split(), "abc")}
        self.assertEqual(recovery._baseline_fixture_ids(ids), ids)
        with self.assertRaises(product_fixture.FixtureError):
            recovery._baseline_fixture_ids({**ids, "app": "packio-o3-baseline:local"})
        with self.assertRaises(product_fixture.FixtureError):
            recovery._baseline_fixture_ids({"app": ids["app"], "worker": ids["worker"]})

    def test_cleanup_command_reports_nonzero_exit(self):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            docker = root / "docker"
            docker.write_text("#!/bin/sh\nexit 17\n")
            docker.chmod(0o700)
            env = {"PATH": temp}
            self.assertFalse(recovery._run_cleanup_command(("docker", "volume", "rm", "owned"), env=env))
            docker.write_text("#!/bin/sh\nexit 0\n")
            docker.chmod(0o700)
            self.assertTrue(recovery._run_cleanup_command(("docker", "volume", "rm", "owned"), env=env))

    def test_restore_registry_host_uses_new_fixture_port_and_keeps_org_slug(self):
        dataset = {"registry_host": "fixture-org.registry.fixture.test:43123", "other": "unchanged"}
        context = {"registry_base_domain": "registry.fixture.test:49876"}
        restored = recovery._rebind_registry_host(dataset, context)
        self.assertEqual(restored["registry_host"], "fixture-org.registry.fixture.test:49876")
        self.assertEqual(restored["other"], "unchanged")
        with self.assertRaises(product_fixture.FixtureError):
            recovery._rebind_registry_host({**dataset, "registry_host": "outside.example:443"}, context)

    def test_cli_requires_exact_recovery_inputs(self):
        args = recovery.parser().parse_args([
            "--baseline-ref", "v0.1.0", "--app-image", "candidate-app",
            "--worker-image", "candidate-worker", "--ssr-image", "candidate-ssr",
            "--expected-sha", "a" * 40, "--report", "report.json",
        ])
        self.assertEqual(args.baseline_ref, "v0.1.0")
        self.assertEqual(args.app_image, "candidate-app")
        self.assertEqual(args.expected_sha, "a" * 40)

    def test_recovery_fixture_deadline_is_bounded_and_default_stays_unchanged(self):
        started = time.monotonic()
        run = product_fixture._new_fixture_run(timeout=41)
        self.assertGreaterEqual(run.deadline - started, 40)
        self.assertLessEqual(run.deadline - started, 41.05)
        with self.assertRaises(product_fixture.FixtureError):
            product_fixture._new_fixture_run(timeout=0)

    def test_backup_manifest_rejects_missing_or_unexpected_artifacts(self):
        manifest = {
            "format": 1,
            "source_sha": "a" * 40,
            "database_sha256": "b" * 64,
            "archives_sha256": "c" * 64,
            "key_sha256": "d" * 64,
            "config_sha256": "e" * 64,
        }
        self.assertTrue(recovery.validate_backup_manifest(manifest))
        with self.assertRaises(recovery.FixtureError):
            recovery.validate_backup_manifest({**manifest, "key": "secret"})
        with self.assertRaises(recovery.FixtureError):
            recovery.validate_backup_manifest({key: value for key, value in manifest.items()
                                               if key != "archives_sha256"})

    def test_empty_path_check_allows_only_known_empty_baseline_markers(self):
        markers = (
            "data/cache/.gitkeep", "data/cache/cycle/.gitkeep",
            "data/cache/proxies/.gitkeep", "data/cache/twig/.gitkeep",
            "data/rate-limits/.gitkeep", "data/sessions/.gitkeep",
        )

        def check(root, include_archives=True):
            script = product_fixture._empty_paths_check(include_archives=include_archives)
            script = script.replace("/app", root)
            return subprocess.run(("sh", "-ec", script), capture_output=True, check=False)

        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            self.assertEqual(check(temp).returncode, 0, "missing directories are empty")
            for marker in markers:
                path = root / marker
                path.parent.mkdir(parents=True, exist_ok=True)
                self.assertEqual(check(temp).returncode, 0, "empty directories are allowed")
                path.touch()
                self.assertNotEqual(check(temp).returncode, 0, "no placeholder file is tolerated: " + marker)
                path.unlink()

            archive_marker = root / "data/package-archives/.gitkeep"
            archive_marker.parent.mkdir(parents=True, exist_ok=True)
            archive_marker.touch()
            self.assertNotEqual(check(temp).returncode, 0)
            self.assertEqual(check(temp, include_archives=False).returncode, 0)
            archive_marker.unlink()

            link = root / markers[0]
            link.symlink_to("missing-target")
            self.assertNotEqual(check(temp).returncode, 0)

    def test_backup_set_rejects_missing_or_mismatched_key_and_archives(self):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            parts = {
                "database": b"custom pg dump",
                "archives": b"private package archive tar",
                "key": b"a" * 64,
                "config": b"protected config",
            }
            paths = {name: root / f"{name}.bin" for name in parts}
            for name, payload in parts.items():
                paths[name].write_bytes(payload)
            manifest = {
                "format": 1,
                "source_sha": "a" * 40,
                "database_sha256": hashlib.sha256(parts["database"]).hexdigest(),
                "archives_sha256": hashlib.sha256(parts["archives"]).hexdigest(),
                "key_sha256": hashlib.sha256(parts["key"]).hexdigest(),
                "config_sha256": hashlib.sha256(parts["config"]).hexdigest(),
            }
            self.assertTrue(recovery.validate_backup_set(manifest, paths))
            paths["key"].write_bytes(b"b" * 64)
            with self.assertRaises(recovery.FixtureError):
                recovery.validate_backup_set(manifest, paths)
            paths["key"].write_bytes(parts["key"])
            paths["archives"].unlink()
            with self.assertRaises(recovery.FixtureError):
                recovery.validate_backup_set(manifest, paths)

    def test_public_report_requires_safe_image_identity_and_utc_time(self):
        image_id = "sha256:" + "f" * 64
        report = {
            "schema": 1,
            "baseline_ref": "v0.1.0",
            "baseline_sha": BASE,
            "candidate_sha": "a" * 40,
            "image_refs": {"baseline": {"app": "packio-o3-baseline:local"},
                           "candidate": {"app": "packio-candidate:local"}},
            "images": {"baseline": {"app": image_id}, "candidate": {"app": image_id}},
            "hashes": {"database_sha256": "b" * 64},
            "postgres_version": "17.6",
            "started_at": datetime.now(timezone.utc).isoformat(),
            "results": {"backup": "passed"},
            "duration_seconds": 1.0,
            "provenance": None,
        }
        roles = {role: "sha256:" + value * 64 for role, value in
                 (("app", "1"), ("worker", "2"), ("ssr", "3"))}
        context = {"resolved_images": roles,
                   "ancillary_images": {"caddy": image_id, "composer": image_id,
                                        "mailpit": image_id, "postgres": image_id}}
        self.assertEqual(len(product_fixture.report_image_ids(context)), 7)
        self.assertEqual(recovery._candidate_report_image_ids(context), roles)
        report["images"]["candidate"] = recovery._candidate_report_image_ids(context)
        report["results"]["candidate_readiness"] = "passed"
        with tempfile.TemporaryDirectory() as temp:
            path = Path(temp) / "report.json"
            recovery._write_report(path, report)
            saved = json.loads(path.read_text())
            self.assertEqual(saved["image_refs"]["baseline"]["app"], "packio-o3-baseline:local")
            self.assertNotIn("key_sha256", json.dumps(saved))
            with self.assertRaises(recovery.FixtureError):
                recovery._write_report(path, {**report, "image_refs": {"candidate": {"app": "secret ref"}}})
            with self.assertRaises(recovery.FixtureError):
                recovery._write_report(path, {**report, "hashes": {"key_sha256": "d" * 64}})

    def test_report_preserves_safe_incomplete_failure_and_baseline_provenance(self):
        report = {
            "schema": 1,
            "baseline_ref": "v0.1.0",
            "baseline_sha": BASE,
            "candidate_sha": "a" * 40,
            "image_refs": {"baseline": {}, "candidate": {"app": "candidate-app:local"}},
            "images": {"baseline": {}, "candidate": {}},
            "hashes": {},
            "postgres_version": "",
            "started_at": datetime.now(timezone.utc).isoformat(),
            "results": {"baseline_product": "failed", "backup": "failed"},
            "duration_seconds": 1.0,
            "provenance": None,
        }
        with tempfile.TemporaryDirectory() as temp:
            path = Path(temp) / "report.json"
            recovery._write_report(path, report)
            self.assertEqual(json.loads(path.read_text())["provenance"], None)
            with self.assertRaises(product_fixture.FixtureError):
                recovery._write_report(path, {**report, "results": {"baseline_product": "passed"}})

            provenance = {
                "source_sha": BASE,
                "stock_dockerfile_sha256": "b" * 64,
                "stock_ignore_sha256": "c" * 64,
                "external_dockerfile_sha256": "b" * 64,
                "external_ignore_sha256": "c" * 64,
                "external_recipe_sha256": "e" * 64,
            }
            safe = {**report, "provenance": provenance}
            recovery._write_report(path, safe)
            self.assertEqual(json.loads(path.read_text())["provenance"], provenance)
            with self.assertRaises(product_fixture.FixtureError):
                recovery._write_report(path, {**safe, "provenance": {**provenance,
                                                                       "unexpected": "secret"}})
            for broken in ({**safe, "provenance": {**provenance, "source_sha": "d" * 40}},
                           {**safe, "baseline_sha": ""},
                           {**safe, "provenance": {**provenance, "external_ignore_sha256": "d" * 64}},
                           {**report, "baseline_sha": report["candidate_sha"]},
                           {**report, "baseline_sha": "v0.1.0"}):
                with self.assertRaises(product_fixture.FixtureError):
                    recovery._write_report(path, broken)
            recovery._write_report(path, {**report, "baseline_sha": ""})

    def test_baseline_is_any_ref_of_this_repository_but_never_the_candidate(self):
        with tempfile.TemporaryDirectory() as temp:
            resolved = "c" * 40

            class Run:
                def command(self, command, **_):
                    if command[:2] == ("git", "rev-parse"):
                        self.asked = command[-1]
                        return mock.Mock(stdout=resolved + "\n")
                    if command[:2] == ("git", "status"):
                        raise product_fixture.FixtureError("stop after resolution")
                    return mock.Mock(stdout="")

            run = Run()
            with self.assertRaisesRegex(product_fixture.FixtureError, "different commits"):
                recovery.build_baseline_images("any/ref", run, Path(temp) / "same", {}, resolved)
            self.assertEqual(run.asked, "any/ref^{commit}")
            with self.assertRaisesRegex(product_fixture.FixtureError, "stop after resolution"):
                recovery.build_baseline_images("v0.1.0", run, Path(temp) / "other", {}, "a" * 40)
            resolved = "not-a-sha"
            with self.assertRaisesRegex(product_fixture.FixtureError, "does not resolve to a commit"):
                recovery.build_baseline_images("v0.1.0", run, Path(temp) / "bad", {}, "a" * 40)


if __name__ == "__main__":
    unittest.main()
