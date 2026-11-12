# Support scope for the first public release

The local **preview** draft `1.17.0-dev.12+5bbe7ca` targets the matrix below. Its frozen candidate is `5bbe7ca75c925681cfb807aa3bc0e0b702e00c65`; [draft notes](releases/1.17.0-dev.12+5bbe7ca.md) record the verified checks, artifact identities and limitations. Historical: the verification results in this file were run on candidate `5bbe7ca` before the architecture refactor and have not been re-run since. Publication remains a separate owner action. **Stable** additionally requires fresh same-candidate restore, upgrade, readiness and limiter drills, together with the required stable artifact evidence.

| Area | First-release target | Current verification / status |
| --- | --- | --- |
| Deployment | Self-hosted Linux containers on one host, amd64 | Production target: `compose.prod.yaml`; fresh candidate final-image/startup and isolated production-stack checks passed. arm64 is outside this release matrix |
| HTTP and jobs | RoadRunner app, `prod-worker` services, SSR in the production Compose stack | Fresh candidate final-image, cold-worker, services, SSR and real proxy/browser checks passed |
| Alternative runtime | FrankenPHP and legacy standalone worker/SSR images | Experimental development paths; incomplete legacy `prod` targets were removed, and production support requires new self-contained images and smoke checks |
| Database | PostgreSQL 17 | `postgres:17-alpine` in production Compose; isolated `postgres-test` uses tmpfs with no persisted data |
| PHP | `~8.5.0` (8.5.x only) | Root Composer constraint; fresh host and candidate runtime checks used PHP 8.5.11; the PHP build-stage base is pinned by digest |
| Node | 24.21.0 for image build and local frontend tooling | `.nvmrc`, `package.json` `engines.node`, and `node:24.21.0-bookworm-slim` in production Dockerfile pin the supported target |
| Composer client | Composer 2.8.x for release rehearsal | Fresh real private install/repeat and denial controls passed with the Composer 2.8 fixture client |
| Package archives | Local filesystem in the `package-archives` named volume | Default `PACKAGE_ARCHIVE_STORAGE_DRIVER=local`; backup must include this volume |
| S3 archives | Experimental | Opt-in contract exists; no active release contract job yet |
| Installation | Production: `compose.prod.yaml` from a clean checkout; local development: `compose.yaml` with bind mounts and Vite | Fresh independent equivalent onboarding passed through the public product harness with a new project/volumes, real TLS/SMTP/Git/jobs/private Composer install. Local development is not a production image test |

The candidate images use Debian Bookworm, PHP 8.5.11 and RoadRunner `2025.1.15`; the RoadRunner and PHP build-stage bases are pinned by digest. The native release manifest records the actual resolved build-base digests. PostgreSQL 17, Node 24.21.0 and a Composer 2.8 client were used for the fresh candidate rehearsal. The PostgreSQL fixture remains the `postgres:17-alpine` series image.

FrankenPHP remains the local development default in `compose.yaml`, but it has no supported production target. The legacy standalone worker and SSR Dockerfiles are development images; their old incomplete production targets have been removed. PostgreSQL is the only accepted database configuration: the default is `postgres`, and any other `DEFAULT_DB_CONNECTION` fails with `Packio supports PostgreSQL only for this release.` before connections or migrations begin. SQLite is not supported. S3 code remains available as an experimental archive backend; the opt-in contract test does not establish release support.

The planned public source repository and issue tracker are [packio-php/packio](https://github.com/packio-php/packio). Source publication is a separate owner action; the local draft does not establish public repository availability. Images are planned for the Docker Hub namespace `sirix777`; this coordinate does not mean images have been published. The current private security reporting channel is documented in [the security policy](../SECURITY.md). Example domains are not release contacts or published artifacts.

Backup must include the database, private archives and original encryption key
with protected configuration and source/schema/image identity. Follow the
[backup/restore](operations/backup-restore.md) and
[upgrade/rollback](operations/upgrade.md) runbooks. The upgrade baseline
is the previous public release tag, starting with `v0.1.0`; an actual
nonempty-data rehearsal on the exact candidate is required before claiming this
path validated. Health alone is insufficient. Live key rotation, arbitrary
historical upgrades, zero downtime and S3 recovery are outside this claim.
