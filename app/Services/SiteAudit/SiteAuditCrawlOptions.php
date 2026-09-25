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

        $maxConcurrency = max(1, (int) config('site_audit.max_concurrency', 8));
        $concurrency = isset($input['concurrency']) ? (int) $input['concurrency'] : 1;
        $concurrency = max(1, min($maxConcurrency, $concurrency));

        $htmlChecker = SiteAuditHtmlChecker::normalize(
            $input['html_checker'] ?? SiteAuditHtmlChecker::defaultChecker()
        );

        return array_merge($input, [
            'crawl_speed' => $speed,
            'rps' => $rps,
            'concurrency' => $concurrency,
            'save_html' => $input['save_html'] ?? 'off',
            'exclude_patterns' => SiteAuditUrlFilter::parsePatterns($input['exclude_patterns'] ?? []),
            'virtual_robots' => self::normalizeVirtualRobots($input['virtual_robots'] ?? ''),
            'html_checker' => $htmlChecker,
            // URL-нормализация всегда включена (не опция UI).
            'unify_www' => true,
            'force_https' => true,
            'strip_trailing_slash' => false,
            // Битые ссылки всегда проверяем (не опция UI).
            'check_broken_links' => true,
            // Только seed-URL: без sitemap и без дообхода по ссылкам.
            'pages_only' => ! empty($input['pages_only']),
        ]);
    }

    private static function normalizeVirtualRobots($raw): string
    {
        $text = trim((string) $raw);
        if ($text === '') {
            return '';
        }
        $max = (int) config('site_audit.robots_max_bytes', 512000);

        return strlen($text) > $max ? substr($text, 0, $max) : $text;
    }
}
