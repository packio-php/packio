#!/bin/sh
set -eu

cd /app

. /usr/local/bin/maintenance-process.sh

mkdir -p /app/data/cache

INTERVAL="${SOURCE_OAUTH_REFRESH_INTERVAL_SECONDS:-86400}"
LIMIT="${SOURCE_OAUTH_REFRESH_LIMIT:-100}"

while true; do
  set -- php vendor/bin/laminas source:oauth:refresh-maintenance --limit "$LIMIT"

  if [ "${SOURCE_OAUTH_REFRESH_PROVIDER:-}" != "" ]; then
    set -- "$@" --provider "$SOURCE_OAUTH_REFRESH_PROVIDER"
  fi

  if [ "${SOURCE_OAUTH_REFRESH_PROVIDER_INSTANCE:-}" != "" ]; then
    set -- "$@" --provider-instance "$SOURCE_OAUTH_REFRESH_PROVIDER_INSTANCE"
  fi

  if [ "${SOURCE_OAUTH_REFRESH_ORGANIZATION:-}" != "" ]; then
    set -- "$@" --organization "$SOURCE_OAUTH_REFRESH_ORGANIZATION"
  fi

  if [ "${SOURCE_OAUTH_REFRESH_BEFORE:-}" != "" ]; then
    set -- "$@" --refresh-before "$SOURCE_OAUTH_REFRESH_BEFORE"
  fi

  if [ "${SOURCE_OAUTH_REFRESH_IDLE_BEFORE:-}" != "" ]; then
    set -- "$@" --idle-before "$SOURCE_OAUTH_REFRESH_IDLE_BEFORE"
  fi

  if [ "${SOURCE_OAUTH_REFRESH_MINIMUM_INTERVAL:-}" != "" ]; then
    set -- "$@" --minimum-refresh-interval "$SOURCE_OAUTH_REFRESH_MINIMUM_INTERVAL"
  fi

  run_maintenance "$@"
  sleep_maintenance "$INTERVAL"
done
