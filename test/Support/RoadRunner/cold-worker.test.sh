#!/bin/sh
# Run real source.create jobs against cold production worker filesystems.
# Build the prod-worker target first. No application ports or persistent database volumes are created.
set -eu

[ "$#" -eq 1 ] || { echo "Usage: $0 WORKER_IMAGE" >&2; exit 64; }
root=$(CDPATH= cd "$(dirname "$0")/../../.." && pwd)
image=$(docker image inspect --format '{{.Id}}' "$1")
echo "Resolved worker image: $image"

network=packio-cold-worker-$$-$(date +%s)
db=$network-db
producer=$network-producer
worker=$network-worker
tmp=$(mktemp -d)
env="-e APP_PRODUCTION=true -e APP_DEBUG=false -e DEFAULT_DB_CONNECTION=postgres -e DATABASE_HOST=$db
    -e DATABASE_PORT=5432 -e DATABASE_NAME=packio -e DATABASE_USER=packio -e DATABASE_PASS=smoke-only
    -e APP_SECRET_ENCRYPTION_KEY=smoke-test-only-key -e LOG_CHANNEL=stderr"

cleanup() {
    docker rm -f "$worker" "$producer" "$db" > /dev/null 2>&1 || true
    docker network rm "$network" > /dev/null 2>&1 || true
    rm -rf "$tmp"
}
trap cleanup EXIT

fail() {
    echo "FAIL: $*" >&2
    docker logs --tail 80 "$worker" >&2 2>&1 || true
    exit 1
}

# wait_until SECONDS DESCRIPTION COMMAND...
wait_until() {
    attempts=$(($1 * 2))
    description=$2
    shift 2
    until "$@"; do
        attempts=$((attempts - 1))
        [ "$attempts" -gt 0 ] || fail "timed out waiting for $description"
        sleep 0.5
    done
}

postgres_ready() {
    docker exec "$db" pg_isready -U packio > /dev/null 2>&1
}

healthy() {
    docker exec "$worker" php bin/healthcheck-roadrunner-services.php > /dev/null 2>&1
}

# fixture COMMAND [ARG...]: run the PHP fixture in the producer container, print its JSON.
fixture() {
    docker exec "$producer" php /tmp/cold-worker-fixture.php "$@"
}

# start_worker COUNT: a new worker container with a cold filesystem.
start_worker() {
    # shellcheck disable=SC2086
    docker run -d --pull=never --name "$worker" --network "$network" $env \
        -e "PACKIO_WORKER_NUM=$1" -e WORKER_TIMEOUT=1 -e WORKER_MAX_JOBS=1 "$image" > /dev/null
    wait_until 90 "worker readiness" healthy
    mode=$(docker exec "$worker" stat -c %a /app/data/cache/object-mapper)
    [ "$mode" = 700 ] || fail "mapper cache permissions are $mode"
    docker exec "$worker" sh -ec 'test -n "$(find /app/data/cache/object-mapper -type f -print -quit)"' \
        || fail "the mapper cache is empty"
    [ "$(docker exec "$worker" id -u)" != 0 ] || fail "the worker runs as root"
}

job_finished() {
    result=$(fixture status "$job" "$resource" "$organization")
    case "$(printf '%s' "$result" | jq -r .status)" in
        completed | failed) ;;
        *) return 1 ;;
    esac
}

# run_job RESOURCE: enqueue source.create and require it to complete on the first attempt; sets $result.
run_job() {
    resource=$1
    job=$(fixture enqueue "$organization" "$owner" "$resource" | jq -r .job)
    wait_until 90 "source.create job $job" job_finished
    printf '%s' "$result" | jq -e '.status == "completed" and (.source_ids | length > 0)
        and (.worker_id | length > 0) and .attempts == 1' > /dev/null || fail "unexpected job result: $result"
}

three_workers_started() {
    [ "$(docker logs "$worker" 2>&1 | grep -oE 'Worker \[[^]]+\] started' | sort -u | wc -l)" -ge 3 ]
}

recycled() {
    healthy && docker logs "$worker" 2>&1 | grep -qF 'reached max jobs (1)'
}

# refuses_to_start DESCRIPTION DOCKER_OPTION...: the worker must fail before any consumer starts.
refuses_to_start() {
    description=$1
    shift
    # shellcheck disable=SC2086
    if output=$(docker run --rm --pull=never --network none $env "$@" "$image" 2>&1); then
        fail "$description: the worker started"
    fi
    case "$output" in
        *"Worker ["*) fail "$description: a consumer started" ;;
        *"worker services were not started"*) ;;
        *) fail "$description: unexpected failure: $output" ;;
    esac
}

docker network create --internal "$network" > /dev/null
docker run -d --name "$db" --network "$network" --tmpfs /var/lib/postgresql/data \
    -e POSTGRES_DB=packio -e POSTGRES_USER=packio -e POSTGRES_PASSWORD=smoke-only postgres:17-alpine > /dev/null
wait_until 90 PostgreSQL postgres_ready
# shellcheck disable=SC2086
docker run -d --pull=never --name "$producer" --network "$network" $env --entrypoint sleep "$image" 600 > /dev/null
docker cp "$root/test/Support/RoadRunner/cold-worker-fixture.php" "$producer:/tmp/cold-worker-fixture.php" > /dev/null
docker exec "$producer" php vendor/bin/laminas cycle:migration:run -n > /dev/null
docker exec "$producer" /usr/local/bin/warmup-object-mapper.sh > /dev/null
setup=$(fixture setup)
organization=$(printf '%s' "$setup" | jq -r .organization)
owner=$(printf '%s' "$setup" | jq -r .owner)

start_worker 3
run_job https://git.example.test/cold/first.git
first=$result
# Workers start one by one; the first job can finish before the last one logs its start.
wait_until 90 "three distinct worker IDs" three_workers_started
wait_until 90 "max-jobs recycling" recycled
docker stop -t 15 "$worker" > /dev/null
docker rm "$worker" > /dev/null
start_worker 2
run_job https://git.example.test/cold/second.git
jq -en --argjson first "$first" --argjson second "$result" '($first.source_ids | length) == 1
    and ($second.source_ids | length) == 2 and ($second.source_ids | index($first.source_ids[0]) != null)' \
    > /dev/null || fail "the jobs did not create distinct Source records: $first $result"
echo "PASS: two real source.create jobs completed with distinct Source records across cold worker recreation."
echo "PASS: mapper cache is owner-only, worker IDs are unique, and max-jobs recycling retained supervision."

refuses_to_start "unwritable mapper cache" -e OBJECT_MAPPER_CACHE_DIR=/proc/packio-unwritable
echo "PASS: unwritable mapper cache fails before consumers start."

cat > "$tmp/invalid.global.php" <<'PHP'
<?php return ['object_mapper' => ['mappings' => ['invalid' => ['source' => 'Missing\\Source', 'target' => 'Missing\\Target']]]];
PHP
chmod 644 "$tmp/invalid.global.php"
refuses_to_start "invalid mapping definition" \
    -v "$tmp/invalid.global.php:/app/config/autoload/invalid.global.php:ro"
echo "PASS: invalid mapping definition fails before consumers start."
