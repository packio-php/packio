#!/bin/sh
# Sourced by maintenance loops. Wait for the current command on shutdown,
# but interrupt the idle sleep immediately. RoadRunner bounds the total wait.
maintenance_child_pid=
maintenance_sleep=false

stop_maintenance() {
  trap '' INT TERM
  if [ -n "$maintenance_child_pid" ]; then
    if [ "$maintenance_sleep" = true ]; then
      kill -TERM "$maintenance_child_pid" 2>/dev/null || true
    fi
    wait "$maintenance_child_pid" || true
  fi
  exit 0
}

trap stop_maintenance INT TERM

run_maintenance() {
  "$@" &
  maintenance_child_pid=$!
  wait "$maintenance_child_pid"
  maintenance_child_pid=
}

sleep_maintenance() {
  maintenance_sleep=true
  run_maintenance sleep "$1"
  maintenance_sleep=false
}
