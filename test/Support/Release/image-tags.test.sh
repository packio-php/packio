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
expect refs/tags/v1.2.3.4 '' 1
expect refs/tags/vfoo '' 1
expect 'refs/tags/v1.2.3 ' '' 1
expect refs/tags/v1.2.3x '' 1
expect xrefs/tags/v1.2.3 '' 1
expect 'refs/tags/v1.2.3
publish=true' '' 1
expect refs/tags/v01.2.3 '' 1
expect refs/tags/v1.02.3 '' 1
expect refs/tags/v1.2.03 '' 1
expect -n '' 1

echo 'image-tags: all expectations passed'
