<?php

namespace App\Services\SiteAudit;

use App\SiteAuditCrawl;

/**
 * Синхронный прогон краула (local / artisan --sync) без очереди.
 */
class SiteAuditSyncRunner
{
    public function run(SiteAuditCrawl $crawl): SiteAuditCrawl
    {
        return (new SiteAuditCrawlEngine())->run($crawl);
    }
}
