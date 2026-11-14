#!/usr/bin/env python3
"""Regression tests for the runtime job of .github/workflows/verify.yml."""
import json
import os
from pathlib import Path
import re
import shlex
import shutil
import subprocess
import sys
import tempfile
import textwrap
import unittest

sys.dont_write_bytecode = True

ROOT = Path(__file__).resolve().parents[3]


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


class VerifyWorkflowTests(unittest.TestCase):
    def test_runtime_job_runs_only_for_manual_runs_and_release_tags(self):
        workflow = (ROOT / ".github/workflows/verify.yml").read_text(encoding="utf-8")
        triggers = workflow.split("\npermissions:", 1)[0]
        self.assertIn("  push:\n    branches: [main]\n    tags: ['v*']\n", triggers)
        self.assertIn("  workflow_dispatch:\n", triggers)
        job = workflow.split("\n  runtime:\n", 1)[1].split("\n    steps:", 1)[0]
        self.assertIn("    if: github.event_name == 'workflow_dispatch' || startsWith(github.ref, 'refs/tags/v')\n", job)
        self.assertNotIn("\n    if: ", job.split("\n    if: ", 1)[1].split("\n", 1)[1],
                         "the runtime job has exactly one condition")

    def test_publish_job_pushes_only_verified_images_after_every_other_job(self):
        workflow = (ROOT / ".github/workflows/verify.yml").read_text(encoding="utf-8")
        job = workflow.split("\n  publish:\n", 1)[1]
        self.assertNotRegex(job, r"\n  [A-Za-z_][A-Za-z0-9_-]*:\n", "publish must be the last job")
        self.assertIn("    needs: [secret-scan, backend, frontend, runtime]\n", job)
        self.assertIn("    if: startsWith(github.ref, 'refs/tags/v')\n", job)
        self.assertNotIn("always()", job)
        self.assertNotIn("pull_request_target", workflow)
        self.assertEqual(1, workflow.count("packages: write"))
        self.assertIn("      packages: write\n", job)
        self.assertNotRegex(job, r"docker (buildx )?build\b")
        self.assertIn("name: verified-runtime-images", job)
        for name in ("packio", "packio-worker", "packio-ssr"):
            self.assertIn(f"ghcr.io/packio-php/{name}", job)
        self.assertIn(".github/scripts/image-tags.sh", job)
        self.assertIn("test/Support/Release/image-tags.test.sh", workflow)

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
            self.assertTrue(any("notices.test.py" in call and "--app-image sha256:" + "a" * 64 in call
                                and "--worker-image sha256:" + "b" * 64 in call
                                and "--ssr-image sha256:" + "c" * 64 in call
                                for call in python_calls), "the runtime step must run the notices check")
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


if __name__ == "__main__":
    unittest.main(verbosity=2)
