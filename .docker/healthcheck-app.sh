#!/bin/sh
set -eu

APP_HEALTHCHECK_URL="${APP_HEALTHCHECK_URL:-http://localhost:80/health}"

curl -fsS "${APP_HEALTHCHECK_URL}" >/dev/null
