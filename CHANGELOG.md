# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- GitHub repository listing no longer fails when the token cannot read organizations (no `read:org` scope, or an SSO-protected organization); those organizations are skipped.

## [0.1.0] - 2026-11-13

### Added

- Initial public release of Packio, a self-hosted private Composer package registry. The supported deployment scope is described in [docs/support.md](docs/support.md).
