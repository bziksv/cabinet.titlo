<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Порядок тарифов для отображения лимитов: Free → Optimal → Ultimate → Maximum (топ).
 */
class TariffTierOrder
{
    public const CODES = ['Free', 'Optimal', 'Ultimate', 'Maximum'];

    public static function sortKey(string $code): int
    {
        $pos = array_search($code, self::CODES, true);

        return $pos === false ? 999 : $pos;
    }

    /**
     * @param  \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection  $fields
     * @return \Illuminate\Support\Collection
     */
    public static function sortFields($fields): Collection
    {
        return $fields->sortBy(static function ($field) {
            return self::sortKey((string) $field->tariff);
        })->values();
    }

    /**
     * ID строк, где лимит ниже, чем у более дешёвого тарифа выше по иерархии.
     *
     * @param  \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection  $fields
     * @return array<int, string>
     */
    public static function invertedLimitWarnings($fields): array
    {
        $warnings = [];
        $prev = null;

        foreach (self::sortFields($fields) as $field) {
            if ($prev !== null && (int) $field->value < (int) $prev->value) {
                $warnings[$field->id] = __('Limit is lower than on :tariff (:prev). Usually each higher plan should be ≥ previous.', [
                    'tariff' => $prev->tariff,
                    'prev' => number_format($prev->value, 0, ',', ' '),
                ]);
            }
            $prev = $field;
        }

        return $warnings;
    }

    /** @return array<string, string> */
    public static function labelsMap(): array
    {
        $labels = [];
        foreach ((new \App\Classes\Tariffs\Facades\Tariffs())->getTariffs() as $tariff) {
            $labels[$tariff->code()] = $tariff->name();
        }
        $labels[(new \App\Classes\Tariffs\FreeTariff())->code()] = (new \App\Classes\Tariffs\FreeTariff())->name();

        return $labels;
    }
}
