#!/usr/bin/env bash
# Smoke: text-analyzer DataTables + jQCloud after POST analyze
set -euo pipefail
export PATH="/opt/homebrew/opt/php@7.4/bin:$PATH"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

php <<'PHP'
<?php
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\User::find(4);
if (!$user) { fwrite(STDERR, "User 4 not found\n"); exit(1); }
Auth::login($user);
if (function_exists('apply_global_team_permissions')) {
    apply_global_team_permissions();
}

$text = str_repeat(
    'слово тест анализ текста персональных данных поле некорректно это поле фмба сибирский западно международный ',
    50
);

$http = $app->make(Illuminate\Contracts\Http\Kernel::class);

$post = Illuminate\Http\Request::create('/text-analyzer', 'POST', [
    '_token' => csrf_token(),
    'type' => 'text',
    'textarea' => $text,
    'conjunctionsPrepositionsPronouns' => '1',
    'removeWords' => '0',
]);
$post->setLaravelSession($app->make('session.store'));
$post->setUserResolver(fn () => $user);
$respPost = $http->handle($post);
if (!in_array($respPost->getStatusCode(), [200, 302], true)) {
    fwrite(STDERR, "POST failed: ".$respPost->getStatusCode()."\n");
    exit(2);
}

$get = Illuminate\Http\Request::create('/text-analyzer', 'GET');
$get->setLaravelSession($app->make('session.store'));
$get->setUserResolver(fn () => $user);
$respGet = $http->handle($get);
$html = $respGet->getContent();
file_put_contents('/tmp/cabinet-ta-live.html', $html);

$checks = [
    'status_200' => $respGet->getStatusCode() === 200,
    'payload' => strpos($html, 'id="cabinet-ta-payload"') !== false,
    'init_fn' => strpos($html, 'cabinet-text-analyzer.js') !== false,
    'datatable_init' => strpos($html, 'cabinetTextAnalyzerConfig') !== false,
    'jqcloud_fn' => strpos($html, 'cabinet-text-analyzer.js') !== false,
    'jqcloud_lib' => strpos($html, 'jqcloud-1.0.4.min.js') !== false,
    'datatables_lib' => strpos($html, 'jquery.dataTables.min.js') !== false,
    'escaped_script' => strpos($html, '&lt;script') !== false,
    'total_rows' => preg_match_all('/<tr class="cabinet-ta-word-row/', $html, $m),
];

foreach ($checks as $k => $v) {
    if ($k === 'total_rows') {
        echo "$k: $v\n";
    } else {
        echo "$k: ".($v ? 'OK' : 'FAIL')."\n";
    }
}

if (preg_match('/id="cabinet-ta-payload">(.+?)<\/script>/s', $html, $match)) {
    $payload = json_decode(html_entity_decode($match[1]), true);
    $cloudN = count($payload['clouds']['text'] ?? []);
    echo "cloud_words: $cloudN\n";
    if ($cloudN > 0) {
        echo "cloud_sample: ".json_encode($payload['clouds']['text'][0], JSON_UNESCAPED_UNICODE)."\n";
    }
}

// Extract script block for browser test marker
if (!strpos($html, 'cabinet-text-analyzer.js')) {
    exit(3);
}
PHP

echo "--- Browser smoke (playwright) ---"
node <<'NODE'
const fs = require('fs');
const path = require('path');
const htmlPath = '/tmp/cabinet-ta-live.html';
if (!fs.existsSync(htmlPath)) {
  console.error('no html');
  process.exit(1);
}

async function main() {
  let playwright;
  try {
    playwright = require('playwright');
  } catch (e) {
    console.log('playwright not installed, skip browser test');
    process.exit(0);
  }

  const html = fs.readFileSync(htmlPath, 'utf8');
  const browser = await playwright.chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1400, height: 900 } });

  const consoleErrors = [];
  page.on('pageerror', (err) => consoleErrors.push(String(err)));
  page.on('console', (msg) => {
    if (msg.type() === 'error') consoleErrors.push(msg.text());
  });

  await page.setContent(html, { waitUntil: 'networkidle', timeout: 60000 });

  // Wait for init
  await page.waitForTimeout(2000);

  const state = await page.evaluate(() => {
    const hasDt = typeof window.jQuery !== 'undefined' && window.jQuery.fn.DataTable && window.jQuery.fn.DataTable.isDataTable('#totalTable');
    const hasDtPhrases = typeof window.jQuery !== 'undefined' && window.jQuery.fn.DataTable && window.jQuery.fn.DataTable.isDataTable('#phrasesTable');
    const cloudSpans = document.querySelectorAll('#cabinet-ta-cloud-text-host span').length;
    const paginateTotal = document.querySelectorAll('#totalTable_wrapper .dataTables_paginate .paginate_button').length;
    const paginatePhrases = document.querySelectorAll('#phrasesTable_wrapper .dataTables_paginate .paginate_button').length;
    const jqCloudFn = typeof window.jQuery !== 'undefined' && typeof window.jQuery.fn.jQCloud === 'function';
    let payloadClouds = 0;
    const el = document.getElementById('cabinet-ta-payload');
    if (el) {
      try {
        payloadClouds = JSON.parse(el.textContent).clouds.text.length;
      } catch (e) {}
    }
    return { hasDt, hasDtPhrases, cloudSpans, paginateTotal, paginatePhrases, jqCloudFn, payloadClouds };
  });

  console.log(JSON.stringify(state, null, 2));
  if (consoleErrors.length) {
    console.log('console_errors:', consoleErrors.slice(0, 5));
  }

  await browser.close();

  const ok = state.hasDt && state.hasDtPhrases && state.jqCloudFn && state.cloudSpans > 0 && state.paginateTotal > 0;
  process.exit(ok ? 0 : 4);
}

main().catch((e) => {
  console.error(e);
  process.exit(5);
});
NODE
