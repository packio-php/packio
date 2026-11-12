#!/bin/sh
set -eu

cd /app
umask 077
mkdir -p /app/data/cache /app/data/rate-limits

# PHP hard limit must exceed --memory-limit, otherwise the soft restart never happens.
set -- php -d memory_limit=1G vendor/bin/laminas worker:run \
  --timeout "${WORKER_TIMEOUT:-30}" \
  --memory-limit "${WORKER_MEMORY_LIMIT:-512M}" \
  --max-jobs "${WORKER_MAX_JOBS:-100}"

if [ -n "${WORKER_ID:-}" ]; then
  set -- "$@" --worker-id "${WORKER_ID}"
fi

exec "$@"
