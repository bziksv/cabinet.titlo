#!/usr/bin/env bash
# Профиль одной страницы кабинета: время + число SQL (auth user).
# Usage: ./scripts/profile-page.sh /news [user_id]
set -euo pipefail

export PATH="/opt/homebrew/opt/php@8.5/bin:$PATH"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
URI="${1:-/}"
USER_ID="${2:-4}"

cd "$ROOT"
PROFILE_URI="$URI" PROFILE_USER_ID="$USER_ID" php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$user = App\User::find((int) getenv("PROFILE_USER_ID"));
if (!$user) { fwrite(STDERR, "User not found: ".getenv("PROFILE_USER_ID")."\n"); exit(1); }
Auth::login($user);
apply_global_team_permissions();
DB::enableQueryLog();
$t = microtime(true);
$uri = getenv("PROFILE_URI");
try {
    ob_start();
    $resp = $app->make(Illuminate\Contracts\Http\Kernel::class)
        ->handle(Illuminate\Http\Request::create($uri, "GET"));
    ob_end_clean();
    $ms = round((microtime(true) - $t) * 1000);
    $n = count(DB::getQueryLog());
    echo $uri."|".$resp->getStatusCode()."|".$ms."|".$n."|".strlen($resp->getContent())."\n";
} catch (Throwable $e) {
    fwrite(STDERR, $uri.": ".$e->getMessage()."\n");
    exit(2);
}
'
