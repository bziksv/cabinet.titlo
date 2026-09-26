#!/usr/bin/env bash
# Деплой cabinet.titlo.ru на s3 (155.212.171.103).
# Включает git pull, composer, кэши и ОБЯЗАТЕЛЬНЫЙ перезапуск queue workers.
set -euo pipefail

SSH_HOST="${DEPLOY_SSH:-root@155.212.171.103}"
BRANCH="${DEPLOY_BRANCH:-main}"

echo "Deploy cabinet.titlo.ru → ${SSH_HOST} (branch ${BRANCH})"

ssh -o BatchMode=yes "$SSH_HOST" bash -s "$BRANCH" <<'REMOTE'
set -euo pipefail
BRANCH="$1"
APP=/var/www/cabinet_titl_usr/data/www/cabinet.titlo.ru
PHP=/opt/php74/bin/php
USER=cabinet_titl_usr

cd "$APP"
git fetch origin
git checkout "$BRANCH"
git reset --hard "origin/${BRANCH}"

COMPOSER_ALLOW_SUPERUSER=1 "$PHP" "$(which composer)" install --no-dev --optimize-autoloader --no-interaction

if git diff --name-only HEAD@{1} HEAD 2>/dev/null | grep -qE '^(package(-lock)?\.json|resources/(js|sass)/)'; then
  NODE_OPTIONS=--openssl-legacy-provider npm run production
fi

chown -R "$USER:$USER" "$APP"

sudo -u "$USER" "$PHP" artisan migrate --force --no-interaction
sudo -u "$USER" "$PHP" artisan config:clear
sudo -u "$USER" "$PHP" artisan config:cache
sudo -u "$USER" "$PHP" artisan route:clear
sudo -u "$USER" "$PHP" artisan view:cache

# Workers держат старый PHP-код в памяти — без рестарта jobs идут со старым кодом.
sudo -u "$USER" "$PHP" artisan queue:restart

SUPERVISOR_GROUPS=(
  cabinet-titlo-default
  cabinet-titlo-db-optimize
  cabinet-titlo-cluster-child
  cabinet-titlo-cluster-main
  cabinet-titlo-cluster-wait
  cabinet-titlo-position
  cabinet-titlo-relevance
  cabinet-titlo-monitoring-helper
  cabinet-titlo-monitoring-change-dates
  cabinet-titlo-monitoring-wait
  cabinet-titlo-monitoring-competitors-stat
  cabinet-titlo-competitor-analyse
  cabinet-titlo-ai-generation
  cabinet-titlo-site-audit
  cabinet-titlo-site-audit-aggregate
)

for group in "${SUPERVISOR_GROUPS[@]}"; do
  if supervisorctl status "${group}:" 2>/dev/null | grep -q RUNNING; then
    echo "supervisorctl restart ${group}:*"
    supervisorctl restart "${group}:"*
  fi
done

curl -sI https://cabinet.titlo.ru/login | head -3
echo "Deploy OK"
REMOTE
