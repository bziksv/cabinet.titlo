<?php

namespace App\Support;

use App\DomainInformation;
use App\DomainInformationCheckLog;
use Carbon\Carbon;

class DomainInformationProjectStats
{
    /**
     * @return array<string, mixed>
     */
    public static function buildForExport(DomainInformation $project): array
    {
        $limit = (int) config('cabinet-domain-information.report_export_log_limit', 100);
        $limit = max(10, min(200, $limit));

        return self::build($project, 1, $limit);
    }

    /**
     * @return array<string, mixed>
     */
    public static function build(DomainInformation $project, int $page = 1, ?int $perPage = null): array
    {
        $perPage = $perPage ?? (int) config('cabinet-domain-information.stats_log_per_page', 25);
        $perPage = max(10, min(100, $perPage));
        $page = max(1, $page);

        $baseQuery = DomainInformationCheckLog::query()
            ->where('domain_information_id', $project->id);

        $totalLogs = (clone $baseQuery)->count();
        $failedTotal = (clone $baseQuery)->where('broken', true)->count();
        $okTotal = $totalLogs - $failedTotal;
        $lastPage = $totalLogs > 0 ? (int) ceil($totalLogs / $perPage) : 1;
        if ($page > $lastPage) {
            $page = $lastPage;
        }

        $timelineLogs = (clone $baseQuery)
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get();

        $from = $totalLogs > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to = $totalLogs > 0 ? min($page * $perPage, $totalLogs) : 0;

        $lastCheck = $project->last_check
            ? Carbon::parse($project->last_check)->format('d.m.Y H:i')
            : null;

        return [
            'project' => [
                'id' => $project->id,
                'domain' => $project->domain,
                'created_at' => $project->created_at
                    ? $project->created_at->format('d.m.Y H:i')
                    : null,
            ],
            'summary' => [
                'total_checks' => $totalLogs,
                'ok_checks' => $okTotal,
                'failed_checks' => $failedTotal,
                'success_rate' => $totalLogs > 0 ? round(($okTotal / $totalLogs) * 100, 1) : null,
                'currently_broken' => (bool) $project->broken,
                'current_status' => $project->broken
                    ? (string) __('Domain information status error')
                    : (string) __('Domain information status ok'),
                'last_check' => $lastCheck,
                'has_history' => $totalLogs > 0,
            ],
            'timeline' => $timelineLogs->map(static function (DomainInformationCheckLog $log) {
                return [
                    'id' => $log->id,
                    'at' => $log->created_at ? $log->created_at->format('d.m.Y H:i:s') : null,
                    'broken' => (bool) $log->broken,
                    'status' => $log->broken
                        ? (string) __('Domain information status error')
                        : (string) __('Domain information status ok'),
                    'info_preview' => self::previewText((string) $log->info_snapshot),
                    'dns_changed' => (bool) $log->dns_changed,
                    'source' => $log->source,
                    'source_label' => self::sourceLabel($log->source),
                ];
            })->values()->all(),
            'timeline_pagination' => [
                'total' => $totalLogs,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => $lastPage,
                'from' => $from,
                'to' => $to,
            ],
        ];
    }

    protected static function previewText(string $text): string
    {
        $text = trim(preg_replace('/\s+/', ' ', str_replace("\n", ' ', $text)));

        if (mb_strlen($text) <= 120) {
            return $text;
        }

        return mb_substr($text, 0, 117) . '…';
    }

    protected static function sourceLabel(?string $source): string
    {
        if ($source === 'manual') {
            return (string) __('Manual check');
        }

        return (string) __('Automatic check');
    }
}
