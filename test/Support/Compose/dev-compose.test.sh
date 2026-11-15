#!/bin/sh
# Check the one-shot dev setup scripts and the dev compose wiring.
set -eu

root=$(CDPATH= cd "$(dirname "$0")/../../.." && pwd)
tmp=$(mktemp -d)
trap 'rm -rf "$tmp"' EXIT

fail() {
    echo "FAIL: $*" >&2
    exit 1
}

# fake NAME EXIT_CODE: a command that logs its arguments.
fake() {
    printf '#!/bin/sh\necho "%s $*" >> "%s"\nexit %s\n' "$1" "$tmp/log" "$2" > "$tmp/bin/$1"
    chmod +x "$tmp/bin/$1"
}

# fresh: an empty checkout with fake composer and npm.
fresh() {
    rm -rf "$tmp/app" "$tmp/bin"
    mkdir "$tmp/app" "$tmp/bin"
    : > "$tmp/log"
    fake composer 0
    fake npm 0
}

# aged PATH SECONDS: create a file last modified SECONDS ago.
aged() {
    mkdir -p "$(dirname "$tmp/app/$1")"
    touch -d "$2 seconds ago" "$tmp/app/$1"
}

setup() {
    PATH="$tmp/bin:$PATH" sh "$root/.docker/$1" "$tmp/app"
}

logged() {
    grep -qF -- "$1" "$tmp/log"
}

php_up_to_date() {
    aged vendor/autoload.php 0
    aged composer.lock "$1"
    aged vendor/composer/installed.json "$2"
    aged config/development.config.php 0
}

fresh
setup setup-dev-php.sh || fail "php setup failed on a fresh checkout"
logged "install --no-interaction --no-progress" || fail "php setup did not install on a fresh checkout"
logged development-enable || fail "php setup did not enable development mode"
logged generate-release-metadata || fail "php setup did not generate release metadata"

fresh
php_up_to_date 100 10
setup setup-dev-php.sh || fail "php setup failed on an up-to-date checkout"
! logged install || fail "php setup reinstalled an up-to-date vendor"
! logged development-enable || fail "php setup re-enabled development mode"
logged generate-release-metadata || fail "php setup did not generate release metadata"

fresh
php_up_to_date 10 100
setup setup-dev-php.sh || fail "php setup failed with a newer lock"
logged "install --no-interaction --no-progress" || fail "php setup did not reinstall for a newer lock"

fresh
fake composer 1
! setup setup-dev-php.sh || fail "php setup succeeded although composer failed"

fresh
setup setup-dev-node.sh || fail "node setup failed on a fresh checkout"
logged "ci --no-audit --no-fund" || fail "node setup did not install on a fresh checkout"
logged "run build:ssr" || fail "node setup did not build the SSR bundle"

fresh
aged package-lock.json 100
aged node_modules/.package-lock.json 10
aged data/ssr/ssr.mjs 0
setup setup-dev-node.sh || fail "node setup failed on an up-to-date checkout"
[ ! -s "$tmp/log" ] || fail "node setup ran npm on an up-to-date checkout"

fresh
aged package-lock.json 10
aged node_modules/.package-lock.json 100
aged data/ssr/ssr.mjs 0
setup setup-dev-node.sh || fail "node setup failed with a newer lock"
logged "ci --no-audit --no-fund" || fail "node setup did not reinstall for a newer lock"
! logged build:ssr || fail "node setup rebuilt an existing SSR bundle"

echo "PASS: dev setup scripts"

if ! docker compose version > /dev/null 2>&1; then
    echo "SKIP: docker compose is not available"
    exit 0
fi

# An empty env file and no PACKIO_*/MAIL_* so a developer's .env cannot change the result.
for name in $(env | sed -n 's/^\(\(PACKIO\|MAIL\)_[A-Za-z0-9_]*\)=.*/\1/p'); do
    unset "$name"
done
: > "$tmp/empty.env"
(cd "$root" && docker compose -f compose.yaml --env-file "$tmp/empty.env" config --format json) > "$tmp/config.json"

# check MESSAGE JQ_FILTER: the filter must be true for the resolved services.
check() {
    jq -e '["worker", "worker-cleanup", "email-verification-cleanup", "password-reset-cleanup", "source-oauth-refresh"] as $workers
        | .services as $s | '"$2" "$tmp/config.json" > /dev/null || fail "$1"
}

check "app and worker services send mail to mailpit" \
    'all(("app", $workers[]); $s[.].environment | .MAIL_HOST == "mailpit" and .MAIL_PORT == "1025")'
check "PHP services wait for setup-php" \
    'all(("app", $workers[]); $s[.].depends_on["setup-php"].condition == "service_completed_successfully")'
check "vite and ssr wait for setup-node" \
    'all(("vite", "ssr"); $s[.].depends_on["setup-node"].condition == "service_completed_successfully")'
check "setup services run once as the docker uid" \
    'all(("setup-php", "setup-node"); $s[.] | .user == "1000:1000" and .restart == "no") and $s.vite.user == "1000:1000"'

echo "PASS: dev compose wiring"
