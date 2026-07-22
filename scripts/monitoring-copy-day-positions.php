<?php

/**
 * Скопировать позиции daily-ПС с одной даты на другую (заполнение пропуска).
 *
 * Usage:
 *   php scripts/monitoring-copy-day-positions.php sv6@list.ru 2026-07-12 2026-07-21 [--dry-run] [--execute]
 *
 * Только ПС с ежедневным расписанием: auto_update=1, time задан, без weekdays/monthday/day.
 * Существующие строки целевого дня для этих ПС не трогаем (идемпотентно через NOT EXISTS).
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\MonitoringProject;
use App\MonitoringSearchengine;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

$email = $argv[1] ?? '';
$fromDate = $argv[2] ?? '';
$toDate = $argv[3] ?? '';
$dryRun = in_array('--dry-run', $argv, true) || ! in_array('--execute', $argv, true);

if ($email === '' || $fromDate === '' || $toDate === '') {
    fwrite(STDERR, "Usage: php scripts/monitoring-copy-day-positions.php email from-date to-date [--dry-run|--execute]\n");
    exit(1);
}

try {
    $from = Carbon::parse($fromDate)->startOfDay();
    $to = Carbon::parse($toDate)->startOfDay();
} catch (Throwable $e) {
    fwrite(STDERR, "Bad date: {$e->getMessage()}\n");
    exit(1);
}

$user = User::query()->where('email', $email)->first();
if (! $user) {
    fwrite(STDERR, "User not found: {$email}\n");
    exit(1);
}

$projectIds = MonitoringProject::query()
    ->where('creator', $user->id)
    ->pluck('id')
    ->merge(
        DB::table('monitoring_project_user')
            ->where('user_id', $user->id)
            ->where('approved', 1)
            ->pluck('monitoring_project_id')
    )
    ->unique()
    ->values();

$engines = MonitoringSearchengine::query()
    ->whereIn('monitoring_project_id', $projectIds)
    ->where('auto_update', 1)
    ->whereNotNull('time')
    ->orderBy('monitoring_project_id')
    ->orderBy('id')
    ->get()
    ->filter(static function (MonitoringSearchengine $engine) {
        $weekdays = $engine->weekdays;
        if (is_array($weekdays) && $weekdays !== []) {
            return false;
        }
        if ($engine->monthday) {
            return false;
        }
        if ($engine->day) {
            return false;
        }

        return true;
    })
    ->values();

echo "User: {$email} (id={$user->id})\n";
echo "Copy {$from->toDateString()} → {$to->toDateString()}\n";
echo "Daily engines: {$engines->count()}\n";
echo $dryRun ? "Mode: DRY-RUN (pass --execute to write)\n\n" : "Mode: EXECUTE\n\n";

if ($engines->isEmpty()) {
    echo "Nothing to do.\n";
    exit(0);
}

$engineIds = $engines->pluck('id')->all();
$fromStart = $from->copy()->startOfDay()->toDateTimeString();
$fromEnd = $from->copy()->endOfDay()->toDateTimeString();
$toStart = $to->copy()->startOfDay()->toDateTimeString();
$toEnd = $to->copy()->endOfDay()->toDateTimeString();

$sourceCount = (int) DB::table('monitoring_positions')
    ->whereIn('monitoring_searchengine_id', $engineIds)
    ->whereBetween('created_at', [$fromStart, $fromEnd])
    ->count();

$targetCount = (int) DB::table('monitoring_positions')
    ->whereIn('monitoring_searchengine_id', $engineIds)
    ->whereBetween('created_at', [$toStart, $toEnd])
    ->count();

$wouldInsert = (int) DB::table('monitoring_positions as p')
    ->join('monitoring_keywords as k', 'k.id', '=', 'p.monitoring_keyword_id')
    ->whereIn('p.monitoring_searchengine_id', $engineIds)
    ->whereBetween('p.created_at', [$fromStart, $fromEnd])
    ->whereNotExists(static function ($q) use ($toStart, $toEnd) {
        $q->select(DB::raw(1))
            ->from('monitoring_positions as x')
            ->whereColumn('x.monitoring_searchengine_id', 'p.monitoring_searchengine_id')
            ->whereColumn('x.monitoring_keyword_id', 'p.monitoring_keyword_id')
            ->whereBetween('x.created_at', [$toStart, $toEnd]);
    })
    ->count();

echo "Source rows ({$from->toDateString()}): {$sourceCount}\n";
echo "Target rows ({$to->toDateString()}): {$targetCount}\n";
echo "Would insert: {$wouldInsert}\n\n";

foreach ($engines as $engine) {
    $cFrom = (int) DB::table('monitoring_positions')
        ->where('monitoring_searchengine_id', $engine->id)
        ->whereBetween('created_at', [$fromStart, $fromEnd])
        ->count();
    $cTo = (int) DB::table('monitoring_positions')
        ->where('monitoring_searchengine_id', $engine->id)
        ->whereBetween('created_at', [$toStart, $toEnd])
        ->count();
    $projectName = optional($engine->project)->name ?? ('#' . $engine->monitoring_project_id);
    echo sprintf(
        "  se=%d project=%s %s/%s  %s=%d  %s=%d\n",
        $engine->id,
        $projectName,
        $engine->engine,
        $engine->lr,
        $from->toDateString(),
        $cFrom,
        $to->toDateString(),
        $cTo
    );
}

if ($dryRun || $wouldInsert === 0) {
    echo "\nDone (no writes).\n";
    exit(0);
}

$toDateStr = $to->toDateString();

$inserted = DB::affectingStatement(
    'INSERT INTO monitoring_positions
        (monitoring_keyword_id, monitoring_searchengine_id, position, url, target, created_at, updated_at)
     SELECT
        p.monitoring_keyword_id,
        p.monitoring_searchengine_id,
        p.position,
        p.url,
        p.target,
        CONCAT(?, \' \', TIME(p.created_at)),
        CONCAT(?, \' \', TIME(COALESCE(p.updated_at, p.created_at)))
     FROM monitoring_positions p
     INNER JOIN monitoring_keywords k ON k.id = p.monitoring_keyword_id
     WHERE p.monitoring_searchengine_id IN (' . implode(',', array_map('intval', $engineIds)) . ')
       AND p.created_at BETWEEN ? AND ?
       AND NOT EXISTS (
            SELECT 1 FROM monitoring_positions x
            WHERE x.monitoring_searchengine_id = p.monitoring_searchengine_id
              AND x.monitoring_keyword_id = p.monitoring_keyword_id
              AND x.created_at BETWEEN ? AND ?
       )',
    [$toDateStr, $toDateStr, $fromStart, $fromEnd, $toStart, $toEnd]
);

echo "\nInserted: {$inserted}\n";

$after = (int) DB::table('monitoring_positions')
    ->whereIn('monitoring_searchengine_id', $engineIds)
    ->whereBetween('created_at', [$toStart, $toEnd])
    ->count();
echo "Target rows after: {$after}\n";

$projectIdList = $engines->pluck('monitoring_project_id')->unique()->values()->all();
echo 'Refresh snapshots for ' . count($projectIdList) . " projects\n";
foreach ($projectIdList as $projectId) {
    try {
        \Artisan::call('monitoring:refresh-snapshots', ['--project' => (string) $projectId]);
        echo trim(\Artisan::output()) . "\n";
    } catch (Throwable $e) {
        echo "Snapshot #{$projectId} failed: {$e->getMessage()}\n";
    }
}

echo "Done.\n";
