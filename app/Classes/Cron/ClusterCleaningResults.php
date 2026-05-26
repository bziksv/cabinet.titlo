<?php

namespace App\Classes\Cron;

use App\ClusterConfiguration;
use App\ClusterResults;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Удаление сохранённых результатов кластеризатора (cluster_results) старше N дней.
 * N — cluster_configuration.cleaning_interval (/cluster-configuration).
 * Запуск: Laravel scheduler daily() (полночь по app.timezone).
 * Ручная проверка: php artisan cluster:prune-results --dry-run
 */
class ClusterCleaningResults
{
    public function __invoke(): void
    {
        $result = static::run(false);
        if ($result['deleted'] > 0 || $result['candidates'] > 0 || $result['error'] !== null) {
            Log::info($result['message']);
        }
    }

    /**
     * @return array{days: int|null, candidates: int, deleted: int, cutoff: string|null, error: string|null, message: string}
     */
    public static function run(bool $dryRun = false): array
    {
        $config = ClusterConfiguration::query()->first(['cleaning_interval']);
        $days = $config ? (int) $config->cleaning_interval : 0;

        if ($days < 1) {
            return [
                'days' => $days ?: null,
                'candidates' => 0,
                'deleted' => 0,
                'cutoff' => null,
                'error' => 'invalid_cleaning_interval',
                'message' => 'ClusterCleaningResults: skipped — cluster_configuration.cleaning_interval is missing or < 1',
            ];
        }

        $cutoff = Carbon::now()->subDays($days);
        $query = ClusterResults::query()->where('created_at', '<', $cutoff);
        $candidates = (clone $query)->count();
        $deleted = $dryRun ? 0 : $query->delete();

        return [
            'days' => $days,
            'candidates' => $candidates,
            'deleted' => $deleted,
            'cutoff' => $cutoff->toDateTimeString(),
            'error' => null,
            'message' => $dryRun
                ? 'ClusterCleaningResults (dry-run): ' . $candidates . ' cluster_results candidate(s), older than ' . $days . ' days (cutoff ' . $cutoff->toDateTimeString() . ').'
                : 'ClusterCleaningResults: removed ' . $deleted . ' of ' . $candidates . ' cluster_results, older than ' . $days . ' days (cutoff ' . $cutoff->toDateTimeString() . ').',
        ];
    }

    /** @deprecated Используйте {@see run()} */
    public function cleaning(): void
    {
        static::run(false);
    }
}
