#!/usr/bin/env bash
# Smoke: text-analyzer tables + jQCloud after POST analyze
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
    'table_search' => strpos($html, 'cabinet-ta-table-search') !== false,
    'table_scroll' => strpos($html, 'cabinet-ta-table-scroll') !== false,
    'no_datatables_lib' => strpos($html, 'jquery.dataTables.min.js') === false,
    'no_jqcloud_lib' => strpos($html, 'jqcloud-1.0.4.min.js') === false,
    'tag_cloud_js' => strpos($html, 'cabinet-ta-tag-cloud') !== false || strpos($html, 'cabinet-text-analyzer.js') !== false,
    'spiral_cloud_css' => strpos($html, 'cabinet-text-analyzer.css') !== false,
    'export_bar' => strpos($html, 'cabinet-ta-export-bar') !== false,
    'escaped_script' => strpos($html, '&lt;script') === false,
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

$excludeText = str_repeat('персональных данных обработку поле ', 40);
$excludeReq = Illuminate\Http\Request::create('/text-analyzer', 'POST', [
    '_token' => csrf_token(),
    'type' => 'text',
    'textarea' => $excludeText,
    'removeWords' => '1',
    'listWords' => "персональных",
]);
$excludeReq->setLaravelSession($app->make('session.store'));
$excludeReq->setUserResolver(fn () => $user);
$excludeResp = $http->handle($excludeReq);
$excludeHtml = $excludeResp->getStatusCode() === 302
    ? $http->handle(Illuminate\Http\Request::create('/text-analyzer', 'GET', [], [], [], [], null, $excludeReq->cookies->all()))->getContent()
  : $excludeResp->getContent();
if (preg_match('/id="cabinet-ta-payload">(.+?)<\/script>/s', $excludeHtml, $excludeMatch)) {
    $excludePayload = json_decode(html_entity_decode($excludeMatch[1]), true);
    $hasExcluded = false;
    foreach ($excludePayload['totalWords'] ?? [] as $row) {
        if (mb_stripos((string) ($row['text'] ?? ''), 'персон') !== false) {
            $hasExcluded = true;
            break;
        }
    }
    echo 'exclude_personal: '.($hasExcluded ? 'FAIL' : 'OK')."\n";

    $baseGeneral = App\TextAnalyzer::analyze($excludeText, [
        'type' => 'text',
        'removeWords' => '0',
        'listWords' => '',
    ])['general']['countWords'] ?? 0;
    $excludedGeneral = App\TextAnalyzer::analyze($excludeText, [
        'type' => 'text',
        'removeWords' => '1',
        'listWords' => 'персональных',
    ])['general']['countWords'] ?? 0;
    echo 'exclude_general_words: '.($excludedGeneral > 0 && $excludedGeneral < $baseGeneral ? 'OK' : 'FAIL')."\n";
    echo 'general_words_base: '.$baseGeneral."\n";
    echo 'general_words_excluded: '.$excludedGeneral."\n";
}

$removed = App\TextAnalyzer::removeWords('персональных', ' обработку персональных данных ');
echo 'remove_words_fn: '.(mb_stripos($removed, 'персон') === false ? 'OK' : 'FAIL')."\n";
echo 'exclude_flag_1: '.(App\TextAnalyzer::shouldApplyCustomWordExclusion(['removeWords' => '1', 'listWords' => 'x']) ? 'OK' : 'FAIL')."\n";
$phraseRows = App\TextAnalyzer::searchPhrases($removed, false);
$phraseRows = App\TextAnalyzer::filterExcludedFromPhrases($phraseRows, 'персональных');
$phraseHasPersonal = false;
foreach ($phraseRows as $row) {
    if (mb_stripos((string) ($row['phrase'] ?? ''), 'персон') !== false) {
        $phraseHasPersonal = true;
        break;
    }
}
echo 'exclude_phrases_fn: '.($phraseHasPersonal ? 'FAIL' : 'OK')."\n";

if (!strpos($html, 'cabinet-text-analyzer.js')) {
    exit(3);
}

$shareCreateReq = Illuminate\Http\Request::create('/text-analyzer/public-share', 'POST', ['_token' => csrf_token()]);
$shareCreateReq->setLaravelSession($app->make('session.store'));
$shareCreateReq->setUserResolver(fn () => $user);
$shareCreateResp = $http->handle($shareCreateReq);
$sharePayload = json_decode((string) $shareCreateResp->getContent(), true);
$publicShareOk = false;
$publicShareUrl = '';
if (is_array($sharePayload) && !empty($sharePayload['success']) && !empty($sharePayload['url'])) {
    $publicShareUrl = (string) $sharePayload['url'];
    $path = parse_url($publicShareUrl, PHP_URL_PATH);
    if ($path) {
        $publicGet = Illuminate\Http\Request::create($path, 'GET');
        $publicGet->setUserResolver(fn () => null);
        $publicResp = $http->handle($publicGet);
        $publicHtml = $publicResp->getContent();
        $publicShareOk = $publicResp->getStatusCode() === 200
            && strpos($publicHtml, 'cabinet-ta-results') !== false
            && strpos($publicHtml, 'cabinet-ta-kpi') !== false
            && strpos($publicHtml, 'cabinet-ta-public-banner') !== false
            && strpos($publicHtml, 'cabinet-ta-export-bar') === false;
    }
}
echo 'public_share_create: '.((is_array($sharePayload) && !empty($sharePayload['success'])) ? 'OK' : 'FAIL')."\n";
echo 'public_share_view: '.($publicShareOk ? 'OK' : 'FAIL')."\n";
if ($publicShareUrl !== '') {
    echo 'public_share_url: '.$publicShareUrl."\n";
}

