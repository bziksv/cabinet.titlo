<?php

namespace App\Classes\Monitoring;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Агрегация позиций для графиков без загрузки сотен тысяч Eloquent-моделей в память.
 */
class MonitoringChartPositionSeries
{
    public const BUCKET_DAY = 'day';
    public const BUCKET_WEEK = 'week';
    public const BUCKET_MONTH = 'month';

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function parseDateRange(?string $dateRange): array
    {
        $end = Carbon::now()->endOfDay();
        $start = Carbon::now()->subMonth()->startOfDay();

        if ($dateRange === null || trim($dateRange) === '') {
            return [$start, $end];
        }

        $parts = explode(' - ', $dateRange);
        if (count($parts) !== 2) {
            return [$start, $end];
        }

        return [
            self::parseDate(trim($parts[0]))->startOfDay(),
            self::parseDate(trim($parts[1]))->endOfDay(),
        ];
    }

    public static function resolveBucket(?string $range, Carbon $start, Carbon $end): string
    {
        if ($range === 'month') {
            return self::BUCKET_MONTH;
        }
        if ($range === 'weeks') {
            return self::BUCKET_WEEK;
        }
        if ($range === 'days') {
            return self::BUCKET_DAY;
        }

        $days = $start->diffInDays($end) + 1;
        if ($days > 120) {
            return self::BUCKET_MONTH;
        }
        if ($days > 45) {
            return self::BUCKET_WEEK;
        }

        return self::BUCKET_DAY;
    }

    /**
     * Средняя позиция по дням/неделям/месяцам для одного региона.
     *
     * @return Collection<string, int> label d.m.Y => middle
     */
    public static function middleSeries(
        int $projectId,
        ?int $groupId,
        int $engineId,
        Carbon $start,
        Carbon $end,
        string $bucket,
        ?array $keywordIds = null
    ): Collection {
        $rows = self::aggregatedRows($projectId, $groupId, $keywordIds, [$engineId], $start, $end, $bucket, true);

        return self::rowsToMiddleSeries($rows, $bucket);
    }

    /**
     * Средняя позиция по регионам (все движки одним запросом).
     *
     * @param int[] $engineIds
     *
     * @return array<int, Collection<string, int>> engineId => [label => middle]
     */
    public static function middleSeriesByEngines(
        int $projectId,
        ?int $groupId,
        array $engineIds,
        Carbon $start,
        Carbon $end,
        string $bucket,
        ?array $keywordIds = null
    ): array {
        $engineIds = array_values(array_unique(array_map('intval', $engineIds)));
        if ($engineIds === []) {
            return [];
        }

        $rows = self::aggregatedRows($projectId, $groupId, $keywordIds, $engineIds, $start, $end, $bucket, true);
        $out = [];
        foreach ($rows->groupBy('monitoring_searchengine_id') as $engineId => $engineRows) {
            $out[(int) $engineId] = self::rowsToMiddleSeries($engineRows, $bucket);
        }

        return $out;
    }

    /**
     * % ключей в ТОП-N по регионам (одна линия на регион).
     *
     * @param int[] $engineIds
     *
     * @return array<int, Collection<string, float>> engineId => [label => percent]
     */
    public static function topPercentSeriesByEngines(
        int $projectId,
        ?int $groupId,
        array $engineIds,
        Carbon $start,
        Carbon $end,
        string $bucket,
        int $top,
        int $keywordCount,
        ?array $keywordIds = null
    ): array {
        $engineIds = array_values(array_unique(array_map('intval', $engineIds)));
        if ($engineIds === [] || $keywordCount <= 0) {
            return [];
        }

        $rows = self::aggregatedRows($projectId, $groupId, $keywordIds, $engineIds, $start, $end, $bucket, false);
        $out = [];

        foreach ($rows->groupBy('monitoring_searchengine_id') as $engineId => $engineRows) {
            $series = collect();
            foreach ($engineRows->groupBy(function ($row) use ($bucket) {
                return self::labelFromBucket($row->pos_bucket, $bucket);
            }) as $label => $items) {
                $positions = $items->unique('monitoring_keyword_id')->pluck('position')->map(function ($pos) {
                    return (int) $pos;
                });
                $inTop = $positions->filter(function ($pos) use ($top) {
                    return $pos <= $top;
                })->count();
                $series->put($label, min(100, round(($inTop / $keywordCount) * 100, 1)));
            }
            $out[(int) $engineId] = $series->sortBy(function ($_, $label) {
                return self::labelSortKey((string) $label);
            });
        }

        return $out;
    }

