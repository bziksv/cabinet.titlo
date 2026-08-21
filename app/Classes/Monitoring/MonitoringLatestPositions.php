<?php

namespace App\Classes\Monitoring;

use App\MonitoringProject;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Последние позиции по парам (ключ × регион) — MAX(id), без N коррелированных подзапросов.
 */
class MonitoringLatestPositions
{
    /**
     * @param list<int> $engineIds
     * @param list<int> $keywordIds
     * @return Collection<int, object{monitoring_keyword_id: int, monitoring_searchengine_id: int, position: mixed, created_at?: mixed}>
     */
    public static function rows(array $engineIds, array $keywordIds): Collection
    {
        if ($engineIds === [] || $keywordIds === []) {
            return collect();
        }

        // JOIN к MAX(id) вместо WHERE id IN (subquery) — тот же выигрыш, что у loadPositions.
        $latestIdsQuery = DB::table('monitoring_positions')
            ->selectRaw('MAX(id) as id')
            ->whereIn('monitoring_searchengine_id', $engineIds)
            ->whereIn('monitoring_keyword_id', $keywordIds)
            ->groupBy('monitoring_keyword_id', 'monitoring_searchengine_id');

        return DB::table('monitoring_positions as p')
            ->select([
                'p.monitoring_keyword_id',
                'p.monitoring_searchengine_id',
                'p.position',
                'p.created_at',
            ])
            ->joinSub($latestIdsQuery, 'latest', static function ($join) {
                $join->on('p.id', '=', 'latest.id');
            })
            ->get();
    }

    /**
     * Только значения position (для PositionsPercentCalculate).
     *
     * @param list<int> $engineIds
     * @param list<int>|null $keywordIds null = все ключи проекта
     */
    public static function valuesForProject(MonitoringProject $project, array $engineIds = null, array $keywordIds = null): Collection
    {
        if ($engineIds === null) {
            $engineIds = $project->searchengines()->pluck('id')->all();
        }

        if ($keywordIds === null) {
            $keywordIds = $project->keywords()->pluck('id')->all();
        }

        return self::rows($engineIds, $keywordIds)
            ->pluck('position')
            ->filter(static function ($pos) {
                return $pos !== null && $pos !== '';
            })
            ->values();
    }

    /**
     * Коллекция для Mastered / ProjectData (query_id, engine_id, position).
     *
     * @param list<int> $engineIds
     * @param list<int> $keywordIds
     */
    public static function collectForProjectData(array $engineIds, array $keywordIds): Collection
    {
        return self::rows($engineIds, $keywordIds)
            ->map(static function ($row) {
                // Массив, не Collection: Mastered читает query_id через ArrayAccess / is_array.
                return [
                    'query_id' => (int) $row->monitoring_keyword_id,
                    'engine_id' => (int) $row->monitoring_searchengine_id,
                    'position' => $row->position,
                ];
            })
            ->filter(static function ($value) {
                return $value['position'] !== null;
            })
            ->values();
    }

    /**
     * MAX(created_at) позиций по проектам — для устаревания снимка списка.
     *
     * @param list<int> $projectIds
     * @return Collection<int, Carbon|string> project_id => latest created_at
     */
    public static function maxCreatedAtByProjectIds(array $projectIds): Collection
    {
        $projectIds = array_values(array_unique(array_filter(array_map('intval', $projectIds))));
        if ($projectIds === []) {
            return collect();
        }

        sort($projectIds);
        $cacheKey = 'mon.max_created_at.' . md5(implode(',', $projectIds));

        /** @var array<int, string> $cached */
        $cached = \Illuminate\Support\Facades\Cache::remember($cacheKey, 120, static function () use ($projectIds) {
            // Локальная копия monitoring_positions ~13M: JOIN/MAX по десяткам проектов
            // кладёт php-fpm на минуты. Для invalidation снимка хватает updated_at регионов.
            if (app()->environment('local')) {
                return DB::table('monitoring_searchengines')
                    ->whereIn('monitoring_project_id', $projectIds)
                    ->groupBy('monitoring_project_id')
                    ->selectRaw('monitoring_project_id as project_id, MAX(updated_at) as latest_at')
                    ->pluck('latest_at', 'project_id')
                    ->map(static function ($v) {
                        return $v !== null ? (string) $v : null;
                    })
                    ->filter()
                    ->all();
            }

            $engines = DB::table('monitoring_searchengines')
                ->whereIn('monitoring_project_id', $projectIds)
                ->get(['id', 'monitoring_project_id']);

            if ($engines->isEmpty()) {
                return [];
            }

            $engineIds = $engines->pluck('id')->all();
            $engineToProject = $engines->pluck('monitoring_project_id', 'id');

            $latestByEngine = DB::table('monitoring_positions')
                ->whereIn('monitoring_searchengine_id', $engineIds)
                ->groupBy('monitoring_searchengine_id')
                ->selectRaw('monitoring_searchengine_id, MAX(created_at) as latest_at')
                ->pluck('latest_at', 'monitoring_searchengine_id');

            $out = [];
            foreach ($latestByEngine as $engineId => $latestAt) {
                if ($latestAt === null || $latestAt === '') {
                    continue;
                }
                $pid = (int) $engineToProject->get($engineId);
                if ($pid < 1) {
                    continue;
                }
                if (!isset($out[$pid]) || (string) $latestAt > (string) $out[$pid]) {
                    $out[$pid] = (string) $latestAt;
                }
            }

            return $out;
        });

        return collect($cached);
    }
}
