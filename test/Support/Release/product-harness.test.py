#!/usr/bin/env python3
"""Regression tests for E1 fixture ownership, validation, and safe reporting."""

import importlib.util
import io
import hashlib
import json
import os
from pathlib import Path
import socket
import signal
import ssl
import subprocess
import tempfile
import threading
import time
import unittest
import uuid
from unittest import mock


ROOT = Path(__file__).resolve().parents[3]
sys_path = ROOT / "test" / "Support" / "Release"
import sys
sys.path.insert(0, str(sys_path))

sys.dont_write_bytecode = True
import product_fixture
from product_fixture import (FixtureCleanupError, FixtureError, Run, assert_safe_mounts, public_report,
                             require_service, require_services, stop_fixture,
                             validate_candidate_sha, validate_image_metadata)

PRODUCT_SPEC = importlib.util.spec_from_file_location("release_product_harness", sys_path / "product.test.py")
product = importlib.util.module_from_spec(PRODUCT_SPEC)
PRODUCT_SPEC.loader.exec_module(product)
RECOVERY_SPEC = importlib.util.spec_from_file_location("release_recovery_harness", sys_path / "recovery.test.py")
recovery = importlib.util.module_from_spec(RECOVERY_SPEC)
RECOVERY_SPEC.loader.exec_module(recovery)


