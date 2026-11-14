#!/bin/sh

set -eu

project_root=$(CDPATH= cd "$(dirname "$0")/../../.." && pwd)
script="$project_root/.github/scripts/image-tags.sh"

expect() {
    ref=$1
    want_output=$2
    want_status=$3
    status=0
    output=$(sh "$script" "$ref") || status=$?
    if [ "$output" != "$want_output" ] || [ "$status" -ne "$want_status" ]; then
        printf 'FAIL: ref "%s": expected "%s" (exit %s), got "%s" (exit %s)\n' \
            "$ref" "$want_output" "$want_status" "$output" "$status" >&2
        exit 1
    fi
}

expect refs/tags/v0.3.1 '0.3.1 0.3 latest' 0
expect refs/tags/v10.20.30 '10.20.30 10.20 latest' 0
expect refs/tags/v0.4.0-rc.1 '' 1
expect refs/tags/v0.3 '' 1
expect refs/tags/0.3.1 '' 1
expect refs/heads/main '' 1
expect '' '' 1

echo 'image-tags: all expectations passed'
