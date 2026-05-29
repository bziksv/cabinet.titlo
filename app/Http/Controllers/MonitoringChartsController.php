<?php

namespace App\Http\Controllers;

use App\Classes\Monitoring\AreaChartData;
use App\Classes\Monitoring\MonitoringChartPalette;
use App\Classes\Monitoring\MonitoringChartPositionSeries;
use App\Classes\Monitoring\MonitoringCompareKeywordIntersect;
use App\Classes\Monitoring\MonitoringLocationLabel;
use App\MonitoringPosition;
use App\MonitoringProject;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;


class MonitoringChartsController extends Controller
{
    protected $project;
    protected $keywords;
    protected $positions;
    protected $region;

    private function initModelClasses(Request $request): bool
    {
        $this->project = MonitoringProject::findOrFail($request->input('projectId', null));

        $this->keywords = $this->project->keywords;

        if ($request->filled('group')) {
            $this->keywords = $this->keywords->where('monitoring_group_id', $request->input('group'));
        }

        $this->region = $this->resolveSearchengine($request);

        if ($this->region === null) {
            $this->positions = collect([]);

            return false;
        }

        $this->positions = $this->getPositionsForRange($request->input('dateRange', null));

        return true;
    }

    /**
     * Регион графика: id текущего проекта или сопоставление engine+lr (сравнение проектов).
     */
    private function resolveSearchengine(Request $request): ?\App\MonitoringSearchengine
    {
        $query = $this->project->searchengines();

        if ($request->filled('regionId')) {
            $byId = (clone $query)->where('id', $request->input('regionId'))->first();
            if ($byId) {
                return $byId;
            }
        }

        if ($request->filled('matchEngine') && $request->filled('matchLr')) {
            $byLr = (clone $query)
                ->where('engine', $request->input('matchEngine'))
                ->where('lr', $request->input('matchLr'))
                ->orderBy('id')
                ->first();
            if ($byLr) {
                return $byLr;
            }
            if ($request->boolean('strictMatch')) {
                return null;
            }
        }

        return $query->orderBy('id', 'asc')->first();
    }

    public function getPositionsForRange($dateRange = null)
    {
        if($dateRange)
            $dateRange = explode(' - ', $dateRange);

        $model = new MonitoringPosition();
        $positions = $model->where('monitoring_searchengine_id', $this->region->id)
            ->whereIn('monitoring_keyword_id', $this->keywords->pluck('id'))
            ->dateRange($dateRange)
            ->get();

        return $positions;
    }

    public function getLastPositionsByDays()
    {
        $positions = $this->positions->groupBy('date')->transform(function($item){
            return $item->sortByDesc('created_at')->unique('monitoring_keyword_id')->pluck('position');
        })->sortBy(function ($product, $key) {

            return strtotime($key);
        });

        return $positions;
    }

    public function getChartData(Request $request)
    {
        @set_time_limit(120);

        $this->resolveKeywords($request);

        if ($request->input('chart') === 'regions_middle') {
            return $this->wrapChartMeta($request, $this->getMiddlePositionAllRegions($request));
        }

        if ($request->input('chart') === 'regions_top') {
            return $this->wrapChartMeta($request, $this->getTopPercentAllRegions($request));
        }

        $this->region = $this->resolveSearchengine($request);
        if ($this->region === null || $this->keywords->isEmpty()) {
            return (new AreaChartData([]))->setData([])->get();
        }

        switch ($request->input('chart')) {
            case 'middle':
                return $this->wrapChartMeta($request, $this->getMiddlePosition($request));

            case 'distribution':
                return $this->wrapChartMeta($request, $this->getDistributionByTop($request));

            default:
                return $this->wrapChartMeta($request, $this->getTopPercent($request));
        }
    }

