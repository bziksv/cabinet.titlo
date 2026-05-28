<?php

namespace App\Http\Controllers;

use App\Classes\Monitoring\Helper;
use App\Classes\Monitoring\Mastered;
use App\Classes\Monitoring\PanelButtons\SimpleButtonsFactory;
use App\Classes\Monitoring\ProjectData;
use App\Classes\Monitoring\Queues\PositionsDispatch;
use App\Common;
use App\Events\MonitoringProjectBeforeDelete;
use App\Events\MonitoringProjectCopyProgress;
use App\Events\MonitoringProjectCreated;
use App\Jobs\Monitoring\MonitoringChangesDateQueue;
use App\Jobs\Monitoring\MonitoringCompetitorsQueue;
use App\Jobs\MonitoringProjectCopyJob;
use App\Mail\MonitoringApproveProjectMail;
use App\Mail\MonitoringShareProjectMail;
use App\Monitoring\Services\MonitoringUserService;
use App\MonitoringChangesDate;
use App\MonitoringColumn;
use App\MonitoringCompetitor;
use App\MonitoringCompetitorsResult;
use App\MonitoringDataTableColumnsProject;
use App\MonitoringKeyword;
use App\MonitoringKeywordPrice;
use App\MonitoringPosition;
use App\MonitoringProject;
use App\MonitoringProjectColumnsSetting;
use App\MonitoringProjectSettings;
use App\MonitoringSearchengine;
use App\MonitoringSettings;
use App\Project;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;

class MonitoringController extends Controller
{
    protected $user;

    protected $subtractionMonths = [0, 1, 3, 6, 12];

    /**
     * ProfilesController constructor.
     */
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();

