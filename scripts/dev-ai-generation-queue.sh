#!/usr/bin/env bash
# Локальный воркер очереди ai_generation (Bitrix Shop API / AI тексты).
# Usage:
#   ./scripts/dev-ai-generation-queue.sh
#   ./scripts/dev-ai-generation-queue.sh stop
#
# Демонизируем через perl setsid — иначе Cursor/IDE shell
# убивает nohup-детей вместе с process group.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

PHP="${PHP_BIN:-/opt/homebrew/opt/php@8.5/bin/php}"
QUEUES="ai_generation"
PIDDIR="storage/logs"
PIDFILE="${PIDDIR}/dev-ai-generation.pid"
LOG="${PIDDIR}/dev-ai-generation.log"
HB="${PIDDIR}/dev-ai-generation.heartbeat"

stop_workers() {
  echo "Stopping ai_generation worker…"
  if [[ -f "$PIDFILE" ]]; then
    pid="$(cat "$PIDFILE" 2>/dev/null || true)"
    if [[ -n "${pid}" ]] && kill -0 "$pid" 2>/dev/null; then
      kill "$pid" 2>/dev/null || true
    fi
    rm -f "$PIDFILE"
  fi
  pkill -f "artisan queue:work.*--queue=${QUEUES}" 2>/dev/null || true
  sleep 1
}

if [[ "${1:-}" == "stop" ]]; then
  stop_workers
  echo "Stopped."
  exit 0
fi

stop_workers

mkdir -p "$PIDDIR"

if ! "$PHP" -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
DB::connection()->getPdo();
file_put_contents("storage/logs/dev-ai-generation.heartbeat", (string) time());
echo "db-ok\n";
' >/dev/null 2>>"${PIDDIR}/dev-ai-generation-guard.log"; then
  echo "ERROR: MySQL недоступен — воркер не стартую."
  echo "См. ${PIDDIR}/dev-ai-generation-guard.log"
  exit 1
fi

echo "Start ai_generation worker → ${LOG}"
: >"$LOG"
perl -MPOSIX -e 'POSIX::setsid(); exec { $ARGV[0] } @ARGV' -- \
  "$PHP" artisan queue:work database \
    --queue="${QUEUES}" \
    --sleep=1 \
    --tries=3 \
    --timeout=300 \
    >>"$LOG" 2>&1 &
echo $! >"$PIDFILE"

sleep 1
pid="$(cat "$PIDFILE" 2>/dev/null || true)"
if [[ -n "${pid}" ]] && kill -0 "$pid" 2>/dev/null; then
  date +%s >"$HB"
  echo "OK: worker pid=${pid} on queue=${QUEUES}"
  echo "Stop: ./scripts/dev-ai-generation-queue.sh stop"
else
  echo "ERROR: worker не поднялся — см. ${LOG}"
  exit 1
fi
