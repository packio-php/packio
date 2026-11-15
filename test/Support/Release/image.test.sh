#!/bin/sh
# Checks three built images: required files, release metadata, every exported layer and build provenance.
# Usage: sh test/Support/Release/image.test.sh APP_IMAGE WORKER_IMAGE SSR_IMAGE EXPECTED_SHA
# Needs docker with the containerd image store, images built with --provenance=true, jq and GNU tar.
set -eu

[ $# -eq 4 ] || { echo "usage: $0 APP_IMAGE WORKER_IMAGE SSR_IMAGE EXPECTED_SHA" >&2; exit 2; }
expected_sha=$4

fail() { echo "FAIL: $*" >&2; exit 1; }
for tool in docker jq tar sha256sum; do
  command -v "$tool" >/dev/null || fail "$tool is required"
done
printf %s "$expected_sha" | grep -Eqx '[0-9a-f]{40}' || fail "EXPECTED_SHA must be a full lowercase Git SHA"

work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT
trap 'exit 130' INT TERM

# A trailing slash forbids everything below the directory; other lines forbid that exact path.
# Anything starting with /app/.env is forbidden as well.
forbidden() { # TARGET
  cat <<'EOF'
/app/.git/
/app/local-plans/
/app/vendor/bin/phpunit
/app/data/sessions/
/app/data/logs/
/app/data/rate-limits/
/app/data/db/sqlite.db
/app/data/package-archives/
/app/config/development.config.php
/app/config/autoload/local.php
/app/config/autoload/development.local.php
/app/node_modules/
/app/test/
/app/tools/
/usr/local/bin/php-cgi
/usr/local/bin/phpdbg
/usr/local/bin/phpize
/usr/local/bin/php-config
/usr/local/bin/pear
/usr/local/bin/peardev
/usr/local/bin/pecl
/usr/local/include/
/app/bin/create-release-tag.php
/app/bin/with-postgres-test
EOF
  case "$1" in
    # Only the SSR image carries the bundle.
    prod | prod-worker) echo /app/data/ssr/ ;;
    # Any /opt content is rejected: a Node base image keeps yarn there in a versioned directory.
    prod-ssr) printf '%s\n' /usr/local/lib/node_modules/ /opt/ ;;
  esac
}

# Ceiling for the uncompressed bytes of all layers, base image included. Raise it only deliberately.
max_layer_megabytes() { # TARGET
  case "$1" in
    prod) echo 300 ;;
    prod-worker) echo 395 ;;
    prod-ssr) echo 220 ;;
  esac
}

in_image() { # IMAGE SCRIPT
  docker run --pull=never --rm --entrypoint sh "$1" -ec "$2"
}

