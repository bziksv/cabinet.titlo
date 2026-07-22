<?php

namespace App\Http\Controllers;

use App\Exports\SiteAuditCanonicalSheet;
use App\Exports\SiteAuditCrawlSummaryExport;
use App\Exports\SiteAuditFindingsExport;
use App\Services\SiteAudit\SiteAuditCrawlStarter;
use App\Services\SiteAudit\SiteAuditCrawlStorage;
use App\Services\SiteAudit\SiteAuditDuplicateGrouper;
use App\Services\SiteAudit\SiteAuditIgnoreService;
use App\Services\SiteAudit\SiteAuditPruner;
use App\Services\SiteAudit\SiteAuditReportFilter;
use App\SiteAuditCrawl;
use App\SiteAuditFinding;
use App\SiteAuditIgnore;
use App\SiteAuditPage;
use App\SiteAuditProject;
use App\SiteAuditSchedule;
use App\Support\DemoCabinet;
use App\Support\SiteAuditLimits;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SiteAuditController extends Controller
{
    private const BUCKET_LABELS = [
        'critical' => 'Грубые',
        'other' => 'Прочие',
        'warning' => 'Предупреждения',
        'info' => 'Инфо',
    ];

    public function index(Request $request): View
    {
        $user = Auth::user();
        $projects = collect();
        $crawls = collect();

        if ($user && ! DemoCabinet::isCurrentUser()) {
            $projects = SiteAuditProject::query()
                ->where('user_id', $user->id)
                ->withCount('crawls')
                ->with(['crawls' => function ($q) {
                    $q->orderByDesc('id')->limit(1);
                }])
                ->orderByDesc('id')
                ->limit(50)
                ->get();

            $crawls = SiteAuditCrawl::query()
                ->where('user_id', $user->id)
                ->with('project')
                ->orderByDesc('id')
                ->limit(30)
                ->get();

            $crawlSizes = SiteAuditCrawlStorage::payloadBytesByCrawlIds($crawls->pluck('id')->all());

            $schedules = SiteAuditSchedule::query()
                ->where('user_id', $user->id)
                ->get()
                ->keyBy('project_id');
        } else {
            $schedules = collect();
            $crawlSizes = [];
        }

        return view('pages.site-audit', [
            'projects' => $projects,
            'crawls' => $crawls,
            'crawlSizes' => $crawlSizes,
            'schedules' => $schedules,
            'canSchedule' => $user && ! DemoCabinet::isCurrentUser() && SiteAuditSchedule::allowedForUser($user),
            'scheduleFrequencies' => SiteAuditSchedule::frequencyLabels(),
            'pagesLimit' => SiteAuditLimits::pagesPerCrawlLimit(),
            'crawlsLimit' => SiteAuditLimits::crawlsPerMonthLimit(),
            'crawlsUsed' => SiteAuditLimits::crawlsUsedThisMonth(),
            'findingsCatalog' => config('site_audit.findings', []),
            'isLocal' => app()->environment('local'),
            'bucketLabels' => self::BUCKET_LABELS,
        ]);
    }

    public function showCrawl(int $id): View
    {
        $crawl = $this->ownedCrawl($id);
        $crawl->load('project');

        $counts = $crawl->counts_json ?: SiteAuditFinding::query()
            ->where('crawl_id', $crawl->id)
            ->selectRaw('code, count(*) as c')
            ->groupBy('code')
            ->pluck('c', 'code')
            ->all();
        $counts = (new SiteAuditIgnoreService())->applyToCounts((array) $counts, $crawl);

        $tree = $this->buildReportTree($counts, 'tech');
        $treeSeo = $this->buildReportTree($counts, 'seo');
        $treeAll = $this->buildReportTree($counts, null);
        $bucketsTech = $this->bucketsFromTree($tree);
        $bucketsSeo = $this->bucketsFromTree($treeSeo);
        $bucketsAll = $this->bucketsFromTree($treeAll);

        $history = SiteAuditCrawl::query()
            ->where('project_id', $crawl->project_id)
            ->where('status', SiteAuditCrawl::STATUS_DONE)
            ->orderByDesc('id')
            ->limit(8)
            ->get(['id', 'buckets_json', 'counts_json', 'pages_total', 'finished_at', 'created_at']);

        $historyRows = [];
        foreach ($history as $h) {
            $hCounts = is_array($h->counts_json) ? $h->counts_json : [];
            if ($h->id === $crawl->id && $hCounts === []) {
                $hCounts = $counts;
            }
            $historyRows[] = [
                'crawl' => $h,
                'tech' => $this->bucketsFromTree($this->buildReportTree($hCounts, 'tech')),
                'seo' => $this->bucketsFromTree($this->buildReportTree($hCounts, 'seo')),
            ];
        }

        $archiveLimit = min(100, max(8, (int) config('site_audit.history_keep_per_project', 200)));
        $archiveCrawls = SiteAuditCrawl::query()
            ->where('project_id', $crawl->project_id)
            ->whereIn('status', [SiteAuditCrawl::STATUS_DONE, SiteAuditCrawl::STATUS_FAILED])
            ->orderByDesc('id')
            ->limit($archiveLimit)
            ->get(['id', 'status', 'buckets_json', 'pages_total', 'pages_fetched', 'finished_at', 'created_at', 'error']);

        return view('pages.site-audit-crawl', [
            'crawl' => $crawl,
            'project' => $crawl->project,
            'buckets' => $bucketsTech,
            'bucketsAll' => $bucketsAll,
            'bucketsSeo' => $bucketsSeo,
            'bucketLabels' => self::BUCKET_LABELS,
            'tree' => $tree,
            'treeSeo' => $treeSeo,
            'treeAll' => $treeAll,
            'counts' => $counts,
            'findingsCatalog' => config('site_audit.findings', []),
            'isLocal' => app()->environment('local'),
            'history' => $history,
            'historyRows' => $historyRows,
            'archiveCrawls' => $archiveCrawls,
            'compareCandidates' => $history->where('id', '!=', $crawl->id)->values(),
            'shareUrl' => $crawl->publicShareUrl(),
        ]);
    }

    public function showReport(Request $request, int $id, string $code): View
    {
        $crawl = $this->ownedCrawl($id);
        $crawl->load('project');

        $meta = config('site_audit.findings.' . $code);
        if (! $meta) {
            abort(404);
        }

        $page = max(1, (int) $request->input('page', 1));
        $perPage = 50;
        $filterFields = SiteAuditReportFilter::fieldsForCode($code);
        $filterValues = SiteAuditReportFilter::valuesFromRequest($request, $code);
        $groupable = SiteAuditDuplicateGrouper::isGroupable($code);
        $viewMode = $request->input('view', $groupable ? 'groups' : 'list');
        if (! in_array($viewMode, ['groups', 'list'], true) || ! $groupable) {
            $viewMode = $groupable ? 'groups' : 'list';
        }

        $showIgnored = in_array((string) $request->input('ignored', ''), ['1', 'true', 'yes'], true);
        $ignoreSvc = new SiteAuditIgnoreService();
        $projectId = (int) $crawl->project_id;
        $ignoredMap = [];
        $codeWideIgnored = SiteAuditIgnore::query()
            ->where('project_id', $projectId)
            ->where('code', $code)
            ->where('url_hash', '')
            ->exists();

        $groups = [];
        $groupTotal = 0;

        if (($meta['source'] ?? '') === 'pages_canonical') {
            $query = SiteAuditPage::query()
                ->where('crawl_id', $crawl->id)
                ->whereNotNull('canonical')
                ->where('canonical', '!=', '')
                ->orderBy('id');
            SiteAuditReportFilter::applyToPages($query, $filterValues);
            $total = (clone $query)->count();
            $rows = $query->forPage($page, $perPage)->get()->map(function (SiteAuditPage $p) use ($meta) {
                return (object) [
                    'id' => null,
                    'url' => $p->url,
                    'url_hash' => null,
                    'severity' => $meta['severity'] ?? 'info',
                    'code' => 'pages_with_canonical',
                    'meta_json' => ['canonical' => $p->canonical],
                ];
            });
            $pages = max(1, (int) ceil($total / $perPage));
        } else {
            $codes = $this->reportCodes($code, $meta);
            $query = SiteAuditFinding::query()
                ->where('crawl_id', $crawl->id)
                ->whereIn('code', $codes)
                ->orderBy('id');
            SiteAuditReportFilter::applyToFindings($query, $crawl->id, $filterValues);
            if (! $showIgnored) {
                $ignoreSvc->excludeIgnored($query, $projectId);
            }

            $total = (clone $query)->count();

            if ($viewMode === 'groups') {
                $allRows = $query->get();
                $allGroups = SiteAuditDuplicateGrouper::group($allRows, $code);
                $groupTotal = count($allGroups);
                $perPage = 20;
                $pages = max(1, (int) ceil(max(1, $groupTotal) / $perPage));
                $page = min($page, $pages);
                $groups = array_slice($allGroups, ($page - 1) * $perPage, $perPage);
                $rows = collect();
                if ($showIgnored) {
                    $ignoredMap = $ignoreSvc->ignoredMapForFindings($projectId, $allRows);
                }
            } else {
                $rows = $query->forPage($page, $perPage)->get();
                $pages = max(1, (int) ceil($total / $perPage));
                $ignoredMap = $ignoreSvc->ignoredMapForFindings($projectId, $rows);
            }
        }

        $filterParams = SiteAuditReportFilter::queryParams($filterValues);
        if ($groupable) {
            $filterParams['view'] = $viewMode;
        }
        if ($showIgnored) {
            $filterParams['ignored'] = 1;
        }

        $sideCounts = (array) ($crawl->counts_json ?: []);
        $sideCounts = $ignoreSvc->applyToCounts($sideCounts, $crawl);

        $tree = $this->buildReportTree($sideCounts, 'tech');
        $treeSeo = $this->buildReportTree($sideCounts, 'seo');
        $treeAll = $this->buildReportTree($sideCounts, null);
        $buckets = $this->bucketsFromTree($tree);
        $bucketsSeo = $this->bucketsFromTree($treeSeo);
        $bucketsAll = $this->bucketsFromTree($treeAll);

        $seoCodes = config('site_audit.seo_codes', []);
        $itemGroup = $meta['group'] ?? (in_array($code, $seoCodes, true) ? 'seo' : 'tech');
        // На отчёте по умолчанию открываем сводку со всеми замечаниями.
        $activeGroup = 'all';

        return view('pages.site-audit-report', [
            'crawl' => $crawl,
            'project' => $crawl->project,
            'code' => $code,
            'meta' => $meta,
            'rows' => $rows,
            'groups' => $groups,
            'groupable' => $groupable,
            'viewMode' => $viewMode,
            'groupTotal' => $groupTotal,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'pages' => $pages,
            'bucketLabels' => self::BUCKET_LABELS,
            'filterFields' => $filterFields,
            'filterValues' => $filterValues,
            'filtersActive' => SiteAuditReportFilter::hasActive($filterValues),
            'filterAction' => route('pages.site-audit.report.show', [$crawl->id, $code]),
            'filterClearUrl' => route('pages.site-audit.report.show', [$crawl->id, $code]),
            'filterParams' => $filterParams,
            'showIgnored' => $showIgnored,
            'ignoredMap' => $ignoredMap,
            'codeWideIgnored' => $codeWideIgnored,
            'sideCounts' => $sideCounts,
            'tree' => $tree,
            'treeSeo' => $treeSeo,
            'treeAll' => $treeAll,
            'buckets' => $buckets,
            'bucketsSeo' => $bucketsSeo,
            'bucketsAll' => $bucketsAll,
            'activeGroup' => $activeGroup,
            'itemGroup' => $itemGroup,
            'canIgnore' => ! DemoCabinet::isCurrentUser() && ($meta['source'] ?? '') !== 'pages_canonical',
        ]);
    }

    public function destroyCrawl(Request $request, int $id)
    {
        if (DemoCabinet::isCurrentUser()) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'demo'], 403);
            }
            abort(403);
        }

        $crawl = $this->ownedCrawl($id);
        if (! $crawl->isFinished()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'active',
                    'message' => 'Нельзя удалить незавершённый краул',
                ], 422);
            }

            return redirect()
                ->route('pages.site-audit.crawl.show', $crawl->id)
                ->with('status', 'Нельзя удалить незавершённый краул');
        }

        (new SiteAuditPruner())->deleteCrawl($crawl);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'redirect' => route('pages.site-audit'),
            ]);
        }

        return redirect()->route('pages.site-audit')->with('status', 'Краул удалён');
    }

    public function destroyProject(Request $request, int $id)
    {
        if (DemoCabinet::isCurrentUser()) {
            abort(403);
        }

        $user = Auth::user();
        abort_unless($user, 401);

        $project = SiteAuditProject::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $active = SiteAuditCrawl::query()
            ->where('project_id', $project->id)
            ->whereNotIn('status', [
                SiteAuditCrawl::STATUS_DONE,
                SiteAuditCrawl::STATUS_FAILED,
            ])
            ->exists();

        if ($active) {
            return redirect()
                ->route('pages.site-audit')
                ->with('status', 'Сначала дождитесь завершения активного краула');
        }

        $pruner = new SiteAuditPruner();
        foreach ($project->crawls()->orderBy('id')->get() as $crawl) {
            $pruner->deleteCrawl($crawl);
        }
        $project->delete();

        return redirect()->route('pages.site-audit')->with('status', 'Проект удалён');
    }

    public function saveSchedule(Request $request, int $projectId)
    {
        if (DemoCabinet::isCurrentUser()) {
            abort(403);
        }

        $user = Auth::user();
        abort_unless($user, 401);

        $project = SiteAuditProject::query()
            ->where('id', $projectId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $enabled = $request->boolean('enabled', false);
        $frequency = SiteAuditSchedule::normalizeFrequency($request->input('frequency', SiteAuditSchedule::FREQ_WEEKLY));

        if (! $enabled) {
            SiteAuditSchedule::query()
                ->where('user_id', $user->id)
                ->where('project_id', $project->id)
                ->delete();

            return redirect()->route('pages.site-audit')->with('status', 'Расписание отключено');
        }

        if (! SiteAuditSchedule::allowedForUser($user)) {
            return redirect()->route('pages.site-audit')
                ->with('error', 'Расписание аудита доступно только на платных тарифах');
        }

        $schedule = SiteAuditSchedule::query()->firstOrNew([
            'user_id' => $user->id,
            'project_id' => $project->id,
        ]);
        $schedule->domain = $project->domain;
        $schedule->enabled = true;
        $schedule->frequency = $frequency;
        $schedule->settings_json = [
            'crawl_speed' => 'normal',
            'save_html' => 'off',
        ];
        // при смене частоты / первом включении — ближайший слот от сейчас
        $schedule->next_run_at = $schedule->computeNextRun(Carbon::now());
        $schedule->save();

        return redirect()->route('pages.site-audit')->with('status', 'Расписание сохранено');
    }

    public function createShare(int $id): JsonResponse
    {
        if (DemoCabinet::isCurrentUser()) {
            return response()->json(['error' => 'demo'], 403);
        }

        $crawl = $this->ownedCrawl($id);
        if ($crawl->status !== SiteAuditCrawl::STATUS_DONE) {
            return response()->json(['error' => 'status', 'message' => 'Шаринг только для готового краула'], 422);
        }

        if (! $crawl->share_token) {
            $crawl->share_token = bin2hex(random_bytes(24));
        }
        $crawl->share_enabled_at = now();
        $crawl->save();

        return response()->json([
            'ok' => true,
            'url' => $crawl->publicShareUrl(),
            'token' => $crawl->share_token,
        ]);
    }

    public function revokeShare(int $id): JsonResponse
    {
        if (DemoCabinet::isCurrentUser()) {
            return response()->json(['error' => 'demo'], 403);
        }

        $crawl = $this->ownedCrawl($id);
        $crawl->share_enabled_at = null;
        // token оставляем — можно снова включить ту же ссылку
        $crawl->save();

        return response()->json(['ok' => true]);
    }

    public function start(Request $request): JsonResponse
    {
        if (! Auth::check()) {
            return response()->json(['error' => 'auth'], 401);
        }

        if (DemoCabinet::isCurrentUser()) {
            return response()->json(['error' => 'demo', 'message' => 'В демо кабинете запуск аудита недоступен'], 403);
        }

        $domain = trim((string) $request->input('domain', ''));
        $seed = trim((string) $request->input('seed_urls', ''));
        $seedUrls = [];
        if ($seed !== '') {
            foreach (preg_split('/\R+/', $seed) as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $seedUrls[] = $line;
                }
            }
        }

        $settings = [
            'seed_urls' => $seedUrls,
            'save_html' => 'off',
            'crawl_speed' => (string) $request->input('crawl_speed', 'normal'),
            'exclude_patterns' => (string) $request->input('exclude_patterns', ''),
            'unify_www' => true,
            'force_https' => true,
            'strip_trailing_slash' => true,
            'check_broken_links' => true,
        ];

        if (app()->environment('local') && $request->filled('pages_limit')) {
            $settings['pages_limit'] = max(1, (int) $request->input('pages_limit'));
        }

        $runSync = app()->environment('local')
            && in_array((string) $request->input('sync', ''), ['1', 'true', 'yes', 'on'], true);

        try {
            $crawl = (new SiteAuditCrawlStarter())->start(
                Auth::user(),
                $domain,
                $settings,
                ! $runSync
            );
            if (! empty($settings['pages_limit'])) {
                $crawl->pages_limit = (int) $settings['pages_limit'];
                $crawl->save();
            }
            if ($runSync) {
                $crawl = (new \App\Services\SiteAudit\SiteAuditSyncRunner())->run($crawl);
            }
        } catch (\Throwable $e) {
            return response()->json(['error' => 'limit', 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'crawl_id' => $crawl->id,
            'status' => $crawl->status,
            'status_label' => $crawl->statusLabelRu(),
            'pages_fetched' => (int) $crawl->pages_fetched,
            'pages_total' => (int) $crawl->pages_total,
            'finished' => $crawl->isFinished(),
            'status_url' => route('pages.site-audit.crawl.status', $crawl->id),
            // Остаёмся в списке «История краулов» — прогресс там, не на сводке.
            'redirect' => route('pages.site-audit'),
            'message' => $runSync
                ? 'Краул выполнен. Смотрите историю ниже.'
                : 'Краул запущен. Прогресс — в истории краулов.',
            'settings' => $crawl->progress_json['settings'] ?? null,
        ]);
    }

    /**
     * Повторный краул проекта с настройками исходного (скорость, exclude, seed, лимит).
     */
    public function repeatCrawl(Request $request, int $id)
    {
        if (DemoCabinet::isCurrentUser()) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'demo', 'message' => 'В демо кабинете запуск аудита недоступен'], 403);
            }
            abort(403);
        }

        $source = $this->ownedCrawl($id);
        $project = $source->project;
        if (! $project || ! $project->domain) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'project', 'message' => 'У краула нет проекта'], 422);
            }

            return redirect()
                ->route('pages.site-audit')
                ->with('status', 'У краула нет проекта');
        }

        $settings = array_merge(
            is_array($project->settings_json) ? $project->settings_json : [],
            is_array($source->progress_json['settings'] ?? null) ? $source->progress_json['settings'] : []
        );
        $settings['pages_limit'] = max(1, (int) $source->pages_limit);
        if (! empty($source->save_html)) {
            $settings['save_html'] = $source->save_html;
        }

        $runSync = app()->environment('local')
            && in_array((string) $request->input('sync', ''), ['1', 'true', 'yes', 'on'], true);

        try {
            $crawl = (new SiteAuditCrawlStarter())->start(
                Auth::user(),
                $project->domain,
                $settings,
                ! $runSync
            );
            if (! empty($settings['pages_limit'])) {
                $crawl->pages_limit = (int) $settings['pages_limit'];
                $crawl->save();
            }
            if ($runSync) {
                $crawl = (new \App\Services\SiteAudit\SiteAuditSyncRunner())->run($crawl);
            }
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'limit', 'message' => $e->getMessage()], 422);
            }

            return redirect()
                ->route('pages.site-audit')
                ->with('status', $e->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'crawl_id' => $crawl->id,
                'status' => $crawl->status,
                'status_label' => $crawl->statusLabelRu(),
                'finished' => $crawl->isFinished(),
                'status_url' => route('pages.site-audit.crawl.status', $crawl->id),
                'redirect' => route('pages.site-audit'),
                'message' => $runSync
                    ? 'Повтор выполнен. Смотрите историю ниже.'
                    : 'Повтор запущен. Прогресс — в истории краулов.',
            ]);
        }

        return redirect(
            route('pages.site-audit') . '?highlight=' . $crawl->id . '#sa-history'
        )->with('status', 'Повторный краул #' . $crawl->id . ' запущен');
    }

    public function crawlStatus(int $id): JsonResponse
    {
        $crawl = $this->ownedCrawl($id);

        $counts = $crawl->counts_json ?: [];
        if ($crawl->status === SiteAuditCrawl::STATUS_DONE && ! $counts) {
            $counts = SiteAuditFinding::query()
                ->where('crawl_id', $crawl->id)
                ->selectRaw('code, count(*) as c')
                ->groupBy('code')
                ->pluck('c', 'code')
                ->all();
        }

        return response()->json([
            'id' => $crawl->id,
            'status' => $crawl->status,
            'status_label' => $crawl->statusLabelRu(),
            'pages_fetched' => (int) $crawl->pages_fetched,
            'pages_total' => (int) $crawl->pages_total,
            'buckets' => $crawl->buckets_json,
            'counts' => $counts,
            'error' => $crawl->error,
            'finished' => $crawl->isFinished(),
            'progress_pct' => $crawl->pages_total > 0
                ? (int) round(100 * $crawl->pages_fetched / $crawl->pages_total)
                : 0,
        ]);
    }

    public function exportReportCsv(Request $request, int $id, string $code): StreamedResponse
    {
        $crawl = $this->ownedCrawl($id);
        $meta = config('site_audit.findings.' . $code, []);
        if (! $meta) {
            abort(404);
        }
        $filename = 'site-audit-' . $crawl->id . '-' . $code . '.csv';
        $filterValues = SiteAuditReportFilter::valuesFromRequest($request, $code);

        if (($meta['source'] ?? '') === 'pages_canonical') {
            return response()->streamDownload(function () use ($crawl, $filterValues) {
                $out = fopen('php://output', 'w');
                fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
                fputcsv($out, ['url', 'canonical'], ';');

                $query = SiteAuditPage::query()
                    ->where('crawl_id', $crawl->id)
                    ->whereNotNull('canonical')
                    ->where('canonical', '!=', '')
                    ->orderBy('id');
                SiteAuditReportFilter::applyToPages($query, $filterValues);
                $query->chunk(200, function ($rows) use ($out) {
                    foreach ($rows as $row) {
                        fputcsv($out, [$row->url, $row->canonical], ';');
                    }
                });

                fclose($out);
            }, $filename, [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        $codes = $this->reportCodes($code, $meta);
        $includeIgnored = in_array((string) $request->input('ignored', ''), ['1', 'true', 'yes'], true);
        $projectId = (int) $crawl->project_id;

        return response()->streamDownload(function () use ($crawl, $codes, $filterValues, $includeIgnored, $projectId) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['url', 'code', 'severity', 'meta'], ';');

            $query = SiteAuditFinding::query()
                ->where('crawl_id', $crawl->id)
                ->whereIn('code', $codes)
                ->orderBy('id');
            SiteAuditReportFilter::applyToFindings($query, $crawl->id, $filterValues);
            if (! $includeIgnored) {
                (new SiteAuditIgnoreService())->excludeIgnored($query, $projectId);
            }
            $query->chunk(200, function ($rows) use ($out) {
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row->url,
                        $row->code,
                        $row->severity,
                        $row->meta_json ? json_encode($row->meta_json, JSON_UNESCAPED_UNICODE) : '',
                    ], ';');
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportReportXlsx(Request $request, int $id, string $code): BinaryFileResponse
    {
        $crawl = $this->ownedCrawl($id);
        $meta = config('site_audit.findings.' . $code, []);
        if (! $meta) {
            abort(404);
        }

        $filename = 'site-audit-' . $crawl->id . '-' . $code . '.xlsx';
        $title = (string) ($meta['title'] ?? $code);
        $filterValues = SiteAuditReportFilter::valuesFromRequest($request, $code);

        if (($meta['source'] ?? '') === 'pages_canonical') {
            return Excel::download(new SiteAuditCanonicalSheet($crawl->id, $filterValues), $filename);
        }

        $codes = $this->reportCodes($code, $meta);
        $includeIgnored = in_array((string) $request->input('ignored', ''), ['1', 'true', 'yes'], true);

        return Excel::download(
            new SiteAuditFindingsExport($crawl->id, $codes, $title, $filterValues, $includeIgnored),
            $filename
        );
    }

    public function exportCrawlXlsx(int $id): BinaryFileResponse
    {
        $crawl = $this->ownedCrawl($id);

        return Excel::download(
            new SiteAuditCrawlSummaryExport($crawl),
            'site-audit-' . $crawl->id . '-summary.xlsx'
        );
    }

    public function exportCrawlDocx(int $id)
    {
        $crawl = $this->ownedCrawl($id);
        $path = (new \App\Services\SiteAudit\SiteAuditDocxBuilder())->buildToTemp($crawl);

        return response()->download(
            $path,
            'site-audit-' . $crawl->id . '-summary.docx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
        )->deleteFileAfterSend(true);
    }

    public function showDiff(Request $request, int $id): View
    {
        $crawl = $this->ownedCrawl($id);
        $crawl->load('project');

        abort_unless($crawl->status === SiteAuditCrawl::STATUS_DONE, 404);

        $withId = (int) $request->input('with', 0);
        $candidates = SiteAuditCrawl::query()
            ->where('project_id', $crawl->project_id)
            ->where('status', SiteAuditCrawl::STATUS_DONE)
            ->where('id', '!=', $crawl->id)
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'pages_total', 'finished_at', 'created_at', 'buckets_json']);

        abort_unless($candidates->isNotEmpty(), 404, 'Нет другого завершённого краула для сравнения');

        if ($withId < 1) {
            $baseline = $candidates->first();
        } else {
            $baseline = $candidates->firstWhere('id', $withId);
            abort_unless($baseline, 404);
        }

        // baseline = более старый по умолчанию; если выбрали новее текущего — всё равно diff current vs with
        $diff = (new \App\Services\SiteAudit\SiteAuditCrawlDiff())->compare($crawl, $baseline);

        return view('pages.site-audit-diff', [
            'crawl' => $crawl,
            'project' => $crawl->project,
            'baseline' => $baseline,
            'candidates' => $candidates,
            'diff' => $diff,
            'bucketLabels' => self::BUCKET_LABELS,
            'findingsCatalog' => config('site_audit.findings', []),
        ]);
    }

    public function ignoreFinding(Request $request, int $id)
    {
        if (DemoCabinet::isCurrentUser()) {
            return $this->ignoreJsonOrRedirect($request, 403, 'demo');
        }

        $crawl = $this->ownedCrawl($id);
        $findingId = (int) $request->input('finding_id', 0);
        $code = (string) $request->input('code', '');
        $scope = (string) $request->input('scope', 'url'); // url|code
        $note = $request->input('note');
        $note = is_string($note) ? mb_substr(trim($note), 0, 500) : null;

        $svc = new SiteAuditIgnoreService();
        $projectId = (int) $crawl->project_id;

        if ($scope === 'code') {
            if ($code === '' || ! config('site_audit.findings.' . $code)) {
                return $this->ignoreJsonOrRedirect($request, 422, 'bad_code');
            }
            $svc->ignore($projectId, (int) Auth::id(), $code, '', null, $note);
        } else {
            $finding = SiteAuditFinding::query()
                ->where('id', $findingId)
                ->where('crawl_id', $crawl->id)
                ->first();
            if (! $finding) {
                return $this->ignoreJsonOrRedirect($request, 404, 'not_found');
            }
            $svc->ignoreFinding($finding, $projectId, (int) Auth::id(), $note);
            $code = $finding->code;
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'code' => $code]);
        }

        return redirect()
            ->route('pages.site-audit.report.show', [$crawl->id, $code])
            ->with('status', 'Находка добавлена в игнор (для следующих краулов тоже)');
    }

    public function restoreIgnore(Request $request, int $id)
    {
        if (DemoCabinet::isCurrentUser()) {
            return $this->ignoreJsonOrRedirect($request, 403, 'demo');
        }

        $crawl = $this->ownedCrawl($id);
        $findingId = (int) $request->input('finding_id', 0);
        $code = (string) $request->input('code', '');
        $scope = (string) $request->input('scope', 'url');
        $projectId = (int) $crawl->project_id;
        $svc = new SiteAuditIgnoreService();

        if ($scope === 'code') {
            if ($code === '') {
                return $this->ignoreJsonOrRedirect($request, 422, 'bad_code');
            }
            $svc->restore($projectId, $code, '');
        } else {
            $finding = SiteAuditFinding::query()
                ->where('id', $findingId)
                ->where('crawl_id', $crawl->id)
                ->first();
            if (! $finding) {
                return $this->ignoreJsonOrRedirect($request, 404, 'not_found');
            }
            $svc->restoreFinding($finding, $projectId);
            $code = $finding->code;
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'code' => $code]);
        }

        return redirect()
            ->to(route('pages.site-audit.report.show', [$crawl->id, $code]) . '?ignored=1')
            ->with('status', 'Игнор снят');
    }

    private function ignoreJsonOrRedirect(Request $request, int $status, string $error)
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => $error], $status);
        }
        abort($status);
    }

    private function ownedCrawl(int $id): SiteAuditCrawl
    {
        $user = Auth::user();
        abort_unless($user, 401);

        return SiteAuditCrawl::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();
    }

    /**
     * @param array $meta
     * @return string[]
     */
    private function reportCodes(string $code, array $meta): array
    {
        if (! empty($meta['virtual']) && ! empty($meta['codes']) && is_array($meta['codes'])) {
            return array_values($meta['codes']);
        }

        return [$code];
    }

    /**
     * @param array|object $counts
     * @param string|null $group tech|seo|null(=all)
     */
    private function buildReportTree($counts, ?string $group = null): array
    {
        $counts = (array) $counts;
        $catalog = config('site_audit.findings', []);
        $seoCodes = config('site_audit.seo_codes', []);
        $bySeverity = [
            'critical' => [],
            'other' => [],
            'warning' => [],
            'info' => [],
        ];

        foreach ($catalog as $code => $meta) {
            $phase = $meta['phase'] ?? '';
            if (! in_array($phase, ['A', 'B'], true)) {
                continue;
            }

            $itemGroup = $meta['group'] ?? (in_array($code, $seoCodes, true) ? 'seo' : 'tech');
            if ($group !== null && $itemGroup !== $group) {
                continue;
            }

            $severity = $meta['severity'] ?? 'info';
            if (! isset($bySeverity[$severity])) {
                $severity = 'info';
            }

            if (! empty($meta['virtual']) && ! empty($meta['codes']) && is_array($meta['codes'])) {
                $count = 0;
                foreach ($meta['codes'] as $c) {
                    $count += (int) ($counts[$c] ?? 0);
                }
            } elseif (($meta['source'] ?? '') === 'pages_canonical') {
                $count = (int) ($counts['pages_with_canonical'] ?? 0);
            } else {
                $count = (int) ($counts[$code] ?? 0);
            }

            $bySeverity[$severity][] = [
                'code' => $code,
                'title' => $meta['title'] ?? $code,
                'description' => $meta['description'] ?? '',
                'count' => $count,
                'phase' => $phase,
                'group' => $itemGroup,
            ];
        }

        foreach ($bySeverity as $sev => $items) {
            usort($items, function ($a, $b) {
                if ($a['count'] === $b['count']) {
                    return strcmp($a['title'], $b['title']);
                }

                return $b['count'] <=> $a['count'];
            });
            $bySeverity[$sev] = $items;
        }

        return $bySeverity;
    }

    private function bucketsFromTree(array $tree): array
    {
        $out = ['critical' => 0, 'other' => 0, 'warning' => 0, 'info' => 0];
        foreach ($tree as $sev => $items) {
            foreach ($items as $item) {
                $out[$sev] = ($out[$sev] ?? 0) + (int) ($item['count'] ?? 0);
            }
        }

        return $out;
    }
}
