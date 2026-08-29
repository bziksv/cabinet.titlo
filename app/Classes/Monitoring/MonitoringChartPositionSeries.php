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
        $days = $start->diffInDays($end) + 1;

        // Явный выбор в UI важнее авто-режима.
        if ($range === 'month') {
            return self::BUCKET_MONTH;
        }
        if ($range === 'weeks') {
            return self::BUCKET_WEEK;
        }
        if ($range === 'days') {
            // Страховка: год «по дням» валит MySQL — укрупняем до недель.
            if ($days > 120) {
                return self::BUCKET_WEEK;
            }

            return self::BUCKET_DAY;
        }

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
        $engineIds = array_values(array_unique(array_map('intval', $engineIds)));
        if ($engineIds === []) {
            return collect();
        }
        if ($keywordIds !== null && $keywordIds === []) {
            return collect();
        }

        // Важно: гнать от engine+created_at (диапазон ~десятки тысяч строк),
        // а не от keywords→positions (на 10M+ таблице это десятки секунд).
        // FORCE INDEX — иначе optimizer часто выбирает mon_pos_engine_kw_created_idx.
        $fromMp = self::hasEngineCreatedIndex()
            ? 'monitoring_positions as mp FORCE INDEX (mon_pos_engine_created_idx)'
            : 'monitoring_positions as mp';

        // month/week на длинном диапазоне: не сканируем все дни года (~170k строк),
        // а берём только якорные дни съёма из monitoring_position_dates.
        $anchorDays = self::anchorCheckDates($engineIds, $start, $end, $bucket);

        $latestSub = DB::table(DB::raw($fromMp))
            ->join('monitoring_keywords as mk', function ($join) use ($projectId, $groupId, $keywordIds) {
                $join->on('mk.id', '=', 'mp.monitoring_keyword_id')
                    ->where('mk.monitoring_project_id', '=', $projectId);
                if ($keywordIds !== null) {
                    $join->whereIn('mk.id', $keywordIds);
                } elseif ($groupId !== null && $groupId > 0) {
                    $join->where('mk.monitoring_group_id', '=', $groupId);
                }
            })
            ->whereIn('mp.monitoring_searchengine_id', $engineIds);

        if ($anchorDays !== null) {
            if ($anchorDays === []) {
                return collect();
            }
            $latestSub->where(function ($outer) use ($anchorDays) {
                foreach ($anchorDays as $day) {
                    $outer->orWhereBetween('mp.created_at', [
                        $day . ' 00:00:00',
                        $day . ' 23:59:59',
                    ]);
                }
            });
        } else {
            $latestSub->whereBetween('mp.created_at', [$start, $end]);
        }

        $latestSub
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

    /**
     * Якорные дни для week/month: последний день съёма в каждом ведре.
     * null = сканировать весь диапазон (day-bucket или нет light-таблицы).
     *
     * @param int[] $engineIds
     * @return list<string>|null Y-m-d
     */
    private static function anchorCheckDates(array $engineIds, Carbon $start, Carbon $end, string $bucket): ?array
    {
        if ($bucket === self::BUCKET_DAY) {
            return null;
        }

        if (!\Illuminate\Support\Facades\Schema::hasTable('monitoring_position_dates')) {
            return null;
        }

        // Раньше график читал якоря без прогрева — на проде после короткого
        // диапазона в таблице оставались ~недели, а span в cache врал «год готов».
        MonitoringPositionDates::datesForEngines(
            $engineIds,
            $start->toDateString(),
            $end->toDateString()
        );

        $dateExpr = $bucket === self::BUCKET_MONTH
            ? 'DATE_FORMAT(check_date, "%Y-%m")'
            : 'DATE(DATE_SUB(check_date, INTERVAL WEEKDAY(check_date) DAY))';

        $rows = DB::table('monitoring_position_dates')
            ->whereIn('monitoring_searchengine_id', $engineIds)
            ->where('check_date', '>=', $start->toDateString())
            ->where('check_date', '<=', $end->toDateString())
            ->selectRaw($dateExpr . ' as bucket_key, MAX(check_date) as last_day')
            ->groupBy(DB::raw($dateExpr))
            ->pluck('last_day');

        if ($rows->isEmpty()) {
            // Таблица пуста для региона — полный scan по created_at.
            return null;
        }

        $anchors = $rows->map(static function ($d) {
            return Carbon::parse($d)->toDateString();
        })->unique()->values()->all();

        // Защита от дырявого прогрева: ~3 недели вместо ~52 при «365 + недели».
        if ($bucket === self::BUCKET_WEEK) {
            $expectedWeeks = max(1, (int) ceil(($start->diffInDays($end) + 1) / 7));
            if (count($anchors) < max(3, (int) floor($expectedWeeks * 0.35))) {
                return null;
            }
        } elseif ($bucket === self::BUCKET_MONTH) {
            $expectedMonths = max(1, $start->copy()->startOfMonth()->diffInMonths($end->copy()->startOfMonth()) + 1);
            if (count($anchors) < max(2, (int) floor($expectedMonths * 0.5))) {
                return null;
            }
        }

        return $anchors;
    }

    private static function hasEngineCreatedIndex(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        try {
            $dbName = DB::getDatabaseName();
            $cached = DB::table('information_schema.statistics')
                ->where('table_schema', $dbName)
                ->where('table_name', 'monitoring_positions')
                ->where('index_name', 'mon_pos_engine_created_idx')
                ->exists();
        } catch (\Throwable $e) {
            $cached = false;
        }

        return $cached;
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
