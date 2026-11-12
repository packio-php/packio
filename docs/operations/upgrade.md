# Upgrade and rollback

Use a maintenance window on the supported single-host PostgreSQL 17/local-archive
installation. The rehearsal baseline is the previous public release tag,
starting with `v0.1.0`. An upgrade path is supported only when a complete
recovery report exists for that exact baseline and the exact candidate images
and source SHA. Until a second public version exists, no upgrade path has been
rehearsed. This does not establish arbitrary historical upgrades or zero downtime.

## Before the candidate starts

1. Obtain and verify the candidate's immutable app, worker and SSR image IDs or
   digests and source identity. Preserve the old image identities, configuration
   and original `APP_SECRET_ENCRYPTION_KEY`. Build historical baseline images
   only in a clean isolated rehearsal context; do not distribute them as rollback
   artifacts or substitute old test images for the current candidate.
2. Close mutating traffic, webhook intake, schedulers and maintenance writers.
   Gracefully stop consumers and HTTP writers, resolving active jobs through
   their existing lease/idempotency rules. Take and verify a consistent
   [database, archives, key/config and identity backup](backup-restore.md).
3. Prove recovery on the backup version in another project before changing the
   installation. Keep external mail/provider side effects isolated during this
   validation. Include real private packages, encrypted credentials, retrying
   jobs, system authentication mail and deletion lifecycle state.
4. Establish the rollback outcome before traffic: test the old binary against
   the candidate schema and persisted job data, or require restoring the whole
   verified backup. Do not infer compatibility from an unchanged migration count,
   a successful process healthcheck or a dependency version alone.

## Apply with traffic and consumers closed

Start PostgreSQL and SSR for the chosen candidate while retaining the closed
proxy gate. Start the candidate app through its normal entrypoint: the standalone
Compose app runs migrations and `RuntimeSettingsCatalogSeed`; do not concurrently
run a second migration/seed command. Its startup also prepares application caches.
Warm the worker's object mappings before allowing any consumer to start.

If migration, seed, warmup or startup fails, stop the candidate app and keep
consumers and public traffic closed. Inspect the migration result and retained
backup before retrying. A partial migration can change schema even when startup
fails; restarting the old binary is not automatically safe. Do not continue jobs
against an unverified intermediate schema.

Before enabling normal workers or routing, verify candidate `/ready`, encrypted
credential access, tenant isolation, exact private Composer 2.8.x installation
with empty cache, and the saved queue/deletion state. Exercise a real worker only
in the isolated rehearsal, with external effects closed, to establish persisted
job compatibility. A new empty installation is insufficient upgrade evidence.
After all checks pass, an operator may enable consumers, observe queue progress,
and open the traffic gate. Readiness does not itself configure the reverse proxy.

## Roll back deliberately

Binary rollback is allowed only when the rehearsal demonstrates the old binary
can safely read/write the upgraded schema and process persisted jobs with the
same payload/authorization/idempotency contract. Record the exact tested source,
schema and image identities and verification results. Otherwise stop all writers
and restore the matching database, archive snapshot, original key and config
from the verified pre-upgrade backup, first on its original binary version.
Regenerate caches and reset sessions/limiter state as described in the restore
runbook. Recheck product behavior before reopening consumers or traffic.

If candidate traffic has already accepted new writes, restoring the earlier
backup loses those writes, including queue changes and archive additions.
Keep traffic closed and obtain an explicit operator decision on that data loss
or another recovery strategy. Never silently overwrite the installation, replay
mail/provider operations, or claim automatic rollback across incompatible data.

Preserve the encryption key between deployments. Changing an environment value
is not a supported live rotation/re-encryption procedure. S3 and multi-host
recovery are outside this release path.

## Run the isolated release rehearsal

With Docker, Python 3, the matching Playwright 1.62.1 browser image and the existing
frontend test dependencies available, supply the resolved local `sha256:…`
image IDs for the exact candidate; resolve tags or repository references first:

```bash
python3 test/Support/Release/recovery.test.py \
  --baseline-ref v0.1.0 \
  --app-image "$app_image" --worker-image "$worker_image" --ssr-image "$ssr_image" \
  --expected-sha "$candidate_sha" --report recovery-report.json
```

The baseline is any tag or commit of this repository other than the candidate
itself; the rehearsal builds its images from a clean clone of that ref.

A successful run must report every positive and expected negative case, real
private installation and readiness, correct source/image/hash identities, and
cleanup of all owned resources while a foreign sentinel remains untouched.
Skipped checks, missing infrastructure and failed cleanup are failures. Aggregate
reports must never include keys, database dumps, tokens or message bodies.
