<?php

namespace App\Http\Controllers;

use App\MonitoringProject;
use App\SearchIndex;
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
        return SearchIndex::select('position', 'url', 'created_at')
            ->whereDate('created_at', $request->date)
            ->where('lr', $request->region)
            ->where('query', $request->word)
            ->orderBy('search_indices.id', 'desc')
            ->take(100)
            ->get();
    }
}
