<?php

namespace App\Classes\Monitoring;

use App\MonitoringProject;

class ProjectDependencies
{
    protected $project;
    protected $queries;
    protected $engines;

    public function __construct(MonitoringProject $project)
    {
        $this->init($project);
    }

    private function init(MonitoringProject $project)
    {
        $this->project = $project->load(['searchengines']);

        $this->engines = $project['searchengines'];

        // Без addLastPositions: N коррелированных подзапросов на тяжёлых проектах
        // тормозили/срывали снимок, и в колонке ТОП оставался старый «архивный» срез.
        $this->queries = $project->keywords()->get(['id', 'monitoring_project_id', 'query']);
    }

    public function getQueries()
    {
        return $this->queries;
    }

    public function getEngines()
    {
        return $this->engines;
    }

    public function getLatestPositionCollect()
    {
        $engineIds = $this->engines->pluck('id')->map(static function ($id) {
            return (int) $id;
        })->all();

        $keywordIds = $this->queries->pluck('id')->map(static function ($id) {
            return (int) $id;
        })->all();

        return MonitoringLatestPositions::collectForProjectData($engineIds, $keywordIds);
    }
}
