#!/bin/sh
# Print the image tags for a release tag ref (exact version first), or exit 1 for any other ref.
set -eu

ref=${1:-}
# grep matches per line, so a multi-line value must be rejected first.
case "$ref" in
    *'
'*) exit 1 ;;
esac
n='(0|[1-9][0-9]*)'
if ! printf '%s\n' "$ref" | grep -Eq "^refs/tags/v$n\\.$n\\.$n\$"; then
    exit 1
fi

version=${ref#refs/tags/v}
printf '%s %s latest\n' "$version" "${version%.*}"
