#!/usr/bin/env bash
# Локальные воркеры очереди анализа релевантности (relevance_*).
# Usage:
#   ./scripts/dev-relevance-queue.sh
#   ./scripts/dev-relevance-queue.sh stop
#   ./scripts/dev-relevance-queue.sh status
#
# setsid — чтобы IDE/shell не убивал nohup-детей.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

PHP="${PHP_BIN:-/opt/homebrew/opt/php@8.5/bin/php}"
# HTML парсер на больших карточках жрёт >128M — иначе worker падает и очередь встаёт.
PHP_ARGS=("-d" "memory_limit=${RELEVANCE_PHP_MEMORY:-512M}")
QUEUES="relevance_high_priority,relevance_medium_priority,relevance_normal_priority"
WORKERS="${RELEVANCE_QUEUE_WORKERS:-2}"
PIDDIR="storage/logs"
PIDFILE="${PIDDIR}/dev-relevance.pid"
LOG="${PIDDIR}/dev-relevance.log"
HB="${PIDDIR}/dev-relevance.heartbeat"

stop_workers() {
  echo "Stopping relevance workers…"
  if [[ -f "$PIDFILE" ]]; then
    while read -r pid; do
      [[ -n "${pid}" ]] || continue
      if kill -0 "$pid" 2>/dev/null; then
        kill "$pid" 2>/dev/null || true
      fi
    done <"$PIDFILE"
    rm -f "$PIDFILE"
  fi
  # старый вариант с /tmp
  if [[ -d /tmp/cabinet-relevance-queue-pids ]]; then
    for f in /tmp/cabinet-relevance-queue-pids/*.pid; do
      [[ -f "$f" ]] || continue
      pid="$(cat "$f" 2>/dev/null || true)"
      if [[ -n "${pid}" ]] && kill -0 "$pid" 2>/dev/null; then
        kill "$pid" 2>/dev/null || true
      fi
    done
    rm -rf /tmp/cabinet-relevance-queue-pids
  fi
  pkill -f "artisan queue:work.*relevance_high_priority" 2>/dev/null || true
  sleep 1
}

running_count() {
  local n=0
  if [[ -f "$PIDFILE" ]]; then
    while read -r pid; do
      [[ -n "${pid}" ]] || continue
      if kill -0 "$pid" 2>/dev/null; then
        n=$((n + 1))
      fi
    done <"$PIDFILE"
  fi
  echo "$n"
}

case "${1:-restart}" in
  stop)
    stop_workers
    echo "Stopped."
    exit 0
    ;;
  status)
    n="$(running_count)"
    if [[ "$n" -gt 0 ]]; then
      echo "running $n worker(s), queues: $QUEUES"
      tail -5 "$LOG" 2>/dev/null || true
    else
      echo "not running"
    fi
    exit 0
    ;;
esac

stop_workers
mkdir -p "$PIDDIR"

if ! "$PHP" -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
DB::connection()->getPdo();
file_put_contents("storage/logs/dev-relevance.heartbeat", (string) time());
echo "db-ok\n";
' >/dev/null 2>>"${PIDDIR}/dev-relevance-guard.log"; then
  echo "ERROR: MySQL недоступен — воркер не стартую."
  echo "См. ${PIDDIR}/dev-relevance-guard.log"
  exit 1
fi

echo "Start relevance workers → ${LOG}"
: >"$LOG"
: >"$PIDFILE"
for i in $(seq 1 "$WORKERS"); do
  perl -MPOSIX -e 'POSIX::setsid(); exec { $ARGV[0] } @ARGV' -- \
    "$PHP" "${PHP_ARGS[@]}" artisan queue:work database \
      --queue="$QUEUES" \
      --sleep=1 \
      --tries=2 \
      --timeout=0 \
      >>"$LOG" 2>&1 &
  echo $! >>"$PIDFILE"
done

sleep 1
n="$(running_count)"
if [[ "$n" -gt 0 ]]; then
  date +%s >"$HB"
  echo "OK: $n worker(s) on queues=${QUEUES}"
  echo "Stop: ./scripts/dev-relevance-queue.sh stop"
else
  echo "ERROR: worker не поднялся — см. ${LOG}"
  exit 1
fi
