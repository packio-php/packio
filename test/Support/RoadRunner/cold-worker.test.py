#!/usr/bin/env python3
"""Run real source.create jobs against cold production worker filesystems."""

import json
from pathlib import Path
import re
import subprocess
import sys
import tempfile
import time
import uuid

ROOT = Path(__file__).resolve().parents[3]
IMAGE_REF = sys.argv[1] if len(sys.argv) > 1 else "packio-012-worker:local"
SUFFIX = uuid.uuid4().hex
NETWORK = "packio-cold-worker-" + SUFFIX
DB = NETWORK + "-db"
PRODUCER = NETWORK + "-producer"
WORKER = NETWORK + "-worker"


def docker(*args, check=True, timeout=90):
    result = subprocess.run(("docker", *args), capture_output=True, text=True, timeout=timeout)
    if check and result.returncode:
        raise RuntimeError(f"docker {' '.join(args)} failed:\n{result.stdout[-2000:]}\n{result.stderr[-2000:]}")
    return result


def resolve_image(ref):
    result = docker("image", "inspect", "--format", "{{.Id}}", ref)
    image_id = result.stdout.strip()
    if not image_id.startswith("sha256:"):
        raise RuntimeError(f"Could not resolve image {ref!r} to a local immutable ID")
    return image_id


IMAGE = resolve_image(IMAGE_REF)
print(f"Resolved worker image: {IMAGE}", flush=True)
created_containers = []
created_networks = []
created_images = []


def run_container(*args, **kwargs):
    result = docker("run", *args, **kwargs)
    container_id = result.stdout.strip().splitlines()[-1]
    if not container_id:
        raise RuntimeError("docker run succeeded without returning a container ID")
    created_containers.append(container_id)
    return container_id


def php(*args):
    result = docker("exec", PRODUCER, "php", "/tmp/cold-worker-fixture.php", *args)
    return json.loads(result.stdout.strip())


def wait_for(predicate, description, seconds=90):
    deadline = time.monotonic() + seconds
    while time.monotonic() < deadline:
        value = predicate()
        if value:
            return value
        time.sleep(0.5)
    logs = docker("logs", WORKER, check=False)
    raise AssertionError(f"Timed out waiting for {description}; worker logs:\n{(logs.stdout + logs.stderr)[-3000:]}")


def job_status(job_id, resource, organization):
    return php("status", job_id, resource, organization)


def ready():
    return docker("exec", WORKER, "php", "bin/healthcheck-roadrunner-services.php", check=False).returncode == 0


def start_worker(env, count):
    worker_id = run_container("-d", "--pull=never", "--name", WORKER, "--network", NETWORK, *env,
           "-e", f"PACKIO_WORKER_NUM={count}", "-e", "WORKER_TIMEOUT=1",
           "-e", "WORKER_MAX_JOBS=1", IMAGE)
    wait_for(ready, "worker readiness")
    mode = docker("exec", WORKER, "stat", "-c", "%a", "/app/data/cache/object-mapper").stdout.strip()
    assert mode == "700", f"mapper cache permissions are {mode}"
    assert docker("exec", WORKER, "sh", "-ec",
                  "test -n \"$(find /app/data/cache/object-mapper -type f -print -quit)\"").returncode == 0
    assert docker("exec", WORKER, "id", "-u").stdout.strip() != "0"
    return worker_id


def enqueue_and_verify(organization, owner, resource):
    job = php("enqueue", organization, owner, resource)["job"]
    result = wait_for(
        lambda: (state if (state := job_status(job, resource, organization))["status"] in ("completed", "failed") else None),
        f"source.create job {job}",
    )
    assert result["status"] == "completed", result
    assert result["source_ids"], result
    assert result["worker_id"], result
    assert result["attempts"] == 1, result
    return result


