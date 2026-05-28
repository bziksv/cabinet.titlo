<?php

namespace App\Classes\Monitoring;

use App\Http\Controllers\MonitoringController;
use App\Classes\Monitoring\Helper;
use App\MonitoringKeywordPrice;
use App\MonitoringPosition;
use App\MonitoringSearchengine;
use App\MonitoringProject;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Регионы и дни (child-rows): один запрос позиций вместо N×5, кэш HTML.
 */
class MonitoringChildRowsService
{
    private const CACHE_TTL_SECONDS = 600;

    /** Выше — грузим помесячно (5 запросов), иначе один запрос за 12 мес. */
    private const SINGLE_QUERY_MAX_ROWS = 120000;

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

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($project, $groupId) {
            $groups = $this->buildGroups($project, $groupId);
            $projectId = $project->id;

            return view('monitoring.partials._child_rows', compact('groups', 'projectId'))->render();
        });
    }

    public static function forgetProjectCache(int $projectId): void
    {
        Cache::put('monitoring_child_rows_ver:' . $projectId, (int) Cache::get('monitoring_child_rows_ver:' . $projectId, 0) + 1, 86400);
    }

    private function cacheKey(int $projectId, string $groupKey): string
    {
        $ver = (int) Cache::get('monitoring_child_rows_ver:' . $projectId, 0);

        return sprintf('monitoring_child_rows:%d:%s:v%d:p8', $projectId, $groupKey, $ver);
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

        if ($this->shouldLoadPositionsInSingleQuery($project, $engineIds, $keywordIds)) {
            $this->fillGroupsFromSingleQuery($engines, $engineIds, $keywordIds, $months, $pricesByEngine);
        } else {
            $this->fillGroupsByMonthQueries($engines, $engineIds, $keywordIds, $months, $pricesByEngine);
        }

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
     * COUNT по monitoring_positions на удалённой БД — 2–5 с даже для малых проектов.
     * Для типичных портфелей оцениваем объём без COUNT.
     */
    private function shouldLoadPositionsInSingleQuery(MonitoringProject $project, array $engineIds, ?array $keywordIds): bool
    {
        $engineCount = count($engineIds);
        if ($engineCount === 0) {
            return true;
        }

        $keywordCount = $keywordIds !== null
            ? count($keywordIds)
            : (int) $project->keywords()->count();

        if ($engineCount <= 12 && $keywordCount <= 8000) {
            return true;
        }

        $query = MonitoringPosition::query()
            ->whereIn('monitoring_searchengine_id', $engineIds)
            ->whereNotNull('position');
        $this->applySubtractionMonthsFilter($query, $this->metrics->getSubtractionMonths());

        if ($keywordIds !== null) {
            $query->whereIn('monitoring_keyword_id', $keywordIds);
        }

        return (int) $query->count() <= self::SINGLE_QUERY_MAX_ROWS;
    }

    private function positionsBaseQuery(array $engineIds, ?array $keywordIds)
    {
        $query = MonitoringPosition::query()
            ->whereIn('monitoring_searchengine_id', $engineIds)
            ->whereNotNull('position');

        if ($keywordIds !== null) {
            $query->whereIn('monitoring_keyword_id', $keywordIds);
        }

        return $query->select(['id', 'monitoring_searchengine_id', 'monitoring_keyword_id', 'position', 'created_at'])
            ->orderByDesc('created_at');
    }

    /**
     * Только месяцы среза (0/1/3/6/12), не весь год — меньше строк с удалённой БД.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    private function applySubtractionMonthsFilter($query, array $months): void
    {
        $query->where(function ($outer) use ($months) {
            foreach ($months as $subMonth) {
                $target = Carbon::now()->subMonths($subMonth);
                $outer->orWhere(function ($q) use ($target) {
                    $q->whereYear('created_at', $target->year)
                        ->whereMonth('created_at', $target->month);
                });
            }
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

    private function fillGroupsFromSingleQuery($engines, array $engineIds, ?array $keywordIds, array $months, array $pricesByEngine): void
    {
        $query = $this->positionsBaseQuery($engineIds, $keywordIds);
        $this->applySubtractionMonthsFilter($query, $months);
        $all = $query->get()->groupBy('monitoring_searchengine_id');

        foreach ($engines as $engine) {
            $positions = $all->get($engine->id, collect());
            $prices = $pricesByEngine[$engine->id] ?? collect();
            foreach ($months as $month) {
                $monthPositions = $this->filterByMonth($positions, $month);
                if ($monthPositions === null) {
                    continue;
                }
                $this->pushMonthSnapshot($engine, $monthPositions, $prices, $month);
            }
        }
    }

    private function fillGroupsByMonthQueries($engines, array $engineIds, ?array $keywordIds, array $months, array $pricesByEngine): void
    {
        foreach ($months as $month) {
            $target = Carbon::now()->subMonths($month);
            $byEngine = $this->positionsBaseQuery($engineIds, $keywordIds)
                ->whereYear('created_at', $target->year)
                ->whereMonth('created_at', $target->month)
                ->get()
                ->groupBy('monitoring_searchengine_id');

            foreach ($engines as $engine) {
                $monthPositions = $byEngine->get($engine->id);
                if (!$monthPositions || $monthPositions->isEmpty()) {
                    continue;
                }
                $prices = $pricesByEngine[$engine->id] ?? collect();
                $this->pushMonthSnapshot($engine, $monthPositions, $prices, $month);
            }
        }
    }

    /**
     * @param \Illuminate\Support\Collection $positions
     */
    private function filterByMonth($positions, int $subMonth)
    {
        $target = Carbon::now()->subMonths($subMonth);
        $filtered = $positions->filter(function ($row) use ($target) {
            $at = $row->created_at;

            return $at && (int) $at->year === (int) $target->year && (int) $at->month === (int) $target->month;
        })->values();

        return $filtered->isEmpty() ? null : $filtered;
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