class ProductHarnessTest(unittest.TestCase):
    def test_request_deadline_restores_prior_signal_handler_and_timer(self):
        import product_fixture

        prior_handler = signal.getsignal(signal.SIGALRM)
        prior_delay, prior_interval = signal.getitimer(signal.ITIMER_REAL)
        observed_handler = lambda _signum, _frame: None
        try:
            signal.signal(signal.SIGALRM, observed_handler)
            signal.setitimer(signal.ITIMER_REAL, 5)
            with product_fixture._request_deadline(Run(timeout=2)):
                time.sleep(0.02)
            remaining, interval = signal.getitimer(signal.ITIMER_REAL)
            self.assertIs(signal.getsignal(signal.SIGALRM), observed_handler)
            self.assertGreater(remaining, 4.8)
            self.assertEqual(interval, 0)
        finally:
            signal.setitimer(signal.ITIMER_REAL, 0)
            signal.signal(signal.SIGALRM, prior_handler)
            if prior_delay:
                signal.setitimer(signal.ITIMER_REAL, prior_delay, prior_interval)

    def test_composer_negative_rejects_docker_startup_failure(self):
        from product_fixture import _composer_case
        context = {"private_dir": tempfile.mkdtemp(), "project": "fixture",
                   "ancillary_images": {"composer": "composer-fixture"}, "_run": mock.Mock()}
        context["_run"].command.return_value = mock.Mock(returncode=125, stdout="")
        dataset = {"registry_host": "packages.registry.fixture.test", "package_name": "packio-fixture/private-package",
                   "package_version": "1.0.0"}
        try:
            with self.assertRaisesRegex(FixtureError, "Docker"):
                _composer_case(context, dataset, "anonymous", expected_success=False)
        finally:
            import shutil
            shutil.rmtree(context["private_dir"])

    def test_composer_negative_rejects_successful_install_with_failed_probe(self):
        from product_fixture import _composer_case
        context = {"private_dir": tempfile.mkdtemp(), "project": "fixture",
                   "ancillary_images": {"composer": "composer-fixture"}, "_run": mock.Mock()}
        context["_run"].command.return_value = mock.Mock(
            returncode=1, stdout="PACKIO_CLIENT=started\nPACKIO_COMPOSER_EXIT=0\n"
                            "PACKIO_PROBE_STATUS=1\nPACKIO_CLIENT=complete\n")
        dataset = {"registry_host": "packages.registry.fixture.test", "package_name": "packio-fixture/private-package",
                   "package_version": "1.0.0"}
        try:
            with self.assertRaisesRegex(FixtureError, "installed"):
                _composer_case(context, dataset, "anonymous", expected_success=False)
        finally:
            import shutil
            shutil.rmtree(context["private_dir"])

    def test_composer_negative_rejects_generic_network_failure_without_auth_denial(self):
        from product_fixture import _composer_case
        context = {"private_dir": tempfile.mkdtemp(), "project": "fixture",
                   "ancillary_images": {"composer": "composer-fixture"}, "_run": mock.Mock()}
        context["_run"].command.return_value = mock.Mock(
            returncode=0, stdout="PACKIO_CLIENT=started\nPACKIO_COMPOSER_EXIT=100\n"
                            "PACKIO_CLIENT=complete\nCould not resolve host: unrelated.example\n")
        dataset = {"registry_host": "packages.registry.fixture.test", "package_name": "packio-fixture/private-package",
                   "package_version": "1.0.0"}
        try:
            with self.assertRaisesRegex(FixtureError, "auth refusal"):
                _composer_case(context, dataset, "anonymous", expected_success=False)
        finally:
            import shutil
            shutil.rmtree(context["private_dir"])

    def test_fixture_request_enforces_absolute_deadline_on_dribbling_tls_headers(self):
        import product_fixture
        from product_fixture import _create_test_certificate

        with tempfile.TemporaryDirectory(prefix=f"packio-e3-{uuid.uuid4().hex}-") as temporary:
            private = Path(temporary)
            _create_test_certificate(Run(), private)

            normal_listener = socket.socket()
            normal_listener.bind(("127.0.0.1", 0))
            normal_listener.listen(1)
            normal_port = normal_listener.getsockname()[1]

            def serve_normal_response():
                raw, _ = normal_listener.accept()
                with raw:
                    tls_context = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
                    tls_context.load_cert_chain(private / "server-cert.pem", private / "server-key.pem")
                    with tls_context.wrap_socket(raw, server_side=True) as peer:
                        peer.recv(4096)
                        peer.sendall(b"HTTP/1.1 200 OK\r\nContent-Length: 2\r\nConnection: close\r\n\r\nok")
                normal_listener.close()

            normal_thread = threading.Thread(target=serve_normal_response, daemon=True)
            normal_thread.start()
            normal_context = {"private_dir": str(private), "https_port": normal_port, "_run": Run(timeout=3)}
            status, _, body = product_fixture._fixture_request(
                normal_context, "packages.registry.fixture.test", "/", label="normal TLS control")
            normal_thread.join(timeout=2)
            self.assertEqual((status, body), (200, b"ok"))

            listener = socket.socket()
            listener.bind(("127.0.0.1", 0))
            listener.listen(1)
            listener.settimeout(2)
            port = listener.getsockname()[1]
            finished = threading.Event()

            def dribble_headers():
                try:
                    raw, _ = listener.accept()
                    with raw:
                        raw.settimeout(1)
                        tls_context = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
                        tls_context.load_cert_chain(private / "server-cert.pem", private / "server-key.pem")
                        with tls_context.wrap_socket(raw, server_side=True) as peer:
                            peer.recv(4096)
                            for byte in b"HTTP/1.1 200 OK\r\nX-Dribble: ":
                                peer.sendall(bytes((byte,)))
                                time.sleep(0.05)
                            time.sleep(1)
                except (OSError, ssl.SSLError):
                    pass
                finally:
                    listener.close()
                    finished.set()

            thread = threading.Thread(target=dribble_headers, daemon=True)
            thread.start()
            run = Run(timeout=0.4)
            start = time.monotonic()
            context = {"private_dir": str(private), "https_port": port, "_run": run}
            try:
                with self.assertRaisesRegex(FixtureError, "timed out"):
                    product_fixture._fixture_request(context, "packages.registry.fixture.test", "/", label="dribble test")
            finally:
                elapsed = time.monotonic() - start
                listener.close()
                thread.join(timeout=2)
            self.assertLess(elapsed, 1.5)
            self.assertTrue(finished.is_set())

    def test_cli_runs_owned_cleanup_after_absolute_http_timeout(self):
        import product_fixture
        from product_fixture import _create_test_certificate

        with tempfile.TemporaryDirectory(prefix=f"packio-e3-{uuid.uuid4().hex}-") as temporary:
            private = Path(temporary)
            _create_test_certificate(Run(), private)
            listener = socket.socket()
            listener.bind(("127.0.0.1", 0))
            listener.listen(1)
            port = listener.getsockname()[1]

            def dribble_headers():
                try:
                    raw, _ = listener.accept()
                    with raw:
                        tls_context = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
                        tls_context.load_cert_chain(private / "server-cert.pem", private / "server-key.pem")
                        with tls_context.wrap_socket(raw, server_side=True) as peer:
                            peer.recv(4096)
                            while True:
                                peer.sendall(b"X")
                                time.sleep(0.05)
                except (OSError, ssl.SSLError):
                    pass
                finally:
                    listener.close()

            threading.Thread(target=dribble_headers, daemon=True).start()
            run = Run(timeout=0.35)
            context = {"private_dir": str(private), "https_port": port, "_run": run,
                       "project": "packio-e3-cleanup", "_hard_deadline": time.monotonic() + 1}

            def exercise_actual_timeout(current):
                product_fixture._fixture_request(current, "packages.registry.fixture.test", "/",
                                                  label="CLI cleanup timeout")

            with tempfile.TemporaryDirectory() as report_dir, mock.patch.object(
                product, "start_fixture", return_value=context
            ), mock.patch.object(product, "prepare_dataset", side_effect=exercise_actual_timeout), mock.patch.object(
                product, "stop_fixture"
            ) as cleanup, mock.patch.object(product_fixture, "_image_harness", return_value=mock.Mock()):
                status = product.main(["--app-image", "app", "--worker-image", "worker", "--ssr-image", "ssr",
                                       "--expected-sha", "a" * 40, "--report", str(Path(report_dir) / "report.json")])
            self.assertEqual(status, 1)
            cleanup.assert_called_once_with(context)

    def test_app_inertia_session_page_retries_with_actual_version_from_conflict(self):
        import product_fixture

        version = "a" * 7
        page = json.dumps({"props": {"csrf": {"token": "fixture-csrf"}}}).encode()
        responses = [(409, [("X-Inertia-Version", version), ("X-Inertia-Location", "/organizations"),
                            ("Set-Cookie", "PACKIO_SESSID=new-session; Path=/; Secure; HttpOnly")], b""),
                     (200, [], page), (200, [], page)]
        context = {"https_port": 443, "app_url": "https://app.fixture.test", "candidate_sha": version + "b" * 33}
        cookies = {"PACKIO_SESSID": "old-session"}
        with mock.patch.object(product_fixture, "_fixture_request", side_effect=responses) as request:
            try:
                props, _ = product_fixture._app_json(context, cookies, "/organizations")
            except FixtureError as error:
                self.fail(f"authenticated Inertia page did not recover from version conflict: {error}")
            product_fixture._app_json(context, cookies, "/organization/tokens")

        self.assertEqual(props["csrf"]["token"], "fixture-csrf")
        self.assertEqual(request.call_count, 3)
        self.assertNotIn("X-Inertia-Version", request.call_args_list[0].kwargs["headers"])
        self.assertEqual(request.call_args_list[1].kwargs["headers"]["X-Inertia-Version"], version)
        self.assertEqual(request.call_args_list[1].kwargs["headers"]["Cookie"], "PACKIO_SESSID=new-session")
        self.assertEqual(request.call_args_list[2].kwargs["headers"]["X-Inertia-Version"], version)
        self.assertEqual(request.call_args_list[2].kwargs["headers"]["Cookie"], "PACKIO_SESSID=new-session")

    def test_precreated_empty_proxy_directories_are_clean_state(self):
        from product_fixture import _empty_paths_check
        with tempfile.TemporaryDirectory() as temporary:
            for relative in ("cache/proxies", "sessions", "rate-limits", "package-archives"):
                (Path(temporary) / relative).mkdir(parents=True)
            result = subprocess.run(["sh", "-ec", _empty_paths_check().replace("/app/data", temporary)],
                                    capture_output=True, text=True)
            self.assertEqual(result.returncode, 0)

    def test_populated_cache_cannot_be_hidden_by_empty_archive_storage(self):
        from product_fixture import _empty_paths_check
        with tempfile.TemporaryDirectory() as temporary:
            for relative in ("cache", "sessions", "rate-limits", "package-archives"):
                (Path(temporary) / relative).mkdir()
            (Path(temporary) / "cache" / "stale.php").write_text("stale configuration")
            result = subprocess.run(["sh", "-ec", _empty_paths_check().replace("/app/data", temporary)],
                                    capture_output=True, text=True)
            self.assertNotEqual(result.returncode, 0)

    def test_public_report_rejects_a_cookie_hidden_in_scenario_names(self):
        with self.assertRaisesRegex(FixtureError, "report"):
            public_report({"results": {"session=generated-private-cookie": "passed"}})

    def test_cli_timeout_is_failure_and_replaces_a_previous_success_report(self):
        with tempfile.TemporaryDirectory() as temporary, mock.patch.object(
            product, "start_fixture", side_effect=FixtureError("fixture timed out")
        ), mock.patch("sys.stderr", new_callable=io.StringIO):
            report = Path(temporary) / "report.json"
            report.write_text('{"results":{"fixture":"passed"}}')
            status = product.main(["--app-image", "app", "--worker-image", "worker", "--ssr-image", "ssr",
                                   "--expected-sha", "a" * 40, "--report", str(report)])
            self.assertNotEqual(status, 0)
            self.assertEqual(json.loads(report.read_text())["results"], {"fixture": "failed"})

    def test_abort_also_cleans_a_partially_started_fixture(self):
        from product_fixture import start_fixture
        with mock.patch("product_fixture.Run.require_tools", side_effect=KeyboardInterrupt), mock.patch(
            "product_fixture.stop_fixture"
        ) as stop:
            with self.assertRaises(KeyboardInterrupt):
                start_fixture("app", "worker", "ssr", "a" * 40)
        stop.assert_called_once()

    def test_cleanup_respects_the_original_hard_deadline(self):
        context = {"project": "packio-e1-" + "3" * 32, "_hard_deadline": 101}
        with mock.patch("product_fixture.time.monotonic", return_value=100), mock.patch(
            "product_fixture.subprocess.run", return_value=mock.Mock(returncode=0, stdout="")
        ) as process:
            stop_fixture(context)
        self.assertTrue(all(call.kwargs["timeout"] <= 1 for call in process.call_args_list))

    def test_cleanup_finishes_container_removal_within_shared_budget(self):
        project = "packio-e1-" + "4" * 32
        owned_id = "a" * 64

        def docker(command, **options):
            if command[1:3] == ("ps", "-aq"):
                output = owned_id + "\n"
            elif command[1] == "inspect":
                output = json.dumps([{"Id": owned_id, "Config": {
                    "Labels": {"com.docker.compose.project": project}}}])
            else:
                if command[1:3] == ("rm", "-f") and options["timeout"] < 6:
                    raise subprocess.TimeoutExpired(command, options["timeout"])
                output = ""
            return subprocess.CompletedProcess(command, 0, output)

        with mock.patch("product_fixture.time.monotonic", return_value=100), mock.patch(
            "product_fixture.subprocess.run", side_effect=docker
        ):
            stop_fixture({"project": project, "_hard_deadline": 130})

    def test_cleanup_failure_retains_only_allowlisted_stderr_categories(self):
        cases = ("permission denied: /private/token permission-canary",
                 "unclassified diagnostic unknown-canary")
        for index, (stderr, category) in enumerate(zip(cases, ("permission_denied", "unknown")), start=5):
            project = "packio-e1-" + str(index) * 32

            def docker(command, *, captured=stderr, **_options):
                if command[1:3] == ("ps", "-aq"):
                    return subprocess.CompletedProcess(command, 23, "", captured)
                return subprocess.CompletedProcess(command, 0, "", "")

            with self.subTest(category=category), mock.patch("product_fixture.subprocess.run", side_effect=docker):
                with self.assertRaises(FixtureError) as caught:
                    stop_fixture({"project": project})
            message = str(caught.exception)
            self.assertIn("docker ps -aq", message)
            self.assertIn("exit 23", message)
            self.assertIn(f"stderr: {category}", message)
            self.assertNotIn("canary", message)

    def test_cleanup_failure_retains_invalid_json_operation_only(self):
        project = "packio-e1-" + "6" * 32
        owned_id = "a" * 64
        responses = [subprocess.CompletedProcess(("docker", "ps"), 0, owned_id + "\n", ""),
                     subprocess.CompletedProcess(("docker", "inspect", owned_id), 0, "not-json stdout-canary", ""),
                     subprocess.CompletedProcess(("docker", "volume", "ls"), 0, "", ""),
                     subprocess.CompletedProcess(("docker", "network", "ls"), 0, "", "")]
        with mock.patch("product_fixture.subprocess.run", side_effect=responses):
            with self.assertRaises(FixtureError) as caught:
                stop_fixture({"project": project})
        message = str(caught.exception)
        self.assertIn("docker inspect: invalid JSON", message)
        self.assertNotIn("stdout-canary", message)

    def test_cleanup_subprocess_timeout_drops_command_and_captured_output(self):
        project = "packio-e1-" + "a" * 32
        canaries = ("command-canary", "stdout-canary", "stderr-canary")

        def docker(command, **options):
            if command[1:3] == ("ps", "-aq"):
                timed_out = subprocess.TimeoutExpired(
                    (*command, canaries[0]), options["timeout"],
                    output=canaries[1], stderr=canaries[2])
                raise timed_out
            return subprocess.CompletedProcess(command, 0, "", "")

        with mock.patch("product_fixture.subprocess.run", side_effect=docker):
            with self.assertRaises(FixtureError) as caught:
                stop_fixture({"project": project})
        error = caught.exception
        message = str(error)
        self.assertIn("docker ps -aq: timeout", message)
        for canary in canaries:
            self.assertNotIn(canary, message)
            self.assertNotIn(canary, repr(error))
        self.assertIsNone(error.__cause__)

    def test_cleanup_deadline_diagnostic_names_the_operation(self):
        project = "packio-e1-" + "9" * 32
        with mock.patch("product_fixture.time.monotonic", return_value=100), \
             mock.patch("product_fixture.subprocess.run") as process:
            with self.assertRaisesRegex(FixtureError, "docker ps -aq: timeout"):
                stop_fixture({"project": project, "_hard_deadline": 99})
        process.assert_not_called()

    def test_startup_failure_remains_primary_when_owned_cleanup_also_fails(self):
        import product_fixture
        with mock.patch.object(product_fixture.Run, "require_tools", side_effect=FixtureError("safe startup failure")), \
             mock.patch.object(product_fixture, "stop_fixture", side_effect=FixtureCleanupError(("docker ps -aq: exit 9",))):
            with self.assertRaisesRegex(FixtureError, "safe startup failure") as caught:
                product_fixture.start_fixture("app", "worker", "ssr", "a" * 40)
        self.assertIsInstance(caught.exception, FixtureCleanupError)
        self.assertEqual(caught.exception.primary_message, "safe startup failure")
        self.assertEqual(caught.exception.diagnostics, ("docker ps -aq: exit 9",))

    def test_unexpected_startup_error_stays_redacted_when_cleanup_fails(self):
        import product_fixture
        with mock.patch.object(product_fixture.Run, "require_tools", side_effect=RuntimeError("primary-canary")), \
             mock.patch.object(product_fixture, "stop_fixture", side_effect=FixtureCleanupError(("docker ps -aq: timeout",))):
            with self.assertRaises(FixtureCleanupError) as caught:
                product_fixture.start_fixture("app", "worker", "ssr", "a" * 40)
        self.assertEqual(caught.exception.primary_message, "unexpected fixture startup failure")
        self.assertNotIn("primary-canary", str(caught.exception))
        self.assertIn("docker ps -aq: timeout", str(caught.exception))

    def test_unexpected_cleanup_error_keeps_safe_startup_failure_classification(self):
        import product_fixture
        with mock.patch.object(product_fixture.Run, "require_tools", side_effect=FixtureError("safe startup failure")), \
             mock.patch.object(product_fixture, "stop_fixture", side_effect=RuntimeError("cleanup-canary")):
            with self.assertRaises(FixtureCleanupError) as caught:
                product_fixture.start_fixture("app", "worker", "ssr", "a" * 40)
        self.assertEqual(caught.exception.primary_message, "safe startup failure")
        self.assertEqual(caught.exception.diagnostics, ("cleanup diagnostic unavailable",))
        self.assertNotIn("cleanup-canary", str(caught.exception))

    def test_startup_cleanup_failure_remains_a_cleanup_failure_in_recovery_report(self):
        project_images = {"app": "app-image", "worker": "worker-image", "ssr": "ssr-image"}
        cleanup = FixtureCleanupError(("docker ps -aq: exit 9 (stderr: permission_denied)",))
        stderr = io.StringIO()

        def command(_run, args, **_options):
            output = args[-1] + "\n" if args[1:3] == ("volume", "create") else ""
            return subprocess.CompletedProcess(args, 0, output, "")

        provenance = {"source_sha": "c" * 40, "stock_dockerfile_sha256": "b" * 64,
                      "stock_ignore_sha256": "d" * 64, "external_dockerfile_sha256": "b" * 64,
                      "external_ignore_sha256": "d" * 64, "external_recipe_sha256": "e" * 64}
        with tempfile.TemporaryDirectory() as temporary, \
             mock.patch.object(recovery, "build_baseline_images", return_value=({}, {}, provenance)), \
             mock.patch.object(recovery, "_baseline_fixture_ids", return_value=project_images), \
             mock.patch.object(recovery.Run, "command", autospec=True, side_effect=command), \
             mock.patch.object(recovery, "_remove_owned_resource", return_value=True), \
             mock.patch.object(product_fixture.Run, "require_tools", side_effect=FixtureError("safe startup failure")), \
             mock.patch.object(product_fixture, "stop_fixture", side_effect=cleanup), \
             mock.patch("sys.stderr", stderr):
            report = Path(temporary) / "recovery.json"
            status = recovery.main(["--baseline-ref", "v0.1.0", "--app-image", "app", "--worker-image", "worker",
                                    "--ssr-image", "ssr", "--expected-sha", "a" * 40,
                                    "--report", str(report)])
            saved_report = json.loads(report.read_text())

        self.assertEqual(status, 1)
        self.assertEqual(saved_report["results"]["cleanup"], "failed")
        self.assertIn("safe startup failure", stderr.getvalue())
        self.assertIn("docker ps -aq: exit 9 (stderr: permission_denied)", stderr.getvalue())
        self.assertNotIn("canary", stderr.getvalue())

    def test_cli_preserves_primary_failure_and_reports_cleanup_diagnostic(self):
        context = {"project": "packio-e1-" + "7" * 32, "private_dir": None, "results": {}}
        cleanup = FixtureCleanupError(("docker rm -f: exit 9 (stderr: resource_in_use)",))
        stderr = io.StringIO()
        reports = []
        with tempfile.TemporaryDirectory() as temporary, \
             mock.patch.object(product, "start_fixture", return_value=context), \
             mock.patch.object(product, "prepare_dataset", side_effect=FixtureError("safe primary failure")), \
             mock.patch.object(product, "stop_fixture", side_effect=cleanup), \
             mock.patch.object(product, "public_report", side_effect=lambda value: reports.append(value) or value), \
             mock.patch("product_fixture._image_harness", return_value=mock.Mock()), \
             mock.patch("sys.stderr", stderr):
            report = Path(temporary) / "report.json"
            status = product.main(["--app-image", "app", "--worker-image", "worker", "--ssr-image", "ssr",
                                   "--expected-sha", "a" * 40, "--report", str(report)])
        self.assertEqual(status, 1)
        self.assertIn("safe primary failure", stderr.getvalue())
        self.assertIn("docker rm -f: exit 9 (stderr: resource_in_use)", stderr.getvalue())
        self.assertEqual(reports[0]["results"], {"fixture": "failed"})

    def test_cli_cleanup_only_failure_is_reported_as_failure(self):
        context = {"project": "packio-e1-" + "8" * 32, "private_dir": None, "results": {},
                   "fixture_sha256": "a" * 64}
        stderr = io.StringIO()
        reports = []
        with tempfile.TemporaryDirectory() as temporary, \
             mock.patch.object(product, "start_fixture", return_value=context), \
             mock.patch.object(product, "prepare_dataset", return_value={}), \
             mock.patch.object(product, "verify_private_install", return_value=None), \
             mock.patch.object(product, "report_image_ids", return_value={}), \
             mock.patch.object(product, "stop_fixture", side_effect=FixtureCleanupError(("docker volume rm: exit 7",))), \
             mock.patch.object(product, "public_report", side_effect=lambda value: reports.append(value) or value), \
             mock.patch("product_fixture._image_harness", return_value=mock.Mock()), \
             mock.patch("sys.stderr", stderr):
            report = Path(temporary) / "report.json"
            status = product.main(["--app-image", "app", "--worker-image", "worker", "--ssr-image", "ssr",
                                   "--expected-sha", "a" * 40, "--report", str(report)])
        self.assertEqual(status, 1)
        self.assertIn("docker volume rm: exit 7", stderr.getvalue())
        self.assertEqual(reports[0]["results"], {"fixture": "failed"})

    def test_tls_cannot_start_after_the_shared_deadline(self):
        from product_fixture import _check_trusted_tls
        run = Run()
        run.deadline = 0
        context = {"private_dir": "/unused", "https_port": 443, "_run": run}
        with mock.patch("product_fixture.ssl.create_default_context"), mock.patch(
            "product_fixture.socket.create_connection"
        ) as connect:
            with self.assertRaisesRegex(FixtureError, "timed out"):
                _check_trusted_tls(context)
        connect.assert_not_called()

    def test_fixture_git_cannot_inherit_repository_or_global_configuration(self):
        from product_fixture import _create_fixture_repository
        with tempfile.TemporaryDirectory() as temporary, mock.patch.dict(
            os.environ, {"GIT_DIR": str(ROOT / ".git"), "GIT_CONFIG_GLOBAL": "/unsafe/config"}
        ):
            run = mock.Mock()
            _create_fixture_repository(Path(temporary), run)
        for call in run.command.call_args_list:
            environment = call.kwargs["env"]
            self.assertFalse("GIT_DIR" in environment, "inherited Git repository can redirect fixture writes")
            self.assertEqual(environment["GIT_CONFIG_GLOBAL"], "/dev/null")

    def test_fixture_git_archive_timestamps_are_utc_under_non_utc_host_timezone(self):
        import zipfile
        from product_fixture import _create_fixture_repository, _make_dist

        temporary_name = f"packio-e2-{uuid.uuid4().hex}-"
        with tempfile.TemporaryDirectory(prefix=temporary_name) as temporary, mock.patch.dict(
            os.environ, {"TZ": "Europe/Riga"}
        ):
            private_dir = Path(temporary)
            run = Run()
            _create_fixture_repository(private_dir, run)
            _make_dist(private_dir, run)

            with zipfile.ZipFile(private_dir / "private-package-1.0.0.zip") as archive:
                timestamps = {entry.date_time for entry in archive.infolist()}
        self.assertTrue(timestamps)
        self.assertEqual(timestamps, {(2000, 1, 1, 0, 0, 0)})

    def test_nonsecret_admin_fixture_config_is_readable_by_image_uid(self):
        from product_fixture import _write_local_config
        with tempfile.TemporaryDirectory() as temporary:
            path = Path(temporary) / "local.php"
            _write_local_config(path, "provider.fixture.test", "172.20.0.2")
            self.assertEqual(path.stat().st_mode & 0o044, 0o044)

    def test_fixture_tls_leaf_is_signed_and_covers_only_fixture_origins(self):
        from product_fixture import _create_test_certificate
        with tempfile.TemporaryDirectory(prefix=f"packio-e2-{uuid.uuid4().hex}-") as temporary:
            private = Path(temporary)
            _create_test_certificate(Run(), private)
            root = private / "root-ca.pem"
            leaf = private / "server-cert.pem"
            root_fingerprint = subprocess.run(
                ["openssl", "x509", "-in", str(root), "-noout", "-fingerprint", "-sha256"],
                capture_output=True, text=True, check=True,
            ).stdout
            leaf_fingerprint = subprocess.run(
                ["openssl", "x509", "-in", str(leaf), "-noout", "-fingerprint", "-sha256"],
                capture_output=True, text=True, check=True,
            ).stdout
            self.assertNotEqual(root_fingerprint, leaf_fingerprint)
            self.assertEqual(subprocess.run(
                ["openssl", "verify", "-CAfile", str(root), str(leaf)],
                capture_output=True, text=True, check=True,
            ).stdout.strip(), f"{leaf}: OK")
            extensions = subprocess.run(
                ["openssl", "x509", "-in", str(leaf), "-noout", "-ext", "subjectAltName"],
                capture_output=True, text=True, check=True,
            ).stdout
            for host in ("app.fixture.test", "*.registry.fixture.test", "provider.fixture.test"):
                self.assertIn(f"DNS:{host}", extensions)
            self.assertEqual(json.loads((private / "browser-policies.json").read_text()),
                             {"policies": {"Certificates": {"Install": ["/run/fixture/root-ca.pem"]}}})
            self.assertEqual((private / "browser-policies.json").stat().st_mode & 0o044, 0o044)

    def test_generic_git_probe_runs_in_the_worker_that_owns_source_jobs(self):
        from product_fixture import _assert_generic_git_provider
        run = mock.Mock()
        run.command.return_value.stdout = "fixture-provider-ok"
        _assert_generic_git_provider({"project": "test"}, run, {})
        command = run.command.call_args.args[0]
        self.assertEqual(command[command.index("exec") + 2], "workers")

    def test_run_requires_docker_and_compose(self):
        with mock.patch("product_fixture.shutil.which", return_value=None):
            with self.assertRaises(FixtureError):
                Run().require_tools()

    def test_run_rejects_commands_after_absolute_deadline(self):
        run = Run(timeout=600)
        run.deadline = 0
        with mock.patch("product_fixture.subprocess.run") as process:
            with self.assertRaisesRegex(FixtureError, "timed out"):
                run.command(("docker", "ps"), label="Docker availability")
        process.assert_not_called()

    def test_run_clamps_each_command_to_remaining_deadline(self):
        run = Run(timeout=600)
        run.deadline = 1000
        with mock.patch("product_fixture.time.monotonic", return_value=990), \
             mock.patch("product_fixture.subprocess.run", return_value=mock.Mock(returncode=0, stdout="ok")) as process:
            run.command(("docker", "ps"), label="Docker availability")
        process.assert_called_once()
        self.assertEqual(process.call_args.kwargs["timeout"], 10)

    def test_compose_cleanup_is_limited_to_the_owned_project(self):
        project = "packio-e1-" + "1" * 32
        owned_id = "a" * 64
        outside_id = "b" * 64
        context = {"project": project}
        responses = [mock.Mock(returncode=0, stdout=f"{owned_id}\n{outside_id}\n"),
                     mock.Mock(returncode=0, stdout=json.dumps([{"Id": "sha256:" + owned_id, "Config": {"Labels": {"com.docker.compose.project": project}}}])),
                     mock.Mock(returncode=0, stdout=json.dumps([{"Id": outside_id, "Config": {"Labels": {"com.docker.compose.project": "other-project"}}}])),
                     mock.Mock(returncode=0, stdout=""), mock.Mock(returncode=0, stdout=""), mock.Mock(returncode=0, stdout="")]
        with mock.patch("product_fixture.subprocess.run", side_effect=responses) as process:
            stop_fixture(context)
        remove = process.call_args_list[3].args[0]
        self.assertEqual(remove, ("docker", "rm", "-f", f"sha256:{owned_id}"))
        self.assertNotIn(outside_id, " ".join(remove))

    def test_external_resource_is_not_in_owned_cleanup_list(self):
        project = "packio-e1-" + "2" * 32
        context = {"project": project, "resource_ids": ["unrelated-sentinel"]}
        responses = [mock.Mock(returncode=0, stdout=""), mock.Mock(returncode=0, stdout=""),
                     mock.Mock(returncode=0, stdout="")]
        with mock.patch("product_fixture.subprocess.run", side_effect=responses) as process:
            stop_fixture(context)
        self.assertTrue(all("unrelated-sentinel" not in call.args[0] for call in process.call_args_list))

    def test_source_mounts_are_rejected_but_named_fixture_mounts_are_allowed(self):
        with tempfile.TemporaryDirectory() as temporary:
            source = Path(temporary) / "checkout"
            fixture = source / "test/Support/Release/fixtures/provider"
            fixture.mkdir(parents=True)
            private = Path(temporary) / "run"
            private.mkdir()
            self.assertTrue(assert_safe_mounts([
                {"type": "bind", "source": str(fixture), "target": "/fixture"},
                {"type": "bind", "source": str(private / "ca.pem"), "target": "/ca.pem"},
            ], source_root=source, allowed_roots=(source / "test/Support/Release/fixtures", private)))
            with self.assertRaisesRegex(FixtureError, "source mount"):
                assert_safe_mounts([{"type": "bind", "source": str(source), "target": "/app"}],
                                   source_root=source,
                                   allowed_roots=(source / "test/Support/Release/fixtures", private))
            with self.assertRaisesRegex(FixtureError, "outside"):
                assert_safe_mounts([{"type": "bind", "source": "/etc/passwd", "target": "/secret"}],
                                   source_root=source,
                                   allowed_roots=(source / "test/Support/Release/fixtures", private))

    def test_public_report_redacts_generated_secrets_and_private_context(self):
        report = public_report({
            "candidate_sha": "a" * 40,
            "images": {"app": "sha256:" + "a" * 64},
            "results": {"startup": "passed"},
            "secret": "private-secret-value",
            "cookie": "session=private-cookie-value",
            "private_dir": "/tmp/private-run",
        })
        encoded = json.dumps(report)
        self.assertNotIn("private-secret-value", encoded)
        self.assertNotIn("private-cookie-value", encoded)
        self.assertNotIn("/tmp/private-run", encoded)
        self.assertEqual(report["candidate_sha"], "a" * 40)

    def test_public_report_keeps_private_install_hashes_and_only_known_results(self):
        install = {
            "organization_id": "12345678-1234-1234-1234-123456789abc",
            "source_id": "abcdefab-cdef-abcd-efab-cdefabcdefab",
            "source_reference": "c" * 40,
            "source_sha256": "a" * 64,
            "archive_sha256": "b" * 64,
            "member_sha256": {"composer.json": "d" * 64, "src/Probe.php": "e" * 64},
            "results": {key: "passed" for key in ("install", "repeat_install", "anonymous", "foreign_org",
                                                    "revoked", "wrong_auth_host")},
        }
        report = public_report({"private_install": install, "token": "must-not-appear"})
        self.assertEqual(report.get("private_install"), install)
        self.assertNotIn("token", json.dumps(report))

        install["results"]["plain_token=secret"] = "passed"
        with self.assertRaisesRegex(FixtureError, "private install"):
            public_report({"private_install": install})

    def test_wrong_image_metadata_sha_is_rejected(self):
        expected = "a" * 40
        with self.assertRaisesRegex(FixtureError, "metadata"):
            validate_image_metadata({"sha": "b" * 12, "dirty": False}, expected, "app")
        with self.assertRaisesRegex(FixtureError, "metadata"):
            validate_image_metadata({"sha": expected, "dirty": True}, expected, "app")
        with self.assertRaisesRegex(FixtureError, "does not match"):
            validate_candidate_sha(expected, "b" * 40)

    def test_explicit_o1_snapshot_is_bound_to_its_frozen_build_and_runtime(self):
        import product_fixture

        base = "a" * 40
        images = {role: "sha256:" + digit * 64 for role, digit in zip(("app", "worker", "ssr"), "123")}
        tags = {role: role + ":test" for role in images}
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary) / "repo"
            context = Path(temporary) / "context"
            evidence = Path(temporary) / "evidence"
            (root / "src").mkdir(parents=True)
            (root / "docs").mkdir()
            (context / "src").mkdir(parents=True)
            (context / "docs").mkdir()
            evidence.mkdir()
            (root / "src/runtime.py").write_text("runtime-frozen\n")
            (context / "src/runtime.py").write_text("runtime-frozen\n")
            changed = b"diff --git a/docs/change.md b/docs/change.md\n"
            (root / "docs/change.md").write_text("docs\n")
            (context / "docs/change.md").write_text("docs\n")
            patch = evidence / "O1.build-01.patch"
            patch.write_bytes(changed)
            patch_hash = hashlib.sha256(changed).hexdigest()
            docs_hash = hashlib.sha256(b"docs\n").hexdigest()
            identity_path = evidence / "identity.json"
            identity_path.write_text(json.dumps({"base_head": base, "patch": patch.name,
                                                "patch_sha256": patch_hash, "full_diff_sha256": patch_hash,
                                                "files": {"docs/change.md": docs_hash}}))
            build_path = evidence / "build.json"
            def write_build(patch_digest=patch_hash):
                build_path.write_text(json.dumps({"kind": "disposable_local_O1_test_snapshot",
                                                  "base_head": base, "patch_sha256": patch_digest,
                                                  "identity": identity_path.name,
                                                  "context": str(context), "tags": tags,
                                                  "targets": {"app": "prod", "worker": "prod-worker",
                                                              "ssr": "prod-ssr"},
                                                  "images": images,
                                                  "builds": {role: {"exit": 0} for role in images}}))

            write_build()
            snapshot = {"identity": str(identity_path), "build": str(build_path)}
            def git_result(args, **_kwargs):
                if args[:2] == ("git", "rev-parse"):
                    return mock.Mock(stdout=base + "\n")
                if args[:3] == ("git", "apply", "--numstat"):
                    return mock.Mock(stdout=b"1\t0\tdocs/change.md\0")
                return mock.Mock(stdout=b"docs/change.md\0src/runtime.py\0")

            with mock.patch.object(product_fixture, "ROOT", root), mock.patch.object(
                    product_fixture.subprocess, "run", side_effect=git_result):
                proof = product_fixture.validate_o1_test_snapshot(snapshot, base, images)
                self.assertRegex(proof["runtime_fingerprint"], r"^[0-9a-f]{64}$")
                self.assertEqual(proof["runtime_file_count"], 1)
                self.assertEqual(proof["images"], images)

                with self.assertRaisesRegex(FixtureError, "snapshot"):
                    product_fixture.validate_o1_test_snapshot(snapshot, "b" * 40, images)
                write_build("0" * 64)
                with self.assertRaisesRegex(FixtureError, "snapshot"):
                    product_fixture.validate_o1_test_snapshot(snapshot, base, images)
                write_build()
                with self.assertRaisesRegex(FixtureError, "snapshot"):
                    product_fixture.validate_o1_test_snapshot(
                        snapshot, base, {**images, "app": "sha256:" + "0" * 64})
                with self.assertRaisesRegex(FixtureError, "snapshot"):
                    product_fixture.validate_o1_test_snapshot(snapshot, base, tags)
                (root / "src/runtime.py").write_text("runtime-edited\n")
                with self.assertRaisesRegex(FixtureError, "snapshot"):
                    product_fixture.validate_o1_test_snapshot(snapshot, base, images)
                (root / "src/runtime.py").write_text("runtime-frozen\n")
                outside = Path(temporary) / "outside-runtime.py"
                outside.write_text("escaped frozen runtime\n")
                (context / "src/runtime.py").unlink()
                (context / "src/runtime.py").symlink_to(outside)
                with self.assertRaisesRegex(FixtureError, "snapshot"):
                    product_fixture.validate_o1_test_snapshot(snapshot, base, images)
                with self.assertRaisesRegex(FixtureError, "snapshot"):
                    product_fixture.validate_o1_test_snapshot({}, base, images)

        dirty_metadata = {"sha": base[:12], "dirty": True}
        with self.assertRaisesRegex(FixtureError, "metadata"):
            validate_image_metadata(dirty_metadata, base, "app")

    def test_explicit_r1_snapshot_binds_notices_and_provenance(self):
        import product_fixture

        base = "a" * 40
        roles = ("app", "worker", "ssr")
        images = {role: "sha256:" + digit * 64 for role, digit in zip(roles, "123")}
        targets = {"app": "prod", "worker": "prod-worker", "ssr": "prod-ssr"}
        runtime_files = (
            "LICENSE", "THIRD_PARTY_NOTICES.md", "third-party-sources.json",
            "public/fonts/LICENSE-JetBrainsMono.txt",
            "public/fonts/LICENSE-PlusJakartaSans.txt", "public/api-doc/assets/LICENSE",
            "public/api-doc/assets/NOTICE",
        )
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary) / "repo"
            context = Path(temporary) / "context"
            evidence = Path(temporary) / "evidence"
            (root / "docs").mkdir(parents=True)
            (context / "docs").mkdir(parents=True)
            evidence.mkdir()
            (root / "docs/change.md").write_text("inventory\n")
            (context / "docs/change.md").write_text("inventory\n")
            for relative in runtime_files:
                for tree in (root, context):
                    path = tree / relative
                    path.parent.mkdir(parents=True, exist_ok=True)
                    path.write_text(relative + "\n")
            patch_bytes = b"diff --git a/docs/change.md b/docs/change.md\n"
            (evidence / "R1.patch").write_bytes(patch_bytes)
            patch_hash = hashlib.sha256(patch_bytes).hexdigest()
            identity_path = evidence / "identity.json"
            identity_path.write_text(json.dumps({"base_head": base, "patch": "R1.patch",
                "patch_sha256": patch_hash, "full_diff_sha256": patch_hash,
                "files": {"docs/change.md": hashlib.sha256(b"inventory\n").hexdigest()}}))
            build_path = evidence / "build.json"

            def write_build(kind="disposable_local_R1_test_snapshot"):
                build_path.write_text(json.dumps({"kind": kind, "base_head": base,
                    "patch_sha256": patch_hash, "identity": identity_path.name, "context": str(context),
                    "tags": {role: role + ":test" for role in roles}, "targets": targets,
                    "images": images, "builds": {role: {"exit": 0} for role in roles}}))

            write_build()
            snapshot = {"identity": str(identity_path), "build": str(build_path)}
            listed = ("docs/change.md", "LICENSE", "THIRD_PARTY_NOTICES.md", "third-party-sources.json",
                      "public/fonts/LICENSE-JetBrainsMono.txt", "public/fonts/LICENSE-PlusJakartaSans.txt",
                      "public/api-doc/assets/LICENSE", "public/api-doc/assets/NOTICE")

            def git_result(args, **_kwargs):
                if args[:2] == ("git", "rev-parse"):
                    return mock.Mock(stdout=base + "\n")
                if args[:3] == ("git", "apply", "--numstat"):
                    return mock.Mock(stdout=b"1\t0\tdocs/change.md\0")
                return mock.Mock(stdout=b"\0".join(path.encode() for path in listed) + b"\0")

            with mock.patch.object(product_fixture, "ROOT", root), mock.patch.object(
                    product_fixture.subprocess, "run", side_effect=git_result):
                proof = product_fixture.validate_r1_test_snapshot(snapshot, base, images)
                self.assertEqual(proof["kind"], "disposable_local_R1_test_snapshot")
                self.assertEqual(proof["runtime_file_count"], len(runtime_files))
                expected_runtime_hashes = sorted((relative, hashlib.sha256((root / relative).read_bytes()).hexdigest())
                                                 for relative in runtime_files)
                expected_fingerprint = hashlib.sha256(b"".join(
                    path.encode() + b"\0" + digest.encode() + b"\n"
                    for path, digest in expected_runtime_hashes
                )).hexdigest()
                self.assertEqual(proof["runtime_fingerprint"], expected_fingerprint)
                self.assertEqual(proof["images"], images)
                write_build(kind="disposable_local_O1_test_snapshot")
                with self.assertRaisesRegex(FixtureError, "snapshot"):
                    product_fixture.validate_r1_test_snapshot(snapshot, base, images)
                write_build()
                (root / "THIRD_PARTY_NOTICES.md").write_text("changed\n")
                with self.assertRaisesRegex(FixtureError, "snapshot"):
                    product_fixture.validate_r1_test_snapshot(snapshot, base, images)
                (root / "THIRD_PARTY_NOTICES.md").write_text("THIRD_PARTY_NOTICES.md\n")
                (root / "third-party-sources.json").write_text("manifest changed\n")
                with self.assertRaisesRegex(FixtureError, "snapshot"):
                    product_fixture.validate_r1_test_snapshot(snapshot, base, images)

    def test_product_cli_explicitly_wires_r1_snapshot_to_the_fixture_and_report(self):
        import product_fixture

        expected = "a" * 40
        images = {role: "sha256:" + digit * 64 for role, digit in zip(("app", "worker", "ssr"), "123")}
        proof = {"kind": "disposable_local_R1_test_snapshot", "dirty": True, "base_head": expected,
                 "patch_sha256": "b" * 64, "runtime_fingerprint": "c" * 64,
                 "runtime_file_count": 4, "images": images}
        provenance = {role: {"id": images[role], "source_sha": expected,
                     "runtime_manifest_digest": "sha256:" + digit * 64,
                     "base_digests": ["sha256:" + "f" * 64]}
                      for role, digit in zip(("app", "worker", "ssr"), "456")}
        context = {"test_snapshot_proof": proof, "resolved_images": images, "results": {},
                   "fixture_sha256": "d" * 64, "image_report": {"images": provenance}}
        with tempfile.TemporaryDirectory() as temporary:
            report = Path(temporary) / "report.json"
            with mock.patch.object(product, "start_fixture", return_value=context) as start, \
                 mock.patch.object(product, "prepare_dataset", return_value={}), \
                 mock.patch.object(product, "verify_private_install", return_value=None), \
                 mock.patch.object(product, "stop_fixture"), \
                 mock.patch.object(product_fixture, "_image_harness", return_value=product_fixture._image_harness()):
                status = product.main(["--app-image", images["app"], "--worker-image", images["worker"],
                    "--ssr-image", images["ssr"], "--expected-sha", expected, "--report", str(report),
                    "--source-identity", "identity.json", "--build-record", "build.json"])
            self.assertEqual(status, 0)
            start.assert_called_once_with(images["app"], images["worker"], images["ssr"], expected,
                test_snapshot={"identity": "identity.json", "build": "build.json"},
                test_snapshot_kind="disposable_local_R1_test_snapshot")
            self.assertEqual(json.loads(report.read_text())["source_snapshot"], proof)
            self.assertEqual(json.loads(report.read_text())["image_provenance"], provenance)

    def test_product_cli_rejects_unpaired_snapshot_flags(self):
        with mock.patch("sys.stderr", new_callable=io.StringIO), self.assertRaises(SystemExit) as error:
            product.main(["--app-image", "app", "--worker-image", "worker", "--ssr-image", "ssr",
                          "--expected-sha", "a" * 40, "--report", "report.json",
                          "--source-identity", "identity.json"])
        self.assertEqual(error.exception.code, 2)
    def test_missing_provider_or_worker_is_a_required_failure(self):
        for service in ("provider", "workers"):
            with self.subTest(service=service), self.assertRaisesRegex(FixtureError, service):
                require_service(service, "exited")
        states = {name: "running" for name in ("app", "workers", "ssr", "postgres", "mailpit", "provider", "tls")}
        for service in ("provider", "workers"):
            with self.subTest(cli_service=service), mock.patch.object(product, "start_fixture",
                    side_effect=lambda *args, missing=service: require_services({**states, missing: "exited"})):
                with tempfile.TemporaryDirectory() as temporary, mock.patch("sys.stderr", new_callable=io.StringIO):
                    status = product.main(["--app-image", "app", "--worker-image", "worker", "--ssr-image", "ssr",
                                           "--expected-sha", "a" * 40, "--report", str(Path(temporary) / "report.json")])
                    self.assertNotEqual(status, 0)

    def test_cli_missing_docker_returns_failure_report(self):
        with tempfile.TemporaryDirectory() as temporary, mock.patch("product_fixture.shutil.which", return_value=None), \
             mock.patch("sys.stderr", new_callable=io.StringIO):
            report = Path(temporary) / "report.json"
            status = product.main(["--app-image", "app", "--worker-image", "worker", "--ssr-image", "ssr",
                                   "--expected-sha", "a" * 40, "--report", str(report)])
            self.assertNotEqual(status, 0)
            self.assertEqual(json.loads(report.read_text())["results"], {"fixture": "failed"})

    def test_cli_wrong_metadata_sha_returns_nonzero(self):
        def fail(*_args):
            validate_image_metadata({"sha": "b" * 12, "dirty": False}, "a" * 40, "app")

        with tempfile.TemporaryDirectory() as temporary, mock.patch.object(product, "start_fixture", side_effect=fail), \
             mock.patch("sys.stderr", new_callable=io.StringIO):
            status = product.main(["--app-image", "app", "--worker-image", "worker", "--ssr-image", "ssr",
                                   "--expected-sha", "a" * 40, "--report", str(Path(temporary) / "report.json")])
            self.assertNotEqual(status, 0)

    def test_fixture_package_and_tag_contract_are_exact(self):
        package = sys_path / "fixtures/private-package"
        metadata = json.loads((package / "composer.json").read_text())
        self.assertEqual(metadata["name"], "packio-fixture/private-package")
        self.assertEqual(metadata["version"], "1.0.0")
        self.assertEqual(metadata["autoload"]["psr-4"]["PackioFixture\\"], "src/")
        self.assertIn("return 'fixture-1.0.0';", (package / "src/Probe.php").read_text())

    def test_product_poll_fails_at_shared_deadline_without_dumping_probe_output(self):
        from product_fixture import poll_until
        run = Run(timeout=30)
        run.deadline = 10
        with mock.patch("product_fixture.time.monotonic", side_effect=[0, 11]), \
             mock.patch("product_fixture.time.sleep") as sleep:
            with self.assertRaisesRegex(FixtureError, "mail did not arrive") as failure:
                poll_until(run, "mail did not arrive", lambda: None, interval=0.1)
        self.assertNotIn("sensitive", str(failure.exception))
        sleep.assert_called_once_with(0.1)

    def test_dataset_archive_hash_is_checked_before_it_is_reported(self):
        from product_fixture import validate_dataset
        with tempfile.TemporaryDirectory() as temporary:
            context = {"private_dir": temporary}
            dataset = {
                "user_id": "aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa",
                "organization_id": "bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb",
                "source_id": "cccccccc-cccc-4ccc-8ccc-cccccccccccc",
                "package_name": "packio-fixture/private-package",
                "package_version": "1.0.0",
                "registry_host": "fixture.registry.test",
                "token_path": str(Path(temporary) / "registry-token"),
                "archive_sha256": "d" * 64,
            }
            archive = Path(temporary) / "stored.zip"
            archive.write_bytes(b"source archive")
            context["_stored_archive_path"] = str(archive)
            dataset["archive_sha256"] = "0" * 64
            with self.assertRaisesRegex(FixtureError, "archive"):
                validate_dataset(context, dataset)

    def test_browser_failure_diagnostic_allows_only_fixed_labels(self):
        from product_fixture import _read_browser_failure
        with tempfile.TemporaryDirectory() as temporary:
            diagnostic = Path(temporary) / "browser-failure.json"
            diagnostic.write_text(json.dumps({"phase": "identity", "step": "authentication_mail",
                                              "error_type": "TimeoutError"}))
            self.assertEqual(_read_browser_failure(diagnostic, "identity"),
                             ("authentication_mail", "TimeoutError"))
            diagnostic.write_text(json.dumps({"phase": "identity", "step": "authentication_mail",
                                              "error_type": "TimeoutError", "message": "private code"}))
            self.assertIsNone(_read_browser_failure(diagnostic, "identity"))
            diagnostic.write_text(json.dumps({"phase": "identity", "step": "private code",
                                              "error_type": "TimeoutError"}))
            self.assertIsNone(_read_browser_failure(diagnostic, "identity"))
            diagnostic.write_text(json.dumps({"phase": "identity", "step": [],
                                              "error_type": "TimeoutError"}))
            self.assertIsNone(_read_browser_failure(diagnostic, "identity"))
            diagnostic.write_text(json.dumps({"phase": "admin-and-org", "step": "organization_submit",
                                              "error_type": "UnknownError"}))
            self.assertEqual(_read_browser_failure(diagnostic, "admin-and-org"),
                             ("organization_submit", "UnknownError"))

    def test_browser_resync_failure_diagnostic_accepts_only_safe_probe_values(self):
        from product_fixture import _read_browser_failure
        with tempfile.TemporaryDirectory() as temporary:
            diagnostic = Path(temporary) / "browser-failure.json"
            safe = {"http_status": 500, "redirect_route_match": False, "flash_visible": False,
                    "final_route_match": False, "browser_error_count": 2}
            for step in ("worker_recreation_resync_http_status", "worker_recreation_resync_redirect",
                         "worker_recreation_resync_flash", "worker_recreation_resync_final_route",
                         "worker_recreation_resync_browser_errors"):
                with self.subTest(step=step):
                    diagnostic.write_text(json.dumps({"phase": "resync", "step": step,
                                                      "error_type": "AssertionError", "resync": safe}))
                    self.assertEqual(_read_browser_failure(diagnostic, "resync"),
                                     (step, "AssertionError", safe))
            diagnostic.write_text(json.dumps({"phase": "resync", "step": "worker_recreation_resync_redirect",
                                              "error_type": "AssertionError",
                                              "resync": {**safe, "location": "https://private.example/path"}}))
            self.assertIsNone(_read_browser_failure(diagnostic, "resync"))
            diagnostic.write_text(json.dumps({"phase": "resync", "step": "worker_recreation_resync_http_status",
                                              "error_type": "AssertionError",
                                              "resync": {**safe, "http_status": True}}))
            self.assertIsNone(_read_browser_failure(diagnostic, "resync"))
            diagnostic.write_text(json.dumps({"phase": "resync", "step": "worker_recreation_resync_http_status",
                                              "error_type": "AssertionError",
                                              "resync": {**safe, "http_status": 99}}))
            self.assertIsNone(_read_browser_failure(diagnostic, "resync"))
            diagnostic.write_text(json.dumps({"phase": "resync", "step": "worker_recreation_resync_browser_errors",
                                              "error_type": "AssertionError",
                                              "resync": {**safe, "browser_error_count": -1}}))
            self.assertIsNone(_read_browser_failure(diagnostic, "resync"))

    def test_browser_source_failure_uses_fixed_action_step_labels(self):
        from product_fixture import _read_browser_failure
        with tempfile.TemporaryDirectory() as temporary:
            diagnostic = Path(temporary) / "browser-failure.json"
            diagnostic.write_text(json.dumps({"phase": "failed-provider-source", "step": "add_source_click",
                                              "error_type": "TimeoutError"}))
            self.assertEqual(_read_browser_failure(diagnostic, "failed-provider-source"),
                             ("add_source_click", "TimeoutError"))
            diagnostic.write_text(json.dumps({"phase": "private-source", "step": "https://private.example/path",
                                              "error_type": "TimeoutError"}))
            self.assertIsNone(_read_browser_failure(diagnostic, "private-source"))

    def test_browser_source_failure_accepts_only_bounded_safe_facts(self):
        from product_fixture import _read_browser_failure
        with tempfile.TemporaryDirectory() as temporary:
            diagnostic = Path(temporary) / "browser-failure.json"
            facts = {"sources_route_match": False, "form_invalid_control_present": True,
                     "browser_error_count": 2, "http_status": 422}
            payload = {"phase": "failed-provider-source", "step": "connect_response_wait_click",
                       "error_type": "TimeoutError", "source": facts}
            diagnostic.write_text(json.dumps(payload))
            self.assertEqual(_read_browser_failure(diagnostic, "failed-provider-source"),
                             ("connect_response_wait_click", "TimeoutError", facts))
            for unsafe in (
                {**facts, "http_status": True},
                {**facts, "http_status": 600},
                {**facts, "browser_error_count": -1},
                {**facts, "form_invalid_control_present": 1},
                {**facts, "location": "https://private.example/path"},
            ):
                diagnostic.write_text(json.dumps({**payload, "source": unsafe}))
                self.assertIsNone(_read_browser_failure(diagnostic, "failed-provider-source"))
            diagnostic.write_text(json.dumps(payload))
            self.assertIsNone(_read_browser_failure(diagnostic, "resync"))
            self.assertIsNone(_read_browser_failure(diagnostic, "private-source"))
            without_status = {key: value for key, value in facts.items() if key != "http_status"}
            diagnostic.write_text(json.dumps({**payload, "source": without_status}))
            self.assertEqual(_read_browser_failure(diagnostic, "failed-provider-source"),
                             ("connect_response_wait_click", "TimeoutError", without_status))

    def test_fresh_worker_cache_check_rejects_files_written_by_warmup(self):
        from product_fixture import _mapper_cache_is_empty
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            self.assertTrue(_mapper_cache_is_empty(root))
            cache = root / "object-mapper" / "v8"
            cache.mkdir(parents=True)
            self.assertTrue(_mapper_cache_is_empty(root))
            (cache / "mapper.php").write_text("generated cache")
            self.assertFalse(_mapper_cache_is_empty(root))


if __name__ == "__main__":
    unittest.main()
