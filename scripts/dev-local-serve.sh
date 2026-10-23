#!/usr/bin/env bash
# Fallback: один artisan serve (только если fpm недоступен)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
export PATH="/opt/homebrew/opt/php@8.5/bin:${PATH:-}"

LOG_TMP="/tmp/cabinet-dev.log"
PID_FILE="/tmp/cabinet-dev.pid"
PORT=3002

log() {
  echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*" | tee -a "$LOG_TMP"
}

bash "$ROOT/scripts/dev-fpm.sh" stop 2>/dev/null || true

if [[ ! -f .env ]]; then
  echo "Нет .env" >&2
  exit 1
fi

php artisan config:clear >/dev/null 2>&1 || true
log "fallback: artisan serve (однопоточный — не для многих вкладок)"

nohup php artisan serve --host=127.0.0.1 --port="$PORT" >>"$LOG_TMP" 2>&1 &
echo "$!" >"$PID_FILE"
echo "serve" >/tmp/cabinet-dev-mode

if [[ "${CABINET_DEV_DETACH:-}" == "1" ]] || [[ "${1:-}" == "detach" ]]; then
  sleep 2
  curl -sS -o /dev/null -w "HTTP %{http_code}\n" --max-time 15 "http://127.0.0.1:${PORT}/login" || true
  echo "http://localhost:${PORT}/login (serve fallback)"
  exit 0
fi

wait "$(cat "$PID_FILE")"
