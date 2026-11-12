#!/usr/bin/env python3
"""Run isolated O1 failure drills against explicitly supplied local test images."""

import argparse
import json
import os
from pathlib import Path
import re
import shutil
import signal
import subprocess
import uuid
import time

from product_fixture import (BROWSER_IMAGE, FixtureError, ROOT, _browser_ready,
                             _compose, _free_port, poll_until, start_fixture, stop_fixture)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    for flag in ('app-image', 'worker-image', 'ssr-image', 'expected-sha', 'source-identity', 'build-record', 'report'):
        parser.add_argument('--' + flag, required=True)
    args = parser.parse_args()
    started = time.monotonic()
    context = None
    report = {'kind': 'disposable_local_O1_test_snapshot', 'base_head': args.expected_sha,
              'source_identity': Path(args.source_identity).name, 'results': {}}
    failure = None
    sentinel = 'packio-o1-foreign-sentinel-' + uuid.uuid4().hex
    sentinel_created = False

    def sentinel_command(*command):
        result = subprocess.run(('docker', *command), stdout=subprocess.PIPE, stderr=subprocess.DEVNULL, text=True, timeout=10)
        if result.returncode:
            raise FixtureError('foreign sentinel control failed')
        return result.stdout.strip()

    def interrupted(_signum, _frame):
        raise FixtureError('O1 drill interrupted')

    signal.signal(signal.SIGTERM, interrupted)
    signal.signal(signal.SIGINT, interrupted)
    try:
        identity = json.loads(Path(args.source_identity).read_text())
        if identity.get('base_head') != args.expected_sha:
            raise FixtureError('test snapshot identity differs from requested base HEAD')
        report['source_patch_sha256'] = identity['patch_sha256']
        sentinel_command('volume', 'create', sentinel)
        sentinel_created = True
        sentinel_command('run', '--rm', '--name', sentinel + '-writer', '--user', '0', '--entrypoint', 'sh', '--volume', sentinel + ':/o1-sentinel', args.app_image,
                         '-c', 'printf o1-foreign-control > /o1-sentinel/control')
        context = start_fixture(args.app_image, args.worker_image, args.ssr_image, args.expected_sha,
                                test_snapshot={'identity': args.source_identity, 'build': args.build_record})
        proof = context['test_snapshot_proof']
        report['runtime_fingerprint'] = proof['runtime_fingerprint']
        report['runtime_file_count'] = proof['runtime_file_count']
        run = context['_run']
        env = context['_compose_env']

        def compose(*command):
            return run.command(_compose(context['project'], *command), label='owned O1 Compose command', cwd=ROOT, env=env)

        def exec_app(*command, check=True):
            return run.command(_compose(context['project'], 'exec', '-T', 'app', *command),
                               label='owned O1 application probe', cwd=ROOT, env=env, check=check)

        def endpoint(path):
            result = exec_app('curl', '--max-time', '2', '-sS', '-w', '\n%{http_code}', 'http://127.0.0.1/' + path)
            body, status = result.stdout.strip().rsplit('\n', 1)
            return int(status), json.loads(body)

        def assert_endpoint(path, status, body):
            if endpoint(path) != (status, body):
                raise FixtureError('unexpected public operational status')

        def restarts():
            container = compose('ps', '-q', 'app').stdout.strip()
            return int(run.command(('docker', 'inspect', '-f', '{{.RestartCount}}', container), label='owned restart count').stdout)

        assert_endpoint('ready', 200, {'status': 'ready'})
        assert_endpoint('health', 200, {'status': 'OK'})
        initial_restarts = restarts()
        report['images'] = context['resolved_images']
        report['results']['ready_bootstrap'] = 'passed'
        compose('stop', '-t', '3', 'postgres')
        tick = time.monotonic()
        assert_endpoint('ready', 503, {'status': 'not_ready'})
        report['database_failure_seconds'] = round(time.monotonic() - tick, 3)
        if report['database_failure_seconds'] >= 2:
            raise FixtureError('readiness exceeded its total budget')
        assert_endpoint('health', 200, {'status': 'OK'})
        if exec_app('sh', '/usr/local/bin/roadrunner-healthcheck.sh', check=False).returncode != 0:
            raise FixtureError('process health failed during database outage')
        if exec_app('sh', '/usr/local/bin/roadrunner-healthcheck.sh', '--ready', check=False).returncode == 0:
            raise FixtureError('traffic readiness accepted database outage')
        report['results']['database_down_process_alive_traffic_closed'] = 'passed'
        compose('start', 'postgres')
        poll_until(run, 'database recovery readiness', lambda: endpoint('ready') == (200, {'status': 'ready'}))
        if restarts() != initial_restarts:
            raise FixtureError('database outage restarted the application')
        report['results']['database_recovery_without_restart'] = 'passed'

        # Readiness may stay ready under SSR failure only if the real browser mounts CSR.
        compose('stop', '-t', '3', 'ssr')
        assert_endpoint('ready', 200, {'status': 'ready'})
        browser_image = run.command(('docker', 'image', 'inspect', '-f', '{{.Id}}', BROWSER_IMAGE), label='matching Firefox image').stdout.strip()
        if not re.fullmatch(r'sha256:[0-9a-f]{64}', browser_image):
            raise FixtureError('matching Firefox image unavailable')
        script = r'''
import { firefox } from 'playwright';
const browser = await firefox.connect(process.env.O1_BROWSER_ENDPOINT);
try {
  const page = await browser.newPage();
  if (process.env.O1_TLS_CONTROL === 'untrusted') {
    let rejected = false;
    try { await page.goto(process.env.O1_APP_URL + '/auth/login'); }
    catch (error) { rejected = String(error).includes('SEC_ERROR_UNKNOWN_ISSUER'); }
    if (!rejected) throw new Error('untrusted certificate was accepted');
  } else {
    const response = await page.goto(process.env.O1_APP_URL + '/auth/login');
    if (!response || response.status() !== 200) throw new Error('CSR page response failed');
    const source = await response.text();
    if (!/id="app"[^>]*>\s*<\/div>/.test(source)) throw new Error('SSR outage did not return an empty CSR mount');
    await page.locator('input[name="email"]').fill('o1-csr@fixture.test');
    await page.locator('input[name="password"]').fill('fixture-only-password');
    await page.getByRole('button', {name: /sign in|log in/i}).waitFor({state: 'visible'});
    if (await page.locator('input[name="email"]').inputValue() !== 'o1-csr@fixture.test') throw new Error('CSR form unavailable');
    await page.getByRole('link', {name: 'Reset', exact: true}).click();
    await page.waitForURL('**/auth/forgot-password');
    await page.locator('input[name="email"]').waitFor({state: 'visible'});
  }
} finally { await browser.close(); }
'''
        for trusted in (False, True):
            port = _free_port()
            command = ['docker', 'run', '--detach', '--init', '--user', 'pwuser', '--name', f"{context['project']}-o1-browser",
                       '--label', f"com.docker.compose.project={context['project']}", '--network', 'host',
                       '--add-host', 'app.fixture.test:127.0.0.1', '--volume', f"{ROOT / 'node_modules'}:/runner/node_modules:ro"]
            if trusted:
                private = Path(context['private_dir'])
                command += ['--volume', f"{private / 'browser-policies.json'}:/run/fixture/browser-policies.json:ro",
                            '--volume', f"{private / 'root-ca.pem'}:/run/fixture/root-ca.pem:ro",
                            '--env', 'PLAYWRIGHT_FIREFOX_POLICIES_JSON=/run/fixture/browser-policies.json']
            command += ['--workdir', '/runner', browser_image, 'node', '/runner/node_modules/playwright/cli.js',
                        'run-server', '--host', '127.0.0.1', '--port', str(port)]
            browser_id = run.command(command, label='owned O1 Firefox').stdout.strip()
            if not re.fullmatch(r'[0-9a-f]{12,64}', browser_id):
                raise FixtureError('owned browser identity unavailable')
            context['browser_id'] = browser_id
            poll_until(run, 'Firefox readiness', lambda: _browser_ready(port), interval=0.2)
            run.command(('node', '--input-type=module', '-e', script), label='actual strict TLS CSR browser proof', cwd=ROOT,
                        env={**os.environ, 'O1_BROWSER_ENDPOINT': f'ws://127.0.0.1:{port}/', 'O1_APP_URL': context['app_url'],
                             'O1_TLS_CONTROL': 'trusted' if trusted else 'untrusted'})
            run.command(('docker', 'rm', '--force', browser_id), label='remove exact owned Firefox')
            context.pop('browser_id', None)
        report['results']['ssr_outage_actual_csr_with_strict_tls_control'] = 'passed'
        compose('start', 'ssr')

        # Actual consumer stop + genuine encrypted auth-mail enqueue, no mock worker.
        compose('stop', '-t', '3', 'workers')
        php = r'''
require 'vendor/autoload.php';
$c = require 'config/container.php';
$c->get(\Packio\Identity\Application\Port\AuthMailQueuePort::class)->passwordReset('o1-nonexistent@fixture.test', null, null);
echo json_encode($c->get(\Packio\Worker\Api\JobApiFacadeInterface::class)->queueProgress('system:identity'));
'''
        before = json.loads(exec_app('php', '-r', php).stdout)
        if before['pending'] != 1 or before['oldest_ready_age_seconds'] is None:
            raise FixtureError('stopped consumer did not leave observable auth-mail progress')
        time.sleep(2)
        query = r'''require 'vendor/autoload.php'; $c = require 'config/container.php'; echo json_encode($c->get(\Packio\Worker\Api\JobApiFacadeInterface::class)->queueProgress('system:identity'));'''
        after = json.loads(exec_app('php', '-r', query).stdout)
        if after['pending'] != 1 or after['oldest_ready_age_seconds'] < before['oldest_ready_age_seconds'] + 1:
            raise FixtureError('stopped queue ready age did not increase')
        assert_endpoint('ready', 200, {'status': 'ready'})
        compose('start', 'workers')
        def recovered_queue():
            progress = json.loads(exec_app('php', '-r', query).stdout)
            if progress['failed']:
                raise FixtureError('actual auth-mail job failed during recovery')
            return progress['pending'] == 0 and progress['running'] == 0 and progress['oldest_ready_age_seconds'] is None

        poll_until(run, 'real auth-mail consumer recovery', recovered_queue)
        final = json.loads(exec_app('php', '-r', query).stdout)
        if final['failed'] or final['running'] or final['oldest_ready_age_seconds'] is not None:
            raise FixtureError('idle auth-mail queue did not recover')
        report['results']['stopped_consumer_progress_recovery_and_idle'] = 'passed'
        report['results']['all_drills'] = 'passed'
    except Exception as error:
        failure = error if isinstance(error, FixtureError) else FixtureError('unexpected O1 drill failure')
        report['results']['all_drills'] = 'failed'
    finally:
        if context is not None:
            try:
                stop_fixture(context)
                report['results']['owned_cleanup'] = 'passed'
            except Exception:
                failure = FixtureError('owned O1 cleanup failed')
                report['results']['owned_cleanup'] = 'failed'
            if context.get('private_dir'):
                shutil.rmtree(context['private_dir'], ignore_errors=True)
        if sentinel_created:
            try:
                value = sentinel_command('run', '--rm', '--name', sentinel + '-reader', '--entrypoint', 'sh', '--volume', sentinel + ':/o1-sentinel:ro', args.app_image,
                                         '-c', 'cat /o1-sentinel/control')
                if value != 'o1-foreign-control':
                    raise FixtureError('foreign sentinel changed')
                report['results']['foreign_sentinel_untouched'] = 'passed'
            except Exception:
                failure = FixtureError('foreign sentinel control failed')
                report['results']['foreign_sentinel_untouched'] = 'failed'
            finally:
                try:
                    # Exact names belong to this UUID run; a timed-out Docker client
                    # must not leave its short-lived sentinel process behind.
                    subprocess.run(('docker', 'rm', '--force', sentinel + '-writer', sentinel + '-reader'),
                                   stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, timeout=10)
                    sentinel_command('volume', 'rm', sentinel)
                except Exception:
                    failure = FixtureError('owned sentinel cleanup failed')
        report['duration_seconds'] = round(time.monotonic() - started, 3)
        destination = Path(args.report)
        destination.parent.mkdir(parents=True, exist_ok=True)
        destination.write_text(json.dumps(report, indent=2) + '\n')
    if failure:
        print(f'O1 readiness drill failed: {failure}')
        return 1
    print('O1 readiness drills passed')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
