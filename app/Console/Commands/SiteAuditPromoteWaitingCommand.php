<?php

namespace App\Console\Commands;

use App\Services\SiteAudit\SiteAuditGlobalCap;
use Illuminate\Console\Command;

class SiteAuditPromoteWaitingCommand extends Command
{
    protected $signature = 'site-audit:promote-waiting';

    protected $description = 'Снимает stale active-краулы и запускает queued_wait при свободном слоте';

    public function handle(): int
    {
        $reclaimed = SiteAuditGlobalCap::reclaimStale();
        $n = SiteAuditGlobalCap::promoteWaiting();
        $this->info(sprintf(
            'reclaimed=%d promoted=%d active=%d max=%d',
            $reclaimed,
            $n,
            SiteAuditGlobalCap::countActive(),
            SiteAuditGlobalCap::maxActive()
        ));

        return 0;
    }
}
