#!/bin/sh
# One-shot dev setup: node_modules/ and the SSR bundle.
set -eu
cd "${1:-/app}"

if [ ! node_modules/.package-lock.json -nt package-lock.json ]; then
    npm ci --no-audit --no-fund
fi
if [ ! -f data/ssr/ssr.mjs ]; then
    npm run build:ssr
fi
