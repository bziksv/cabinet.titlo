#!/usr/bin/env bash
# Локальные воркеры очереди site_audit (fetch) + site_audit_aggregate (отдельно).
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
# По умолчанию локальная очередь: общая site_audit на remote DB хватает prod.
if [[ -f "${ROOT}/.env" ]]; then
  set -a
  # shellcheck disable=SC1091
  source <(grep -E '^(SITE_AUDIT_QUEUE|SITE_AUDIT_AGGREGATE_QUEUE|APP_ENV)=' "${ROOT}/.env" | sed 's/\r$//' ) || true
  set +a
fi
QUEUES="${SITE_AUDIT_QUEUE:-site_audit_local}"
AGG_QUEUES="${SITE_AUDIT_AGGREGATE_QUEUE:-site_audit_aggregate_local}"
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
  pkill -f "artisan queue:work.*--queue=${AGG_QUEUES}" 2>/dev/null || true
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

# Fail-fast: зомби-воркер без MySQL (sandbox) иначе вечно «Запуск».
if ! "$PHP" -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
DB::connection()->getPdo();
file_put_contents("storage/logs/dev-site-audit.heartbeat", (string) time());
echo "db-ok\n";
' >/dev/null 2>>"${PIDDIR}/dev-site-audit-guard.log"; then
  echo "ERROR: MySQL недоступен — воркеры не стартую (проверь сеть/sandbox)."
  echo "См. ${PIDDIR}/dev-site-audit-guard.log"
  exit 1
fi

for i in $(seq 1 "$NUM"); do
  LOG="${PIDDIR}/dev-site-audit-${i}.log"
  PIDFILE="${PIDDIR}/dev-site-audit-${i}.pid"
  echo "Start fetch worker ${i}/${NUM} → ${LOG}"
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

# Отдельный процесс на агрегацию — иначе Aggregate* снова голодает fetch.
AGG_LOG="${PIDDIR}/dev-site-audit-agg.log"
AGG_PIDFILE="${PIDDIR}/dev-site-audit-agg.pid"
echo "Start aggregate worker → ${AGG_LOG}"
: >"$AGG_LOG"
perl -MPOSIX -e 'POSIX::setsid(); exec { $ARGV[0] } @ARGV' -- \
  "$PHP" artisan queue:work database \
    --queue="${AGG_QUEUES}" \
    --sleep=1 \
    --tries=2 \
    --timeout=3600 \
    >>"$AGG_LOG" 2>&1 &
echo $! >"$AGG_PIDFILE"

sleep 1
alive=0
for i in $(seq 1 "$NUM"); do
  pid="$(cat "${PIDDIR}/dev-site-audit-${i}.pid" 2>/dev/null || true)"
  if [[ -n "${pid}" ]] && kill -0 "$pid" 2>/dev/null; then
    alive=$((alive + 1))
  fi
done
agg_alive=0
agg_pid="$(cat "${AGG_PIDFILE}" 2>/dev/null || true)"
if [[ -n "${agg_pid}" ]] && kill -0 "$agg_pid" 2>/dev/null; then
  agg_alive=1
fi

echo "OK: ${alive}/${NUM} fetch worker(s) on queue=${QUEUES}; aggregate=${agg_alive}/1 on ${AGG_QUEUES}"
echo "Stop: ./scripts/dev-site-audit-queue.sh stop"
