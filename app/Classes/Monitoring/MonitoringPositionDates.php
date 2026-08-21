<?php

namespace App\Classes\Monitoring;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Дни съёма позиций по региону (лёгкая таблица monitoring_position_dates).
 */
class MonitoringPositionDates
{
    /**
     * @param list<int> $engineIds
     * @return list<string> Y-m-d
     */
    public static function datesForEngines(array $engineIds, string $start, string $end): array
    {
        $engineIds = array_values(array_unique(array_filter(array_map('intval', $engineIds))));
        if ($engineIds === []) {
            return [];
        }

        if (!Schema::hasTable('monitoring_position_dates')) {
            return self::discoverFromPositions($engineIds, $start, $end, false);
        }

        $startDate = Carbon::parse($start)->toDateString();
        $endDate = Carbon::parse($end)->toDateString();

        foreach ($engineIds as $engineId) {
            self::ensureEngineCoverage((int) $engineId, $startDate, $endDate);
        }

        return DB::table('monitoring_position_dates')
            ->whereIn('monitoring_searchengine_id', $engineIds)
            ->where('check_date', '>=', $startDate)
            ->where('check_date', '<=', $endDate)
            ->distinct()
            ->orderByDesc('check_date')
            ->pluck('check_date')
            ->map(static function ($d) {
                return Carbon::parse($d)->toDateString();
            })
            ->values()
            ->all();
    }

    /**
     * Прогрев / догрев лёгкой таблицы для региона в диапазоне дат.
     * Span покрытия (в т.ч. пустые края) храним в cache, иначе пустой left/right
     * edge будет DISTINCT'иться на каждый запрос.
     *
     * Важно: нельзя считать диапазон «прогретым», если в таблице есть хоть одна дата —
     * иначе остаются дыры (пример: 32 дня из 365).
     */
    private static function ensureEngineCoverage(int $engineId, string $startDate, string $endDate): void
    {
        $span = self::coverageSpan($engineId);

        // Cache miss после flush/рестарта: НЕ сканировать 13M positions —
        // сначала восстановить span из уже заполненной monitoring_position_dates.
        if ($span === null) {
            $span = self::coverageSpanFromTable($engineId);
            if ($span !== null) {
                self::rememberCoverageSpan($engineId, $span['min'], $span['max']);
            }
        }

        if ($span === null) {
            self::discoverEngineRangeLocked($engineId, $startDate, $endDate);
            self::rememberCoverageSpan($engineId, $startDate, $endDate);
            return;
        }

        $minKnown = $span['min'];
        $maxKnown = $span['max'];

        if ($startDate < $minKnown) {
            $edgeEnd = Carbon::parse($minKnown)->subDay()->toDateString();
            self::discoverEngineRangeLocked($engineId, $startDate, $edgeEnd);
            $minKnown = $startDate;
        }

        if ($endDate > $maxKnown) {
            $edgeStart = Carbon::parse($maxKnown)->addDay()->toDateString();
            self::discoverEngineRangeLocked($engineId, $edgeStart, $endDate);
            $maxKnown = $endDate;
        }

        if ($minKnown !== $span['min'] || $maxKnown !== $span['max']) {
            self::rememberCoverageSpan($engineId, $minKnown, $maxKnown);
        }
    }

    /**
     * @return array{min: string, max: string}|null
     */
    private static function coverageSpanFromTable(int $engineId): ?array
    {
        $row = DB::table('monitoring_position_dates')
            ->where('monitoring_searchengine_id', $engineId)
            ->selectRaw('MIN(check_date) as min_d, MAX(check_date) as max_d')
            ->first();

        if (!$row || empty($row->min_d) || empty($row->max_d)) {
            return null;
        }

        return [
            'min' => Carbon::parse($row->min_d)->toDateString(),
            'max' => Carbon::parse($row->max_d)->toDateString(),
        ];
    }

    private static function discoverEngineRangeLocked(int $engineId, string $startDate, string $endDate): void
    {
        $lockKey = 'mon.pos_dates.discover.' . $engineId . '.' . $startDate . '.' . $endDate;
        if (!Cache::add($lockKey, 1, 90)) {
            // Другой запрос уже сканирует — не ждём секундами в HTTP /table.
            return;
        }

        try {
            self::discoverFromPositions(
                [$engineId],
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59',
                true
            );
        } finally {
            Cache::forget($lockKey);
        }
    }

