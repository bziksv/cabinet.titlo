<?php

namespace App\Services\SeoReports;

use App\DomainInformation;
use App\DomainMonitoring;
use App\ProjectRelevanceHistory;
use App\SeoChecklist\SeoChecklistItem;
use App\SeoChecklist\SeoChecklistProject;
use App\Services\SiteAudit\SiteAuditFindingPresenter;
use App\SiteAuditCrawl;
use App\SiteAuditFinding;
use App\SiteAuditProject;
use App\Support\HomeUserSites;
use Carbon\Carbon;
use Throwable;

class SeoReportTitloModulesCollector
{
    /**
     * @return array{ok:bool,status:string,message?:string,progress:string,data?:array}
     */
    public function collectAudit(int $userId, string $domain): array
    {
        $domain = HomeUserSites::normalizeDomain($domain);
        if ($domain === '') {
            return $this->fail('empty', __('Project not found'));
        }

        try {
            $project = SiteAuditProject::query()
                ->where('user_id', $userId)
                ->where('domain', $domain)
                ->orderByDesc('id')
                ->first();
        } catch (Throwable $e) {
            return $this->fail('error', __('Could not load Titlo module data'));
        }

        if (!$project) {
            return $this->fail('not_connected', __('Site Audit is not connected'));
        }

        /** @var SiteAuditCrawl|null $crawl */
        $crawl = $project->crawls()
            ->where('status', 'done')
            ->orderByDesc('finished_at')
            ->orderByDesc('id')
            ->first();

        if (!$crawl) {
            return $this->fail('empty', __('No Site Audit crawl yet'));
        }

        $buckets = is_array($crawl->buckets_json) ? $crawl->buckets_json : [];
        $critical = (int) ($buckets['critical'] ?? 0);
        $other = (int) ($buckets['other'] ?? 0);
        $important = (int) ($buckets['important'] ?? 0);
        $warning = (int) ($buckets['warning'] ?? 0);
        $info = (int) ($buckets['info'] ?? 0);
        $pages = (int) ($crawl->pages_fetched ?: $crawl->pages_total ?: 0);
        $topIssues = $this->auditTopIssues((int) $crawl->id, is_array($crawl->counts_json) ? $crawl->counts_json : []);

        $summary = $critical > 0
            ? __('SEO report audit summary critical', ['count' => $critical, 'pages' => $pages])
            : ($warning > 0
                ? __('SEO report audit summary warnings', ['count' => $warning, 'pages' => $pages])
                : __('SEO report audit summary ok', ['pages' => $pages]));

        return [
            'ok' => true,
            'status' => 'ok',
            'progress' => 'ok',
            'data' => [
                'project_id' => (int) $project->id,
                'crawl_id' => (int) $crawl->id,
                'finished_at' => optional($crawl->finished_at)->toIso8601String(),
                'pages_fetched' => $pages,
                'buckets' => [
                    'critical' => $critical,
                    'other' => $other,
                    'important' => $important,
                    'warning' => $warning,
                    'info' => $info,
                ],
                'bucket_labels' => [
                    'critical' => SiteAuditFindingPresenter::severityLabel('critical'),
                    'other' => SiteAuditFindingPresenter::severityLabel('other'),
                    'important' => SiteAuditFindingPresenter::severityLabel('important'),
                    'warning' => SiteAuditFindingPresenter::severityLabel('warning'),
                    'info' => SiteAuditFindingPresenter::severityLabel('info'),
                ],
                'top_issues' => $topIssues,
                'summary' => $summary,
                'hint' => __('SEO report audit hint'),
                'open_url' => route('pages.site-audit.crawl.show', ['id' => $crawl->id]),
                'public_url' => $crawl->publicShareUrl(),
            ],
        ];
    }

