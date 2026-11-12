#!/bin/sh
set -eu

SSR_RENDER_URL="${INERTIA_SSR_URL:-http://127.0.0.1:13714/render}"
SSR_HEALTH_URL="${INERTIA_SSR_HEALTH_URL:-${SSR_RENDER_URL%/render}/health}"

curl -fsS "${SSR_HEALTH_URL}" >/dev/null
