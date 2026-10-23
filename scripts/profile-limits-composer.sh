#!/usr/bin/env bash
# Замер SQL по LimitsComposer::getUsedLimit (все ключи тарифа пользователя).
# Usage: ./scripts/profile-limits-composer.sh [user_id]
set -euo pipefail

export PATH="/opt/homebrew/opt/php@8.5/bin:$PATH"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
USER_ID="${1:-4}"

cd "$ROOT"
PROFILE_USER_ID="$USER_ID" php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$user = App\User::find((int) getenv("PROFILE_USER_ID"));
if (!$user) { fwrite(STDERR, "User not found\n"); exit(1); }
Auth::login($user);
apply_global_team_permissions();
$tariff = $user->tariff();
$keys = isset($tariff) ? array_keys($tariff->getAsArray()["settings"] ?? []) : [];
DB::enableQueryLog();
$t = microtime(true);
foreach ($keys as $key) {
    App\ViewComposers\LimitsComposer::getUsedLimit($key, $user);
}
$ms = round((microtime(true) - $t) * 1000);
$n = count(DB::getQueryLog());
echo "limits|".$ms."|".$n."|".count($keys)." keys\n";
'
