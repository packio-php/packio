# Project commands

This document lists the commands used by this project: application CLI commands, framework/tooling CLI commands, Composer scripts, and npm scripts.

Run application CLI commands from the project root:

```bash
php vendor/bin/laminas <command>
```

If configuration cache is enabled and command registration changes, run `composer clear-config-cache`.

## Application CLI

Application commands are registered through Laminas CLI in `cli.config.php` files under `src/Module/*/config`, `src/Application/config` and `src/Common/config`.

## HTTP runtime operations

HTTP runtime selection, the mutually exclusive Compose command matrix, RoadRunner pool controls, and production rollback are documented in [Docker and HTTP runtimes](docker.md). Use `WORKER_*` only for the Packio job worker; `RR_*` controls RoadRunner's HTTP pool.

| Command                            | Purpose                                                                                                                                                                                                                                                    | Common usage                                                                                |
| ---------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------- |
| `email-verification:cleanup`       | Deletes expired email verification codes.                                                                                                                                                                                                                  | `php vendor/bin/laminas email-verification:cleanup`                                         |
| `password-reset:cleanup`           | Deletes expired and already used password reset tokens.                                                                                                                                                                                                    | `php vendor/bin/laminas password-reset:cleanup`                                             |
| `user:make-admin <email>`          | Grants the `admin` role to an existing user by email. The command keeps existing roles and is safe to rerun.                                                                                                                                               | `php vendor/bin/laminas user:make-admin user@example.com`                                   |
| `storage:archives:backfill`        | Schedules jobs to backfill private release archive objects.                                                                                                                                                                                                | `php vendor/bin/laminas storage:archives:backfill --organization=acme`                      |
| `storage:archives:cleanup-dev`     | Finds stale dev release archive objects. It is a dry run unless `--delete` is passed.                                                                                                                                                                      | `php vendor/bin/laminas storage:archives:cleanup-dev --organization=acme`                   |
| `source:oauth:refresh-maintenance` | Refreshes Source OAuth credentials before provider tokens go stale. By default it schedules worker jobs.                                                                                                                                                   | `php vendor/bin/laminas source:oauth:refresh-maintenance --organization=acme --dry-run`     |
| `source:webhook-secrets:encrypt`   | Encrypts Source webhook secrets still stored as plaintext. Safe to rerun; `--dry-run` only counts them. Run it during low traffic: it loads sources in batches without clearing the ORM heap, and an edit made to a source at the same moment can be lost. | `php vendor/bin/laminas source:webhook-secrets:encrypt --dry-run`                           |
| `organization:invitations:encrypt` | Encrypts organization invitation tokens still stored as plaintext. Safe to rerun; `--dry-run` only counts them.                                                                                                                                            | `php vendor/bin/laminas organization:invitations:encrypt --dry-run`                         |
| `webhook:events:prune`             | Deletes webhook delivery events older than `--older-than-days` (default 30), in batches. `--dry-run` only counts them.                                                                                                                                     | `php vendor/bin/laminas webhook:events:prune --older-than-days=30 --dry-run`                |
| `audit:log:prune`                  | Deletes audit log entries older than `AUDIT_LOG_RETENTION_DAYS` (default 180), in batches. `--older-than-days` overrides it; `--dry-run` only counts them.                                                                                                 | `php vendor/bin/laminas audit:log:prune --dry-run`                                          |
| `release:orphans:cleanup`          | Schedules archive cleanup for and deletes releases whose package no longer exists. `--dry-run` only counts them.                                                                                                                                           | `php vendor/bin/laminas release:orphans:cleanup --dry-run`                                  |
| `worker:run`                       | Starts a background job worker.                                                                                                                                                                                                                            | `php vendor/bin/laminas worker:run`                                                         |
| `worker:cleanup`                   | Deletes old jobs and job logs.                                                                                                                                                                                                                             | `php vendor/bin/laminas worker:cleanup --days=30`                                           |
| `object-mapper:warmup`             | Warms the generated object mapper cache. Required in deploys before HTTP/worker restart; see [ENV.md](../ENV.md).                                                                                                                                         | `APP_PRODUCTION=true OBJECT_MAPPER_GENERATE_ON_DEMAND=0 php vendor/bin/laminas object-mapper:warmup` |
| `rate-limit:cleanup`               | Cleans expired limiter files with a stable lock and resumable bounded scan.                                                                                                                                                                                | `php vendor/bin/laminas rate-limit:cleanup --retention-seconds 3600 --limit 1000 --dry-run` |

### `webhook:events:prune` cron

Run it daily, for example from cron:

```cron
17 3 * * * cd /path/to/packio && php vendor/bin/laminas webhook:events:prune --older-than-days=30
```

### `audit:log:prune` cron

