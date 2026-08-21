<?php

namespace App\Http\Controllers;

use App\MonitoringProject;
use App\SearchIndex;
use App\Support\HeavyDb;
use Illuminate\Http\Request;

class MonitoringTopController extends Controller
{
    public function index(MonitoringProject $project)
    {
        apply_team_permissions($project->id);

        $project->load([
            'keywords',
            'searchengines.location',
            'competitors',
        ]);

        return view('monitoring.top100.index', compact('project'));
    }

    public function getTopSites(Request $request)
    {
        $dates = $request->input('dates');
        if (is_array($dates) && $dates !== []) {
            return $this->topSitesByDates($request, $dates);
        }

        $date = (string) $request->input('date', '');
        if ($date === '') {
            return [];
        }

        return $this->topSitesByDates($request, [$date])[$date] ?? [];
    }

    /**
     * Один запрос на весь период вместо N по дням.
     *
     * @param  list<string>  $dates  YYYY-MM-DD
     * @return array<string, list<array{position:mixed,url:mixed,created_at:mixed}>>
     */
    private function topSitesByDates(Request $request, array $dates): array
    {
        $word = trim((string) $request->input('word', ''));
        $region = $request->input('region');
        $dates = array_values(array_unique(array_filter(array_map(static function ($d) {
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $d) ? (string) $d : null;
        }, $dates))));

        $empty = [];
        foreach ($dates as $d) {
            $empty[$d] = [];
        }

        if ($word === '' || $region === null || $region === '' || $dates === []) {
            return $empty;
        }

        sort($dates);
        $from = $dates[0] . ' 00:00:00';
        $to = $dates[count($dates) - 1] . ' 23:59:59';
        $wanted = array_flip($dates);
        $byDate = $empty;

        try {
            $rows = HeavyDb::table('search_indices')
                ->where('query', $word)
                ->where('lr', $region)
                ->whereBetween('created_at', [$from, $to])
                ->orderByDesc('id')
                ->limit(max(500, count($dates) * 150))
                ->get(['position', 'url', 'created_at']);
        } catch (\Throwable $e) {
            $rows = SearchIndex::query()
                ->where('query', $word)
                ->where('lr', $region)
                ->whereBetween('created_at', [$from, $to])
                ->orderByDesc('id')
                ->limit(max(500, count($dates) * 150))
                ->get(['position', 'url', 'created_at']);
        }

        foreach ($rows as $row) {
            $created = (string) ($row->created_at ?? '');
            $day = strlen($created) >= 10 ? substr($created, 0, 10) : '';
            if ($day === '' || !isset($wanted[$day]) || count($byDate[$day]) >= 100) {
                continue;
            }
            $byDate[$day][] = [
                'position' => $row->position,
                'url' => $row->url,
                'created_at' => $row->created_at,
            ];
        }

        return $byDate;
    }
}
