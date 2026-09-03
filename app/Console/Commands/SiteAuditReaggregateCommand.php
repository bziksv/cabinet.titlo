<?php

namespace App\Console\Commands;

use App\Jobs\SiteAudit\AggregateSiteAuditCrawlJob;
use App\Services\SiteAudit\SiteAuditAggregator;
use App\SiteAuditCrawl;
use Illuminate\Console\Command;

class SiteAuditReaggregateCommand extends Command
{
    protected $signature = 'site-audit:reaggregate
        {crawl_id : ID краула}
        {--notify : Отправить email о завершении}
        {--queue : В очередь тиками (для больших краулов, не блокирует CLI)}
        {--resume : Продолжить с текущего aggregate stage (без сброса; для failed после зависания)}';

    protected $description = 'Пересчитать aggregate-findings по уже скачанным pages';

    public function handle(): int
    {
        $id = (int) $this->argument('crawl_id');
        $crawl = SiteAuditCrawl::query()->find($id);
        if (! $crawl) {
            $this->error('Crawl not found');

            return 1;
        }

        $notify = (bool) $this->option('notify');
        $resume = (bool) $this->option('resume');

        $crawl->status = SiteAuditCrawl::STATUS_AGGREGATING;
        $crawl->error = null;
        $crawl->finished_at = null;
        $crawl->save();

        if ($this->option('queue')) {
            if (! $resume) {
                (new SiteAuditAggregator())->resetAggregateState($crawl, $notify);
            } else {
                $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
                $agg = is_array($progress['aggregate'] ?? null) ? $progress['aggregate'] : [];
                $stage = (string) ($agg['stage'] ?? '');
                if ($stage === '' || $stage === 'done') {
                    (new SiteAuditAggregator())->resetAggregateState($crawl, $notify);
                    $this->warn('Resume: stage пустой/done — старт с начала агрегации');
                } else {
                    $agg['notify'] = $notify;
                    // Сброс meta текущего stage — тик начнёт этап заново, но без cleanup всех findings.
                    $agg['meta'] = [];
                    $progress['aggregate'] = $agg;
                    $crawl->progress_json = $progress;
                    $crawl->save();
                    $this->info("Resume from stage: {$stage}");
                }
            }
            AggregateSiteAuditCrawlJob::dispatch($crawl->id);
            $this->info("Queued staged aggregate for crawl #{$id}");

            return 0;
        }

        if ($resume) {
            $this->warn('--resume без --queue: полный sync aggregate() всё равно сбрасывает state');
        }
        (new SiteAuditAggregator())->aggregate($crawl, $notify);
        $crawl->refresh();

        $this->info('Status: ' . $crawl->statusLabelRu());
        $this->info('Buckets: ' . json_encode($crawl->buckets_json, JSON_UNESCAPED_UNICODE));
        $this->info('Counts: ' . json_encode($crawl->counts_json, JSON_UNESCAPED_UNICODE));

        return 0;
    }
}
