<?php

namespace App\Classes\Monitoring;

use App\MonitoringPosition;
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

        return MonitoringPosition::query()
            ->select([
                'monitoring_keyword_id',
                'monitoring_searchengine_id',
                'position',
                'created_at',
            ])
            ->whereIn('id', function ($query) use ($engineIds, $keywordIds) {
                $query->select(DB::raw('MAX(id)'))
                    ->from('monitoring_positions')
                    ->whereIn('monitoring_searchengine_id', $engineIds)
                    ->whereIn('monitoring_keyword_id', $keywordIds)
                    ->groupBy('monitoring_keyword_id', 'monitoring_searchengine_id');
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
        if ($projectIds === []) {
            return collect();
        }

        return DB::table('monitoring_positions as mp')
            ->join('monitoring_searchengines as se', 'se.id', '=', 'mp.monitoring_searchengine_id')
            ->whereIn('se.monitoring_project_id', $projectIds)
            ->groupBy('se.monitoring_project_id')
            ->selectRaw('se.monitoring_project_id as project_id, MAX(mp.created_at) as latest_at')
            ->pluck('latest_at', 'project_id');
    }
}
