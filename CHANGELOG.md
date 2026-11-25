# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Self-hosted Forgejo as a source provider: organizations add their own Forgejo instances and connect them with OAuth (PKCE) or an access token, with webhooks for automatic sync.

### Security

- Twig is updated to 3.31.1, which fixes several sandbox bypass advisories. Packio does not use the Twig sandbox.

### Fixed

- Switching providers in the Add source dialog no longer changes visibility from Private to Public, and a warning appears when a private repository is about to be connected as Public.
- Clicking Edit on a self-hosted instance and the password link on the profile page no longer scroll the target under the sticky header.

## [0.6.0] - 2026-11-23

### Added

- The profile page shows your 20 most recent successful sign-ins: time, method, IP address and browser.
- The admin user page shows the user's sign-in methods (password and connected OAuth accounts) and their sign-in history. The admin users list has a "Last sign-in" column.
- Sign-in history is kept for `SIGN_IN_HISTORY_RETENTION_DAYS` days (90 by default). The new `sign-in-history:prune` command deletes older entries, and `worker-cleanup` runs it in its loop.
- Optional privacy policy and terms pages from Markdown files (`PRIVACY_POLICY_PATH`, `TERMS_OF_SERVICE_PATH`), linked in the footer and on sign-up.

### Security

- The session gets a new ID when a user logs in, which protects against session fixation.

### Changed

- Outbound HTTPS requests (Git and OAuth providers, webhooks, dist downloads, S3-compatible storage) now require TLS 1.2 or newer. Self-hosted services that still use TLS 1.0 or 1.1 stop working.
- PHP and JavaScript dependencies are updated, including Guzzle 8 and Symfony Mailer 8.
- The scroll-to-top button is now a small pill centred at the top of the page instead of a button in the bottom-right corner.

### Fixed

- The password icon in the sign-in methods block of the profile page now matches the size and colour of the OAuth provider icons.

## [0.5.1] - 2026-11-19

### Security

- The Docker images run PHP 8.5.13 instead of 8.5.11. PHP 8.5.13 fixes security issues in the engine, sessions, dates, DOM, Phar, HTTPS stream wrappers and the URI extension.

## [0.5.0] - 2026-11-18

### Added

- The admin jobs page has a "Refresh" button that reloads the jobs, counts and queue health with the current filters.
- The admin dashboard shows queue health, failed syncs and recent audit events.
- The admin organizations page has a search field.

### Changed

- The admin Users and Organizations pages look like the other admin sections: a section heading without a card, and badges for statuses and roles. Organizations are listed in a table.
- The admin Sources table is compact: the source URL stays on one line, there are fewer columns, and the actions are icon buttons.
- Table headers are no longer uppercase across the app.
- The admin detail and settings cards follow the admin style, and the side cards on the settings page no longer stretch to the height of the main column.
- The admin jobs page shows one "Queue health" card instead of "Queue progress" and "System auth mail", which repeated the status counts. It shows retrying, stuck and oldest ready age for the whole queue and pending, failed and oldest ready age for auth mail, and highlights stuck jobs, failed auth mail and a ready age above five minutes. The status counts are in a "Statuses" card that says whether the page filters apply.
- Source URLs in package and source cards and tables stay on one line and end with an ellipsis when they do not fit, instead of breaking mid-word. The full URL is shown on hover.
- The admin Users and Organizations pages show pagination above and below the list with a page size selector, like the other admin lists. Admin lists hide pagination when they have no records.

## [0.4.0] - 2026-11-17

### Added

- The version in the footer links to GitHub: the commit hash to its commit, and the version of a tagged build to its release.

### Changed

- Package cards in the package list have an "Open details" button that opens the package, as the table view does. The source name on the card links to the source; the "Open source" button is removed.
- `sirix/mezzio-radixrouter` is updated to 4.0.0. A `HEAD` request to a `GET` route now reaches middleware and handlers as `GET`, and the response body is emptied afterwards. After updating, run `composer refresh-route-cache`.

## [0.3.2] - 2026-11-16

### Changed

- Local development runs on RoadRunner, like production. In `compose.yaml` one `workers` service replaces `worker` and the four maintenance services; run `docker compose up -d --build --remove-orphans` once to remove the old containers.

### Removed

