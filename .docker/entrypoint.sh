#!/bin/sh
set -eu

cd /app

if [ "$#" -gt 0 ]; then
  exec "$@"
fi

rm -f /app/data/cache/config-cache.php /app/data/cache/cycle/schema.php
mkdir -p /app/data/cache /app/data/rate-limits /app/data/sessions /app/data/db/cycle/migrations /app/data/db/cycle/seeds

/usr/local/bin/run-migrations.sh

exec /usr/bin/frankenphp run --config /etc/caddy/Caddyfile
