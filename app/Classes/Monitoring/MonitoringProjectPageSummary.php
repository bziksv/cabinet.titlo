<?php

namespace App\Classes\Monitoring;

use App\MonitoringDataTableColumnsProject;
use App\MonitoringPosition;
use App\MonitoringProject;
use App\MonitoringSearchengine;
use App\Support\MonitoringProjectPublicStats;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * KPI на /monitoring/{id}: актуальные цифры (не застрявший кэш 2024 при графиках 2026).
 */
class MonitoringProjectPageSummary
{
    /**
     * @return array<string, mixed>
     */
    public static function build(MonitoringProject $project, ?int $regionId = null): array
    {
        apply_team_permissions($project->id);

        try {
            if ($regionId !== null && $regionId > 0) {
                $summary = self::liveForRegion($project, $regionId);
            } else {
                $snapshot = MonitoringProjectPublicStats::summaryFromSnapshot($project);

                if (self::summaryNeedsLiveFallback($snapshot)) {
                    $summary = self::liveForAllRegions($project, $snapshot);
                } else {
                    $summary = $snapshot;
                }
            }

            $summary['scope_label'] = self::scopeLabel($project, $regionId);

            return $summary;
        } finally {
            apply_global_team_permissions();
        }
    }

    /**
     * Снимок без полного набора ТОП-% (часто top1 есть, остальное null) — нужен live-расчёт.
     *
     * @param array<string, mixed> $summary
     */
    private static function summaryNeedsLiveFallback(array $summary): bool
    {
        if (((int) ($summary['words'] ?? 0)) === 0 && empty($summary['has_data'])) {
            return false;
        }

        foreach (['top1', 'top3', 'top10', 'top30', 'top100', 'middle'] as $key) {
            if (!isset($summary[$key]) || $summary[$key] === null || $summary[$key] === '') {
                return true;
            }
        }

        return false;
    }

    private static function scopeLabel(MonitoringProject $project, ?int $regionId): ?string
    {
        $project->loadMissing(['searchengines.location']);

        if ($regionId !== null && $regionId > 0) {
            /** @var MonitoringSearchengine|null $engine */
            $engine = $project->searchengines->firstWhere('id', $regionId);
            if ($engine === null) {
                return null;
            }

            return MonitoringLocationLabel::displayName(
                (string) $engine->engine,
                (string) $engine->lr,
                $engine->location ? (string) $engine->location->name : null
            );
        }

        if ($project->searchengines->count() > 1) {
            return (string) __('Monitoring show kpi scope all regions');
        }

        return null;
    }

    /**
     * KPI по последним позициям всех регионов (как ProjectData::percentCalc, без Blade-снимка списка).
     *
     * @param array<string, mixed> $snapshotSummary
     *
     * @return array<string, mixed>
     */
    private static function liveForAllRegions(MonitoringProject $project, array $snapshotSummary = []): array
    {
        $positions = self::latestPositionValues($project);
        $calc = new PositionsPercentCalculate($positions);
        $words = (int) $project->keywords()->count();

        $engineIds = $project->searchengines()->pluck('id');
        $latestAt = MonitoringPosition::query()
            ->whereIn('monitoring_searchengine_id', $engineIds)
            ->max('updated_at');

        return [
            'words' => $words,
            'middle' => $calc->middle(),
            'top1' => $calc->top1(),
            'diff_top1' => $snapshotSummary['diff_top1'] ?? null,
            'top3' => $calc->top3(),
            'diff_top3' => $snapshotSummary['diff_top3'] ?? null,
            'top5' => $calc->top5(),
            'diff_top5' => $snapshotSummary['diff_top5'] ?? null,
            'top10' => $calc->top10(),
            'diff_top10' => $snapshotSummary['diff_top10'] ?? null,
            'top30' => $calc->top30(),
            'diff_top30' => $snapshotSummary['diff_top30'] ?? null,
            'top100' => $calc->top100(),
            'diff_top100' => $snapshotSummary['diff_top100'] ?? null,
            'mastered' => $snapshotSummary['mastered'] ?? null,
            'mastered_percent' => $snapshotSummary['mastered_percent'] ?? null,
            'snapshot_at' => $latestAt
                ? Carbon::parse($latestAt)->format('d.m.Y H:i')
                : ($snapshotSummary['snapshot_at'] ?? null),
            'snapshot_scope' => 'project',
            'has_data' => $positions->isNotEmpty(),
        ];
    }

    /**
     * KPI по последним позициям выбранного региона (как график % в ТОП).
     *
     * @return array<string, mixed>
     */
    private static function liveForRegion(MonitoringProject $project, int $regionId): array
    {
        /** @var MonitoringSearchengine|null $engine */
        $engine = $project->searchengines()->where('id', $regionId)->first();
        if ($engine === null) {
            return MonitoringProjectPublicStats::summaryFromSnapshot($project);
        }

        $positions = self::latestPositionValues($project, [$engine->id]);
        $calc = new PositionsPercentCalculate($positions);

        $latestAt = MonitoringPosition::query()
            ->where('monitoring_searchengine_id', $engine->id)
            ->max('updated_at');

        $words = (int) $project->keywords()->count();

        return [
            'words' => $words,
            'middle' => $calc->middle(),
            'top1' => $calc->top1(),
            'diff_top1' => null,
            'top3' => $calc->top3(),
            'diff_top3' => null,
            'top5' => $calc->top5(),
            'diff_top5' => null,
            'top10' => $calc->top10(),
            'diff_top10' => null,
            'top30' => $calc->top30(),
            'diff_top30' => null,
            'top100' => $calc->top100(),
            'diff_top100' => null,
            'mastered' => null,
            'mastered_percent' => null,
            'snapshot_at' => $latestAt
                ? Carbon::parse($latestAt)->format('d.m.Y H:i')
                : null,
            'snapshot_scope' => 'region',
            'has_data' => $positions->isNotEmpty(),
        ];
    }

    /**
     * Последние позиции по парам (ключ × регион) — один запрос вместо N подзапросов на строку.
     *
     * @param list<int> $engineIds
     */
    private static function latestPositionValues(MonitoringProject $project, array $engineIds = null): Collection
    {
        if ($engineIds === null) {
            $engineIds = $project->searchengines()->pluck('id')->all();
        }

        if ($engineIds === []) {
            return collect();
        }

        $keywordIds = $project->keywords()->pluck('id')->all();
        if ($keywordIds === []) {
            return collect();
        }

        return MonitoringPosition::query()
            ->select('position')
            ->whereIn('monitoring_searchengine_id', $engineIds)
            ->whereIn('monitoring_keyword_id', $keywordIds)
            ->whereIn('id', function ($query) use ($engineIds, $keywordIds) {
                $query->select(DB::raw('MAX(id)'))
                    ->from('monitoring_positions')
                    ->whereIn('monitoring_searchengine_id', $engineIds)
                    ->whereIn('monitoring_keyword_id', $keywordIds)
                    ->groupBy('monitoring_keyword_id', 'monitoring_searchengine_id');
            })
            ->pluck('position')
            ->filter(function ($pos) {
                return $pos !== null && $pos !== '';
            })
            ->values();
    }
}
