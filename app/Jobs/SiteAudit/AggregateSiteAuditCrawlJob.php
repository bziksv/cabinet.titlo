<?php

namespace App\Jobs\SiteAudit;

use App\Services\SiteAudit\SiteAuditAggregator;
use App\SiteAuditCrawl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class AggregateSiteAuditCrawlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $timeout = 300;

    /** @var int */
    public $crawlId;

    public function __construct(int $crawlId)
    {
        $this->crawlId = $crawlId;
        $this->onQueue(config('site_audit.queue', 'site_audit'));
    }

    public function handle(): void
    {
        $lockKey = 'site_audit_aggregate_' . $this->crawlId;
        if (! Cache::add($lockKey, 1, 120)) {
            return;
        }

        try {
            $crawl = SiteAuditCrawl::query()->find($this->crawlId);
            if (! $crawl || $crawl->status === SiteAuditCrawl::STATUS_DONE) {
                return;
            }

            $crawl->status = SiteAuditCrawl::STATUS_AGGREGATING;
            $crawl->save();

            (new SiteAuditAggregator())->aggregate($crawl);
        } catch (\Throwable $e) {
            $crawl = SiteAuditCrawl::query()->find($this->crawlId);
            if ($crawl && ! $crawl->isFinished()) {
                $crawl->status = SiteAuditCrawl::STATUS_FAILED;
                $crawl->error = 'Aggregate failed: ' . mb_substr($e->getMessage(), 0, 500);
                $crawl->finished_at = now();
                $crawl->save();
            }
            throw $e;
        } finally {
            Cache::forget($lockKey);
        }
    }
}
