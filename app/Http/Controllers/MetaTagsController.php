<?php

namespace App\Http\Controllers;


use App\Classes\Tariffs\Facades\Tariffs;
use App\Exports\MetaTagsFormExport;
use App\Exports\MetaTagsHistoriesExport;
use App\Exports\MetaTagsCompareHistoriesExport;
use App\Mail\MetaTagsEmail;
use App\MetaTag;
use App\MetaTagsHistory;
use App\MetaTagsSettings;
use App\Support\MetaTagsAdminStats;
use App\User;
use Carbon\Carbon;
use ErrorException;
use Illuminate\Contracts\View\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use KubAT\PhpSimple\HtmlDomParser;
use Ixudra\Curl\Facades\Curl;

/**
 * Class MetaTagsController
 * @package App\Http\Controllers
 */
class MetaTagsController extends Controller
{
    protected $html;

    protected $tags = [
        ['name' => 'title', 'tag' => 'title', 'type' => 'string'],
        ['name' => 'description', 'tag' => 'meta[name=description]', 'type' => 'string'],
        ['name' => 'keywords', 'tag' => 'meta[name=keywords]', 'type' => 'string'],
        ['name' => 'canonical', 'tag' => 'link[rel=canonical]', 'type' => 'int'],
        ['name' => 'noindex', 'tag' => 'noindex', 'type' => 'int'],
        ['name' => 'robots', 'tag' => 'robots', 'type' => 'string'],
        ['name' => 'h1', 'tag' => 'h1', 'type' => 'string'],
        ['name' => 'h2', 'tag' => 'h2', 'type' => 'string'],
        ['name' => 'h3', 'tag' => 'h3', 'type' => 'string'],
        ['name' => 'a', 'tag' => 'a', 'type' => 'string'],
    ];

    protected $response;

    /**
     * MetaTagsController constructor.
     */
    public function __construct()
    {
        $this->middleware(['permission:Meta tags']);
    }

    public function tagsOptions()
    {
        return response()->json($this->buildTagsOptions());
    }

    /**
     * @return array<int, array{value: string, text: string}>
     */
    private function buildTagsOptions(): array
    {
        $labels = [
            'title' => __('Meta tags field title'),
            'description' => __('Meta tags field description'),
            'keywords' => __('Meta tags field keywords'),
            'canonical' => __('Meta tags field canonical'),
            'noindex' => __('Meta tags field noindex'),
            'robots' => __('Meta tags field robots'),
            'h1' => __('Meta tags field h1'),
            'h2' => __('Meta tags field h2'),
            'h3' => __('Meta tags field h3'),
            'h4' => __('Meta tags field h4'),
            'h5' => __('Meta tags field h5'),
            'h6' => __('Meta tags field h6'),
            'a' => __('Meta tags field a'),
        ];

        $options = [];

        foreach ($this->tags as $tag) {
            $name = $tag['name'];
            $options[] = [
                'value' => $name,
                'text' => $labels[$name] ?? $name,
            ];
        }

        return $options;
    }

    public function settings(Request $request)
    {
        $settings = new MetaTagsSettings();

        if ($request->isMethod('post') && $request->has('delete_records')) {
            $settings->updateOrCreate(['code' => 'delete_records'], ['value' => $request->input('delete_records')]);

            return redirect()->route('meta-tags.settings')->with('status', __('Saved'));
        }

        $delete_records = $settings->where('code', 'delete_records')->value('value');
        $registry = MetaTagsAdminStats::snapshot();
        $stats = $registry['summary'];

        return view('meta-tags.settings', compact('delete_records', 'registry', 'stats'));
    }

    /**
     * @param $id
     * @return BinaryFileResponse
     */
    public function export($id)
    {
        return Excel::download(new MetaTagsHistoriesExport($id), 'meta_tags.csv');
    }

    /**
     * @param $id
     * @return BinaryFileResponse
     */
    public function exportCompare($id, $id_compare)
    {
        return Excel::download(new MetaTagsCompareHistoriesExport($id, $id_compare), 'meta_tags_compare.csv');
    }

