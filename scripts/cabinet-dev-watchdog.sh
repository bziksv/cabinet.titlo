#!/usr/bin/env bash
# Если :3002 не отвечает 8 с — перезапуск serve (однопоточный PHP зависает на MySQL).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
export PATH="/opt/homebrew/opt/php@8.5/bin:${PATH:-}"
PORT=3002
PID_FILE="/tmp/cabinet-dev.pid"
LOG="/tmp/cabinet-dev.log"
INTERVAL="${CABINET_WATCHDOG_INTERVAL:-10}"
MAX_TIME=5

wlog() {
  echo "[$(date '+%Y-%m-%d %H:%M:%S')] [watchdog] $*" >>"$LOG"
}

restart_serve() {
  wlog "перезапуск (зависший запрос / очередь от браузера)"
  lsof -ti :"$PORT" -sTCP:LISTEN 2>/dev/null | xargs kill -9 2>/dev/null || true
  pkill -f "artisan serve.*--port=${PORT}" 2>/dev/null || true
  sleep 0.5
  cd "$ROOT"
  nohup php artisan serve --host=127.0.0.1 --port="$PORT" >>"$LOG" 2>&1 &
  echo "$!" >"$PID_FILE"
  wlog "новый PID $(cat "$PID_FILE")"
}

wlog "старт (проверка каждые ${INTERVAL}s, таймаут curl ${MAX_TIME}s)"

while true; do
  code=$(curl -sS -o /dev/null -w '%{http_code}' --max-time "$MAX_TIME" "http://127.0.0.1:${PORT}/login" 2>/dev/null || echo "000")
  if [[ "$code" == "200" || "$code" == "302" ]]; then
    sleep "$INTERVAL"
    continue
  fi
  wlog "/login → HTTP ${code} (ожидали 200)"
  restart_serve
  sleep 3
done
