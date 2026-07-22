<?php

namespace App\Jobs\SiteAudit;

use App\Services\SiteAudit\SiteAuditPageProcessor;
use App\Services\SiteAudit\SiteAuditUrlNormalizer;
use App\Services\SiteAudit\SiteAuditUserAgentSession;
use App\SiteAuditCrawl;
use App\SiteAuditProject;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FetchParsePageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;

    public $timeout = 60;

    /** @var int */
    public $crawlId;

    /** @var string */
    public $url;

    public function __construct(int $crawlId, string $url)
    {
        $this->crawlId = $crawlId;
        $this->url = $url;
        $this->onQueue(config('site_audit.queue', 'site_audit'));
    }

    public function handle(): void
    {
        $crawl = SiteAuditCrawl::query()->find($this->crawlId);
        if (! $crawl || $crawl->isFinished()) {
            return;
        }

        $project = SiteAuditProject::query()->find($crawl->project_id);
        if (! $project) {
            return;
        }
        $host = SiteAuditUrlNormalizer::hostOf('https://' . $project->domain) ?: $project->domain;
        $settings = array_merge(
            $project->settings_json ?? [],
            is_array($crawl->progress_json['settings'] ?? null) ? $crawl->progress_json['settings'] : []
        );

        (new SiteAuditPageProcessor())->process($this->crawlId, $this->url, $host, $settings);

        SiteAuditCrawl::query()->where('id', $this->crawlId)->increment('pages_fetched');
        $crawl->refresh();
        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        $progress['fetched'] = (int) $crawl->pages_fetched;
        $progress['total'] = (int) $crawl->pages_total;
        // не затираем settings (скорость / UA)
        $crawl->progress_json = $progress;
        if ($crawl->status !== SiteAuditCrawl::STATUS_FETCHING) {
            $crawl->status = SiteAuditCrawl::STATUS_FETCHING;
        }
        $crawl->save();

        if ((int) $crawl->pages_fetched >= (int) $crawl->pages_total && (int) $crawl->pages_total > 0) {
            AggregateSiteAuditCrawlJob::dispatch($crawl->id);
            SiteAuditUserAgentSession::clear($this->crawlId);
        }
    }
}
