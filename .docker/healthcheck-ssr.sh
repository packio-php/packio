#!/bin/sh
set -eu

SSR_RENDER_URL="${INERTIA_SSR_URL:-http://127.0.0.1:13714/render}"
SSR_HEALTH_URL="${INERTIA_SSR_HEALTH_URL:-${SSR_RENDER_URL%/render}/health}"

exec node -e 'fetch(process.argv[1], { signal: AbortSignal.timeout(4000) }).then((r) => process.exit(r.ok ? 0 : 1), () => process.exit(1))' "${SSR_HEALTH_URL}"
