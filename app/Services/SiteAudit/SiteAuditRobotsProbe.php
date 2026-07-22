<?php

namespace App\Services\SiteAudit;

use App\SiteAuditCrawl;
use App\SiteAuditFinding;

/**
 * Прогон robots.txt в начале краула: findings + rules в progress_json.
 */
class SiteAuditRobotsProbe
{
    public function run(SiteAuditCrawl $crawl, string $domain): void
    {
        $robots = new SiteAuditRobotsTxt();
        $analysis = $robots->analyze($domain);

        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        $progress['robots'] = [
            'url' => $analysis['url'],
            'status_code' => $analysis['status_code'],
            'closed' => $analysis['closed'],
            'sitemaps' => $analysis['sitemaps'],
            'groups' => $analysis['groups'],
            'error' => $analysis['error'],
        ];
        $crawl->progress_json = $progress;
        $crawl->save();

        // crawl-level findings (привязаны к URL robots.txt)
        SiteAuditFinding::query()
            ->where('crawl_id', $crawl->id)
            ->whereIn('code', ['robots_txt_error', 'robots_txt_closed'])
            ->delete();

        $url = $analysis['url'];
        $hash = SiteAuditUrlNormalizer::hash($url);

        foreach ($analysis['findings'] as $f) {
            $cfg = config('site_audit.findings.' . $f['code'], []);
            SiteAuditFinding::query()->create([
                'crawl_id' => $crawl->id,
                'code' => $f['code'],
                'severity' => $cfg['severity'] ?? 'warning',
                'url' => $url,
                'url_hash' => $hash,
                'meta_json' => $f['meta'] ?? null,
            ]);
        }
    }
}
