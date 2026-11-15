#!/bin/sh
# Check the user-facing Compose files in deploy/ (no Packio images are pulled).
set -eu

root=$(CDPATH= cd "$(dirname "$0")/../../.." && pwd)

if ! docker compose version > /dev/null 2>&1; then
    echo "SKIP: docker compose is not available"
    exit 0
fi

tmp=$(mktemp -d)
caddy=packio-caddy-test-$$
trap 'docker rm -f "$caddy" > /dev/null 2>&1 || true; rm -rf "$tmp"' EXIT
cp -r "$root/deploy" "$tmp/deploy"
cd "$tmp/deploy"

# The caller's environment must not change the result.
for name in $(env | sed -n 's/^\(\(PACKIO\|MAIL\|COMPOSE\|APP\|DATABASE\|HTTP\)_[A-Za-z0-9_]*\)=.*/\1/p'); do
    unset "$name"
done

local=compose.yaml:compose.local.yaml
local_ssr=$local:compose.ssr.yaml
server=compose.yaml
server_ssr=compose.yaml:compose.ssr.yaml
with_caddy=compose.yaml:compose.caddy.yaml
with_caddy_ssr=$with_caddy:compose.ssr.yaml
server_env='PACKIO_DOMAIN=packio.example.com
DATABASE_PASS=db-pass
APP_SECRET_ENCRYPTION_KEY=base64:dGVzdA==
MAIL_DSN=smtp://mail.example.com:25'

fail() {
    echo "FAIL: $*" >&2
    exit 1
}

# config FILES ENV [ARG...]: `docker compose config` with a fresh .env; ENV "local" copies .env.local.example.
config() {
    files=$1
    values=$2
    shift 2
    if [ "$values" = local ]; then
        cp .env.local.example .env
    else
        printf 'COMPOSE_FILE=%s\n%s\n' "$files" "$values" > .env
    fi
    COMPOSE_FILE=$files docker compose config "$@"
}

# check MESSAGE FILES ENV JQ_FILTER: the filter must be true for the resolved services.
check() {
    config "$2" "$3" --format json > "$tmp/config.json" 2> "$tmp/stderr" || fail "$1: $(cat "$tmp/stderr")"
    jq -e ".services as \$s | $4" "$tmp/config.json" > /dev/null || fail "$1"
}

for files in "$local" "$local_ssr"; do
    config "$files" local --quiet || fail "$files is not valid"
done
for files in "$server" "$server_ssr" "$with_caddy" "$with_caddy_ssr"; do
    config "$files" "$server_env" --quiet || fail "$files is not valid"
done

for name in PACKIO_DOMAIN DATABASE_PASS APP_SECRET_ENCRYPTION_KEY MAIL_DSN; do
    blanked=$(printf '%s\n' "$server_env" | sed "s|^$name=.*|$name=|")
    ! config "$server" "$blanked" --quiet 2> "$tmp/stderr" || fail "an empty $name was accepted"
    grep -qF "$name" "$tmp/stderr" || fail "the error for an empty $name does not name it"
done
blanked=$(printf '%s\nAPP_URL=https://x.example.com\n' "$server_env" | sed 's|^PACKIO_DOMAIN=.*|PACKIO_DOMAIN=|')
! config "$server" "$blanked" --quiet 2> "$tmp/stderr" || fail "APP_URL replaced the required PACKIO_DOMAIN"
grep -qF PACKIO_DOMAIN "$tmp/stderr" || fail "the error for an empty PACKIO_DOMAIN does not name it"

cp .env.local.example .env
services=$(docker compose config --services | sort | tr '\n' ' ')
[ "$services" = "app mailpit postgres workers " ] || fail "COMPOSE_FILE is not read from .env: $services"

check "SSR is off by default" "$server" "$server_env" \
    '$s.app.environment.INERTIA_SSR_ENABLED == "false" and ($s | has("ssr") | not)'
check "the SSR overlay enables SSR" "$server_ssr" "$server_env" \
    '$s.app | .environment.INERTIA_SSR_ENABLED == "true"
        and .environment.INERTIA_SSR_URL == "http://ssr:13714/render"
        and .depends_on.ssr.condition == "service_healthy"'
check "the images share one version tag" "$server_ssr" "$server_env
PACKIO_VERSION=9.9.9" \
    '$s.app.image == "ghcr.io/packio-php/packio:9.9.9"
        and $s.workers.image == "ghcr.io/packio-php/packio-worker:9.9.9"
        and $s.ssr.image == "ghcr.io/packio-php/packio-ssr:9.9.9"'
