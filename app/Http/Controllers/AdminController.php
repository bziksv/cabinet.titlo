<?php

namespace App\Http\Controllers;

use App\Classes\AnalyzeRelevance\RelevanceQueues;
use App\PolicyTermsDocs;
use App\ProjectRelevanceHistory;
use App\RelevanceAllUniqueDomains;
use App\RelevanceAllUniquePages;
use App\RelevanceAnalysisConfig;
use App\RelevanceHistory;
use App\RelevanceStatistics;
use App\RelevanceUniqueDomains;
use App\RelevanceUniquePages;
use App\User;
use App\UsersJobs;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware(['role:Super Admin|admin']);
    }

    /**
     * @return View|void
     */
    public function relevanceHistoryProjects(): View
    {
        if (!User::isUserAdmin()) {
            return abort(403);
        }

        $firstDay = new Carbon('first day of this month');

        return view('relevance-analysis.all', [
            'config' => RelevanceAnalysisConfig::first(),
            'usersJobs' => $this->userJobs(),
            'statistics' => [
                'toDay' => RelevanceStatistics::where('date', '=', Carbon::now()->toDateString())->first(),
                'month' => RelevanceStatistics::where('created_at', '>=', $firstDay->toDateString())->sum('count_checks'),
                'countProjects' => ProjectRelevanceHistory::count(),
                'countSavedResults' => RelevanceHistory::count(),
                'pages' => RelevanceUniquePages::count(),
                'domains' => RelevanceUniqueDomains::count(),
                'allDomains' => RelevanceAllUniqueDomains::count(),
                'allPages' => RelevanceAllUniquePages::count(),
                'countJobs' => UsersJobs::where('count_jobs', '>', 0)->sum('count_jobs'),
            ]
        ]);
    }

    protected function userJobs()
    {
        $rows = collect([]);
        $queues = new RelevanceQueues();
        $jobs = $queues->all();

        $userIds = [];
        $historyIds = [];
        $projectIds = [];

        foreach ($jobs as $job) {
            if (property_exists($job, 'userId') && $job->userId) {
                $userIds[] = (int) $job->userId;
            }
            if (property_exists($job, 'historyId') && $job->historyId) {
                $historyIds[] = (int) $job->historyId;
            }
            if (property_exists($job, 'mainId') && $job->mainId) {
                $projectIds[] = (int) $job->mainId;
            }
        }

        $users = User::query()
            ->whereIn('id', array_values(array_unique($userIds)))
            ->get()
            ->keyBy('id');

        $histories = RelevanceHistory::query()
            ->with('mainHistory:id,name')
            ->whereIn('id', array_values(array_unique($historyIds)))
            ->get(['id', 'phrase', 'main_link', 'project_relevance_history_id'])
            ->keyBy('id');

        $projects = ProjectRelevanceHistory::query()
            ->whereIn('id', array_values(array_unique($projectIds)))
            ->get(['id', 'name'])
            ->keyBy('id');

        foreach ($jobs as $job) {
            $request = (property_exists($job, 'request') && is_array($job->request))
                ? $job->request
                : [];

            $phrase = (string) ($request['phrase'] ?? '');
            $link = (string) ($request['link'] ?? $request['main_link'] ?? '');
            $project = '';
            $historyId = property_exists($job, 'historyId') ? (int) $job->historyId : 0;

            if ($historyId > 0 && isset($histories[$historyId])) {
                $history = $histories[$historyId];
                if ($phrase === '') {
                    $phrase = (string) $history->phrase;
                }
                if ($link === '') {
                    $link = (string) $history->main_link;
                }
                $project = (string) optional($history->mainHistory)->name;
            }

            if ($project === '' && property_exists($job, 'mainId') && isset($projects[(int) $job->mainId])) {
                $project = (string) $projects[(int) $job->mainId]->name;
            }

            if ($project === '' && $link !== '') {
                $host = parse_url($link, PHP_URL_HOST);
                $project = is_string($host) ? $host : '';
            }

            $jobClass = class_basename($job);
            $jobType = property_exists($job, 'type') ? (string) $job->type : '';
            $jobLabel = $this->relevanceJobLabel($jobClass, $jobType);
            $queue = (string) ($job->queue ?? '');

            $row = [
                'user' => 'System',
                'email' => '',
                'project' => $project,
                'phrase' => $phrase,
                'link' => $link,
                'queue' => $queue,
                'queue_label' => $this->relevanceQueueLabel($queue),
                'job' => $jobClass,
                'job_label' => $jobLabel,
            ];

            if (property_exists($job, 'userId') && isset($users[(int) $job->userId])) {
                $user = $users[(int) $job->userId];
                $row['user'] = $user->fullName;
                $row['email'] = (string) $user->email;
            }

            $rows->push($row);
        }

        return $rows;
    }

    protected function relevanceQueueLabel(string $queue): string
    {
        $map = [
            'relevance_high_priority' => __('Relevance queue high'),
            'relevance_medium_priority' => __('Relevance queue medium'),
            'relevance_normal_priority' => __('Relevance queue normal'),
        ];

        return $map[$queue] ?? $queue;
    }

    protected function relevanceJobLabel(string $jobClass, string $type = ''): string
    {
        if ($jobClass === 'RelevanceThoughAnalysisQueue') {
            return __('Relevance job through analysis');
        }

        if ($jobClass === 'RelevanceAnalyseQueue') {
            return __('Relevance job new analysis');
        }

        if ($jobClass === 'RelevanceHistoryQueue') {
            if ($type === 'mainPage') {
                return __('Relevance job refresh landing');
            }
            if ($type === 'competitors') {
                return __('Relevance job refresh competitors');
            }

            return __('Relevance job history check');
        }

        if ($jobClass === 'RunRelevanceAnalyseQueue') {
            return __('Relevance job finish analysis');
        }

        if ($jobClass === 'RemoveRelevanceProgress') {
            return __('Relevance job cleanup');
        }

        return $jobClass;
    }

    /**
     * @return View
     */
    public function showConfig(): View
    {
        $config = RelevanceAnalysisConfig::first();
        $host = config('database.host');
        $db_name = config('database.database');
        $user = config('database.username');
        $password = config('database.password');
        $connection = mysqli_connect($host, $user, $password, $db_name);

        $query = 'SELECT table_name AS `Table`,
                        round(((data_length + index_length) / 1024 / 1024), 2)
                    FROM information_schema.TABLES
                    WHERE table_name = "relevance_history_result";';
        $result = mysqli_query($connection, $query);
        $result = $result->fetch_assoc();

        return view('relevance-analysis.relevance-config', [
            'admin' => true,
            'config' => $config,
            'size' => $result['round(((data_length + index_length) / 1024 / 1024), 2)']
        ]);
    }


    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function changeConfig(Request $request): RedirectResponse
    {
        $config = RelevanceAnalysisConfig::first();
        if (!$config) {
            $config = new RelevanceAnalysisConfig();
        }

        $config->count_sites = $request->count;
        $config->region = $request->region;
        $config->ignored_domains = $request->ignored_domains;
        $config->separator = $request->separator;

        $config->noindex = $request->noindex;
        $config->meta_tags = $request->meta_tags;
        $config->parts_of_speech = $request->parts_of_speech;
        $config->remove_my_list_words = $request->remove_my_list_words;
        $config->my_list_words = $request->my_list_words;
        $config->hide_ignored_domains = $request->hide_ignored_domains;

        $config->ltp_count = $request->ltp_count;
        $config->ltps_count = $request->ltps_count;
        $config->scanned_sites_count = $request->scanned_sites_count;
        $config->recommendations_count = $request->recommendations_count;

        $config->boostPercent = $request->boostPercent;
        $config->word_worms = $request->word_worms;

        $config->save();

        return Redirect::back();
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function changeCleaningInterval(Request $request): JsonResponse
    {
        $config = RelevanceAnalysisConfig::first();
        if (!$config) {
            $config = new RelevanceAnalysisConfig();
        }

        $config->cleaning_interval = $request->newInterval;
        $config->save();

        return response()->json([
            'success' => true,
            'message' => __('Cleaning parameters have been successfully changed'),
            'code' => 200
        ]);
    }

    /**
     * @return JsonResponse
     */
    public function getCountQueue(): JsonResponse
    {
        return response()->json([
            'count' => UsersJobs::where('count_jobs', '>', 0)->sum('count_jobs')
        ]);
    }

    /**
     * @return JsonResponse
     */
    public function getUserJobs(): JsonResponse
    {
        return response()->json([
            'jobs' => UsersJobs::query()
                ->where('count_jobs', '>', 0)
                ->with(['user' => static function ($query) {
                    $query->select(['id', 'name', 'last_name', 'email']);
                }])
                ->get(['id', 'user_id', 'count_jobs']),
        ]);
    }

    public function editPolicyFilesView()
    {
        return view('policy.index');
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function editPolicyFiles(Request $request): RedirectResponse
    {
        PolicyTermsDocs::editDocument($request->input('type'), $request->input('description'));

        flash()->overlay('Документ успешно отредактирован', ' ')->success();

        return Redirect::back();
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getPolicyDocument(Request $request): JsonResponse
    {
        $docs = PolicyTermsDocs::first($request->input('type'))->toArray();

        return response()->json([
            'code' => 200,
            'document' => $docs[$request->input('type')]
        ]);
    }

    public function removeUserJobs(): JsonResponse
    {
        UsersJobs::truncate();

        return response()->json([]);
    }
}
