#!/usr/bin/env bash
# Локальный кабинет :3002 — по умолчанию nginx+php-fpm (много вкладок).
# Fallback: CABINET_DEV_SERVE=1 — один artisan serve (медленно).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

export PATH="/opt/homebrew/opt/php@8.5/bin:/opt/homebrew/opt/php@8.5/sbin:${PATH:-}"

PORT=3002

if [[ "${1:-}" == "stop" ]]; then
  bash "$ROOT/scripts/dev-fpm.sh" stop
  exit 0
fi

if [[ "${1:-}" == "status" ]]; then
  bash "$ROOT/scripts/dev-fpm.sh" status 2>/dev/null || true
  if [[ -f /tmp/cabinet-dev.pid ]] && kill -0 "$(cat /tmp/cabinet-dev.pid)" 2>/dev/null; then
    echo "Также: artisan serve PID $(cat /tmp/cabinet-dev.pid)"
  fi
  exit 0
fi

if [[ "${1:-}" == "logs" ]]; then
  exec tail -f /tmp/cabinet-dev.log /tmp/cabinet-nginx-access.log 2>/dev/null
fi

if [[ "${1:-}" == "serve" ]] || [[ "${CABINET_DEV_SERVE:-0}" == "1" ]]; then
  shift 2>/dev/null || true
  exec bash "$ROOT/scripts/dev-local-serve.sh" "${@:-}"
fi

# Канон: php-fpm
if [[ "${1:-}" == "detach" ]]; then
  export CABINET_DEV_DETACH=1
  exec bash "$ROOT/scripts/dev-fpm.sh" detach
fi

exec bash "$ROOT/scripts/dev-fpm.sh" "${@:-}"
