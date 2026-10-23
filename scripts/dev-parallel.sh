#!/usr/bin/env bash
# Локальный кабинет :3002 — несколько php artisan serve + Node-прокси (параллельные запросы)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

export PATH="/opt/homebrew/opt/php@8.5/bin:${PATH:-}"

if [[ ! -f .env ]]; then
  echo "Нет .env" >&2
  exit 1
fi

WORKERS=(13002 13003 13004)
PROXY_PORT=3002
PID_DIR="/tmp/cabinet-dev-pids"
mkdir -p "$PID_DIR"

stop_all() {
  pkill -f "cabinet-worker-watchdog.sh" 2>/dev/null || true
  pkill -f "artisan serve.*--port=3002" 2>/dev/null || true
  pkill -f "artisan serve.*127.0.0.1:(13002|13003|13004|3002)" 2>/dev/null || true
  pkill -f "cabinet-proxy.mjs" 2>/dev/null || true
  for port in 3002 "${WORKERS[@]}"; do
    lsof -ti :"$port" -sTCP:LISTEN 2>/dev/null | xargs kill -9 2>/dev/null || true
  done
}

wait_workers_ready() {
  local ok=0
  for _ in 1 2 3 4 5 6 7 8 9 10; do
    ok=1
    for p in "${WORKERS[@]}"; do
      code=$(curl -sS -o /dev/null -w '%{http_code}' --max-time 6 "http://127.0.0.1:${p}/login" 2>/dev/null || echo "000")
      if [[ "$code" != "200" && "$code" != "302" ]]; then
        ok=0
        break
      fi
    done
    if [[ "$ok" -eq 1 ]]; then
      return 0
    fi
    sleep 1
  done
  echo "Не все воркеры ответили на /login — см. /tmp/cabinet-worker-*.log" >&2
  return 1
}

port_3002_listener() {
  lsof -i :3002 -sTCP:LISTEN 2>/dev/null | awk 'NR==2 {print $1}'
}

if [[ "${1:-}" == "stop" ]]; then
  stop_all
  echo "Кабинет остановлен"
  exit 0
fi

stop_all
sleep 1

# Next на 3002 ломает кабинет (после stop_all — только чужой процесс)
for pid in $(lsof -ti :3002 -sTCP:LISTEN 2>/dev/null); do
  cmd=$(ps -p "$pid" -o command= 2>/dev/null || true)
  if [[ "$cmd" == *"next dev"* ]] || [[ "$cmd" == *"next-server"* ]]; then
    echo "На :3002 висит Next (маркетинг). Остановите: cd titlo.ru && npm run dev:stop" >&2
    exit 1
  fi
  if [[ -n "$cmd" ]] && [[ "$cmd" != *"cabinet-proxy.mjs"* ]]; then
    echo "Порт 3002 занят (PID $pid): $cmd" >&2
    exit 1
  fi
done

php -v | head -1
if grep -q '^LOG_CHANNEL=stack' .env 2>/dev/null; then
  echo "Подсказка: в .env для Mac поставьте LOG_CHANNEL=stderr (иначе 500 на /login)" >&2
fi
php artisan config:clear 2>/dev/null || true

for p in "${WORKERS[@]}"; do
  nohup php artisan serve --host=127.0.0.1 --port="$p" >>/tmp/cabinet-worker-"$p".log 2>&1 &
  echo "$!" >"$PID_DIR/worker-$p.pid"
done

wait_workers_ready || true

if [[ "${CABINET_DEV_WATCHDOG:-1}" == "1" ]]; then
  chmod +x "$ROOT/scripts/cabinet-worker-watchdog.sh" 2>/dev/null || true
  nohup bash "$ROOT/scripts/cabinet-worker-watchdog.sh" >>/tmp/cabinet-watchdog.log 2>&1 &
  echo "$!" >"$PID_DIR/watchdog.pid"
fi

# :3002 — только Node-прокси, не artisan serve
if [[ "$(port_3002_listener)" == "php" ]]; then
  stop_all
  sleep 1
fi
if [[ -n "$(port_3002_listener)" ]]; then
  echo "Порт 3002 занят ($(port_3002_listener)). Закройте вкладки :3002 или: npm run dev:stop (если Next)" >&2
  exit 1
fi

export CABINET_PROXY_PORT="$PROXY_PORT"
export CABINET_WORKER_PORTS="$(IFS=,; echo "${WORKERS[*]}")"
export CABINET_PROXY_TIMEOUT_MS="${CABINET_PROXY_TIMEOUT_MS:-20000}"
nohup node "$ROOT/scripts/cabinet-proxy.mjs" >>/tmp/cabinet-proxy.log 2>&1 &
echo "$!" >"$PID_DIR/proxy.pid"

sleep 2
listener="$(port_3002_listener)"
if [[ "$listener" != "node" ]]; then
  echo "Прокси не слушает :3002 (сейчас: ${listener:-ничего}). /tmp/cabinet-proxy.log:" >&2
  tail -5 /tmp/cabinet-proxy.log 2>/dev/null >&2
  exit 1
fi
ok=0
for _ in 1 2 3 4 5 6 7 8; do
  if curl -sS --max-time 10 -o /dev/null http://127.0.0.1:3002/login; then
    ok=1
    break
  fi
  sleep 1
done

if [[ "$ok" -eq 1 ]]; then
  code=$(curl -sS -o /dev/null -w '%{http_code}' --max-time 8 http://127.0.0.1:3002/login)
  echo "Кабинет: http://localhost:3002/login → HTTP $code (воркеры ${WORKERS[*]})"
  echo "Остановка: $ROOT/scripts/dev-parallel.sh stop"
else
  echo "Кабинет не ответил на /login — см. /tmp/cabinet-worker-*.log" >&2
  exit 1
fi

trap 'stop_all; exit 0' INT TERM

if [[ -n "${CABINET_DEV_DETACH:-}" ]]; then
  exit 0
fi

# foreground: Ctrl+C — stop
wait
