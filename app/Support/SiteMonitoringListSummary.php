<?php

namespace App\Support;

use App\DomainMonitoring;
use Illuminate\Support\Collection;

class SiteMonitoringListSummary
{
    /** @var int */
    public $total;

    /** @var int */
    public $available;

    /** @var int */
    public $withIssues;

    /** @var int */
    public $awaitingCheck;

    /** @var float|null */
    public $avgUptimePercent;

    /**
     * @param  Collection<int, DomainMonitoring>|iterable<DomainMonitoring>  $projects
     */
    public static function fromProjects($projects): self
    {
        $summary = new self();
        $summary->total = 0;
        $summary->available = 0;
        $summary->withIssues = 0;
        $summary->awaitingCheck = 0;
        $summary->avgUptimePercent = null;

        $uptimeSum = 0.0;
        $uptimeCount = 0;

        foreach ($projects as $project) {
            $summary->total++;

            if ($project->isPendingResetStatus()) {
                $summary->awaitingCheck++;
                continue;
            }

            if ($project->broken) {
                $summary->withIssues++;
                continue;
            }

            $summary->available++;

            if ($project->last_check !== null && $project->uptime_percent !== null) {
                $uptimeSum += (float) $project->uptime_percent;
                $uptimeCount++;
            }
        }

        if ($uptimeCount > 0) {
            $summary->avgUptimePercent = round($uptimeSum / $uptimeCount, 1);
        }

        return $summary;
    }

    public function formatAvgUptime(): string
    {
        return $this->avgUptimePercent !== null
            ? number_format($this->avgUptimePercent, 1, '.', '') . '%'
            : '—';
    }
}
