# Docker and HTTP runtimes

Packio has two HTTP runtimes. FrankenPHP is the local development default; the supported production Compose example uses RoadRunner. Both use the same Mezzio application, PostgreSQL-backed job queue, and companion services.

| Runtime | Compose files | HTTP entrypoint | Use |
| --- | --- | --- | --- |
| FrankenPHP | `compose.yaml` | `public/index-worker.php` | Local development default |
| RoadRunner | `compose.yaml` + `compose.roadrunner.yaml` | `public/index-roadrunner.php` | Local alternative; used by standalone production example |

Do not run both file sets in the same Compose project at once. They publish the same `8080:80` mapping and use the same `app` service name. When switching to or from RoadRunner, always include `--build`; this prevents reuse of an `app` container built for the other runtime.

## Production example with RoadRunner

`compose.prod.yaml` is a standalone single-host example. It builds the application,
frontend and SSR from source, installs locked Composer dependencies without dev
packages, and runs four containers: `app`, `workers`, `ssr` and `postgres`.
The `workers` container runs RoadRunner with `config/roadrunner/services.yaml`.
No host `vendor`, Node installation or source bind mounts are needed.

```bash
cp .env.prod.example .env.prod.local
chmod 600 .env.prod.local
openssl rand -hex 32     # use as DATABASE_PASS
openssl rand -base64 32  # use as APP_SECRET_ENCRYPTION_KEY, prefixed with base64:
```

Edit `.env.prod.local`: fill the PostgreSQL password and encryption key, your public
HTTPS URL, branding domains, CORS origin, and working SMTP `MAIL_DSN` (for example
`smtps://user:password@smtp.example.com:465`, with URL-encoded credentials). SMTP
and the queue workers are required to deliver registration verification, password
reset, and other authentication mail. Keep the encryption key across deployments: workers
and HTTP use the same key to decrypt stored credentials. Additional application
settings from `.env.example` can be added to this file.

Malformed, tampered or wrong-key stored secrets are rejected without using the
encrypted value as a credential. Authentication mail jobs fail safely without
sending mail; provider credentials that cannot be decrypted are never sent as
empty authentication tokens or encrypted payloads. This does not rewrite stored
records or rotate keys.
Back up `APP_SECRET_ENCRYPTION_KEY` with the database and keep the same key in HTTP
and worker processes. If decryption fails after a configuration change, restore
the original key; if it is lost, the affected provider accounts require
reauthorization and pending authentication mail must be requested again. Generating
a replacement key does not recover existing encrypted data. Invalid or missing
production key configuration remains a startup error.

```bash
docker compose --env-file .env.prod.local -f compose.prod.yaml config --quiet
docker compose --env-file .env.prod.local -f compose.prod.yaml up -d --build --remove-orphans --wait --wait-timeout 300
curl -fsS http://127.0.0.1:8080/health
docker compose --env-file .env.prod.local -f compose.prod.yaml ps
docker compose --env-file .env.prod.local -f compose.prod.yaml logs -f app workers
```

Use only this Compose file, without `compose.yaml` or `compose.roadrunner.yaml`.
The app listens on container port `80`, published only on host loopback as
`127.0.0.1:${HTTP_PORT:-8080}`. `HTTP_PORT` changes this published HTTP port; it
does not change the HTTPS listener or Caddy's upstream automatically. PostgreSQL
and SSR have no published host ports. The app's RoadRunner RPC listener is also
private.

### Host Caddy reverse proxy

For a single Linux host, [the Caddy example](examples/reverse-proxy/Caddyfile)
terminates HTTPS and forwards only to the app's published loopback HTTP port.
Copy it to the host's Caddy configuration and set its environment inputs before
starting Caddy. `PACKIO_HTTP_UPSTREAM` must match `127.0.0.1:${HTTP_PORT}`; its
default is `127.0.0.1:8080`. `PACKIO_APP_DOMAIN` is the public application host,
and `PACKIO_REGISTRY_DOMAIN` must match `BRAND_REGISTRY_BASE_DOMAIN`. Configure
DNS A/AAAA records for the app host and wildcard registry hosts
(`*.<registry-domain>`) to reach this Linux host. Provision a certificate and
key before starting Caddy; the certificate must cover the app host and
`*.<registry-domain>`. This example loads those files directly and does not
configure automatic certificate issuance or DNS plugins.

