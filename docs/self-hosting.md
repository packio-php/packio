# Self-hosting Packio

Run Packio on your own server from ready-made Docker images. You do not clone the repository or build anything.

The images are `ghcr.io/packio-php/packio`, `ghcr.io/packio-php/packio-worker` and `ghcr.io/packio-php/packio-ssr`. Compose downloads them for you. The images are published starting with 0.3.0; use the version you find on the releases page. The downloads are about 85 MiB for the application image, 115 MiB for the worker image and 70 MiB for the SSR image.

There are two ways to start:

- [Try it locally](#try-it-locally): a trial on your own machine in about a minute. No domain, HTTPS or mail account needed.
- [Install on a server](#requirements-for-a-server): the real installation with a domain, HTTPS and outgoing mail.

## Try it locally

To look around without a domain, use the local variant. It runs over plain HTTP and catches all mail in Mailpit. You need Linux on amd64, Docker with Compose v2 and curl; no domain, certificate or SMTP account. Do not use it on a server.

1. Find the latest version on the [releases page](https://github.com/packio-php/packio/releases) and replace `X.Y.Z` with it (without the `v`, for example `0.3.0`). In a new, empty folder, download the deploy files and start:

   ```bash
   mkdir packio-trial && cd packio-trial
   curl -fsSL https://github.com/packio-php/packio/archive/refs/tags/vX.Y.Z.tar.gz | tar -xz --strip-components=2 --wildcards '*/deploy/*'
   cp .env.local.example .env
   docker compose up -d --wait
   ```

   Never run this in the folder of a server installation: `cp .env.local.example .env` would overwrite your real `.env`, including the encryption key.

2. The command returns after about a minute, once the database is prepared. `docker compose ps` must then show four containers, `app`, `postgres`, `workers` and `mailpit`, all `healthy`.
3. Open `http://packio.localhost:8080` and register. The verification code arrives in Mailpit at `http://localhost:8025`; enter it to confirm your address.
4. Create an organization and connect a source; the [README](../README.md#try-it-locally) walks through it up to `composer require`. For the admin pages, make your account an admin first, see [First account](#first-account).
5. To remove everything including the data:

   ```bash
   docker compose down -v
   ```

If Packio already runs on this machine, the trial collides with it: the Compose project is always named `packio`, and ports 8080 and 8025 are taken. Check with `docker compose ls`. For a second copy, add `COMPOSE_PROJECT_NAME=packio-trial` and `HTTP_PORT=18080` to the trial's `.env` before starting, and change the first `8025` in `compose.local.yaml` to a free port; the addresses above change to match.

## Requirements for a server

- A Linux server on amd64 (not arm64).
- Docker with Compose v2. Check with `docker compose version`.
- A domain name, for example `packio.example.com`.
- DNS A/AAAA records for that domain and for `*.packio.example.com` (a wildcard record) pointing at the server. Each organization gets its own address such as `acme.packio.example.com`.
- Ports 80 and 443 open on the server.
- An SMTP account for outgoing mail. Packio sends the sign-up verification codes by mail.

## Quick start on a server

1. Find the latest version on the [releases page](https://github.com/packio-php/packio/releases) and replace `X.Y.Z` below with it (without the `v`). Download the deploy files:

   ```bash
   mkdir packio && cd packio
   curl -fsSL https://github.com/packio-php/packio/archive/refs/tags/vX.Y.Z.tar.gz | tar -xz --strip-components=2 --wildcards '*/deploy/*'
   ```

2. Create your settings file:

   ```bash
   cp .env.example .env
   ```

3. Generate two secrets and copy them into `.env`:

   ```bash
   openssl rand -hex 32
   echo "base64:$(openssl rand -base64 32)"
   ```

   Open `.env` in an editor. Set `PACKIO_VERSION=X.Y.Z` to the version you just downloaded, so the images match the files. Then fill in four values:

   - `PACKIO_DOMAIN`: your domain, without `https://`.
   - `DATABASE_PASS`: the output of the first command.
   - `APP_SECRET_ENCRYPTION_KEY`: the output of the second command. If you lose it, stored secrets cannot be read, so back it up.
   - `MAIL_DSN`: your SMTP address, for example `smtps://user:password@smtp.example.com:465`.

4. Choose how HTTPS is served. In `.env`, replace the line `COMPOSE_FILE=compose.yaml` with this one. It adds Caddy, a small web server that gets and renews HTTPS certificates for you:

   ```text
   COMPOSE_FILE=compose.yaml:compose.caddy.yaml
   ```

   Optional: set `ACME_EMAIL` to your address so Let's Encrypt can warn you before a certificate expires.

5. Start everything and check it:

   ```bash
   docker compose up -d
   docker compose ps
   ```

   The first start takes a minute or two: the database is prepared before the app reports healthy. Run `docker compose ps` again until `app` shows `healthy`.

6. Open `https://<your domain>` and register. Then make your account an admin, see [First account](#first-account).

`PACKIO_VERSION=latest` in `.env` means "the newest release". Pin it to the version you downloaded (`PACKIO_VERSION=X.Y.Z`) so that updates happen only when you choose, see [Updating](#updating).

## Using your own reverse proxy

If you already run a web server such as Nginx or Caddy on the server, keep `COMPOSE_FILE=compose.yaml` (the default in `.env.example`). Then Packio does not start its own proxy.

The app listens only on `127.0.0.1:8080` of the server (change the port with `HTTP_PORT`). A proxy running on the host itself (or with host networking) can reach it there. A proxy in another container cannot: it has its own `127.0.0.1`. The bundled files do not open this port to other machines.

Your proxy must:

- Serve HTTPS for the domain and for `*.<registry domain>` (`PACKIO_DOMAIN` unless `BRAND_REGISTRY_BASE_DOMAIN` is set).
- Forward requests to `127.0.0.1:8080` and keep the original `Host` header.
- Pass the client's `Origin` header through unchanged; the app uses it for CSRF checks.
- Overwrite the forwarding headers (`X-Forwarded-For`, `X-Forwarded-Proto`, `X-Forwarded-Host`, `X-Forwarded-Port`, `Forwarded`) instead of passing the client's values.

You must also set `TRUSTED_PROXIES` in `.env` to the address the app sees your proxy at. Without it the app sees `http://<domain>` while the browser sends `https://<domain>`, so every form (sign-in, registration) fails the origin check, and all visitors share a single rate limit. Never use `*`.

For the usual case (a proxy on the host talking to `127.0.0.1:8080`) the app sees the gateway of the Compose network. Start the stack once, read the address, and start again:

```bash
docker compose up -d
docker network inspect packio_default -f '{{(index .IPAM.Config 0).Gateway}}'
```

Put that address into `.env` as `TRUSTED_PROXIES=<address>`, then run `docker compose up -d` again. The network is called `packio_default` because the Compose project is named `packio`. For other setups see [Docker and runtime operations](docker.md#host-caddy-reverse-proxy).

A ready Caddy example with the header rules is in [examples/reverse-proxy/Caddyfile](examples/reverse-proxy/Caddyfile).

## Enabling SSR

SSR means server-side rendering: the server prepares the HTML of each page instead of the browser. It is optional and starts one more container. Packio works without it.

Add `:compose.ssr.yaml` to the end of the `COMPOSE_FILE` line in `.env`. With Caddy:

```text
COMPOSE_FILE=compose.yaml:compose.caddy.yaml:compose.ssr.yaml
```

Then apply it:

```bash
docker compose up -d
```

## First account

Register on the site first and confirm the code that arrives by mail. Then make that account an admin:

```bash
docker compose exec app php vendor/bin/laminas user:make-admin you@example.com
```

Replace the address with the one you registered. Sign in, create an organization, and follow [the OAuth guide](oauth.md) to connect a source.

## Updating

1. Back up first, see [Backups](#backups).
2. Download the new version's deploy files as in step 1 of the quick start, and compare them with yours (for example with `diff`), because a release can change the Compose files.
3. Set `PACKIO_VERSION=X.Y.Z` in `.env` to the new version.
4. Download the new images and restart:

   ```bash
   docker compose pull
   docker compose up -d
   ```

The app applies database migrations on start. Read [Upgrade and rollback](operations/upgrade.md) before updating a server that matters.

## Backups

Back up the database, the package archives, and `.env` (it holds the encryption key). The commands for the files in `deploy/` are in [Backup and restore](operations/backup-restore.md).

## External PostgreSQL and S3 archives

PostgreSQL: `compose.yaml` runs its own PostgreSQL 17 and fixes `DATABASE_HOST` to it, so `.env` cannot point the app at another database. To use your own server:

1. Copy `compose.yaml` to `compose.external-db.yaml` (a copy survives the next download of the deploy files).
2. In the copy, delete the `postgres` service, the whole `depends_on:` block of the `app` service (its only entry is `postgres`), and `postgres-data` under `volumes`.
3. In the copy, set `DATABASE_HOST` and `DATABASE_PORT` in the `x-environment` block to an address the containers can reach (not `localhost`).
4. In `.env`, use `compose.external-db.yaml` instead of `compose.yaml` in the `COMPOSE_FILE` line, then run `docker compose up -d --remove-orphans` (otherwise the old `postgres` container keeps running).

Only PostgreSQL 17 is supported. See [Environment variables](../ENV.md#database).

S3 archives: package archives are stored in a Docker volume by default. S3-compatible storage is experimental. Its `PACKAGE_ARCHIVE_*` settings go into `.env`; the names are in [Environment variables](../ENV.md).

## Certificates for registry subdomains

Each organization has its own address, `<org>.<registry domain>`. Caddy cannot know them all in advance. It gets a certificate the first time someone opens an address, which takes a few seconds. Before asking, Caddy checks that the name looks like `<one-word>.<registry domain>`.

The limit: any single-label subdomain may request a certificate, whether or not that organization exists. A stranger can therefore use up the weekly Let's Encrypt limit for new names. If that happens, replace the check with an app endpoint that knows the existing registries, or use a DNS-issued wildcard certificate. The note is in `Caddyfile`.

By default the registry domain is `PACKIO_DOMAIN`. To serve registries from another domain, set `BRAND_REGISTRY_BASE_DOMAIN` in `.env` and point its wildcard DNS record at the server.

## Troubleshooting

- `Set PACKIO_DOMAIN in .env` (or another `Set ... in .env`): the named variable is empty. Fill it in `.env` and run the command again.
- `port is already allocated` or `address already in use`: something else uses port 80, 443 or 8080 (8025 in the local trial). Stop it, or for 8080 set another `HTTP_PORT` in `.env`.
- Containers of another Packio installation were recreated or stopped: both folders use the Compose project name `packio`. Run `docker compose ls` to see it, and give one of them its own `COMPOSE_PROJECT_NAME` in `.env`, see [Try it locally](#try-it-locally).
- No certificate: check that DNS for the domain and `*.<domain>` points at this server, that ports 80 and 443 are open, and read `docker compose logs caddy`.
- No mail arrives: check `MAIL_DSN` in `.env`. Mail is sent by the `workers` container, so `docker compose ps` must show it running; read `docker compose logs workers`.
- `Pool overlaps with other one on this address space`: the private network between Caddy and the app clashes with one of yours. Set another `PACKIO_EDGE_SUBNET` (default `172.30.255.0/29`) and a `PACKIO_EDGE_PROXY_IP` inside it (default `172.30.255.2`), then run `docker compose up -d`.
- The app stays `starting` for a long time: read `docker compose logs app`. It prepares the database on the first start.
