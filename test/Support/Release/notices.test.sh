#!/bin/sh
# Check that the release images carry the license and notice files of this checkout.
set -eu

[ "$#" -eq 3 ] || { echo "Usage: $0 APP_IMAGE WORKER_IMAGE SSR_IMAGE" >&2; exit 64; }
cd "$(dirname "$0")/../../.."

notices="LICENSE THIRD_PARTY_NOTICES.md
public/fonts/LICENSE-JetBrainsMono.txt public/fonts/LICENSE-PlusJakartaSans.txt
public/api-doc/assets/LICENSE public/api-doc/assets/NOTICE public/api-doc/assets/THIRD_PARTY_NOTICES.md
public/api-doc/assets/swagger-ui-bundle.js.LICENSE.txt
public/api-doc/assets/swagger-ui-standalone-preset.js.LICENSE.txt"
protobuf=/usr/local/lib/php/doc/protobuf

# The Swagger supplement is written by hand for one swagger-ui-dist version.
swagger="$(jq -r '.packages["node_modules/swagger-ui-dist"].version' package-lock.json)"
grep -qxF "Applies to the distributed Swagger UI $swagger assets:" public/api-doc/assets/THIRD_PARTY_NOTICES.md || {
    echo "public/api-doc/assets/THIRD_PARTY_NOTICES.md does not describe swagger-ui-dist $swagger" >&2
    exit 1
}

# shellcheck disable=SC2086
expected="$(sha256sum $notices)"

# check IMAGE FILE...: the notices match the checkout and each FILE is present and not empty.
check() {
    image="$1"
    shift
    # shellcheck disable=SC2086
    actual="$(docker run --rm --pull=never --workdir /app --entrypoint sha256sum "$image" $notices)" || {
        echo "$image: a license or notice file is missing" >&2
        exit 1
    }
    [ "$actual" = "$expected" ] || {
        echo "$image: license or notice files differ from the checkout" >&2
        exit 1
    }
    docker run --rm --pull=never --entrypoint sh "$image" -ec \
        'for file; do test -s "$file" || { echo "missing $file" >&2; exit 1; }; done
         ls /usr/share/doc/*/copyright > /dev/null' sh "$@" /usr/share/common-licenses/GPL-2 || {
        echo "$image: a retained upstream license is missing" >&2
        exit 1
    }
}

check "$1" "$protobuf/LICENSE" "$protobuf/third_party/utf8_range/LICENSE"
check "$2" "$protobuf/LICENSE" "$protobuf/third_party/utf8_range/LICENSE"
check "$3" /usr/local/LICENSE
echo "license and notice files verified in all three images (swagger-ui-dist $swagger)"