def main():
    env = []
    for key, value in {
        "APP_PRODUCTION": "true", "APP_DEBUG": "false", "DEFAULT_DB_CONNECTION": "postgres",
        "DATABASE_HOST": DB, "DATABASE_PORT": "5432", "DATABASE_NAME": "packio",
        "DATABASE_USER": "packio", "DATABASE_PASS": "smoke-only",
        "APP_SECRET_ENCRYPTION_KEY": "smoke-test-only-key", "LOG_CHANNEL": "stderr",
    }.items():
        env += ("-e", f"{key}={value}")
    try:
        created_networks.append(docker("network", "create", "--internal", NETWORK).stdout.strip())
        run_container("-d", "--name", DB, "--network", NETWORK,
               "--tmpfs", "/var/lib/postgresql/data", "-e", "POSTGRES_DB=packio",
               "-e", "POSTGRES_USER=packio", "-e", "POSTGRES_PASSWORD=smoke-only",
               "postgres:17-alpine")
        wait_for(lambda: docker("exec", DB, "pg_isready", "-U", "packio", check=False).returncode == 0, "PostgreSQL")
        run_container("-d", "--pull=never", "--name", PRODUCER, "--network", NETWORK, *env,
               "--entrypoint", "sleep", IMAGE, "600")
        docker("cp", str(ROOT / "test/Support/RoadRunner/cold-worker-fixture.php"),
               PRODUCER + ":/tmp/cold-worker-fixture.php")
        docker("exec", PRODUCER, "php", "vendor/bin/laminas", "cycle:migration:run", "-n")
        docker("exec", PRODUCER, "/usr/local/bin/warmup-object-mapper.sh")
        setup = php("setup")
        worker_id = start_worker(env, 3)
        first = enqueue_and_verify(setup["organization"], setup["owner"],
                                   "https://git.example.test/cold/first.git")
        # Workers start one by one; the first job can finish before the last one logs its start.
        wait_for(lambda: len(set(re.findall(r"Worker \[([^]]+)\] started",
                 (lambda r: r.stdout + r.stderr)(docker("logs", WORKER))))) >= 3, "three distinct worker IDs")
        wait_for(lambda: ready() and "reached max jobs (1)" in (lambda r: r.stdout + r.stderr)(docker("logs", WORKER)),
                 "max-jobs recycling")
        docker("stop", "-t", "15", WORKER, timeout=30)
        docker("rm", worker_id)
        worker_id = start_worker(env, 2)
        second = enqueue_and_verify(setup["organization"], setup["owner"],
                                    "https://git.example.test/cold/second.git")
        assert len(first["source_ids"]) == 1, first
        assert len(second["source_ids"]) == 2, second
        assert first["source_ids"][0] in second["source_ids"], second
        print("PASS: two real source.create jobs completed with distinct Source records across cold worker recreation.")
        print("PASS: mapper cache is owner-only, worker IDs are unique, and max-jobs recycling retained supervision.")
        failed = docker("run", "--rm", "--pull=never", "--network", "none", *env,
                        "-e", "OBJECT_MAPPER_CACHE_DIR=/proc/packio-unwritable",
                        IMAGE, check=False)
        assert failed.returncode != 0 and "worker services were not started" in failed.stderr
        assert "Worker [" not in failed.stdout + failed.stderr
        print("PASS: unwritable mapper cache fails before consumers start.")
        with tempfile.TemporaryDirectory(prefix="packio-invalid-mapping-") as temp:
            path = Path(temp)
            # BuildKit treats a bare local ID in FROM as a repository/tag.
            # Bind this disposable build reference to the already resolved worker.
            base_image = "packio-cold-worker-base:" + SUFFIX
            docker("tag", IMAGE, base_image)
            created_images.append((base_image, IMAGE))
            (path / "Dockerfile").write_text(
                f"FROM {base_image}\nUSER root\nCOPY invalid.global.php /app/config/autoload/invalid.global.php\nUSER app\n"
            )
            (path / "invalid.global.php").write_text(
                "<?php return ['object_mapper' => ['mappings' => ['invalid' => "
                "['source' => 'Missing\\\\Source', 'target' => 'Missing\\\\Target']]]];\n"
            )
            invalid_image = "packio-invalid-mapping:" + SUFFIX
            built = docker("build", "-q", "-t", invalid_image, str(path), timeout=120)
            invalid_image_id = built.stdout.strip().splitlines()[-1]
            if not invalid_image_id.startswith("sha256:"):
                raise RuntimeError("docker build succeeded without returning an image ID")
            created_images.append((invalid_image, invalid_image_id))
            failed = docker("run", "--rm", "--pull=never", "--network", "none", *env, invalid_image_id, check=False)
            assert failed.returncode != 0 and "worker services were not started" in failed.stderr
            assert "Worker [" not in failed.stdout + failed.stderr
            print("PASS: invalid mapping definition fails before consumers start.")
    finally:
        for container_id in created_containers:
            docker("rm", "-f", container_id, check=False)
        for network_id in created_networks:
            docker("network", "rm", network_id, check=False)
        for image_tag, image_id in created_images:
            current = docker("image", "inspect", "--format", "{{.Id}}", image_tag, check=False)
            if current.returncode == 0 and current.stdout.strip() == image_id:
                docker("image", "rm", image_tag, check=False)


if __name__ == "__main__":
    main()
