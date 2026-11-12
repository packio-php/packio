# Support scope

This page lists what Packio supports. Anything not listed here is not supported.

| Area | Supported |
| --- | --- |
| Deployment | Self-hosted Linux containers on one host, amd64 |
| HTTP and jobs | RoadRunner app, `prod-worker` services, SSR in the production Compose stack |
| Alternative runtime | None. FrankenPHP and the standalone worker/SSR images are experimental (see below) |
| Database | PostgreSQL 17 |
| PHP | `~8.5.0` (8.5.x only) |
| Node | 24.21.0 for the image build and local frontend tooling |
| Composer client | Composer 2.8.x |
| Package archives | Local filesystem in the `package-archives` named volume (`PACKAGE_ARCHIVE_STORAGE_DRIVER=local`); back up this volume |
| S3 archives | Experimental |
| Installation | Production: `compose.prod.yaml` from a clean checkout. Local development: `compose.yaml` with bind mounts and Vite |

## Experimental

FrankenPHP is the local development default in `compose.yaml`, but it has no supported production target. The standalone worker and SSR Dockerfiles are development images. S3 is available as an opt-in package archive backend, but it is not a supported configuration.

## Not supported

PostgreSQL 17 is the only supported database. The default is `postgres`, and any other `DEFAULT_DB_CONNECTION` fails with `Packio supports PostgreSQL only for this release.` before any connection or migration starts. SQLite is not supported. arm64 is not supported. Building from a source archive without Git is not supported.

## Images

Prebuilt images are not published. Build them from source by following [docker.md](docker.md).

## Backup and upgrades

A backup must include the database, the private package archives and the original encryption key, together with your configuration. Follow the [backup and restore](operations/backup-restore.md) and [upgrade and rollback](operations/upgrade.md) runbooks. The upgrade baseline is the previous public release tag, starting with `v0.1.0`. Live key rotation, upgrades from arbitrary older versions, zero downtime and S3 recovery are not covered.

## Where to get help

Open an issue at [packio-php/packio](https://github.com/packio-php/packio/issues). For a security vulnerability, do not open a public issue; follow [the security policy](../SECURITY.md).
