<?php

namespace App\Jobs\SiteAudit;

use App\Services\SiteAudit\SiteAuditCrawlEngine;
use App\SiteAuditCrawl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Старт краула: полный прогон в CrawlEngine (sitemap + дообход по ссылкам до лимита).
 */
class DiscoverSiteAuditUrlsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $timeout = 3600;

    /** @var int */
    public $crawlId;

    public function __construct(int $crawlId)
    {
        $this->crawlId = $crawlId;
        $this->onQueue(config('site_audit.queue', 'site_audit'));
    }

    public function handle(): void
    {
        $crawl = SiteAuditCrawl::query()->find($this->crawlId);
        if (! $crawl || $crawl->isFinished()) {
            return;
        }

        (new SiteAuditCrawlEngine())->run($crawl);
    }
}