    /**
     * @return array{ok:bool,status:string,message?:string,progress:string,data?:array}
     */
    public function collectChecklist(int $userId, string $domain, ?Carbon $from, ?Carbon $to): array
    {
        $domain = HomeUserSites::normalizeDomain($domain);
        try {
            $project = SeoChecklistProject::query()
                ->where('user_id', $userId)
                ->where('domain', $domain)
                ->orderByDesc('id')
                ->first();
        } catch (Throwable $e) {
            return $this->fail('error', __('Could not load Titlo module data'));
        }

        if (!$project) {
            return $this->fail('not_connected', __('SEO Checklist is not connected'));
        }

        $closedQuery = $project->items()
            ->whereIn('status', SeoChecklistItem::CLOSED_STATUSES)
            ->whereNotNull('done_at');
        if ($from) {
            $closedQuery->where('done_at', '>=', $from->copy()->startOfDay());
        }
        if ($to) {
            $closedQuery->where('done_at', '<=', $to->copy()->endOfDay());
        }
        $closedInPeriod = (int) $closedQuery->count();

        $overdue = (int) $project->items()
            ->whereIn('status', SeoChecklistItem::OPEN_STATUSES)
            ->whereNotNull('due_at')
            ->where('due_at', '<', Carbon::now())
            ->count();

        $work = $this->workListsForChecklistProject($project, $from, $to);

        return [
            'ok' => true,
            'status' => 'ok',
            'progress' => 'ok',
            'data' => [
                'project_id' => (int) $project->id,
                'progress_done' => (int) ($project->progress_done ?? 0),
                'progress_total' => (int) ($project->progress_total ?? 0),
                'closed_in_period' => $closedInPeriod,
                'overdue' => $overdue,
                'closed_items' => $work['closed'],
                'plan_items' => $work['plan'],
                'open_url' => route('pages.seo-checklist.show', ['id' => $project->id]),
            ],
        ];
    }

    /**
     * Задачи чеклиста для блоков «Выполненные работы» / «План работ».
     *
     * @return array{ok:bool,status:string,message?:string,data?:array{project_id:int,open_url:string,closed:list<array>,plan:list<array>}}
     */
    public function collectWorkFromChecklist(int $userId, string $domain, ?Carbon $from, ?Carbon $to): array
    {
        $domain = HomeUserSites::normalizeDomain($domain);
        try {
            $project = SeoChecklistProject::query()
                ->where('user_id', $userId)
                ->where('domain', $domain)
                ->orderByDesc('id')
                ->first();
        } catch (Throwable $e) {
            return $this->fail('error', __('Could not load Titlo module data'));
        }

        if (!$project) {
            return $this->fail('not_connected', __('SEO Checklist is not connected'));
        }

        $work = $this->workListsForChecklistProject($project, $from, $to);

        return [
            'ok' => true,
            'status' => 'ok',
            'progress' => 'ok',
            'data' => [
                'project_id' => (int) $project->id,
                'open_url' => route('pages.seo-checklist.show', ['id' => $project->id]),
                'closed' => $work['closed'],
                'plan' => $work['plan'],
            ],
        ];
    }

    /**
     * @return array{closed:list<array{id:int,title:string,status:string,done_at:?string}>,plan:list<array{id:int,title:string,status:string,due_at:?string,overdue:bool}>}
     */
    private function workListsForChecklistProject(SeoChecklistProject $project, ?Carbon $from, ?Carbon $to): array
    {
        $closedQuery = $project->items()
            ->where('include_in_report', true)
            ->whereIn('status', SeoChecklistItem::CLOSED_STATUSES)
            ->whereNotNull('done_at');
        if ($from) {
            $closedQuery->where('done_at', '>=', $from->copy()->startOfDay());
        }
        if ($to) {
            $closedQuery->where('done_at', '<=', $to->copy()->endOfDay());
        }
        $closed = $closedQuery
            ->orderByDesc('done_at')
            ->limit(40)
            ->get(['id', 'title', 'status', 'done_at', 'parent_id'])
            ->map(static function (SeoChecklistItem $item) {
                return [
                    'id' => (int) $item->id,
                    'title' => (string) $item->title,
                    'status' => (string) $item->status,
                    'done_at' => $item->done_at ? $item->done_at->format('Y-m-d') : null,
                ];
            })
            ->values()
            ->all();

        $horizonEnd = ($to ? $to->copy() : Carbon::now())->addDays(21)->endOfDay();
        $now = Carbon::now();
        $plan = $project->items()
            ->where('include_in_report', true)
            ->whereIn('status', SeoChecklistItem::OPEN_STATUSES)
            ->where(function ($q) use ($horizonEnd) {
                $q->where(function ($q2) use ($horizonEnd) {
                    $q2->whereNotNull('due_at')->where('due_at', '<=', $horizonEnd);
                })->orWhereIn('status', ['doing', 'rework', 'clarify', 'review']);
            })
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->orderBy('id')
            ->limit(30)
            ->get(['id', 'title', 'status', 'due_at', 'parent_id'])
            ->map(static function (SeoChecklistItem $item) use ($now) {
                $due = $item->due_at;
                return [
                    'id' => (int) $item->id,
                    'title' => (string) $item->title,
                    'status' => (string) $item->status,
                    'due_at' => $due ? $due->format('Y-m-d') : null,
                    'overdue' => $due ? $due->lt($now) : false,
                ];
            })
            ->values()
            ->all();

        return ['closed' => $closed, 'plan' => $plan];
    }

