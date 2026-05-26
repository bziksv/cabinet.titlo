<?php

namespace App\Http\Controllers;

use App\Cluster;
use App\ClusterConfiguration;
use App\ClusterConfigurationClassic;
use App\ClusterLimit;
use App\ClusterQueue;
use App\ClusterResults;
use App\Common;
use App\Exports\Cluster\ClusterGroupExport;
use App\Exports\Cluster\ClusterResultExport;
use App\Jobs\Cluster\StartClusterAnalyseQueue;
use App\Support\ClusterAnalysisDebugLog;
use App\Support\ClusterProgress;
use App\Support\ClusterQueues;
use App\Support\YandexLrRegions;
use App\User;
use Carbon\Carbon;
use Doctrine\DBAL\Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class ClusterController extends Controller
{
    public function index($result = null): View
    {
        $admin = User::isUserAdmin();
        $config = ClusterConfiguration::first();
        $configClassic = ClusterConfigurationClassic::first();

        return view('cluster-v2.index', [
            'admin' => $admin,
            'config' => $config,
            'config_classic' => $configClassic,
            'clusterV2DefaultsClassic' => $this->clusterV2DefaultsFromConfig($configClassic),
            'clusterV2DefaultsPro' => $this->clusterV2DefaultsFromConfig($config),
            'clusterV2DefaultRegion' => $this->clusterV2ResolvedRegion($configClassic),
            'telegramConnected' => Auth::user()->isTelegramConnected(),
            'clusterV2PresetKawe' => $admin ? $this->clusterV2PresetKawe() : null,
        ]);
    }

    private function clusterV2PresetKawe(): ?array
    {
        $preset = config('cabinet-cluster.presets.kawe');
        $file = is_array($preset) ? ($preset['phrases_file'] ?? null) : null;

        if (!$file || !is_readable($file)) {
            return null;
        }

        $phrases = trim((string) file_get_contents($file));
        if ($phrases === '') {
            return null;
        }

        return [
            'phrases' => $phrases,
            'domain' => (string) ($preset['domain'] ?? ''),
            'searchBase' => (bool) ($preset['search_base'] ?? false),
            'searchPhrases' => (bool) ($preset['search_phrases'] ?? false),
            'searchTarget' => (bool) ($preset['search_target'] ?? false),
            'searchRelevance' => (bool) ($preset['search_relevance'] ?? false),
            'save' => (string) ($preset['save'] ?? '0'),
            'sendMessage' => (string) (($preset['send_message'] ?? false) ? '1' : '0'),
        ];
    }

    public function searchRegions(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $limit = min(50, max(5, (int) $request->query('limit', 25)));

        return response()->json([
            'results' => YandexLrRegions::search($q, $limit),
        ]);
    }

    private function clusterV2ResolvedRegion($config): ?array
    {
        $id = $config !== null ? (string) $config->region : '213';
        if ($id === '') {
            $id = '213';
        }

        return YandexLrRegions::find($id) ?? YandexLrRegions::find('213');
    }

    private function clusterV2DefaultsFromConfig($config): array
    {
        if ($config === null) {
            return [];
        }

        $regionItem = YandexLrRegions::find((string) $config->region);

        return [
            'region' => $config->region,
            'region_text' => $regionItem['name'] ?? ($regionItem['text'] ?? (string) $config->region),
            'count' => $config->count ?? 30,
            'clustering_level' => $config->clustering_level,
            'save_results' => (bool) $config->save_results,
            'search_base' => (bool) $config->search_base,
            'search_phrased' => (bool) $config->search_phrased,
            'search_target' => (bool) $config->search_target,
            'search_relevance' => (bool) $config->search_relevance,
            'search_engine' => $config->search_engine ?? 'yandex',
            'send_message' => (bool) $config->send_message,
            'brut_force' => (bool) $config->brut_force,
            'gain_factor' => $config->gain_factor,
            'brut_force_count' => $config->brut_force_count,
            'reduction_ratio' => $config->reduction_ratio,
            'ignored_domains' => $config->ignored_domains,
            'ignored_words' => $config->ignored_words,
        ];
    }

    public function analyseCluster(Request $request): JsonResponse
    {
        if ($request->has('domain')) {
            $request->merge(['domain' => $this->normalizeClusterDomains((string) $request->input('domain'))]);
        }

        $progressId = (string) $request->input('progressId', '');
        if ($progressId !== '') {
            ClusterAnalysisDebugLog::info($progressId, 'http.analyseCluster.accepted', [
                'mode' => $request->input('mode'),
                'region' => $request->input('region'),
                'phrases' => count(array_filter(explode("\n", str_replace("\r", '', (string) $request->input('phrases', ''))))),
                'search_base' => filter_var($request->input('searchBase'), FILTER_VALIDATE_BOOLEAN),
                'search_phrases' => filter_var($request->input('searchPhrases'), FILTER_VALIDATE_BOOLEAN),
                'search_target' => filter_var($request->input('searchTarget'), FILTER_VALIDATE_BOOLEAN),
                'search_relevance' => filter_var($request->input('searchRelevance'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        $this->validate($request, [
            'domain' => 'sometimes|required_if:searchRelevance,==,true',
        ], [
            'domain.required_if' => __('the domain is required if the relevant page selection mode is selected')
        ]);

        $countRequests = ClusterLimit::calculateCountRequests($request->all());
        if (ClusterLimit::checkClustersLimits($countRequests)) {
            return response()->json([
                'errors' => ['limits' => __('Your limits are exhausted')]
            ], 422);
        }

        if (filter_var($request->input('searchRelevance'), FILTER_VALIDATE_BOOL)) {
            foreach ($this->clusterDomainLines((string) $request->input('domain')) as $line) {
                $link = parse_url($line);
                if (empty($link['host'])) {
                    return response()->json([
                        'errors' => ['domain' => __('url not valid')]
                    ], 422);
                }
            }
        }

        $user = Auth::user();
        if (filter_var($request->input('sendMessage'), FILTER_VALIDATE_BOOLEAN) && !$user->isTelegramConnected()) {
            return response()->json([
                'errors' => ['sendMessage' => __('Subscribe to notifications in Telegram first.')],
            ], 422);
        }

        dispatch(new StartClusterAnalyseQueue($request->all(), $user))->onQueue(ClusterQueues::name('main'));

        ClusterAnalysisDebugLog::info($progressId, 'http.analyseCluster.dispatched', [
            'queue' => ClusterQueues::name('main'),
        ]);

        return $this->withClusterDebug($progressId, [
            'result' => true,
            'totalPhrases' => count(array_unique(array_diff(explode("\n", str_replace("\r", "", $request['phrases'])), []))),
            'totalRequests' => $countRequests,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function withClusterDebug(string $progressId, array $payload, int $status = 200): JsonResponse
    {
        if (User::isUserAdmin() && $progressId !== '') {
            $payload['debug_log'] = ClusterAnalysisDebugLog::get($progressId);
            $payload['debug_admin'] = true;
        }

        return response()->json($payload, $status);
    }

    /**
     * @return array<int, string>
     */
    private function clusterDomainLines(string $domain): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $domain) ?: [];
        $out = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                $out[] = $line;
            }
        }

        return $out;
    }

    private function normalizeClusterDomains(string $domain): string
    {
        $lines = $this->clusterDomainLines($domain);
        if ($lines === []) {
            return '';
        }

        $normalized = array_map(function (string $line): string {
            if (!preg_match('#^https?://#i', $line)) {
                $line = 'https://' . ltrim($line, '/');
            }

            return $line;
        }, $lines);

        return implode("\n", $normalized);
    }

    public function startProgress(): JsonResponse
    {
        $progressId = md5(microtime(true));
        ClusterAnalysisDebugLog::clear($progressId);
        ClusterAnalysisDebugLog::info($progressId, 'http.startProgress', ['progress_id' => $progressId]);

        return $this->withClusterDebug($progressId, [
            'id' => $progressId,
        ], 201);
    }

    public function telegramStatus(): JsonResponse
    {
        $user = Auth::user();

        return response()->json([
            'connected' => $user ? $user->isTelegramConnected() : false,
        ]);
    }

    public function getProgress(string $id): JsonResponse
    {
        $cluster = ClusterResults::where('progress_id', '=', $id)->first();
        if (isset($cluster)) {
            ClusterQueue::where('progress_id', '=', $id)->delete();
            ClusterAnalysisDebugLog::info($id, 'http.getProgress.complete', [
                'object_id' => $cluster->id,
                'count_phrases' => $cluster->count_phrases,
            ]);

            return $this->withClusterDebug($id, [
                'count' => $cluster->count_phrases,
                'result' => Cluster::unpackCluster($cluster->result),
                'objectId' => $cluster->id,
            ]);
        }

        $progress = ClusterProgress::snapshot($id);
        ClusterAnalysisDebugLog::info($id, 'http.getProgress.pending', $progress);

        return $this->withClusterDebug($id, [
            'count' => $progress['queue_count'],
            'phrases_done' => $progress['phrases_done'],
            'phrases_pending' => $progress['phrases_pending'],
            'phrases_total' => $progress['phrases_total'],
            'waiting_in_queue' => $progress['waiting_in_queue'],
            'debug_state' => $progress,
        ]);
    }

    public function getProgressModify($id): JsonResponse
    {
        $cluster = ClusterResults::where('progress_id', '=', $id)->first();
        if (isset($cluster)) {
            ClusterQueue::where('progress_id', '=', $id)->delete();
            $cluster->request = json_decode($cluster->request, true);
            $cluster->region = Common::getRegionName($cluster->request['region']);
            return response()->json([
                'cluster' => $cluster,
            ]);
        }

        return response()->json([
            'count' => ClusterQueue::where('progress_id', '=', $id)->count(),
        ]);
    }

    public function fastScanClusters(Request $request): JsonResponse
    {
        $user = Auth::user();
        $cluster = new Cluster($request->all(), $user, false);
        $results = ClusterResults::findOrFail($request->input('resultId'));
        $cluster->setSites($results->sites_json);
        $cluster->searchClusters();
        $cluster->calculateClustersInfo();
        $clusters = $cluster->getClusters();
        ksort($clusters);
        arsort($clusters);

        return response()->json([
            'sites' => $clusters,
            'count' => count($clusters)
        ]);
    }

    public function clusterProjects(): View
    {
        return view('cluster.projects', $this->clusterProjectsViewData());
    }

    /**
     * @return array{projects: \Illuminate\Support\Collection, admin: bool, config: ClusterConfiguration|null}
     */
    private function clusterProjectsViewData(): array
    {
        $admin = User::isUserAdmin();
        $projects = ClusterResults::where('user_id', '=', Auth::id())
            ->where('show', '=', 1)->get([
                'id', 'user_id', 'comment', 'domain', 'count_phrases', 'count_clusters', 'clustering_level',
                'top', 'created_at', 'request'
            ]);

        foreach ($projects as $project) {
            $request = json_decode($project->request, true);
            $project->region = Common::getRegionName($request['region'] ?? '');
            $project->request = $request;
        }

        return [
            'projects' => $projects,
            'admin' => $admin,
            'config' => ClusterConfiguration::first(),
        ];
    }

    public function edit(Request $request): JsonResponse
    {
        ClusterResults::where('id', $request->id)
            ->where('user_id', '=', Auth::id())
            ->update([$request->option => $request->value]);

        return response()->json([]);
    }

    public function getClusterRequest(Request $request): JsonResponse
    {
        $cluster = ClusterResults::where('id', '=', $request->id)->first();

        if ($cluster->user_id !== Auth::id()) {
            return response()->json([
                'message' => __("You don't have access to this object")
            ], 500);
        }

        return response()->json([
            'created_at' => Carbon::parse($cluster->created_at)->toDateTimeString(),
            'request' => json_decode($cluster->request, true)
        ]);
    }

    public function showResult(int $id): View
    {
        return $this->renderClusterResultView($id, 'cluster-v2.show');
    }

    private function renderClusterResultView(int $id, string $viewName): View
    {
        $payload = $this->clusterResultViewPayload($id);
        if ($payload === null) {
            return abort(403);
        }

        if (!empty($payload['too_large'])) {
            return view('cluster.too-large', [
                'clusterId' => $payload['clusterId'],
                'countPhrases' => $payload['countPhrases'],
                'countClusters' => $payload['countClusters'],
            ]);
        }

        return view($viewName, [
            'cluster' => $payload['cluster'],
            'admin' => $payload['admin'],
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function clusterResultViewPayload(int $id): ?array
    {
        $cluster = ClusterResults::where('id', $id)->first([
            'count_clusters', 'count_phrases',
            'default_result', 'result',
            'request', 'user_id',
            'id', 'show'
        ]);

        if (!$cluster || !(($cluster->user_id == Auth::id() || User::isUserAdmin()) && $cluster->show === 1)) {
            return null;
        }

        $compressed = isset($cluster->default_result) ? $cluster->default_result : $cluster->result;
        $maxCompressedBytes = 40 * 1024 * 1024;
        if ($compressed !== null && strlen($compressed) > $maxCompressedBytes) {
            return [
                'too_large' => true,
                'clusterId' => $cluster->id,
                'countPhrases' => (int) $cluster->count_phrases,
                'countClusters' => (int) $cluster->count_clusters,
            ];
        }

        $cluster->result = isset($cluster->default_result)
            ? Common::uncompressArray($cluster->default_result, false)
            : Common::uncompressArray($cluster->result, false);
        unset($cluster->default_result);

        $cluster->request = json_decode($cluster->request, true);

        return [
            'cluster' => $cluster->toArray(),
            'admin' => User::isUserAdmin(),
        ];
    }

    public function setClusterRelevanceUrl(Request $request): JsonResponse
    {
        $cluster = ClusterResults::where('id', '=', $request->input('projectId'))
            ->where('user_id', '=', Auth::id())
            ->first();

        if (isset($cluster)) {
            if ($request->input('type') === 'notDefault') {
                $results = Cluster::unpackCluster($cluster->result);
            } else {
                $results = Cluster::unpackCluster($cluster->default_result);
            }

            foreach ($results as $key => $items) {
                foreach ($items as $phrase => $item) {
                    if ($phrase === $request->input('phrase')) {
                        $results[$key][$phrase]['link'] = $request->input('url');
                        unset($results[$key][$phrase]['relevance']);
                    }
                }
            }

            if ($request->input('type') === 'notDefault') {
                $cluster->result = base64_encode(gzcompress(json_encode($results), 9));
            } else {
                $cluster->default_result = base64_encode(gzcompress(json_encode($results), 9));
            }

            $cluster->save();

            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false], 400);
    }

    public function setClusterRelevanceUrls(Request $request): JsonResponse
    {
        $cluster = ClusterResults::where('id', '=', $request->input('projectId'))
            ->where('user_id', '=', Auth::id())
            ->first();

        if (isset($cluster)) {
            if ($request->input('type') === 'notDefault') {
                $results = Cluster::unpackCluster($cluster->result);
            } else {
                $results = Cluster::unpackCluster($cluster->default_result);
            }

            foreach ($results as $key => $items) {
                foreach ($items as $phrase => $item) {
                    if (in_array($phrase, $request->input('phrases'))) {
                        $results[$key][$phrase]['link'] = $request->input('url');
                        unset($results[$key][$phrase]['relevance']);
                    }
                }
            }

            if ($request->input('type') === 'notDefault') {
                $cluster->result = base64_encode(gzcompress(json_encode($results), 9));
            } else {
                $cluster->default_result = base64_encode(gzcompress(json_encode($results), 9));
            }
            $cluster->save();

            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false], 400);
    }

    public function downloadClusterResult(ClusterResults $cluster, string $type)
    {
        if ($cluster->created_at <= Carbon::parse('00:00 22.02.2023')) {
            return abort(403, __('In order to edit this result, you need to reshoot it'));
        }

        if ((User::isUserAdmin() || $cluster->user_id == Auth::id()) && ($type === 'xls' || $type === 'csv')) {
            if (isset($cluster->domain)) {
                $domain = str_replace(['https://', 'http://'], '', $cluster->domain);
                $fileName = Carbon::parse($cluster->created_at)->toDateString() . '-' . str_replace(['.', '/', ' '], '-', $domain);
            } else {
                $fileName = Carbon::parse($cluster->created_at)->toDateString() . '-' . $cluster->id;
            }

            $file = Excel::download(new ClusterResultExport($cluster), $fileName . '.' . $type);

            Common::fileExport($file, $type, $fileName);
        }

        return abort(403);
    }

    public function clusterConfiguration(): View
    {
        if (!User::isUserAdmin()) {
            return abort(403);
        }

        return view('cluster.config', [
            'config' => ClusterConfiguration::first(),
            'config_classic' => ClusterConfigurationClassic::first(),
            'admin' => User::isUserAdmin(),
            'counter' => ClusterResults::countScansInCurrentMonth(),
            'uniqueUsers' => [
                30 => ClusterResults::countUniqueUsersSinceDays(30),
                60 => ClusterResults::countUniqueUsersSinceDays(60),
                90 => ClusterResults::countUniqueUsersSinceDays(90),
            ],
        ]);
    }

    public function changeClusterConfiguration(Request $request): RedirectResponse
    {
        if (!User::isUserAdmin()) {
            return abort(403);
        }

        if ($request->input('type') === 'pro') {
            $config = ClusterConfiguration::first();
        } else {
            $config = ClusterConfigurationClassic::first();
        }

        $params = $request->all();
        unset($params['type']);

        $config->update($params);

        return Redirect::route('cluster.configuration');
    }

    public function downloadClusterSites(Request $request): JsonResponse
    {
        $cluster = ClusterResults::where('id', '=', $request->projectId)->first('default_result');
        $results = Common::uncompressArray($cluster->default_result);

        foreach ($results as $result) {
            if (key_exists($request->phrase, $result)) {
                return response()->json([
                    'sites' => $result[$request->phrase]['sites'],
                    'mark' => $result[$request->phrase]['mark'] ?? 0
                ]);
            }
        }

        return response()->json([], 404);
    }

    public function downloadClusterCompetitors(Request $request): JsonResponse
    {
        $cluster = ClusterResults::where('id', '=', $request->projectId)->first('default_result');
        $results = Common::uncompressArray($cluster->default_result);
        arsort($results[$request->key]['finallyResult']['sites']);

        return response()->json([
            'competitors' => $results[$request->key]['finallyResult']['sites']
        ]);
    }

    public function downloadClusterPhrases(Request $request): JsonResponse
    {
        $cluster = ClusterResults::where('id', '=', $request->projectId)->first('request');
        $phrases = json_decode($cluster->request, true)['phrases'];

        return response()->json([
            'phrases' => explode("\n", $phrases)
        ]);
    }


    /**
     * @param array<string, array<string, mixed>> $clusters
     * @return array{groups: array<int, array<string, mixed>>, singles: array<int, array<string, mixed>>, groupNames: string[]}
     */
    protected function buildClusterEditV2Payload(array $clusters): array
    {
        ksort($clusters);

        $groups = [];
        $singles = [];
        $groupNames = [];

        foreach ($clusters as $mainPhrase => $items) {
            $phraseRows = [];
            foreach ($items as $phrase => $item) {
                if ($phrase === 'finallyResult' || !is_array($item)) {
                    continue;
                }

                $phraseRows[] = [
                    'phrase' => $phrase,
                    'based' => (int) ($item['based']['number'] ?? $item['based'] ?? 0),
                    'phrased' => (int) ($item['phrased']['number'] ?? $item['phrased'] ?? 0),
                    'target' => (int) ($item['target']['number'] ?? $item['target'] ?? 0),
                    'url' => $this->extractClusterPhraseUrl($item),
                ];
            }

            if (count($items) <= 2) {
                foreach ($phraseRows as $row) {
                    $singles[] = array_merge($row, ['from' => $mainPhrase]);
                }
                continue;
            }

            $groupNames[] = $mainPhrase;
            $groups[] = [
                'name' => $mainPhrase,
                'phrases' => $phraseRows,
                'totals' => [
                    'based' => array_sum(array_column($phraseRows, 'based')),
                    'phrased' => array_sum(array_column($phraseRows, 'phrased')),
                    'target' => array_sum(array_column($phraseRows, 'target')),
                ],
                'relevance' => $this->summarizeGroupRelevance($phraseRows),
            ];
        }

        return [
            'groups' => $groups,
            'singles' => $singles,
            'groupNames' => $groupNames,
        ];
    }

    /**
     * @param array<string, mixed> $item
     */
    protected function extractClusterPhraseUrl(array $item): ?string
    {
        if (!empty($item['link']) && is_string($item['link'])) {
            return $item['link'];
        }

        if (!empty($item['relevance']) && is_array($item['relevance'])) {
            foreach ($item['relevance'] as $url) {
                if (is_string($url) && $url !== '') {
                    return $url;
                }
            }
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $phraseRows
     * @return array{url: string|null, uniform: bool, count: int}
     */
    protected function summarizeGroupRelevance(array $phraseRows): array
    {
        $urls = array_values(array_filter(array_column($phraseRows, 'url')));
        $unique = array_values(array_unique($urls));

        return [
            'url' => $unique[0] ?? null,
            'uniform' => count($unique) === 1 && count($unique) > 0,
            'count' => count($unique),
        ];
    }

    public function editClusters(ClusterResults $cluster)
    {
        if ($cluster->created_at <= Carbon::parse('00:00 22.02.2023')) {
            return abort(403, __('In order to edit this result, you need to reshoot it'));
        }

        $cluster->request = json_decode($cluster->request, true);
        $editPayload = $this->buildClusterEditV2Payload(Cluster::unpackCluster($cluster->result));

        return view('cluster-v2.edit', [
            'cluster' => $cluster,
            'admin' => User::isUserAdmin(),
            'groups' => $editPayload['groups'],
            'singles' => $editPayload['singles'],
            'groupNames' => $editPayload['groupNames'],
        ]);
    }

    public function editCluster(Request $request): ?JsonResponse
    {
        $cluster = ClusterResults::where('id', '=', $request->input('id'))->first();
        if (User::isUserAdmin() || $cluster->user_id == Auth::id()) {
            $clusterItem = [];

            $cluster->result = Cluster::unpackCluster($cluster->result);
            $clusters = $cluster->result;
            foreach ($clusters as $mainPhrase => $items) {
                foreach ($items as $phrase => $item) {
                    if ($phrase === $request->input('phrase')) {
                        unset($item['merge']);
                        unset($clusters[$mainPhrase][$phrase]);
                        $clusters[$request->input('mainPhrase')][$request->input('phrase')] = $item;
                        $clusterItem = $item;
                    }
                }
            }

            Cluster::recalculateClusterInfo($cluster, $clusters);

            return response()->json([
                'success' => true,
                'countClusters' => $cluster->count_clusters,
                'based' => $clusterItem['based']['number'] ?? $clusterItem['based'],
                'phrased' => $clusterItem['phrased']['number'] ?? $clusterItem['phrased'],
                'target' => $clusterItem['target']['number'] ?? $clusterItem['target'],
            ]);

        }

        return abort(403);
    }

    public function checkGroupName(Request $request): JsonResponse
    {
        $cluster = ClusterResults::where('id', '=', $request->input('id'))->first();
        if (User::isUserAdmin() || $cluster->user_id == Auth::id()) {
            $cluster->result = Cluster::unpackCluster($cluster->result);
            $result = Cluster::isGroupNameExist($request->input('groupName'), $cluster->result);

            if ($result['error']) {
                return response()->json([
                    'message' => $result['message'],
                ], 400);
            }

            return response()->json([
                'result' => $result ?? false
            ]);
        }

        return response()->json([
            'success' => false,
        ], 400);

    }

    public function changeGroupName(Request $request): JsonResponse
    {
        $cluster = ClusterResults::where('id', '=', $request->input('id'))->first();
        if (User::isUserAdmin() || $cluster->user_id == Auth::id()) {
            $cluster->result = Cluster::unpackCluster($cluster->result);
            $keys = array_keys($cluster->result);

            if (in_array($request->input('newGroupName'), $keys)) {
                if (count($cluster->result[$request->input('newGroupName')]) > 2) {
                    return response()->json([
                        'success' => false,
                    ], 400);
                } else {
                    $item = $cluster->result[$request->input('newGroupName')][$request->input('newGroupName')];
                }

            }

            $clusters = $cluster->result;
            $clusters[$request->input('newGroupName')] = $clusters[$request->input('oldGroupName')];
            if (isset($item)) {
                $clusters[$request->input('newGroupName')][$request->input('newGroupName')] = $item;
            }
            unset($clusters[$request->input('oldGroupName')]);
            ksort($clusters);
            arsort($clusters);
            $cluster->result = base64_encode(gzcompress(json_encode($clusters), 9));
            $cluster->count_clusters = count($clusters);
            $cluster->save();

            return response()->json([
                'success' => true,
                'move' => isset($item)
            ]);
        }

        return response()->json([
            'success' => false,
        ], 400);
    }

    public function confirmationNewCluster(Request $request): ?JsonResponse
    {
        $cluster = ClusterResults::where('id', '=', $request->input('projectId'))->first();
        if (User::isUserAdmin() || $cluster->user_id == Auth::id()) {
            $clusters = Cluster::unpackCluster($cluster->result);
            foreach ($clusters as $mainPhrase => $items) {
                foreach ($items as $phrase => $item) {
                    if (in_array($phrase, $request->input('phrases')) && $request->input('mainPhrase') !== $mainPhrase) {
                        $clusters[$request->input('mainPhrase')][$phrase] = $item;
                        unset($clusters[$mainPhrase][$phrase]);
                    }
                }
            }

            Cluster::recalculateClusterInfo($cluster, $clusters);

            return response()->json([
                'success' => true,
                'groupId' => Str::random(10),
            ]);
        }

        return abort(403);

    }

    public function resetAllChanges(Request $request): ?JsonResponse
    {
        $cluster = ClusterResults::where('id', '=', $request->input('projectId'))->first();
        if (User::isUserAdmin() || $cluster->user_id == Auth::id()) {
            $cluster->result = $cluster->default_result;
            $cluster->html = null;
            $cluster->save();

            return response()->json([]);
        }

        return response()->json([], 403);
    }

    public function downloadClusterGroup(Request $request)
    {
        $cluster = ClusterResults::find($request->input('id'));
        $clusters = Cluster::unpackCluster($cluster->result);
        $array = json_decode($request->json, true);
        $file = Excel::download(new ClusterGroupExport($clusters, $array), "group_results.$request->type");

        Common::fileExport($file, $request->type, 'group_results');
    }

    public function saveTree(Request $request): JsonResponse
    {
        $cluster = ClusterResults::where('id', '=', $request->input('projectId'))->first();
        if (User::isUserAdmin() || $cluster->user_id == Auth::id()) {
            $cluster->html = $request->html;
            $cluster->save();

            return response()->json([]);
        }

        return response()->json([], 403);
    }

    public function setCleaningInterval(Request $request): RedirectResponse
    {
        ClusterConfiguration::where('id', '>', 0)->update([
            'cleaning_interval' => $request->input('cleaning_interval')
        ]);

        return Redirect::back();
    }
}
