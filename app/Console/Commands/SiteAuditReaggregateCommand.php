<?php

namespace App\Console\Commands;

use App\Services\SiteAudit\SiteAuditAggregator;
use App\SiteAuditCrawl;
use Illuminate\Console\Command;

class SiteAuditReaggregateCommand extends Command
{
    protected $signature = 'site-audit:reaggregate
        {crawl_id : ID краула}
        {--notify : Отправить email о завершении}';

    protected $description = 'Пересчитать aggregate-findings по уже скачанным pages';

    public function handle(): int
    {
        $id = (int) $this->argument('crawl_id');
        $crawl = SiteAuditCrawl::query()->find($id);
        if (! $crawl) {
            $this->error('Crawl not found');

            return 1;
        }

        $crawl->status = SiteAuditCrawl::STATUS_AGGREGATING;
        $crawl->save();

        (new SiteAuditAggregator())->aggregate($crawl, (bool) $this->option('notify'));
        $crawl->refresh();

        $this->info('Status: ' . $crawl->statusLabelRu());
        $this->info('Buckets: ' . json_encode($crawl->buckets_json, JSON_UNESCAPED_UNICODE));
        $this->info('Counts: ' . json_encode($crawl->counts_json, JSON_UNESCAPED_UNICODE));

        return 0;
    }
}
