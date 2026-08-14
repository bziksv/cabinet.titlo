<?php

namespace App\Classes\Monitoring;

use App\MonitoringDataTableColumnsProject;
use App\MonitoringProject;
use App\MonitoringPublicShare;
use App\MonitoringUserStatus;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Быстрый JSON для списка проектов v2: без Blade, без 86× team permissions.
 */
class MonitoringProjectListSerializer
{
    private const CACHE_TTL_SECONDS = 120;

    /** Смена схемы ответа — сброс старого кэша с пустыми снимками. */
    private const CACHE_KEY_SUFFIX = 's21';

    /**
     * Снимки до этого момента могли быть посчитаны через addLastPositions
     * и «залипнуть» на архивном срезе — принудительный пересчёт через MAX(id).
     */
    private const METRICS_CALC_CUTOVER = '2026-08-14 11:00:00';

    /** Фоновая догрузка метрик — отдельный endpoint, не в list. */
    /** Один проект за HTTP — иначе таймаут 90 с на тяжёлых ProjectData. */
    public const FILL_SNAPSHOT_BATCH = 1;

    /** Кнопка «Обновить» — пересчёт за один fill-запрос. */
    public const FILL_SNAPSHOT_FORCE_BATCH = 2;

    /** Макс. время одного fill-запроса (мс), затем отдаём частичный результат. */
    private const FILL_SNAPSHOT_WALL_MS = 22000;

    /** Кнопка «Обновить» через list (legacy) — максимум за запрос. */
    private const FORCE_REBUILD_LIMIT = 20;

    /** @var Collection<int, MonitoringUserStatus> */
    private $statusById;

    /** @var array<string, bool> */
    private $authPermissions = [];

    public function forUser(User $user, bool $useCache = true, bool $rebuildSnapshots = false): array
    {
        if ($rebuildSnapshots) {
            return $this->buildPayload($user, true);
        }

        if (!$useCache) {
            return $this->buildPayload($user, false);
        }

        $key = $this->cacheKeyForUser($user);

        return Cache::remember($key, self::CACHE_TTL_SECONDS, function () use ($user) {
            return $this->buildPayload($user, false);
        });
    }