Audit log entries are kept for `AUDIT_LOG_RETENTION_DAYS` days (default 180). Nothing is archived: export the
`audit_entries` table first if you need older entries. The Docker `worker-cleanup` service runs the command in its
loop; without Docker, run it daily:

```cron
27 3 * * * cd /path/to/packio && php vendor/bin/laminas audit:log:prune
```

### `user:make-admin`

```bash
php vendor/bin/laminas user:make-admin user@example.com
```

Arguments:

| Argument | Required | Description                        |
| -------- | -------: | ---------------------------------- |
| `email`  |      yes | Email address of an existing user. |

Exit codes:

| Code | Meaning                                    |
| ---: | ------------------------------------------ |
|  `0` | User was promoted or was already an admin. |
|  `1` | User was not found.                        |
|  `2` | Email argument was empty.                  |

### `storage:archives:backfill`

```bash
php vendor/bin/laminas storage:archives:backfill --organization=acme
```

Options:

| Option                 | Required | Description                                                               |
| ---------------------- | -------: | ------------------------------------------------------------------------- |
| `--organization`, `-o` |      yes | Organization id or slug to backfill.                                      |
| `--package`, `-p`      |       no | Limit backfill to a single package name.                                  |
| `--dry-run`            |       no | Print matching releases without enqueueing jobs.                          |
| `--force`, `-f`        |       no | Enqueue jobs even when release storage metadata already exists.           |
| `--include-public`     |       no | Include public packages. By default only private packages are backfilled. |

### `storage:archives:cleanup-dev`

```bash
php vendor/bin/laminas storage:archives:cleanup-dev --organization=acme
```

Options:

| Option                 | Required | Description                                                                              |
| ---------------------- | -------: | ---------------------------------------------------------------------------------------- |
| `--organization`, `-o` |      yes | Organization id or slug to clean.                                                        |
| `--package`, `-p`      |       no | Limit cleanup to a single package name.                                                  |
| `--include-public`     |       no | Include public packages. By default only private packages are scanned.                   |
| `--delete`             |       no | Delete matching stale dev archive objects. Without this option the command is a dry run. |

### `source:oauth:refresh-maintenance`

```bash
php vendor/bin/laminas source:oauth:refresh-maintenance --organization=acme --dry-run
php vendor/bin/laminas source:oauth:refresh-maintenance --organization=acme
```

The command finds active Source OAuth accounts whose access tokens expire soon or whose credentials need an idle keepalive refresh. Without `--run-inline`, matching accounts are scheduled as `source.oauth.refresh` jobs and must be processed by `worker:run`.

Defaults come from `source_oauth_refresh` config: refresh tokens expiring within `24 hours`, refresh idle accounts last used before `30 days` by default, skip accounts refreshed within `1 day`, and inspect up to `100` accounts per run. Provider-specific config can override these values.

Options:

| Option                       | Required | Description                                                                           |
| ---------------------------- | -------: | ------------------------------------------------------------------------------------- |
| `--provider`, `-p`           |       no | Limit maintenance to one provider, for example `github` or `gitlab_self_hosted`.      |
| `--provider-instance`        |       no | Limit maintenance to a source provider instance id.                                   |
| `--organization`, `-o`       |       no | Limit maintenance to an organization id or slug.                                      |
| `--limit`, `-l`              |       no | Maximum Source OAuth accounts to inspect.                                             |
| `--refresh-before`           |       no | Refresh tokens expiring within this relative duration, for example `12 hours`.        |
| `--idle-before`              |       no | Refresh tokens whose account has not been used since this relative duration.          |
| `--minimum-refresh-interval` |       no | Skip accounts updated more recently than this relative duration, for example `1 day`. |
| `--dry-run`                  |       no | Print candidate counts without enqueueing refresh jobs.                               |
| `--run-inline`               |       no | Refresh candidates immediately instead of enqueueing worker jobs.                     |

### `worker:run`

This worker also delivers registration, verification and password-reset emails. Keep it running for authentication mail delivery. Web and worker processes must use the same `APP_SECRET_ENCRYPTION_KEY`; authentication tasks are encrypted and use the reserved `system:identity` scope, outside organization job lists.

Connecting a source deduplicates active `source.create` jobs. A new connection request after completion, failure or cancellation releases the old job's deduplication key and queues a new job, allowing a deleted source to be added again. Previous jobs, payloads and logs remain available; other job types retain their existing deduplication rules.

```bash
php vendor/bin/laminas worker:run
```

Options:

| Option                 | Default | Description                                                  |
| ---------------------- | ------: | ------------------------------------------------------------ |
| `--timeout`, `-t`      |    `30` | Polling timeout in seconds.                                  |
| `--memory-limit`, `-m` |  `128M` | Memory limit in PHP ini format, for example `512M` or `1G`.  |
| `--max-jobs`, `-j`     |     `0` | Maximum jobs to process before exiting. `0` means unlimited. |
| `--worker-id`, `-w`    |    auto | Unique worker identifier.                                    |
| `--stop-when-empty`    |     off | Exit if no job is available after one polling cycle.         |