    private function resolveKeywords(Request $request): void
    {
        $this->project = MonitoringProject::findOrFail($request->input('projectId', null));
        $this->keywords = $this->project->keywords;

        if ($request->filled('group')) {
            $this->keywords = $this->keywords->where('monitoring_group_id', (int) $request->input('group'));
        }

        if (!$request->boolean('intersect') || !$request->filled('intersectProjectId')) {
            return;
        }

        $ids = MonitoringCompareKeywordIntersect::keywordIdsForIntersection(
            (int) $this->project->id,
            $request->filled('group') ? (int) $request->input('group') : null,
            (int) $request->input('intersectProjectId'),
            $request->filled('intersectGroup') ? (int) $request->input('intersectGroup') : null
        );

        $this->keywords = $this->keywords->whereIn('id', $ids)->values();
    }

    /**
     * @return int[]
     */
    private function keywordIds(): array
    {
        return $this->keywords->pluck('id')->map(function ($id) {
            return (int) $id;
        })->all();
    }

    /**
     * @param array<string, mixed> $chart
     *
     * @return array<string, mixed>
     */
    private function wrapChartMeta(Request $request, array $chart): array
    {
        if ($request->boolean('intersect')) {
            $chart['_meta'] = [
                'intersected' => true,
                'keywords' => $this->keywords->count(),
            ];
        }

        return $chart;
    }

    protected function getDistributionByTop(Request $request)
    {
        $response = [
            'labels' => ['ТОП 3', 'ТОП 10', 'ТОП 30', 'ТОП 50', 'ТОП 100', 'ТОП 101+'],
            'data' => [0, 0, 0, 0, 0, 0],
        ];

        [$start, $end] = MonitoringChartPositionSeries::parseDateRange($request->input('dateRange', null));
        $bucket = MonitoringChartPositionSeries::resolveBucket(null, $start, $end);
        $groupId = $request->filled('group') ? (int) $request->input('group') : null;
        $keywordIds = $this->keywordIds();
        $positionsByDay = MonitoringChartPositionSeries::latestPositionsSeries(
            (int) $this->project->id,
            $groupId,
            (int) $this->region->id,
            $start,
            $end,
            $bucket,
            $keywordIds
        );

        if ($positionsByDay->isEmpty()) {
            return (new AreaChartData([]))->setData([])->get();
        }

        $positions = $positionsByDay->last();

        $response['data'][0] = $this->calculatePercentPositionsInTop($positions, 3);
        $response['data'][1] = $this->calculatePercentPositionsInTop($positions, 10);
        $response['data'][2] = $this->calculatePercentPositionsInTop($positions, 30);
        $response['data'][3] = $this->calculatePercentPositionsInTop($positions, 50);
        $response['data'][4] = $this->calculatePercentPositionsInTop($positions, 100);
        $response['data'][5] += ($this->keywords->count() - $positions->count());

        $chart = new AreaChartData($response['labels']);
        $chart->setBackgroundColor(MonitoringChartPalette::distributionColors())
            ->setBorderColor('#ffffff')
            ->setHidden(false)
            ->setLabel('Распределение по ТОП-100')
            ->setData($response['data']);

        return $chart->get();
    }

    protected function getMiddlePosition(Request $request)
    {
        [$start, $end] = MonitoringChartPositionSeries::parseDateRange($request->input('dateRange', null));
        $bucket = MonitoringChartPositionSeries::resolveBucket($request->input('range'), $start, $end);
        $groupId = $request->filled('group') ? (int) $request->input('group') : null;
        $series = MonitoringChartPositionSeries::middleSeries(
            (int) $this->project->id,
            $groupId,
            (int) $this->region->id,
            $start,
            $end,
            $bucket,
            $this->keywordIds()
        );

        if ($series->isEmpty()) {
            return (new AreaChartData([]))->setData([])->get();
        }

        $chart = new AreaChartData($series->keys()->values()->all());
        $chart->setBackgroundColor('#28a745')
            ->setHidden(false)
            ->setBorderColor('#28a745')
            ->setLabel('Средняя позиция')
            ->setData($series->values()->all());

        return $chart->get();
    }