    /**
     * Порционный пересчёт снимков (отдельный HTTP, не блокирует list).
     *
     * @return array{
     *   rebuilt: int,
     *   pending: int,
     *   updates: array<int, array<string, mixed>>,
     *   timed_out?: bool,
     *   wall_ms?: int,
     *   can_continue?: bool,
     *   without_positions?: array<int, array{id: int, name: string}>
     * }
     */
    public function fillMissingSnapshots(User $user, int $limit, bool $force = false): array
    {
        $limit = max(1, min($force ? self::FILL_SNAPSHOT_FORCE_BATCH : self::FILL_SNAPSHOT_BATCH, $limit));

        $projects = $user->monitoringProjectsDataTable()
            ->select('monitoring_projects.id', 'monitoring_projects.name')
            ->orderBy('monitoring_projects.name')
            ->get();

        if ($projects->isEmpty()) {
            return [
                'rebuilt' => 0,
                'pending' => 0,
                'updates' => [],
                'can_continue' => false,
                'without_positions' => [],
            ];
        }

        $projectIds = $projects->pluck('id')->all();
        $snapshots = $this->loadSnapshots($projectIds);
        $latestByProject = MonitoringLatestPositions::maxCreatedAtByProjectIds($projectIds);
        if ($force) {
            Cache::forget('monitoring_v2_snap_skip:' . (int) $user->id);
        }
        $skipIds = $this->snapshotFillSkipIds((int) $user->id);
        $toRefresh = [];

        foreach ($projects as $project) {
            $pid = (int) $project->id;
            if (!$force && isset($skipIds[$pid])) {
                continue;
            }
            $snap = $snapshots->get($project->id);
            if ($force || $this->snapshotNeedsRefresh($snap, $latestByProject->get($project->id))) {
                $toRefresh[] = $project;
            }
        }

        $withoutPositions = $this->projectsWithoutPositionsList($projects, $latestByProject);

        if ($toRefresh === []) {
            return [
                'rebuilt' => 0,
                'pending' => 0,
                'updates' => [],
                'can_continue' => false,
                'without_positions' => $withoutPositions,
            ];
        }

        set_time_limit(max(60, (int) ini_get('max_execution_time')));

        $snapshotService = app(MonitoringProjectSnapshotService::class);
        $rebuilt = 0;
        $updates = [];
        $timedOut = false;
        $wallStart = microtime(true);

        foreach (array_slice($toRefresh, 0, $limit) as $project) {
            if ((microtime(true) - $wallStart) * 1000 >= self::FILL_SNAPSHOT_WALL_MS) {
                $timedOut = true;
                break;
            }
            $full = null;
            try {
                $projectStart = microtime(true);
                $full = MonitoringProject::query()->find($project->id);
                if ($full === null) {
                    continue;
                }
                $snapshotService->refreshProject($full);
                $snap = MonitoringDataTableColumnsProject::query()
                    ->where('monitoring_project_id', $project->id)
                    ->first();
                $updates[] = $this->metricsFromSnapshot((int) $project->id, $snap);
                $rebuilt++;
                if ((microtime(true) - $projectStart) * 1000 >= self::FILL_SNAPSHOT_WALL_MS) {
                    $timedOut = true;
                    break;
                }
            } catch (\Throwable $e) {
                report($e);
                $pid = (int) $project->id;
                $hasPositions = $latestByProject->get($project->id) !== null
                    && $latestByProject->get($project->id) !== '';

                if (!$hasPositions) {
                    // Нет ни одной позиции в БД — пишем пустой снимок, чтобы не крутить forever.
                    if ($full === null) {
                        $full = MonitoringProject::query()->find($project->id);
                    }
                    if ($full) {
                        $snap = $this->storeEmptySnapshot($full);
                        $updates[] = $this->metricsFromSnapshot($pid, $snap);
                        $rebuilt++;
                    }
                } else {
                    $this->rememberSnapshotFillSkip((int) $user->id, $pid);
                }
            }
        }

        if ($rebuilt > 0) {
            self::forgetCacheForUser((int) $user->id);
        }

        $snapshots = $this->loadSnapshots($projectIds);
        $latestByProject = MonitoringLatestPositions::maxCreatedAtByProjectIds($projectIds);
        $pending = $this->countPendingSnapshots($projects, $snapshots, $latestByProject);
        $skipIds = $this->snapshotFillSkipIds((int) $user->id);
        $refreshable = 0;
        foreach ($projects as $project) {
            $pid = (int) $project->id;
            if (isset($skipIds[$pid])) {
                continue;
            }
            if ($this->snapshotNeedsRefresh($snapshots->get($project->id), $latestByProject->get($project->id))) {
                $refreshable++;
            }
        }

        return [
            'rebuilt' => $rebuilt,
            'pending' => $pending,
            'updates' => $updates,
            'timed_out' => $timedOut,
            'wall_ms' => (int) round((microtime(true) - $wallStart) * 1000),
            'can_continue' => $refreshable > 0,
            'without_positions' => $this->projectsWithoutPositionsList($projects, $latestByProject),
        ];
    }

    /**
     * @param \Illuminate\Support\Collection<int, MonitoringProject> $projects
     * @param \Illuminate\Support\Collection<int, mixed> $latestByProject
     * @return array<int, array{id: int, name: string}>
     */
    private function projectsWithoutPositionsList($projects, $latestByProject): array
    {
        $out = [];
        foreach ($projects as $project) {
            $latest = $latestByProject->get($project->id);
            if ($latest !== null && $latest !== '') {
                continue;
            }
            $name = trim((string) ($project->name ?? ''));
            if ($name === '') {
                $name = '#' . $project->id;
            }
            $out[] = [
                'id' => (int) $project->id,
                'name' => $name,
            ];
        }

        return $out;
    }

    private function storeEmptySnapshot(MonitoringProject $project): MonitoringDataTableColumnsProject
    {
        $words = (int) $project->keywords()->count();

        return MonitoringDataTableColumnsProject::updateOrCreate(
            ['monitoring_project_id' => $project->id],
            [
                'words' => $words,
                'middle' => 0,
                'top1' => 0,
                'top3' => 0,
                'top5' => 0,
                'top10' => 0,
                'top30' => 0,
                'top100' => 0,
                'mastered' => 0,
                'mastered_percent' => null,
            ]
        );
    }

    /**
     * @return array<int, true>
     */
    private function snapshotFillSkipIds(int $userId): array
    {
        $raw = Cache::get('monitoring_v2_snap_skip:' . $userId, []);
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $id) {
            $out[(int) $id] = true;
        }

