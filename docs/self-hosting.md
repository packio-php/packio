# Self-hosting Packio

Run Packio on your own server from ready-made Docker images. You do not clone the repository, build anything, or need a GitHub token.

The images are `ghcr.io/packio-php/packio`, `ghcr.io/packio-php/packio-worker` and `ghcr.io/packio-php/packio-ssr`. Compose downloads them for you.

## Requirements

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

   Open `.env` in an editor and fill in four values:

   - `PACKIO_DOMAIN`: your domain, without `https://`.
   - `DATABASE_PASS`: the output of the first command.
   - `APP_SECRET_ENCRYPTION_KEY`: the output of the second command. Back it up. If you lose it, stored secrets cannot be read.
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

`PACKIO_VERSION=latest` in `.env` means "the newest release". Pin it to a number such as `0.3.0` so that updates happen only when you choose, see [Updating](#updating).

## Using your own reverse proxy

If you already run a web server such as Nginx or Caddy on the server, keep `COMPOSE_FILE=compose.yaml` (the default in `.env.example`). Then Packio does not start its own proxy.

The app listens only on `127.0.0.1:8080` of the server (change the port with `HTTP_PORT`). A proxy running on the same server can reach it there. The bundled files do not open this port to other machines.

Your proxy must:

- Serve HTTPS for the domain and for `*.<registry domain>` (the registry domain is `PACKIO_DOMAIN`).
- Forward requests to `127.0.0.1:8080` and keep the original `Host` header.
- Overwrite the forwarding headers (`X-Forwarded-For`, `X-Forwarded-Proto`, `X-Forwarded-Host`, `X-Forwarded-Port`, `Forwarded`) instead of passing the client's values.

Set `TRUSTED_PROXIES` in `.env` to the address the app sees your proxy at. Without it, all visitors look like one client and share a single rate limit. Never use `*`. How to find that address is described in [Docker and runtime operations](docker.md#host-caddy-reverse-proxy).

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

1. Pin the version in `.env`, for example `PACKIO_VERSION=0.3.0`.
2. Back up first, see [Backups](#backups).
3. Download the new images and restart:

   ```bash
   docker compose pull
   docker compose up -d
   ```

The app applies database migrations on start. Read [Upgrade and rollback](operations/upgrade.md) before updating a server that matters.

## Backups

Back up the database, the package archives, `.env` (it holds the encryption key), and keep the key safe. The commands for the files in `deploy/` are in [Backup and restore](operations/backup-restore.md).

## External PostgreSQL and S3 archives

PostgreSQL: `compose.yaml` runs its own PostgreSQL 17 and sets `DATABASE_HOST` to it, so `.env` cannot point the app at another database. To use your own server, copy `compose.yaml`, remove the `postgres` service, and set `DATABASE_HOST` and `DATABASE_PORT` in the shared `environment` block. Only PostgreSQL 17 is supported. See [Environment variables](../ENV.md#database).

S3 archives: package archives are stored in a Docker volume by default. S3-compatible storage is experimental. Its `PACKAGE_ARCHIVE_*` settings go into `.env`; the names are in [Environment variables](../ENV.md).

## Try it locally

To look around without a domain, use the local variant. It runs over plain HTTP and catches all mail in Mailpit. Do not use it on a server.

```bash
cp .env.local.example .env
docker compose up -d
```

Open `http://packio.localhost:8080` and register. Mail arrives in Mailpit at `http://localhost:8025`. To remove everything including the data:

```bash
docker compose down -v
```

## Certificates for registry subdomains

Each organization has its own address, `<org>.<registry domain>`. Caddy cannot know them all in advance. It gets a certificate the first time someone opens an address, which takes a few seconds. Before asking, Caddy checks that the name looks like `<one-word>.<registry domain>`.

The limit: any single-label subdomain may request a certificate, whether or not that organization exists. A stranger can therefore use up the weekly Let's Encrypt limit for new names. If that happens, replace the check with an app endpoint that knows the existing registries, or use a DNS-issued wildcard certificate. The note is in `Caddyfile`.

By default the registry domain is `PACKIO_DOMAIN`. To serve registries from another domain, set `BRAND_REGISTRY_BASE_DOMAIN` in `.env` and point its wildcard DNS record at the server.

## Troubleshooting

- `Set PACKIO_DOMAIN in .env` (or another `Set ... in .env`): the named variable is empty. Fill it in `.env` and run the command again.
- `port is already allocated` or `address already in use`: something else uses port 80, 443 or 8080. Stop it, or for 8080 set another `HTTP_PORT` in `.env`.
- No certificate: check that DNS for the domain and `*.<domain>` points at this server, that ports 80 and 443 are open, and read `docker compose logs caddy`.
- No mail arrives: check `MAIL_DSN` in `.env`. Mail is sent by the `workers` container, so `docker compose ps` must show it running; read `docker compose logs workers`.
- `Pool overlaps with other one on this address space`: the private network between Caddy and the app clashes with one of yours. Set another `PACKIO_EDGE_SUBNET` (default `172.30.255.0/29`) and a `PACKIO_EDGE_PROXY_IP` inside it (default `172.30.255.2`), then run `docker compose up -d`.
- The app stays `starting` for a long time: read `docker compose logs app`. It prepares the database on the first start.
