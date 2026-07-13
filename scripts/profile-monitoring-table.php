<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$projectId = (int) ($argv[1] ?? 63);
$datesRange = $argv[2] ?? '15-06-2026 - 14-07-2026';
$length = (int) ($argv[3] ?? 100);

$userId = DB::table('monitoring_project_user')->where('monitoring_project_id', $projectId)->value('user_id');
Auth::login(App\User::findOrFail($userId));

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$regionId = DB::table('monitoring_searchengines')->where('monitoring_project_id', $projectId)->orderBy('id')->value('id');

$payload = [
    'draw' => 1,
    'start' => 0,
    'length' => $length,
    'region_id' => $regionId,
    'dates_range' => $datesRange,
    'mode_range' => 'range',
    'search' => ['value' => ''],
    'columns' => [],
    'offset' => [],
];

DB::listen(function ($query) use (&$dbMs, &$dbCount) {
    $dbMs += $query->time;
    $dbCount++;
});

$dbMs = 0;
$dbCount = 0;

$t0 = microtime(true);
$req = Illuminate\Http\Request::create('/monitoring/' . $projectId . '/table', 'POST', $payload);
$resp = $kernel->handle($req);
$totalMs = round((microtime(true) - $t0) * 1000);
$body = json_decode($resp->getContent(), true);

echo json_encode([
    'total_ms' => $totalMs,
    'db_ms' => round($dbMs),
    'db_queries' => $dbCount,
    'rows' => count($body['data'] ?? []),
    'cols' => count($body['columns'] ?? []),
    'kb' => round(strlen($resp->getContent()) / 1024),
], JSON_UNESCAPED_UNICODE) . PHP_EOL;
