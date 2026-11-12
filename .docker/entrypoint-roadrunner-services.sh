#!/bin/sh
set -eu

cd /app
umask 077
: "${PACKIO_WORKER_NUM:=7}"
case "$PACKIO_WORKER_NUM" in
  ''|*[!0-9]*|0*) echo "PACKIO_WORKER_NUM must be a positive integer." >&2; exit 1 ;;
esac
export PACKIO_WORKER_NUM
# A shared explicit ID would collide across the service's worker processes.
unset WORKER_ID

rm -f data/cache/config-cache.php data/cache/cycle/schema.php
mkdir -p data/cache/proxies data/rate-limits
php bin/generate-release-metadata.php
/usr/local/bin/warmup-object-mapper.sh

exec /usr/bin/rr serve -c /app/config/roadrunner/services.yaml