check "the version defaults to latest" "$server" "$server_env" \
    '$s.app.image == "ghcr.io/packio-php/packio:latest"'
check "the server domain drives the app settings" "$server" "$server_env" \
    '$s.app.environment | .APP_URL == "https://packio.example.com" and .BRAND_DOMAIN == "packio.example.com"
        and .MAIL_FROM_ADDRESS == "noreply@packio.example.com" and .APP_PRODUCTION == "true"'
check "the local variant uses HTTP and Mailpit" "$local" local \
    '($s | has("mailpit")) and all(("app", "workers"); $s[.].environment | .APP_PRODUCTION == "false"
        and .APP_URL == "http://packio.localhost:8080" and .MAIL_DSN == "smtp://mailpit:1025"
        and .BRAND_DOMAIN == "packio.localhost")'

for files in "$server" "$server_ssr" "$local" "$local_ssr"; do
    values=$server_env
    case "$files" in *compose.local.yaml*) values=local ;; esac
    check "$files builds nothing and keeps HTTP on loopback" "$files" "$values" \
        'all($s[]; (has("build") | not) and all(.ports[]?; .host_ip == "127.0.0.1"))
            and $s.app.ports[0].published == "8080"'
done

for files in "$with_caddy" "$with_caddy_ssr"; do
    check "$files trusts only the proxy" "$files" "$server_env" \
        '$s.app.environment.TRUSTED_PROXIES == "172.30.255.2" and $s.app.ports[0].host_ip == "127.0.0.1"
            and ($s.caddy.networks | keys) == ["edge"] and $s.caddy.networks.edge.ipv4_address == "172.30.255.2"
            and $s.caddy.environment.PACKIO_REGISTRY_DOMAIN == "packio.example.com"'
done
check "the Caddy registry domain can be overridden" "$with_caddy" "$server_env
BRAND_REGISTRY_BASE_DOMAIN=reg.example.com" \
    '$s.caddy.environment.PACKIO_REGISTRY_DOMAIN == "reg.example.com"'
check "the ACME email is optional" "$with_caddy" "$server_env" \
    '$s.caddy.environment.ACME_EMAIL_OPTION == ""'
check "the ACME email reaches Caddy" "$with_caddy" "$server_env
ACME_EMAIL=ops@example.com" \
    '$s.caddy.environment.ACME_EMAIL_OPTION == "email ops@example.com"'

echo "PASS: deploy compose files"

caddy_image=caddy:2.11.2-alpine
# caddy_run DOCKER_OPTION... -- [COMMAND...]: run the Caddy image with the deploy Caddyfile.
caddy_run() {
    options=
    while [ "$1" != -- ]; do
        options="$options $1"
        shift
    done
    shift
    # shellcheck disable=SC2086
    docker run $options -e PACKIO_DOMAIN=example.test -e PACKIO_REGISTRY_DOMAIN=example.test \
        -v "$root/deploy/Caddyfile:/etc/caddy/Caddyfile:ro" "$caddy_image" "$@"
}
caddy_validate() {
    caddy_run --rm "$@" -- caddy validate --config /etc/caddy/Caddyfile > "$tmp/caddy.log" 2>&1 \
        || fail "the Caddyfile is not valid ($*): $(cat "$tmp/caddy.log")"
}
caddy_validate
# Passed through the environment: caddy_run splits its options on spaces.
export ACME_EMAIL_OPTION='email ops@example.test'
caddy_validate -e ACME_EMAIL_OPTION
unset ACME_EMAIL_OPTION

# ask_status DOMAIN: HTTP status of the on-demand TLS permission endpoint, or "none".
ask_status() {
    docker exec "$caddy" wget -S -O /dev/null "http://127.0.0.1:9123/?domain=$1" 2>&1 \
        | awk '$1 ~ /^HTTP\// { print $2; found = 1; exit } END { if (!found) print "none" }'
}
caddy_run -d --name "$caddy" --network none -- > /dev/null
attempt=0
while [ "$(ask_status acme.example.test)" = none ] && [ "$attempt" -lt 30 ]; do
    attempt=$((attempt + 1))
    sleep 1
done
[ "$(ask_status acme.example.test)" = 200 ] || fail "a registry subdomain is not allowed a certificate"
for domain in example.test a.b.example.test evil.example acme.example.test.evil.example acmeXexample.test \
    -a.example.test ''; do
    [ "$(ask_status "$domain")" != 200 ] || fail "'$domain' is allowed a certificate"
done

echo "PASS: Caddyfile"