# Prints the path of an exported blob after checking its content against the digest.
blob() { # EXPORT_DIR DIGEST
  hex=${2#sha256:}
  printf %s "$hex" | grep -Eqx '[0-9a-f]{64}' || fail "invalid blob digest: $2"
  [ -f "$1/blobs/sha256/$hex" ] || fail "missing blob: $2"
  [ "$(sha256sum < "$1/blobs/sha256/$hex" | cut -d' ' -f1)" = "$hex" ] || fail "blob content differs from $2"
  echo "$1/blobs/sha256/$hex"
}

check_layers() { # IMAGE TARGET EXPORT_DIR
  forbidden "$2" > "$work/forbidden"
  total=0
  for layer in $(jq -er '.[0].Layers[]' "$3/manifest.json"); do
    gzip -dcf "$3/$layer" > "$work/layer.tar"
    total=$((total + $(wc -c < "$work/layer.tar")))
    tar -tf "$work/layer.tar" | awk '
      NR == FNR { deny[$0]; next }
      {
        path = $0; sub(/^[.\/]*/, "/", path); sub(/\/$/, "", path)
        bad = index(path, "/app/.env") == 1 || (path in deny)
        for (entry in deny) if (entry ~ /\/$/ && index(path, entry) == 1) bad = 1
        if (bad) { print path; found = 1 }
      }
      END { exit found }' "$work/forbidden" - >&2 || fail "$1: forbidden path in $layer"
  done
  rm -f "$work/layer.tar"
  limit=$(($(max_layer_megabytes "$2") * 1024 * 1024))
  [ "$total" -le "$limit" ] || fail "$1: $total bytes of layers exceed the ceiling $limit"
}

# The BuildKit attestation stored next to the image must name this manifest, this target and this commit.
check_provenance() { # IMAGE TARGET EXPORT_DIR
  image_digest=$(docker image inspect -f '{{.Id}}' "$1")
  platform_os=$(docker image inspect -f '{{.Os}}' "$1")
  platform_arch=$(docker image inspect -f '{{.Architecture}}' "$1")
  jq -e --arg digest "$image_digest" 'any(.manifests[]; .digest == $digest)' "$3/index.json" >/dev/null \
    || fail "$1: the export is not bound to the resolved image ID"
  index=$(blob "$3" "$image_digest")
  runtime=$(jq -r --arg os "$platform_os" --arg arch "$platform_arch" '
    .manifests[]? | select(.annotations["vnd.docker.reference.type"] != "attestation-manifest"
      and .platform.os == $os and .platform.architecture == $arch) | .digest' "$index")
  [ "$(printf '%s\n' "$runtime" | grep -c .)" -eq 1 ] || fail "$1: expected one $platform_os/$platform_arch manifest"
  blob "$3" "$runtime" >/dev/null
  for attestation_digest in $(jq -r --arg runtime "$runtime" '
    .manifests[] | select(.annotations["vnd.docker.reference.type"] == "attestation-manifest"
      and .annotations["vnd.docker.reference.digest"] == $runtime) | .digest' "$index"); do
    attestation=$(blob "$3" "$attestation_digest")
    for statement_digest in $(jq -r --arg runtime "$runtime" '
      select(.subject.digest == $runtime) | .layers[] | select(.mediaType == "application/vnd.in-toto+json") | .digest' \
      "$attestation"); do
      statement=$(blob "$3" "$statement_digest")
      jq -e '.predicateType == "https://slsa.dev/provenance/v1"' "$statement" >/dev/null || continue
      jq -e --arg target "$2" --arg sha "$expected_sha" --arg runtime "${runtime#sha256:}" '
        .predicate.buildDefinition.externalParameters.request.root.request.args as $args
        | $args.target == $target and $args["vcs:revision"] == $sha
          and any(.subject[]; .digest.sha256 == $runtime)' "$statement" >/dev/null \
        || fail "$1: provenance does not name target $2, commit $expected_sha and this image manifest"
      return 0
    done
  done
  fail "$1: linked SLSA provenance for $2 is missing"
}

check_export() { # IMAGE TARGET
  export_dir="$work/$2"
  mkdir "$export_dir"
  docker save "$1" | tar -x -C "$export_dir"
  check_layers "$1" "$2" "$export_dir"
  check_provenance "$1" "$2" "$export_dir"
  rm -rf "$export_dir"
  echo "$2: clean layers and matching provenance"
}

check_app() { # IMAGE
  # shellcheck disable=SC2016
  in_image "$1" '
    test "$(id -u)" != 0
    test -f vendor/autoload.php
    test -f public/build/.vite/manifest.json
    test -f public/api-doc/assets/swagger-ui.css
    test -f data/db/cycle/seeds/RuntimeSettingsCatalogSeed.php
    test -d data/db/cycle/migrations
    php -r "require \"vendor/autoload.php\"; exit(class_exists(\"Packio\\\\Application\\\\Release\\\\AppReleaseVersion\") ? 0 : 1);"
    php -m | grep -qi pdo_pgsql
    php -m | grep -qi sodium
    test -w data/cache
    test -w data/package-archives
  ' || fail "$1: a required file, extension or writable directory is missing"
  in_image "$1" 'cat /app/data/build/release.json' > "$work/release.json"
  jq -e --arg sha "$expected_sha" '
    (.sha | type == "string" and test("^[0-9a-f]{7,}$")) and (.sha as $prefix | $sha | startswith($prefix))
    and .dirty == false' "$work/release.json" >/dev/null \
    || fail "$1: release metadata is not a clean build of $expected_sha: $(cat "$work/release.json")"
  echo "$1: $(jq -r '"\(.version) (\(.sha))"' "$work/release.json")"
}

ssr_scripts() { # IMAGE
  in_image "$1" 'sha256sum /usr/local/bin/entrypoint-ssr.sh /usr/local/bin/healthcheck-ssr.sh'
}

check_app "$1"
check_app "$2"
# shellcheck disable=SC2016
in_image "$3" 'test "$(id -u)" != 0 && test -f /app/data/ssr/ssr.mjs' || fail "$3: the SSR bundle is missing"
[ "$(ssr_scripts "$2")" = "$(ssr_scripts "$3")" ] || fail "SSR runtime scripts differ between the worker and SSR images"

check_export "$1" prod
check_export "$2" prod-worker
check_export "$3" prod-ssr

echo "PASS: files, metadata, layers and provenance match $expected_sha"
