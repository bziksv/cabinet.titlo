<?php

namespace App\Listeners;

use App\Services\Queue\QueueDailyStatsService;
use Illuminate\Queue\Events\JobProcessed;

class RecordQueueJobProcessed
{
    public function handle(JobProcessed $event): void
    {
        $queue = $this->queueName($event);

        if ($queue !== '') {
            app(QueueDailyStatsService::class)->incrementProcessed($queue);
        }
    }

    private function queueName(JobProcessed $event): string
    {
        try {
            return (string) $event->job->getQueue();
        } catch (\Throwable $e) {
            return '';
        }
    }
}
