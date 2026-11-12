#!/bin/sh
set -eu

tr '\0' ' ' < /proc/1/cmdline | grep -F "worker-cleanup-entrypoint.sh" >/dev/null