    protected function lang()
    {

        return collect([
            'check_url' => __('Meta tags urls label'),
            'urls_placeholder' => __('Meta tags urls placeholder'),
            'urls_empty' => __('Meta tags urls empty'),
            'fetch_fields' => __('Meta tags fetch fields'),
            'fetch_fields_hint' => __('Meta tags fetch fields hint'),
            'tags_loading' => __('Meta tags tags loading'),
            'tags_load_error' => __('Meta tags tags load error'),
            'tags_none_selected' => __('Meta tags tags none selected'),
            'timeout_request' => __('Timeout'),
            'timeout_hint' => __('Meta tags timeout hint'),
            'length_word' => __('Length'),
            'length_hint' => __('Meta tags length hint'),
            'title' => __('Meta tags title length'),
            'description' => __('Meta tags description length'),
            'keywords' => __('Meta tags keywords length'),
            'min' => __('Minimum'),
            'max' => __('Maximum'),
            'send' => __('Meta tags check button'),
            'step_label' => __('Meta tags step label'),
            'step_1_title' => __('Meta tags step 1 title'),
            'step_1_hint' => __('Meta tags step 1 hint'),
            'step_2_title' => __('Meta tags step 2 title'),
            'step_2_hint' => __('Meta tags step 2 hint'),
            'step_3_title' => __('Meta tags step 3 title'),
            'step_3_hint' => __('Meta tags step 3 hint'),
            'results_title' => __('Meta tags results title'),
            'projects_title' => __('Meta tags projects title'),
            'projects_empty_hint' => __('Meta tags projects empty hint'),
            'projects' => __('Projects'),
            'id' => __('ID'),
            'name' => __('Name'),
            'period' => __('Period'),
            'timeout' => __('Timeout'),
            'link' => __('Link'),
            'status' => __('Status'),
            'off' => __('Off'),
            'on' => __('On'),
            'history' => __('History'),
            'start' => __('Start'),
            'action_run' => __('Meta tags action run'),
            'action_tip_history' => __('Meta tags action tip history'),
            'action_tip_run' => __('Meta tags action tip run'),
            'action_tip_edit' => __('Meta tags action tip edit'),
            'action_tip_delete' => __('Meta tags action tip delete'),
            'edit' => __('Edit'),
            'delete' => __('Delete'),
            'filter' => __('Filter'),
            'all' => __('All'),
            'done' => __('Done'),
            'text_analysis' => __('Text analysis'),
            'save_as_project' => __('Save as project'),
            'check_interval_every' => __('Check interval every'),
            'hours' => __('hours'),
            'period_manual' => __('Meta tags period manual'),
            'period_6h' => __('Meta tags period 6h'),
            'period_12h' => __('Meta tags period 12h'),
            'period_24h' => __('Meta tags period 24h'),
            'ms_unit' => __('Meta tags ms unit'),
            'default_project_name' => __('Meta tags default project name'),
            'delete_confirm' => __('Meta tags delete confirm'),
            'history_saved' => __('Meta tags history saved'),
            'saved_success' => __('Meta tags saved success'),
            'deleted_success' => __('Meta tags deleted success'),
            'export_csv' => __('Meta tags export csv'),
            'export_xlsx' => __('Meta tags export xlsx'),
            'save_project' => __('Save project'),
            'project_name' => __('Project name'),
            'close' => __('Close'),
            'save' => __('Save'),
            'export' => __('Export'),
            'tag' => __('Tag'),
            'content' => __('Content'),
            'count' => __('Count'),
            'main_problems' => __('Main problems'),
            'go_to_site' => __('Go to site'),
            'actions' => __('Actions'),
        ]);
    }

    public function index()
    {
        $lang = $this->lang();
        $tagsOptions = $this->buildTagsOptions();

        return view('meta-tags.index', compact('lang', 'tagsOptions'));
    }

    /**
     * Список проектов мета-тегов (AJAX — не встраивать в HTML).
     */
    public function projectsForUser()
    {
        $projects = Auth::user()->metaTags()
            ->latest()
            ->get([
                'id',
                'user_id',
                'status',
                'name',
                'period',
                'links',
                'timeout',
                'title_min',
                'title_max',
                'description_min',
                'description_max',
                'keywords_min',
                'keywords_max',
                'created_at',
                'updated_at',
            ]);

        return response()->json($projects);
    }

    /**
     * @param Request $request
     * @return array
     */
    public function getMetaTags(Request $request)
    {
        $title = $request->input('url', false);
        $length = $request->input('length', false);
        $tags = $request->input('tags', false);

        $this->tags = array_filter($this->tags, function($tag) use ($tags) {
            return in_array($tag['name'], $tags);
        });

        return $this->dataMetaTags($title, $length);
    }

    public function getTariffMetaTagsPages()
    {
        /** @var User $user */
        $user = Auth::user();
        if ($tariff = $user->tariff()) {
            $tariff = $tariff->getAsArray();
            if (array_key_exists('MetaTagsPages', $tariff['settings'])) {
                return collect($tariff['settings']['MetaTagsPages']);
            }
        }

        return collect([]);
    }

