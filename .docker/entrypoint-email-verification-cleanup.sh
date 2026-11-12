#!/bin/sh
set -eu

cd /app

. /usr/local/bin/maintenance-process.sh

mkdir -p /app/data/cache

INTERVAL="${EMAIL_VERIFICATION_CLEANUP_INTERVAL_SECONDS:-3600}"

while true; do
  run_maintenance php vendor/bin/laminas email-verification:cleanup
  sleep_maintenance "$INTERVAL"
done
