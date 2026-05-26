<?php

namespace App\Support;

use App\DomainMonitoring;
use App\DomainMonitoringCheckLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SiteMonitoringProjectStats
{
    private const INCIDENT_LOG_LIMIT = 500;

    /**
     * Полный снимок для PDF и публичной ссылки (до N последних проверок).
     *
     * @return array<string, mixed>
     */
    public static function buildForExport(DomainMonitoring $project): array
    {
        $limit = (int) config('cabinet-site-monitoring.report_export_log_limit', 100);
        $limit = max(10, min(200, $limit));

        return self::build($project, 1, $limit);
    }

    /**
     * @return array<string, mixed>
     */
    public static function build(DomainMonitoring $project, int $page = 1, ?int $perPage = null): array
    {
        $perPage = $perPage ?? (int) config('cabinet-site-monitoring.stats_log_per_page', 25);
        $perPage = max(10, min(100, $perPage));
        $page = max(1, $page);

        $baseQuery = DomainMonitoringCheckLog::query()
            ->where('domain_monitoring_id', $project->id);

        $totalLogs = (clone $baseQuery)->count();
        $failedTotal = (clone $baseQuery)->where('broken', true)->count();
        $okTotal = $totalLogs - $failedTotal;
        $lastPage = $totalLogs > 0 ? (int) ceil($totalLogs / $perPage) : 1;
        if ($page > $lastPage) {
            $page = $lastPage;
        }

        $incidentLogs = (clone $baseQuery)
            ->orderByDesc('id')
            ->limit(self::INCIDENT_LOG_LIMIT)
            ->get()
            ->sortBy('id')
            ->values();

        $timelineLogs = (clone $baseQuery)
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get();

        $from = $totalLogs > 0 ? (($page - 1) * $perPage) + 1 : 0;
        $to = $totalLogs > 0 ? min($page * $perPage, $totalLogs) : 0;

        return [
            'project' => [
                'id' => $project->id,
                'name' => $project->project_name,
                'link' => $project->link,
                'timing' => (int) $project->timing,
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
                'current_status' => $project->status ? __($project->status) : '—',
                'current_code' => $project->code,
                'uptime_percent' => $project->uptime_percent !== null
                    ? round((float) $project->uptime_percent, 2)
                    : null,
                'last_check' => $project->last_check
                    ? Carbon::parse($project->last_check)->format('d.m.Y H:i')
                    : null,
                'downtime_minutes' => $project->broken && $project->total_time_last_breakdown
                    ? (int) $project->total_time_last_breakdown
                    : null,
                'has_history' => $totalLogs > 0,
            ],
            'incidents' => self::buildIncidents($incidentLogs),
            'timeline' => $timelineLogs->map(static function (DomainMonitoringCheckLog $log) {
                return [
                    'id' => $log->id,
                    'at' => $log->created_at ? $log->created_at->format('d.m.Y H:i:s') : null,
                    'broken' => (bool) $log->broken,
                    'status' => $log->status ? __($log->status) : '—',
                    'status_key' => $log->status,
                    'http_code' => $log->http_code,
                    'uptime_percent' => $log->uptime_percent !== null
                        ? round((float) $log->uptime_percent, 2)
                        : null,
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

    /**
     * @param  Collection<int, DomainMonitoringCheckLog>  $logs
     * @return array<int, array<string, mixed>>
     */
    private static function buildIncidents(Collection $logs): array
    {
        if ($logs->isEmpty()) {
            return [];
        }

        $incidents = [];
        $open = null;

        foreach ($logs as $log) {
            if ($log->broken && $open === null) {
                $open = [
                    'started_at' => $log->created_at,
                    'started_status' => $log->status,
                    'started_code' => $log->http_code,
                    'checks_while_down' => 1,
                ];
                continue;
            }

            if ($log->broken && $open !== null) {
                $open['checks_while_down']++;
                continue;
            }

            if (!$log->broken && $open !== null) {
                $incidents[] = self::finalizeIncident($open, $log->created_at);
                $open = null;
            }
        }

        if ($open !== null) {
            $incidents[] = self::finalizeIncident($open, null);
        }

        return array_reverse($incidents);
    }

    /**
     * @param  array<string, mixed>  $open
     * @return array<string, mixed>
     */
    private static function finalizeIncident(array $open, ?Carbon $endedAt): array
    {
        $started = $open['started_at'];
        $durationMinutes = null;
        if ($started) {
            $end = $endedAt ?? Carbon::now();
            $durationMinutes = max(0, $started->diffInMinutes($end));
        }

        return [
            'started_at' => $started ? $started->format('d.m.Y H:i') : null,
            'ended_at' => $endedAt ? $endedAt->format('d.m.Y H:i') : null,
            'ongoing' => $endedAt === null,
            'duration_minutes' => $durationMinutes,
            'checks_while_down' => $open['checks_while_down'],
            'started_status' => $open['started_status'] ? __($open['started_status']) : '—',
            'started_code' => $open['started_code'],
        ];
    }

    private static function sourceLabel(string $source): string
    {
        if ($source === 'manual') {
            return __('Manual check');
        }

        return __('Scheduled check');
    }
}
