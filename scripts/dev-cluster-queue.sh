#!/usr/bin/env bash
# Локальные воркеры очередей кластеризатора (новый RiverFacade / Wordstat New).
# Без них jobs из :3002 забирает прод на lk.redbox.su со старым /wordstat/json → частотность 0.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

export PATH="/opt/homebrew/opt/php@8.5/bin:/opt/homebrew/opt/php@8.5/sbin:${PATH:-}"

PID_DIR="/tmp/cabinet-cluster-queue-pids"
LOG_FILE="/tmp/cabinet-cluster-queue.log"

queue_prefix() {
  local prefix=""
  if [[ -f .env ]]; then
    prefix="$(grep '^CLUSTER_QUEUE_PREFIX=' .env 2>/dev/null | cut -d= -f2- | tr -d '\r' || true)"
    if [[ -z "$prefix" ]]; then
      local app_env
      app_env="$(grep '^APP_ENV=' .env 2>/dev/null | cut -d= -f2- | tr -d '\r' || true)"
      if [[ "$app_env" == "local" ]]; then
        prefix="local_"
      fi
    fi
  fi
  echo "$prefix"
}

worker_count() {
  local n
  n="$(grep '^CLUSTER_QUEUE_WORKERS=' .env 2>/dev/null | cut -d= -f2- | tr -d '\r' || true)"
  if [[ -z "$n" ]]; then
    n=4
  fi
  echo "$n"
}

PREFIX="$(queue_prefix)"
QUEUES="${PREFIX}main_cluster,${PREFIX}child_cluster,${PREFIX}cluster_wait"
WORKERS="$(worker_count)"

stop_workers() {
  if [[ -d "$PID_DIR" ]]; then
  for f in "$PID_DIR"/*.pid; do
    [[ -f "$f" ]] || continue
    local pid
    pid="$(cat "$f" 2>/dev/null || true)"
    if [[ -n "$pid" ]] && kill -0 "$pid" 2>/dev/null; then
      kill "$pid" 2>/dev/null || true
      sleep 0.2
      kill -9 "$pid" 2>/dev/null || true
    fi
  done
    rm -rf "$PID_DIR"
  fi
  pkill -f "artisan queue:work.*${QUEUES}" 2>/dev/null || true
  rm -f /tmp/cabinet-cluster-queue.pid
}

start_workers() {
  stop_workers
  mkdir -p "$PID_DIR"
  : >"$LOG_FILE"
  local i pid
  for i in $(seq 1 "$WORKERS"); do
    # Отдельная session: иначе Cursor/IDE убивает воркеры вместе с shell после команды.
    # macOS часто без setsid — используем Python start_new_session.
    pid="$(
      PATH="/opt/homebrew/opt/php@8.5/bin:${PATH}" \
      QUEUE_LIST="$QUEUES" \
      LOG_FILE="$LOG_FILE" \
      ROOT="$ROOT" \
      python3 - <<'PY'
import os, subprocess
root = os.environ["ROOT"]
queues = os.environ["QUEUE_LIST"]
log_path = os.environ["LOG_FILE"]
os.chdir(root)
log = open(log_path, "a")
proc = subprocess.Popen(
    [
        "php", "artisan", "queue:work", "database",
        "--queue=" + queues,
        "--sleep=1",
        "--tries=2",
        "--timeout=600",
    ],
    stdout=log,
    stderr=log,
    stdin=subprocess.DEVNULL,
    start_new_session=True,
    env=os.environ.copy(),
)
print(proc.pid)
PY
    )"
    echo "$pid" >"$PID_DIR/worker-${i}.pid"
    echo "Worker $i PID $pid"
  done
  echo "$WORKERS" >"$PID_DIR/count"
  echo "Cluster queue workers: $WORKERS, log: $LOG_FILE"
  echo "Queues: $QUEUES (prefix=${PREFIX:-none})"
}

running_count() {
  local n=0
  if [[ ! -d "$PID_DIR" ]]; then
    echo 0
    return
  fi
  for f in "$PID_DIR"/worker-*.pid; do
    [[ -f "$f" ]] || continue
    local pid
    pid="$(cat "$f" 2>/dev/null || true)"
    if [[ -n "$pid" ]] && kill -0 "$pid" 2>/dev/null; then
      n=$((n + 1))
    fi
  done
  echo "$n"
}

case "${1:-start}" in
  stop)
    stop_workers
    echo "Cluster queue workers stopped"
    ;;
  status)
    local_n="$(running_count)"
    if [[ "$local_n" -gt 0 ]]; then
      echo "running $local_n worker(s), queues: $QUEUES"
      tail -5 "$LOG_FILE" 2>/dev/null || true
    else
      echo "not running"
    fi
    ;;
  restart)
    start_workers
    ;;
  start|*)
    start_workers
    ;;
esac
