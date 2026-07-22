<?php

namespace App\Services\SiteAudit;

use App\Services\IndexCheckService;
use App\SiteAuditCrawl;
use App\SiteAuditFinding;
use App\SiteAuditProject;
use Illuminate\Support\Facades\Log;

/**
 * Сравнение site:{host} found (Яндекс/Google) с pages_total краула.
 * По умолчанию выкл. (SITE_AUDIT_SERP_INDEX) — без XML на local.
 */
class SiteAuditSerpIndexProbe
{
    public function run(SiteAuditCrawl $crawl): void
    {
        SiteAuditFinding::query()
            ->where('crawl_id', $crawl->id)
            ->where('code', 'index_count_mismatch')
            ->delete();

        $enabled = (bool) config('site_audit.serp_index_enabled', false);
        $pagesTotal = (int) ($crawl->pages_total ?: 0);
        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];

        if (! $enabled) {
            $progress['serp_index'] = [
                'skipped' => true,
                'reason' => 'disabled',
                'pages_total' => $pagesTotal,
            ];
            $crawl->progress_json = $progress;
            $crawl->save();

            return;
        }

        if ($pagesTotal < 1) {
            $progress['serp_index'] = [
                'skipped' => true,
                'reason' => 'no_pages',
                'pages_total' => $pagesTotal,
            ];
            $crawl->progress_json = $progress;
            $crawl->save();

            return;
        }

        $project = SiteAuditProject::query()->find($crawl->project_id);
        $domain = $project ? (string) $project->domain : '';
        if ($domain === '') {
            $progress['serp_index'] = [
                'skipped' => true,
                'reason' => 'no_domain',
                'pages_total' => $pagesTotal,
            ];
            $crawl->progress_json = $progress;
            $crawl->save();

            return;
        }

        $engines = config('site_audit.serp_index_engines', ['yandex', 'google']);
        if (! is_array($engines) || $engines === []) {
            $engines = ['yandex', 'google'];
        }

        $ratioLow = (float) config('site_audit.serp_index_ratio_low', 0.5);
        $ratioHigh = (float) config('site_audit.serp_index_ratio_high', 3.0);
        $rootUrl = 'https://' . preg_replace('#^https?://#i', '', rtrim($domain, '/')) . '/';

        try {
            $counts = IndexCheckService::siteIndexCount($domain, $engines);
        } catch (\Throwable $e) {
            Log::warning('SiteAudit serp index probe failed: ' . $e->getMessage(), [
                'crawl_id' => $crawl->id,
            ]);
            $progress['serp_index'] = [
                'skipped' => true,
                'reason' => 'exception',
                'error' => $e->getMessage(),
                'pages_total' => $pagesTotal,
            ];
            $crawl->progress_json = $progress;
            $crawl->save();

            return;
        }

        $byEngine = [];
        foreach ($counts as $engine => $row) {
            $found = isset($row['found']) && $row['found'] !== null ? (int) $row['found'] : null;
            $ratio = ($found !== null && $pagesTotal > 0)
                ? round($found / $pagesTotal, 4)
                : null;
            $mismatch = $found !== null
                && $found > 0
                && $ratio !== null
                && ($ratio < $ratioLow || $ratio > $ratioHigh);

            $byEngine[$engine] = [
                'found' => $found,
                'query' => $row['query'] ?? null,
                'error' => $row['error'] ?? null,
                'ratio' => $ratio,
                'mismatch' => $mismatch,
            ];

            if ($mismatch) {
                $cfg = config('site_audit.findings.index_count_mismatch', []);
                SiteAuditFinding::query()->create([
                    'crawl_id' => $crawl->id,
                    'code' => 'index_count_mismatch',
                    'severity' => $cfg['severity'] ?? 'warning',
                    'url' => $rootUrl,
                    'url_hash' => SiteAuditUrlNormalizer::hash($rootUrl),
                    'meta_json' => [
                        'engine' => $engine,
                        'indexed' => $found,
                        'pages_total' => $pagesTotal,
                        'ratio' => $ratio,
                        'ratio_low' => $ratioLow,
                        'ratio_high' => $ratioHigh,
                        'query' => $row['query'] ?? null,
                    ],
                ]);
            }
        }

        $progress['serp_index'] = [
            'skipped' => false,
            'pages_total' => $pagesTotal,
            'ratio_low' => $ratioLow,
            'ratio_high' => $ratioHigh,
            'engines' => $byEngine,
        ];
        $crawl->progress_json = $progress;
        $crawl->save();
    }
}
