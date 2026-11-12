#!/bin/sh
set -eu

if [ "${RUN_MIGRATIONS:-true}" != "true" ]; then
  exit 0
fi

migration_attempt=1
migration_attempts="${MIGRATION_ATTEMPTS:-12}"
migration_retry_seconds="${MIGRATION_RETRY_SECONDS:-5}"

while true; do
  echo "Running database migrations (attempt ${migration_attempt}/${migration_attempts})..."

  if php vendor/bin/laminas cycle:migration:run -n; then
    echo "Database migrations completed."
    break
  fi

  if [ "${migration_attempt}" -ge "${migration_attempts}" ]; then
    echo "Database migrations failed after ${migration_attempts} attempts." >&2
    exit 1
  fi

  migration_attempt=$((migration_attempt + 1))
  echo "Database migrations failed. Retrying in ${migration_retry_seconds}s..." >&2
  sleep "${migration_retry_seconds}"
done

echo "Syncing runtime settings catalog..."
if ! php vendor/bin/laminas cycle:seed:run RuntimeSettingsCatalogSeed -n; then
  echo "Runtime settings catalog seed failed." >&2
  exit 1
fi
echo "Runtime settings catalog synced."
