#!/bin/sh
# End-to-end check of three built images: a private Composer install through the documented Caddy edge.
# Usage: sh test/Support/Release/product.test.sh APP_IMAGE WORKER_IMAGE SSR_IMAGE
# Needs docker, git, openssl, jq, `npm ci` done in the checkout, and Internet access for the Turnstile step.
set -eu

[ $# -eq 3 ] || { echo "usage: $0 APP_IMAGE WORKER_IMAGE SSR_IMAGE" >&2; exit 2; }
cd "$(dirname "$0")/../../.."

fail() { echo "FAIL: $*" >&2; exit 1; }
for tool in docker git openssl jq; do
  command -v "$tool" >/dev/null || fail "$tool is required"
done
[ -d node_modules/@playwright/test ] || fail "run npm ci first"

work=$(mktemp -d)
state="$work/state"
project="packio-product-$$"
package=packio-fixture/private-package
fixture=test/Support/Release/fixtures/private-package
export APP_IMAGE="$1" WORKER_IMAGE="$2" SSR_IMAGE="$3"
export PRODUCT_DIR="$work" PRODUCT_ORG_SLUG=fixture-org
PRODUCT_USER="$(id -u):$(id -g)"
APP_SECRET_ENCRYPTION_KEY=$(openssl rand -hex 32)
DATABASE_PASS=$(openssl rand -hex 16)
export PRODUCT_USER APP_SECRET_ENCRYPTION_KEY DATABASE_PASS APP_URL=https://app.fixture.test
registry="$PRODUCT_ORG_SLUG.registry.fixture.test"

compose() {
  docker compose -p "$project" -f compose.prod.yaml -f test/Support/Release/compose.product.yaml "$@"
}
cleanup() {
  status=$?
  # Secrets here are random and die with the fixture, so the logs are safe to show.
  [ "$status" -eq 0 ] || compose logs --no-color --tail 60 app workers tls >&2 || true
  compose --profile tools down -v --remove-orphans >/dev/null 2>&1 || true
  rm -rf "$work"
}
trap cleanup EXIT
trap 'exit 130' INT TERM

up() { compose up -d --no-build --wait --wait-timeout 300 "$@"; }
pw() {
  echo "== browser: $1"
  compose run --rm -T playwright node node_modules/@playwright/test/cli.js test \
    -c test/Support/Release/playwright.config.ts --grep "$1"
}
address() { docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' "$(compose ps -q "$1")"; }
sql() { compose exec -T postgres psql -U packio -d packio -Atqc "$1"; }
wait_sql() { # QUERY EXPECTED LABEL
  tries=0
  until [ "$(sql "$1")" = "$2" ]; do
    tries=$((tries + 1))
    [ "$tries" -lt 180 ] || fail "timed out waiting for $3 (last: $(sql "$1"))"
    sleep 1
  done
}

# Disposable CA and one certificate for the app, registry and provider hosts.
mkdir "$state"
(
  cd "$work"
  openssl req -x509 -newkey rsa:2048 -nodes -days 1 -subj "/CN=Packio fixture CA" \
    -keyout ca-key.pem -out root-ca.pem 2>/dev/null
  openssl req -new -newkey rsa:2048 -nodes -subj /CN=app.fixture.test \
    -keyout server-key.pem -out server.csr 2>/dev/null
  printf 'subjectAltName=DNS:app.fixture.test,DNS:*.registry.fixture.test,DNS:provider.fixture.test\nextendedKeyUsage=serverAuth\n' > leaf.ext
  openssl x509 -req -in server.csr -CA root-ca.pem -CAkey ca-key.pem -CAcreateserial -days 1 \
    -extfile leaf.ext -out server-cert.pem 2>/dev/null
  # The containers read these as other users; nothing here outlives the run.
  chmod 644 root-ca.pem server-cert.pem server-key.pem
  printf 'curl.cainfo=/run/fixture/root-ca.pem\nopenssl.cafile=/run/fixture/root-ca.pem\n' > fixture-ca.ini
  : > local.php
)

# The private package as a tagged Git repository served over dumb HTTP.
cp -r "$fixture" "$work/package"
(
  cd "$work/package"
  export GIT_CONFIG_GLOBAL=/dev/null GIT_CONFIG_SYSTEM=/dev/null
  export GIT_AUTHOR_NAME=Fixture GIT_AUTHOR_EMAIL=fixture@example.invalid
  export GIT_COMMITTER_NAME=Fixture GIT_COMMITTER_EMAIL=fixture@example.invalid
  git init -q -b main
  git add .
  git commit -q -m "fixture package 1.0.0"
  git tag 1.0.0
  git clone -q --bare . "$work/provider/private-package"
  git --git-dir "$work/provider/private-package" update-server-info
)
for ref in main 1.0.0; do
  mkdir -p "$work/provider/private-package/raw/$ref"
  cp "$fixture/composer.json" "$work/provider/private-package/raw/$ref/"
done

# The app trusts exactly the edge address and may call exactly the provider address.
up postgres mailpit provider tls
PRODUCT_TRUSTED_PROXIES=$(address tls)
export PRODUCT_TRUSTED_PROXIES
printf "<?php\nreturn ['outbound_http' => ['endpoints' => ['https://provider.fixture.test' => ['%s']]]];\n" \
  "$(address provider)" > "$work/local.php"

up app ssr
pw @no-worker
up workers
pw @identity
compose exec -T app php vendor/bin/laminas user:make-admin "$(jq -er .email "$state/user.json")"
pw @organization

failed_source=$(jq -er .failed "$state/sources.json")
source=$(jq -er .private "$state/sources.json")
latest_sync="SELECT status FROM jobs WHERE type='source.sync' AND payload->>'source_id'='%s' ORDER BY created_at DESC LIMIT 1"
# shellcheck disable=SC2059
wait_sql "$(printf "$latest_sync" "$failed_source")" failed "the failed sync of a missing repository"
wait_sql "SELECT status FROM jobs WHERE type='store.release.archive' AND payload->>'package_name'='$package' ORDER BY created_at DESC LIMIT 1" \
  completed "the stored archive of $package"

# A fresh worker container must pick up new jobs.
compose up -d --no-build --force-recreate --wait --wait-timeout 300 workers
pw @resync
wait_sql "SELECT count(*) FROM jobs WHERE type='source.sync' AND status='completed' AND payload->>'source_id'='$source'" \
  2 "the sync after worker recreation"

pw @registry

composer_install() { # CASE [TOKEN [AUTH_HOST]]; output lands in $case/output.txt
  case="$work/composer-$1"
  mkdir -p "$case/project" "$case/home"
  jq -n --arg url "https://$registry/private/packages.json" --arg package "$package" \
    '{name: "packio-fixture/install-check", repositories: [{type: "composer", url: $url}, {"packagist.org": false}], require: {($package): "1.0.0"}}' \
    > "$case/project/composer.json"
  if [ -n "${2:-}" ]; then
    jq -n --arg host "${3:-$registry}" --arg token "$2" \
      '{"http-basic": {($host): {username: "token", password: $token}}}' > "$case/home/auth.json"
  fi
  compose run --rm -T -v "$case:/case" composer install --no-interaction --prefer-dist --no-progress --no-ansi \
    > "$case/output.txt" 2>&1
}
composer_denied() { # HTTP_STATUS CASE [TOKEN [AUTH_HOST]]
  expected=$1
  shift
  echo "== composer: $1 is denied with $expected"
  if composer_install "$@"; then fail "Composer case $1 installed the private package"; fi
  grep -Eq "HTTP[^ ]* $expected" "$work/composer-$1/output.txt" || {
    cat "$work/composer-$1/output.txt" >&2
    fail "Composer case $1 was not refused with HTTP $expected"
  }
}
token() { jq -er ".$1" "$state/tokens.json"; }

echo "== composer: install with a token"
composer_install install "$(token install)" || { cat "$work/composer-install/output.txt" >&2; fail "Composer install failed"; }
installed="$work/composer-install/project/vendor"
for file in composer.json src/Probe.php; do
  cmp -s "$fixture/$file" "$installed/$package/$file" || fail "installed $file differs from the fixture"
done
jq -e --arg package "$package" \
  '(.packages // .)[] | select(.name == $package) | .version == "1.0.0" and ."installation-source" == "dist"' \
  "$installed/composer/installed.json" >/dev/null || fail "the package was not installed from the registry dist"
probe=$(compose run --rm -T -v "$work/composer-install:/case" composer \
  php -r 'require "vendor/autoload.php"; echo \PackioFixture\Probe::value();')
[ "$probe" = fixture-1.0.0 ] || fail "installed package returned '$probe'"

composer_denied 401 anonymous
composer_denied 403 foreign-org "$(token foreign)"
composer_denied 401 revoked "$(token revoked)"
composer_denied 401 wrong-auth-host "$(token install)" wrong-auth.fixture.test

pw @edge
export PRODUCT_TURNSTILE=true
up app
pw @turnstile

echo "PASS: private Composer install, tenant isolation and the documented edge on $APP_IMAGE"