- `compose.prod.yaml` and `.env.prod.example`. A source build now runs with the `deploy/` files, see "Production from a source build" in `docs/docker.md`. To keep the volumes of an existing `compose.prod.yaml` installation, move its settings to `deploy/.env`, add `PACKIO_DOMAIN`, `COMPOSE_PROJECT_NAME=packio-prod` and `compose.ssr.yaml` in `COMPOSE_FILE`, and tag your images as `ghcr.io/packio-php/packio`, `packio-worker` and `packio-ssr`.
- The FrankenPHP development runtime: `compose.roadrunner.yaml`, `public/index-worker.php`, the FrankenPHP, standalone worker and SSR Dockerfiles, and the `SERVER_NAME` setting.
- `third-party-sources.json`, from the repository and the images. `THIRD_PARTY_NOTICES.md` now says where the upstream sources are.

## [0.3.1] - 2026-11-15

### Changed

- Self-hosting guide: the local trial comes first and can be followed on its own, with the full download command, the expected result and the steps after registration. It also states the download sizes of the images and explains how to run a trial on a machine that already runs Packio.

## [0.3.0] - 2026-11-15

### Added

- Verified production images are published to GitHub Container Registry on release tags.
- `deploy/`: Compose files and `.env` examples that start Packio from the published images, with an optional Caddy overlay for automatic HTTPS and a local trial variant. See `docs/self-hosting.md`.

### Changed

- Production images are smaller.

### Removed

- The manual release bundle workflow and its source archive of third-party packages. `third-party-sources.json` still lists the upstream sources.

## [0.2.0] - 2026-11-14

### Added

- Audit log retention: `audit:log:prune` deletes entries older than `AUDIT_LOG_RETENTION_DAYS` (default 180 days). The Docker `worker-cleanup` service runs it; other installations need a cron entry, see `docs/commands.md`.
- Admin actions are written to the audit log: user email and role changes, manual email verification, runtime setting changes, organization updates, and creating, revoking and deleting invitations.

### Fixed

- Two simultaneous "add source" requests for the same repository can no longer create two sources: `sources (organization_id, canonical_url)` is now unique. The migration stops with an explanation if such duplicates already exist; delete the extra sources and run it again.
- Webhooks: the 409 answer for a payload from another repository no longer depends on the wording of the message, and the receiving endpoint no longer keeps its own list of providers. An unknown provider in the webhook URL now answers `Source not found.` (still 404).
- REST API: `POST /organizations/{organization}/sources` answers 422 instead of 500 when the provider is unknown or `resource` is not a repository URL the provider accepts.
- REST API: `scopes`, `permissions` and `roles` that are not lists of strings are rejected with 422; before, the invalid items were dropped silently.
- REST API: the `admin:*` token scope is no longer accepted when a token is created. It never granted anything; use `admin`.
- OpenAPI: token scopes are described by the full accepted pattern, and `DELETE` package documents the 503 response.
- GitHub repository listing no longer fails when the token cannot read organizations (no `read:org` scope, or an SSO-protected organization); those organizations are skipped.
- Failed console commands are logged with secrets redacted; before, the raw exception message and trace were written to the log.
- The webhook secret of a source is no longer passed along with release archive download data.
- Worker: a worker that lost the race for a job could not take that job later if it returned to the queue; it retried against a stale copy until the dequeue timeout.
- The 404 page for an unknown route shows the configured `BRAND_*` name and domain instead of the defaults.
- Docker: web sessions are stored in the `sessions` volume and expire after 2 hours without activity. Before, the PHP defaults applied: sessions went to the container's temp directory, were lost when the container was recreated, and could expire after 24 minutes.

### Changed

- Packio no longer sets PHP session settings with `ini_set`; it did so only when the config cache was off. The Docker image sets them in its PHP configuration. Other installations: set `session.save_path` and `session.gc_maxlifetime` (at least `7200`) in `php.ini`, see `docs/docker.md`.

### Removed

- The legacy job type `increment.install.count`. Before upgrading, make sure the queue has no such jobs: `SELECT COUNT(*) FROM jobs WHERE type = 'increment.install.count' AND status IN ('pending', 'running');` must return 0. A job of this type left in the queue fails after the upgrade.

## [0.1.0] - 2026-11-13

### Added

- Initial public release of Packio, a self-hosted private Composer package registry. The supported deployment scope is described in [docs/support.md](docs/support.md).
