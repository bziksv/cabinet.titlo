<?php

namespace App\Http\Controllers;

use App\Classes\Monitoring\MonitoringProjectListSerializer;
use App\Classes\Monitoring\ProjectFaviconFetcher;
use App\Classes\Monitoring\ProjectFaviconService;
use App\MonitoringProject;
use App\MonitoringV2UserPreference;
use App\Support\MonitoringV2DebugLog;
use App\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
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

    public function index(): View
    {
        App::setLocale('ru');

        /** @var User $user */
        $user = Auth::user();

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

        $limit = (int) $request->input('limit', 2);
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

                $result = $service->fillMissingBatch($projects, $limit, $force);

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

            if ($force || $service->absolutePath($project) === null) {
                $service->refresh($project, $force, true);
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
