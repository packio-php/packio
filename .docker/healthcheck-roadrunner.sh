#!/bin/sh
set -eu

if ! curl --max-time 2 -fsS http://127.0.0.1:2114/health >/dev/null; then
  echo "RoadRunner status endpoint is unavailable." >&2
  exit 1
fi

# Default remains process liveness. Compose explicitly gates traffic on readiness.
case "${1:-}" in
  --ready) endpoint="${APP_READINESS_URL:-http://127.0.0.1:80/ready}" ;;
  "") endpoint="${APP_HEALTHCHECK_URL:-http://127.0.0.1:80/health}" ;;
  *) exit 1 ;;
esac
curl --max-time 2 -fsS "$endpoint" >/dev/null
