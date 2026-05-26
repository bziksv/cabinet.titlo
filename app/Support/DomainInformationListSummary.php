<?php

namespace App\Support;

use App\DomainInformation;
use Illuminate\Support\Collection;

class DomainInformationListSummary
{
    /** @var int */
    public $total;

    /** @var int */
    public $ok;

    /** @var int */
    public $withIssues;

    /** @var int */
    public $expiringSoon;

    /** @var int */
    public $dnsMonitoring;

    /**
     * @param  Collection<int, DomainInformation>|iterable<DomainInformation>  $projects
     */
    public static function fromProjects($projects, int $expiringWithinDays = 30): self
    {
        $summary = new self();
        $summary->total = 0;
        $summary->ok = 0;
        $summary->withIssues = 0;
        $summary->expiringSoon = 0;
        $summary->dnsMonitoring = 0;

        foreach ($projects as $project) {
            $summary->total++;

            if ($project->broken) {
                $summary->withIssues++;
            } else {
                $summary->ok++;
            }

            if ($project->check_dns || $project->check_dns_email) {
                $summary->dnsMonitoring++;
            }

            $daysLeft = DomainInformationDisplay::daysUntilExpiry($project);
            if ($daysLeft !== null && $daysLeft >= 0 && $daysLeft <= $expiringWithinDays && !$project->broken) {
                $summary->expiringSoon++;
            }
        }

        return $summary;
    }
}
