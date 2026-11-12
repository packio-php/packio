# Health, readiness and queue progress

`GET /health` is process liveness: HTTP 200 with `{"status":"OK"}`. It remains available when PostgreSQL is down. `GET /ready` is a traffic gate: exactly `{"status":"ready"}` with HTTP 200, or `{"status":"not_ready"}` with HTTP 503. HEAD returns the same status without a body. Probe responses do not contain dependency hosts, paths, credentials or failure reasons; the readiness result is cached per worker for at most one second.

An early interceptor selects only GET and HEAD requests to the named `health` and `ready` routes. It runs a closed native routing, registry restriction, implicit HEAD and dispatch branch before runtime CORS, session, authentication and page middleware. The branch shares the application's RadixRouter; its HEAD adapter is local to probes. This lets probes run without initializing database-dependent main services. Other requests enter the original main pipeline unchanged: runtime CORS precedes body parsing and normal routing, registry restriction follows routing, and implicit HEAD remains after authentication and Inertia middleware. The registry-host restriction prevents probe handlers from running on the registry host, preserving query strings in redirects or Inertia location responses.

Registry-host OPTIONS requests follow the original CORS-before-registry order. With runtime CORS enabled and an allowed cross-origin request, native CORS can return 204 with CORS headers before registry restriction, including for probe paths. With CORS disabled, the request continues to the registry-host restriction. Ordinary UI/API requests retain the original raw-router and authentication behavior, including the original handling of HEAD. Same-origin OPTIONS requests continue to native implicit OPTIONS, which returns 200 with `Allow: GET,HEAD` for the GET probe routes without CORS headers.

Readiness checks PostgreSQL connectivity, the executed migration ledger against the installed migration files, the compiled ORM schema and its mapped database tables/columns, and the complete runtime settings seed catalog. Archive storage configuration is validated using the application's existing validator. For local storage, the archive directory must exist and allow reading, writing and directory search. S3 does not require a local archive directory; readiness does not make S3 network calls or verify bucket availability and credentials against the provider. Required cache locations, or their nearest existing parent for lazy paths, must grant the same permissions. The probe never creates files, warms caches, sends mail or calls a provider. It uses an isolated read-only database connection in a short-lived PHP process; an absolute watchdog terminates a blocked probe before the two-second request budget, including stalled connection/DNS/wire calls. It does not reuse or alter request/worker transactions.

`ReadyHandler` only builds the HTTP response: it calls `ReadinessChecker`, which launches `bin/readiness-probe.php` and delegates the checks to `ReadinessProbe`. The checker starts its total 1.8-second deadline before collecting configuration, sends that configuration only through stdin, and runs the CLI from the project root so relative paths resolve consistently. The CLI loads only Composer autoload and the probe creates its own read-only PDO connection; it does not bootstrap the HTTP application or use the worker connection.

The production Compose app healthcheck passes `--ready` to `/usr/local/bin/roadrunner-healthcheck.sh`; dependent workers start after the app is ready. Running that script without arguments checks RoadRunner process status and `/health`. A database outage closes the readiness gate while process liveness stays healthy. Docker does not restart a running container merely because its healthcheck becomes unhealthy; avoid external restart policies that turn readiness failures into restart loops. A reverse proxy or deployment system must explicitly use `/ready` to remove an instance from traffic: Compose health alone does not stop requests to a published port. `APP_READINESS_URL` and `APP_HEALTHCHECK_URL` override the respective internal probe URLs.

The existing Inertia renderer falls back to CSR when SSR is unavailable. Readiness permits that degradation only with the actual browser fallback drill described below: an empty HTML mount must become an interactive login form and navigate to password recovery with JavaScript. The probe does not continuously fetch every client asset; check client asset delivery when diagnosing a browser failure.

## Queue progress

The admin-only `/admin/jobs` page adds global `queue_progress` and reserved `system:identity` `auth_mail_progress`; existing `status_counts` remain unchanged. Ordinary organization actors cannot access the admin summary or system/foreign jobs. No public health endpoint exposes queue data.

| Field | Meaning |
| --- | --- |
| `pending` | All pending jobs, including future schedules |
| `running` | Running jobs |
| `failed` | Failed jobs |
| `retrying` | Pending jobs with `attempt_count > 0` |
| `stuck` | Running jobs with `started_at` older than the Worker's existing 30-minute recovery timeout |
| `oldest_ready_age_seconds` | Age since the oldest pending `scheduled_at` that is due; null when no pending job is ready |

An idle queue has zero counts and a null ready age. It is healthy. Worker process health is independent from these queue counts: a running process can stop making progress, while an idle worker has nothing to complete. Compare successive observations and the workload's normal completion times before alerting. A scheduled retry can remain pending without increasing ready age until it becomes due.

If ready age grows, check consumer process health and logs, job retries/errors, database access and local archive/cache permissions. Restore the dependency or consumer, then confirm that ready age/counts recover. Investigate stuck jobs before using existing admin restart/cancel actions; preserve idempotency and existing deletion safeguards. Do not invent a different lease timeout or treat a failed count retained from past jobs as a failed worker.

The summary uses one aggregate query, without hydrating job rows. A local PostgreSQL 17 rehearsal with 50,007 rows measured a global sequential aggregate at 366.249 ms (803 shared buffer hits) and an organization-scoped aggregate at 0.993 ms using the existing `(organization_id, type, idempotency_key)` index. Exact global counts examine all jobs; the index does not eliminate that work. These measurements describe that fixture, not an SLA or an alert threshold. Re-measure on the installation's retained job volume before increasing polling frequency; use existing retention operations according to the job lifecycle.

## Isolated failure drill

Run the supported PHP feature regressions sequentially, then static analysis:

```sh
php vendor/bin/phpunit test/Unit/Application/Config/OperationalProbePipelineTest.php
php vendor/bin/phpunit test/Integration/Application/Infrastructure/Readiness/ReadinessProbeProcessTest.php
bin/with-postgres-test php vendor/bin/phpunit test/Feature/Operations/ReadinessFeatureTest.php
bin/with-postgres-test php vendor/bin/phpunit test/Feature/Operations/QueueProgressFeatureTest.php
composer phpstan
```

The operational pipeline regression builds and exercises the real application with no database available, proving that route discovery, cached routes, and GET/HEAD probe handling do not resolve the database or main-stack services. `ReadinessFeatureTest` remains the regression against real readiness dependencies and database outcomes.

`test/Support/Release/readiness.test.py` accepts `--app-image`, `--worker-image`, `--ssr-image`, `--expected-sha`, `--source-identity`, `--build-record` and `--report`. Supply immutable IDs from a frozen, disposable local test snapshot and its immutable build identity/record (base HEAD, full patch hash, file manifest, frozen context and exact image IDs). The explicit dirty-worktree path requires dirty test metadata and proves all current shipped runtime inputs still match the frozen build; the standard clean path remains strict. This is test evidence, not verification of a clean release candidate. The script reuses the isolated product fixture: it stops/restarts only its own PostgreSQL, SSR and worker services, checks traffic readiness against separate process health, verifies strict-TLS Firefox CSR behavior with an untrusted-certificate control, and observes an actual encrypted auth-mail job while its consumer is stopped and after recovery. A nonexistent fixture account avoids external mail. The runner verifies a separate sentinel and removes its own resources on success/failure. It does not operate on an existing installation.

Use the saved stage report to inspect timings, image IDs, snapshot identity and cleanup results. Backup/restore, upgrade rehearsal and limiter cleanup are separate operations; this readiness check does not validate recovery of private package data.
