#!/bin/sh
set -eu

cd /app
umask 077
/usr/local/bin/warmup-object-mapper.sh
exec /usr/local/bin/run-worker.sh
