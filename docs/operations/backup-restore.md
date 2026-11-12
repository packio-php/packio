# Backup and restore

This procedure targets one Linux amd64 host, PostgreSQL 17 and local filesystem
archives. A backup is recoverable only when its database, archives, original
secret key, configuration and version identity belong to the same installation.
The recovery rehearsal is a release gate; a successful `/health` response alone
proves only that the HTTP process is alive. Until a complete recovery report for
an exact candidate passes, treat restore and upgrade as unvalidated.

## Prepare and stop writes

Choose a maintenance window and a private backup destination with enough free
space. Set `umask 077`; restrict the backup directory to its operator. Database
dumps and archives contain private tenant data. Encrypt backups at rest and
restrict access and retention according to the installation's policy.

1. Record the full source SHA, release metadata, exact app/worker/SSR image IDs
   or digests, PostgreSQL version, applied migration identities and backup time.
   Save the resolved nonsecret configuration and the deployment configuration
   in protected storage. Keep credentials out of reports and shell history.
2. Close application and registry traffic at the reverse proxy, including
   webhooks and administrative writes. Stop scheduled scripts and other API/CLI
   writers. Merely removing a healthcheck or stopping the browser UI is
   insufficient. Verify the maintenance gate from outside the host.
3. Stop the `workers` service, including all its maintenance loops. Allow active
   jobs to finish within the configured shutdown grace period, or use the
   existing graceful shutdown and job lease/retry contract. Confirm no active
   consumers remain and inspect running jobs before snapshotting. Do not edit
   serialized jobs, reset attempts or replay external side effects blindly.
4. Stop `app` and any remaining process that can change the database or archive
   volume. Keep PostgreSQL running for the dump. Confirm the installation is
   quiescent; preserve pending/retrying jobs and deletion lifecycle state.

Use the installation's explicit Compose project, file and protected environment
file for every command. For the standalone example, after the proxy is closed:

```bash
docker compose --env-file .env.prod.local -f compose.prod.yaml stop workers app
```

Do not use `down -v`: it destroys database and archive volumes.

## Capture one backup set

Run PostgreSQL 17 `pg_dump --format=custom --no-owner --no-acl` against the
quiescent installation, using the container's existing credential environment
rather than a password argument. For the standalone example:

```bash
umask 077
mkdir -m 700 backup-set
docker compose --env-file .env.prod.local -f compose.prod.yaml exec -T postgres \
  sh -ec 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" --format=custom --no-owner --no-acl' \
  > backup-set/database.dump
```

Check the command exit status before proceeding. Validate the dump with
`pg_restore --list` from PostgreSQL 17, and save a SHA-256 checksum. While writers
remain stopped, snapshot the entire `package-archives` named volume, preserving
relative paths, permissions and file contents. Identify the actual volume from
the project's resolved mounts; never guess a shared volume name. Use an approved
local archiving tool/container to create the archive without mounting a source
checkout. Verify its member list and checksums and retain an archive inventory.
A database-only backup cannot restore private distributions. For this Compose
layout, obtain the stopped app's exact image and archive volume, then snapshot
that read-only mount with networking disabled:

```bash
app_container=$(docker compose --env-file .env.prod.local -f compose.prod.yaml ps --all --quiet app)
backup_app_image=$(docker inspect --format '{{.Image}}' "$app_container")
archive_volume=$(docker inspect --format '{{range .Mounts}}{{if eq .Destination "/app/data/package-archives"}}{{.Name}}{{end}}{{end}}' "$app_container")
test -n "$archive_volume"
docker run --rm --network none --read-only --user 0 --entrypoint tar \
  --mount "type=volume,source=$archive_volume,target=/archives,readonly" \
  "$backup_app_image" -cpf - -C /archives . > backup-set/archives.tar
docker compose --env-file .env.prod.local -f compose.prod.yaml exec -T postgres \
  pg_restore --list < backup-set/database.dump > backup-set/database.inventory
tar -tf backup-set/archives.tar > backup-set/archives.inventory
sha256sum backup-set/database.dump backup-set/archives.tar > backup-set/SHA256SUMS
sha256sum -c backup-set/SHA256SUMS
```

Check each command's exit status; do not run the snapshot if the exact app or
named volume cannot be identified. Keep the inventory private too. These examples
assume the stock image has `tar` and the archive mount is a named volume. A custom
layout requires the operator to identify and snapshot its actual storage.

Back up `APP_SECRET_ENCRYPTION_KEY` and secret deployment/provider configuration
in a separate protected store, linked to this backup set by a nonsecret identifier.
Keep the original key value and encoding. Do not print it, pass it as a CLI
argument or put it in the aggregate manifest. Test that the recovery operator can
retrieve it. Loss of this key prevents recovery of encrypted credentials and
queued authentication mail; generating a new key does not decrypt old data.

The nonsecret manifest records dump/archive hashes, source/schema/image identity,
PostgreSQL version, timestamp and the protected configuration/key reference.
Verify that every component exists before reopening the original installation.
A checksum establishes integrity, not confidentiality or a successful restore.

## Restore in a different project

1. Keep the original installation untouched. Create an isolated project with
   new database, archive, session and rate-limit volumes. Restore first using
   the exact version and image identities recorded in the backup, before any
   upgrade. Disable public routing, scheduled jobs and external provider/mail
   delivery; use isolated test endpoints when validating delivery. Keep all
   consumers stopped until the dataset is verified.
2. Retrieve the original key/config from protected storage. Check manifest
   hashes and archive inventory before loading anything. A missing key,
   mismatched key or missing/corrupt archive is a restore failure.
3. Restore the PostgreSQL custom dump into the empty PostgreSQL 17 database
   using `pg_restore --exit-on-error --no-owner --no-acl`. Restore the private
   archive snapshot to the new project's archive volume, preserving permissions.
   Never merge it into a live database or shared archive volume.
4. Reset transient state: generated application/mapper/schema caches, sessions
   and filesystem limiter data. Users must sign in again and limiter windows
   restart. Do not copy stale generated code across binary versions. Run that
   version's documented cache/warmup/startup procedure without starting consumers.
5. Verify encrypted credentials decrypt without revealing their values; verify
   tenant authorization and denial for anonymous, foreign and revoked tokens.
   Install the exact private package with Composer 2.8.x and a genuinely empty
   cache, and compare the installed archive/content against independent fixture
   expectations. Check pending/retry/system-mail/deletion state as well as
   database row counts. Check `/ready` on versions that provide it.
6. Resolve the [upgrade and rollback decision](upgrade.md) before reopening
   traffic or consumers. A failed check leaves both closed. Reconnect real
   external endpoints only after an operator has assessed retries and possible
   duplicate side effects.

Keep full dumps, keys, tokens and message bodies out of CI artifacts and aggregate
reports. A safe recovery report contains only version/image identities, hashes,
case results and elapsed time. Delete only resources created by the rehearsal;
never run global Docker prune or broad volume deletion.

No S3 recovery, live key rotation, arbitrary historical upgrade or zero downtime
claim follows from this runbook. See [support scope](../support.md).
