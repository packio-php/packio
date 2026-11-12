#!/bin/sh
set -eu

tr '\0' ' ' < /proc/1/cmdline | grep -F "worker:run" >/dev/null
