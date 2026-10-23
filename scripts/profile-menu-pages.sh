#!/usr/bin/env bash
# Прогон группы A (main_projects show=1) + ключевые страницы C.
set -euo pipefail

export PATH="/opt/homebrew/opt/php@8.5/bin:$PATH"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
USER_ID="${1:-4}"

PATHS=(
  "/"
  "/analyze-relevance"
  "/competitor-analysis"
  "/text-analyzer"
  "/cluster"
  "/duplicates"
  "/list-comparison"
  "/unique"
  "/counting-text-length"
  "/html-editor"
  "/monitoring"
  "/site-monitoring"
  "/domain-information"
  "/meta-tags"
  "/backlink"
  "/http-headers"
  "/utm-marks"
  "/password-generator"
  "/keyword-generator"
  "/roi-calculator"
  "/configuration-menu"
  "/news"
  "/profile/"
  "/balance"
  "/tariff"
  "/partners"
  "/history"
)

echo "user_id=$USER_ID"
printf "%-28s %5s %6s %5s\n" "URI" "HTTP" "ms" "SQL"
echo "--------------------------------------------------------------"

for uri in "${PATHS[@]}"; do
  line=$("$ROOT/scripts/profile-page.sh" "$uri" "$USER_ID" 2>/dev/null || echo "$uri|ERR|0|0|0")
  IFS='|' read -r path code ms sql size <<< "$line"
  printf "%-28s %5s %6s %5s\n" "$path" "$code" "$ms" "$sql"
done
