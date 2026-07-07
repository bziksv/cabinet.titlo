<?php

namespace App\Http\Controllers;


use App\Classes\Monitoring\Queues\OccurrenceDispatch;
use App\Jobs\EnqueueMonitoringOccurrenceJob;
use App\Jobs\EnqueueMonitoringOccurrenceKeysJob;
use App\Monitoring\Services\MonitoringUserService;
use App\MonitoringProject;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MonitoringOccurrenceController extends Controller
{
    protected $user;

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();

            return $next($request);
        });
    }

    public function update(Request $request)
    {
        return $this->updateByProjectId($request->input('id'));
    }

    public function updateKeys(Request $request)
    {
        $this->authorize('update_occurrence_monitoring');

        /** @var User $user */
        $user = $this->user;

        $project = $user->monitoringProjects()->find($request->input('projectId'));
        if (!$project instanceof MonitoringProject) {
            return response()->json([
                'status' => false,
                'error' => __('Monitoring parse queue error'),
            ]);
        }

        $keywordIds = array_values(array_map('intval', (array) $request->input('keys', [])));
        $regionId = (int) $request->input('region');
        $engine = $project->searchengines()->find($regionId);

        if ($engine === null || $keywordIds === []) {
            return response()->json([
                'status' => false,
                'error' => __('Monitoring parse select region'),
            ]);
        }

        if ($engine->engine !== 'yandex') {
            return response()->json([
                'status' => false,
                'error' => __('Monitoring occurrence yandex only'),
            ]);
        }

        if ($userAdmin = (new MonitoringUserService())->getMonitoringAdminUser($project)) {
            $user = $userAdmin;
        }

        $keywords = $project->keywords()->whereIn('id', $keywordIds)->get();
        if ($keywords->isEmpty()) {
            return response()->json([
                'status' => false,
                'error' => __('Monitoring keyword delete select one'),
            ]);
        }

        $queue = new OccurrenceDispatch($user['id'], 'high');
        if (!$queue->reserveForPairs($keywords->count())) {
            return $queue->notify();
        }

        EnqueueMonitoringOccurrenceKeysJob::dispatch(
            (int) $project->id,
            $regionId,
            $keywords->pluck('id')->all(),
            'high'
        );

        return $queue->notify();
    }

    protected function updateByProjectId($id)
    {
        $this->authorize('update_occurrence_monitoring');

        /** @var User $user */
        $user = $this->user;
        $project = $user->monitoringProjects()->find($id);
        if (!$project instanceof MonitoringProject) {
            return response()->json([
                'status' => false,
                'error' => __('Monitoring parse queue error'),
            ]);
        }

        $regions = $project->searchengines->where('engine', 'yandex');
        if ($regions->isEmpty()) {
            return response()->json([
                'status' => false,
                'error' => __('Monitoring occurrence yandex only'),
            ]);
        }

        if ($userAdmin = (new MonitoringUserService())->getMonitoringAdminUser($project)) {
            $user = $userAdmin;
        }

        $keywordCount = (int) $project->keywords()->count();
        $pairCount = $regions->count() * $keywordCount;

        $queue = new OccurrenceDispatch($user['id'], 'high');
        if (!$queue->reserveForPairs($pairCount)) {
            return $queue->notify();
        }

        EnqueueMonitoringOccurrenceJob::dispatch((int) $project->id, 'high');

        return $queue->notify();
    }

}
