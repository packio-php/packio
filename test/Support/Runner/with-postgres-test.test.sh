#!/bin/sh

set -eu

project_root=$(CDPATH= cd "$(dirname "$0")/../../.." && pwd)
temporary_directory=$(mktemp -d)
fake_bin="$temporary_directory/bin"
docker_log="$temporary_directory/docker.log"
docker_marker="$temporary_directory/postgres-test.started"
pg_isready_log="$temporary_directory/pg_isready.log"

cleanup() {
    rm -rf "$temporary_directory"
}

trap cleanup EXIT

mkdir -p "$fake_bin"

cat > "$fake_bin/docker" <<'FAKE_DOCKER'
#!/bin/sh

set -eu

printf '%s\n' "$*" >> "$FAKE_DOCKER_LOG"

if [ "${1:-}" != 'compose' ]; then
    exit 1
fi

shift
case "$*" in
    version)
        exit 0
        ;;
    *' up -d postgres-test')
        touch "$FAKE_DOCKER_MARKER"
        exit 0
        ;;
    *' exec -T postgres-test pg_isready'*)
        exit 0
        ;;
    *' stop postgres-test')
        exit 0
        ;;
    *)
        exit 1
        ;;
esac
FAKE_DOCKER
chmod +x "$fake_bin/docker"

cat > "$fake_bin/pg_isready" <<'FAKE_PG_ISREADY'
#!/bin/sh

set -eu

printf '%s\n' "$*" >> "$FAKE_PG_ISREADY_LOG"
FAKE_PG_ISREADY
chmod +x "$fake_bin/pg_isready"

export PATH="$fake_bin:$PATH"
export FAKE_DOCKER_LOG="$docker_log"
export FAKE_DOCKER_MARKER="$docker_marker"
export FAKE_PG_ISREADY_LOG="$pg_isready_log"
export PACKIO_TEST_RUNNER_MODE=local
export PACKIO_TEST_DATABASE_PORT=15433
export PACKIO_TEST_DATABASE_NAME=packio_test
export PACKIO_TEST_DATABASE_USER=packio_test
export PACKIO_TEST_DATABASE_PASS=packio_test

"$project_root/bin/with-postgres-test" sh -c 'test "$DEFAULT_DB_CONNECTION" = postgres && test "$PACKIO_TEST_DB" = postgres && test "$DATABASE_PORT" = 15433'
test -f "$docker_marker"
grep -q 'up -d postgres-test' "$docker_log"
grep -q 'exec -T postgres-test pg_isready' "$docker_log"

"$project_root/bin/with-postgres-test" true

set +e
"$project_root/bin/with-postgres-test" sh -c 'exit 37'
status=$?
set -e

test "$status" -eq 37
test "$(grep -c 'stop postgres-test' "$docker_log")" -eq 3

unset PACKIO_TEST_DATABASE_HOST PACKIO_TEST_DATABASE_PORT PACKIO_TEST_DATABASE_NAME PACKIO_TEST_DATABASE_USER PACKIO_TEST_DATABASE_PASS
set +e
PACKIO_TEST_RUNNER_MODE=ci "$project_root/bin/with-postgres-test" true 2> "$temporary_directory/ci-error"
status=$?
set -e

test "$status" -eq 1
grep -q 'requires PACKIO_TEST_DATABASE_HOST' "$temporary_directory/ci-error"

export PACKIO_TEST_DATABASE_HOST=postgres-test
export PACKIO_TEST_DATABASE_PORT=5432
export PACKIO_TEST_DATABASE_NAME=packio_test
export PACKIO_TEST_DATABASE_USER=packio_test
export PACKIO_TEST_DATABASE_PASS=packio_test
PACKIO_TEST_RUNNER_MODE=ci "$project_root/bin/with-postgres-test" sh -c 'test "$DATABASE_HOST" = postgres-test && test "$DATABASE_PORT" = 5432'
grep -q -- '-h postgres-test -p 5432 -U packio_test -d packio_test' "$pg_isready_log"