            return $next($request);
        });
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        /** @var User $user */
        $user = $this->user;

        $count = $user->monitoringProjects()->count();

        return view('monitoring.index', compact( 'count'));
    }

    public function copy($id)
    {
        /** @var User $user */
        $user = $this->user;

        $original = MonitoringProject::findOrFail($id);

        $newProject = $original->replicate();
        $newProject->name = $original->name . ' (копия)';
        $newProject->created_at = Carbon::now();
        $newProject->updated_at = Carbon::now();
        $newProject->save();

        $user->monitoringProjects()->syncWithoutDetaching([
            $newProject->id => ['approved' => 1]
        ]);

        event(new MonitoringProjectCreated($user, $newProject));

        event(new MonitoringProjectCopyProgress($user->id, "Проект создан, копирование запущено"));

        MonitoringProjectCopyJob::dispatch($user->id, $newProject, $original);
    }

    public function attachUser(Request $request)
    {
        /** @var User $user */
        $currentUser = $this->user;
        $emails = explode(",", str_replace(" ", "", $request->input('email')));
        $users = User::whereIn('email', $emails)->get()->whereNotIn('id', [$currentUser['id']]);

        if ($users->isEmpty())
            return abort('403');

        $id = $request->input('id');

        foreach ($users as $user) {
            if ($user->monitoringProjects()->find($id) === null) {

                $result = $user->monitoringProjects()->syncWithoutDetaching([$id => ['approved' => 0]]);

                if (count($result['attached']) > 0) {
                    Mail::to($user)->send(new MonitoringShareProjectMail(MonitoringProject::find($id)));

                    apply_team_permissions($id);

                    $user->assignRole($request->input('status'));
                }
            }
        }

        return $users->count();
    }

    public function approveOrDetachUser(Request $request)
    {
        $id = $request->input('id');
        $approve = $request->input('approve');

        apply_team_permissions($id);

        /** @var User $user */
        $user = $this->user;

        if ($approve) {
            $project = $user->monitoringProjects()->find($id);

            foreach($project->users as $project_user) {
                if ($project_user->hasRole('admin_monitoring')) {
                    Mail::to($project_user)->send(new MonitoringApproveProjectMail($user, $project));
                }
            }

            return $user->monitoringProjects()->updateExistingPivot($id, ["approved" => 1]);
        }

        $user->syncRoles([]);

        return $user->monitoringProjects()->detach($id);
    }

    public function detachUser(Request $request)
    {
        $projectId = $request->input('project_id');
        $userId = $request->input('user_id');

        apply_team_permissions($projectId);

        $user = User::findOrFail($userId);
        $project = $user->monitoringProjects()->findOrFail($projectId);

        $user->syncRoles([]);

        return $user->monitoringProjects()->detach($project['id']);
    }

    public function parsePositionsInProject(Request $request)
    {
        /** @var User $user */
        $user = $this->user;

        $project = $user->monitoringProjects()->find($request->input('projectId'));

        if (!$project)
            return collect(['status' => false]);

        $engines = $project->searchengines()->whereIn('id', $request->input('regions'))->get();

        if ($userAdmin = (new MonitoringUserService())->getMonitoringAdminUser($project)) {
            $user = $userAdmin;
        }

        $queue = new PositionsDispatch($user['id'], 'position_high');

        foreach ($engines as $engine) {
            foreach ($project->keywords as $query)
                $queue->addQueryWithRegion($query, $engine);
        }
        $queue->dispatch();

        return $queue->notify();
    }

    public function parsePositionsInProjectKeys(Request $request)
    {
        /** @var User $user */
        $user = $this->user;

        $project = $user->monitoringProjects()->find($request->input('projectId'));
        $keywords = $project->keywords()->whereIn('id', $request->input('keys'))->get();
        $engine = $project->searchengines()->find($request->input('region'));

        if ($userAdmin = (new MonitoringUserService())->getMonitoringAdminUser($project)) {
            $user = $userAdmin;
        }

        $queue = new PositionsDispatch($user['id'], 'position_high');

        foreach ($keywords as $keyword)
            $queue->addQueryWithRegion($keyword, $engine);

        $queue->dispatch();

        return $queue->notify();
    }

    public function getProjects(Request $request)
    {
        $length = max(1, (int) $request->input('length', 1));
        $start = max(0, (int) $request->input('start', 0));
        if ($length > 15) {
            set_time_limit(180);
        }
        $page = (int) floor($start / $length) + 1;

        /** @var User $user */
        $user = $this->user;
        $model = $user->monitoringProjectsDataTable();

        $projects = $this->extendFields($model->paginate($length, ['*'], 'page', $page));

        $this->enrichProjectsForList($projects);

        return $projects->items();
    }

    /**
     * Метрики из кэша (monitoring_data_table_columns_projects) + колонки UI без тяжёлого пересчёта.
     *
     * @param \Illuminate\Support\Collection|\Illuminate\Contracts\Pagination\Paginator $projects
     */
    public function enrichProjectsForList($projects): void
    {
        $collection = $projects instanceof \Illuminate\Pagination\AbstractPaginator
            ? $projects->getCollection()
            : collect($projects);

        if ($collection->isEmpty()) {
            return;
        }

        $cached = MonitoringDataTableColumnsProject::query()
            ->whereIn('monitoring_project_id', $collection->pluck('id'))
            ->get()
            ->keyBy('monitoring_project_id');

        $metricKeys = [
            'words', 'middle', 'top3', 'top5', 'top10', 'top30', 'top100',
            'mastered', 'mastered_percent',
        ];

        foreach ($collection as $project) {
            apply_team_permissions($project->id);

            if ($row = $cached->get($project->id)) {
                $project->fill($row->only($metricKeys));
            }

            $project->users_column = view('monitoring.partials.users-column', ['project' => $project])->render();
            $project->dropdown_menu = view('monitoring.partials.dropdown-menu', ['project' => $project])->render();

            apply_global_team_permissions();
        }
    }

    public function searchColumnByName(string $name, array $columns)
    {
        foreach ($columns as $key => $col)
            if ($col['name'] === $name)
                return $columns[$key];

        return null;
    }

    public function getCountProject($id)
    {
        $collection = collect([
            'queries' => 0,
            'regions' => 0,
            'region_google' => 0,
            'region_yandex' => 0,
        ]);

        /** @var User $user */
        $user = $this->user;
        $project = $user->monitoringProjects()->findOrFail($id);

        $collection->put('queries', $project->keywords()->count());
        $collection->put('regions', $project->searchengines()->count());
        $collection->put('region_google', $project->searchengines()->where('engine', 'google')->count());
        $collection->put('region_yandex', $project->searchengines()->where('engine', 'yandex')->count());

        return $collection;
    }

    public function extendFields($projects)
    {
        $projects->transform(function ($item) {
            $item->load(['searchengines' => function ($query) {
                $query->groupBy('engine');
            }]);

            $item->engines = $item->searchengines->pluck('engine')->map(function ($item) {
                return '<span class="badge badge-light"><i class="fab fa-' . $item . ' fa-sm"></i></span>';
            })->implode(' ');

            $item->users->transform(function ($user) {
                $statusId = $user['pivot']['status'];
                $user['status'] = MonitoringProjectUserStatusController::getStatusById($statusId);
                return $user;
            });

            return $item;
        });

        return $projects;
    }

    public function getChildRowsPageByProject(int $project_id, $group_id = null)
    {
        /** @var User $user */
        $user = $this->user;

        $html = app(\App\Classes\Monitoring\MonitoringChildRowsService::class)
            ->htmlForProject($user, $project_id, $group_id);

        return response($html);
    }

    public function getSubtractionMonths(): array
    {
        return $this->subtractionMonths;
    }

    /**
     * @param Collection|null $priceByKeyword предзагруженные цены (monitoring_keyword_id => row)
     * @param bool $withinMonthDelta true — дельта в скобках: первая vs вторая проверка внутри месяца (legacy)
     */
    public function calculateTopPercent(
        Collection $positions,
        $model,
        $priceByKeyword = null,
        bool $withinMonthDelta = true
    ) {
        $engine = clone $model;

        $percents = [
            'top_1' => 1,
            'top_3' => 3,
            'top_5' => 5,
            'top_10' => 10,
            'top_20' => 20,
            'top_50' => 50,
            'top_100' => 100,
        ];

        $pos = $this->getLastCoupleOfPositions($positions);
        if ($priceByKeyword === null) {
            $priceByKeyword = $this->preloadKeywordPrices($model->id, $pos);
        }

        foreach ($percents as $name => $percent) {
            $first = Helper::calculateTopPercentByPositions($pos->pluck('first.position'), $percent);
            if ($withinMonthDelta) {
                $last = Helper::calculateTopPercentByPositions($pos->pluck('last.position'), $percent);
                $engine->$name = $first . Helper::differentTopPercent($first, $last);
            } else {
                $engine->{$name . '_raw'} = $first;
                $engine->$name = (string) $first;
            }
        }
        $engine->middle_position = round($pos->pluck('first')->sum('position') / $pos->pluck('first')->count(), 2);
        $engine->latest_created = $pos->pluck('first')->last()->created_at;

        $mastered = new Mastered($pos->pluck('first'), $priceByKeyword);
        $engine->mastered = $mastered->total();
        $engine->mastered_percent = $mastered->percentOf($engine->project['budget']);
        $engine->mastered_percent_day = $mastered->percentOfDay($engine->project['budget']);

        return $engine;
    }

    public function getLastCoupleOfPositions(Collection $positions)
    {
        $couples = [];
        foreach ($positions as $row) {
            $kid = $row->monitoring_keyword_id;
            if (!isset($couples[$kid])) {
                $couples[$kid] = ['first' => $row, 'last' => null];
            } elseif ($couples[$kid]['last'] === null) {
                $couples[$kid]['last'] = $row;
            }
        }

        return collect($couples)->map(function ($pair) {
            return collect([
                'first' => $pair['first'],
                'last' => $pair['last'] ?: [],
            ]);
        });
    }

    /**
     * @param Collection $pos результат getLastCoupleOfPositions
     */
    private function preloadKeywordPrices(int $engineId, Collection $pos)
    {
        $keywordIds = $pos->pluck('first.monitoring_keyword_id')->filter()->unique()->values();

        if ($keywordIds->isEmpty()) {
            return collect([]);
        }

        return MonitoringKeywordPrice::query()
            ->where('monitoring_searchengine_id', $engineId)
            ->whereIn('monitoring_keyword_id', $keywordIds)
            ->get()
            ->keyBy('monitoring_keyword_id');
    }

    public function groupPositionsByMonth($positions, int $subMonth = null)
    {
        $positions = clone $positions;

        $date = explode('-', Carbon::now()->subMonths($subMonth)->format('Y-m'));
        $collection = $positions->whereYear('created_at', $date[0])->whereMonth('created_at', $date[1])->get();

        if ($collection->isEmpty())
            return null;

        return $collection;
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        return view('monitoring.create');
    }

    /**
     * Display the specified resource.
     *
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function show($id)
    {
        apply_team_permissions($id);

        /** @var User $user */
        $user = $this->user;

        /** @var MonitoringProject $project */
        $project = $user->monitoringProjects()
            ->withCount('competitors')
            ->with(['backlinks:id,monitoring_project_id,total_link,total_broken_link'])
            ->findOrFail($id);
        $navigations = $this->navigations($project);

        $length = $this->getLength($project->id);
        $lengthMenu = $this->getPaginationMenu();

        return view('monitoring.show', compact('navigations', 'project', 'length', 'lengthMenu'));
    }

    public function getPaginationMenu()
    {
        $lengthMenu = '[10,20,30,50]';

        if ($global = $this->globalMonitoringSetting('pagination_items')) {
            $lengthMenu = '[' . $global . ']';
        }

        return $lengthMenu;
    }

    public function getLength(int $projectId)
    {
        $lengthDefault = 100;

        if ($global = $this->globalMonitoringSetting('pagination_query')) {
            $lengthDefault = (int) $global;
        }

        if ($length = $this->getSetting($projectId, 'length')) {
            $lengthDefault = $length->value;
        }

        return $lengthDefault;
    }

    private static $globalMonitoringSettings;

    private function globalMonitoringSetting(string $name)
    {
        if (self::$globalMonitoringSettings === null) {
            self::$globalMonitoringSettings = MonitoringSettings::query()
                ->whereIn('name', ['pagination_items', 'pagination_query'])
                ->pluck('value', 'name');
        }

        $value = self::$globalMonitoringSettings->get($name);

        return $value !== null && $value !== '' ? $value : false;
    }

    public function getColumnSettings()
    {
        $user = $this->user;
        return MonitoringColumn::where(['user_id' => $user['id']])->get();
    }

    public function setColumnSettings(Request $request)
    {
        $user = $this->user;
        MonitoringColumn::updateOrCreate(
            ['user_id' => $user['id'], 'column' => $request->input('column')],
            ['state' => $request->input('state')]
        );
    }

    public function setColumnSettingsForProject(Request $request)
    {
        MonitoringProjectColumnsSetting::updateOrCreate(
            ['monitoring_project_id' => $request->input('monitoring_project_id'), 'name' => $request->input('name')],
            ['state' => $request->input('state')]
        );
    }

    public function getColumnSettingsForProject(Request $request)
    {
        return MonitoringProjectColumnsSetting::where(['monitoring_project_id' => $request->input('monitoring_project_id')])->get();
    }

    public function getSetting(int $idProject, string $name)
    {
        return MonitoringProjectSettings::where(['monitoring_project_id' => $idProject, 'name' => $name])->first();
    }

    public function setSetting(int $idProject, string $name, string $value)
    {
        MonitoringProjectSettings::updateOrCreate(
            ['monitoring_project_id' => $idProject, 'name' => $name],
            ['value' => $value]
        );
    }

    public function getPositionsForCalendars(Request $request)
    {
        /** @var User $user */
        $user = $this->user;
        $project = $user->monitoringProjects()->find($request->input('projectId'));
        $region = $project->searchengines();

        if ($request->input('regionId'))
            $region->where('id', $request->input('regionId'));

        $region = $region->orderBy('id', 'asc')->first();
        $keywordsId = $project->keywords->pluck('id');

        $dates = collect($request->input('dates'))->pluck('date');

        $positions = MonitoringPosition::select(DB::raw('*, DATE(created_at) as dateOnly'))
            ->where('monitoring_searchengine_id', $region->id)
            ->whereIn('monitoring_keyword_id', $keywordsId)
            ->whereIn(DB::raw('DATE(created_at)'), $dates)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->get();

        return $positions;
    }

    private function navigations(MonitoringProject $project): array
    {
        /** @var User $user */
        $user = $this->user;
        $buttons = new SimpleButtonsFactory();

        return $buttons->createButtons($user, $project);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return void
     * @throws \Exception
     */
    public function destroy($id)
    {
        /** @var User $user */
        $user = $this->user;

        $project = $user->monitoringProjects()->find($id);

        apply_team_permissions($project->id);

        if ($user->hasRole('admin_monitoring')) {

            event(new MonitoringProjectBeforeDelete($user, $project));

            $project->delete();
        }
    }

    public function monitoringCompetitors(MonitoringProject $project)
    {
        apply_team_permissions($project->id);

        $project->loadCount('competitors');
        $countQuery = $project->keywords()->count();
        $navigations = $this->navigations($project);
        $ignoredDomains = MonitoringSettings::where('name', 'ignored_domains')->value('value');
        $competitors = $project->competitors()->get()->toArray();

        return view('monitoring.competitors.index', compact(
            'navigations',
            'countQuery',
            'ignoredDomains',
            'project',
            'competitors'
        ));
    }

    public function getProjectCompetitorsInfo(MonitoringProject $project)
    {
        return $project->competitors->toArray();
    }

    public function getCompetitorsInfo(Request $request): JsonResponse
    {
        if ($request->region == '') {
            $lastDays = json_encode(array_column(MonitoringProject::getLastDates(MonitoringProject::find($request->projectId)), 'dateOnly'));
            $region = 'all';
        } else {
            $lastDays = MonitoringProject::getLastDate(MonitoringProject::find($request->projectId), $request->region, true);
            $region = $request->region;
        }

        $record = MonitoringCompetitorsResult::where('project_id', $request->projectId)
            ->where('region', $region)
            ->where('user_id', Auth::id())
            ->latest()
            ->first();

        if (isset($record)) {
            if ($record->date !== $lastDays) {
                $record->delete();
                $id = MonitoringController::startNewCompetitorsAnalyse($lastDays, $region, $request->all());

                return response()->json([
                    'state' => 'in queue',
                    'id' => $id,
                    'newScan' => true
                ]);
            }

            $response = [
                'state' => $record->state,
                'date' => $record->date,
                'id' => $record->id,
            ];

            if ($record->state === 'ready') {
                $response['result'] = Common::uncompressArray($record->result, false);
                $response['newScan'] = false;
            }

            return response()->json($response);
        }

        $id = MonitoringController::startNewCompetitorsAnalyse($lastDays, $region, $request->all());

        return response()->json([
            'state' => 'in queue',
            'id' => $id,
            'newScan' => true
        ]);
    }

    private static function startNewCompetitorsAnalyse(string $lastDays, string $region, array $request): int
    {
        $newRecord = new MonitoringCompetitorsResult();
        $newRecord->date = $lastDays;
        $newRecord->region = $region;
        $newRecord->project_id = $request['projectId'];
        $newRecord->user_id = Auth::id();
        $newRecord->save();

        MonitoringCompetitorsQueue::dispatch(
            $request,
            $newRecord->id
        )->onQueue('monitoring_competitors_stat');

        return $newRecord->id;
    }

    public function getMonitoringCompetitorsResult(Request $request): JsonResponse
    {
        $record = MonitoringCompetitorsResult::find($request->id);

        if ($record->state === 'ready') {
            return response()->json([
                'state' => $record->state,
                'date' => $record->date,
                'result' => Common::uncompressArray($record->result, false)
            ]);
        }

        return response()->json([
            'state' => $record->state,
        ]);
    }

    public function addCompetitor(Request $request): ?JsonResponse
    {
        $project = MonitoringProject::findOrFail($request->projectId);
        $parse = parse_url($request->url);
        $domain = $parse['host'] ?? $parse['path'];
        $url = Common::domainFilter($domain);

        $record = MonitoringCompetitor::where('monitoring_project_id', $project->id)
            ->where('url', $url)
            ->first();

        if (empty($record)) {
            MonitoringCompetitor::insert([
                'monitoring_project_id' => $project->id,
                'url' => $url,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        return response()->json([], 201);
    }

    public function addCompetitors(Request $request): ?JsonResponse
    {
        $project = MonitoringProject::findOrFail($request->projectId);
        $urls = [];

        foreach ($request->domains as $domain) {
            if ($domain === null) {
                continue;
            }
            $parse = parse_url($domain);
            $domain = $parse['host'] ?? $parse['path'];
            $url = Common::domainFilter($domain);
            $record = MonitoringCompetitor::where('monitoring_project_id', $project->id)
                ->where('url', $url)
                ->first();

            if (empty($record)) {
                $urls[] = $url;
                MonitoringCompetitor::insert([
                    'monitoring_project_id' => $project->id,
                    'url' => $url,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }
        }

        return response()->json([
            'urls' => $urls
        ], 201);
    }

    public function removeCompetitor(Request $request): ?JsonResponse
    {
        $project = MonitoringProject::findOrFail($request->projectId);

        MonitoringCompetitor::where('monitoring_project_id', $request->projectId)
            ->where('url', $request->url)
            ->delete();

        return response()->json([], 200);
    }

    public function competitorsPositions(MonitoringProject $project)
    {
        $competitors = MonitoringCompetitor::where('monitoring_project_id', $project->id)->pluck('url')->toArray();
        array_unshift($competitors, $project->url);
        $navigations = $this->navigations($project);

        $allWords = MonitoringKeyword::where('monitoring_project_id', $project->id)->get(['id', 'query'])->toArray();
        $totalWords = count($allWords);
        $keywords = array_chunk($allWords, 100);

        return view('monitoring.competitors.statistics', [
            'project' => $project,
            'searchEngines' => $project->searchengines,
            'changesDates' => $project->dates,
            'competitors' => $competitors,
            'navigations' => $navigations,
            'keywords' => json_encode($keywords),
            'totalWords' => $totalWords,
        ]);
    }

    public function getStatistics(Request $request): JsonResponse
    {
        $statistics = MonitoringCompetitor::calculateStatistics($request->all());

        return response()->json([
            'visibility' => $statistics['visibility'],
            'statistics' => $statistics['statistics'],
        ]);
    }

    public function competitorsHistoryPositions(Request $request): JsonResponse
    {
        $newRecord = new MonitoringChangesDate([
            'monitoring_project_id' => $request->projectId,
            'range' => $request->dateRange,
            'request' => json_encode($request->all(), true)
        ]);
        $newRecord->save();

        MonitoringChangesDateQueue::dispatch(
            $newRecord,
            $request->all()
        )->onQueue('monitoring_change_dates');

        return response()->json([
            'analyseId' => $newRecord->id,
            'redirect' => false,
        ]);
    }

    public function checkChangesDatesState(Request $request): JsonResponse
    {
        $record = MonitoringChangesDate::where('id', $request['id'])->first();

        if (isset($record) && $record->state === 'ready' || $record->state === 'in process') {
            return response()->json([
                'state' => $record->state,
                'range' => $record->range,
                'result' => json_decode($record->result, true),
                'id' => $record->id
            ]);
        }

        return response()->json([
            'state' => 'in queue',
        ]);
    }

    public function removeChangesDatesState(Request $request): JsonResponse
    {
        $count = MonitoringChangesDate::where('id', $request['id'])->delete();

        if ($count === 1) {
            return response()->json([], 200);
        }

        return response()->json([], 415);
    }

    public function resultChangesDatesState(MonitoringChangesDate $project)
    {
        $request = json_decode($project->request, true);
        $engine = MonitoringSearchengine::with('location:id,lr,name')
            ->where('id', $request['region'])
            ->first();
        $request['region'] = $engine && $engine->location ? $engine->location->name : '';

        return view('monitoring.competitors.dates-results', compact('project', 'request'));
    }

    public function getCompetitorsDomain(Request $request): array
    {
        if ($request->region == '') {
            $region = 'all';
        } else {
            $region = $request->region;
        }

        $record = MonitoringCompetitorsResult::where('project_id', $request->projectId)
            ->where('region', $region)
            ->where('user_id', Auth::id())
            ->latest()
            ->first();

        $results = Common::uncompressArray($record->result)[$request->targetDomain]['urls'];

        $response = [];
        foreach ($results as $engine => $phrases) {
            foreach ($phrases as $key => $info) {
                $response[$key][$engine] = $info;
            }
        }

        return $response;
    }

    public function getProjectCompetitors(MonitoringProject $project): array
    {
        return array_column($project->competitors->toArray(), 'url');
    }

}
