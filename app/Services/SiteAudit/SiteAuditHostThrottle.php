<?php

namespace App\Services\SiteAudit;

use Illuminate\Support\Facades\Cache;

class SiteAuditHostThrottle
{
    /**
     * Ограничение частоты запросов к хосту (сек между запросами = 1/rps).
     */
    public static function wait(string $host, float $rps): void
    {
        $rps = max(0.1, min(20.0, $rps));
        $interval = 1.0 / $rps;
        $key = 'site_audit_rps:' . md5(strtolower($host));

        for ($i = 0; $i < 80; $i++) {
            $now = microtime(true);
            $last = (float) Cache::get($key, 0.0);
            $due = $last + $interval;
            if ($now >= $due) {
                Cache::put($key, $now, 120);
                return;
            }
            $sleep = min(0.25, max(0.02, $due - $now));
            usleep((int) round($sleep * 1_000_000));
        }
    }
}
