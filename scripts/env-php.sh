# shellcheck shell=bash
# Общий PATH для локальных скриптов кабинета.
# Откат на 7.4: CABINET_PHP_PREFIX=/opt/homebrew/opt/php@7.4 ./scripts/dev-local.sh
CABINET_PHP_PREFIX="${CABINET_PHP_PREFIX:-/opt/homebrew/opt/php@8.5}"
export PATH="${CABINET_PHP_PREFIX}/bin:${CABINET_PHP_PREFIX}/sbin:${PATH:-}"
