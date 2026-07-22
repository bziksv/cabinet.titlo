<?php

namespace App\Services\SiteAudit;

use App\SiteAuditCrawl;
use App\SiteAuditFinding;
use App\SiteAuditPage;

/**
 * Анализ доступности по данным краула: корень + доля ошибок в выборке.
 * Без повторных HTTP и без DomainMonitoring.
 */
class SiteAuditAvailabilityProbe
{
    public function run(SiteAuditCrawl $crawl): void
    {
        SiteAuditFinding::query()
            ->where('crawl_id', $crawl->id)
            ->where('code', 'site_availability')
            ->delete();

        $pages = SiteAuditPage::query()
            ->where('crawl_id', $crawl->id)
            ->get(['url', 'url_hash', 'status_code']);

        if ($pages->isEmpty()) {
            return;
        }

        $root = $this->pickRoot($pages);
        $total = $pages->count();
        $unreachable = 0;
        $http4xx = 0;
        $http5xx = 0;
        $ok = 0;

        foreach ($pages as $page) {
            $code = $page->status_code;
            if ($code === null) {
                $unreachable++;
                continue;
            }
            $c = (int) $code;
            if ($c >= 200 && $c < 400) {
                $ok++;
            } elseif ($c >= 500) {
                $http5xx++;
            } elseif ($c >= 400) {
                $http4xx++;
            } else {
                $unreachable++;
            }
        }

        $failCount = $unreachable + $http5xx + $http4xx;
        $failRate = $total > 0 ? round($failCount / $total, 4) : 0.0;
        $failRatePct = round($failRate * 100, 1);
        $threshold = (float) config('site_audit.availability_fail_rate', 0.10);

        $rootStatus = $root ? ($root->status_code !== null ? (int) $root->status_code : null) : null;
        $rootBad = $root && (
            $root->status_code === null
            || $rootStatus === null
            || $rootStatus >= 400
        );

        $summary = [
            'pages_total' => $total,
            'ok' => $ok,
            'unreachable' => $unreachable,
            'http_4xx' => $http4xx,
            'http_5xx' => $http5xx,
            'fail_count' => $failCount,
            'fail_rate' => $failRate,
            'fail_rate_pct' => $failRatePct,
            'threshold' => $threshold,
            'root_url' => $root ? $root->url : null,
            'root_status' => $rootStatus,
            'root_bad' => (bool) $rootBad,
        ];

        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        $progress['availability'] = $summary;
        $crawl->progress_json = $progress;
        $crawl->save();

        $warn = $rootBad || $failRate >= $threshold;
        if (! $warn) {
            return;
        }

        $url = $root ? $root->url : (string) $pages->first()->url;
        $cfg = config('site_audit.findings.site_availability', []);
        SiteAuditFinding::query()->create([
            'crawl_id' => $crawl->id,
            'code' => 'site_availability',
            'severity' => $cfg['severity'] ?? 'warning',
            'url' => $url,
            'url_hash' => SiteAuditUrlNormalizer::hash($url),
            'meta_json' => $summary,
        ]);
    }

    /**
     * @param \Illuminate\Support\Collection|iterable $pages
     */
    private function pickRoot($pages): ?SiteAuditPage
    {
        $best = null;
        $bestLen = PHP_INT_MAX;
        foreach ($pages as $page) {
            $path = (string) (parse_url($page->url, PHP_URL_PATH) ?: '/');
            if ($path === '/' || $path === '') {
                return $page;
            }
            $len = strlen($path);
            if ($len < $bestLen) {
                $bestLen = $len;
                $best = $page;
            }
        }

        return $best;
    }
}
