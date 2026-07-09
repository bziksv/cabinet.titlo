#!/usr/bin/env bash
# Локальные воркеры очереди анализа релевантности (relevance_*).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

export PATH="/opt/homebrew/opt/php@7.4/bin:/opt/homebrew/opt/php@7.4/sbin:${PATH:-}"

PID_DIR="/tmp/cabinet-relevance-queue-pids"
LOG_FILE="/tmp/cabinet-relevance-queue.log"
QUEUES="relevance_high_priority,relevance_medium_priority,relevance_normal_priority"
WORKERS="${RELEVANCE_QUEUE_WORKERS:-2}"

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
  pkill -f "artisan queue:work.*relevance_high_priority" 2>/dev/null || true
}

start_workers() {
  stop_workers
  php artisan queue:restart >/dev/null 2>&1 || true
  mkdir -p "$PID_DIR"
  : >"$LOG_FILE"
  local i pid
  for i in $(seq 1 "$WORKERS"); do
    nohup php artisan queue:work database \
      --queue="$QUEUES" \
      --sleep=1 \
      --tries=2 \
      --timeout=0 \
      >>"$LOG_FILE" 2>&1 &
    pid=$!
    echo "$pid" >"$PID_DIR/worker-${i}.pid"
    echo "Relevance worker $i PID $pid"
  done
  echo "Queues: $QUEUES, log: $LOG_FILE"
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

case "${1:-restart}" in
  stop)
    stop_workers
    echo "Relevance queue workers stopped"
    ;;
  status)
    n="$(running_count)"
    if [[ "$n" -gt 0 ]]; then
      echo "running $n worker(s), queues: $QUEUES"
      tail -5 "$LOG_FILE" 2>/dev/null || true
    else
      echo "not running"
    fi
    ;;
  start)
    start_workers
    ;;
  restart|*)
    start_workers
    ;;
esac
