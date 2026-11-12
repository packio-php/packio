#!/bin/sh
set -eu

cd /app

SSR_BUNDLE_PATH="${INERTIA_SSR_BUNDLE:-data/ssr/ssr.mjs}"

case "$SSR_BUNDLE_PATH" in
  /*) SSR_BUNDLE_ABSOLUTE_PATH="$SSR_BUNDLE_PATH" ;;
  *) SSR_BUNDLE_ABSOLUTE_PATH="/app/${SSR_BUNDLE_PATH}" ;;
esac

if [ ! -f "${SSR_BUNDLE_ABSOLUTE_PATH}" ]; then
  echo "Inertia SSR bundle is missing at ${SSR_BUNDLE_ABSOLUTE_PATH}" >&2
  exit 1
fi

echo "Starting Inertia SSR runtime from ${SSR_BUNDLE_ABSOLUTE_PATH} on ${INERTIA_SSR_HOST}:${INERTIA_SSR_PORT}" >&2

exec node "${SSR_BUNDLE_ABSOLUTE_PATH}"
