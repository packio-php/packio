#!/bin/sh
set -eu

cd /app
umask 077
mkdir -p data/cache

# Do not emit configuration values or mapping definitions on startup failure.
# The command must finish before any queue or maintenance process is started.
if ! php vendor/bin/laminas object-mapper:warmup >/dev/null 2>&1; then
  echo 'Object mapper warmup failed; worker services were not started.' >&2
  exit 1
fi

echo 'Object mapper warmup completed.' >&2
