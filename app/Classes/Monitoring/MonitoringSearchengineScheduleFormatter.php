<?php

namespace App\Classes\Monitoring;

use App\MonitoringSearchengine;

/**
 * Человекочитаемое расписание съёма позиций для списка v2.
 */
class MonitoringSearchengineScheduleFormatter
{
    private const WEEKDAY_SHORT = [
        '0' => 'Вс',
        '1' => 'Пн',
        '2' => 'Вт',
        '3' => 'Ср',
        '4' => 'Чт',
        '5' => 'Пт',
        '6' => 'Сб',
    ];

    /**
     * @return array{manual: bool, mode: string, label: string, short: string}
     */
    public function describe(MonitoringSearchengine $engine): array
    {
        if (!$engine->auto_update) {
            return $this->manual();
        }

        if ($engine->monthday) {
            $n = (int) $engine->monthday;
            $time = $this->formatTime($engine->time);

            return [
                'manual' => false,
                'mode' => 'ranges',
                'label' => __('Monitoring v2 schedule ranges', ['days' => $n, 'time' => $time]),
                'short' => __('Monitoring v2 schedule ranges short', ['days' => $n, 'time' => $time]),
            ];
        }

        $weekdays = $engine->weekdays;
        if (is_array($weekdays) && $weekdays !== []) {
            $days = $this->formatWeekdays($weekdays);
            $time = $this->formatTime($engine->time);

            return [
                'manual' => false,
                'mode' => 'weeks',
                'label' => __('Monitoring v2 schedule weeks', ['days' => $days, 'time' => $time]),
                'short' => __('Monitoring v2 schedule weeks short', ['days' => $days, 'time' => $time]),
            ];
        }

        if ($engine->day) {
            $day = (int) $engine->day;
            $time = $this->formatTime($engine->time);

            return [
                'manual' => false,
                'mode' => 'months',
                'label' => __('Monitoring v2 schedule months', ['day' => $day, 'time' => $time]),
                'short' => __('Monitoring v2 schedule months short', ['day' => $day, 'time' => $time]),
            ];
        }

        if ($engine->time) {
            $time = $this->formatTime($engine->time);

            return [
                'manual' => false,
                'mode' => 'times',
                'label' => __('Monitoring v2 schedule daily', ['time' => $time]),
                'short' => __('Monitoring v2 schedule daily short', ['time' => $time]),
            ];
        }

        return $this->manualIncomplete();
    }

    /**
     * @return array{manual: bool, mode: string, label: string, short: string}
     */
    private function manual(): array
    {
        return [
            'manual' => true,
            'mode' => 'manual',
            'label' => __('Monitoring v2 schedule manual'),
            'short' => __('Monitoring v2 schedule manual short'),
        ];
    }

    /**
     * @return array{manual: bool, mode: string, label: string, short: string}
     */
    private function manualIncomplete(): array
    {
        return [
            'manual' => true,
            'mode' => 'manual',
            'label' => __('Monitoring v2 schedule manual incomplete'),
            'short' => __('Monitoring v2 schedule manual short'),
        ];
    }

    /**
     * @param array<int|string>|null $weekdays
     */
    private function formatWeekdays($weekdays): string
    {
        $parts = [];
        foreach ((array) $weekdays as $d) {
            $key = (string) $d;
            $parts[] = self::WEEKDAY_SHORT[$key] ?? $key;
        }

        return implode(', ', $parts);
    }

    private function formatTime(?string $time): string
    {
        $time = trim((string) $time);

        return $time !== '' ? $time : '—';
    }
}
