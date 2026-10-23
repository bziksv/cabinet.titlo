#!/usr/bin/env bash
# Прогон страниц вне пакета profile-menu-pages (группы B, C).
set -euo pipefail

export PATH="/opt/homebrew/opt/php@8.5/bin:$PATH"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
USER_ID="${1:-4}"

PATHS=(
  "/create-queue"
  "/relevance-config"
  "/access-projects"
  "/share-my-projects"
  "/all-projects"
  "/main-projects"
  "/users"
  "/visits-statistics/"
  "/modules-statistics/"
  "/meta-tags/settings"
  "/meta-tags/statistic"
  "/create-project"
  "/create-news"
  "/edit-news/45"
  "/edit-project/3"
  "/meta-tags/history/7317"
  "/meta-tags/history/9451/compare/9448"
  "/main-projects/statistics/37"
  "/http-headers/settings"
  "/cluster-projects"
  "/visit-statistics/4"
  "/create-description"
  "/edit-description/4"
  "/get-user-jobs"
  "/get-queue-count"
  "/competitors-config"
  "/monitoring/admin"
  "/monitoring/stat"
  "/cluster-configuration"
  "/ai-generation/story"
)

echo "user_id=$USER_ID"
printf "%-32s %5s %6s %5s\n" "URI" "HTTP" "ms" "SQL"
echo "----------------------------------------------------------------"

for uri in "${PATHS[@]}"; do
  line=$("$ROOT/scripts/profile-page.sh" "$uri" "$USER_ID" 2>/dev/null || echo "$uri|ERR|0|0|0")
  IFS='|' read -r path code ms sql size <<< "$line"
  printf "%-32s %5s %6s %5s\n" "$path" "$code" "$ms" "$sql"
done
