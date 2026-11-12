#!/usr/bin/env python3
"""Exercise RR service supervision against a disposable PostgreSQL instance.

Build the prod-worker target first, then run:
    python3 test/Support/RoadRunner/services.test.py [image]
No application ports or persistent database volumes are created.
"""
import json
import subprocess
import sys
import time
import uuid
suffix = uuid.uuid4().hex
network = 'packio-service-check-' + suffix
db = network + '-db'
worker = network + '-worker'
image_ref = sys.argv[1] if len(sys.argv) > 1 else 'packio-worker-service-check:local'

def docker(*args, check=True, timeout=30):
    r = subprocess.run(['docker', *args], capture_output=True, text=True, timeout=timeout)
    if check and r.returncode:
        raise RuntimeError(r.stderr + '\n' + r.stdout)
    return r


def resolve_image(ref):
    result = docker('image', 'inspect', '--format', '{{.Id}}', ref)
    image_id = result.stdout.strip()
    if not image_id.startswith('sha256:'):
        raise RuntimeError(f'Could not resolve image {ref!r} to a local immutable ID')
    return image_id


image = resolve_image(image_ref)
print(f'Resolved worker image: {image}', flush=True)
created_containers = []
created_networks = []


def run_container(*args, **kwargs):
    result = docker('run', *args, **kwargs)
    container_id = result.stdout.strip().splitlines()[-1]
    if not container_id:
        raise RuntimeError('docker run succeeded without returning a container ID')
    created_containers.append(container_id)
    return container_id
rpc = "require '/app/vendor/autoload.php'; $r=Spiral\\Goridge\\RPC\\RPC::create('tcp://127.0.0.1:6001'); echo json_encode($r->call('service.Statuses',['name'=>'worker']));"

def statuses():
    return json.loads(docker('exec', worker, 'php', '-r', rpc).stdout)['status']

def healthy():
    return docker('exec', worker, 'php', 'bin/healthcheck-roadrunner-services.php', check=False).returncode == 0
env = []
for k, v in {'APP_PRODUCTION': 'true', 'APP_DEBUG': 'false', 'DEFAULT_DB_CONNECTION': 'postgres', 'DATABASE_HOST': db, 'DATABASE_PORT': '5432', 'DATABASE_NAME': 'packio', 'DATABASE_USER': 'packio', 'DATABASE_PASS': 'smoke-only', 'APP_SECRET_ENCRYPTION_KEY': 'smoke-test-only-key', 'LOG_CHANNEL': 'stderr', 'PACKIO_WORKER_NUM': '7', 'WORKER_TIMEOUT': '1'}.items():
    env += ['-e', k + '=' + v]
try:
    created_networks.append(docker('network', 'create', '--internal', network).stdout.strip())
    run_container('-d', '--name', db, '--network', network, '--tmpfs', '/var/lib/postgresql/data', '-e', 'POSTGRES_DB=packio', '-e', 'POSTGRES_USER=packio', '-e', 'POSTGRES_PASSWORD=smoke-only', 'postgres:17-alpine')
    for i in range(30):
        if docker('exec', db, 'pg_isready', '-U', 'packio', check=False).returncode == 0:
            break
        time.sleep(0.5)
    docker('run', '--rm', '--pull=never', '--network', network, *env, '--entrypoint', 'php', image, 'vendor/bin/laminas', 'cycle:migration:run', '-n', timeout=60)
    worker_id = run_container('-d', '--pull=never', '--name', worker, '--network', network, *env, image)
    for i in range(30):
        if healthy():
            break
        time.sleep(0.5)
    else:
        raise RuntimeError('Worker health failed: ' + docker('logs', worker).stdout + docker('logs', worker).stderr)
    print('PASS: seven queue processes and one of each maintenance service are healthy.', flush=True)
    for signal in ['TERM', 'KILL']:
        before = {x['pid'] for x in statuses()}
        docker('exec', worker, 'sh', '-c', 'kill "$1" "$2"', 'sh', '-' + signal, str(next(iter(before))))
        for i in range(30):
            time.sleep(0.5)
            after = {x['pid'] for x in statuses()}
            if len(after) == 7 and after != before and healthy():
                break
        else:
            raise RuntimeError('Worker not replaced after ' + signal)
        print('PASS: worker replaced after ' + signal, flush=True)
    docker('exec', worker, 'php', '-r', rpc.replace("'service.Statuses'", "'service.Terminate'").replace("'name'=>'worker'", "'name'=>'worker-cleanup'"))
    assert not healthy(), 'Healthcheck accepted missing maintenance service'
    print('PASS: healthcheck detects missing maintenance service.', flush=True)
    start = time.monotonic()
    docker('stop', '-t', '15', worker, timeout=25)
    state = json.loads(docker('inspect', worker, '--format', '{{json .State}}').stdout)
    print('Stop result:', state['ExitCode'], 'in', round(time.monotonic() - start, 2), 'seconds', flush=True)
    assert state['ExitCode'] == 0, 'Shutdown required forced kill'
    docker('rm', worker_id)
    worker_id = run_container('-d', '--pull=never', '--name', worker, '--network', network, *env, '-e', 'PACKIO_WORKER_NUM=3', image)
    for i in range(30):
        if healthy() and len(statuses()) == 3:
            break
        time.sleep(0.5)
    else:
        raise RuntimeError('Configured worker count was not applied')
    print('PASS: changing PACKIO_WORKER_NUM starts three queue consumers.', flush=True)
    docker('stop', '-t', '15', worker, timeout=25)
    state = json.loads(docker('inspect', worker, '--format', '{{json .State}}').stdout)
    assert state['ExitCode'] == 0, 'Full service set did not stop gracefully'
    invalid = docker('run', '--rm', '--pull=never', '--network', 'none', '-e', 'PACKIO_WORKER_NUM=0', image, check=False)
    assert invalid.returncode != 0 and 'positive integer' in invalid.stderr
    print('PASS: invalid worker count is rejected.', flush=True)
finally:
    for container_id in created_containers:
        docker('rm', '-f', container_id, check=False)
    for network_id in created_networks:
        docker('network', 'rm', network_id, check=False)
