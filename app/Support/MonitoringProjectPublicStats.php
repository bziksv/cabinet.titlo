<?php

namespace App\Support;

use App\Classes\Monitoring\MonitoringChildRowsService;
use App\Classes\Monitoring\MonitoringSearchengineScheduleFormatter;
use App\MonitoringDataTableColumnsProject;
use App\MonitoringProject;

class MonitoringProjectPublicStats
{
    /**
     * Снимок KPI и регионов для публичной ссылки / модалки «Статистика для клиента».
     *
     * @return array<string, mixed>
     */
    public static function buildForExport(MonitoringProject $project): array
    {
        $project->loadMissing(['searchengines.location']);

        $engines = app(MonitoringChildRowsService::class)->exportGroupsForProject($project);

        return [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'url' => $project->url,
                'host' => parse_url((string) $project->url, PHP_URL_HOST) ?: $project->url,
            ],
            'summary' => self::summaryFromSnapshot($project, null, $engines !== []),
            'engines' => $engines,
        ];
    }

    /**
     * KPI из снимка списка — без exportGroupsForProject (~4 с на HTTP).
     *
     * @return array<string, mixed>
     */
    public static function summaryFromSnapshot(
        MonitoringProject $project,
        ?MonitoringDataTableColumnsProject $snap = null,
        ?bool $hasEngines = null
    ): array {
        if ($snap === null) {
            $snap = MonitoringDataTableColumnsProject::query()
                ->where('monitoring_project_id', $project->id)
                ->first();
        }

        if ($hasEngines === null) {
            $hasEngines = $project->relationLoaded('searchengines')
                ? $project->searchengines->isNotEmpty()
                : $project->searchengines()->exists();
        }

        return [
            'words' => $snap ? $snap->words : null,
            'middle' => $snap ? $snap->middle : null,
            'top1' => $snap ? $snap->top1 : null,
            'diff_top1' => $snap ? $snap->diff_top1 : null,
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
            'snapshot_at' => $snap && $snap->updated_at
                ? $snap->updated_at->format('d.m.Y H:i')
                : null,
            'has_data' => $snap !== null || $hasEngines,
            'snapshot_scope' => 'project',
        ];
    }

    /**
     * @param \Illuminate\Support\Collection<int, \App\MonitoringSearchengine> $searchengines
     *
     * @return array<string, list<array{name: string, schedule: string}>>
     */
    public static function engineRegionsMeta($searchengines): array
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

            $name = null;
            if ($se->location !== null && trim((string) $se->location->name) !== '') {
                $name = trim((string) $se->location->name);
            } elseif ($se->lr !== null && trim((string) $se->lr) !== '') {
                $name = '[' . trim((string) $se->lr) . ']';
            }

            if ($name === null) {
                continue;
            }

            $schedule = $formatter->describe($se);
            $out[$key][] = [
                'name' => $name,
                'schedule' => $schedule['label'],
            ];
        }

        return $out;
    }
}
