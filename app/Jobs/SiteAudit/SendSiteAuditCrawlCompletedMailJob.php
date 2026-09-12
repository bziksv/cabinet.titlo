<?php

namespace App\Jobs\SiteAudit;

use App\Notifications\SiteAuditCrawlCompletedNotification;
use App\SiteAuditCrawl;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Письмо «аудит готов» в default-очереди с отложенными ретраями при SMTP 550/timeout.
 */
class SendSiteAuditCrawlCompletedMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 10;

    public $timeout = 90;

    /** @var int */
    public $crawlId;

    /** @var array<int, int> секунды до следующей попытки */
    private const BACKOFF = [60, 120, 300, 600, 900, 1800, 3600, 7200, 10800];

    public function __construct(int $crawlId)
    {
        $this->crawlId = $crawlId;
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $crawl = SiteAuditCrawl::query()->find($this->crawlId);
        if (! $crawl || $crawl->status !== SiteAuditCrawl::STATUS_DONE) {
            return;
        }

        $user = User::query()->find($crawl->user_id);
        if (! $user || ! $user->email) {
            return;
        }

        try {
            // sync внутри job — ретраи контролируем сами через release()
            $user->notifyNow(new SiteAuditCrawlCompletedNotification($crawl));
        } catch (\Throwable $e) {
            $attempt = max(1, (int) $this->attempts());
            $delay = self::BACKOFF[min($attempt - 1, count(self::BACKOFF) - 1)];

            Log::warning('SiteAudit email notify failed, will retry', [
                'crawl_id' => $this->crawlId,
                'user_id' => $user->id,
                'attempt' => $attempt,
                'retry_in' => $delay,
                'error' => $e->getMessage(),
            ]);

            if ($attempt < $this->tries) {
                $this->release($delay);

                return;
            }

            throw $e;
        }
    }
}