`PACKIO_HTTPS_PORT`, `PACKIO_EDGE_BIND`, the two domain variables,
`PACKIO_TLS_CERT`, `PACKIO_TLS_KEY`, and `PACKIO_HTTP_UPSTREAM` configure only
this Caddy edge. They do not set application URLs or Compose port mappings.
Keep `APP_URL` set to the public HTTPS application URL; the app derives its
configured registry URLs from `APP_URL` and `BRAND_REGISTRY_BASE_DOMAIN`.
`HTTP_PORT` controls Compose's loopback publication, while `PACKIO_HTTPS_PORT`
controls Caddy's public listener and forwarded port header.

The edge sets TLS, HSTS, `X-Content-Type-Options: nosniff`, `X-Frame-Options:
DENY`, and a Content Security Policy in report-only mode. Keep it report-only
while checking the real Inertia/SSR pages and Cloudflare Turnstile before any
separate decision about enforcement. Preserve the request's real `Origin` for
CSRF validation. The example removes incoming `Forwarded`, `X-Forwarded-*`,
Cloudflare identity, and `X-Real-IP` values, then sets its own forwarded host,
scheme, port, and client address. Do not trust values supplied by an untrusted
client or use `*` for `TRUSTED_PROXIES`.

Set `TRUSTED_PROXIES` to the exact peer address or narrow CIDR that the app
actually sees for Caddy's upstream connection. In this production-style Compose
bridge, that peer may be the Docker network gateway; do not assume it is a fixed
gateway address. To measure it, send a request through Caddy, then inspect the
app container's established TCP socket to container port `80` in its own network
namespace (`/proc/net/tcp` and `/proc/net/tcp6`). Decode the remote address from
that socket and corroborate it with `docker inspect` network settings for the
app gateway and the proxy's host/network placement. Trust only the measured
proxy peer, not the whole bridge subnet
unless the topology requires it and its full range is reviewed. Re-measure if
the Docker network or proxy placement changes.

The existing app `current_url` value still reflects the HTTP request URI behind
this TLS-terminating proxy, so its Open Graph and canonical URLs may use HTTP.
This is separate from the trusted HTTPS origin used for CSRF checks and the
configured HTTPS registry URLs derived from `APP_URL`. This example does not
normalize the app request URI.

Supply verified image IDs through `PACKIO_PROXY_APP_IMAGE` and
`PACKIO_PROXY_SSR_IMAGE`, or build the two existing targets for this disposable
edge regression:

```bash
docker build -f .docker/Dockerfile.roadrunner --target prod -t packio-release-011-prod:local .
docker build -f .docker/Dockerfile.roadrunner --target prod-ssr -t packio-release-011-prod-ssr:local .
python3 test/Support/Release/proxy.test.py
```

Run the fixture as an ordinary non-root user; Caddy uses that user's UID/GID
and the generated TLS private key stays owner-only. It starts a temporary app,
SSR, PostgreSQL, and Caddy fixture, uses disposable
credentials, and cleans up its own containers, network, and temporary files.
Browser validation of SSR and Turnstile remains a separate required check;
neither running this command nor using the example alone establishes that
browser validation passed. To include it, supply `--browser-endpoint` for a
matching Playwright 1.62.1 remote browser and `--node` for the existing host
Node 24.21.0 executable. The browser check uses Cloudflare's public test keys,
loads its real script/iframe, checks the SSR form and Inertia navigation, and
rejects report-only CSP violations. It requires access to
`https://challenges.cloudflare.com`; it does not mock that service.
`PACKIO_PROXY_APP_IMAGE` and `PACKIO_PROXY_SSR_IMAGE` can select other local
candidate tags without changing the script.

Build from a Git checkout with release tags available (`git fetch --tags` if needed).
The image generates `data/build/release.json` from that checkout during the build
and excludes `.git` from every final image layer. The Docker context is restricted
to build inputs; local sessions, logs, databases, archives, credentials, caches,
and previous frontend builds are excluded. Build from a clean checkout for a
release. On every RoadRunner startup,
`bin/generate-release-metadata.php` runs before migrations: it regenerates metadata
when Git is present and preserves the packaged metadata otherwise. The build
trusts `/app` explicitly for Git because that directory belongs to the runtime
user while the build runs as root. An unreadable Git HEAD fails the build;
packaged metadata without a valid version and commit hash fails startup.
If an older image reports `0.0.1-dev.1+`, rebuild both `app` and `workers` with the
updated Dockerfile; restarting the old image cannot repair its packaged metadata.

