#!/bin/sh
set -eu

cd /app

if [ "$#" -gt 0 ]; then
  exec "$@"
fi

rm -f /app/data/cache/config-cache.php /app/data/cache/cycle/schema.php
mkdir -p /app/data/cache/proxies /app/data/rate-limits /app/data/sessions /app/data/db/cycle/migrations /app/data/db/cycle/seeds

# RR expands these values from its configuration. Keep defaults here as well so
# direct `rr serve` invocations have the same conservative operational limits.
: "${RR_NUM_WORKERS:=2}"
: "${RR_MAX_JOBS:=500}"
: "${RR_MAX_QUEUE_SIZE:=256}"
: "${RR_MAX_WORKER_MEMORY_MB:=256}"
: "${RR_ALLOCATE_TIMEOUT:=60s}"
: "${RR_DESTROY_TIMEOUT:=60s}"
: "${RR_EXEC_TTL:=60s}"
: "${RR_RELAY_TIMEOUT:=60s}"
: "${RR_MAX_REQUEST_SIZE_MB:=32}"
export RR_NUM_WORKERS RR_MAX_JOBS RR_MAX_QUEUE_SIZE RR_MAX_WORKER_MEMORY_MB RR_ALLOCATE_TIMEOUT RR_DESTROY_TIMEOUT RR_EXEC_TTL RR_RELAY_TIMEOUT RR_MAX_REQUEST_SIZE_MB

php bin/generate-release-metadata.php

/usr/local/bin/run-migrations.sh

exec /usr/bin/rr serve -c /app/config/roadrunner/.rr.yaml