    /**
     * @return array{ok:bool,status:string,message?:string,progress:string,data?:array}
     */
    public function collectRelevance(int $userId, string $domain): array
    {
        $domain = HomeUserSites::normalizeDomain($domain);
        try {
            $project = ProjectRelevanceHistory::query()
                ->where('user_id', $userId)
                ->where('name', $domain)
                ->orderByDesc('id')
                ->first();
        } catch (Throwable $e) {
            return $this->fail('error', __('Could not load Titlo module data'));
        }

        if (!$project) {
            return $this->fail('not_connected', __('Relevance analysis is not connected'));
        }

        $checks = (int) ($project->count_checks ?? 0);
        $sites = (int) ($project->count_sites ?? 0);
        // total_points уже средний балл по уникальным проверкам (шкала ~0–100), не сумма.
        $avgPoints = $project->total_points !== null ? round((float) $project->total_points, 1) : null;
        $avgPosition = $project->avg_position !== null ? round((float) $project->avg_position, 1) : null;

        if ($checks < 1) {
            return $this->fail('empty', __('No relevance analyses yet'));
        }

        $scoreTone = 'neutral';
        if ($avgPoints !== null) {
            $scoreTone = $avgPoints >= 70 ? 'good' : ($avgPoints >= 40 ? 'warn' : 'bad');
        }
        $posTone = 'neutral';
        if ($avgPosition !== null) {
            $posTone = $avgPosition <= 10 ? 'good' : ($avgPosition <= 30 ? 'warn' : 'bad');
        }

        $summaryParts = [];
        if ($avgPoints !== null) {
            $summaryParts[] = __('SEO report relevance summary score', ['score' => $avgPoints]);
        }
        if ($avgPosition !== null) {
            $summaryParts[] = __('SEO report relevance summary position', ['pos' => $avgPosition]);
        }

        return [
            'ok' => true,
            'status' => 'ok',
            'progress' => 'ok',
            'data' => [
                'project_id' => (int) $project->id,
                'count_checks' => $checks,
                'count_sites' => $sites,
                'avg_points' => $avgPoints,
                'avg_position' => $avgPosition,
                'score_tone' => $scoreTone,
                'position_tone' => $posTone,
                'last_check' => $project->last_check ? (string) $project->last_check : null,
                'summary' => $summaryParts !== [] ? implode(' ', $summaryParts) : null,
                'hint' => __('SEO report relevance hint'),
                'open_url' => route('relevance.history'),
            ],
        ];
    }

