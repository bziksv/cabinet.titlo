<?php

namespace App\Classes\Monitoring;

use Illuminate\Support\Facades\Cache;

/**
 * Кэш ответа /monitoring/{id}/table + версия для инвалидации после съёма позиций.
 */
class MonitoringTableResponseCache
{
    public const TTL_SECONDS = 90;

    public static function versionKey(int $projectId): string
    {
        return 'mon.table.ver.' . $projectId;
    }

    public static function version(int $projectId): int
    {
        return (int) Cache::get(self::versionKey($projectId), 0);
    }

    public static function bump(int $projectId): void
    {
        if ($projectId < 1) {
            return;
        }

        $key = self::versionKey($projectId);
        // add+increment без гонки «прочитал 0, записал 1» на параллельном съёме.
        if (!Cache::has($key)) {
            Cache::put($key, 1, 86400);

            return;
        }

        try {
            Cache::increment($key);
        } catch (\Throwable $e) {
            Cache::put($key, self::version($projectId) + 1, 86400);
        }
    }

    public static function bumpForEngine(int $engineId): void
    {
        static $engineToProject = [];
        static $bumpedProjects = [];

        if ($engineId < 1) {
            return;
        }

        if (!isset($engineToProject[$engineId])) {
            $engineToProject[$engineId] = (int) \Illuminate\Support\Facades\DB::table('monitoring_searchengines')
                ->where('id', $engineId)
                ->value('monitoring_project_id');
        }

        $projectId = $engineToProject[$engineId];
        if ($projectId < 1 || isset($bumpedProjects[$projectId])) {
            return;
        }

        $bumpedProjects[$projectId] = true;
        self::bump($projectId);
        MonitoringChildRowsService::forgetProjectCache($projectId);
    }
}
