<?php

namespace App\Services\SiteAudit;

use Illuminate\Support\Facades\Cache;

/**
 * Sticky UA на краул: один агент, пока нет «плохих» ответов; после — смена.
 */
class SiteAuditUserAgentSession
{
    /** HTTP-коды, после которых меняем UA */
    private const ROTATE_STATUSES = [401, 403, 407, 429, 503];

    public static function current(int $crawlId, bool $rotateEnabled): string
    {
        if (! $rotateEnabled) {
            return (string) config('site_audit.user_agent', 'TitloSiteAuditBot/1.0');
        }

        $key = self::key($crawlId);
        $ua = Cache::get($key);
        if (is_string($ua) && $ua !== '') {
            return $ua;
        }

        $ua = self::randomUa();
        Cache::put($key, $ua, now()->addHours(12));

        return $ua;
    }

    /**
     * Нужно ли сменить UA после ответа / ошибки.
     */
    public static function shouldRotate(?int $statusCode, bool $transportError): bool
    {
        if ($transportError) {
            return true;
        }
        if ($statusCode === null) {
            return false;
        }

        return in_array((int) $statusCode, self::ROTATE_STATUSES, true);
    }

    public static function rotate(int $crawlId, ?string $avoidUa = null): string
    {
        $pool = config('site_audit.user_agents', []);
        if (! is_array($pool) || ! $pool) {
            $ua = (string) config('site_audit.user_agent', 'Mozilla/5.0');
            Cache::put(self::key($crawlId), $ua, now()->addHours(12));

            return $ua;
        }

        $candidates = array_values($pool);
        if ($avoidUa) {
            $filtered = array_values(array_filter($candidates, function ($u) use ($avoidUa) {
                return $u !== $avoidUa;
            }));
            if ($filtered) {
                $candidates = $filtered;
            }
        }

        $ua = $candidates[array_rand($candidates)];
        Cache::put(self::key($crawlId), $ua, now()->addHours(12));

        return $ua;
    }

    public static function clear(int $crawlId): void
    {
        Cache::forget(self::key($crawlId));
    }

    private static function key(int $crawlId): string
    {
        return 'site_audit_ua:' . $crawlId;
    }

    private static function randomUa(): string
    {
        $pool = config('site_audit.user_agents', []);
        if (! is_array($pool) || ! $pool) {
            return (string) config('site_audit.user_agent', 'Mozilla/5.0');
        }

        return $pool[array_rand($pool)];
    }
}
