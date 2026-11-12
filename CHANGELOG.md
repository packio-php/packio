# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Deploy

- Run, in this order: `cycle:migration:run` → `source:webhook-secrets:encrypt` → `organization:invitations:encrypt` → `release:orphans:cleanup --dry-run` → `release:orphans:cleanup`.
- During the deploy window, containers still running the old code can fail on OAuth login and on creating an invitation until they stop. Run the two encrypt commands only after the old containers have stopped: old code compares webhook signatures against the stored value and looks invitations up by the plaintext token.
- Rolling back the code breaks the encrypted webhook secrets and invitation links for the old release: rotate the webhook secrets and reissue pending invitations after a rollback. The old release also needs the migrations rolled back; the dropped login OAuth token columns come back empty.

### Added

- `webhook:events:prune` deletes webhook delivery events older than 30 days (configurable, run it daily), and `release:orphans:cleanup` removes releases whose package no longer exists. The webhook events table has an index on `created_at` for the prune.

### Changed

- The CI job that builds and exercises the production images runs only for manual runs of the verification workflow and for `v*` tags; pull requests and pushes to `master` no longer build images.
- The secret scan covers the repository's own history and tags instead of a frozen list of historical refs.
- The upgrade rehearsal takes its baseline from `--baseline-ref` (the previous public tag) instead of one hard-coded historical version.
- Trusted-proxy configuration defines which forwarded client-address data Packio accepts; arbitrary client-supplied forwarding headers are not trusted.
- PostgreSQL 17 is the only database in the current first-release support scope, and unsupported database drivers are rejected before database connection or migration work.
- The support matrix distinguishes the gated preview target from stable maturity requirements; a green test suite alone does not establish stable support.
- Turnstile verification and other requests through the shared HTTP client now time out after 10 seconds (5 seconds to connect); a timed-out Turnstile check fails closed.
- Git subprocesses check the workspace size limit once per second and once more after the process exits, instead of on every poll.
- The jobs table has an index on status and schedule, which makes the stuck-job lookup about 200 times faster on large tables.
- The audit log has an index on organization and source, which makes the source page's audit lookup about 150 times faster on large tables.
- User emails are now unique, and the missing Identity indexes (registry tokens, password reset selector, OAuth provider accounts, email verification codes) are declared and created. Resolve duplicate emails and duplicate token, selector and OAuth account rows before upgrading, otherwise the migration fails.
- Organization members and invitations have new indexes (unique invitation token, organization lookups). Duplicate invitation tokens make the migration fail.
- Organization names that differ only in the case of non-ASCII letters (for example "Ärzte" and "ärzte") now count as the same name.
- Log messages no longer carry `[FIX:…]` prefixes.

- Prepared a local [preview candidate draft](docs/releases/1.17.0-dev.12+5bbe7ca.md) with frozen SHA/images/source checksums, verified scope and explicit stable/experimental limitations; publication remains a separate owner action.
- The Sources page makes far fewer settings queries (131 → 34 in a typical setup) and no longer issues more of them for each provider instance.
- Admin organizations list is paginated.
- The REST OpenAPI document is built once per worker instead of on every request, and `/openapi.json` is no longer served as a static file from the runtime image.
- `/ready` caches its result for up to one second per worker.
- Admin Jobs text filters ignore leading and trailing spaces.
- The shared `app` page prop contains only `version`; product name and tagline remain in `branding`. The default page title and description are unchanged.
- Tests connect to PostgreSQL with `synchronous_commit=off`, and the dev `postgres-test` service runs with `fsync=off` and `full_page_writes=off` on tmpfs without persisted data. The old `postgres-test-data` volume can be removed with `docker volume rm <project>_postgres-test-data`.
- PHPUnit fails on warnings, notices, deprecations (PHP and PHPUnit) and risky tests; `composer check-flaky` detects `#[Group('flaky')]` attributes and no longer writes to `/tmp`.
- Architecture checks run as one `composer deptrac` (replacing `deptrac-layers` and `deptrac-modules`) that enforces module boundaries and fails on dependencies outside any layer.

### Fixed

