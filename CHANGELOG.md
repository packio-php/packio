# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

### Removed

- The legacy job type `increment.install.count`. Before upgrading, make sure the queue has no such jobs: `SELECT COUNT(*) FROM jobs WHERE type = 'increment.install.count' AND status IN ('pending', 'running');` must return 0. A job of this type left in the queue fails after the upgrade.

## [0.1.0] - 2026-11-13

### Added

- Initial public release of Packio, a self-hosted private Composer package registry. The supported deployment scope is described in [docs/support.md](docs/support.md).