In `compose.prod.yaml`, the app service sets `RUN_MIGRATIONS=true`, so pending
migrations and then `RuntimeSettingsCatalogSeed` run on app startup. Workers and
SSR keep migrations disabled, and workers start after application readiness. Do not
run migrations or the seed manually while this app service is starting. Before RoadRunner starts any worker or
maintenance process, the worker container compiles all registered object mappings
into its own owner-only cache. A failed warmup exits the container with an error
before any queue consumer starts. HTTP warms its own cache separately. Restarting
the worker container repeats warmup; recycling an individual queue consumer does not.
RoadRunner's `service` plugin supervises these independent CLI processes:

| Service | Processes |
| --- | --- |
| `worker` (`worker:run`, PostgreSQL queue) | `PACKIO_WORKER_NUM`, default **7** |
| `worker-cleanup` | 1 |
| `email-verification-cleanup` | 1 |
| `password-reset-cleanup` | 1 |
| `source-oauth-refresh` | 1 |

Every process is restarted after exit, including normal `WORKER_MAX_JOBS` recycling.
Queue worker IDs are generated from hostname and PID; `WORKER_ID` is deliberately
cleared so the seven processes cannot share an explicit ID. Maintenance intervals
and queue settings retain their existing environment variables. HTTP worker count
is controlled separately by `RR_NUM_WORKERS`.

Change `PACKIO_WORKER_NUM` in `.env.prod.local`, then apply it with:

```bash
docker compose --env-file .env.prod.local -f compose.prod.yaml up -d --no-deps --force-recreate workers
```

The healthcheck queries RoadRunner's loopback RPC and verifies all expected service
processes. It checks process presence, not job progress. RPC is not published.
RoadRunner allows up to 90 seconds for a service process to stop; Docker allows
120 seconds for the container. Keep one `workers` container so maintenance loops
are not duplicated; increase `PACKIO_WORKER_NUM` to add queue consumers.

This example assumes one application instance. Sessions, rate limits, package
archives and PostgreSQL data use named volumes; caches stay within each container.
Back up the database, package archives and encryption key. `down` preserves volumes;
`down -v` deletes them.

When migrating from the previous production Compose, stop its old `worker`,
`worker-cleanup`, `email-verification-cleanup`, `password-reset-cleanup` and
`source-oauth-refresh` containers using the previous file before starting this
version. `--remove-orphans` removes those obsolete containers.

For subsequent updates, follow the [backup/restore procedure](operations/backup-restore.md)
and [upgrade/rollback runbook](operations/upgrade.md). Close traffic and stop all
writers, including worker maintenance loops, before the consistent database and
archive snapshot or schema changes. Keep the original key in separate protected
storage. Failed migration/startup leaves traffic and consumers closed; binary
rollback requires verified schema/job compatibility, otherwise restore the backup.
Restoring after new writes loses those writes and needs an operator decision.

To verify service supervision in isolation (Docker and Python 3 required):

```bash
docker build -f .docker/Dockerfile.roadrunner --target prod-worker -t packio-worker-service-check:local .
worker_image=$(docker image inspect --format '{{.Id}}' packio-worker-service-check:local)
python3 test/Support/RoadRunner/services.test.py "$worker_image"
```

The smoke test uses a temporary PostgreSQL container and internal network. It
checks process counts, replacement after exit/crash, missing-service health,
graceful shutdown and configurable worker count, then removes its containers/network.

To verify a real job after cold startup and after worker recreation:

```bash
python3 test/Support/RoadRunner/cold-worker.test.py "$worker_image"
```

This test creates a separate temporary PostgreSQL container and internal network,
enqueues `source.create` through the application facade, checks completed jobs and
Source records, and tests that warmup failures stop startup before consumers run.

This is a baseline with a maintenance window, not a rolling deployment setup.
Local PHP configuration overrides do not enter production images; configure this
example through `.env.prod.local`.

## Local command matrix

Every RoadRunner command below uses both Compose files. The complete supported stack is `app`, `worker`, `worker-cleanup`, `email-verification-cleanup`, `password-reset-cleanup`, `source-oauth-refresh`, `ssr`, `postgres`, `mailpit`, and `vite` (plus the opt-in `postgres-test` profile). The one-shot `setup-php` and `setup-node` services install dependencies and exit; the other services wait for them to finish. RoadRunner does not replace or contain the Packio workers: they remain independently supervised services.

