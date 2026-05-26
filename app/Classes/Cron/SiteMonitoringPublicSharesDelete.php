<?php

namespace App\Classes\Cron;

use App\SiteMonitoringPublicShare;
use Carbon\Carbon;

class SiteMonitoringPublicSharesDelete
{
    public function __invoke(): void
    {
        if (!SiteMonitoringPublicShare::tableAvailable()) {
            return;
        }

        SiteMonitoringPublicShare::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', Carbon::now())
            ->delete();
    }
}