- The development stack starts from a clean clone with Docker only: `setup-php` and `setup-node` install dependencies and build the SSR bundle. Emails from the development workers now reach Mailpit.
- Development stack: the app image gets the `sockets` extension (outbound HTTP such as loading GitHub repositories works) and the other `composer.json` extensions it lacked: `ctype`, `dom`, `pcntl`, `posix`, `xmlwriter`.
- A transient failure while refreshing an OAuth token (network error, HTTP 429 or 5xx, `temporarily_unavailable` or `server_error`) no longer marks the provider authorization invalid; the source sync and the refresh job retry. Any other OAuth refresh error still requires reconnecting.
- After a key change, an invitation link whose stored token can no longer be decrypted can still be accepted, and registering a webhook replaces a stored secret that can no longer be decrypted.
- Recovery modal shows "Loading..." from the first frame, and the error and landing pages use the configured branding name instead of a hard-coded "Packio".
- Two identical webhook deliveries arriving at the same time both get the duplicate-delivery answer instead of one failing with a server error, and the sync is scheduled once.
- CSRF origin checks and trusted forwarded hosts reject URLs with user or password parts; a failed web form validation redirects to the Referer only when it has the request's own origin, else to `/`; the `/ready` one-second cache window starts when the probe starts; error logs hide token-like URL path segments.
- Issuing an API token with an unknown scope is rejected with a validation error instead of storing a scope that never matches.
- Web route and API audit identifiers in non-canonical UUID forms (`urn:uuid:`, braces) are canonicalized, so such requests no longer fail with a server error; non-UUID authorization ids answer 404 instead of a server error.
- Job search treats `%` and `_` literally; sync failure results hide credentials; `--organization` token refresh only considers accounts authorized in that organization; failed verification emails are logged.
- The admin user update rejects unknown roles with a validation error instead of silently dropping them, and the web admin now shows why removing the last active administrator's admin role is refused.
- Force-finalizing a source deletion audits a failure that happens after the package loop, and a package-delete 503 returns a fixed message instead of the internal one.
- Queue workers in the Docker images run with a 1G PHP memory limit, above the default 512M restart threshold.
- The SSR server exits after an uncaught exception so the supervisor restarts it.
- Web routes answer malformed record ids like missing records instead of a server error, including the public webhook endpoint.
- Release date resolution no longer keeps a permanent Git mirror per source in the Composer cache.
- Submitting an email after OAuth sign-in is rate limited (5 attempts per 15 minutes per IP); the limit was configured on the route but never enforced.
- Password changes are rate limited per user (5 attempts per 15 minutes).
- Expected authentication redirects no longer log an error, and uncaught HTTP errors are logged once with secrets redacted.
- REST API answers 404 instead of 500 for malformed resource ids and unknown organization slugs.
- Admin REST user update rejects an email already in use with a validation error instead of a server error.
- A failed audit write no longer turns a successful REST write into a server error.
- Stuck jobs with no attempts left now fail instead of being requeued forever.
- Worker cleanup removes only finished jobs and does it in one query.
- Duplicate organization memberships (concurrent invitation accepts) are prevented by a unique index; the upgrade migration removes existing duplicates, keeping the owner row (otherwise the earliest).
- Two owners removing each other at the same time can no longer leave an organization without an owner.
- Auth forms (sign-in, registration, forgot and reset password, email after OAuth, email verification and resend) reject submissions with a filled hidden honeypot field the same way as a failed security challenge.
- The landing page and `sitemap.xml` use the installation's branding (product name, tagline, registry domain, `APP_URL`) instead of hard-coded Packio values.
- Redelivered webhooks retry a failed sync scheduling.
- The last active administrator can no longer lose the admin role.
- Interrupted source force-finalize is recorded in the audit log.
- The server-rendered page highlights the current section in the navigation, including on pages with a query string.
- The back button no longer starts a drag when pressed and dragged.
- The confirmation dialog focuses Cancel when it opens and returns focus to the previous element when it closes.

### Removed

- Removed the PHPUnit suites `e2e` and `default` and the composer scripts `test:e2e`, `test:fast` and `test:slow` (use `test:feature`, `test:base` and `test:concurrency`), along with unused test builders and provider HTTP fakes.
- Unsupported SQLite connection configuration and SQLite extensions in the development images.

### Security

- The Tokens page sends the full registry token values only to organization owners; members see masked tokens and cannot copy them.
- The REST API returns a source's `webhook_secret` only to tokens with the `sources:write` scope, so read-only tokens can no longer read it.
- Development Compose publishes PostgreSQL, Mailpit, Vite and SSR ports on 127.0.0.1 only.
- Responses now send `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff` and `Referrer-Policy: strict-origin-when-cross-origin`.
- The source page no longer sends audit entry context to organization members' browsers; the page displays only the event and time.
- Secrets are redacted in job logs and job failure messages.
- Signing in with OAuth to an unverified account with the same email now clears that account's password, so whoever registered the address first can no longer use it.
- Web sessions created before this release are rejected, so every user has to sign in again once after upgrading.
- Source webhook secrets are stored encrypted. Existing plaintext secrets keep working; run `source:webhook-secrets:encrypt` once after upgrading to encrypt them. If `APP_SECRET_ENCRYPTION_KEY` changes, stored webhook secrets can no longer be read and webhook deliveries are rejected until the secret is rotated.
- Organization invitation tokens are stored encrypted and looked up by a SHA-256 hash. The upgrade migration hashes existing tokens, so their links keep working; run `organization:invitations:encrypt` once after upgrading to encrypt them. If `APP_SECRET_ENCRYPTION_KEY` changes, invitation links still work but the members page lists those invitations without a link.
- Login OAuth tokens are no longer stored: the access token, refresh token and expiry columns of `user_oauth_accounts` are dropped (the data is not restored on rollback).
- GitLab, Codeberg and Bitbucket sign-in accepts only emails the provider has confirmed.
- Other sessions are signed out after a password change or reset.
- Webhook sync scheduling errors no longer expose internal error details.
- A malformed OAuth state or token id no longer causes a 500 error.
- Organization members no longer see invitation links; only owners can copy them.
- Accepting an invitation no longer changes the role of someone who is already a member.
- Server-side rendering errors are logged without the request body.
- Updated symfony/mime (CVE-2026-45070), symfony/filesystem and symfony/yaml to patched releases.
