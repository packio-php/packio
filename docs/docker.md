# Docker and the HTTP runtime

Packio runs on RoadRunner, in development and in production. The HTTP entrypoint is `public/index-roadrunner.php`; a second RoadRunner process supervises the queue workers and maintenance loops.

| Compose file | Use |
| --- | --- |
| `compose.yaml` | Local development: the checkout is bind-mounted, with Vite and Mailpit |
| `deploy/` | Production: the published images, see [self-hosting.md](self-hosting.md), or [your own build](#production-from-a-source-build) |

## Production from a source build

Most users should follow [self-hosting.md](self-hosting.md), which uses the published images.
To run your own build, build the three images from a Git checkout and start them with the
same `deploy/` files:

```bash
git clone https://github.com/packio-php/packio.git packio
cd packio
for pair in prod:packio prod-worker:packio-worker prod-ssr:packio-ssr; do
  docker build -f .docker/Dockerfile.roadrunner --target "${pair%%:*}" \
    -t "ghcr.io/packio-php/${pair#*:}:local" .
done
cd deploy
cp .env.example .env
chmod 600 .env
```

The build needs Git metadata; a source archive is not a substitute for a Git checkout.
It installs locked Composer dependencies without dev packages and builds the frontend and
SSR inside Docker: no host `vendor`, Node installation or source bind mounts are needed.

In `.env` set `PACKIO_VERSION=local`, so Compose runs the images you just built instead of
pulling a release, and fill in the other values as the [self-hosting guide](self-hosting.md)
describes: the domain, the PostgreSQL password, the encryption key and a working SMTP
`MAIL_DSN` (for example `smtps://user:password@smtp.example.com:465`, with URL-encoded
credentials). SMTP and the queue workers are required to deliver registration verification,
password reset, and other authentication mail. Keep the encryption key across deployments:
workers and HTTP use the same key to decrypt stored credentials. Any other setting from
[ENV.md](../ENV.md) can be added to the same file.

The stack runs `app`, `workers` and `postgres`, plus `ssr` when `COMPOSE_FILE` includes
`compose.ssr.yaml`. The `workers` container runs RoadRunner with
`config/roadrunner/services.yaml`.

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
docker compose config --quiet
docker compose up -d --remove-orphans --wait --wait-timeout 300
curl -fsS http://127.0.0.1:8080/health
docker compose ps
docker compose logs -f app workers
```

Run these in `deploy/`; do not combine its files with the development `compose.yaml`.
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

This example is the edge of the [product check](testing.md#product-check-on-final-images):
it runs the built images behind this Caddyfile and sends forged forwarding
headers, foreign origins and repeated logins through it. The check also loads
the login page with Turnstile on Cloudflare's public test keys and rejects
report-only CSP violations.

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

In `deploy/compose.yaml`, the app service sets `RUN_MIGRATIONS=true`, so pending
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

Change `PACKIO_WORKER_NUM` in `.env`, then apply it with:

```bash
docker compose up -d --no-deps --force-recreate workers
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

Packio does not change PHP session settings at runtime. The images set them in
`.docker/php/session.ini`: files in `/app/data/sessions`, removed after 2 hours
without activity. When running Packio outside these images, set `session.save_path`
to a directory used only by Packio and `session.gc_maxlifetime` to at least `7200`
in `php.ini`; with the PHP default of `1440` a user can be signed out after
24 minutes without activity.

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

To verify service supervision in isolation (Docker required):

```bash
docker build -f .docker/Dockerfile.roadrunner --target prod-worker -t packio-worker-service-check:local .
worker_image=$(docker image inspect --format '{{.Id}}' packio-worker-service-check:local)
sh test/Support/RoadRunner/services.test.sh "$worker_image"
```

The smoke test uses a temporary PostgreSQL container and internal network. It
checks process counts, replacement after exit/crash, missing-service health,
graceful shutdown and configurable worker count, then removes its containers/network.

To verify a real job after cold startup and after worker recreation (Docker and `jq` required):

```bash
sh test/Support/RoadRunner/cold-worker.test.sh "$worker_image"
```

This test creates a separate temporary PostgreSQL container and internal network,
enqueues `source.create` through the application facade, checks completed jobs and
Source records, and tests that warmup failures stop startup before consumers run.

This is a baseline with a maintenance window, not a rolling deployment setup.
Local PHP configuration overrides do not enter production images; configure this
setup through `deploy/.env`.

## Try the development stack from source

Try Packio from a source checkout with the development stack. Most users should use the published images instead, see [self-hosting.md](self-hosting.md#try-it-locally). It serves plain HTTP on `localhost` and is not a production setup.

1. Requirements: Linux on amd64 (macOS is not guaranteed), Git, curl, and Docker with Compose. Nothing else is installed on the host. Create a GitHub [fine-grained personal access token](https://github.com/settings/personal-access-tokens/new) before you start: set Repository access to "Public repositories", add no permissions, and choose a short expiry. The token can only read public data; a classic token with no scopes selected works too.
2. Clone the repository and start the stack:

   ```bash
   git clone https://github.com/packio-php/packio.git
   cd packio
   cp .env.example .env
   ```

   If your user ID (`id -u`) is not 1000, set `PACKIO_DOCKER_UID` and `PACKIO_DOCKER_GID` in `.env` now. Then start the stack and wait until the app answers:

   ```bash
   docker compose -f compose.yaml up -d --build
   until curl -fsS http://localhost:8080/health; do sleep 2; done
   ```

   One `up` command also installs PHP and Node dependencies and builds the SSR bundle. The first start compiles PHP for the image and takes about 15 minutes; later starts take a few seconds. If the `until` loop does not finish, press Ctrl-C and check `docker compose logs app`. The workers start after `app` has run the migrations and is healthy.
3. Open `http://packio.localhost:8080` and register. The verification code arrives in Mailpit at `http://localhost:8025`.
4. Create an organization, for example `acme`.
5. Open Sources, click Add source, and choose GitHub. Under "or enter token manually", paste your GitHub token and click Save provider access, then Continue to add source. The list shows only repositories of the token owner, so pick one of your own public repositories that has a `composer.json` and version tags; if you have none, fork a small Composer package and untick "Copy the main branch only" in GitHub's fork dialog so the fork keeps its tags. Click Connect. A read-only token cannot create webhooks, so Packio shows a warning and does not follow new pushes on its own; synchronization from the source page still works. The source synchronizes in the background; its page shows the sync status and the packages found.
6. Open Tokens (`/organization/acme/tokens`), enter a name, and click Issue token. Copy the token.
7. The Registry Setup page (`/organization/acme/registry/setup`) shows the registry host and repository URL; for `acme` the host is `acme.packio.localhost:8080`. In an empty project folder, run Composer in Docker (Linux; `--network host` lets the container reach the host's port 8080). Any local Composer 2 works too.

   ```bash
   mkdir demo && cd demo
   composer() { docker run --rm $([ -t 0 ] && echo -it) --user "$(id -u):$(id -g)" --network host -v "$PWD":/app -w /app composer:2.8 "$@"; }
   composer init --no-interaction --name acme/demo
   composer config secure-http false   # only for this local trial: the registry is plain HTTP
   composer config --auth http-basic.acme.packio.localhost:8080 token '<registry-token>'
   composer config repositories.packio composer '<repository URL from the Registry page>'
   composer require <vendor/package>
   ```
8. Stop and remove everything, including the database volume:

   ```bash
   docker compose -f compose.yaml down -v
   ```

## Local command matrix

The complete stack is `app`, `workers`, `ssr`, `postgres`, `mailpit`, and `vite` (plus the opt-in `postgres-test` profile). The one-shot `setup-php` and `setup-node` services install dependencies and exit; the other services wait for them to finish. `app`, `workers` and `setup-php` share one image, the `dev` target of `.docker/Dockerfile.roadrunner`.

| Operation | Command |
| --- | --- |
| Full stack | `docker compose -f compose.yaml up -d --build` |
| Build the PHP image | `docker compose -f compose.yaml build app` |
| Follow app and worker logs | `docker compose -f compose.yaml logs -f app workers ssr` |
| Execute CLI command | `docker compose -f compose.yaml exec app php vendor/bin/laminas list` |
| Reload HTTP workers after a code change | `docker compose -f compose.yaml exec app rr reset -c /app/config/roadrunner/.rr.yaml` |
| Reload queue workers after a code change | `docker compose -f compose.yaml restart workers` |
| Stop | `docker compose -f compose.yaml stop` |
| Remove containers/networks | `docker compose -f compose.yaml down` |
| Remove local PostgreSQL data | `docker compose -f compose.yaml down -v` |
| Smoke check | `curl -fsS http://localhost:8080/health` |

The application is available at `http://localhost:8080/`; Mailpit is at `http://localhost:8025/`; SSR health is at `http://localhost:13714/health`.

Use `docker compose ... ps` to verify that every service is healthy. `docker compose ... logs` writes application, job-worker, maintenance, SSR, and RoadRunner supervisor output to the terminal; do not expect a container-local log file.

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

`ssr` runs the stock Node image with the project bind-mounted, so the rebuilt bundle is visible after a restart.

## Packio worker services

In the development Compose, the `workers` container runs RoadRunner with `config/roadrunner/services.yaml`, as in production: `PACKIO_WORKER_NUM` queue workers (`worker:run`, default 2 in development) and one process for each cleanup or Source OAuth refresh loop. Authentication emails are delivered by the queue workers, so this service must be running even when no package synchronization is needed. The web and worker processes must share the same `APP_SECRET_ENCRYPTION_KEY` and mail configuration.

| Variable | Default | Purpose |
| --- | --- | --- |
| `WORKER_TIMEOUT` | `30` seconds | Job queue polling timeout |
| `WORKER_MEMORY_LIMIT` | `512M` | Restart threshold for peak job-worker memory |
| `WORKER_MAX_JOBS` | `100` | Jobs before a job worker exits; `0` is unlimited |
| `WORKER_CLEANUP_INTERVAL_SECONDS` | `3600` seconds | Old job/log cleanup interval |
| `WORKER_CLEANUP_RETENTION_DAYS` | `30` days | Retention for jobs and job logs |
| `AUDIT_LOG_RETENTION_DAYS` | `180` days | Retention for audit log entries; `worker-cleanup` prunes them in the same loop |
| `EMAIL_VERIFICATION_CLEANUP_INTERVAL_SECONDS` | `3600` seconds | Expired verification-code cleanup interval |
| `PASSWORD_RESET_CLEANUP_INTERVAL_SECONDS` | `3600` seconds | Password-reset cleanup interval |
| `SOURCE_OAUTH_REFRESH_INTERVAL_SECONDS` | `86400` seconds | Source OAuth maintenance interval |

The bind-mounted services run with `PACKIO_DOCKER_UID` and `PACKIO_DOCKER_GID` (both default to `1000`) so generated files belong to the host user. On Linux, set them to the local account IDs and rebuild the PHP image when the build arguments change:

```bash
PACKIO_DOCKER_UID=$(id -u) PACKIO_DOCKER_GID=$(id -g) docker compose -f compose.yaml up -d --build
```

Run a one-off migration or application CLI command with the `app` service; migration startup already retries while PostgreSQL is becoming ready:

```bash
docker compose -f compose.yaml run --rm app php vendor/bin/laminas cycle:migration:run -n
docker compose -f compose.yaml run --rm app php vendor/bin/laminas list
```

## RoadRunner configuration and tuning

The images use `.docker/Dockerfile.roadrunner`, `config/roadrunner/.rr.yaml`, and `.docker/entrypoint-roadrunner.sh`. RoadRunner listens only on container port `80`. Its RPC (`127.0.0.1:6001`) and status (`127.0.0.1:2114`) listeners are not published to the host.

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

`RR_*` controls only RoadRunner's HTTP pool. It is intentionally distinct from `WORKER_TIMEOUT`, `WORKER_MEMORY_LIMIT`, and `WORKER_MAX_JOBS`, which control Packio's PostgreSQL job consumer (`worker:run`). Do not tune one namespace expecting it to affect the other.

RoadRunner PHP worker stdout is reserved for the Goridge protocol. PHP/application diagnostics must be written to stderr. Safe diagnostics are:

```bash
docker compose -f compose.yaml exec app rr --version
docker compose -f compose.yaml exec app curl -fsS http://127.0.0.1:2114/
docker compose -f compose.yaml logs --tail=200 app
curl -fsS http://localhost:8080/health
```

Do not use `printenv`, dump request payloads, cookies, authorization headers, session IDs, or environment files for diagnosis. RoadRunner worker spawn/recycle and supervisor failures are emitted on container stdout/stderr. RoadRunner's built-in access logger is disabled because its unredacted request URI can contain OAuth codes, reset tokens, or signed URL parameters. Packio instead writes a query-free access event with method, path, status, and duration through its stderr logger. Enable the built-in logger only behind a reviewed query-redacting log pipeline.

## HTTP, proxy, and static-file policy

Packio retains CORS, CSRF, authentication, rate limiting, host/subdomain routing, and trusted-proxy policy in Mezzio. With RoadRunner behind a TLS-terminating reverse proxy, set `TRUSTED_PROXIES` to the explicit proxy addresses/ranges and forward the standard host, scheme, port, and client-address headers. Do not trust arbitrary client-supplied forwarded headers.

RoadRunner serves only allowlisted browser asset extensions rooted at `/app/public` (`.css`, `.ico`, `.js`, `.json`, `.png`, `.svg`, and `.woff2`). Dotfiles, PHP/source files, backups, configuration files, and traversal targets are not static matches and fall through to Mezzio rather than being exposed as files. Terminate TLS at the external reverse proxy.

The container healthcheck first uses the loopback RoadRunner status endpoint and then the application `/health` endpoint. The public `/health` endpoint is the readiness and deployment signal; status is diagnostic only.

## Migrations and graceful operations

The app entrypoint runs the migration helper. By default, startup retries migrations while PostgreSQL becomes ready. After successful migrations, it runs `cycle:seed:run RuntimeSettingsCatalogSeed -n` to add missing runtime settings without overwriting existing admin choices. A migration or seed failure prevents the HTTP server from starting. Set `RUN_MIGRATIONS=false` to skip both migrations and this seed; `MIGRATION_ATTEMPTS` defaults to `12` and `MIGRATION_RETRY_SECONDS` to `5` seconds.

For source changes in local development, run `rr reset -c /app/config/roadrunner/.rr.yaml` (the matrix command above) after updating code. In production, publish and prepare caches/schema before restarting services. For an intentional RoadRunner recycle or graceful restart, let the service manager send its configured stop signal, honor at least `RR_DESTROY_TIMEOUT`, and wait for replacement workers plus `/health` to become ready. Do not kill the container during an in-flight request unless the configured grace period has expired.

## Production images

The supported production images use the RoadRunner Dockerfile. They install locked
PHP dependencies and build client, API documentation, and SSR assets inside Docker.
Production must not use a source bind mount:

```bash
sh test/Support/Release/image.test.sh "$app_image" "$worker_image" "$ssr_image" "$(git rev-parse HEAD)"
sh test/Support/RoadRunner/cold-worker.test.sh "$worker_image"
sh test/Support/RoadRunner/services.test.sh "$worker_image"
docker save -o /tmp/packio-release-images.tar "$app_image" "$worker_image" "$ssr_image"
```

Set the three shell variables to already built local images; nothing is built or pulled.
`image.test.sh` checks that:

- the app and worker images run as a non-root user and carry the locked dependencies, the
  built assets, the migrations and the required PHP extensions;
- their release metadata names the given commit and is not dirty;
- no exported layer contains a forbidden path, and the uncompressed layers stay under the
  ceiling of the target. Both lists are at the top of the script; a ceiling is raised only
  deliberately, in a separate change;
- the SSR bundle ships only in the SSR image, and its startup and health scripts match the
  worker copies;
- the BuildKit provenance stored with each image names the image manifest, the build target
  and the given commit. The SSR image has no release metadata, so this is its only source check.

Build with `--provenance=true` and a Docker containerd image store that preserves
attestations, as the `runtime` job in `.github/workflows/verify.yml` does. Startup of the
same images is covered by the [product check](testing.md#product-check-on-final-images).

The RoadRunner image runs as a
non-root user, uses the pinned RoadRunner binary declared in its Dockerfile,
and includes PostgreSQL support. Its `dev` target is the development image used
by `compose.yaml`; it is not a production target.
