#!/usr/bin/env bash
# Локальный кабинет :3002 — nginx+php-fpm (по умолчанию)
set -euo pipefail
cd "$(dirname "$0")/.."
export PATH="/opt/homebrew/opt/php@8.5/bin:/opt/homebrew/opt/php@8.5/sbin:${PATH:-}"

if [[ "${1:-}" == "parallel" ]]; then
  echo "dev-parallel устарел, используйте dev-fpm (nginx+php-fpm)" >&2
  exec bash scripts/dev-fpm.sh "${@:2}"
fi

if [[ "${1:-}" == "serve" ]]; then
  exec bash scripts/dev-local.sh serve "${@:2}"
fi

exec bash scripts/dev-local.sh "${@:-}"