        return $out;
    }

    private function rememberSnapshotFillSkip(int $userId, int $projectId): void
    {
        $key = 'monitoring_v2_snap_skip:' . $userId;
        $raw = Cache::get($key, []);
        if (!is_array($raw)) {
            $raw = [];
        }
        $raw[] = $projectId;
        $raw = array_values(array_unique(array_map('intval', $raw)));
        Cache::put($key, $raw, 600);
    }

    public static function forgetCacheForUser(int $userId): void
    {
        $verKey = 'monitoring_v2_list_ver:' . $userId;
        Cache::put($verKey, (int) Cache::get($verKey, 0) + 1, 86400);
    }

    private function cacheVersion(int $userId): int
    {
        return (int) Cache::get('monitoring_v2_list_ver:' . $userId, 0);
    }

    private function cacheKeyForUser(User $user): string
    {
        return sprintf(
            'monitoring_v2_list:%d:v%d:%s',
            $user->id,
            $this->cacheVersion($user->id),
            self::CACHE_KEY_SUFFIX
        );
    }

    private function buildPayload(User $user, bool $rebuildSnapshots = false): array
    {
        $this->statusById = MonitoringUserStatus::all()->keyBy('id');
        $this->authPermissions = $this->resolveAuthPermissions($user);

        $projects = $user->monitoringProjectsDataTable()
            ->select(
                'monitoring_projects.id',
                'monitoring_projects.url',
                'monitoring_projects.name',
                'monitoring_projects.budget',
                'monitoring_projects.favicon_path',
                'monitoring_projects.favicon_host',
                'monitoring_projects.favicon_updated_at'
            )
            ->with([
                'users' => static function ($query) {
                    $query->select('users.id', 'users.name', 'users.last_name', 'users.image');
                },
                'users.roles:id,name,title',
                'searchengines' => static function ($query) {
                    $query->select(
                        'id',
                        'monitoring_project_id',
                        'engine',
                        'lr',
                        'auto_update',
                        'time',
                        'weekdays',
                        'monthday',
                        'day'
                    )->with('location:id,lr,name');
                },
            ])
            ->orderBy('monitoring_projects.name')
            ->get();

        if ($projects->isEmpty()) {
            return [
                'total' => 0,
                'projects' => [],
                'snapshots_pending' => 0,
                'snapshots_rebuilt' => 0,
            ];
        }

        $faviconService = app(ProjectFaviconService::class);
        $faviconDonorsByHost = $faviconService->buildHostDonorMap($projects);
        if (!$rebuildSnapshots) {
            try {
                $propagateResult = $faviconService->propagateMissingFromBatch($projects);
                if ($propagateResult['count'] > 0) {
                    $faviconDonorsByHost = $faviconService->buildHostDonorMap($projects);
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $snapshots = $this->loadSnapshots($projects->pluck('id')->all());
        $latestByProject = MonitoringLatestPositions::maxCreatedAtByProjectIds($projects->pluck('id')->all());
        $snapshotsRebuilt = $this->ensureSnapshots($projects, $snapshots, $rebuildSnapshots);
        if ($snapshotsRebuilt > 0) {
            $snapshots = $this->loadSnapshots($projects->pluck('id')->all());
            $latestByProject = MonitoringLatestPositions::maxCreatedAtByProjectIds($projects->pluck('id')->all());
        }

        $items = [];
        $activeShares = $this->loadActivePublicShares((int) $user->id, $projects->pluck('id')->all());
        foreach ($projects as $project) {
            $items[] = $this->serializeProject(
                $project,
                $snapshots->get($project->id),
                $user,
                $faviconDonorsByHost,
                $activeShares
            );
        }

        $pending = $this->countPendingSnapshots($projects, $snapshots, $latestByProject);

        return [
            'total' => count($items),
            'projects' => $items,
            'cached_at' => now()->toIso8601String(),
            'snapshots_rebuilt' => $snapshotsRebuilt,
            'snapshots_pending' => $pending,
            'without_positions' => $this->projectsWithoutPositionsList($projects, $latestByProject),
        ];
    }

    /**
     * @param int[] $projectIds
     * @return \Illuminate\Support\Collection<int, MonitoringDataTableColumnsProject>
     */
    private function loadSnapshots(array $projectIds)
    {
        if ($projectIds === []) {
            return collect();
        }

        return MonitoringDataTableColumnsProject::query()
            ->whereIn('monitoring_project_id', $projectIds)
            ->get()
            ->keyBy('monitoring_project_id');
    }

    /**
     * Пересчёт снимков без метрик (как cron ProjectData). Лимит — чтобы не убить HTTP.
     *
     * @param \Illuminate\Support\Collection<int, MonitoringProject> $projects
     */
    private function ensureSnapshots($projects, $snapshots, bool $forceAll): int
    {
        $snapshotService = app(MonitoringProjectSnapshotService::class);
        $toRefresh = [];

        if (!$forceAll) {
            return 0;
        }

        foreach ($projects as $project) {
            $toRefresh[] = $project;
        }

        if ($toRefresh === []) {
            return 0;
        }

        $limit = self::FORCE_REBUILD_LIMIT;
        if (count($toRefresh) > $limit) {
            set_time_limit(max(120, (int) ini_get('max_execution_time')));
        }

        $rebuilt = 0;
        foreach (array_slice($toRefresh, 0, $limit) as $project) {
            try {
                $snapshotService->refreshProject($project);
                $rebuilt++;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $rebuilt;
    }

    /**
     * @return array<string, mixed>
     */
    private function metricsFromSnapshot(int $projectId, ?MonitoringDataTableColumnsProject $snap): array
    {
        return [
            'id' => $projectId,
            'words' => $snap ? $snap->words : null,
            'middle' => $snap ? $snap->middle : null,
            'top3' => $snap ? $snap->top3 : null,
            'diff_top3' => $snap ? $snap->diff_top3 : null,
            'top5' => $snap ? $snap->top5 : null,
            'diff_top5' => $snap ? $snap->diff_top5 : null,
            'top10' => $snap ? $snap->top10 : null,
            'diff_top10' => $snap ? $snap->diff_top10 : null,
            'top30' => $snap ? $snap->top30 : null,
            'diff_top30' => $snap ? $snap->diff_top30 : null,
            'top100' => $snap ? $snap->top100 : null,
            'diff_top100' => $snap ? $snap->diff_top100 : null,
            'mastered' => $snap ? $snap->mastered : null,
            'mastered_percent' => $snap ? $snap->mastered_percent : null,
            'snapshot_at' => $snap && $snap->updated_at ? $snap->updated_at->toIso8601String() : null,
        ];
    }

    private function snapshotIsEmpty(?MonitoringDataTableColumnsProject $snap): bool
    {
        if ($snap === null) {
            return true;
        }

        return $snap->top10 === null
            && $snap->top30 === null
            && $snap->words === null
            && $snap->middle === null;
    }

    private function snapshotNeedsRefresh(?MonitoringDataTableColumnsProject $snap, $latestPositionAt = null): bool
    {
        if ($this->snapshotIsEmpty($snap)) {
            return true;
        }

        if (!$snap->updated_at) {
            return true;
        }

        if ($snap->updated_at->lt(Carbon::parse(self::METRICS_CALC_CUTOVER))) {
            return true;
        }

        if ($latestPositionAt !== null && $latestPositionAt !== '') {
            try {
                if (Carbon::parse($latestPositionAt)->gt($snap->updated_at)) {
                    return true;
                }
            } catch (\Throwable $e) {
                // ignore parse errors — fallback to age check
            }
        }

        return $snap->updated_at->lt(now()->subHours(24));
    }

    /**
     * @param \Illuminate\Support\Collection<int, MonitoringProject> $projects
     * @param \Illuminate\Support\Collection<int, MonitoringDataTableColumnsProject> $snapshots
     * @param \Illuminate\Support\Collection<int, mixed>|null $latestByProject
     */
    private function countPendingSnapshots($projects, $snapshots, $latestByProject = null): int
    {
        $pending = 0;
        foreach ($projects as $project) {
            $latestAt = $latestByProject ? $latestByProject->get($project->id) : null;
            if ($this->snapshotNeedsRefresh($snapshots->get($project->id), $latestAt)) {
                $pending++;
            }
        }

        return $pending;
    }

    /**
     * Права для меню — один раз в контексте global team (список не требует team=project).
     *
     * @return array<string, bool>
     */
    private function resolveAuthPermissions(User $auth): array
    {
        apply_global_team_permissions();

        return [
            'add_user' => $auth->can('add_user_to_project_monitoring'),
            'export' => $auth->can('export_report_monitoring'),
            'create_query' => $auth->can('create_query_monitoring'),
            'edit_project' => $auth->can('edit_project_monitoring'),
            'leave' => $auth->can('leave_project_monitoring'),
            'detach_user' => $auth->can('delete_user_from_project_monitoring'),
            'change_status' => $auth->can('change_user_status_project_monitoring'),
        ];
    }

    /**
     * @param array<string, MonitoringProject> $faviconDonorsByHost
     * @param array<int, array{active: bool, url: string, expires_label: string}> $activeShares
     */
    private function serializeProject(
        MonitoringProject $project,
        ?MonitoringDataTableColumnsProject $snap,
        User $auth,
        array $faviconDonorsByHost = [],
        array $activeShares = []
    ): array {
        $engineRegions = $this->buildEngineRegions($project->searchengines);
        $engines = array_keys($engineRegions);

        $perms = $this->authPermissions;
        $authId = $auth->id;

        $users = $project->users->map(function ($member) use ($project, $perms, $authId) {
            $statusId = (int) ($member->pivot->status ?? 0);
            $status = $this->statusById->get($statusId);
            $role = $member->roles->first();
            $statusCode = $status ? (string) $status->code : '';
            $statusName = $status ? (string) $status->name : __('Without status');
            if ($statusCode === '' || $statusCode === 'EMPTY') {
                $statusName = __('Without status');
            }

            return [
                'id' => $member->id,
                'name' => trim($member->name . ' ' . $member->last_name),
                'initials' => self::initials($member->name, $member->last_name),
                'image' => $member->image,
                'is_admin' => $role && $role->name === 'admin_monitoring',
                'is_project_admin' => (int) ($member->pivot->admin ?? 0) >= 1,
                'status_code' => $statusCode,
                'status_name' => $statusName,
                'role_title' => $role ? ($role->title ?? '') : '',
                'can_detach' => $perms['detach_user'] && $member->id !== $authId,
                'can_change_status' => $perms['change_status'],
                'project_id' => $project->id,
            ];
        })->values()->all();

        $faviconService = app(ProjectFaviconService::class);
        $faviconMeta = $faviconService->faviconDisplayMeta($project, $faviconDonorsByHost);

        return [
            'id' => $project->id,
            'url' => $project->url,
            'name' => $project->name,
            'favicon_src_project_id' => $faviconMeta ? $faviconMeta['id'] : null,
            'favicon_v' => $faviconMeta ? $faviconMeta['v'] : null,
            'favicon_url' => $faviconService->displayUrl($project, $faviconDonorsByHost),
            'budget' => $project->budget,
            'words' => $snap ? $snap->words : null,
            'middle' => $snap ? $snap->middle : null,
            'top3' => $snap ? $snap->top3 : null,
            'diff_top3' => $snap ? $snap->diff_top3 : null,
            'top5' => $snap ? $snap->top5 : null,
            'diff_top5' => $snap ? $snap->diff_top5 : null,
            'top10' => $snap ? $snap->top10 : null,
            'diff_top10' => $snap ? $snap->diff_top10 : null,
            'top30' => $snap ? $snap->top30 : null,
            'diff_top30' => $snap ? $snap->diff_top30 : null,
            'top100' => $snap ? $snap->top100 : null,
            'diff_top100' => $snap ? $snap->diff_top100 : null,
            'mastered' => $snap ? $snap->mastered : null,
            'mastered_percent' => $snap ? $snap->mastered_percent : null,
            'snapshot_at' => $snap && $snap->updated_at ? $snap->updated_at->toIso8601String() : null,
            'engines' => $engines,
            'engine_regions' => $engineRegions,
            'users' => $users,
            'actions' => $this->buildActions($project, $auth, $perms),
            'public_share' => $activeShares[(int) $project->id] ?? [
                'active' => false,
                'url' => null,
                'expires_label' => null,
            ],
        ];
    }

    /**
     * Активные публичные ссылки текущего пользователя по проектам списка.
     *
     * @param int[] $projectIds
     *
     * @return array<int, array{active: bool, url: string, expires_label: string}>
     */
    private function loadActivePublicShares(int $userId, array $projectIds): array
    {
        if (!MonitoringPublicShare::tableAvailable() || $projectIds === []) {
            return [];
        }

        $out = [];
        $shares = MonitoringPublicShare::query()
            ->where('user_id', $userId)
            ->whereIn('monitoring_project_id', $projectIds)
            ->active()
            ->orderByDesc('id')
            ->get();

        foreach ($shares as $share) {
            $pid = (int) $share->monitoring_project_id;
            if (isset($out[$pid])) {
                continue;
            }
            $out[$pid] = [
                'active' => true,
                'url' => $share->publicUrl(),
                'expires_label' => $share->expiresLabel(),
            ];
        }

        return $out;
    }

    /**
     * Регионы и расписание съёма по ПС (тултип и подпись в списке v2).
     *
     * @param \Illuminate\Support\Collection<int, \App\MonitoringSearchengine> $searchengines
     *
     * @return array<string, list<array{name: string, schedule: string, schedule_short: string, manual: bool, mode: string}>>
     */
    private function buildEngineRegions($searchengines): array
    {
        $formatter = app(MonitoringSearchengineScheduleFormatter::class);
        $out = [];

        foreach ($searchengines as $se) {
            $key = strtolower(trim((string) $se->engine));
            if ($key === '') {
                continue;
            }
            if (!isset($out[$key])) {
                $out[$key] = [];
            }

            $lr = trim((string) ($se->lr ?? ''));
            if ($lr === '' && ($se->location === null || trim((string) $se->location->name) === '')) {
                continue;
            }

            $name = MonitoringLocationLabel::displayName(
                (string) $se->engine,
                $lr,
                $se->location ? (string) $se->location->name : null
            );

            $schedule = $formatter->describe($se);
            $out[$key][] = [
                'engine' => $key,
                'lr' => $lr,
                'name' => $name,
                'schedule' => $schedule['label'],
                'schedule_short' => $schedule['short'],
                'manual' => $schedule['manual'],
                'mode' => $schedule['mode'],
            ];
        }

        foreach ($out as $engine => $regions) {
            usort($regions, static function (array $a, array $b): int {
                return strnatcasecmp($a['name'], $b['name']);
            });
            $out[$engine] = $regions;
        }

        ksort($out);

        return $out;
    }

    private static function initials(?string $first, ?string $last): string
    {
        $a = mb_substr(trim((string) $first), 0, 1);
        $b = mb_substr(trim((string) $last), 0, 1);

        return mb_strtoupper($a . $b) ?: '?';
    }

    /**
     * @param array<string, bool> $perms
     */
    private function buildActions(MonitoringProject $project, User $auth, array $perms): array
    {
        $id = $project->id;
        $actions = [
            [
                'kind' => 'link',
                'href' => route('monitoring.show', $id),
                'label' => 'Позиции и ключи',
                'icon' => 'fas fa-chart-line',
            ],
            [
                'kind' => 'link',
                'href' => route('monitoring.show', $id),
                'label' => 'Открыть проект',
                'icon' => 'far fa-folder-open',
            ],
        ];

        if ($perms['add_user']) {
            $actions[] = [
                'kind' => 'add_user',
                'id' => $id,
                'label' => 'Добавить пользователя',
                'icon' => 'far fa-user',
            ];
        }

        $actions[] = [
            'kind' => 'copy',
            'href' => route('monitoring.copy', $id),
            'label' => 'Копировать проект',
            'icon' => 'far fa-copy',
        ];

        $actions[] = [
            'kind' => 'public_share',
            'id' => $id,
            'label' => (string) __('Monitoring v2 public share menu'),
            'icon' => 'bi bi-share',
        ];

        if ($perms['export']) {
            $actions[] = [
                'kind' => 'modal',
                'modal' => 'export-edit',
                'id' => $id,
                'label' => 'Экспорт отчета',
                'icon' => 'fas fa-file-download',
            ];
        }

        if ($perms['create_query']) {
            $actions[] = [
                'kind' => 'modal',
                'modal' => 'create_keywords',
                'id' => $id,
                'label' => 'Добавить запрос',
                'icon' => 'far fa-plus-square',
            ];
        }

        if ($perms['edit_project']) {
            $actions[] = [
                'kind' => 'link',
                'href' => route('monitoring.create') . '#id=' . $id,
                'label' => 'Изменить проект',
                'icon' => 'fas fa-edit',
            ];
        }

        $actions[] = [
            'kind' => 'link',
            'href' => route('groups.index', $id),
            'label' => 'Группы проекта',
            'icon' => 'far fa-folder',
        ];

        // «Покинуть» — только если есть другие участники. Единственный создатель
        // не может «выйти», оставляя сироту: ему нужен «Удалить проект».
        if ($perms['leave']) {
            if ($project->users->count() > 1) {
                $actions[] = [
                    'kind' => 'detach_self',
                    'project_id' => $id,
                    'user_id' => $auth->id,
                    'label' => 'Покинуть проект',
                    'icon' => 'fas fa-door-open',
                ];
            } else {
                $actions[] = [
                    'kind' => 'delete_project',
                    'id' => $id,
                    'label' => 'Удалить проект',
                    'icon' => 'fas fa-trash',
                ];
            }
        }

        return $actions;
    }
}
