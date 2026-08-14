<?php

namespace App\Classes\Monitoring;

use App\MonitoringDataTableColumnsProject;
use App\MonitoringKeyword;
use App\MonitoringPosition;
use App\MonitoringProject;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\PermissionRegistrar;

class ProjectData
{
    protected $project;
    protected $queries;
    protected $positions;
    protected $result = [];

    public function __construct(MonitoringProject $project)
    {
        $this->project = $project;
        $calculate = new ProjectDependencies($project);

        $this->queries = $calculate->getQueries();
        $this->positions = $calculate->getLatestPositionCollect();

        apply_team_permissions($this->project['id']);

        foreach ($this->project->users as $user) {
            $user->unsetRelation('roles');
        }

        $this->resultInit();

        apply_global_team_permissions();
    }

    private function resultInit()
    {
        $this->percentCalc();
        $this->masteredCalc();

        $this->result['words'] = $this->queries->count();
    }

    public function save()
    {
        $this->store($this->result);
    }

    public function extension()
    {
        $this->project->fill($this->result);
    }

    private function masteredCalc()
    {
        $priceByKeyword = $this->preloadPrices();
        $mastered = new Mastered($this->positions, $priceByKeyword);

        $this->result['mastered'] = $mastered->total();
        $this->result['mastered_percent'] = $mastered->percentOf($this->project['budget']);
        $this->result['mastered_info'] = collect([
            'top1' => $mastered->top1(),
            'top3' => $mastered->top3(),
            'top5' => $mastered->top5(),
            'top10' => $mastered->top10(),
            'top20' => $mastered->top20(),
            'top50' => $mastered->top50(),
            'top100' => $mastered->top100(),
            'total' => $mastered->total(),
        ]);
    }

    /**
     * Цены одним запросом — иначе Mastered бьёт БД на каждый ключ×топ.
     */
    private function preloadPrices()
    {
        $keywordIds = $this->positions->map(static function ($row) {
            if (is_array($row)) {
                return (int) ($row['query_id'] ?? $row['monitoring_keyword_id'] ?? 0);
            }

            return (int) ($row['query_id'] ?? $row->query_id ?? $row->monitoring_keyword_id ?? 0);
        })->filter()->unique()->values()->all();

        if ($keywordIds === []) {
            return collect();
        }

        $engineIds = $this->positions->map(static function ($row) {
            if (is_array($row)) {
                return (int) ($row['engine_id'] ?? $row['monitoring_searchengine_id'] ?? 0);
            }

            return (int) ($row['engine_id'] ?? $row->engine_id ?? $row->monitoring_searchengine_id ?? 0);
        })->filter()->unique()->values()->all();

        $query = \App\MonitoringKeywordPrice::query()->whereIn('monitoring_keyword_id', $keywordIds);
        if ($engineIds !== []) {
            $query->whereIn('monitoring_searchengine_id', $engineIds);
        }

        // Mastered::getPrice ищет по keyword_id в коллекции — одна цена на ключ (как child-rows).
        return $query->get()->keyBy('monitoring_keyword_id');
    }

    private function percentCalc()
    {
        $percentOfTop = new PositionsPercentCalculate($this->positions->pluck('position'));

        $this->result['top1'] = $percentOfTop->top1();
        $this->result['top3'] = $percentOfTop->top3();
        $this->result['top5'] = $percentOfTop->top5();
        $this->result['top10'] = $percentOfTop->top10();
        $this->result['top30'] = $percentOfTop->top30();
        $this->result['top100'] = $percentOfTop->top100();
        $this->result['middle'] = $percentOfTop->middle();
    }

    public function store(array $data)
    {
        MonitoringDataTableColumnsProject::updateOrCreate(
            ['monitoring_project_id' => $this->project['id']],
            $data
        );
    }
}
