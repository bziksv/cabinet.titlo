<?php
/**
 * Проверка POST /api/demo/klasterizator-klyuchevykh-slov/run + poll
 *
 * cd cabinet.datagon.ru && php scripts/verify-cluster-demo.php
 */

$base = getenv('CABINET_BASE') ?: 'http://127.0.0.1:3002';

$phrases = implode("\n", [
    'дерматоскоп kawe',
    'дерматоскоп kawe piccolight d',
    'дерматоскоп eurolight d30',
]);

$runBody = json_encode([
    'phrases' => $phrases,
    'region_id' => '213',
    'clustering_level' => 'soft',
], JSON_UNESCAPED_UNICODE);

$ch = curl_init(rtrim($base, '/') . '/api/demo/klasterizator-klyuchevykh-slov/run');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $runBody,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
]);
$runRaw = curl_exec($ch);
$runCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "RUN HTTP $runCode\n";
$run = json_decode((string) $runRaw, true);
if (!is_array($run) || empty($run['progress_id'])) {
    echo $runRaw . "\n";
    exit(1);
}

$progressId = $run['progress_id'];
echo "progress_id=$progressId\n";

for ($i = 0; $i < 60; $i++) {
    sleep(3);
    $pollBody = json_encode(['progress_id' => $progressId], JSON_UNESCAPED_UNICODE);
    $ch = curl_init(rtrim($base, '/') . '/api/demo/klasterizator-klyuchevykh-slov/poll');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $pollBody,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $pollRaw = curl_exec($ch);
    $pollCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $poll = json_decode((string) $pollRaw, true);
  if (!is_array($poll)) {
        echo "POLL invalid JSON\n";
        exit(1);
    }

    $status = $poll['status'] ?? '?';
    $done = $poll['progress']['phrases_done'] ?? '?';
    $total = $poll['progress']['phrases_total'] ?? '?';
    echo "POLL $pollCode status=$status $done/$total\n";

    if ($status === 'complete' && !empty($poll['result']['groups'])) {
        echo "OK clusters=" . count($poll['result']['groups']) . "\n";
        exit(0);
    }
}

echo "TIMEOUT\n";
exit(1);
