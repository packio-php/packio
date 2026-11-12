#!/bin/sh
set -eu

tr '\0' ' ' < /proc/1/cmdline | grep -F "password-reset-cleanup-entrypoint.sh" >/dev/null