| Operation | FrankenPHP | RoadRunner |
| --- | --- | --- |
| Full stack | `docker compose -f compose.yaml up -d --build` | `docker compose -f compose.yaml -f compose.roadrunner.yaml up -d --build` |
| Selective development stack | `docker compose -f compose.yaml up -d --build postgres mailpit vite app worker worker-cleanup email-verification-cleanup password-reset-cleanup source-oauth-refresh ssr` | `docker compose -f compose.yaml -f compose.roadrunner.yaml up -d --build postgres mailpit vite app worker worker-cleanup email-verification-cleanup password-reset-cleanup source-oauth-refresh ssr` |
| Build app | `docker compose -f compose.yaml build app` | `docker compose -f compose.yaml -f compose.roadrunner.yaml build app` |
| Follow app and worker logs | `docker compose -f compose.yaml logs -f app worker worker-cleanup email-verification-cleanup password-reset-cleanup source-oauth-refresh ssr` | `docker compose -f compose.yaml -f compose.roadrunner.yaml logs -f app worker worker-cleanup email-verification-cleanup password-reset-cleanup source-oauth-refresh ssr` |
| Execute CLI command | `docker compose -f compose.yaml exec app php vendor/bin/laminas list` | `docker compose -f compose.yaml -f compose.roadrunner.yaml exec app php vendor/bin/laminas list` |
| Reload HTTP workers | `docker compose -f compose.yaml restart app` | `docker compose -f compose.yaml -f compose.roadrunner.yaml exec app rr reset -c /app/config/roadrunner/.rr.yaml` |
| Stop | `docker compose -f compose.yaml stop` | `docker compose -f compose.yaml -f compose.roadrunner.yaml stop` |
| Remove containers/networks | `docker compose -f compose.yaml down` | `docker compose -f compose.yaml -f compose.roadrunner.yaml down` |
| Remove local PostgreSQL data | `docker compose -f compose.yaml down -v` | `docker compose -f compose.yaml -f compose.roadrunner.yaml down -v` |
| Smoke check | `curl -fsS http://localhost:8080/health` | `curl -fsS http://localhost:8080/health` |

The application is available at `http://localhost:8080/`; Mailpit is at `http://localhost:8025/`; SSR health is at `http://localhost:13714/health`.

Use `docker compose ... ps` to verify that every shared companion service is healthy. `docker compose ... logs` writes application, job-worker, maintenance, SSR, and RoadRunner supervisor output to the terminal; do not expect a container-local log file.

## Vite and SSR development

Local Laminas development mode loads Vite assets from `http://localhost:5173/@vite/client`; keep `vite` running while changing frontend code. If the browser cannot reach that URL, verify the service rather than changing the HTTP runtime:

```bash
docker compose -f compose.yaml ps vite
docker compose -f compose.yaml logs -f vite
```

The SSR service runs `node data/ssr/ssr.mjs`. `setup-node` builds the bundle only when it is missing, so remove it first. Rebuild the bundle and restart the shared service after SSR runtime changes (with a host Node install, `npm run build:ssr` works too):

```bash
rm -f data/ssr/ssr.mjs && docker compose -f compose.yaml run --rm setup-node
docker compose -f compose.yaml restart ssr
curl -fsS http://localhost:13714/health
```

`ssr` bind-mounts the project in local Compose, so the rebuilt bundle is visible without rebuilding the image. The same SSR service is used by both HTTP runtime variants.

## Packio worker services

In the development Compose, the `worker` container runs `worker:run`; the maintenance containers run cleanup or Source OAuth refresh loops. They remain PHP CLI services and are unchanged by the RoadRunner override. Authentication emails are delivered by `worker`, so this service must be running even when no package synchronization is needed. The web and worker processes must share the same `APP_SECRET_ENCRYPTION_KEY` and mail configuration.

