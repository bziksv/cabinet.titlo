<?php

namespace App\Services\SiteAudit;

use App\Common;
use App\MonitoringKeyword;
use App\SiteAuditCrawl;
use Illuminate\Support\Facades\Log;

/**
 * Посадочные URL из модуля мониторинга (keywords.page) для домена краула.
 */
class SiteAuditLandingResolver
{
    /**
     * @return array{
     *   urls: string[],
     *   project_ids: int[],
     *   raw_count: int,
     *   by_keyword: array<string, array{url: string, query: string, project_id: int}>
     * }
     */
    public function forCrawl(SiteAuditCrawl $crawl): array
    {
        $empty = ['urls' => [], 'project_ids' => [], 'raw_count' => 0, 'by_keyword' => []];

        $domain = optional($crawl->project)->domain;
        if (! $domain || ! $crawl->user_id) {
            return $empty;
        }

        $host = Common::domainFilter(
            parse_url('https://' . preg_replace('#^https?://#i', '', $domain), PHP_URL_HOST) ?: $domain
        );
        if ($host === '') {
            return $empty;
        }

        try {
            $rows = MonitoringKeyword::query()
                ->whereNotNull('page')
                ->where('page', '!=', '')
                ->whereHas('project', function ($q) use ($crawl, $host) {
                    $q->where(function ($uq) use ($host) {
                        $uq->where('url', $host)
                            ->orWhere('url', 'www.' . $host)
                            ->orWhere('url', 'like', '%' . $host . '%');
                    })->whereHas('users', function ($uq) use ($crawl) {
                        $uq->where('users.id', $crawl->user_id);
                    });
                })
                ->limit(5000)
                ->get(['id', 'page', 'monitoring_project_id', 'query']);
        } catch (\Throwable $e) {
            Log::warning('SiteAudit landing resolve failed: ' . $e->getMessage(), [
                'crawl_id' => $crawl->id,
            ]);

            return $empty;
        }

        $opts = SiteAuditUrlNormalizer::optionsFromSettings(
            is_array($crawl->progress_json['settings'] ?? null) ? $crawl->progress_json['settings'] : [],
            $domain
        );

        $urls = [];
        $projectIds = [];
        $byKeyword = [];
        foreach ($rows as $row) {
            $projectIds[(int) $row->monitoring_project_id] = true;
            $raw = trim((string) $row->page);
            if ($raw === '') {
                continue;
            }
            // относительные пути → абсолютные
            if (strpos($raw, 'http://') !== 0 && strpos($raw, 'https://') !== 0) {
                $raw = 'https://' . $host . '/' . ltrim($raw, '/');
            }
            $norm = SiteAuditUrlNormalizer::normalize($raw, $domain, $opts);
            if (! $norm) {
                continue;
            }
            $urls[$norm] = true;
            $kid = (string) (int) $row->id;
            $byKeyword[$kid] = [
                'url' => $norm,
                'query' => SiteAuditUtf8::scrubString((string) $row->query),
                'project_id' => (int) $row->monitoring_project_id,
            ];
        }

        return [
            'urls' => array_keys($urls),
            'project_ids' => array_map('intval', array_keys($projectIds)),
            'raw_count' => $rows->count(),
            'by_keyword' => $byKeyword,
        ];
    }
}
