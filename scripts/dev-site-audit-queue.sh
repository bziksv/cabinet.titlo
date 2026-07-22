#!/usr/bin/env bash
# Локальные воркеры очереди site_audit (волна 2).
# Usage:
#   ./scripts/dev-site-audit-queue.sh [numprocs=2]
#   ./scripts/dev-site-audit-queue.sh stop
#
# Демонизируем через perl setsid — иначе Cursor/IDE shell
# убивает nohup-детей вместе с process group.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

PHP="${PHP_BIN:-/opt/homebrew/opt/php@7.4/bin/php}"
QUEUES="site_audit"
PIDDIR="storage/logs"

stop_workers() {
  echo "Stopping site_audit workers…"
  if compgen -G "${PIDDIR}/dev-site-audit-*.pid" > /dev/null; then
    for f in "${PIDDIR}"/dev-site-audit-*.pid; do
      pid="$(cat "$f" 2>/dev/null || true)"
      if [[ -n "${pid}" ]] && kill -0 "$pid" 2>/dev/null; then
        kill "$pid" 2>/dev/null || true
      fi
      rm -f "$f"
    done
  fi
  pkill -f "artisan queue:work.*--queue=${QUEUES}" 2>/dev/null || true
  sleep 1
}

if [[ "${1:-}" == "stop" ]]; then
  stop_workers
  echo "Stopped."
  exit 0
fi

NUM="${1:-2}"
stop_workers

mkdir -p "$PIDDIR"
for i in $(seq 1 "$NUM"); do
  LOG="${PIDDIR}/dev-site-audit-${i}.log"
  PIDFILE="${PIDDIR}/dev-site-audit-${i}.pid"
  echo "Start worker ${i}/${NUM} → ${LOG}"
  : >"$LOG"
  # новый session leader → не умирает с родительским shell
  perl -MPOSIX -e 'POSIX::setsid(); exec { $ARGV[0] } @ARGV' -- \
    "$PHP" artisan queue:work database \
      --queue="${QUEUES}" \
      --sleep=1 \
      --tries=2 \
      --timeout=3600 \
      >>"$LOG" 2>&1 &
  echo $! >"$PIDFILE"
done

sleep 1
alive=0
for i in $(seq 1 "$NUM"); do
  pid="$(cat "${PIDDIR}/dev-site-audit-${i}.pid" 2>/dev/null || true)"
  if [[ -n "${pid}" ]] && kill -0 "$pid" 2>/dev/null; then
    alive=$((alive + 1))
  fi
done

echo "OK: ${alive}/${NUM} worker(s) on queue=${QUEUES}"
echo "Stop: ./scripts/dev-site-audit-queue.sh stop"
