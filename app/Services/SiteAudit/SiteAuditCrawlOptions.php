<?php

namespace App\Services\SiteAudit;

class SiteAuditCrawlOptions
{
    public static function normalize(array $input): array
    {
        $presets = config('site_audit.speed_presets', []);
        $speed = (string) ($input['crawl_speed'] ?? 'normal');
        if (! isset($presets[$speed])) {
            $speed = 'normal';
        }

        $rps = isset($input['rps']) ? (float) $input['rps'] : (float) ($presets[$speed] ?? 1.0);
        $rps = max(0.1, min(20.0, $rps));

        return array_merge($input, [
            'crawl_speed' => $speed,
            'rps' => $rps,
            'save_html' => $input['save_html'] ?? 'off',
            'exclude_patterns' => SiteAuditUrlFilter::parsePatterns($input['exclude_patterns'] ?? []),
            // URL-нормализация всегда включена (не опция UI).
            'unify_www' => true,
            'force_https' => true,
            'strip_trailing_slash' => true,
            // Битые ссылки всегда проверяем (не опция UI).
            'check_broken_links' => true,
        ]);
    }
}
