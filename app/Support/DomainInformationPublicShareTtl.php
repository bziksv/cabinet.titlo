<?php

namespace App\Support;

use Carbon\Carbon;

class DomainInformationPublicShareTtl
{
    public const UNLIMITED = 0;

    /**
     * @return int[]
     */
    public static function allowedDays(): array
    {
        $options = config('cabinet-domain-information.public_share_ttl_days', [30, 90, 180, 365, 0]);

        if (!is_array($options)) {
            return [30, 90, 180, 365, self::UNLIMITED];
        }

        $normalized = array_values(array_unique(array_map('intval', $options)));
        sort($normalized);

        return $normalized !== [] ? $normalized : [30, 90, 180, 365, self::UNLIMITED];
    }

    public static function normalize($value): int
    {
        $ttl = (int) $value;

        return in_array($ttl, self::allowedDays(), true) ? $ttl : 30;
    }

    public static function isUnlimited(int $ttlDays): bool
    {
        return $ttlDays === self::UNLIMITED;
    }

    public static function resolveExpiresAt(int $ttlDays): ?Carbon
    {
        if (self::isUnlimited($ttlDays)) {
            return null;
        }

        return Carbon::now()->addDays($ttlDays);
    }

    /**
     * @return array<int, string>
     */
    public static function labelsForUi(): array
    {
        $labels = [];
        foreach (self::allowedDays() as $days) {
            if (self::isUnlimited($days)) {
                $labels[$days] = (string) __('Domain information share ttl unlimited');

                continue;
            }
            $labels[$days] = (string) __('Domain information share ttl days', ['days' => $days]);
        }

        return $labels;
    }
}