$excelReq = Illuminate\Http\Request::create('/text-analyzer/export/excel', 'POST', ['_token' => csrf_token()]);
$excelReq->setLaravelSession($app->make('session.store'));
$excelReq->setUserResolver(fn () => $user);
$excelResp = $http->handle($excelReq);
$excelType = (string) $excelResp->headers->get('Content-Type', '');
echo 'export_excel: '.($excelResp->getStatusCode() === 200 && strpos($excelType, 'spreadsheet') !== false ? 'OK' : 'FAIL')."\n";

$pdfReq = Illuminate\Http\Request::create('/text-analyzer/export/pdf', 'POST', ['_token' => csrf_token()]);
$pdfReq->setLaravelSession($app->make('session.store'));
$pdfReq->setUserResolver(fn () => $user);
$pdfResp = $http->handle($pdfReq);
$pdfType = (string) $pdfResp->headers->get('Content-Type', '');
echo 'export_pdf: '.($pdfResp->getStatusCode() === 200 && strpos($pdfType, 'pdf') !== false ? 'OK' : 'FAIL')."\n";
if ($pdfResp->getStatusCode() === 200) {
    $pdfMagic = 'FAIL';
    if ($pdfResp instanceof Symfony\Component\HttpFoundation\BinaryFileResponse) {
        $pdfPath = $pdfResp->getFile()->getPathname();
        $pdfMagic = substr(file_get_contents($pdfPath), 0, 4) === '%PDF' ? 'OK' : 'FAIL';
        echo 'export_pdf_bytes: '.filesize($pdfPath)."\n";
    } else {
        $pdfBody = $pdfResp->getContent();
        echo 'export_pdf_bytes: '.strlen($pdfBody)."\n";
        $pdfMagic = substr($pdfBody, 0, 4) === '%PDF' ? 'OK' : 'FAIL';
    }
    echo 'export_pdf_magic: '.$pdfMagic."\n";
    if ($pdfResp->getStatusCode() === 200) {
        $pdfText = '';
        if ($pdfResp instanceof Symfony\Component\HttpFoundation\BinaryFileResponse) {
            $pdfText = (string) file_get_contents($pdfResp->getFile()->getPathname());
        } else {
            $pdfText = (string) $pdfResp->getContent();
        }
        $hasBrandText = (strpos($pdfText, 'Датагон') !== false || strpos($pdfText, 'Datagon') !== false);
        $hasBrandAssets = is_file(public_path('img/logo-pdf.png')) && is_file(public_path('img/logo-icon-pdf.png'));
        $pdfSize = strlen($pdfText);
        $hasBrand = $hasBrandText || ($hasBrandAssets && $pdfSize > 28000);
        echo 'export_pdf_brand: '.($hasBrand ? 'OK' : 'FAIL')."\n";
        echo 'export_pdf_size_ok: '.($pdfSize > 28000 ? 'OK' : 'FAIL')." ($pdfSize bytes)\n";
    }
}
PHP

php scripts/verify-text-analyzer-pdf.php || exit 1

echo "--- Browser smoke (playwright) ---"
node <<'NODE'
const fs = require('fs');
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
  await page.waitForTimeout(2500);

  const state = await page.evaluate(() => {
    const cloudWords = document.querySelectorAll('#cabinet-ta-cloud-text-host .cabinet-ta-spiral-cloud__word, #cabinet-ta-cloud-text-host .cabinet-ta-tag-cloud__word').length;
    const wordRows = document.querySelectorAll('#totalTable tbody tr.cabinet-ta-word-row').length;
    const phraseRows = document.querySelectorAll('#phrasesTable tbody tr').length;
    const visibleWordRows = document.querySelectorAll('#totalTable tbody tr.cabinet-ta-word-row:not(.d-none)').length;
    const searchInputs = document.querySelectorAll('.cabinet-ta-table-search').length;
    const scrollWraps = document.querySelectorAll('.cabinet-ta-table-scroll').length;
    let payloadClouds = 0;
    const el = document.getElementById('cabinet-ta-payload');
    if (el) {
      try {
        payloadClouds = JSON.parse(el.textContent).clouds.text.length;
      } catch (e) {}
    }
    return { wordRows, visibleWordRows, phraseRows, cloudWords, searchInputs, scrollWraps, payloadClouds };
  });

  console.log(JSON.stringify(state, null, 2));
  if (consoleErrors.length) {
    console.log('console_errors:', consoleErrors.slice(0, 5));
  }

  await browser.close();

  const ok = state.wordRows > 10
    && state.visibleWordRows === state.wordRows
    && state.phraseRows > 5
    && state.searchInputs >= 2
    && state.scrollWraps >= 2
    && state.cloudWords > 5;
  process.exit(ok ? 0 : 4);
}

main().catch((e) => {
  console.error(e);
  process.exit(5);
});
NODE