    /**
     * @return array{ok:bool,status:string,message?:string,progress:string,data?:array}
     */
    public function collectUptime(int $userId, string $domain): array
    {
        $domain = HomeUserSites::normalizeDomain($domain);
        try {
            $monitors = DomainMonitoring::query()
                ->where('user_id', $userId)
                ->orderByDesc('id')
                ->limit(200)
                ->get();
        } catch (Throwable $e) {
            return $this->fail('error', __('Could not load Titlo module data'));
        }

        $monitor = null;
        foreach ($monitors as $row) {
            if (HomeUserSites::normalizeDomain((string) $row->link) === $domain) {
                $monitor = $row;
                break;
            }
        }
        if (!$monitor) {
            return $this->fail('not_connected', __('Uptime monitoring is not connected'));
        }

        $domainInfo = null;
        try {
            $domainInfo = DomainInformation::query()
                ->where('user_id', $userId)
                ->where('domain', $domain)
                ->first();
        } catch (Throwable $e) {
            $domainInfo = null;
        }

        $daysLeft = null;
        if ($domainInfo && method_exists($domainInfo, 'daysUntilExpiry')) {
            try {
                $daysLeft = $domainInfo->daysUntilExpiry();
            } catch (Throwable $e) {
                $daysLeft = null;
            }
        }

        return [
            'ok' => true,
            'status' => 'ok',
            'progress' => 'ok',
            'data' => [
                'project_id' => (int) $monitor->id,
                'uptime_percent' => $monitor->uptime_percent !== null ? (float) $monitor->uptime_percent : null,
                'broken' => !empty($monitor->broken),
                'last_check' => $monitor->last_check ? (string) $monitor->last_check : null,
                'domain_days_left' => $daysLeft,
                'open_url' => route('site.monitoring'),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $counts
     * @return list<array{code:string,title:string,severity:string,severity_label:string,count:int,what:?string}>
     */
    private function auditTopIssues(int $crawlId, array $counts): array
    {
        $rows = [];
        if ($counts !== []) {
            foreach ($counts as $code => $count) {
                if (!is_string($code) || $code === '' || in_array($code, ['pages_with_canonical', 'click_depth_max'], true)) {
                    continue;
                }
                $n = (int) $count;
                if ($n < 1) {
                    continue;
                }
                $meta = config('site_audit.findings.' . $code, []);
                if (!is_array($meta) || $meta === []) {
                    continue;
                }
                $severity = (string) ($meta['severity'] ?? 'info');
                $rows[] = [
                    'code' => $code,
                    'title' => (string) ($meta['title'] ?? $code),
                    'severity' => $severity,
                    'severity_label' => SiteAuditFindingPresenter::severityLabel($severity),
                    'count' => $n,
                    'what' => isset($meta['description']) ? (string) $meta['description'] : null,
                ];
            }
        } else {
            try {
                $grouped = SiteAuditFinding::query()
                    ->where('crawl_id', $crawlId)
                    ->selectRaw('code, severity, COUNT(*) as c')
                    ->groupBy('code', 'severity')
                    ->orderByDesc('c')
                    ->limit(40)
                    ->get();
            } catch (Throwable $e) {
                $grouped = collect();
            }
            foreach ($grouped as $row) {
                $code = (string) $row->code;
                $meta = config('site_audit.findings.' . $code, []);
                $severity = (string) ($row->severity ?: ($meta['severity'] ?? 'info'));
                $rows[] = [
                    'code' => $code,
                    'title' => (string) ($meta['title'] ?? $code),
                    'severity' => $severity,
                    'severity_label' => SiteAuditFindingPresenter::severityLabel($severity),
                    'count' => (int) $row->c,
                    'what' => isset($meta['description']) ? (string) $meta['description'] : null,
                ];
            }
        }

        $rank = ['critical' => 0, 'other' => 1, 'important' => 2, 'warning' => 3, 'info' => 4];
        usort($rows, static function (array $a, array $b) use ($rank) {
            $ra = $rank[$a['severity']] ?? 9;
            $rb = $rank[$b['severity']] ?? 9;
            if ($ra !== $rb) {
                return $ra <=> $rb;
            }

            return $b['count'] <=> $a['count'];
        });

        return array_slice($rows, 0, 10);
    }

    /**
     * @return array{ok:bool,status:string,message:string,progress:string}
     */
    private function fail(string $status, string $message): array
    {
        return [
            'ok' => false,
            'status' => $status,
            'message' => $message,
            'progress' => $status === 'error' ? 'error' : 'skip',
        ];
    }
}
