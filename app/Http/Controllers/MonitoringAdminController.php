<?php

namespace App\Http\Controllers;

use App\Classes\Monitoring\StatisticsAdmin;
use App\Jobs;
use App\MonitoringProject;
use App\MonitoringSettings;
use App\Support\MonitoringStaleScheduleReport;
use App\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MonitoringAdminController extends Controller
{
    protected $jobs;
    protected $users;
    protected $projects;
    protected $settings;

    public function __construct()
    {
        $this->middleware(['role:Super Admin|admin']);

        $this->jobs = (new Jobs())->positionsQueue();
        $this->users = new User();
        $this->projects = new MonitoringProject();
        $this->settings = new MonitoringSettings();
    }

    public function statPage(Request $request)
    {
        if ($request->ajax()) {
            return $this->getQueuesForDataTable($request);
        }

        $statistics = (new StatisticsAdmin())->getDashboardStatistics();

        $sites = MonitoringProject::query()
            ->select('url')
            ->distinct()
            ->orderBy('url')
            ->pluck('url', 'url');

        return view('monitoring.admin.stat', compact('statistics', 'sites'));
    }

    public function adminPage()
    {
        $sections = $this->adminSettingsSections();
        $values = $this->settings->getValuesAsArray(
            collect($sections)->flatMap(static function (array $section) {
                return collect($section['fields'])->pluck('name');
            })
        );

        return view('monitoring.admin.admin', [
            'sections' => $sections,
            'values' => $values,
            'staleMonitoring' => Cache::remember(
                'cabinet.monitoring.stale_schedules.summary',
                now()->addMinutes(5),
                static function () {
                    return MonitoringStaleScheduleReport::summary();
                }
            ),
        ]);
    }

    public function staleSchedulesList(Request $request): JsonResponse
    {
        $start = max(0, (int) $request->input('start', 0));
        $length = max(1, min((int) $request->input('length', 25), 100));
        $inactiveDays = (int) $request->input('inactive_days', MonitoringStaleScheduleReport::inactiveDays());
        $freeOnly = $request->boolean('free_only');

        $sortColumns = ['url', 'email', 'last_online_at', 'tariff', 'keywords_count', 'auto_regions'];
        $orderColumn = (int) $request->input('order.0.column', 4);
        $sortBy = $sortColumns[$orderColumn] ?? 'keywords_count';
        $sortDir = strtolower((string) $request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $page = MonitoringStaleScheduleReport::listPage(
            $start,
            $length,
            $inactiveDays,
            $freeOnly,
            $sortBy,
            $sortDir
        );

        return response()->json([
            'draw' => (int) $request->input('draw', 0),
            'recordsTotal' => $page['total'],
            'recordsFiltered' => $page['total'],
            'data' => $page['rows'],
            'summary' => MonitoringStaleScheduleReport::summary($inactiveDays),
        ]);
    }

    public function disableStaleSchedules(Request $request): JsonResponse
    {
        $projectId = (int) $request->input('project_id', 0);
        $userId = (int) $request->input('user_id', 0);

        if ($projectId > 0) {
            $updated = MonitoringStaleScheduleReport::disableProjectSchedule($projectId);
        } elseif ($userId > 0) {
            $updated = MonitoringStaleScheduleReport::disableUserSchedules($userId);
        } else {
            return response()->json(['success' => false, 'message' => __('Users stale monitoring nothing selected')], 422);
        }

        Cache::forget('cabinet.monitoring.stale_schedules.summary');
        Cache::forget('cabinet.users.stale_monitoring.summary');

        return response()->json([
            'success' => true,
            'updated' => $updated,
            'message' => __('Users stale monitoring disabled', ['count' => $updated]),
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function adminSettingsSections(): array
    {
        return [
            'interface' => [
                'icon' => 'bi-layout-text-window',
                'title_key' => 'Monitoring admin section interface',
                'lead_key' => 'Monitoring admin section interface lead',
                'fields' => [
                    [
                        'type' => 'text',
                        'name' => 'pagination_items',
                        'label_key' => 'Monitoring admin field pagination items',
                        'hint_key' => 'Monitoring admin field pagination items hint',
                        'placeholder' => '10,20,30,50,100,200,500,1000',
                        'col' => 12,
                    ],
                    [
                        'type' => 'number',
                        'name' => 'pagination_project',
                        'label_key' => 'Monitoring admin field pagination project',
                        'hint_key' => 'Monitoring admin field pagination project hint',
                        'placeholder' => '10',
                        'col' => 6,
                        'min' => 1,
                        'max' => 500,
                    ],
                    [
                        'type' => 'number',
                        'name' => 'pagination_query',
                        'label_key' => 'Monitoring admin field pagination query',
                        'hint_key' => 'Monitoring admin field pagination query hint',
                        'placeholder' => '100',
                        'col' => 6,
                        'min' => 10,
                        'max' => 2000,
                    ],
                ],
            ],
            'collect' => [
                'icon' => 'bi-speedometer2',
                'title_key' => 'Monitoring admin section collect',
                'lead_key' => 'Monitoring admin section collect lead',
                'fields' => [
                    [
                        'type' => 'number',
                        'name' => 'position_collect_rps',
                        'label_key' => 'Monitoring admin field position rps',
                        'hint_key' => 'Monitoring admin field position rps hint',
                        'placeholder' => '30',
                        'col' => 6,
                        'min' => 1,
                        'max' => 120,
                        'default' => 30,
                    ],
                ],
            ],
            'cron' => [
                'icon' => 'bi-clock-history',
                'title_key' => 'Monitoring admin section cron',
                'lead_key' => 'Monitoring admin section cron lead',
                'fields' => [
                    [
                        'type' => 'time',
                        'name' => 'data_projects',
                        'label_key' => 'Monitoring admin field data projects',
                        'hint_key' => 'Monitoring admin field data projects hint',
                        'placeholder' => '00:00',
                        'col' => 6,
                    ],
                ],
            ],
            'competitors' => [
                'icon' => 'bi-people',
                'title_key' => 'Monitoring admin section competitors',
                'lead_key' => 'Monitoring admin section competitors lead',
                'fields' => [
                    [
                        'type' => 'textarea',
                        'name' => 'ignored_domains',
                        'label_key' => 'Monitoring admin field ignored domains',
                        'hint_key' => 'Monitoring admin field ignored domains hint',
                        'placeholder' => 'example.com',
                        'col' => 12,
                        'rows' => 4,
                    ],
                ],
            ],
            'storage' => [
                'icon' => 'bi-database-gear',
                'title_key' => 'Monitoring admin section storage',
                'lead_key' => 'Monitoring admin section storage lead',
                'fields' => [
                    [
                        'type' => 'number',
                        'name' => 'search_indices_days_delete',
                        'label_key' => 'Monitoring admin field search indices retention',
                        'hint_key' => 'Monitoring admin field search indices retention hint',
                        'hint_detail_key' => 'Monitoring admin field search indices retention detail',
                        'placeholder' => '180',
                        'col' => 12,
                        'min' => 0,
                        'max' => 3650,
                        'default' => 30,
                        'cmd' => 'php artisan search-indices:count',
                    ],
                    [
                        'type' => 'number',
                        'name' => 'free_tariff_positions_retention_days',
                        'label_key' => 'Monitoring admin field free positions retention',
                        'hint_key' => 'Monitoring admin field free positions retention hint',
                        'placeholder' => '365',
                        'col' => 12,
                        'min' => 0,
                        'max' => 3650,
                        'default' => (int) config('cabinet-monitoring.free_tariff_positions_retention_days', 365),
                        'cmd' => 'php artisan monitoring:prune-free-positions --dry-run',
                    ],
                    [
                        'type' => 'number',
                        'name' => 'competitors_changes_dates_retention_days',
                        'label_key' => 'Monitoring admin field dynamics retention',
                        'hint_key' => 'Monitoring admin field dynamics retention hint',
                        'placeholder' => '180',
                        'col' => 12,
                        'min' => 0,
                        'max' => 3650,
                        'default' => (int) config('cabinet-monitoring.competitors_changes_dates_retention_days', 180),
                        'cmd' => 'php artisan monitoring:prune-competitors-dynamics --dry-run',
                    ],
                ],
            ],
        ];
    }

    public function deleteQueues(Request $request)
    {
        if ($request->has('delete_queues')) {

            $this->jobs->delete();
            flash()->overlay(__('Delete successfully'), __('Delete queues'))->success();
        } else {

            $queues = collect([]);
            if ($request->filled(['user', 'project'])) {

                $queues = $this->jobs->get()->filter(function ($item) use ($request) {

                    $jobData = unserialize($item->payload['data']['command']);
                    $keyword = $jobData->getModel();

                    return ($keyword->project->url == $request->input('project') && $keyword->project->admin->first()->id == $request->input('user'));
                });
            } else {

                $params = collect($request->only(['user', 'project']))->filter();
                if ($params->isNotEmpty()) {

                    $queues = $this->jobs->get()->filter(function ($item) use ($params) {

                        $jobData = unserialize($item->payload['data']['command']);
                        $keyword = $jobData->getModel();

                        if (array_key_exists('user', $params->toArray())) {
                            return ($keyword->project->admin->first()->id == $params['user']);
                        }

                        if (array_key_exists('project', $params->toArray())) {
                            return ($keyword->project->url == $params['project']);
                        }

                    });
                }
            }

            if ($queues->isNotEmpty()) {
                $this->jobs->whereIn('id', $queues->pluck('id'))->delete();
                flash()->overlay('Удалено ' . $queues->count(), __('Delete queues'))->success();
            }
        }

        return redirect()->back();
    }

    public function getQueuesForDataTable(Request $request)
    {
        $dataTable = collect([]);

        $page = ($request->input('start') / $request->input('length')) + 1;
        $queues = $this->getQueuesOnPage($request->input('length', 1), $page);

        foreach ($queues->getCollection() as $item) {

            $dataTable->push([
                'id' => $item->id,
                'user' => $item->keyword->project->admin->first()->fullName,
                'email' => $item->keyword->project->admin->first()->email,
                'site' => $item->keyword->project->url,
                'group' => $item->keyword->group->name,
                'params' => $item->keyword->params,
                'query' => $item->keyword->query,
                'priority' => ($item->queue === 'position_high') ? __('High') : __('Low'),
                'created_at' => $item->created_at->format('d.m.Y H:i:s'),
                'attempts' => $item->attempts,
            ]);
        }

        return collect([
            'data' => $dataTable,
            'draw' => $request->input('draw'),
            'recordsFiltered' => $queues->total(),
            'recordsTotal' => $queues->total(),
        ]);
    }

    protected function getQueuesOnPage($length, $page)
    {
        $forgetKeys = [];
        $jobs = $this->jobs->paginate($length, ['*'], 'page', $page);

        $jobs->getCollection()->transform(function ($item, $key) use (&$forgetKeys) {
            try {
                $jobData = unserialize($item->payload['data']['command']);
                $item->keyword = $jobData->getModel();
                $item->keyword->params = $jobData->getParams();
            } catch (ModelNotFoundException $e) {
                $forgetKeys[] = $key;
                $item->delete();
            }

            return $item;
        });

        if (count($forgetKeys) > 0) {
            $jobs->forget($forgetKeys);
        }

        return $jobs;
    }
}