    protected function getMiddlePositionAllRegions(Request $request)
    {
        if ($this->project->searchengines->count() <= 1) {
            return (new AreaChartData([]))->setData([])->get();
        }

        [$start, $end] = MonitoringChartPositionSeries::parseDateRange($request->input('dateRange', null));
        $bucket = MonitoringChartPositionSeries::resolveBucket($request->input('range'), $start, $end);
        $groupId = $request->filled('group') ? (int) $request->input('group') : null;
        $engineIds = $this->project->searchengines->pluck('id')->map(function ($id) {
            return (int) $id;
        })->all();
        $keywordIds = $this->keywordIds();

        $seriesByEngine = MonitoringChartPositionSeries::middleSeriesByEngines(
            (int) $this->project->id,
            $groupId,
            $engineIds,
            $start,
            $end,
            $bucket,
            $keywordIds
        );

        $labels = $this->mergeSeriesLabels($seriesByEngine);
        if (count($labels) < 2) {
            $extendedStart = $end->copy()->subDays(89)->startOfDay();
            if ($extendedStart->lt($start)) {
                $seriesByEngine = MonitoringChartPositionSeries::middleSeriesByEngines(
                    (int) $this->project->id,
                    $groupId,
                    $engineIds,
                    $extendedStart,
                    $end,
                    $bucket,
                    $keywordIds
                );
                $labels = $this->mergeSeriesLabels($seriesByEngine);
            }
        }

        if ($seriesByEngine === [] || $labels === []) {
            return (new AreaChartData([]))->setData([])->get();
        }

        $chart = new AreaChartData($labels);
        $seriesIndex = 0;
        foreach ($this->project->searchengines as $engine) {
            $series = $seriesByEngine[(int) $engine->id] ?? null;
            if ($series === null || $series->isEmpty()) {
                continue;
            }

            $dataByLabel = [];
            foreach ($labels as $label) {
                $dataByLabel[$label] = $series->has($label) ? $series->get($label) : null;
            }

            $color = MonitoringChartPalette::lineColor($seriesIndex++);
            $chart->setBackgroundColor($color)
                ->setHidden(false)
                ->setBorderColor($color)
                ->setLabel(MonitoringLocationLabel::chartLegend($engine))
                ->setDataForLabels($labels, $dataByLabel);
        }

        return $chart->get();
    }

    protected function getTopPercentAllRegions(Request $request)
    {
        if ($this->project->searchengines->count() <= 1) {
            return (new AreaChartData([]))->setData([])->get();
        }

        $topN = (int) $request->input('topN', 10);
        if ($topN < 1) {
            $topN = 10;
        }
        if ($topN > 100) {
            $topN = 100;
        }

        $keywordCount = $this->keywords->count();
        if ($keywordCount === 0) {
            return (new AreaChartData([]))->setData([])->get();
        }

        [$start, $end] = MonitoringChartPositionSeries::parseDateRange($request->input('dateRange', null));
        $bucket = MonitoringChartPositionSeries::resolveBucket($request->input('range'), $start, $end);
        $groupId = $request->filled('group') ? (int) $request->input('group') : null;
        $engineIds = $this->project->searchengines->pluck('id')->map(function ($id) {
            return (int) $id;
        })->all();
        $keywordIds = $this->keywordIds();

        $seriesByEngine = MonitoringChartPositionSeries::topPercentSeriesByEngines(
            (int) $this->project->id,
            $groupId,
            $engineIds,
            $start,
            $end,
            $bucket,
            $topN,
            $keywordCount,
            $keywordIds
        );

        $labels = $this->mergeSeriesLabels($seriesByEngine);
        if (count($labels) < 2) {
            $extendedStart = $end->copy()->subDays(89)->startOfDay();
            if ($extendedStart->lt($start)) {
                $seriesByEngine = MonitoringChartPositionSeries::topPercentSeriesByEngines(
                    (int) $this->project->id,
                    $groupId,
                    $engineIds,
                    $extendedStart,
                    $end,
                    $bucket,
                    $topN,
                    $keywordCount,
                    $keywordIds
                );
                $labels = $this->mergeSeriesLabels($seriesByEngine);
            }
        }

        if ($seriesByEngine === [] || $labels === []) {
            return (new AreaChartData([]))->setData([])->get();
        }

        $chart = new AreaChartData($labels);
        $seriesIndex = 0;
        foreach ($this->project->searchengines as $engine) {
            $series = $seriesByEngine[(int) $engine->id] ?? null;
            if ($series === null || $series->isEmpty()) {
                continue;
            }

            $dataByLabel = [];
            foreach ($labels as $label) {
                $dataByLabel[$label] = $series->has($label) ? $series->get($label) : null;
            }

            $color = MonitoringChartPalette::lineColor($seriesIndex++);
            $chart->setBackgroundColor($color)
                ->setHidden(false)
                ->setBorderColor($color)
                ->setLabel(MonitoringLocationLabel::chartLegend($engine))
                ->setDataForLabels($labels, $dataByLabel);
        }

        return $chart->get();
    }

