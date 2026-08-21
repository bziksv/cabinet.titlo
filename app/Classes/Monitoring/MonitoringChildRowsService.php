<?php

namespace App\Classes\Monitoring;

use App\Http\Controllers\MonitoringController;
use App\MonitoringKeywordPrice;
use App\MonitoringProject;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Регионы и дни (child-rows): последние позиции по срезам месяцев + кэш HTML.
 */
class MonitoringChildRowsService
{
    /** Дольше — меньше повторных тяжёлых сборок при скролле списка. */
    private const CACHE_TTL_SECONDS = 1800;

    /** @var MonitoringController */
    private $metrics;

    public function __construct(MonitoringController $metrics)
    {
        $this->metrics = $metrics;
    }

    public function htmlForProject(User $user, int $projectId, $groupId = null): string
    {
        $project = $user->monitoringProjects()->findOrFail($projectId);
        $groupKey = $groupId ? (string) $groupId : '0';
        $cacheKey = $this->cacheKey($projectId, $groupKey);

        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        // Анти-штамп: hover/warm не должны параллельно собирать один проект.
        $lockKey = 'mon.child_rows.lock.' . $projectId . '.' . $groupKey;
        if (!Cache::add($lockKey, 1, 60)) {
            $deadline = microtime(true) + 8.0;
            while (microtime(true) < $deadline) {
                usleep(120000);
                $cached = Cache::get($cacheKey);
                if (is_string($cached) && $cached !== '') {
                    return $cached;
                }
                if (!Cache::has($lockKey)) {
                    break;
                }
            }
            $cached = Cache::get($cacheKey);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        try {
            $groups = $this->buildGroups($project, $groupId);
            $html = view('monitoring.partials._child_rows', [
                'groups' => $groups,
                'projectId' => $project->id,
            ])->render();
            Cache::put($cacheKey, $html, self::CACHE_TTL_SECONDS);

            return $html;
        } finally {
            Cache::forget($lockKey);
        }
    }

    /**
     * Регионы и срезы для публичного отчёта (JSON, без HTML).
     *
     * @return array<int, array<string, mixed>>
     */
    public function exportGroupsForProject(MonitoringProject $project): array
    {
        $formatter = app(MonitoringSearchengineScheduleFormatter::class);
        $groups = $this->buildGroups($project, null);

        return $groups->map(function ($engine) use ($formatter) {
            $schedule = $formatter->describe($engine);

            return [
                'engine' => (string) $engine->engine,
                'location' => MonitoringLocationLabel::displayName(
                    (string) $engine->engine,
                    (string) $engine->lr,
                    $engine->location ? (string) $engine->location->name : null
                ),
                'lr' => (string) $engine->lr,
                'schedule' => $schedule['label'],
                'rows' => $engine->data->map(function ($row) {
                    return [
                        'date' => !empty($row->latest_created)
                            ? $row->latest_created->format('d.m.Y')
                            : null,
                        'period_label' => (string) ($row->snapshot_period_label ?? ''),
                        'delta_vs_label' => (string) ($row->delta_vs_label ?? ''),
                        'middle' => $row->middle_position,
                        'top_1' => (string) ($row->top_1 ?? ''),
                        'top_3' => (string) ($row->top_3 ?? ''),
                        'top_5' => (string) ($row->top_5 ?? ''),
                        'top_10' => (string) ($row->top_10 ?? ''),
                        'top_20' => (string) ($row->top_20 ?? ''),
                        'top_50' => (string) ($row->top_50 ?? ''),
                        'top_100' => (string) ($row->top_100 ?? ''),
                        'mastered' => $row->mastered ?? null,
                        'mastered_percent' => $row->mastered_percent ?? null,
                        'mastered_percent_day' => $row->mastered_percent_day ?? null,
                    ];
                })->values()->all(),
            ];
        })->values()->all();
    }

    public static function forgetProjectCache(int $projectId): void
    {
        Cache::put('monitoring_child_rows_ver:' . $projectId, (int) Cache::get('monitoring_child_rows_ver:' . $projectId, 0) + 1, 86400);
    }

    private function cacheKey(int $projectId, string $groupKey): string
    {
        $ver = (int) Cache::get('monitoring_child_rows_ver:' . $projectId, 0);

        return sprintf('monitoring_child_rows:%d:%s:v%d:p9', $projectId, $groupKey, $ver);
    }

    /**
     * @return Collection
     */
    private function buildGroups(MonitoringProject $project, $groupId)
    {
        $engines = $project->searchengines()->with('location')->get();
        if ($engines->isEmpty()) {
            return collect([]);
        }

        foreach ($engines as $engine) {
            $engine->setRelation('project', $project);
        }

        $engineIds = $engines->pluck('id')->all();
        $keywordIds = $this->keywordFilterIds($project, $groupId);
        $months = $this->metrics->getSubtractionMonths();

        foreach ($engines as $engine) {
            $engine->data = collect([]);
        }

        $pricesByEngine = $this->keywordPricesByEngine($engineIds, $keywordIds);
        $this->fillGroupsByLatestMonthSnapshots($engines, $engineIds, $keywordIds, $months, $pricesByEngine);

        foreach ($engines as $engine) {
            $engine->data = $this->applyPeriodOverPeriodDeltas($engine->data);
            $engine->chart_payload = $this->chartPayloadForEngineData($engine->data);
        }

        return $engines;
    }

    /**
     * Дельта в ТОП: сравнение с предыдущей строкой таблицы (более ранняя дата), не внутри месяца.
     *
     * @param Collection<int, object> $data
     */
    private function applyPeriodOverPeriodDeltas(Collection $data): Collection
    {
        $keys = ['top_1', 'top_3', 'top_5', 'top_10', 'top_20', 'top_50', 'top_100'];

        $rows = $data->sortByDesc(function ($row) {
            if (empty($row->latest_created)) {
                return 0;
            }
            $at = $row->latest_created;

            return $at instanceof Carbon ? $at->timestamp : (int) strtotime((string) $at);
        })->values();

        foreach ($rows as $index => $row) {
            $older = $index + 1 < $rows->count() ? $rows[$index + 1] : null;

            if ($older !== null && !empty($row->latest_created) && !empty($older->latest_created)) {
                $row->delta_vs_label = $this->deltaVsLabel($row->latest_created, $older->latest_created);
            }

            foreach ($keys as $name) {
                if (!isset($row->{$name . '_raw'})) {
                    continue;
                }
                $current = (float) $row->{$name . '_raw'};
                if ($older !== null && isset($older->{$name . '_raw'})) {
                    $previous = (float) $older->{$name . '_raw'};
                    $row->$name = $current . Helper::differentTopPercent($current, $previous);
                } else {
                    $row->$name = (string) $current;
                }
            }
        }

        return $rows;
    }

    /**
     * Точки для Chart.js (фильтр срезов и серий — на фронте).
     *
     * @param Collection<int, object> $data
     * @return array{version: int, points: array<int, array<string, mixed>>}
     */
    private function chartPayloadForEngineData(Collection $data): array
    {
        $topKeys = ['top_1', 'top_3', 'top_5', 'top_10', 'top_20', 'top_50', 'top_100'];

        $rows = $data->sortBy(function ($row) {
            if (empty($row->latest_created)) {
                return 0;
            }
            $at = $row->latest_created;

            return $at instanceof Carbon ? $at->timestamp : (int) strtotime((string) $at);
        })->values();

        $points = [];
        foreach ($rows as $row) {
            $point = [
                'date' => $row->latest_created->format('d.m.Y'),
                'sub_month' => (int) ($row->snapshot_sub_month ?? -1),
                'period_label' => (string) ($row->snapshot_period_label ?? ''),
                'middle_position' => is_numeric($row->middle_position)
                    ? round((float) $row->middle_position, 2)
                    : null,
            ];
            foreach ($topKeys as $key) {
                $rawKey = $key . '_raw';
                $point[$key] = isset($row->{$rawKey}) ? round((float) $row->{$rawKey}, 2) : null;
            }
            $points[] = $point;
        }

        return ['version' => 2, 'points' => $points];
    }

    private function pushMonthSnapshot($engine, $monthPositions, $prices, int $subMonth): void
    {
        $row = $this->metrics->calculateTopPercent($monthPositions, $engine, $prices, false);
        $row->snapshot_sub_month = $subMonth;
        $row->snapshot_period_label = $this->snapshotPeriodLabel($subMonth);
        $engine->data->push($row);
    }

    private function snapshotPeriodLabel(int $subMonth): string
    {
        if ($subMonth === 0) {
            return (string) __('Monitoring child row period current');
        }
        if ($subMonth === 1) {
            return (string) __('Monitoring child row period 1m');
        }
        if ($subMonth === 3) {
            return (string) __('Monitoring child row period 3m');
        }
        if ($subMonth === 6) {
            return (string) __('Monitoring child row period 6m');
        }
        if ($subMonth === 12) {
            return (string) __('Monitoring child row period 12m');
        }

        return (string) __('Monitoring child row period nm', ['n' => $subMonth]);
    }

    /**
     * @param Carbon|\DateTimeInterface|string $currentAt
     * @param Carbon|\DateTimeInterface|string $olderAt
     */
    private function deltaVsLabel($currentAt, $olderAt): string
    {
        $current = $currentAt instanceof Carbon ? $currentAt : Carbon::parse($currentAt);
        $older = $olderAt instanceof Carbon ? $olderAt : Carbon::parse($olderAt);
        $days = (int) $older->diffInDays($current);
        if ($days < 1) {
            return '';
        }

        $dateStr = $older->format('d.m.Y');
        $span = $this->humanSpanDays($days);

        return (string) __('Monitoring child row delta vs', [
            'date' => $dateStr,
            'span' => $span,
        ]);
    }

    private function humanSpanDays(int $days): string
    {
        if ($days <= 40) {
            return (string) __('Monitoring child row span ~1m');
        }
        if ($days <= 70) {
            return (string) __('Monitoring child row span ~2m');
        }
        if ($days <= 100) {
            return (string) __('Monitoring child row span ~90d');
        }
        if ($days <= 130) {
            return (string) __('Monitoring child row span ~4m');
        }
        if ($days <= 200) {
            return (string) __('Monitoring child row span ~6m');
        }
        if ($days <= 380) {
            return (string) __('Monitoring child row span ~12m');
        }

        return (string) __('Monitoring child row span nd', ['days' => $days]);
    }

    /**
     * По каждому срезу месяца — только последняя позиция ключа (JOIN MAX(id)),
     * диапазон дат без YEAR()/MONTH() (иначе индекс не используется).
     */
    private function fillGroupsByLatestMonthSnapshots(
        $engines,
        array $engineIds,
        ?array $keywordIds,
        array $months,
        array $pricesByEngine
    ): void {
        foreach ($months as $month) {
            $target = Carbon::now()->subMonths((int) $month);
            $start = $target->copy()->startOfMonth();
            $end = $target->copy()->endOfMonth();

            $byEngine = $this->latestPositionsInRange($engineIds, $keywordIds, $start, $end)
                ->groupBy('monitoring_searchengine_id');

            foreach ($engines as $engine) {
                $monthPositions = $byEngine->get($engine->id);
                if (!$monthPositions || $monthPositions->isEmpty()) {
                    continue;
                }
                $prices = $pricesByEngine[$engine->id] ?? collect();
                $this->pushMonthSnapshot($engine, $monthPositions, $prices, (int) $month);
            }
        }
    }

    /**
     * @param list<int> $engineIds
     * @param list<int>|null $keywordIds
     * @return Collection<int, object>
     */
    private function latestPositionsInRange(
        array $engineIds,
        ?array $keywordIds,
        Carbon $start,
        Carbon $end
    ): Collection {
        if ($engineIds === []) {
            return collect();
        }

        $latestIdsQuery = DB::table('monitoring_positions')
            ->selectRaw('MAX(id) as id')
            ->whereIn('monitoring_searchengine_id', $engineIds)
            ->whereNotNull('position')
            ->where('created_at', '>=', $start->toDateTimeString())
            ->where('created_at', '<=', $end->toDateTimeString());

        if ($keywordIds !== null) {
            if ($keywordIds === []) {
                return collect();
            }
            $latestIdsQuery->whereIn('monitoring_keyword_id', $keywordIds);
        }

        $latestIdsQuery->groupBy('monitoring_keyword_id', 'monitoring_searchengine_id');

        $rows = DB::table('monitoring_positions as p')
            ->select([
                'p.id',
                'p.monitoring_searchengine_id',
                'p.monitoring_keyword_id',
                'p.position',
                'p.created_at',
            ])
            ->joinSub($latestIdsQuery, 'latest', static function ($join) {
                $join->on('p.id', '=', 'latest.id');
            })
            ->get();

        return $rows->map(static function ($row) {
            $row->created_at = $row->created_at ? Carbon::parse($row->created_at) : null;

            return $row;
        });
    }

    /**
     * Одна выборка цен на все ПС проекта (вместо N×5 запросов в calculateTopPercent).
     *
     * @return array<int, Collection>
     */
    private function keywordPricesByEngine(array $engineIds, ?array $keywordIds): array
    {
        if ($engineIds === []) {
            return [];
        }

        $query = MonitoringKeywordPrice::query()->whereIn('monitoring_searchengine_id', $engineIds);
        if ($keywordIds !== null) {
            $query->whereIn('monitoring_keyword_id', $keywordIds);
        }

        $out = [];
        foreach ($query->get() as $row) {
            if (!isset($out[$row->monitoring_searchengine_id])) {
                $out[$row->monitoring_searchengine_id] = collect();
            }
            $out[$row->monitoring_searchengine_id]->put($row->monitoring_keyword_id, $row);
        }

        return $out;
    }

    /**
     * @return int[]|null null = без фильтра по ключевым словам
     */
    private function keywordFilterIds(MonitoringProject $project, $groupId): ?array
    {
        if (!$groupId) {
            return null;
        }

        $section = $project->groups()->find($groupId);
        if (!$section) {
            return null;
        }

        return $section->keywords()->pluck('id')->all();
    }
}