### `worker:cleanup`

```bash
php vendor/bin/laminas worker:cleanup --days=30
```

Options:

| Option         | Default | Description                           |
| -------------- | ------: | ------------------------------------- |
| `--days`, `-d` |    `30` | Retention days for jobs and job logs. |

## Framework and tooling CLI

These commands are provided by dependencies but are part of the project workflow.

### Cycle ORM

Run Cycle commands through Laminas CLI:

```bash
php vendor/bin/laminas cycle:migration:run
```

| Command                           | Purpose                                                                                                                          |
| --------------------------------- | -------------------------------------------------------------------------------------------------------------------------------- |
| `cycle:migration:run`             | Applies pending database migrations.                                                                                             |
| `cycle:migration:rollback`        | Rolls back the last migration. Use only when a task explicitly calls for rollback.                                               |
| `cycle:migration:create <Name>`   | Creates an empty migration file. Use this only as a fallback after schema migration generation cannot produce the required diff. |
| `cycle:schema:migration:generate` | Generates schema migrations from Cycle schema changes. This is the default way to create schema migrations.                      |
| `cycle:schema:compile`            | Compiles Cycle ORM schema and stores it in a PHP file.                                                                           |
| `cycle:schema:sync`               | Synchronizes schema tables and can refresh compiled schema cache. Prefer migrations for normal database changes.                 |
| `cycle:cache:clear`               | Clears the Cycle ORM schema cache file.                                                                                          |
| `cycle:seed:create`               | Creates a seed file.                                                                                                             |
| `cycle:seed:run`                  | Runs seed files. Use `php vendor/bin/laminas cycle:seed:run RuntimeSettingsCatalogSeed` to sync runtime setting rows.            |

## Composer scripts

Run Composer scripts from the project root:

```bash
composer <script>
```

PHPUnit Composer scripts provision and validate the isolated PostgreSQL test database through `bin/with-postgres-test`. Running `composer check` locally requires Docker; it starts only `postgres-test` and does not use the development database.

### Setup and runtime

| Script                         | Purpose                                                                                              |
| ------------------------------ | ---------------------------------------------------------------------------------------------------- |
| `composer install`             | Installs PHP dependencies.                                                                           |
| `composer serve`               | Starts the PHP built-in server on `0.0.0.0:8080` with `public/` as the document root.                |
| `composer development-enable`  | Enables development mode.                                                                            |
| `composer development-disable` | Disables development mode.                                                                           |
| `composer development-status`  | Shows development mode status.                                                                       |
| `composer clear-config-cache`  | Clears merged configuration cache. Required after config changes when cache is enabled.              |
| `composer refresh-route-cache` | Rebuilds the route cache.                                                                            |
| `composer clear-application-cache` | Clears config and route caches.                                                                  |
| `composer mezzio`              | Runs Laminas CLI with ANSI output. Pass arguments after `--`, for example `composer mezzio -- list`. |

### Lifecycle hooks

| Script                                        | Purpose                                                        |
| --------------------------------------------- | -------------------------------------------------------------- |
| `composer run-script post-install-cmd`        | Runs install-time hooks. Clears config and route caches.       |
| `composer run-script post-create-project-cmd` | Runs create-project hooks. Currently enables development mode. |

### Quality checks

| Script                     | Purpose                                                                                                           |
| -------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| `composer check`           | Runs the main backend quality gate: CS check, Rector dry run, PHPStan, Deptrac, runner tests, base tests, and flaky-test check. |
| `composer cs-check`        | Runs PHP CS Fixer in dry-run mode with diff output.                                                               |
| `composer cs-fix`          | Applies PHP CS Fixer formatting.                                                                                  |
| `composer phpstan`         | Runs PHPStan with `phpstan.neon`.                                                                                 |
| `composer rector`          | Runs Rector in dry-run mode.                                                                                      |
| `composer rector-fix`      | Applies Rector changes.                                                                                           |
| `composer deptrac`         | Checks module and layer boundaries with `deptrac.php`; fails on violations and on uncovered dependencies.         |
| `composer analyse-deps`    | Runs Composer dependency analyser.                                                                                |
| `composer normalize`       | Checks and normalizes `composer.json`.                                                                            |
| `composer check-flaky`     | Fails if tests contain `@group flaky` or `#[Group('flaky')]`.                                                                            |
| `composer dead-code`       | Runs PHPStan dead-code analysis with `phpstan-dead-code.neon`.                                                    |
| `composer openapi-generate` | Generates `public/openapi.json` from `src/Module/Rest/src`.                                                      |
| `composer openapi-check`   | Regenerates `public/openapi.json` and fails if it differs from the committed file (CI gate).                      |

