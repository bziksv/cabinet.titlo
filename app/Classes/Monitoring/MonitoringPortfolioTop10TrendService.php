<?php

namespace App\Classes\Monitoring;

use App\MonitoringPosition;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Средний ТОП-10 по портфелю: на каждую дату — все проекты с историей;
 * если съёма в этот день нет — ближайшее известное значение проекта (интерполяция).
 */
class MonitoringPortfolioTop10TrendService
{
    public const ALLOWED_DAYS = [30, 60, 90, 180, 366];

    private const MAX_PROJECTS = 100;

    private const PROJECT_CHUNK = 5;

    public function seriesForUser(User $user, array $projectIds, int $days, string $range = 'weeks'): array
    {
        if (!in_array($days, self::ALLOWED_DAYS, true)) {
            $days = 90;
        }
        if (!in_array($range, ['days', 'weeks', 'month'], true)) {
            $range = 'weeks';
        }

        $projects = $this->resolveProjects($user, $projectIds);
        if ($projects->isEmpty()) {
            return $this->emptyPayload($days, $range, 0);
        }

        $ids = $projects->pluck('id')->map(function ($id) {
            return (int) $id;
        })->all();

        $requested = count($projectIds) > 0 ? count(array_unique(array_map('intval', $projectIds))) : 0;

        sort($ids);
        $cacheKey = sprintf(
            'mon_v2_portfolio_trend:%d:%d:%s:%s:v4',
            (int) $user->id,
            $days,
            $range,
            md5(implode(',', $ids))
        );

        $payload = Cache::remember($cacheKey, 300, function () use ($ids, $days, $range) {
            return $this->buildSeries($ids, $days, $range);
        });

        $payload['projects_used'] = count($ids);
        $payload['interpolation'] = 'nearest';
        if ($requested > count($ids)) {
            $payload['projects_capped'] = true;
            $payload['projects_requested'] = $requested;
        }

        return $payload;
    }

    /**
     * @return \Illuminate\Support\Collection<int, \App\MonitoringProject>
     */
    private function resolveProjects(User $user, array $projectIds)
    {
        $query = $user->monitoringProjects()->orderBy('monitoring_projects.id');

        if ($projectIds !== []) {
            $filter = array_values(array_unique(array_map('intval', $projectIds)));
            $filter = array_filter($filter, function ($id) {
                return $id > 0;
            });
            if ($filter !== []) {
                $query->whereIn('monitoring_projects.id', $filter);
            }
        }

        return $query->limit(self::MAX_PROJECTS)->get(['monitoring_projects.id']);
    }

    /**
     * @param int[] $projectIds
     */
    private function buildSeries(array $projectIds, int $days, string $range): array
    {
        @ini_set('memory_limit', '512M');

        /** @var array<int, array<string, float>> $perProject */
        $perProject = [];

        foreach (array_chunk($projectIds, self::PROJECT_CHUNK) as $chunk) {
            foreach ($chunk as $pid) {
                $pid = (int) $pid;
                $slice = $this->seriesSliceForProject($pid, $days, $range);
                if ($slice !== []) {
                    $perProject[$pid] = $slice;
                }
            }
        }

        $projectsWithHistory = count($perProject);
        if ($projectsWithHistory === 0) {
            return $this->emptyPayload($days, $range, count($projectIds), true);
        }

        $allLabels = $this->collectSortedLabels($perProject);
        $labels = [];
        $values = [];

        foreach ($allLabels as $label) {
            $sum = 0.0;
            foreach ($perProject as $sparse) {
                $value = $this->valueAtLabelWithNearest($sparse, $label);
                if ($value !== null) {
                    $sum += $value;
                }
            }
            $labels[] = $label;
            $values[] = round($sum / $projectsWithHistory, 2);
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'days' => $days,
            'range' => $range,
            'projects' => count($projectIds),
            'projects_with_history' => $projectsWithHistory,
            'empty' => false,
        ];
    }

    /**
     * @param array<int, array<string, float>> $perProject
     * @return list<string>
     */
    private function collectSortedLabels(array $perProject): array
    {
        $set = [];
        foreach ($perProject as $sparse) {
            foreach (array_keys($sparse) as $label) {
                $set[$label] = true;
            }
        }
        $labels = array_keys($set);
        usort($labels, function ($a, $b) {
            return $this->labelTimestamp($a) <=> $this->labelTimestamp($b);
        });

        return $labels;
    }

