#!/bin/sh
set -eu

cd /app

. /usr/local/bin/maintenance-process.sh

mkdir -p /app/data/cache

INTERVAL="${WORKER_CLEANUP_INTERVAL_SECONDS:-3600}"
RETENTION_DAYS="${WORKER_CLEANUP_RETENTION_DAYS:-30}"

while true; do
  run_maintenance php vendor/bin/laminas worker:cleanup --days "$RETENTION_DAYS"
  run_maintenance php vendor/bin/laminas audit:log:prune
  sleep_maintenance "$INTERVAL"
done
