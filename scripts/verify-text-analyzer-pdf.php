<?php

/**
 * Проверка PDF: обложка с растром на весь лист, тело со 2-й страницы.
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\User::find(4);
if ($user === null) {
    fwrite(STDERR, "verify_pdf: user id=4 not found\n");
    exit(1);
}
Auth::login($user);

$response = App\TextAnalyzer::analyze(
    str_repeat('seo текст ', 120),
    ['type' => 'text', 'compare' => 1, 'competitor' => 'https://almamed.su/']
);
$meta = [
    'generated_at' => date('d.m.Y H:i'),
    'source_label' => 'https://www.dealmed.ru/o_kompanii.html',
    'version' => config('cabinet-text-analyzer.version', '6.2'),
];
$pdf = app(App\Services\TextAnalyzerPdfService::class)->renderBinary($response, ['type' => 'text'], $meta);

$coverPath = App\Support\TextAnalyzerPdfBranding::coverPageImagePath(
    $meta,
    true,
    'Конкурент · almamed.su'
);

$errors = [];
foreach (App\Support\TextAnalyzerPdfBranding::verifyCoverPng($coverPath) as $e) {
    $errors[] = $e;
}
foreach (App\Support\TextAnalyzerPdfBranding::verifyLogoIconPng(App\Support\TextAnalyzerPdfBranding::logoIconPath()) as $e) {
    $errors[] = $e;
}
$size = strlen($pdf);

if ($size < 90000) {
    $errors[] = "pdf too small ($size bytes)";
}
if (substr($pdf, 0, 4) !== '%PDF') {
    $errors[] = 'not a PDF';
}

preg_match_all('/\/Type\s*\/Page\b/', $pdf, $pages);
$pageCount = count($pages[0]);
if ($pageCount < 2) {
    $errors[] = 'expected >=2 pages, got ' . $pageCount;
}

preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $streams);
$hasCoverRaster = false;
$coverStreamOk = false;
$bodyStreamOk = false;
$contentIdx = 0;

foreach ($streams[1] as $raw) {
    $data = @gzuncompress($raw);
    if ($data === false) {
        $data = $raw;
    }
    if (strlen($data) > 500000) {
        $hasCoverRaster = true;
    }
    if (!preg_match('/\/I\d+\s+Do|\)\s*Tj/', $data)) {
        continue;
    }
  $contentIdx++;
    if ($contentIdx === 1) {
        $fullPageImg = (bool) preg_match('/59[35]\.\d+\s+0\s+0\s+83[89]\.\d+\s+0/m', $data);
        $coverStreamOk = preg_match('/\/I\d+\s+Do/', $data) && !preg_match('/\)\s*Tj/', $data) || $fullPageImg;
        if (preg_match('/\)\s*Tj/', $data) && preg_match_all('/\)\s*Tj/', $data, $tj) > 3) {
            $errors[] = 'cover page contains body header/footer text';
        }
    }
    if ($contentIdx === 2 && preg_match('/\)\s*Tj/', $data)) {
        $bodyStreamOk = true;
    }
}

if (!$hasCoverRaster) {
    $errors[] = 'cover PNG not embedded';
}
if (!$coverStreamOk) {
    $errors[] = 'cover page has no full-page image';
}
if (!$bodyStreamOk) {
    $errors[] = 'page 2 has no text';
}

if ($errors !== []) {
    foreach ($errors as $e) {
        fwrite(STDERR, "verify_pdf: FAIL $e\n");
    }
    exit(1);
}

echo "verify_pdf: OK pages=$pageCount bytes=$size\n";
exit(0);
