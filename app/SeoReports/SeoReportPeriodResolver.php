<?php

namespace App\SeoReports;

use Carbon\Carbon;

/**
 * Resolve report + compare date ranges from template settings and/or request overrides.
 */
class SeoReportPeriodResolver
{
    public const PERIOD_PREV_MONTH = 'prev_month';
    public const PERIOD_THIS_MONTH = 'this_month';
    public const PERIOD_LAST_14 = 'last_14';
    public const PERIOD_LAST_30 = 'last_30';
    public const PERIOD_CALENDAR_MONTH = 'calendar_month';
    public const PERIOD_CUSTOM = 'custom';

    public const COMPARE_PREVIOUS_PERIOD = 'previous_period';
    public const COMPARE_PREVIOUS_MONTH = 'previous_calendar_month';
    public const COMPARE_SAME_MONTH_LAST_YEAR = 'same_month_last_year';
    public const COMPARE_CALENDAR_MONTH = 'calendar_month';
    public const COMPARE_CUSTOM = 'custom';

    /** @return list<string> */
    public static function periodPresets(): array
    {
        return [
            self::PERIOD_PREV_MONTH,
            self::PERIOD_THIS_MONTH,
            self::PERIOD_LAST_14,
            self::PERIOD_LAST_30,
            self::PERIOD_CALENDAR_MONTH,
            self::PERIOD_CUSTOM,
        ];
    }

