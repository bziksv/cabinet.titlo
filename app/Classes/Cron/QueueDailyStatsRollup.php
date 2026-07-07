<?php

namespace App\Classes\Cron;

use App\Services\Queue\QueueDailyStatsService;
use Carbon\Carbon;

class QueueDailyStatsRollup
{
    public function __invoke(): void
    {
        $stats = app(QueueDailyStatsService::class);
        $stats->rollupDate(Carbon::yesterday());
        $stats->purgeOldSamples();
        $stats->purgeOldHourly();
        $stats->purgeOldDaily();
    }
}
