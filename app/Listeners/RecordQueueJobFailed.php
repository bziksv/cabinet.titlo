<?php

namespace App\Listeners;

use App\Services\Queue\QueueDailyStatsService;
use Illuminate\Queue\Events\JobFailed;

class RecordQueueJobFailed
{
    public function handle(JobFailed $event): void
    {
        $queue = $this->queueName($event);

        if ($queue !== '') {
            app(QueueDailyStatsService::class)->incrementFailed($queue);
        }
    }

    private function queueName(JobFailed $event): string
    {
        try {
            return (string) $event->job->getQueue();
        } catch (\Throwable $e) {
            return '';
        }
    }
}
