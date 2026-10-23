#!/usr/bin/env bash
# Перезапускает зависший artisan serve (однопоточный + удалённая БД).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
export PATH="/opt/homebrew/opt/php@8.5/bin:${PATH:-}"

WORKERS=(13002 13003 13004)
PROBE_PATH="${CABINET_HEALTH_PATH:-/login}"
MAX_SEC="${CABINET_HEALTH_MAX_SEC:-8}"

restart_worker() {
  local port=$1
  lsof -ti :"$port" -sTCP:LISTEN 2>/dev/null | xargs kill -9 2>/dev/null || true
  sleep 0.3
  nohup php artisan serve --host=127.0.0.1 --port="$port" >>/tmp/cabinet-worker-"$port".log 2>&1 &
  echo "$(date '+%H:%M:%S') watchdog: перезапущен воркер :$port" >>/tmp/cabinet-watchdog.log
}

check_worker() {
  local port=$1
  local code
  code=$(curl -sS -o /dev/null -w '%{http_code}' --max-time "$MAX_SEC" "http://127.0.0.1:${port}${PROBE_PATH}" 2>/dev/null || echo "000")
  if [[ "$code" == "200" || "$code" == "302" || "$code" == "301" ]]; then
    return 0
  fi
  restart_worker "$port"
}

while true; do
  for p in "${WORKERS[@]}"; do
    check_worker "$p" &
  done
  wait
  sleep 12
done
