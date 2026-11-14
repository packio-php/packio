#!/bin/sh
# Print the image tags for a release tag ref (exact version first), or exit 1 for any other ref.
set -eu

ref=${1:-}
if ! printf '%s\n' "$ref" | grep -Eq '^refs/tags/v([0-9]+)\.([0-9]+)\.([0-9]+)$'; then
    exit 1
fi

version=${ref#refs/tags/v}
printf '%s %s latest\n' "$version" "${version%.*}"
