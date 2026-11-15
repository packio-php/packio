#!/bin/sh
# Exercise RoadRunner service supervision against a disposable PostgreSQL instance.
# Build the prod-worker target first. No application ports or persistent database volumes are created.
set -eu

[ "$#" -eq 1 ] || { echo "Usage: $0 WORKER_IMAGE" >&2; exit 64; }
image=$(docker image inspect --format '{{.Id}}' "$1")
echo "Resolved worker image: $image"

network=packio-service-check-$$-$(date +%s)
db=$network-db
worker=$network-worker
env="-e APP_PRODUCTION=true -e APP_DEBUG=false -e DEFAULT_DB_CONNECTION=postgres -e DATABASE_HOST=$db
    -e DATABASE_PORT=5432 -e DATABASE_NAME=packio -e DATABASE_USER=packio -e DATABASE_PASS=smoke-only
    -e APP_SECRET_ENCRYPTION_KEY=smoke-test-only-key -e LOG_CHANNEL=stderr -e WORKER_TIMEOUT=1"

cleanup() {
    docker rm -f "$worker" "$db" > /dev/null 2>&1 || true
    docker network rm "$network" > /dev/null 2>&1 || true
}
trap cleanup EXIT

fail() {
    echo "FAIL: $*" >&2
    docker logs --tail 50 "$worker" >&2 2>&1 || true
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

# rpc METHOD SERVICE: call the RoadRunner service plugin, print the sorted process IDs it reports.
rpc() {
    docker exec "$worker" php -r 'require "/app/vendor/autoload.php";
        $result = Spiral\Goridge\RPC\RPC::create("tcp://127.0.0.1:6001")->call($argv[1], ["name" => $argv[2]]);
        $pids = array_column(is_array($result) ? $result["status"] ?? [] : [], "pid");
        sort($pids);
        echo implode(" ", $pids);' "$1" "$2"
}

pids() {
    rpc service.Statuses worker
}

postgres_ready() {
    docker exec "$db" pg_isready -U packio > /dev/null 2>&1
}

healthy() {
    docker exec "$worker" php bin/healthcheck-roadrunner-services.php > /dev/null 2>&1
}

# start_worker [DOCKER_OPTION...]
start_worker() {
    # shellcheck disable=SC2086
    docker run -d --pull=never --name "$worker" --network "$network" $env "$@" "$image" > /dev/null
}

stop_gracefully() {
    docker stop -t 15 "$worker" > /dev/null
    [ "$(docker inspect --format '{{.State.ExitCode}}' "$worker")" = 0 ] || fail "$1"
}

replaced() {
    after=$(pids)
    # shellcheck disable=SC2086
    [ "$(set -- $after; echo $#)" -eq 7 ] && [ "$after" != "$before" ] && healthy
}

has_three_workers() {
    # shellcheck disable=SC2046
    healthy && [ "$(set -- $(pids); echo $#)" -eq 3 ]
}

docker network create --internal "$network" > /dev/null
docker run -d --name "$db" --network "$network" --tmpfs /var/lib/postgresql/data \
    -e POSTGRES_DB=packio -e POSTGRES_USER=packio -e POSTGRES_PASSWORD=smoke-only postgres:17-alpine > /dev/null
wait_until 30 PostgreSQL postgres_ready
# shellcheck disable=SC2086
docker run --rm --pull=never --network "$network" $env --entrypoint php "$image" \
    vendor/bin/laminas cycle:migration:run -n > /dev/null

start_worker -e PACKIO_WORKER_NUM=7
wait_until 60 "worker health" healthy
echo "PASS: seven queue processes and one of each maintenance service are healthy."

for signal in TERM KILL; do
    before=$(pids)
    docker exec "$worker" sh -c 'kill "$1" "$2"' sh "-$signal" "${before%% *}"
    wait_until 60 "a replacement worker after $signal" replaced
    echo "PASS: worker replaced after $signal"
done

rpc service.Terminate worker-cleanup > /dev/null
! healthy || fail "the healthcheck accepted a missing maintenance service"
echo "PASS: healthcheck detects missing maintenance service."

stop_gracefully "shutdown required a forced kill"
docker rm "$worker" > /dev/null
start_worker -e PACKIO_WORKER_NUM=3
wait_until 60 "three queue consumers" has_three_workers
echo "PASS: changing PACKIO_WORKER_NUM starts three queue consumers."
stop_gracefully "the full service set did not stop gracefully"

if output=$(docker run --rm --pull=never --network none -e PACKIO_WORKER_NUM=0 "$image" 2>&1); then
    fail "an invalid worker count was accepted"
fi
case "$output" in
    *"positive integer"*) ;;
    *) fail "an invalid worker count was rejected without an explanation: $output" ;;
esac
echo "PASS: invalid worker count is rejected."
