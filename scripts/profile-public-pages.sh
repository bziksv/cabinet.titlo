#!/usr/bin/env bash
# Профиль публичных страниц (без auth) — C.10 мини-аудит.
set -euo pipefail

export PATH="/opt/homebrew/opt/php@8.5/bin:$PATH"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

PATHS=(
  "/login"
  "/register"
)

echo "public pages (no auth)"
printf "%-24s %5s %6s %5s\n" "URI" "HTTP" "ms" "SQL"
echo "--------------------------------------------------------"

for uri in "${PATHS[@]}"; do
  line=$(cd "$ROOT" && PROFILE_URI="$uri" php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
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
' 2>/dev/null || echo "$uri|ERR|0|0|0")
  IFS='|' read -r path code ms sql size <<< "$line"
  printf "%-24s %5s %6s %5s\n" "$path" "$code" "$ms" "$sql"
done
