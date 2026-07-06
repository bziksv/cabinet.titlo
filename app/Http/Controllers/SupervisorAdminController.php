<?php

namespace App\Http\Controllers;

use App\Services\Queue\QueueInventoryService;
use App\Services\Supervisor\SupervisorAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupervisorAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware(['role:Super Admin|admin']);
    }

    public function index(SupervisorAdminService $supervisor, QueueInventoryService $queues): View
    {
        $probe = $supervisor->probe();
        $processes = $probe['ok'] ? $supervisor->processes() : [];
        $queueSnapshot = $queues->getSnapshot(request()->boolean('fresh'));
        $capacity = $probe['ok'] ? $supervisor->capacityOverview($queueSnapshot) : null;
        $logProgram = (string) request()->query('log', '');
        $logTail = $logProgram !== '' ? $supervisor->tailLog($logProgram) : null;

        return view('admin.supervisor.index', [
            'probe' => $probe,
            'processes' => $processes,
            'capacity' => $capacity,
            'queueSnapshot' => $queueSnapshot,
            'logTail' => $logTail,
            'logProgram' => $logProgram,
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
}