    /**
     * @param $title
     * @param $length
     * @return array
     */
    protected function dataMetaTags($title, $length)
    {
        $error = [];
        $recommend_length = [];
        $url = $title;

        foreach ($length as $len) {
            $recommend_length[$len['id'] . '_min'] = $len['input']['min'];
            $recommend_length[$len['id'] . '_max'] = $len['input']['max'];
        }

        $data = $this->domain($title)->get();

        foreach ($data as $tag => $value) {
            $error['main'][$tag] = $this->errorsMetaTags($tag, $value, 'main', $recommend_length);


            if ($this->response['status'] !== 200 && $tag === 'title') {
                $status = 'code:' . $this->response['status'];
                $error['badge'][$status] = [$this->templateErrors(__('Error') . ' ' . __('code') . ': ' . $this->response['status'], '')];
            }

            $error['badge'][$tag] = $this->errorsMetaTags($tag, $value, 'badge', $recommend_length);
        }

        if ($this->response["redirect"]) {
            $title = $this->response["redirect"];
            $url = $this->response['headers']['Location'];
        }

        return compact('title', 'url', 'data', 'error');
    }

    /**
     * @param string $domain
     * @return $this
     */
    public function domain(string $domain)
    {
        $html = Curl::to($domain)
            ->allowRedirect()
            ->withResponseHeaders()
            ->returnResponseArray()
            ->get();

        $html["redirect"] = "";

        if (array_key_exists('Location', $html['headers'])) {
            if ($html['headers']['Location'] != $domain) {
                $html["redirect"] = implode(" → ", [$domain, $html['headers']['Location']]);
            }
        }

        $this->response = $html;

        $this->html = HtmlDomParser::str_get_html($html['content']);

        return $this;
    }

    /**
     * Get array http
     *
     * @return array
     */
    public function get()
    {
        $result = [];

        foreach ($this->tags as $tag) {
            $result[$tag['name']] = $this->getByString($tag['tag']);
        }

        return $result;
    }

    public function getByString(string $tag)
    {
        $el = $this->html->find($tag);

        if (!$el)
            return false;

        $arr = [];
        foreach ($el as $e) {

            if (strlen(trim($e->plaintext)) > 1)
                $arr[] = trim($e->plaintext);
            elseif (isset($e->attr['content']))
                $arr[] = trim($e->attr['content']);
            else
                $arr[] = trim($e->outertext);
        }

        return $arr;
    }

    public function exportForm(Request $request)
    {
        if ($request->input('format', 'csv') == "xlsx")
        {
            return $this->exportFormXLS($request);
        }

        return $this->exportFormCSV($request);
    }

    public function exportFormXLS(Request $request)
    {
        return Excel::download(new MetaTagsFormExport($request->input('result')), "meta_tags.xlsx", \Maatwebsite\Excel\Excel::XLSX);
    }

