<?php

namespace App\Http\Controllers;

use App\Classes\Monitoring\MonitoringPortfolioTop10TrendService;
use App\Classes\Monitoring\MonitoringProjectListSerializer;
use App\Classes\Monitoring\ProjectFaviconFetcher;
use App\Classes\Monitoring\ProjectFaviconService;
use App\MonitoringProject;
use App\MonitoringPublicShare;
use App\Classes\Monitoring\MonitoringProjectPageSummary;
use App\Support\MonitoringProjectPublicStats;
use App\Support\MonitoringPublicShareTtl;
use App\MonitoringV2UserPreference;
use App\Support\MonitoringPositionsSchedule;
use App\Support\MonitoringV2DebugLog;
use App\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MonitoringV2Controller extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            return $next($request);
        });
    }

    /**
     * @return View|RedirectResponse
     */
    public function index()
    {
        App::setLocale('ru');

        /** @var User $user */
        $user = Auth::user();

        MonitoringPositionsSchedule::enforceForFreeUser($user);

        $count = $user->monitoringProjects()->count();
        $isMonitoringAdmin = $user->hasAnyRole(['Super Admin', 'admin']);

        $debugSession = $isMonitoringAdmin
            ? 'monv2-u' . (int) $user->id . '-' . time()
            : '';
        if ($debugSession !== '') {
            MonitoringV2DebugLog::clear($debugSession);
        }

        try {
            $listColumns = MonitoringV2UserPreference::listColumnsForUser((int) $user->id);
        } catch (\Throwable $e) {
            report($e);
            $listColumns = MonitoringV2UserPreference::defaultListColumns();
        }

        return view('monitoring-v2.index', [
            'count' => $count,
            'isMonitoringAdmin' => $isMonitoringAdmin,
            'statusOptions' => MonitoringProjectUserStatusController::getOptions(),
            'listColumns' => $listColumns,
            'debugSessionId' => $debugSession,
        ]);
    }

    public function saveListColumns(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $columns = $request->input('columns', []);
        if (!is_array($columns)) {
            $columns = [];
        }

        $saved = MonitoringV2UserPreference::saveListColumns((int) $user->id, $columns);

        return response()->json(['columns' => $saved]);
    }

    /**
     * Список для карточек v2: JSON без Blade в цикле, метрики из снимка.
     */
    public function listProjects(Request $request, MonitoringProjectListSerializer $serializer): JsonResponse
    {
        App::setLocale('ru');

        /** @var User $user */
        $user = Auth::user();

        $refresh = $request->boolean('refresh');
        $rebuildSnapshots = $request->boolean('rebuild_snapshots');
        $debugSession = $this->debugSession($request, $user);
        $t0 = microtime(true);

        try {
            MonitoringV2DebugLog::info($debugSession, 'http.list.start', [
                'refresh' => $refresh,
                'rebuild_snapshots' => $rebuildSnapshots,
                'user_id' => (int) $user->id,
            ]);

            if ($refresh) {
                MonitoringProjectListSerializer::forgetCacheForUser((int) $user->id);
            }

            if ($rebuildSnapshots) {
                set_time_limit(max(120, (int) ini_get('max_execution_time')));
            }

            $payload = $serializer->forUser($user, !$refresh, $rebuildSnapshots);
            $ms = (int) round((microtime(true) - $t0) * 1000);

            MonitoringV2DebugLog::info($debugSession, 'http.list.done', [
                'ms' => $ms,
                'total' => $payload['total'] ?? 0,
                'snapshots_pending' => $payload['snapshots_pending'] ?? 0,
                'cached_at' => $payload['cached_at'] ?? null,
            ]);
            MonitoringV2DebugLog::setState($debugSession, [
                'last' => 'list',
                'ms' => $ms,
                'total' => $payload['total'] ?? 0,
                'snapshots_pending' => $payload['snapshots_pending'] ?? 0,
                'at' => now()->toIso8601String(),
            ]);

            return response()
                ->json($this->attachDebug($request, $user, $payload))
                ->header('Cache-Control', 'private, max-age=60');
        } catch (\Throwable $e) {
            report($e);
            MonitoringV2DebugLog::error($debugSession, 'http.list.fail', [
                'message' => $e->getMessage(),
                'ms' => (int) round((microtime(true) - $t0) * 1000),
            ]);

            return response()->json([
                'message' => __('Monitoring v2 list load error'),
            ], 500);
        }
    }

    /**
     * Тренд среднего ТОП-10 по портфелю (30/60/90/180/365 дней).
     */
    public function portfolioTop10Trend(Request $request, MonitoringPortfolioTop10TrendService $trend): JsonResponse
    {
        App::setLocale('ru');

        /** @var User $user */
        $user = Auth::user();

        $days = (int) $request->input('days', 90);
        $range = (string) $request->input('range', 'weeks');
        $projectIds = $request->input('project_ids', []);
        if (!is_array($projectIds)) {
            $projectIds = [];
        }

        $debugSession = $this->debugSession($request, $user);
        $t0 = microtime(true);

        try {
            MonitoringV2DebugLog::info($debugSession, 'http.trend.start', [
                'days' => $days,
                'range' => $range,
                'projects' => count($projectIds),
            ]);
            set_time_limit(max(300, (int) ini_get('max_execution_time')));
            @ini_set('memory_limit', '512M');

            $refresh = $request->boolean('refresh');
            if ($request->boolean('partial')) {
                MonitoringV2DebugLog::info($debugSession, 'http.trend.legacy_partial', [
                    'projects' => count($projectIds),
                ]);
            }
            $payload = $trend->seriesForUser($user, $projectIds, $days, $range, $refresh);

            $ms = (int) round((microtime(true) - $t0) * 1000);
            MonitoringV2DebugLog::info($debugSession, 'http.trend.done', [
                'ms' => $ms,
                'partial' => $request->boolean('partial'),
                'points' => count($payload['labels'] ?? []),
                'projects_used' => $payload['projects_used'] ?? null,
                'chunk_ms' => $payload['chunk_ms'] ?? null,
                'from_cache' => $payload['from_cache'] ?? null,
            ]);

            return response()->json($this->attachDebug($request, $user, $payload));
        } catch (\Throwable $e) {
            report($e);
            MonitoringV2DebugLog::error($debugSession, 'http.trend.fail', [
                'message' => $e->getMessage(),
                'ms' => (int) round((microtime(true) - $t0) * 1000),
            ]);

            return response()->json($this->attachDebug($request, $user, [
                'labels' => [],
                'values' => [],
                'empty' => true,
                'error' => true,
                'message' => __('Monitoring v2 portfolio trend error'),
            ]), 500);
        }
    }

    /**
     * Порционный пересчёт снимков метрик (не блокирует list).
     */
    public function fillSnapshots(Request $request, MonitoringProjectListSerializer $serializer): JsonResponse
    {
        App::setLocale('ru');

        /** @var User $user */
        $user = Auth::user();

        $limit = (int) $request->input('limit', MonitoringProjectListSerializer::FILL_SNAPSHOT_BATCH);
        $force = $request->boolean('force');

        $debugSession = $this->debugSession($request, $user);
        $t0 = microtime(true);

        try {
            MonitoringV2DebugLog::info($debugSession, 'http.snapshots.fill.start', [
                'force' => $force,
                'limit' => $limit,
            ]);
            set_time_limit(max(90, (int) ini_get('max_execution_time')));

            $result = $serializer->fillMissingSnapshots($user, $limit, $force);
            $ms = (int) round((microtime(true) - $t0) * 1000);

            MonitoringV2DebugLog::info($debugSession, 'http.snapshots.fill.done', array_merge($result, ['ms' => $ms]));
            MonitoringV2DebugLog::setState($debugSession, [
                'last' => 'snapshots.fill',
                'ms' => $ms,
                'pending' => $result['pending'] ?? 0,
                'rebuilt' => $result['rebuilt'] ?? 0,
                'at' => now()->toIso8601String(),
            ]);

            return response()->json($this->attachDebug($request, $user, $result));
        } catch (\Throwable $e) {
            report($e);
            MonitoringV2DebugLog::error($debugSession, 'http.snapshots.fill.fail', [
                'message' => $e->getMessage(),
                'ms' => (int) round((microtime(true) - $t0) * 1000),
            ]);

            return response()->json([
                'message' => __('Monitoring v2 list load error'),
            ], 500);
        }
    }

    /**
     * Порционно скачать и сохранить фавиконки (фон для списка v2).
     */
    public function fillFavicons(Request $request, ProjectFaviconService $service): JsonResponse
    {
        App::setLocale('ru');

        /** @var User $user */
        $user = Auth::user();

        $limit = (int) $request->input('limit', 3);
        $force = $request->boolean('force');
        $projectId = (int) $request->input('project_id');

        $debugSession = $this->debugSession($request, $user);
        $t0 = microtime(true);

        try {
            MonitoringV2DebugLog::info($debugSession, 'http.favicons.fill.start', [
                'limit' => $limit,
                'force' => $force,
                'project_id' => $projectId > 0 ? $projectId : null,
            ]);
            set_time_limit(max(90, (int) ini_get('max_execution_time')));

            if ($projectId > 0) {
                /** @var MonitoringProject|null $project */
                $project = $user->monitoringProjects()->find($projectId);
                if ($project === null) {
                    return response()->json(['message' => __('Not found')], 404);
                }

                $portfolio = $user->monitoringProjectsDataTable()
                    ->select(
                        'monitoring_projects.id',
                        'monitoring_projects.url',
                        'monitoring_projects.favicon_path',
                        'monitoring_projects.favicon_host',
                        'monitoring_projects.favicon_updated_at'
                    )
                    ->get();

                $propagateResult = $service->propagateMissingFromBatch(
                    $service->projectsWithSameHost($project, $portfolio)
                );
                $project->refresh();

                $ok = !$service->needsRefresh($project) || $service->refresh($project, true, false);
                if ($ok || $propagateResult['count'] > 0) {
                    MonitoringProjectListSerializer::forgetCacheForUser((int) $user->id);
                }

                $donorsByHost = $service->buildHostDonorMap($portfolio);
                $updates = $propagateResult['updates'];
                $payload = $service->faviconUpdatePayload($project, $donorsByHost);
                if ($payload['favicon_src_project_id'] !== null) {
                    $found = false;
                    foreach ($updates as $u) {
                        if ((int) ($u['id'] ?? 0) === $projectId) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $updates[] = $payload;
                    }
                }

                $result = [
                    'rebuilt' => $ok ? 1 : 0,
                    'pending' => 0,
                    'propagated' => $propagateResult['count'],
                    'updates' => $updates,
                ];
            } else {
                $projects = $user->monitoringProjectsDataTable()
                    ->select(
                        'monitoring_projects.id',
                        'monitoring_projects.url',
                        'monitoring_projects.favicon_path',
                        'monitoring_projects.favicon_host',
                        'monitoring_projects.favicon_updated_at'
                    )
                    ->orderBy('monitoring_projects.name')
                    ->get();

                $result = $service->fillMissingBatch($projects, $limit, $force, (int) $user->id);

                if (($result['rebuilt'] ?? 0) > 0 || (int) ($result['propagated'] ?? 0) > 0) {
                    MonitoringProjectListSerializer::forgetCacheForUser((int) $user->id);
                }
            }

            $ms = (int) round((microtime(true) - $t0) * 1000);
            MonitoringV2DebugLog::info($debugSession, 'http.favicons.fill.done', array_merge($result, ['ms' => $ms]));

            return response()->json($this->attachDebug($request, $user, $result));
        } catch (\Throwable $e) {
            report($e);
            MonitoringV2DebugLog::error($debugSession, 'http.favicons.fill.fail', [
                'message' => $e->getMessage(),
                'ms' => (int) round((microtime(true) - $t0) * 1000),
            ]);

            return response()->json([
                'message' => __('Monitoring v2 list load error'),
            ], 500);
        }
    }

    /**
     * PNG 128×128: сохранённая фавиконка проекта или on-demand загрузка.
     */
    public function favicon(Request $request, ProjectFaviconService $service, ProjectFaviconFetcher $fetcher): Response
    {
        $headers = [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=2592000, immutable',
        ];

        $projectId = (int) $request->query('project');
        $force = $request->boolean('refresh');

        if ($projectId > 0) {
            /** @var User $user */
            $user = Auth::user();
            /** @var MonitoringProject|null $project */
            $project = $user->monitoringProjects()->find($projectId);
            if ($project === null) {
                return response('', 404);
            }

            if ($force || $service->needsRefresh($project)) {
                $service->refresh($project, $force, false);
            }

            $path = $service->absolutePath($project);
            if ($path !== null) {
                return response()->file($path, $headers);
            }

            return response('', 404);
        }

        // ?host= — не качаем в HTTP (список v2 догружает через favicons/fill).
        return response('', 404);
    }

    /**
     * KPI и регионы проекта для модалки «Статистика для клиента».
     */
    public function projectStats(Request $request): JsonResponse
    {
        App::setLocale('ru');

        $project = $this->findAccessibleProject((int) $request->input('projectId'));

        $regionId = $request->filled('regionId') ? (int) $request->input('regionId') : null;
        $summary = MonitoringProjectPageSummary::build($project, $regionId);

        if ($request->boolean('summaryOnly')) {
            return response()->json([
                'summary' => $summary,
                'share' => $this->shareStateForProject($project),
            ]);
        }

        $payload = MonitoringProjectPublicStats::buildForExport($project);
        $payload['summary'] = $summary;
        $payload['share'] = $this->shareStateForProject($project);

        return response()->json($payload);
    }

    public function createPublicShare(Request $request): JsonResponse
    {
        App::setLocale('ru');

        $project = $this->findAccessibleProject((int) $request->input('projectId'));

        if (!MonitoringPublicShare::tableAvailable()) {
            return response()->json([
                'success' => false,
                'message' => __('Monitoring public share unavailable'),
                'code' => 503,
            ], 503);
        }

        $ttlDays = MonitoringPublicShareTtl::normalize($request->input('ttl_days', 30));
        $report = MonitoringProjectPublicStats::buildForExport($project);
        $meta = $this->buildReportMeta($project);
        $share = MonitoringPublicShare::issueForProject(
            (int) Auth::id(),
            $project->id,
            $report,
            $meta,
            $ttlDays
        );

        if ($share === null) {
            return response()->json([
                'success' => false,
                'message' => __('Public link could not be created.'),
                'code' => 500,
            ], 500);
        }

        MonitoringProjectListSerializer::forgetCacheForUser((int) Auth::id());

        return response()->json([
            'success' => true,
            'message' => __('Public link created'),
            'url' => $share->publicUrl(),
            'ttl_days' => $ttlDays,
            'expires_at' => $share->expires_at ? $share->expires_at->format('d.m.Y H:i') : null,
            'expires_label' => $share->expiresLabel(),
        ]);
    }

    public function revokePublicShare(Request $request): JsonResponse
    {
        App::setLocale('ru');

        $project = $this->findAccessibleProject((int) $request->input('projectId'));

        if (!MonitoringPublicShare::tableAvailable()) {
            return response()->json([
                'success' => false,
                'message' => __('Public sharing is temporarily unavailable.'),
                'code' => 503,
            ], 503);
        }

        MonitoringPublicShare::revokeForProject((int) Auth::id(), $project->id);

        MonitoringProjectListSerializer::forgetCacheForUser((int) Auth::id());

        return response()->json([
            'success' => true,
            'message' => __('Public link revoked'),
        ]);
    }

    protected function findAccessibleProject(int $id): MonitoringProject
    {
        /** @var User $user */
        $user = Auth::user();

        return $user->monitoringProjects()->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildReportMeta(MonitoringProject $project): array
    {
        return [
            'generated_at' => now()->format('d.m.Y H:i'),
            'source_label' => trim($project->name . ' · ' . $project->url),
            'version' => config('cabinet-monitoring.version'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function shareStateForProject(MonitoringProject $project): array
    {
        if (!MonitoringPublicShare::tableAvailable()) {
            return [
                'available' => false,
                'url' => null,
                'expires_at' => null,
                'expires_label' => null,
                'ttl_days' => 30,
                'ttl_options' => [],
            ];
        }

        $share = MonitoringPublicShare::activeForProject($project->id, (int) Auth::id());

        return [
            'available' => true,
            'url' => $share ? $share->publicUrl() : null,
            'expires_at' => $share && $share->expires_at ? $share->expires_at->format('d.m.Y H:i') : null,
            'expires_label' => $share ? $share->expiresLabel() : null,
            'ttl_days' => $share ? $share->ttlDaysFromPayload() : 30,
            'ttl_options' => MonitoringPublicShareTtl::labelsForUi(),
        ];
    }

    private function isMonitoringDebugUser(User $user): bool
    {
        return $user->hasAnyRole(['Super Admin', 'admin']);
    }

    private function debugSession(Request $request, User $user): string
    {
        if (! $this->isMonitoringDebugUser($user)) {
            return '';
        }

        $session = trim((string) $request->input('debug_session', ''));
        if ($session === '' || strlen($session) > 80) {
            return '';
        }

        return preg_match('/^[a-zA-Z0-9._:-]+$/', $session) ? $session : '';
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function attachDebug(Request $request, User $user, array $payload): array
    {
        $session = $this->debugSession($request, $user);
        if ($session === '') {
            return $payload;
        }

        $payload['debug_admin'] = true;
        $payload['debug_log'] = MonitoringV2DebugLog::get($session);
        $payload['debug_state'] = MonitoringV2DebugLog::getState($session);

        return $payload;
    }
}