    /**
     * @return array{min: string, max: string}|null
     */
    private static function coverageSpan(int $engineId): ?array
    {
        $cached = Cache::get(self::coverageCacheKey($engineId));
        if (is_array($cached) && !empty($cached['min']) && !empty($cached['max'])) {
            return [
                'min' => Carbon::parse($cached['min'])->toDateString(),
                'max' => Carbon::parse($cached['max'])->toDateString(),
            ];
        }

        return null;
    }

    private static function rememberCoverageSpan(int $engineId, string $minDate, string $maxDate): void
    {
        Cache::put(
            self::coverageCacheKey($engineId),
            ['min' => $minDate, 'max' => $maxDate],
            86400 * 30
        );
    }

    private static function coverageCacheKey(int $engineId): string
    {
        // v2: сброс битых span после бага alreadyWarm (дыры внутри диапазона).
        return 'mon.pos_dates.cov.v2.' . $engineId;
    }

    /**
     * @param list<int> $engineIds
     * @return list<string> Y-m-d
     */
    public static function discoverFromPositions(
        array $engineIds,
        string $start,
        string $end,
        bool $persist = false
    ): array {
        $engineIds = array_values(array_unique(array_filter(array_map('intval', $engineIds))));
        if ($engineIds === []) {
            return [];
        }

        $from = 'monitoring_positions';
        try {
            $hasIdx = DB::table('information_schema.statistics')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', 'monitoring_positions')
                ->where('index_name', 'mon_pos_engine_created_idx')
                ->exists();
            if ($hasIdx) {
                $from = 'monitoring_positions FORCE INDEX (mon_pos_engine_created_idx)';
            }
        } catch (\Throwable $e) {
        }

        $rows = DB::table(DB::raw($from))
            ->whereIn('monitoring_searchengine_id', $engineIds)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<=', $end)
            ->selectRaw('monitoring_searchengine_id, DATE(created_at) as d')
            ->groupBy('monitoring_searchengine_id', DB::raw('DATE(created_at)'))
            ->get();

        $byEngine = [];
        $allDates = [];
        foreach ($rows as $row) {
            if ($row->d === null || $row->d === '') {
                continue;
            }
            $date = Carbon::parse($row->d)->toDateString();
            $engineId = (int) $row->monitoring_searchengine_id;
            $byEngine[$engineId][] = $date;
            $allDates[$date] = true;
        }

        if ($persist && $byEngine !== [] && Schema::hasTable('monitoring_position_dates')) {
            foreach ($byEngine as $engineId => $dates) {
                self::rememberMany([(int) $engineId], $dates);
            }
        }

        $dates = array_keys($allDates);
        rsort($dates);

        return $dates;
    }

    /**
     * @param list<int> $engineIds
     * @param list<string> $dates Y-m-d
     */
    public static function rememberMany(array $engineIds, array $dates): void
    {
        if (!Schema::hasTable('monitoring_position_dates')) {
            return;
        }

        $engineIds = array_values(array_unique(array_filter(array_map('intval', $engineIds))));
        $dates = array_values(array_unique(array_filter(array_map(static function ($d) {
            try {
                return Carbon::parse($d)->toDateString();
            } catch (\Throwable $e) {
                return null;
            }
        }, $dates))));

        if ($engineIds === [] || $dates === []) {
            return;
        }

        $rows = [];
        foreach ($engineIds as $engineId) {
            foreach ($dates as $date) {
                $rows[] = [
                    'monitoring_searchengine_id' => $engineId,
                    'check_date' => $date,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('monitoring_position_dates')->insertOrIgnore($chunk);
        }
    }

    public static function remember(int $engineId, $date): void
    {
        if ($engineId < 1 || !Schema::hasTable('monitoring_position_dates')) {
            return;
        }

        try {
            $checkDate = Carbon::parse($date)->toDateString();
        } catch (\Throwable $e) {
            return;
        }

        DB::table('monitoring_position_dates')->insertOrIgnore([
            'monitoring_searchengine_id' => $engineId,
            'check_date' => $checkDate,
        ]);
    }
}
