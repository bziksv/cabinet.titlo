<?php

namespace App\Http\Controllers;

use App\Services\Queue\QueueDailyStatsService;
use App\Services\Queue\QueueInventoryService;
use App\Services\Supervisor\SupervisorAdminService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupervisorAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware(['role:Super Admin|admin']);
    }

    public function index(
        SupervisorAdminService $supervisor,
        QueueInventoryService $queues,
        QueueDailyStatsService $dailyStats
    ): View {
        $probe = $supervisor->probe();
        $processes = $probe['ok'] ? $supervisor->processes() : [];
        $queueSnapshot = $queues->getSnapshot(request()->boolean('fresh'));
        $capacity = $probe['ok'] ? $supervisor->capacityOverview($queueSnapshot) : null;
        $logProgram = (string) request()->query('log', '');
        $logTail = $logProgram !== '' ? $supervisor->tailLog($logProgram) : null;

        $statsDate = request()->query('stats_date');
        try {
            $statsDay = $statsDate ? Carbon::parse($statsDate)->startOfDay() : Carbon::today();
        } catch (\Throwable $e) {
            $statsDay = Carbon::today();
        }

        $dailyReport = $dailyStats->isEnabled()
            ? $dailyStats->getReportForDate($statsDay)
            : null;
        $dailyRecent = $dailyStats->isEnabled()
            ? $dailyStats->getRecentDays(7)
            : [];

        return view('admin.supervisor.index', [
            'probe' => $probe,
            'processes' => $processes,
            'capacity' => $capacity,
            'queueSnapshot' => $queueSnapshot,
            'logTail' => $logTail,
            'logProgram' => $logProgram,
            'dailyReport' => $dailyReport,
            'dailyRecent' => $dailyRecent,
            'statsDay' => $statsDay,
        ]);
    }

    public function action(Request $request, SupervisorAdminService $supervisor): RedirectResponse
    {
        $program = (string) $request->input('program', '');
        $action = (string) $request->input('action', '');

        $result = $supervisor->control($program, $action);

        return redirect()
            ->route('admin.supervisor.index')
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function actionAll(Request $request, SupervisorAdminService $supervisor): RedirectResponse
    {
        $action = (string) $request->input('action', '');
        $result = $supervisor->controlAll($action);

        return redirect()
            ->route('admin.supervisor.index')
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    }
}