    /** @return list<string> */
    public static function compareModes(): array
    {
        return [
            self::COMPARE_PREVIOUS_PERIOD,
            self::COMPARE_PREVIOUS_MONTH,
            self::COMPARE_SAME_MONTH_LAST_YEAR,
            self::COMPARE_CALENDAR_MONTH,
            self::COMPARE_CUSTOM,
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $override
     * @return array{0:Carbon,1:Carbon,2:?Carbon,3:?Carbon}
     */
    public static function resolve(array $settings = [], array $override = []): array
    {
        $rawPreset = $override['period_preset'] ?? null;
        if (($rawPreset === null || $rawPreset === '') && !empty($override['period_from']) && !empty($override['period_to'])) {
            $rawPreset = self::PERIOD_CUSTOM;
        } elseif (($rawPreset === null || $rawPreset === '') && !empty($override['period_month'])) {
            $rawPreset = self::PERIOD_CALENDAR_MONTH;
        }
        $periodPreset = (string) ($rawPreset ?: ($settings['default_period'] ?? self::PERIOD_PREV_MONTH));
        if (!in_array($periodPreset, self::periodPresets(), true)) {
            $periodPreset = self::PERIOD_PREV_MONTH;
        }

        [$from, $to] = self::resolveReportPeriod($periodPreset, $settings, $override);

        if (array_key_exists('auto_compare', $override)) {
            $autoCompare = !empty($override['auto_compare']);
        } elseif (array_key_exists('auto_compare', $settings)) {
            $autoCompare = !empty($settings['auto_compare']);
        } else {
            $autoCompare = true;
        }

        if (!$autoCompare) {
            return [$from, $to, null, null];
        }

        $compareMode = (string) ($override['compare_mode'] ?? $settings['compare_mode'] ?? self::COMPARE_PREVIOUS_PERIOD);
        if (!in_array($compareMode, self::compareModes(), true)) {
            $compareMode = self::COMPARE_PREVIOUS_PERIOD;
        }

        [$cFrom, $cTo] = self::resolveComparePeriod($from, $to, $compareMode, $settings, $override);

        return [$from, $to, $cFrom, $cTo];
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $override
     * @return array{0:Carbon,1:Carbon}
     */
    public static function resolveReportPeriod(string $preset, array $settings = [], array $override = []): array
    {
        if ($preset === self::PERIOD_THIS_MONTH) {
            $to = Carbon::today();
            $from = $to->copy()->startOfMonth();

            return [$from, $to];
        }

        if ($preset === self::PERIOD_LAST_14) {
            $to = Carbon::today();
            $from = $to->copy()->subDays(13);

            return [$from, $to];
        }

        if ($preset === self::PERIOD_LAST_30) {
            $to = Carbon::today();
            $from = $to->copy()->subDays(29);

            return [$from, $to];
        }

        if ($preset === self::PERIOD_CALENDAR_MONTH) {
            $month = self::parseYearMonth(
                $override['period_month'] ?? $settings['default_period_month'] ?? null
            );
            if ($month) {
                return [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()->startOfDay()];
            }
            // Fallback: previous calendar month
            $from = Carbon::today()->subMonthNoOverflow()->startOfMonth();

            return [$from, $from->copy()->endOfMonth()->startOfDay()];
        }

        if ($preset === self::PERIOD_CUSTOM) {
            $fromRaw = $override['period_from'] ?? $settings['default_period_from'] ?? null;
            $toRaw = $override['period_to'] ?? $settings['default_period_to'] ?? null;
            if ($fromRaw && $toRaw) {
                $from = Carbon::parse((string) $fromRaw)->startOfDay();
                $to = Carbon::parse((string) $toRaw)->startOfDay();
                if ($from->gt($to)) {
                    [$from, $to] = [$to, $from];
                }

                return [$from, $to];
            }
        }

        $from = Carbon::today()->subMonthNoOverflow()->startOfMonth();

        return [$from, $from->copy()->endOfMonth()->startOfDay()];
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, mixed> $override
     * @return array{0:?Carbon,1:?Carbon}
     */
    public static function resolveComparePeriod(
        Carbon $from,
        Carbon $to,
        string $mode,
        array $settings = [],
        array $override = []
    ): array {
        if ($mode === self::COMPARE_SAME_MONTH_LAST_YEAR) {
            return [
                $from->copy()->subYearNoOverflow()->startOfDay(),
                $to->copy()->subYearNoOverflow()->startOfDay(),
            ];
        }

        if ($mode === self::COMPARE_PREVIOUS_MONTH) {
            $month = $from->copy()->startOfMonth()->subMonthNoOverflow();

            return [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()->startOfDay()];
        }

        if ($mode === self::COMPARE_CALENDAR_MONTH) {
            $month = self::parseYearMonth(
                $override['compare_month'] ?? $settings['compare_month'] ?? null
            );
            if ($month) {
                return [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()->startOfDay()];
            }

            // Fallback to previous calendar month vs report
            $prev = $from->copy()->startOfMonth()->subMonthNoOverflow();

            return [$prev->copy()->startOfMonth(), $prev->copy()->endOfMonth()->startOfDay()];
        }

        if ($mode === self::COMPARE_CUSTOM) {
            $fromRaw = $override['compare_from'] ?? $settings['default_compare_from'] ?? null;
            $toRaw = $override['compare_to'] ?? $settings['default_compare_to'] ?? null;
            if ($fromRaw && $toRaw) {
                $cFrom = Carbon::parse((string) $fromRaw)->startOfDay();
                $cTo = Carbon::parse((string) $toRaw)->startOfDay();
                if ($cFrom->gt($cTo)) {
                    [$cFrom, $cTo] = [$cTo, $cFrom];
                }

                return [$cFrom, $cTo];
            }
        }

        // previous_period — equal length immediately before
        $days = $from->diffInDays($to) + 1;
        $cTo = $from->copy()->subDay();
        $cFrom = $cTo->copy()->subDays($days - 1);

        return [$cFrom, $cTo];
    }

    /**
     * Normalize period/compare fields from a request into settings_json keys.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function normalizeSettingsInput(array $input): array
    {
        $period = (string) ($input['default_period'] ?? self::PERIOD_PREV_MONTH);
        if (!in_array($period, self::periodPresets(), true)) {
            $period = self::PERIOD_PREV_MONTH;
        }

        $compareMode = (string) ($input['compare_mode'] ?? self::COMPARE_PREVIOUS_PERIOD);
        if (!in_array($compareMode, self::compareModes(), true)) {
            $compareMode = self::COMPARE_PREVIOUS_PERIOD;
        }

        $out = [
            'default_period' => $period,
            'default_period_month' => self::normalizeYearMonth($input['default_period_month'] ?? null),
            'default_period_from' => self::normalizeDate($input['default_period_from'] ?? null),
            'default_period_to' => self::normalizeDate($input['default_period_to'] ?? null),
            'auto_compare' => !empty($input['auto_compare']),
            'compare_mode' => $compareMode,
            'compare_month' => self::normalizeYearMonth($input['compare_month'] ?? null),
            'default_compare_from' => self::normalizeDate($input['default_compare_from'] ?? null),
            'default_compare_to' => self::normalizeDate($input['default_compare_to'] ?? null),
        ];

        if ($period !== self::PERIOD_CALENDAR_MONTH) {
            $out['default_period_month'] = $out['default_period_month'] ?: null;
        }
        if ($period !== self::PERIOD_CUSTOM) {
            // keep stored dates optional for later switch-back
        }

        return $out;
    }

    private static function parseYearMonth($value): ?Carbon
    {
        $raw = trim((string) $value);
        if ($raw === '' || !preg_match('/^\d{4}-\d{2}$/', $raw)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $raw . '-01')->startOfMonth();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function normalizeYearMonth($value): ?string
    {
        $month = self::parseYearMonth($value);

        return $month ? $month->format('Y-m') : null;
    }

    private static function normalizeDate($value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
