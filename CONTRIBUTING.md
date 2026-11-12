# Contributing to Packio

Thanks for helping improve Packio. Before opening a pull request, check existing issues and keep changes focused. For a security vulnerability, follow [SECURITY.md](SECURITY.md) and do not open a public issue.

## Development setup

Packio's current development and first-release target is Linux on amd64 with PHP 8.5.x, PostgreSQL 17, and Node 24.21.0. Use Composer 2 for development; Composer 2.8.x is the client target for release rehearsal. RoadRunner 2025.1.15 is the production HTTP and worker runtime. FrankenPHP remains a local development option. See the [support matrix](docs/support.md) for current limits and verification status; this does not announce a stable public release.

From a clean clone you only need Docker:

```bash
cp .env.example .env
docker compose -f compose.yaml up -d --build
```

The one-shot `setup-php` and `setup-node` services install Composer and npm dependencies, enable development mode, and build the SSR bundle. They skip work when the lock files are unchanged. See [Try it locally](README.md#try-it-locally) for a walkthrough and the [Docker command matrix](docs/docker.md#local-command-matrix) for the runtime choices. Keep `.env` and any local credentials private.

Host `composer install` and `npm ci --no-audit --no-fund` are needed only to run the checks on the host (`composer check`); for browser tests also run `npx playwright install --with-deps chromium`. `composer install` installs the six locked Composer tool dependencies through the configured Composer bin plugin and does not enable Laminas development mode; run `composer development-enable` when you work on the host.

`setup-node` builds the SSR bundle only when `data/ssr/ssr.mjs` is missing. After pulling frontend changes, rebuild it:

```bash
rm -f data/ssr/ssr.mjs && docker compose -f compose.yaml run --rm setup-node && docker compose -f compose.yaml restart ssr
```

The default development stack uses PostgreSQL and serves the app at `http://localhost:8080/` (or `http://packio.localhost:8080/`); Mailpit is available at `http://localhost:8025/`. The PHP test runner uses a separate `postgres-test` Compose service and isolated test database. It requires Docker. Do not point tests at a development or production database.

## Checks

Run the relevant checks before requesting review. Backend database suites use shared local test resources, so run them serially; do not run `composer check` and `composer test:concurrency` at the same time.

```bash
composer check
composer test:concurrency
composer openapi-check
npm run check
python3 test/Support/Docs/links.test.py
```

`composer check` runs the configured PHP style, static and architecture checks (see [Module architecture](docs/development/architecture.md)), runner checks, and base PHPUnit suites. `composer test:concurrency` runs the PostgreSQL concurrency and locking group separately. `composer openapi-check` regenerates the OpenAPI document and checks that it is current. `npm run check` runs frontend types, lint, Inertia compatibility checks, unit/component tests, SSR tests, real Chromium browser tests, formatting, and client/SSR builds. The browser check needs the locked Playwright browser and its operating-system dependencies. See [Testing](docs/testing.md) for suite boundaries, database isolation, and focused test commands.

## Changes that affect persisted or public contracts

- Review database migrations for upgrade and rollback behavior. Do not assume a schema change is safe merely because a fresh database migrates.
- Preserve REST request/response behavior or document intentional API changes in the API contract and changelog.
- Treat queued job payloads as persisted data: keep old payloads readable where possible and cover compatibility when changing a job contract.
- Preserve tenant isolation and authorization boundaries. Organization-scoped data must remain inaccessible across tenants; review authorization at the application boundary and add tests for both allowed and denied access.
- Avoid logging credentials, tokens, private package contents, authentication data, or full request payloads.

Open a pull request with the [PR template](.github/pull_request_template.md). Include the reason for the change, user-visible behavior, and checks you actually ran. Do not include secrets, private customer data, database dumps, or generated local planning material.
