#!/usr/bin/env bash
# Полный аудит: menu-pages + extended + public + limits-composer.
# Usage: ./scripts/profile-full-audit.sh [user_id]
# Ориентир по времени: ~5–8 мин (remote DB).
set -euo pipefail

export PATH="/opt/homebrew/opt/php@8.5/bin:$PATH"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
USER_ID="${1:-4}"
LOG="${2:-/tmp/cabinet-full-audit-$(date +%Y%m%d-%H%M).log}"

{
  echo "=== FULL AUDIT user_id=$USER_ID $(date -Iseconds) ==="
  echo ""
  echo "=== A + key C (profile-menu-pages.sh) ==="
  "$ROOT/scripts/profile-menu-pages.sh" "$USER_ID"
  echo ""
  echo "=== B + C extended (profile-audit-extended.sh) ==="
  "$ROOT/scripts/profile-audit-extended.sh" "$USER_ID"
  echo ""
  echo "=== C.10 public ==="
  "$ROOT/scripts/profile-public-pages.sh"
  echo ""
  echo "=== limits composer (prod-like) ==="
  "$ROOT/scripts/profile-limits-composer.sh" "$USER_ID" || echo "profile-limits-composer: failed"
  echo ""
  echo "=== DONE $(date -Iseconds) ==="
} 2>&1 | tee "$LOG"

echo "LOG: $LOG"
