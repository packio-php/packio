#!/bin/sh
set -eu

tr '\0' ' ' < /proc/1/cmdline | grep -F "source-oauth-refresh-entrypoint.sh" >/dev/null