| Variable | Default | Purpose |
| --- | --- | --- |
| `WORKER_TIMEOUT` | `30` seconds | Job queue polling timeout |
| `WORKER_MEMORY_LIMIT` | `512M` | Restart threshold for peak job-worker memory |
| `WORKER_MAX_JOBS` | `100` | Jobs before a job worker exits; `0` is unlimited |
| `WORKER_ID` | empty | Optional explicit job-worker identifier |
| `WORKER_CLEANUP_INTERVAL_SECONDS` | `3600` seconds | Old job/log cleanup interval |
| `WORKER_CLEANUP_RETENTION_DAYS` | `30` days | Retention for jobs and job logs |
| `EMAIL_VERIFICATION_CLEANUP_INTERVAL_SECONDS` | `3600` seconds | Expired verification-code cleanup interval |
| `PASSWORD_RESET_CLEANUP_INTERVAL_SECONDS` | `3600` seconds | Password-reset cleanup interval |
| `SOURCE_OAUTH_REFRESH_INTERVAL_SECONDS` | `86400` seconds | Source OAuth maintenance interval |

Local bind-mounted worker services run with `PACKIO_DOCKER_UID` and `PACKIO_DOCKER_GID` (both default to `1000`) so generated files belong to the host user. On Linux, set them to the local account IDs and rebuild a worker image when the build arguments change:

```bash
PACKIO_DOCKER_UID=$(id -u) PACKIO_DOCKER_GID=$(id -g) docker compose -f compose.yaml up -d --build worker
```

Run a one-off migration or application CLI command with the selected runtime's `app` service; migration startup already retries while PostgreSQL is becoming ready:

```bash
docker compose -f compose.yaml run --rm app php vendor/bin/laminas cycle:migration:run -n
docker compose -f compose.yaml run --rm app php vendor/bin/laminas list
```

## RoadRunner configuration and tuning

The alternative image uses `.docker/Dockerfile.roadrunner`, `config/roadrunner/.rr.yaml`, and `.docker/entrypoint-roadrunner.sh`. RoadRunner listens only on container port `80`. Its RPC (`127.0.0.1:6001`) and status (`127.0.0.1:2114`) listeners are not published to the host.

| Variable | Default | Unit | Safe production starting point | Meaning |
| --- | ---: | --- | --- | --- |
| `RR_NUM_WORKERS` | `2` | processes | CPU-aware: 2, then measure | HTTP PHP worker-pool size |
| `RR_MAX_JOBS` | `500` | requests per process | 500 | Recycle a PHP HTTP worker after this many requests |
| `RR_MAX_QUEUE_SIZE` | `256` | requests | 256 | Bounded pending HTTP request queue |
| `RR_MAX_WORKER_MEMORY_MB` | `256` | MiB | 256, based on measured peak | Recycle when a worker exceeds this memory limit |
| `RR_ALLOCATE_TIMEOUT` | `60s` | Go duration | `60s` | Maximum time to allocate a PHP worker |
| `RR_DESTROY_TIMEOUT` | `60s` | Go duration | `60s` | Grace period while stopping a PHP worker |
| `RR_EXEC_TTL` | `60s` | Go duration | Match request SLO | Maximum execution time before supervisor action |
| `RR_RELAY_TIMEOUT` | `60s` | Go duration | `60s` | Goridge relay timeout |
| `RR_MAX_REQUEST_SIZE_MB` | `32` | MiB | Match upload policy | Maximum accepted HTTP request size |

`RR_*` controls only RoadRunner's HTTP pool. It is intentionally distinct from `WORKER_TIMEOUT`, `WORKER_MEMORY_LIMIT`, `WORKER_MAX_JOBS`, and `WORKER_ID`, which control Packio's PostgreSQL job consumer (`worker:run`). Do not tune one namespace expecting it to affect the other.

RoadRunner PHP worker stdout is reserved for the Goridge protocol. PHP/application diagnostics must be written to stderr. Safe diagnostics are:

```bash
docker compose -f compose.yaml -f compose.roadrunner.yaml exec app rr --version
docker compose -f compose.yaml -f compose.roadrunner.yaml exec app curl -fsS http://127.0.0.1:2114/
docker compose -f compose.yaml -f compose.roadrunner.yaml logs --tail=200 app
curl -fsS http://localhost:8080/health
```

Do not use `printenv`, dump request payloads, cookies, authorization headers, session IDs, or environment files for diagnosis. RoadRunner worker spawn/recycle and supervisor failures are emitted on container stdout/stderr. RoadRunner's built-in access logger is disabled because its unredacted request URI can contain OAuth codes, reset tokens, or signed URL parameters. Packio instead writes a query-free access event with method, path, status, and duration through its stderr logger. Enable the built-in logger only behind a reviewed query-redacting log pipeline.

