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
use Illuminate\Support\Facades\Log;

/**
 * Агрегация краула тиками: один job = несколько лёгких этапов или кусок тяжёлого.
 * Большие сайты (десятки тысяч URL) дожимаются цепочкой Continue без 300s timeout.
 *
 * Падение/timeout тика не должно убивать краул: kickStuckActive / failed() снова
 * ставят AggregateSiteAuditCrawlJob, пока pages уже скачаны.
 */
class AggregateSiteAuditCrawlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;

    public $timeout = 600;

    /** @var int */
    public $crawlId;

    public function __construct(int $crawlId)
    {
        $this->crawlId = $crawlId;
        $this->onQueue(config('site_audit.queue', 'site_audit'));
        $this->timeout = max(300, (int) config('site_audit.aggregate_job_timeout', 600));
    }

    public function handle(): void
    {
        $lockKey = 'site_audit_aggregate_' . $this->crawlId;
        $lockTtl = max(
            180,
            (int) config('site_audit.aggregate_tick_seconds', 150) + 180
        );
        if (! Cache::add($lockKey, 1, $lockTtl)) {
            self::dispatch($this->crawlId)->delay(now()->addSeconds(20));

            return;
        }

        try {
            $crawl = SiteAuditCrawl::query()->find($this->crawlId);
            if (! $crawl || $crawl->isFinished()) {
                return;
            }

            if ($crawl->status !== SiteAuditCrawl::STATUS_AGGREGATING) {
                $crawl->status = SiteAuditCrawl::STATUS_AGGREGATING;
                $crawl->error = null;
                $crawl->save();
            }

            $more = (new SiteAuditAggregator())->processTick($crawl, true);
            if ($more) {
                $pause = max(1, (int) config('site_audit.aggregate_tick_pause_seconds', 3));
                self::dispatch($this->crawlId)->delay(now()->addSeconds($pause));
            }
        } catch (\Throwable $e) {
            // Не переводим краул в failed: иначе слот мёртв до ручного reaggregate.
            // Retries / failed() / kickStuckActive дожмут тик.
            Log::error('SiteAudit aggregate tick failed', [
                'crawl_id' => $this->crawlId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            Cache::forget($lockKey);
        }
    }

    /**
     * После исчерпания tries / timeout — снова в очередь, не failed краул.
     */
    public function failed(\Throwable $exception = null): void
    {
        Cache::forget('site_audit_aggregate_' . $this->crawlId);

        $crawl = SiteAuditCrawl::query()->find($this->crawlId);
        if (! $crawl || $crawl->isFinished()) {
            return;
        }

        if ((int) $crawl->pages_fetched < 1) {
            $crawl->status = SiteAuditCrawl::STATUS_FAILED;
            $msg = $exception ? $exception->getMessage() : 'aggregate job failed';
            $crawl->error = 'Aggregate failed: ' . mb_substr($msg, 0, 500);
            $crawl->finished_at = now();
            $crawl->save();
            \App\Services\SiteAudit\SiteAuditGlobalCap::promoteWaiting();

            return;
        }

        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        $agg = is_array($progress['aggregate'] ?? null) ? $progress['aggregate'] : [];
        $retries = (int) ($agg['job_fail_retries'] ?? 0) + 1;
        $agg['job_fail_retries'] = $retries;
        $progress['aggregate'] = $agg;
        $crawl->progress_json = $progress;
        $crawl->status = SiteAuditCrawl::STATUS_AGGREGATING;
        $crawl->error = null;
        $crawl->finished_at = null;
        $crawl->updated_at = now();
        $crawl->save();

        $maxAuto = max(5, (int) config('site_audit.aggregate_job_fail_retries', 40));
        if ($retries > $maxAuto) {
            $crawl->status = SiteAuditCrawl::STATUS_FAILED;
            $msg = $exception ? $exception->getMessage() : 'aggregate job failed';
            $crawl->error = 'Aggregate failed after ' . $retries . ' retries: ' . mb_substr($msg, 0, 400);
            $crawl->finished_at = now();
            $crawl->save();
            \App\Services\SiteAudit\SiteAuditGlobalCap::promoteWaiting();
            Log::error('SiteAudit aggregate gave up after retries', [
                'crawl_id' => $this->crawlId,
                'retries' => $retries,
            ]);

            return;
        }

        self::dispatch($this->crawlId)->delay(now()->addSeconds(45));
        Log::warning('SiteAudit aggregate job exhausted — requeued', [
            'crawl_id' => $this->crawlId,
            'retries' => $retries,
            'error' => $exception ? $exception->getMessage() : null,
        ]);
    }
}
