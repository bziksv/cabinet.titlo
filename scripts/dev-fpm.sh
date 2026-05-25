#!/usr/bin/env bash
# Кабинет :3002 — nginx + php-fpm (параллельные запросы, много вкладок). Канон для Mac.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

export PATH="/opt/homebrew/opt/php@7.4/bin:/opt/homebrew/opt/php@7.4/sbin:${PATH:-}"

LOG="/tmp/cabinet-dev.log"
MODE_FILE="/tmp/cabinet-dev-mode"
PORT=3002

log() {
  echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*" | tee -a "$LOG"
}

find_nginx() {
  if command -v nginx >/dev/null 2>&1; then
    command -v nginx
    return 0
  fi
  [[ -x /opt/homebrew/bin/nginx ]] && echo /opt/homebrew/bin/nginx && return 0
  [[ -x "$(brew --prefix nginx 2>/dev/null)/bin/nginx" ]] && echo "$(brew --prefix nginx)/bin/nginx" && return 0
  return 1
}

find_php_fpm() {
  if command -v php-fpm >/dev/null 2>&1; then
    command -v php-fpm
    return 0
  fi
  [[ -x /opt/homebrew/opt/php@7.4/sbin/php-fpm ]] && echo /opt/homebrew/opt/php@7.4/sbin/php-fpm && return 0
  return 1
}

stop_all() {
  bash "$ROOT/scripts/dev-parallel.sh" stop 2>/dev/null || true
  pkill -f "cabinet-dev-watchdog.sh" 2>/dev/null || true
  pkill -f "artisan serve.*--port=${PORT}" 2>/dev/null || true

  if [[ -f /tmp/cabinet-nginx.pid ]]; then
    kill "$(cat /tmp/cabinet-nginx.pid)" 2>/dev/null || true
    rm -f /tmp/cabinet-nginx.pid
  fi
  pkill -f "nginx.*cabinet-nginx.conf" 2>/dev/null || true

  if [[ -f /tmp/cabinet-php-fpm.pid ]]; then
    kill "$(cat /tmp/cabinet-php-fpm.pid)" 2>/dev/null || true
    rm -f /tmp/cabinet-php-fpm.pid
  fi
  pkill -f "php-fpm.*cabinet-php-fpm" 2>/dev/null || true

  bash "$ROOT/scripts/dev-cluster-queue.sh" stop 2>/dev/null || true

  lsof -ti :"$PORT" -sTCP:LISTEN 2>/dev/null | xargs kill -9 2>/dev/null || true
  lsof -ti :9074 -sTCP:LISTEN 2>/dev/null | xargs kill -9 2>/dev/null || true
  rm -f "$MODE_FILE" /tmp/cabinet-dev.pid 2>/dev/null || true
}

start_fpm() {
  local NGINX_BIN PHP_FPM NGINX_PREFIX
  NGINX_BIN="$(find_nginx)" || return 1
  PHP_FPM="$(find_php_fpm)" || return 1

  NGINX_PREFIX="$(dirname "$(dirname "$NGINX_BIN")")/etc/nginx"
  if [[ ! -f "$NGINX_PREFIX/fastcgi_params" ]]; then
    NGINX_PREFIX="/opt/homebrew/etc/nginx"
  fi

  if [[ ! -f "$ROOT/.env" ]]; then
    log "ERROR: нет .env"
    return 1
  fi

  stop_all
  sleep 0.5

  for pid in $(lsof -ti :"$PORT" -sTCP:LISTEN 2>/dev/null); do
    local cmd
    cmd=$(ps -p "$pid" -o command= 2>/dev/null || true)
    if [[ "$cmd" == *"next dev"* ]] || [[ "$cmd" == *"next-server"* ]]; then
      log "ERROR: на :${PORT} висит Next"
      return 1
    fi
  done

  php artisan config:clear >/dev/null 2>&1 || true

  sed -e "s|__CABINET_ROOT__|$ROOT|g" \
    -e "s|__NGINX_PREFIX__|$NGINX_PREFIX|g" \
    -e "s|__USER__|$(whoami)|g" \
    "$ROOT/scripts/php-fpm-cabinet.conf" >/tmp/cabinet-php-fpm-pool.conf

  cat >/tmp/cabinet-php-fpm.conf <<'EOF'
[global]
pid = /tmp/cabinet-php-fpm.pid
error_log = /tmp/cabinet-php-fpm.log
daemonize = yes
include=/tmp/cabinet-php-fpm-pool.conf
EOF

  "$PHP_FPM" -y /tmp/cabinet-php-fpm.conf

  sed -e "s|__CABINET_ROOT__|$ROOT|g" \
    -e "s|__NGINX_PREFIX__|$NGINX_PREFIX|g" \
    "$ROOT/scripts/nginx-cabinet.local.conf" >/tmp/cabinet-nginx.conf

  "$NGINX_BIN" -c /tmp/cabinet-nginx.conf

  echo fpm >"$MODE_FILE"
  log "режим nginx+php-fpm (до 16 воркеров), DB=$(grep '^DB_HOST=' .env 2>/dev/null | cut -d= -f2- || echo '?')"
}

health_check() {
  local code
  code=$(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "http://127.0.0.1:${PORT}/login" 2>/dev/null || echo "000")
  [[ "$code" == "200" || "$code" == "302" ]]
}

parallel_smoke() {
  local ok=0 fail=0 i
  for i in $(seq 1 8); do
    if curl -sS -o /dev/null --max-time 20 "http://127.0.0.1:${PORT}/login" 2>/dev/null; then
      ok=$((ok + 1))
    else
      fail=$((fail + 1))
    fi
  done
  log "параллельная проверка: ${ok}/8 OK, ${fail} fail"
  [[ "$fail" -le 2 ]]
}

if [[ "${1:-}" == "stop" ]]; then
  stop_all
  log "остановлен (nginx+fpm)"
  echo "Кабинет остановлен"
  exit 0
fi

if [[ "${1:-}" == "status" ]]; then
  echo "Режим: $(cat "$MODE_FILE" 2>/dev/null || echo 'не запущен')"
  echo "Лог: $LOG"
  lsof -i :"$PORT" -sTCP:LISTEN 2>/dev/null | head -3 || echo "порт $PORT свободен"
  health_check && echo "GET /login → OK" || echo "GET /login → нет ответа"
  exit 0
fi

# --- main / detach ---
if ! start_fpm; then
  echo "nginx/php-fpm не поднялись. Установите: brew install nginx" >&2
  echo "Временный fallback: CABINET_DEV_SERVE=1 bash scripts/dev-local.sh detach" >&2
  exit 1
fi

sleep 1
for _ in 1 2 3 4 5 6 7 8 9 10; do
  if health_check; then
    break
  fi
  sleep 1
done

if ! health_check; then
  log "ERROR: /login не отвечает"
  tail -5 /tmp/cabinet-php-fpm.log 2>/dev/null | tee -a "$LOG"
  exit 1
fi

parallel_smoke || true

bash "$ROOT/scripts/dev-cluster-queue.sh" start >/dev/null 2>&1 || true

echo "Кабинет: http://localhost:${PORT}/login (nginx + php-fpm, параллельные запросы)"
echo "Лог: tail -f $LOG"
echo "Очереди кластера: tail -f /tmp/cabinet-cluster-queue.log"
echo "Остановка: bash scripts/dev-fpm.sh stop"

if [[ "${1:-}" == "detach" ]] || [[ -n "${CABINET_DEV_DETACH:-}" ]]; then
  exit 0
fi

# foreground: ждём Ctrl+C
trap 'stop_all; exit 0' INT TERM
while true; do sleep 3600; done