## HTTP, proxy, and static-file policy

Packio retains CORS, CSRF, authentication, rate limiting, host/subdomain routing, and trusted-proxy policy in Mezzio. With RoadRunner behind a TLS-terminating reverse proxy, set `TRUSTED_PROXIES` to the explicit proxy addresses/ranges and forward the standard host, scheme, port, and client-address headers. Do not trust arbitrary client-supplied forwarded headers.

RoadRunner serves only allowlisted browser asset extensions rooted at `/app/public` (`.css`, `.ico`, `.js`, `.json`, `.png`, `.svg`, and `.woff2`). Dotfiles, PHP/source files, backups, configuration files, and traversal targets are not static matches and fall through to Mezzio rather than being exposed as files. Terminate TLS at the external reverse proxy for RoadRunner. FrankenPHP/Caddy may terminate TLS in its own topology.

The container healthcheck first uses the loopback RoadRunner status endpoint and then the application `/health` endpoint. The public `/health` endpoint is the readiness and deployment signal; status is diagnostic only.

## Migrations and graceful operations

Both app entrypoints use the same migration helper. By default, startup retries migrations while PostgreSQL becomes ready. After successful migrations, it runs `cycle:seed:run RuntimeSettingsCatalogSeed -n` to add missing runtime settings without overwriting existing admin choices. A migration or seed failure prevents the HTTP server from starting. Set `RUN_MIGRATIONS=false` to skip both migrations and this seed; `MIGRATION_ATTEMPTS` defaults to `12` and `MIGRATION_RETRY_SECONDS` to `5` seconds.

For source changes in local RoadRunner development, run `rr reset -c /app/config/roadrunner/.rr.yaml` (the matrix command above) after updating code. In production, publish and prepare caches/schema before restarting services. For an intentional RoadRunner recycle or graceful restart, let the service manager send its configured stop signal, honor at least `RR_DESTROY_TIMEOUT`, and wait for replacement workers plus `/health` to become ready. Do not kill the container during an in-flight request unless the configured grace period has expired.

## Production images

The supported production images use the RoadRunner Dockerfile. They install locked
PHP dependencies and build client, API documentation, and SSR assets inside Docker.
Production must not use a source bind mount:

```bash
python3 test/Support/Release/image.test.py --report /tmp/packio-images.json
```

The local build mode uses unique tags and a disposable tracked-source context,
inserts harmless canaries, checks every exported layer, verifies dependencies and
assets, and starts the final images without source mounts. Keep the reported
immutable image IDs to verify the same artifacts again without building or pulling:

```bash
python3 test/Support/Release/image.test.py --verify-only \
  --app-image "$app_image" --worker-image "$worker_image" --ssr-image "$ssr_image" \
  --expected-sha "$(git rev-parse HEAD)" --report /tmp/packio-verification.json
python3 test/Support/RoadRunner/cold-worker.test.py "$worker_image"
python3 test/Support/RoadRunner/services.test.py "$worker_image"
docker save -o /tmp/packio-release-images.tar "$app_image" "$worker_image" "$ssr_image"
```

Set the three shell variables to the image IDs in the build report. Verification
resolves supplied local refs before checking, rejects missing or mismatched app/worker
release metadata and dirty builds, and reports layer/startup results. SSR source identity is verified against the BuildKit provenance in its exported OCI
image index: the attestation subject must match the image manifest and its source
revision must match the candidate SHA. The complete SSR asset tree must also match
both clean app and worker copies, and the SSR startup/health scripts must match the
clean worker copies. Dirty metadata anchors are rejected before these comparisons.
Missing or mismatched provenance or packaged SSR contents fails verification. Build with BuildKit and a Docker containerd image store that preserves
attestations; prefetch `postgres:17-alpine` before verify-only startup checks.
Missing Docker or Compose fails the check. Preserve the export and report together;
a registry digest and publication bundle remain separate release requirements.
The harness removes only its own runtime resources; built images remain available.
The RoadRunner image runs as a
non-root user, uses the pinned RoadRunner binary declared in its Dockerfile,
and includes PostgreSQL support. `.docker/Dockerfile` and
`.docker/Dockerfile.worker` remain development images; their incomplete `prod`
targets have been removed. `.docker/Dockerfile.ssr` builds its own SSR bundle for
development; its source bind mount in `compose.yaml` still exposes local changes.