    protected function getTopPercent(Request $request)
    {
        $topTops = [1, 3, 5, 10, 20, 30, 50, 100];
        $topSettings = [];
        foreach ($topTops as $i => $top) {
            $color = MonitoringChartPalette::lineColor($i);
            $topSettings[$top] = ['top' => $top, 'color' => $color, 'hidden' => false];
        }

        [$start, $end] = MonitoringChartPositionSeries::parseDateRange($request->input('dateRange', null));
        $bucket = MonitoringChartPositionSeries::resolveBucket($request->input('range'), $start, $end);
        $groupId = $request->filled('group') ? (int) $request->input('group') : null;
        $keywordIds = $this->keywordIds();
        $positionsByDay = MonitoringChartPositionSeries::latestPositionsSeries(
            (int) $this->project->id,
            $groupId,
            (int) $this->region->id,
            $start,
            $end,
            $bucket,
            $keywordIds
        );

        if ($positionsByDay->isEmpty()) {
            return (new AreaChartData([]))->setData([])->get();
        }

        $response = ['labels' => [], 'data' => []];
        foreach ($positionsByDay as $date => $position) {
            $response['labels'][] = $date;
            foreach ($topSettings as $setting) {
                $response['data'][$setting['top']][] = $this->calculatePercentPositionsInTop($position, $setting['top']);
            }
        }

        $chart = new AreaChartData($response['labels']);
        foreach ($response['data'] as $top => $data) {
            $chart->setBackgroundColor($topSettings[$top]['color'])
                ->setHidden($topSettings[$top]['hidden'])
                ->setBorderColor($topSettings[$top]['color'])
                ->setLabel('% ключей в ТОП-' . $top)
                ->setData($data);
        }

        return $chart->get();
    }

    public function getLastPositions(string $range = null)
    {
        $positions = $this->getLastPositionsByDays();

        if($range == 'weeks')
            $positions = $this->getLastPositionsByWeeks($positions);

        if($range == 'month')
            $positions = $this->getLastPositionsByMonths($positions);

        return $positions;
    }

    protected function getLastPositionsByWeeks(Collection $positionByDays)
    {
        $filtered = collect([]);

        $currentWeek = null;
        foreach($positionByDays as $date => $positions){

            $week = Carbon::parse($date)->week();
            if($currentWeek === null || $currentWeek !== $week)
                $filtered->put($date, $positions);

            $currentWeek = $week;
        }

        return $filtered;
    }

    protected function getLastPositionsByMonths(Collection $positionByDays)
    {
        $unique = $positionByDays->unique(function ($item, $key) {
            $carbon = Carbon::parse($key);
            return $carbon->format('m.Y');
        });

        return $unique;
    }

    public function calculatePercentPositionsInTop(Collection $positions, $top)
    {
        $items = $this->keywords->count();
        if ($items === 0) {
            return 0;
        }

        $count = $positions->filter(function ($val) use ($top) {
            return $val <= $top;
        })->count();

        return min(100, round(($count / $items) * 100, 1));
    }

    /**
     * @param array<int, \Illuminate\Support\Collection<string, int>> $seriesByEngine
     *
     * @return list<string>
     */
    private function mergeSeriesLabels(array $seriesByEngine): array
    {
        $labels = [];
        foreach ($seriesByEngine as $series) {
            foreach ($series->keys() as $label) {
                $labels[] = $label;
            }
        }
        $labels = array_values(array_unique($labels));
        usort($labels, function ($a, $b) {
            return strtotime(str_replace('.', '-', $a)) <=> strtotime(str_replace('.', '-', $b));
        });

        return $labels;
    }
}
