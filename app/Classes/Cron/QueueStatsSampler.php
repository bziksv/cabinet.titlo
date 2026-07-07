<?php

namespace App\Classes\Cron;

use App\Services\Queue\QueueDailyStatsService;

class QueueStatsSampler
{
    public function __invoke(): void
    {
        app(QueueDailyStatsService::class)->recordSample();
    }
}