    /**
     * @param array<string, float> $sparse
     */
    private function valueAtLabelWithNearest(array $sparse, string $label): ?float
    {
        if (isset($sparse[$label])) {
            return $sparse[$label];
        }

        $targetTs = $this->labelTimestamp($label);
        if ($targetTs === null) {
            return null;
        }

        $prevVal = null;
        $prevTs = null;
        $nextVal = null;
        $nextTs = null;

        foreach ($sparse as $l => $v) {
            $ts = $this->labelTimestamp($l);
            if ($ts === null) {
                continue;
            }
            if ($ts <= $targetTs && ($prevTs === null || $ts > $prevTs)) {
                $prevTs = $ts;
                $prevVal = $v;
            }
            if ($ts >= $targetTs && ($nextTs === null || $ts < $nextTs)) {
                $nextTs = $ts;
                $nextVal = $v;
            }
        }

        if ($prevVal !== null && $nextVal !== null) {
            $dPrev = $targetTs - $prevTs;
            $dNext = $nextTs - $targetTs;

            return $dPrev <= $dNext ? $prevVal : $nextVal;
        }
        if ($prevVal !== null) {
            return $prevVal;
        }
        if ($nextVal !== null) {
            return $nextVal;
        }

        return null;
    }

    private function labelTimestamp(string $label): int
    {
        $dt = \DateTime::createFromFormat('d.m.Y', $label);

        return $dt ? $dt->getTimestamp() : 0;
    }

    /**
     * @return array<string, float>
     */
    private function seriesSliceForProject(int $projectId, int $days, string $range): array
    {
        $keywordIds = DB::table('monitoring_keywords')
            ->where('monitoring_project_id', $projectId)
            ->pluck('id')
            ->map(function ($id) {
                return (int) $id;
            })
            ->all();

        if ($keywordIds === []) {
            return [];
        }

        $end = Carbon::today()->endOfDay();
        $start = Carbon::today()->subDays($days)->startOfDay();

        $positions = MonitoringPosition::query()
            ->whereIn('monitoring_keyword_id', $keywordIds)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<=', $end)
            ->orderByDesc('created_at')
            ->get(['monitoring_keyword_id', 'monitoring_searchengine_id', 'position', 'created_at']);

        if ($positions->isEmpty()) {
            return [];
        }

        $byDate = $this->lastPositionsByDay($positions);
        $byDate = $this->applyRangeGrouping($byDate, $range);

        $out = [];
        foreach ($byDate as $label => $tops) {
            $out[(string) $label] = Helper::calculateTopPercentByPositions($tops, 10);
        }

        return $out;
    }

    private function emptyPayload(int $days, string $range, int $projects, bool $noPositions = false): array
    {
        return [
            'labels' => [],
            'values' => [],
            'days' => $days,
            'range' => $range,
            'projects' => $projects,
            'projects_with_history' => 0,
            'empty' => true,
            'no_positions' => $noPositions,
        ];
    }

    /**
     * @param \Illuminate\Support\Collection<int, object> $positions
     */
    private function lastPositionsByDay(Collection $positions): Collection
    {
        return $positions
            ->groupBy(function ($row) {
                return $this->positionCarbon($row)->format('d.m.Y');
            })
            ->map(function (Collection $items) {
                return $items
                    ->sortByDesc(function ($row) {
                        return $this->positionCarbon($row)->timestamp;
                    })
                    ->unique(function ($row) {
                        return $row->monitoring_keyword_id . '-' . $row->monitoring_searchengine_id;
                    })
                    ->pluck('position');
            });
    }

    /**
     * @param \Illuminate\Support\Collection<string, \Illuminate\Support\Collection> $byDay
     */
    private function applyRangeGrouping(Collection $byDay, string $range): Collection
    {
        $sorted = $byDay->sortBy(function ($item, $key) {
            return $this->labelTimestamp((string) $key);
        });

        if ($range === 'weeks') {
            $filtered = collect([]);
            $currentWeek = null;
            foreach ($sorted as $date => $positions) {
                $carbon = Carbon::createFromFormat('d.m.Y', (string) $date);
                if ($carbon === false) {
                    continue;
                }
                $week = (int) $carbon->format('W');
                if ($currentWeek === null || $currentWeek !== $week) {
                    $filtered->put($date, $positions);
                }
                $currentWeek = $week;
            }

            return $filtered;
        }

        if ($range === 'month') {
            return $sorted->unique(function ($item, $key) {
                $carbon = Carbon::createFromFormat('d.m.Y', (string) $key);
                if ($carbon === false) {
                    return (string) $key;
                }

                return $carbon->format('m.Y');
            });
        }

        return $sorted;
    }

    /**
     * @param object $row
     */
    private function positionCarbon($row): Carbon
    {
        $at = $row->created_at;
        if ($at instanceof Carbon) {
            return $at;
        }

        return Carbon::parse($at);
    }
}
