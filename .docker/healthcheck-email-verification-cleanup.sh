#!/bin/sh
set -eu

tr '\0' ' ' < /proc/1/cmdline | grep -F "email-verification-cleanup-entrypoint.sh" >/dev/null