    public function exportFormCSV(Request $request)
    {
        return Excel::download(new MetaTagsFormExport($request->input('result')), "meta_tags.csv", \Maatwebsite\Excel\Excel::CSV);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return Model
     */
    public function store(Request $request): Model
    {
        /** @var User $user */
        $user = Auth::user();
        $model = $user->metaTags();

        if ($tariff = $user->tariff()) {

            $tariff = $tariff->getAsArray();
            if (array_key_exists('MetaTagsProject', $tariff['settings'])) {

                if ($model->count() >= $tariff['settings']['MetaTagsProject']['value']) {
                    abort(403, $tariff['settings']['MetaTagsProject']['message']);
                }
            }
        }

        $meta = $model->create($request->all((new MetaTag)->getFillable()));

        $this->storeHistories($request, $meta->id);

        return $meta;
    }

    /**
     * @param Request $request
     * @param $id
     */
    public function storeHistories(Request $request, $id)
    {
        $history = $request->input('histories', false);

        if ($history) {
            $history_links = count($history);
            $historyJson = collect($history)->toJson();

            MetaTagsHistory::create([
                'meta_tag_id' => $id,
                'quantity' => $history_links,
                'errors_count' => $this->countHistoryErrorsFromJson($historyJson),
                'data' => $historyJson,
            ]);
        }
    }

    /**
     * All histories by meta tags
     *
     * @param $id
     * @return array|Factory|View|mixed
     */
    public function showHistories($id)
    {
        $project = Auth::user()->metaTags()->findOrFail($id);

        $histories = $project->histories()
            ->select(['id', 'meta_tag_id', 'ideal', 'quantity', 'errors_count', 'created_at', 'updated_at'])
            ->orderBy('ideal', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(50);

        $histories->getCollection()->transform(function ($item) {
            $item->error_quantity = $item->errors_count !== null ? (int) $item->errors_count : null;

            return $item;
        });

        return view('meta-tags.show', compact('project', 'histories'));
    }

    /**
     * One history by meta tags
     *
     * @param $id
     * @return array|Factory|View|mixed
     * @throws ErrorException
     */
    public function showHistory($id)
    {

        $history = MetaTagsHistory::query()
            ->select(['id', 'meta_tag_id', 'quantity', 'created_at'])
            ->with(['project:id,user_id,name'])
            ->findOrFail($id);

        if ($history->project->user_id != Auth::id()) {
            throw new ErrorException('User not valid');
        }

        $project = $history->project;
        $historyId = (int) $history->id;
        $lang = $this->lang();

        return view('meta-tags.history', compact('project', 'lang', 'historyId'));
    }

    /**
     * JSON истории мета-тегов (не встраивать data в HTML — может быть >2 MB).
     */
    public function historyData(Request $request, $id)
    {
        $history = MetaTagsHistory::query()
            ->select(['id', 'meta_tag_id', 'data'])
            ->findOrFail($id);

        $ownsProject = MetaTag::query()
            ->where('id', $history->meta_tag_id)
            ->where('user_id', Auth::id())
            ->exists();

        if (! $ownsProject) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = json_decode($history->data, true) ?? [];

        if (! $request->has('offset') && ! $request->has('limit')) {
            return response()->json($data);
        }

        $offset = max(0, (int) $request->get('offset', 0));
        $limit = min(100, max(1, (int) $request->get('limit', 50)));
        $slice = array_slice($data, $offset, $limit);

        return response()->json([
            'items' => $slice,
            'total' => count($data),
            'offset' => $offset,
            'limit' => $limit,
            'has_more' => ($offset + $limit) < count($data),
        ]);
    }

    public function showHistoryCompare($id, $id_compare)
    {
        $response = [];

        $history = MetaTagsHistory::query()->findOrFail($id);
        $history_compare = MetaTagsHistory::query()->findOrFail($id_compare);

        $projectIds = collect([$history->meta_tag_id, $history_compare->meta_tag_id])
            ->filter()
            ->unique()
            ->values()
            ->all();

        $owns = MetaTag::query()
            ->where('user_id', Auth::id())
            ->whereIn('id', $projectIds)
            ->count();

        if ($owns < count($projectIds)) {
            abort(403);
        }

        $this->createCompareArray($history, 'card', $response);
        $this->createCompareArray($history_compare, 'card_compare', $response);

        $filterErrors = [];
        $filterDiff = [];

        foreach ($response as $url => &$row) {
            $row['diff_tags'] = [];
            if (! isset($row['card']['tags'], $row['card_compare']['tags'])) {
                continue;
            }

            $left = $this->metaTagTagsToArray($row['card']['tags']);
            $right = $this->metaTagTagsToArray($row['card_compare']['tags']);
            $tagKeys = array_unique(array_merge(array_keys($left), array_keys($right)));

            foreach ($tagKeys as $tag) {
                if (! $this->metaTagValuesEqual($left[$tag] ?? null, $right[$tag] ?? null)) {
                    $row['diff_tags'][$tag] = $tag;
                    $filterDiff[$tag] = $this->metaTagFieldLabel($tag);
                }
            }
        }
        unset($row);

        $collection = collect($response);

        foreach ($response as $r) {
            if (! empty($r['error_tags'])) {
                foreach ($r['error_tags'] as $tag) {
                    $filterErrors[$tag] = $this->metaTagFieldLabel($tag);
                }
            }
        }

        ksort($filterErrors);
        ksort($filterDiff);

        $lang = $this->lang();

        return view('meta-tags.histories_compare', [
            'collection' => $collection,
            'filterErrors' => $filterErrors,
            'filterDiff' => $filterDiff,
            'metaDiffFields' => config('cabinet-meta-tags.compare_meta_fields', []),
            'history' => $history,
            'historyCompare' => $history_compare,
            'lang' => $lang,
        ]);
    }

    private function metaTagValuesEqual($left, $right): bool
    {
        if (is_object($left) || is_object($right)) {
            $left = json_decode(json_encode($left), true);
            $right = json_decode(json_encode($right), true);
        }

        return json_encode($left) === json_encode($right);
    }

    /**
     * @param array|object|null $tags
     * @return array<string, mixed>
     */
    private function metaTagTagsToArray($tags): array
    {
        if (is_array($tags)) {
            return $tags;
        }

        if (is_object($tags)) {
            return json_decode(json_encode($tags), true) ?: [];
        }

        return [];
    }

    private function metaTagFieldLabel(string $tag): string
    {
        $key = 'Meta tags field ' . $tag;
        $label = __($key);

        return $label === $key ? ucfirst($tag) : $label;
    }

    protected function createCompareArray($model, $name = 'card', &$response = [])
    {
        $histories = json_decode($model->data);
        foreach ($histories as $item) {
            $response[$item->title][$name]['id'] = $model->id;
            $response[$item->title][$name]['date'] = $model->created_at->format('d.m.Y');
            $response[$item->title][$name]['tags'] = $item->data;
            $response[$item->title][$name]['error'] = $item->error->main;

            foreach ($item->error->badge as $t => $b) {
                if (count($b)) {
                    $response[$item->title]['badge'][$model->created_at->format('d.m.Y') . '(' . $model->id . ')'][$t] = $b;
                    $response[$item->title]['error_tags'][$t] = $t;
                }
            }
        }

        return $response;
    }

    /**
     * @param $tag
     * @param $val
     * @param array $recommend_length
     * @param $type
     * @return array
     */
    public function errorsMetaTags($tag, $val, $type, $recommend_length = array())
    {

        if (empty($type))
            $type = 'main';

        $strSmall = '';
        $errors = [];

        if (is_array($val)) {

            if (count($val) > 1 && ($tag === 'title' || $tag === 'description' || $tag === 'keywords' || $tag === 'canonical' || $tag === 'h1')) {

                if ($type === 'main')
                    $strSmall = __('Duplicate tag, Check the page and leave 1 tag');

                $errors[] = $this->templateErrors('< ' . $tag . ' > ' . count($val) . 'шт.', $strSmall);
            } elseif (count($val) === 1) {

                if (isset($recommend_length[$tag . '_min']) && $recommend_length[$tag . '_max']) {

                    $min = $recommend_length[$tag . '_min'];
                    $max = $recommend_length[$tag . '_max'];

                    if ($min && $max) {
                        if (strlen($val[0]) < $min || strlen($val[0]) > $max) {

                            if ($type === 'main')
                                $strSmall = __('You have set a range from') . ' ' . $min . ' ' . __('to') . ' ' . $max;

                            $errors[] = $this->templateErrors(__('Length') . ' ' . $tag . ': ' . strlen($val[0]), $strSmall);
                        }
                    }
                }
            }
        }

        if ($type === 'main') {
            if (empty($errors))
                $errors[] = '<span class="badge text-bg-success">' . __('No problem') . '</span>';
        }

        return $errors;
    }

    protected function templateErrors($text, $smallText)
    {
        $str = '';

        if (strlen($text))
            $str .= '<span class="badge badge-danger mr-1">' . $text . '</span>';

        if (strlen($smallText))
            $str .= '<br/><small>' . $smallText . '</small>';

        return $str;
    }

    public function updateHistoriesIdeal(Request $request, $id)
    {

        $project = Auth::user()->metaTags()->find($id);
        $project->histories()->where('ideal', true)->update(['ideal' => false]);

        $history_id = $request->input('id', false);
        $project->histories()->where('id', $history_id)->update(['ideal' => true]);

        return $history_id;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param int $id
     * @return void
     */
    public function update(Request $request, $id)
    {
        Auth::user()->metaTags()->find($id)->update($request->all((new MetaTag)->getFillable()));
        return Auth::user()->metaTags()->find($id);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return Response
     */
    public function destroy($id)
    {
        Auth::user()->metaTags()->find($id)->delete();
    }

    public function destroyHistory($id)
    {
        $history = MetaTagsHistory::query()
            ->with(['project:id,user_id,name'])
            ->findOrFail($id);

        if ($history->project->user_id != Auth::id())
            throw new ErrorException('User not valid');

        $history->delete();
    }

    /**
     * Количество ошибок в снимке (без загрузки data в списке историй).
     */
    protected function countHistoryErrorsFromJson(?string $json): int
    {
        if ($json === null || $json === '') {
            return 0;
        }

        $errors = json_decode($json);

        if (!is_array($errors) && !($errors instanceof \Traversable)) {
            return 0;
        }

        $errorQuantity = 0;

        foreach ($errors as $e) {
            if (!isset($e->error)) {
                continue;
            }

            $arrError = Arr::flatten($e->error->badge ?? []);

            if (is_array($arrError)) {
                $errorQuantity += count($arrError);
            }
        }

        return $errorQuantity;
    }
}