### Tests

| Script                      | Purpose                                                                             |
| --------------------------- | ----------------------------------------------------------------------------------- |
| `composer test`             | Runs unit, integration, and feature suites.                                         |
| `composer test:base`        | Runs unit, integration, and feature suites excluding `slow`.                        |
| `composer test:unit`        | Runs the unit suite excluding `slow`.                                               |
| `composer test:integration` | Runs the integration suite excluding `slow`.                                        |
| `composer test:feature`     | Runs the feature suite excluding `slow`.                                            |
| `composer test:runner`      | Runs the shell runner tests under `test/Support/Runner/`.                           |
| `composer test:regression`  | Runs the feature regression filter for security, auth, registry, and webhook flows. |
| `composer test:concurrency` | Runs PostgreSQL concurrency and locking tests.                                      |
| `composer test:coverage`    | Generates text and Clover coverage reports under `data/coverage/`.                  |

### Release and build helpers

| Script                               | Purpose                                   |
| ------------------------------------ | ----------------------------------------- |
| `composer create-release-tag`        | Runs `bin/create-release-tag.php`.        |
| `composer generate-release-metadata` | Runs `bin/generate-release-metadata.php`. |
| `composer verify-vite-assets`        | Verifies built Vite assets.               |

## npm scripts

Run frontend scripts from the project root:

```bash
npm run <script>
```

| Script                        | Purpose                                                                                                                     |
| ----------------------------- | --------------------------------------------------------------------------------------------------------------------------- |
| `npm install`                 | Installs frontend dependencies.                                                                                             |
| `npm run dev`                 | Starts the Vite development server.                                                                                         |
| `npm run build`               | Builds client and SSR frontend bundles.                                                                                     |
| `npm run build:client`        | Builds the client bundle.                                                                                                   |
| `npm run build:ssr`           | Builds the SSR bundle from `resources/js/ssr.ts`.                                                                           |
| `npm run preview`             | Starts Vite preview for built assets.                                                                                       |
| `npm run check`               | Runs type checks, lint, Inertia v3 check, frontend tests, SSR smoke test, browser e2e tests (needs a Playwright Chromium), format check, and build.                                                 |
| `npm run check:types`         | Runs `svelte-check` and TypeScript `--noEmit`.                                                                              |
| `npm run lint`                | Runs ESLint.                                                                                                                |
| `npm run lint:fix`            | Applies ESLint fixes.                                                                                                       |
| `npm run test:front`          | Runs frontend Vitest unit/component tests.                                                                                  |
| `npm run test:front:ci`       | Runs frontend Vitest tests and writes a CI-compatible JUnit report.                                                         |
| `npm run test:front:coverage` | Runs frontend Vitest tests with coverage output.                                                                            |
| `npm run test:ssr`            | Builds the SSR bundle and runs its runtime smoke test.                                                                      |
| `npm run test:e2e:browser`    | Runs Playwright Inertia v3 browser contract tests; it starts a fixture by default, or targets `E2E_BASE_URL` when supplied. Install the browser once locally with `npx playwright install --with-deps chromium`. |
| `npm run check:inertia-v3`    | Verifies that legacy Svelte and Inertia compatibility code cannot return.                                                   |
| `npm run openapi-assets`      | Copies swagger-ui assets to `public/api-doc/assets`.                                                                        |
| `npm run test:front:watch`    | Runs frontend Vitest tests in watch mode.                                                                                   |
| `npm run format`              | Formats frontend source and frontend config files with Prettier.                                                            |
| `npm run format:check`        | Checks frontend formatting with Prettier.                                                                                   |


### `rate-limit:cleanup`

```bash
php vendor/bin/laminas rate-limit:cleanup --retention-seconds 3600 --limit 1000 --dry-run
```

| Option | Required | Meaning |
| --- | --- | --- |
| `--retention-seconds` | yes | Positive retention covering configured retention and the operator-confirmed historical maximum. |
| `--limit` | no | Candidates per run, 1..1000; default 1000. |
| `--dry-run` | no | Report candidates without changing data, lock or cursor. |

Output is `scanned=N deleted=N skipped=N`; dry-run `deleted` counts proposed
deletions. Live runs resume using `.cleanup.cursor` and wrap at the end of sorted
candidates. Invalid options return exit 2; storage errors return exit 1. No automatic
cleanup schedule is installed. `3600` is an example, not a universal safe retention.

Before a live run, follow the [lock-protocol migration and historical-window
procedure](rate-limiting.md#storage-lifecycle-and-cleanup): stop all old writers,
confirm retention, start all new writers, then dry-run. Do not lower retention
without draining writers and waiting out the previous maximum. Unknown historical
windows require an operator-chosen conservative waiting period before activation.
