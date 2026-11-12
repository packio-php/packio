#!/usr/bin/env python3
"""Check the one-shot dev setup scripts and the dev compose wiring."""
import json
import os
from pathlib import Path
import subprocess
import tempfile
import time
import unittest

ROOT = Path(__file__).resolve().parents[3]
DOCKER = subprocess.run(["docker", "compose", "version"], capture_output=True).returncode == 0 \
    if subprocess.run(["which", "docker"], capture_output=True).returncode == 0 else False


class ScriptTest(unittest.TestCase):
    def setUp(self):
        self._tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self._tmp.cleanup)
        self.app = Path(self._tmp.name) / "app"
        self.app.mkdir()
        self.bin = Path(self._tmp.name) / "bin"
        self.bin.mkdir()
        self.log = Path(self._tmp.name) / "log"
        self.log.touch()
        self.fake("composer")
        self.fake("npm")

    def fake(self, name, code=0):
        path = self.bin / name
        path.write_text(f'#!/bin/sh\necho "{name} $*" >> "{self.log}"\nexit {code}\n')
        path.chmod(0o755)

    def touch(self, name, age):
        path = self.app / name
        path.parent.mkdir(parents=True, exist_ok=True)
        path.touch()
        stamp = time.time() - age
        os.utime(path, (stamp, stamp))

    def run_script(self, script):
        return subprocess.run(["sh", str(ROOT / ".docker" / script), str(self.app)],
                              env={**os.environ, "PATH": f"{self.bin}:{os.environ['PATH']}"},
                              capture_output=True)

    def logged(self):
        return self.log.read_text()

    def php_up_to_date(self, lock_age=100, installed_age=10):
        self.touch("vendor/autoload.php", 0)
        self.touch("composer.lock", lock_age)
        self.touch("vendor/composer/installed.json", installed_age)
        self.touch("config/development.config.php", 0)

    def test_php_setup_installs_on_fresh_checkout(self):
        self.assertEqual(self.run_script("setup-dev-php.sh").returncode, 0)
        log = self.logged()
        self.assertIn("install --no-interaction --no-progress", log)
        self.assertIn("development-enable", log)
        self.assertIn("generate-release-metadata", log)

    def test_php_setup_skips_when_up_to_date(self):
        self.php_up_to_date()
        self.assertEqual(self.run_script("setup-dev-php.sh").returncode, 0)
        log = self.logged()
        self.assertNotIn("install", log)
        self.assertNotIn("development-enable", log)
        self.assertIn("generate-release-metadata", log)

    def test_php_setup_reinstalls_when_lock_is_newer(self):
        self.php_up_to_date(lock_age=10, installed_age=100)
        self.run_script("setup-dev-php.sh")
        self.assertIn("install --no-interaction --no-progress", self.logged())

    def test_node_setup_installs_on_fresh_checkout(self):
        self.assertEqual(self.run_script("setup-dev-node.sh").returncode, 0)
        log = self.logged()
        self.assertIn("ci --no-audit --no-fund", log)
        self.assertIn("run build:ssr", log)

    def test_node_setup_skips_when_up_to_date(self):
        self.touch("package-lock.json", 100)
        self.touch("node_modules/.package-lock.json", 10)
        self.touch("data/ssr/ssr.mjs", 0)
        self.assertEqual(self.run_script("setup-dev-node.sh").returncode, 0)
        self.assertEqual(self.logged(), "")

    def test_node_setup_reinstalls_when_lock_is_newer(self):
        self.touch("package-lock.json", 10)
        self.touch("node_modules/.package-lock.json", 100)
        self.touch("data/ssr/ssr.mjs", 0)
        self.run_script("setup-dev-node.sh")
        self.assertIn("ci --no-audit --no-fund", self.logged())
        self.assertNotIn("build:ssr", self.logged())

    def test_setup_fails_when_install_fails(self):
        self.fake("composer", 1)
        self.assertNotEqual(self.run_script("setup-dev-php.sh").returncode, 0)


@unittest.skipUnless(DOCKER, "docker compose is not available")
class ComposeTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        # Empty env file and no PACKIO_*/MAIL_* so a developer's .env cannot change the result.
        env = {k: v for k, v in os.environ.items() if not k.startswith(("PACKIO_", "MAIL_"))}
        with tempfile.NamedTemporaryFile() as empty:
            out = subprocess.check_output(
                ["docker", "compose", "-f", "compose.yaml", "--env-file", empty.name, "config", "--format", "json"],
                cwd=ROOT, env=env)
        cls.services = json.loads(out)["services"]

    WORKERS = ["worker", "worker-cleanup", "email-verification-cleanup",
               "password-reset-cleanup", "source-oauth-refresh"]

    def test_all_app_services_send_mail_to_mailpit(self):
        for name in ["app", *self.WORKERS]:
            env = self.services[name]["environment"]
            self.assertEqual(env.get("MAIL_HOST"), "mailpit", name)
            self.assertEqual(env.get("MAIL_PORT"), "1025", name)

    def test_dependents_wait_for_setup_success(self):
        for setup, names in (("setup-php", ["app", *self.WORKERS]), ("setup-node", ["vite", "ssr"])):
            for name in names:
                dep = self.services[name]["depends_on"].get(setup, {})
                self.assertEqual(dep.get("condition"), "service_completed_successfully", f"{name}/{setup}")

    def test_setup_services_run_as_docker_uid(self):
        for name in ("setup-php", "setup-node"):
            self.assertEqual(self.services[name]["user"], "1000:1000")
            self.assertEqual(self.services[name]["restart"], "no")
        self.assertEqual(self.services["vite"]["user"], "1000:1000")


if __name__ == "__main__":
    unittest.main()
