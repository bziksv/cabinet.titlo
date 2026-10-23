#!/usr/bin/env bash
# Локальная обработка очереди мониторинга (частотность high, позиции position_*).
# Нужен, если с localhost ставите задачи в database-очередь и не хотите ждать prod workers.
set -euo pipefail
cd "$(dirname "$0")/.."
export PATH="/opt/homebrew/opt/php@8.5/bin:/opt/homebrew/opt/php@8.5/sbin:${PATH:-}"

echo "Monitoring queue worker (database: default,high,position_high,position_low). Ctrl+C to stop."
exec php artisan queue:work database \
  --queue=default,cluster_high,high,medium,position_high,position_low \
  --sleep=1 --tries=3 --timeout=600