    /**
     * Последние позиции ключей по дням (для % в ТОП и распределения).
     *
     * @return Collection<string, Collection<int, int>>
     */
    public static function latestPositionsSeries(
        int $projectId,
        ?int $groupId,
        int $engineId,
        Carbon $start,
        Carbon $end,
        string $bucket,
        ?array $keywordIds = null
    ): Collection {
        $rows = self::aggregatedRows($projectId, $groupId, $keywordIds, [$engineId], $start, $end, $bucket, false);

        return $rows->groupBy(function ($row) use ($bucket) {
            return self::labelFromBucket($row->pos_bucket, $bucket);
        })->map(function ($items) {
            return $items->unique('monitoring_keyword_id')->pluck('position')->map(function ($pos) {
                return (int) $pos;
            })->values();
        })->sortBy(function ($_, $label) {
            return self::labelSortKey((string) $label);
        });
    }

    private static function parseDate(string $value): Carbon
    {
        foreach (['Y-m-d', 'd-m-Y', 'd.m.Y'] as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $value);
                if ($parsed instanceof Carbon) {
                    return $parsed;
                }
            } catch (\Throwable $e) {
            }
        }

        return Carbon::parse($value);
    }

    /**
     * @param int[] $engineIds
     */
    private static function aggregatedRows(
        int $projectId,
        ?int $groupId,
        ?array $keywordIds,
        array $engineIds,
        Carbon $start,
        Carbon $end,
        string $bucket,
        bool $aggregateMiddle
    ): Collection {
        $bucketExpr = self::bucketExpression($bucket);

        $keywords = DB::table('monitoring_keywords')
            ->where('monitoring_project_id', $projectId);
        if ($keywordIds !== null) {
            if ($keywordIds === []) {
                return collect();
            }
            $keywords->whereIn('id', $keywordIds);
        } elseif ($groupId !== null && $groupId > 0) {
            $keywords->where('monitoring_group_id', $groupId);
        }

        $latestSub = DB::table('monitoring_positions as mp')
            ->joinSub($keywords->select('id'), 'mk', 'mk.id', '=', 'mp.monitoring_keyword_id')
            ->whereIn('mp.monitoring_searchengine_id', $engineIds)
            ->whereBetween('mp.created_at', [$start, $end])
            ->selectRaw(
                'mp.monitoring_keyword_id, mp.monitoring_searchengine_id, '
                . $bucketExpr . ' as pos_bucket, MAX(mp.id) as latest_id'
            )
            ->groupBy('mp.monitoring_keyword_id', 'mp.monitoring_searchengine_id', DB::raw($bucketExpr));

        $query = DB::table('monitoring_positions as p')
            ->joinSub($latestSub, 'latest', function ($join) {
                $join->on('p.id', '=', 'latest.latest_id');
            });

        if ($aggregateMiddle) {
            return $query
                ->selectRaw('p.monitoring_searchengine_id, latest.pos_bucket, ROUND(AVG(p.position)) as middle')
                ->groupBy('p.monitoring_searchengine_id', 'latest.pos_bucket')
                ->orderBy('latest.pos_bucket')
                ->get();
        }

        return $query
            ->selectRaw('p.monitoring_keyword_id, p.monitoring_searchengine_id, latest.pos_bucket, p.position')
            ->orderBy('latest.pos_bucket')
            ->get();
    }

    private static function bucketExpression(string $bucket): string
    {
        if ($bucket === self::BUCKET_MONTH) {
            return 'DATE_FORMAT(mp.created_at, "%Y-%m")';
        }
        if ($bucket === self::BUCKET_WEEK) {
            return 'DATE(DATE_SUB(mp.created_at, INTERVAL WEEKDAY(mp.created_at) DAY))';
        }

        return 'DATE(mp.created_at)';
    }

    private static function rowsToMiddleSeries(Collection $rows, string $bucket): Collection
    {
        $series = collect();
        foreach ($rows as $row) {
            $label = self::labelFromBucket($row->pos_bucket, $bucket);
            $series->put($label, (int) $row->middle);
        }

        return $series->sortBy(function ($_, $label) {
            return self::labelSortKey((string) $label);
        });
    }

    private static function labelFromBucket($bucket, string $bucketType): string
    {
        if ($bucketType === self::BUCKET_MONTH) {
            return Carbon::createFromFormat('Y-m', (string) $bucket)->startOfMonth()->format('d.m.Y');
        }

        return Carbon::parse((string) $bucket)->format('d.m.Y');
    }

    private static function labelSortKey(string $label): int
    {
        try {
            return Carbon::createFromFormat('d.m.Y', $label)->getTimestamp();
        } catch (\Throwable $e) {
            return (int) strtotime(str_replace('.', '-', $label));
        }
    }
}
