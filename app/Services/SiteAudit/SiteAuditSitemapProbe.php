<?php

namespace App\Services\SiteAudit;

use App\SiteAuditCrawl;
use App\SiteAuditFinding;

/**
 * Probe sitemap в начале краула: meta в progress_json + findings ошибок.
 */
class SiteAuditSitemapProbe
{
    public function run(SiteAuditCrawl $crawl, string $domain, int $seedLimit): array
    {
        $meta = (new SiteAuditSitemapDiscovery())->discoverWithMeta($domain, $seedLimit);

        $urlsGz = null;
        if ($meta['all_urls'] !== []) {
            $packed = @gzcompress(implode("\n", $meta['all_urls']), 6);
            if (is_string($packed) && $packed !== '') {
                $urlsGz = base64_encode($packed);
            }
        }

        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        $progress['sitemap'] = [
            'found' => (bool) $meta['found'],
            'url_count' => count($meta['all_urls']),
            'seed_count' => count($meta['seed_urls']),
            'sources' => array_slice($meta['sources'], 0, 40),
            'errors' => array_slice($meta['errors'], 0, 40),
            'urls_gz' => $urlsGz,
        ];
        $crawl->progress_json = $progress;
        $crawl->save();

        SiteAuditFinding::query()
            ->where('crawl_id', $crawl->id)
            ->whereIn('code', ['sitemap_error', 'sitemap_missing'])
            ->delete();

        if (! $meta['found']) {
            $home = 'https://' . preg_replace('#^https?://#i', '', rtrim($domain, '/')) . '/';
            $cfg = config('site_audit.findings.sitemap_missing', []);
            SiteAuditFinding::query()->create([
                'crawl_id' => $crawl->id,
                'code' => 'sitemap_missing',
                'severity' => $cfg['severity'] ?? 'warning',
                'url' => $home,
                'url_hash' => SiteAuditUrlNormalizer::hash($home),
                'meta_json' => [
                    'tried' => array_values(array_unique(array_map(function ($s) {
                        return $s['url'] ?? null;
                    }, $meta['sources']))),
                ],
            ]);
        }

        foreach ($meta['errors'] as $err) {
            // запасные кандидаты (/sitemap_index.xml и т.п.) часто 404 — не шумим, если карта уже найдена
            if ($meta['found']) {
                $reason = (string) ($err['reason'] ?? '');
                if ($reason === 'not_xml' || strpos($reason, 'http_') === 0 || $reason === 'empty') {
                    continue;
                }
            }
            $sm = (string) ($err['sitemap'] ?? '');
            if ($sm === '') {
                continue;
            }
            $cfg = config('site_audit.findings.sitemap_error', []);
            SiteAuditFinding::query()->create([
                'crawl_id' => $crawl->id,
                'code' => 'sitemap_error',
                'severity' => $cfg['severity'] ?? 'warning',
                'url' => $sm,
                'url_hash' => SiteAuditUrlNormalizer::hash($sm),
                'meta_json' => $err,
            ]);
        }

        return $meta;
    }

    /**
     * @return string[]
     */
    public static function urlsFromProgress(SiteAuditCrawl $crawl): array
    {
        $gz = $crawl->progress_json['sitemap']['urls_gz'] ?? null;
        if (! is_string($gz) || $gz === '') {
            return [];
        }
        $bin = base64_decode($gz, true);
        if ($bin === false) {
            return [];
        }
        $raw = @gzuncompress($bin);
        if (! is_string($raw) || $raw === '') {
            return [];
        }

        return array_values(array_filter(explode("\n", $raw), function ($u) {
            return $u !== '';
        }));
    }
}
