# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Audit log retention: `audit:log:prune` deletes entries older than `AUDIT_LOG_RETENTION_DAYS` (default 180 days). The Docker `worker-cleanup` service runs it; other installations need a cron entry, see `docs/commands.md`.
- Admin actions are written to the audit log: user email and role changes, manual email verification, runtime setting changes, organization updates, and creating, revoking and deleting invitations.

### Fixed

- GitHub repository listing no longer fails when the token cannot read organizations (no `read:org` scope, or an SSO-protected organization); those organizations are skipped.
- Failed console commands are logged with secrets redacted; before, the raw exception message and trace were written to the log.
- The webhook secret of a source is no longer passed along with release archive download data.

## [0.1.0] - 2026-11-13

### Added

- Initial public release of Packio, a self-hosted private Composer package registry. The supported deployment scope is described in [docs/support.md](docs/support.md).
