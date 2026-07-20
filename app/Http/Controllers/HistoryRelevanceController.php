<?php

namespace App\Http\Controllers;

use App\Common;
use App\Exports\RelevanceStatisticsExport;
use App\Jobs\Relevance\RelevanceHistoryQueue;
use App\ProjectRelevanceHistory;
use App\ProjectRelevanceThough;
use App\Relevance;
use App\RelevanceAnalysisConfig;
use App\RelevanceHistory;
use App\RelevanceHistoryResult;
use App\RelevanceSharing;
use App\RelevanceTags;
use App\User;
use App\UsersJobs;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class HistoryRelevanceController extends Controller
{
    public function index(): View
    {
        $config = RelevanceAnalysisConfig::first();
        $tags = RelevanceTags::where('user_id', '=', Auth::id())->get();
        $projects = ProjectRelevanceHistory::where('user_id', '=', Auth::id())->with('relevanceTags')->get();
        $admin = User::isUserAdmin();

        return view('relevance-analysis.history', [
            'main' => $projects,
            'admin' => $admin,
            'config' => $config,
            'tags' => $tags,
        ]);
    }

    /**
     * Eager load для списка проектов (без longText result — иначе OOM на DataTables).
     *
     * @return array
     */
    private function historyListRelations(): array
    {
        return [
            'relevanceTags',
            'story' => function ($query) {
                $query->select('id', 'project_relevance_history_id', 'last_check');
            },
            'though' => function ($query) {
                $query->select([
                    'id',
                    'project_relevance_history_id',
                    'state',
                    'stage',
                    'though_words',
                    'word_worms',
                    'created_at',
                    'updated_at',
                ]);
            },
        ];
    }

    private function prepareData($records, $totalRecords, $request, $owner = false)
    {
        $aaData = [];
        foreach ($records as $record) {
            $though = [];
            if (isset($record['though'])) {
                $though = $record['though'];
                unset($though['result']);
            }

            $data = [
                'id' => $record['id'],
                'name' => $record['name'],
                'relevanceTags' => $record['relevance_tags'] ?? [],
                'count_sites' => $record['count_sites'],
                'count_checks' => $record['count_checks'],
                'total_points' => $record['total_points'],
                'avg_position' => $record['avg_position'],
                'though' => $though,
                'last_check' => ($record->story) ? $record->story->last_check : '',
            ];

            if ($owner) {
                $data['owner'] = $record['user'];
            }

            $aaData[] = $data;
        }

        return json_encode([
            'draw' => intval($request['draw']),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalRecords,
            'aaData' => $aaData
        ]);
    }

    public function getAllProjects(Request $request)
    {
        $start = $request->input('start');
        $pageNumber = floor($start / $request->input('length')) + 1;

        $columnIndex = $request->input('order.0.column');
        $columnSortOrder = $request->input('order.0.dir');
        $columnName = $request->input('columns.' . $columnIndex . '.name');
        $search = $request->input('search.value');

        $query = ProjectRelevanceHistory::query();

        if ($search !== null && $search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', '%' . $search . '%')
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('email', 'like', '%' . $search . '%');
                    });
            });
        }

        $totalRecords = (clone $query)->count();

        $records = $query->orderBy($columnName, $columnSortOrder)
            ->with(array_merge(['user:id,email,name,last_name'], $this->historyListRelations()))
            ->paginate($request->input('length'), ['*'], 'page', $pageNumber);

        return $this->prepareData($records, $totalRecords, $request, true);
    }

    public function getProjects(Request $request)
    {
        $start = $request->input('start');
        $pageNumber = floor($start / $request->input('length')) + 1;

        $columnIndex = $request->input('order.0.column');
        $columnSortOrder = $request->input('order.0.dir');
        $columnName = $request->input('columns.' . $columnIndex . '.name');
        $search = $request->input('search.value');

        $totalRecords = ProjectRelevanceHistory::where('user_id', '=', Auth::id())
            ->where('name', 'like', "%$search%")
            ->count();

        $records = ProjectRelevanceHistory::orderBy($columnName, $columnSortOrder)
            ->where('user_id', '=', Auth::id())
            ->where('name', 'like', "%$search%")
            ->with($this->historyListRelations())
            ->paginate($request->input('length'), ['*'], 'page', $pageNumber);

        return $this->prepareData($records, $totalRecords, $request);
    }

    public function getStories(Request $request): JsonResponse
    {
        $history = ProjectRelevanceHistory::where('id', '=', $request->history_id)->first();
        $admin = User::isUserAdmin();
        $userId = Auth::id();

        $share = RelevanceSharing::where('user_id', '=', $userId)
            ->where('owner_id', '=', $history->user_id)
            ->where('access', '=', 2)
            ->first();

        if ($history->user_id != $userId && !isset($share) && !$admin) {
            return response()->json([
                'success' => false,
                'message' => __("You don't have access to this object"),
                'code' => 415
            ]);
        }

        return response()->json([
            'stories' => $this->loadProjectStories($history),
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, RelevanceHistory>
     */
    private function loadProjectStories(ProjectRelevanceHistory $history)
    {
        $results = $history->stories()->with([
            'results:id,project_id,average_values',
        ])->get([
            'phrase', 'main_link', 'region',
            'last_check', 'points', 'position',
            'coverage', 'coverage_tf', 'density',
            'width', 'density', 'calculate',
            'id', 'project_relevance_history_id',
            'comment', 'user_id', 'state',
        ]);

        foreach ($results as $result) {
            $averageValues = $result->results->average_values ?? null;
            if ($averageValues !== null && $averageValues !== '') {
                $result['average_values'] = json_decode($averageValues, true);
            }
            unset($result->results);
        }

        return $results;
    }

    public function editGroupName(Request $request): JsonResponse
    {
        ProjectRelevanceHistory::where('id', '=', $request->id)->update([
            'group_name' => $request->name
        ]);

        return response()->json([]);
    }

    public function changeCalculateState(Request $request): JsonResponse
    {
        $project = RelevanceHistory::where('id', '=', $request->id)->first();

        $project->calculate = filter_var($request->calculate, FILTER_VALIDATE_BOOLEAN);

        $project->save();

        ProjectRelevanceHistory::calculateInfo($project->projectRelevanceHistory);

        return response()->json([]);
    }

    public function show(int $id)
    {
        $admin = User::isUserAdmin();
        $object = RelevanceHistory::with('projectRelevanceHistory:id,user_id,name')
            ->where('id', $id)
            ->first();

        if ($object === null) {
            abort(404);
        }

        $access = RelevanceSharing::where('user_id', Auth::id())
            ->where('project_id', $object->project_relevance_history_id)
            ->first();

        if (!isset($access) && $object->projectRelevanceHistory->user_id != Auth::id() && !$admin) {
            return abort(403, __("You don't have access to this object"));
        }

        $this->reconcileQueueState($object);

        $object->request = json_decode($object->request, true);
        return view('relevance-analysis.show-history', [
            'admin' => $admin,
            'id' => $id,
            'object' => $object,
            'access' => $access ?? null
        ]);
    }

    public function getDetailsInfo(Request $request): JsonResponse
    {
        $part = (string) $request->input('part', 'full');
        $projectId = (int) $request->input('id');
        $started = microtime(true);

        try {
            $history = RelevanceHistoryResult::where('project_id', '=', $request->id)->latest('updated_at')->first();

            $admin = User::isUserAdmin();
            $ownerId = $history->mainHistory->mainHistory->user_id;
            $userId = Auth::id();

            $share = RelevanceSharing::where('user_id', '=', $userId)
                ->where('owner_id', '=', $ownerId)
                ->where('access', '=', 2)
                ->first();

            if ($ownerId != $userId && !isset($share) && !$admin) {
                return response()->json([
                    'success' => false,
                    'message' => __("You don't have access to this object"),
                    'code' => 415
                ]);
            } elseif (!$history->compressed) {
                foreach ($history->getOriginal() as $key => $item) {
                    if ($key != 'id' && $key != 'project_id' && $key != 'created_at' && $key != 'updated_at' && $key != 'compressed' && $key != 'cleaning' && $key != 'hash') {
                        // Уже сжатый блоб (base64 gz) повторно не трогаем.
                        if (is_string($item) && $item !== '' && preg_match('/^[A-Za-z0-9+\/=]{32,}$/', $item) && @gzuncompress(base64_decode($item, true) ?: '') !== false) {
                            continue;
                        }
                        if ($item === null || $item === '') {
                            continue;
                        }
                        $history[$key] = base64_encode(gzcompress($item, 9));
                    }
                }

                $history->compressed = true;
                $history->save();

                $history = RelevanceHistoryResult::where('project_id', '=', $request->id)->latest('updated_at')->first();
            }
            $history = Relevance::uncompress($history);
            $history = Relevance::historyDetailsPart($history, $part);

        } catch (Throwable $exception) {
            Log::warning('relevance.details.failed', [
                'part' => $part,
                'project_id' => $projectId,
                'ms' => (int) round((microtime(true) - $started) * 1000),
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'code' => 415,
                'message' => __('The data was lost')
            ]);
        }

        $response = [
            'code' => 200,
            'history' => $history,
        ];

        if ($part === 'full' || $part === 'meta') {
            $response['config'] = RelevanceAnalysisConfig::first();
        }

        $encoded = json_encode($response);
        Log::info('relevance.details', [
            'part' => $part,
            'project_id' => $projectId,
            'ms' => (int) round((microtime(true) - $started) * 1000),
            'bytes' => is_string($encoded) ? strlen($encoded) : null,
            'history_keys' => isset($history) && is_array($history) ? array_keys($history) : null,
        ]);

        return response()->json($response);
    }

    public function editComment(Request $request): JsonResponse
    {
        $project = RelevanceHistory::where('id', '=', $request->id)->first();

        $project->comment = $request->comment;

        $project->save();

        return response()->json([]);
    }

    public function repeatScan(Request $request): JsonResponse
    {
        if (RelevanceHistory::checkRelevanceAnalysisLimits()) {
            return response()->json([
                'code' => 415,
                'message' => __('Your limits are exhausted this month')
            ]);
        }

        $this->validate($request, [
            'phrase' => 'required',
            'link' => 'required',
        ]);

        $admin = User::isUserAdmin();
        $userId = Auth::id();
        $object = RelevanceHistory::where('id', '=', $request->id)->with('mainHistory')->first();
        $ownerId = $object->mainHistory->user_id;

        $share = RelevanceSharing::where('user_id', '=', $userId)
            ->where('owner_id', '=', $ownerId)
            ->where('access', '=', 2)
            ->first();

        if ($ownerId != $userId && !isset($share) && !$admin) {
            return response()->json([
                'success' => false,
                'message' => __("You don't have access to this object"),
                'code' => 415
            ]);
        } else if ($object->state == 1 || $object->state == -1) {
            $object->state = 0;
            $object->save();

            RelevanceHistoryQueue::dispatch(
                $ownerId,
                $request->all(),
                $request['id']
            )->onQueue(UsersJobs::getPriority($ownerId))->onConnection('database');
        }
        return response()->json([
            'success' => true,
            'code' => 200
        ]);
    }

    public function repeatQueueCompetitorsScan(Request $request): JsonResponse
    {
        if (RelevanceHistory::checkRelevanceAnalysisLimits()) {
            return response()->json([
                'code' => 415,
                'message' => __('Your limits are exhausted this month')
            ]);
        }

        $admin = User::isUserAdmin();
        $userId = Auth::id();
        $object = RelevanceHistory::where('id', '=', $request->id)->first();
        $ownerId = $object->mainHistory->user_id;

        $share = RelevanceSharing::where('user_id', '=', $userId)
            ->where('owner_id', '=', $ownerId)
            ->where('access', '=', 2)
            ->first();

        if ($ownerId != $userId && !isset($share) && !$admin) {
            return response()->json([
                'success' => false,
                'message' => __("You don't have access to this object"),
                'code' => 415
            ]);
        } else if ($object->state == 1 || $object->state == -1) {
            $object->state = 0;
            $object->save();

            RelevanceHistoryQueue::dispatch(
                $ownerId,
                $request->all(),
                $request->id,
                false,
                false,
                'competitors'
            )->onQueue(UsersJobs::getPriority($ownerId))->onConnection('database');
        }
        return response()->json([
            'success' => true,
            'code' => 200
        ]);
    }

    public function repeatQueueMainPageScan(Request $request): JsonResponse
    {
        if (RelevanceHistory::checkRelevanceAnalysisLimits()) {
            return response()->json([
                'code' => 415,
                'message' => __('Your limits are exhausted this month')
            ]);
        }

        $admin = User::isUserAdmin();
        $userId = Auth::id();
        $object = RelevanceHistory::where('id', '=', $request->id)->first();
        $ownerId = $object->mainHistory->user_id;

        $share = RelevanceSharing::where('user_id', '=', $userId)
            ->where('owner_id', '=', $ownerId)
            ->where('access', '=', 2)
            ->first();

        if ($ownerId != $userId && !isset($share) && !$admin) {
            return response()->json([
                'success' => false,
                'message' => __("You don't have access to this object"),
                'code' => 415
            ]);
        } else if ($object->state == 1 || $object->state == -1) {
            $object->state = 0;
            $object->save();

            RelevanceHistoryQueue::dispatch(
                $ownerId,
                $request->all(),
                $request['id'],
                false,
                false,
                'mainPage'
            )->onQueue(UsersJobs::getPriority($ownerId))->onConnection('database');
        }
        return response()->json([
            'success' => true,
            'code' => 200
        ]);
    }

    public function getHistoryInfo(RelevanceHistory $object): JsonResponse
    {
        $userId = Auth::id();
        $ownerId = $object->user_id;
        $admin = User::isUserAdmin();
        $share = RelevanceSharing::where('user_id', '=', $userId)
            ->where('owner_id', '=', $ownerId)
            ->where('access', '=', 2)
            ->first();

        if ($ownerId != $userId && !isset($share) && !$admin) {
            return response()->json([
                'success' => false,
                'message' => __("You don't have access to this object"),
                'code' => 415
            ]);
        }

        return response()->json([
            'history' => json_decode($object->request)
        ]);
    }

    public function getHistoryInfoV2(Request $request): JsonResponse
    {
        $projects = RelevanceHistory::where('project_relevance_history_id', $request->historyId)->latest('id')
            ->get([
                'id',
                'created_at',
                'region',
                'main_link',
                'points',
                'position',
                'user_id',
                'coverage',
                'coverage_tf',
                'width',
                'density',
                'phrase',
                'state',
                'comment'
            ]);

        $ownerId = $projects[0]->user_id;
        $admin = User::isUserAdmin();
        $userId = Auth::id();

        $share = RelevanceSharing::where('user_id', '=', $userId)
            ->where('owner_id', '=', $ownerId)
            ->where('access', '=', 2)
            ->first();

        if ($ownerId != $userId && !isset($share) && !$admin) {
            return response()->json([
                'success' => false,
                'message' => __("You don't have access to this object"),
                'code' => 415
            ]);
        }

        $responseObject = [];
        foreach ($projects as $project) {
            $responseObject[$project->phrase][] = $project->toArray();
        }

        return response()->json([
            'object' => $responseObject
        ]);
    }

    public function removeEmptyResults(Request $request): JsonResponse
    {
        $userId = Auth::id();
        $main = ProjectRelevanceHistory::where('id', '=', $request->id)->first();
        $admin = User::isUserAdmin();
        $share = RelevanceSharing::where('user_id', '=', $userId)
            ->where('owner_id', '=', $main->user_id)
            ->where('access', '=', 2)
            ->first();

        if ($main->user_id != $userId && !isset($share) && !$admin) {
            return response()->json([
                'success' => false,
                'message' => __("You don't have access to this object"),
                'code' => 415
            ]);
        }

        $items = $this->getUniqueScanned($request->id);

        foreach ($items as $link) {
            $records = RelevanceHistory::where('comment', '!=', '')
                ->where('main_link', '=', $link->main_link)
                ->where('phrase', '=', $link->phrase)
                ->where('region', '=', $link->region)
                ->where('project_relevance_history_id', '=', $request->id)
                ->latest('last_check')
                ->get();
            if (count($records) >= 1) {
                RelevanceHistory::where('comment', '=', '')
                    ->where('main_link', '=', $link->main_link)
                    ->where('phrase', '=', $link->phrase)
                    ->where('region', '=', $link->region)
                    ->where('project_relevance_history_id', '=', $request->id)
                    ->delete();
            } else {
                $records = RelevanceHistory::where('comment', '=', '')
                    ->where('main_link', '=', $link->main_link)
                    ->where('phrase', '=', $link->phrase)
                    ->where('region', '=', $link->region)
                    ->where('project_relevance_history_id', '=', $request->id)
                    ->latest('last_check')
                    ->get();
                $iterator = 0;
                foreach ($records as $key => $record) {
                    if ($key != array_key_first($records->toArray())) {
                        $iterator++;
                        $record->delete();
                    }
                }
            }
        }

        $info = ProjectRelevanceHistory::calculateInfo($main);
        $removed = ProjectRelevanceHistory::where('id', '=', $request->id)
            ->where('count_sites', '=', 0)->delete();

        return response()->json([
            'success' => true,
            'message' => __('Success'),
            'points' => $info['points'],
            'countSites' => $info['count'],
            'countChecks' => $info['countChecks'],
            'avgPosition' => $info['avgPosition'],
            'objectId' => $request->id,
            'removed' => $removed,
            'code' => 200
        ]);
    }

    public function removeEmptyResultsFilters(Request $request): JsonResponse
    {
        $userId = Auth::id();
        $main = ProjectRelevanceHistory::where('id', '=', $request->id)->first();
        $admin = User::isUserAdmin();
        $share = RelevanceSharing::where('user_id', '=', $userId)
            ->where('owner_id', '=', $main->user_id)
            ->where('access', '=', 2)
            ->first();

        if ($main->user_id != $userId && !isset($share) && !$admin) {
            return response()->json([
                'success' => false,
                'message' => __("You don't have access to this object"),
                'code' => 415
            ]);
        }

        $query = RelevanceHistory::where('project_relevance_history_id', '=', $request->id);

        if (isset($request->positionAfter)) {
            $query->where('position', '>=', $request->positionAfter);
        }

        if (isset($request->positionBefore)) {
            $query->where('position', '<=', $request->positionBefore);
        }

        if (isset($request->after)) {
            $query->where('last_check', '>=', $request->after);
        }

        if (isset($request->before)) {
            $query->where('last_check', '<=', $request->before);
        }

        if (isset($request->comment)) {
            $query->where('comment', '=', $request->comment);
        }

        if (isset($request->phrase)) {
            $query->where('phrase', '=', $request->phrase);
        }

        if ($request->region === 'all') {
            $query->where('region', '!=', 0);
        } elseif ($request->region !== 'none') {
            $query->where('region', '=', $request->region);
        }

        if (isset($request->link)) {
            $query->where('main_link', '=', $request->link);
        }

        $count = $query->delete();

        $info = ProjectRelevanceHistory::calculateInfo($main);
        $removed = ProjectRelevanceHistory::where('id', '=', $request->id)
            ->where('count_sites', '=', 0)->delete();

        return response()->json([
            'success' => true,
            'message' => __('It was deleted') . ' ' . $count . ' ' . __('projects'),
            'points' => $info['points'],
            'countSites' => $info['count'],
            'countChecks' => $info['countChecks'],
            'avgPosition' => $info['avgPosition'],
            'objectId' => $request->id,
            'removed' => $removed,
            'code' => 200
        ]);
    }

    function checkQueueScanState(Request $request): JsonResponse
    {
        $project = RelevanceHistory::where('id', '=', $request->id)->first();

        if ($project->state == 1) {
            $newProject = RelevanceHistory::where('id', '!=', $request->id)
                ->where('id', '>', $request->id)
                ->where('user_id', '=', $project->user_id)
                ->latest('id')
                ->first();

            return response()->json([
                'message' => 'success',
                'newProject' => $newProject
            ]);
        }

        return response()->json([
            'message' => 'wait',
        ]);
    }

    public function repeatScanUniqueSites(Request $request): JsonResponse
    {
        $ownerId = $this->checkAccess($request);
        $items = $this->getUniqueScanned($request->id);

        $ids = [];
        foreach ($items as $item) {
            $record = RelevanceHistory::where('main_link', '=', $item->main_link)
                ->where('project_relevance_history_id', '=', $request->id)
                ->where('phrase', '=', $item->phrase)
                ->where('region', '=', $item->region)
                ->where('calculate', '=', 1)
                ->latest('last_check')
                ->first();

            RelevanceHistoryQueue::dispatch(
                $ownerId,
                json_decode($record->request, true),
                $record->id
            )->onQueue($request->input('queue', RelevanceController::MEDIUM_QUEUE))
                ->onConnection('database');

            $record->state = 0;
            $record->save();

            $ids[] = $record->id;
        }

        return response()->json([
            'success' => false,
            'code' => 200,
            'message' => __('Your tasks have been successfully added to the queue'),
            'object' => $ids,
        ]);
    }

    public static function checkAccess($request)
    {
        $userId = Auth::id();
        $project = ProjectRelevanceHistory::where('id', '=', $request->id)->first();
        $admin = User::isUserAdmin();
        $share = RelevanceSharing::where('user_id', '=', $userId)
            ->where('owner_id', '=', $project->user_id)
            ->where('access', '=', 2)
            ->first();

        if ($project->user_id != $userId && !isset($share) && !$admin) {
            return response()->json([
                'success' => false,
                'code' => 415,
                'message' => __("You don't have access to this object")
            ]);
        }

        return $project->user_id;
    }

    public static function getUniqueScanned($id): Collection
    {
        return RelevanceHistory::where('project_relevance_history_id', '=', $id)
            ->distinct(['main_link', 'phrase', 'region'])
            ->get(['main_link', 'phrase', 'region']);
    }

    public function rescanProjects(Request $request): JsonResponse
    {
        $admin = User::isUserAdmin();
        $userId = Auth::id();

        foreach (json_decode($request->ids, true) as $id) {
            $object = RelevanceHistory::where('id', '=', $id)->with('mainHistory')->first();
            $ownerId = $object->mainHistory->user_id;

            $share = RelevanceSharing::where('user_id', '=', $userId)
                ->where('owner_id', '=', $ownerId)
                ->where('access', '=', 2)
                ->first();

            if ($ownerId != $userId && !isset($share) && !$admin) {
                return response()->json([
                    'success' => false,
                    'message' => __("You don't have access to this object"),
                    'code' => 415
                ]);
            } else if ($object->state == 1 || $object->state == -1) {
                $object->state = 0;
                $object->save();

                RelevanceHistoryQueue::dispatch(
                    $ownerId,
                    json_decode($object->request, true),
                    $id
                )->onQueue(UsersJobs::getPriority($ownerId))->onConnection('database');
            }

            ProjectRelevanceThough::where('id', '=', $request->thoughId)->update(['cleaning_state' => 1]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Проекты успешно добавлены в очередь на повторный анализ',
            'code' => 200
        ]);
    }

    function checkAnalyseProgress(Request $request): JsonResponse
    {
        $object = RelevanceHistory::where('id', '=', $request->id)->first();

        if ($object === null) {
            return response()->json([
                'message' => 'error',
                'code' => 500,
            ]);
        }

        $this->reconcileQueueState($object);
        $object->refresh();

        if ($object->state == 0) {
            return response()->json([
                'message' => 'wait',
                'code' => 200
            ]);
        } else if ($object->state == -1) {
            return response()->json([
                'message' => 'error',
                'code' => 200
            ]);
        }

        $latest = RelevanceHistory::where('project_relevance_history_id', '=', $object->project_relevance_history_id)
            ->orderByDesc('id')
            ->first();

        $sinceId = (int) $request->input('since_id', 0);
        if ($sinceId > 0 && ($latest === null || (int) $latest->id <= $sinceId)) {
            return response()->json([
                'message' => 'wait',
                'code' => 200,
            ]);
        }

        try {
            return response()->json([
                'message' => 'success',
                'code' => 200,
                'completedHistoryId' => $latest ? (int) $latest->id : (int) $object->id,
                'newObject' => $latest ? [
                    'id' => (int) $latest->id,
                    'last_check' => $latest->last_check,
                ] : null,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'error',
                'code' => 500,
            ]);
        }

    }

    private function reconcileQueueState(RelevanceHistory $object): void
    {
        if ((int) $object->state !== 0) {
            return;
        }

        $newerCompleted = RelevanceHistory::where('project_relevance_history_id', '=', $object->project_relevance_history_id)
            ->where('id', '>', $object->id)
            ->where('state', '=', 1)
            ->orderByDesc('id')
            ->first();

        if ($newerCompleted === null) {
            return;
        }

        $newerCompletedAt = $newerCompleted->last_check ?? $newerCompleted->updated_at;
        if ($newerCompletedAt < $object->updated_at) {
            return;
        }

        $object->state = 1;
        $object->save();
    }

    public function showMissingWords(RelevanceHistoryResult $result)
    {
        $admin = User::isUserAdmin();
        $wordForms = json_decode(gzuncompress(base64_decode($result->unigram_table)), true);

        $result = [];
        foreach ($wordForms as $wordForm) {
            $key = array_key_first($wordForm);
            $elem = $wordForm[$key];

            if ($elem['repeatInLinkMainPage'] == 0 && $elem['repeatInTextMainPage'] == 0) {
                $result[$key] = $elem;
            }
        }

        return view('relevance-analysis.scan-result.missing-words', [
            'result' => $result,
            'admin' => $admin
        ]);
    }

    public function showChildrenRows(RelevanceHistoryResult $result): View
    {
        $admin = User::isUserAdmin();
        $wordForms = json_decode(gzuncompress(base64_decode($result->unigram_table)), true);

        $result = [];
        foreach ($wordForms as $wordForm) {
            foreach ($wordForm as $keyword => $word) {
                if ($keyword != 'total') {
                    $result[$keyword] = $word;
                }
            }
        }

        return view('relevance-analysis.scan-result.child-words', [
            'result' => $result,
            'admin' => $admin
        ]);
    }

    public function getFile(int $id, string $type)
    {
        $history = ProjectRelevanceHistory::where('id', '=', $id)->first();
        $admin = User::isUserAdmin();
        $userId = Auth::id();

        $share = RelevanceSharing::where('user_id', '=', $userId)
            ->where('owner_id', '=', $history->user_id)
            ->where('access', '=', 2)
            ->first();

        if ($history->user_id != $userId && !isset($share) && !$admin) {
            abort(403);
        }

        $file = Excel::download(new RelevanceStatisticsExport($id), 'relevance_statistics.' . $type);
        Common::fileExport($file, $type, 'relevance_statistics');
    }

    public function showDetail($url, $id, $search)
    {
        $history = RelevanceHistoryResult::where($search, $id)->latest('updated_at')->first();
        $url = str_replace('splittedSlashe', '/', $url);
        $sites = Common::uncompressArray($history->sites);

        return gzuncompress(base64_decode($sites[$url]['defaultHtml']));
    }
}
