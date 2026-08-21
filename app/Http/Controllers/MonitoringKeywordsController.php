<?php

namespace App\Http\Controllers;

use App\Classes\Monitoring\Mastered;
use App\Classes\Monitoring\MonitoringPositionDates;
use App\Classes\Monitoring\MonitoringTableResponseCache;
use App\Classes\Position\PositionStore;
use App\Jobs\PositionQueue;
use App\Location;
use App\MonitoringKeyword;
use App\MonitoringKeywordPrice;
use App\MonitoringOccurrence;
use App\MonitoringPosition;
use App\MonitoringProject;
use App\MonitoringProjectColumnsSetting;
use App\MonitoringProjectSettings;
use App\MonitoringSearchengine;
use App\User;
use function GuzzleHttp\Psr7\str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MonitoringKeywordsController extends Controller
{
    const GROUP_NAME = 'group_name';

    /** Короткий формат даты в заголовках колонок таблицы позиций (влезает в ~88px). */
    private const MONITORING_TABLE_DATE_FORMAT = 'd.m.y';

    /** Сколько дней позиций грузить в одном чанке (поэтапная заливка ячеек). */
    private const TABLE_POSITION_CHUNK_DAYS = 14;

    protected $user;
    protected $project;
    protected $projectID;
    protected $queries;
    protected $regions;
    protected $columns;
    protected $mode = "range";
    protected $total = 0;
    protected $offset = [];
    /** @var bool */
    protected $lazyPositions = false;
    /** @var list<array{from: string, to: string}>|null */
    protected $positionChunks = null;

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();

            return $next($request);
        });

        $this->columns = $this->getColumns();
    }

    public function setMode(string $mode = null): void
    {
        if(strlen($mode) > 1)
            $this->mode = $mode;
    }

    public function setColumns(Collection $collection)
    {
        $this->columns = $this->columns->merge($collection);
    }

    public function setProjectID($id)
    {
        $this->projectID = $id;

        return $this;
    }

    public function getProjectID()
    {
        if(!$this->projectID)
            throw new \Exception('Project ID does not exist, insert project ID.');

        return $this->projectID;
    }

    private function init()
    {
        $id = $this->getProjectID();
        $this->project = $this->user->monitoringProjects()->find($id);
        $this->regions = $this->project->searchengines()->with('location')->orderBy('id', 'asc')->get();
        $this->queries = $this->project->keywords()
            ->select('monitoring_keywords.*', 'monitoring_groups.name as ' . self::GROUP_NAME)
            ->joinGroup()
            ->with([
                'group' => function ($query) {
                    $query->without('users');
                },
            ]);
    }

    public function showDataTable(Request $request, $id)
    {
        @set_time_limit(120);
        @ini_set('memory_limit', '768M');

        apply_team_permissions($id);

        $cacheKey = $this->tableResponseCacheKey((int) $id, $request);
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            $draw = (int) $request->input('draw', 0);
            if ($cached instanceof Collection) {
                $cached = $cached->all();
            }
            if (is_array($cached)) {
                $cached['draw'] = $draw;

                return $cached;
            }

            return $cached;
        }

        $this->setProjectID($id);
        $requestData = collect($request->all());

        $response = $this->dataPrepare($requestData)->generateDataTable($request->get('draw', 0));
        Cache::put($cacheKey, $response, now()->addSeconds(MonitoringTableResponseCache::TTL_SECONDS));

        return $response;
    }

    private function tableResponseCacheKey(int $projectId, Request $request): string
    {
        // Без draw: DataTables инкрементит его на каждый ajax — иначе кэш почти не попадает.
        $payload = $request->only([
            'start',
            'length',
            'region_id',
            'dates_range',
            'mode_range',
            'search',
            'offset',
        ]);
        // Сортировка: DT шлёт индекс колонки, early prefetch — пусто. Нормализуем к имени.
        $payload['order_norm'] = $this->normalizeOrderForCache(
            $request->input('order'),
            $request->input('columns', [])
        );
        // Только фильтры колонок (group/url/…), не весь DT-meta: иначе early prefetch
        // (columns:[]) и ajax DataTables никогда не шарят кэш → 2–3 холодных /table.
        $filters = [];
        foreach ((array) $request->input('columns', []) as $col) {
            if (!is_array($col) || empty($col['data'])) {
                continue;
            }
            $searchVal = '';
            if (isset($col['search']) && is_array($col['search'])) {
                $searchVal = trim((string) ($col['search']['value'] ?? ''));
            }
            if ($searchVal !== '') {
                $filters[(string) $col['data']] = $searchVal;
            }
        }
        ksort($filters);
        $payload['col_filters'] = $filters;
        $payload['lazy'] = $request->boolean('lazy_positions', true) ? 1 : 0;
        // Видимость URL влияет на setUrls — иначе после включения колонки отдаётся кэш без urls.
        $payload['url_col'] = $this->isUrlColumnVisibleForProject($projectId) ? 1 : 0;
        $payload['ver'] = MonitoringTableResponseCache::version($projectId);

        return 'monitoring.table.v4.' . $projectId . '.' . Auth::id() . '.' . md5(json_encode($payload));
    }

    /**
     * @param mixed $order
     * @param mixed $columns
     */
    private function normalizeOrderForCache($order, $columns): string
    {
        $column = 'query';
        $direction = 'asc';

        if (is_array($order)) {
            $order = collect($order)->collapse();
            $colIdx = $order->get('column');
            $dir = strtolower((string) $order->get('dir', 'asc'));
            if ($dir === 'desc') {
                $direction = 'desc';
            }
            if ($colIdx !== null && is_array($columns) && isset($columns[$colIdx]['data'])) {
                $column = (string) $columns[$colIdx]['data'];
                if ($column === 'group') {
                    $column = 'group';
                }
            }
        }

        return $column . ':' . $direction;
    }

    private function isUrlColumnVisible(): bool
    {
        return $this->isUrlColumnVisibleForProject((int) $this->getProjectID());
    }

    private function isUrlColumnVisibleForProject(int $projectId): bool
    {
        $map = MonitoringProjectColumnsSetting::visibilityMapForProject($projectId);

        return !empty($map['url']);
    }

    public function dataPrepare(Collection $collection)
    {
        $this->init();
        $regionID = $collection->get('region_id');
        $order = $collection->get('order');
        $start = $collection->get('start');
        $length = $collection->get('length');
        $filteredColumns = $collection->get('columns', []);
        $datesRange = $collection->get('dates_range');

        $this->setMode($collection->get('mode_range'));

        $this->offset = $collection->get('offset', []);

        if ($regionID) {
            $this->regions = $this->regions->where('id', $regionID);
        }

        $search = $collection->get('search', []);
        if (!empty($search['value'])) {
            $this->applyQuerySmartSearch($this->queries, $search['value']);
        }

        $this->filter($filteredColumns)->order($order, $filteredColumns);

        $page = ($length) ? ($start / $length) + 1 : false;

        if ($page) {
            $this->queries = $this->queries->paginate($length, ['*'], 'page', $page);
            $this->total = $this->queries->total();
        } else {
            $this->queries = $this->queries->get();
            $this->total = $this->queries->count();
        }

        if ($length > 1) {
            $this->persistPageLengthIfChanged($length);
        }

        $dates = null;
        if (strlen($datesRange) > 1) {
            $dates = explode(' - ', $datesRange, 2);
        }

        $this->lazyPositions = $this->shouldLazyLoadPositions($dates, $collection);
        $this->positionChunks = null;

        $this->loadKeywordPricesForTable();

        if ($this->lazyPositions) {
            $this->queries->each(function ($keyword) {
                $keyword->setRelation('positions', collect());
            });
            $this->positionChunks = $this->buildPositionChunks($dates);
        } else {
            $this->loadPositions($dates);
        }

        if (!$this->isMainView()) {
            $this->setOccurrence();
        }

        if ($this->regions && $this->regions->isNotEmpty()) {
            if ($this->isMainView()) {
                if ($this->isUrlColumnVisible()) {
                    if ($this->lazyPositions) {
                        $this->loadUrlsFromDb($dates);
                    } else {
                        $this->setUrls($dates);
                    }
                }
                $this->mainView();
                $this->columns->forget(['dynamics', 'base', 'phrasal', 'exact']);
            } else {
                if ($this->isUrlColumnVisible()) {
                    if ($this->lazyPositions) {
                        $this->loadUrlsFromDb($dates);
                    } else {
                        $this->setUrls($dates);
                    }
                }
                $this->getLatestPositions($dates)->updateDynamics();
            }
        }

        return $this;
    }

    private function isMainView()
    {
        return ($this->regions->count() > 1);
    }

    private function mainView()
    {
        $mainColumns = collect([]);
        $regions = $this->regions;
        $this->queries->transform(function ($item) use ($regions, $mainColumns) {

            $lastPosition = collect([]);
            foreach ($regions as $reg) {

                $model = $item->positions
                    ->where('monitoring_searchengine_id', $reg->id)
                    ->sortByDesc('created_at')
                    ->values();
                $col = 'engine_' . $reg->lr;

                if ($model->isNotEmpty()) {
                    $monitoringPosition = $model->first();

                    if ($monitoringPosition->id != $model->last()->id)
                        $monitoringPosition->diffPosition = ($model->last()->position - $monitoringPosition->position);
                    else
                        $monitoringPosition->diffPosition = null;

                    $lastPosition->put($col, $monitoringPosition);
                }

                $city = stristr($reg->location->name, ',', true);
                if ($city === false) {
                    $city = $reg->location->name;
                }

                $mainColumns->put($col, view('monitoring.partials.show.header.region', [
                    'engine' => $reg->engine,
                    'city' => $city,
                ])->render());
            }

            $item->positions_view = $lastPosition;

            return $item;
        });

        $this->setColumns($mainColumns);
    }

    protected function generateDataTable($draw = 0)
    {
        $table = [];
        foreach ($this->queries as $keyword) {
            $id = $keyword->id;
            $table[$id] = $this->generateRowDataTable($keyword);
        }

        return collect([
            'region' => $this->regions->values(),
            'columns' => $this->columns,
            'data' => collect($table)->values(),
            'draw' => $draw,
            'recordsFiltered' => $this->total,
            'recordsTotal' => $this->total,
            'lazy_positions' => $this->lazyPositions,
            'position_chunks' => $this->positionChunks,
            'keyword_ids' => $this->queries->pluck('id')->values()->all(),
        ]);
    }

    /**
     * Поэтапная заливка ячеек позиций (чанки по датам) — без полного loadPositions.
     */
    public function showDataTablePositions(Request $request, $id)
    {
        @set_time_limit(60);
        @ini_set('memory_limit', '512M');

        apply_team_permissions($id);
        $this->setProjectID($id);
        $this->init();

        $regionId = (int) $request->input('region_id', 0);
        if ($regionId > 0) {
            $this->regions = $this->regions->where('id', $regionId);
        }

        $keywordIds = array_values(array_filter(array_map('intval', (array) $request->input('keyword_ids', []))));
        if ($keywordIds === [] || $this->regions->isEmpty()) {
            return ['cells' => new \stdClass()];
        }

        $this->setMode($request->input('mode_range', 'range'));
        $datesRange = (string) $request->input('dates_range', '');
        $fullDates = null;
        if (strlen($datesRange) > 1) {
            $fullDates = explode(' - ', $datesRange, 2);
        }

        $chunkFrom = (string) $request->input('from', '');
        $chunkTo = (string) $request->input('to', '');
        if ($chunkFrom === '' || $chunkTo === '') {
            return ['cells' => new \stdClass()];
        }

        $this->queries = MonitoringKeyword::query()
            ->where('monitoring_project_id', (int) $id)
            ->whereIn('id', $keywordIds)
            ->get();

        $this->loadPositions([$chunkFrom, $chunkTo]);

        $columnCollection = $this->collectProjectDateColumns($fullDates);
        $labelToCol = [];
        foreach ($columnCollection as $colKey => $label) {
            $labelToCol[$label] = $colKey;
        }

        // Колонки дат, которые этот чанк «закрыл» — чтобы фронт сбросил «…» в «-».
        $coveredCols = [];
        try {
            $chunkStart = Carbon::parse($chunkFrom)->startOfDay();
            $chunkEnd = Carbon::parse($chunkTo)->endOfDay();
            foreach ($columnCollection as $colKey => $label) {
                $day = Carbon::createFromFormat(self::MONITORING_TABLE_DATE_FORMAT, $label)->startOfDay();
                if ($day->gte($chunkStart) && $day->lte($chunkEnd)) {
                    $coveredCols[] = $colKey;
                }
            }
        } catch (\Throwable $e) {
            $coveredCols = [];
        }

        $cells = [];
        foreach ($this->queries as $keyword) {
            $byCol = [];
            $positions = $keyword->positions ?? collect();
            $byDate = $positions->groupBy(function ($item) {
                return $item->created_at->format(self::MONITORING_TABLE_DATE_FORMAT);
            })->map(function ($items) {
                return $items->sortByDesc(function ($i) {
                    return $i->created_at->timestamp;
                })->first();
            });

            foreach ($byDate as $label => $model) {
                $colKey = $labelToCol[$label] ?? null;
                if ($colKey === null) {
                    continue;
                }
                $byCol[$colKey] = $this->positionCellPayload($model, false);
            }

            if ($byCol !== []) {
                $cells[(string) $keyword->id] = $byCol;
            }
        }

        return [
            'cells' => $cells === [] ? new \stdClass() : $cells,
            'covered_cols' => $coveredCols,
            'from' => $chunkFrom,
            'to' => $chunkTo,
        ];
    }

    /**
     * @param list<string>|null $dates
     */
    private function shouldLazyLoadPositions(?array $dates, Collection $collection): bool
    {
        if ($collection->get('lazy_positions') === false || $collection->get('lazy_positions') === '0') {
            return false;
        }
        if ($this->mode === 'datesFind') {
            return false;
        }
        if ($dates === null || count($dates) < 2) {
            return false;
        }
        try {
            $span = Carbon::parse($dates[0])->diffInDays(Carbon::parse($dates[1])) + 1;
        } catch (\Throwable $e) {
            return false;
        }

        // Короткие диапазоны — одним запросом; длинные — чанками.
        return $span > self::TABLE_POSITION_CHUNK_DAYS;
    }

    /**
     * Чанки от новых дат к старым (сначала свежие ячейки).
     *
     * @param list<string>|null $dates
     * @return list<array{from: string, to: string}>
     */
    private function buildPositionChunks(?array $dates): array
    {
        if ($dates === null || count($dates) < 2) {
            return [];
        }

        try {
            $start = Carbon::parse($dates[0])->startOfDay();
            $end = Carbon::parse($dates[1])->endOfDay();
        } catch (\Throwable $e) {
            return [];
        }

        $chunks = [];
        $cursorEnd = $end->copy();
        while ($cursorEnd->gte($start)) {
            $cursorStart = $cursorEnd->copy()->subDays(self::TABLE_POSITION_CHUNK_DAYS - 1)->startOfDay();
            if ($cursorStart->lt($start)) {
                $cursorStart = $start->copy();
            }
            $chunks[] = [
                'from' => $cursorStart->toDateString(),
                'to' => $cursorEnd->toDateString(),
            ];
            $cursorEnd = $cursorStart->copy()->subDay()->endOfDay();
        }

        return $chunks;
    }

    private function _offsetPositions(&$positions, $offset)
    {
        $positions->transform(function ($item) use ($offset) {

            if ($item->position >= $offset['from'] && $item->position <= $offset['to']) {

                if ($offset['operator'] == "+") {
                    $item->position += $offset['count'];
                } else {
                    $item->position -= $offset['count'];
                }
            }

            if ($item->position <= 0) {
                $item->position = 1;
            }

            return $item;
        });
    }

    private function generateRowDataTable($keyword)
    {
        $row = collect([]);
        $collectionPositions = $keyword->positions_view;
        if (!$collectionPositions instanceof Collection) {
            $collectionPositions = collect($collectionPositions ?: []);
        }

        if (count($this->offset)) {

            $offsets = $this->offset;

            foreach ($offsets as $offset) {
                if ($offset['from'] && $offset['to'] && $offset['count']) {
                    $this->_offsetPositions($collectionPositions, $offset);
                }
            }
        }

        if ($this->mode == 'finance' && !$this->lazyPositions) {
            $priceByKeyword = null;
            $engineID = $this->regions->pluck('id')->first();
            $priceRow = null;
            if ($engineID) {
                $prices = $keyword->relationLoaded('prices') ? $keyword->prices : $keyword->prices()->get();
                $priceRow = $prices->firstWhere('monitoring_searchengine_id', (int) $engineID)
                    ?: $prices->first();
            }
            if ($priceRow) {
                $priceByKeyword = collect([(int) $keyword->id => $priceRow]);
            }

            $positionsForMastered = $collectionPositions instanceof \Illuminate\Support\Collection
                ? $collectionPositions->values()
                : collect($collectionPositions)->values();

            // Подстраховка: на гидратированных позициях keyword_id мог не проставиться.
            $positionsForMastered->each(function ($position) use ($keyword) {
                if (!is_object($position)) {
                    return;
                }
                if (empty($position->monitoring_keyword_id)) {
                    $position->setAttribute('monitoring_keyword_id', (int) $keyword->id);
                }
                $position->query_id = (int) ($position->monitoring_keyword_id ?: $keyword->id);
                if (empty($position->engine_id) && !empty($position->monitoring_searchengine_id)) {
                    $position->engine_id = (int) $position->monitoring_searchengine_id;
                }
            });

            $mastered = new Mastered($positionsForMastered, $priceByKeyword);
        }

        $top1 = $top3 = $top5 = $top10 = $top20 = $top50 = $top100 = 0;

        if ($this->regions->isNotEmpty()) {
            $engineID = $this->regions->pluck('id')->first();
            $priceRow = $keyword->prices->firstWhere('monitoring_searchengine_id', $engineID);

            if ($priceRow) {
                $top1 = $priceRow->top1;
                $top3 = $priceRow->top3;
                $top5 = $priceRow->top5;
                $top10 = $priceRow->top10;
                $top20 = $priceRow->top20;
                $top50 = $priceRow->top50;
                $top100 = $priceRow->top100;
            }
        }

        $columns = $this->columns;

        foreach ($columns as $i => $v) {

            switch ($i) {
                case 'id':
                    $row->put('id', $keyword->id);
                    break;
                case 'checkbox':
                    $row->put('checkbox', view('monitoring.partials.show.checkbox', ['id' => $keyword->id])->render());
                    break;
                case 'btn':
                    $row->put('btn', view('monitoring.partials.show.btn', ['key' => $keyword])->render());
                    break;
                case 'query':
                    $row->put('query', view('monitoring.partials.show.query', ['key' => $keyword])->render());
                    break;
                case 'url':
                    if (isset($keyword->urls)) {
                        $urls = $keyword->urls;
                        $textClass = 'text-bold';
                        if ($keyword->page && $urls->count()) {
                            $lastUrl = $urls->first();
                            if ($lastUrl->url != $keyword->page)
                                $textClass = 'text-danger';
                            else
                                $textClass = 'text-success';
                        }

                        $row->put('url', view('monitoring.partials.show.url', [
                            'textClass' => $textClass,
                            'urls' => $urls,
                            'page' => $keyword->page,
                        ])->render());
                    } else
                        $row->put($i, '-');
                    break;
                case 'group':
                    $row->put('group', view('monitoring.partials.show.group', ['group' => $keyword->group])->render());
                    break;
                case 'target_url':
                    $row->put('target_url', view('monitoring.partials.show.target_url', ['url' => $keyword->page])->render());
                    break;
                case 'target':
                    $row->put('target', view('monitoring.partials.show.target', ['key' => $keyword])->render());
                    break;
                case 'dynamics':
                    if ($this->lazyPositions) {
                        $row->put('dynamics', '…');
                        break;
                    }
                    $dynamics = 0;
                    if ($collectionPositions && $collectionPositions->count() > 1)
                        $dynamics = ($collectionPositions->last()->position - $collectionPositions->first()->position);

                    $row->put('dynamics', view('monitoring.partials.show.dynamics', ['dynamics' => $dynamics])->render());
                    break;
                case 'base':
                    if (isset($keyword->base))
                        $row->put('base', $keyword->base);
                    else
                        $row->put('base', '-');
                    break;
                case 'phrasal':
                    if (isset($keyword->phrasal))
                        $row->put('phrasal', $keyword->phrasal);
                    else
                        $row->put('phrasal', '-');
                    break;
                case 'exact':
                    if (isset($keyword->exact))
                        $row->put('exact', $keyword->exact);
                    else
                        $row->put('exact', '-');
                    break;
                case 'price_top_1':
                    $row->put('price_top_1', $top1);
                    break;
                case 'price_top_3':
                    $row->put('price_top_3', $top3);
                    break;
                case 'price_top_5':
                    $row->put('price_top_5', $top5);
                    break;
                case 'price_top_10':
                    $row->put('price_top_10', $top10);
                    break;
                case 'price_top_20':
                    $row->put('price_top_20', $top20);
                    break;
                case 'price_top_50':
                    $row->put('price_top_50', $top50);
                    break;
                case 'price_top_100':
                    $row->put('price_top_100', $top100);
                    break;
                case 'days_top_1':
                    $top = $mastered->top1();
                    $row->put('days_top_1', $top['count']);
                    break;
                case 'days_top_3':
                    $top = $mastered->top3();
                    $row->put('days_top_3', $top['count']);
                    break;
                case 'days_top_5':
                    $top = $mastered->top5();
                    $row->put('days_top_5', $top['count']);
                    break;
                case 'days_top_10':
                    $top = $mastered->top10();
                    $row->put('days_top_10', $top['count']);
                    break;
                case 'days_top_20':
                    $top = $mastered->top20();
                    $row->put('days_top_20', $top['count']);
                    break;
                case 'days_top_50':
                    $top = $mastered->top50();
                    $row->put('days_top_50', $top['count']);
                    break;
                case 'days_top_100':
                    $top = $mastered->top100();
                    $row->put('days_top_100', $top['count']);
                    break;
                case 'mastered':
                    $row->put('mastered', $mastered->total());
                    break;
                default:
                    $mode = $this->mode;
                    $emptyCell = $this->lazyPositions ? '…' : '-';
                    if ($mode === "dates" || $mode === "main") {
                        if (isset($collectionPositions[$i])) {
                            $row->put($i, $this->positionCellPayload($collectionPositions[$i], true));
                        } else {
                            $row->put($i, $emptyCell);
                        }
                    } else {
                        if (isset($collectionPositions[$i])) {
                            $row->put($i, $this->positionCellPayload($collectionPositions[$i], false));
                        } else {
                            $row->put($i, $emptyCell);
                        }
                    }
            }
        }

        return $row;
    }

    /**
     * Компактный payload ячейки позиции для /table (рендер HTML на клиенте).
     *
     * @return array{p: int, d?: int, t?: string}
     */
    private function positionCellPayload($model, bool $withDate = false): array
    {
        $payload = ['p' => (int) $model->position];
        if (!empty($model->diffPosition)) {
            $payload['d'] = (int) $model->diffPosition;
        }
        if ($withDate) {
            $payload['t'] = (string) $model->date;
        }

        return $payload;
    }

    /** @deprecated оставлен для экспортов/совместимости; /table использует positionCellPayload */
    private function renderPositionCell($model): string
    {
        $position = (int) $model->position;
        $html = '<span data-position="' . $position . '">' . $position;
        if (!empty($model->diffPosition)) {
            $diff = (int) $model->diffPosition;
            $html .= '<sup class="text-sm">' . ($diff > 0 ? '+' : '') . $diff . '</sup>';
        }

        return $html . '</span>';
    }

    private function renderPositionWithDateCell($model): string
    {
        $position = (int) $model->position;
        $html = '<span data-position="' . $position . '" style="display: block;">' . $position;
        if (!empty($model->diffPosition)) {
            $diff = (int) $model->diffPosition;
            $html .= '<sup class="text-sm">' . ($diff > 0 ? '+' : '') . $diff . '</sup>';
        }
        $html .= '</span><div class="badge badge-info">' . e($model->date) . '</div>';

        return $html;
    }

    private function getLatestPositions(?array $dates = null)
    {
        foreach ($this->queries as &$keyword) {

            $grouped = $keyword->positions->groupBy(function ($item) {
                return $item->created_at->format(self::MONITORING_TABLE_DATE_FORMAT);
            })->sortByDesc(function ($i, $k) {
                return Carbon::createFromFormat(self::MONITORING_TABLE_DATE_FORMAT, $k)->timestamp;
            });

            $grouped->transform(function ($item) {
                return $item->sortByDesc(function ($i) {
                    return $i->created_at->timestamp;
                })->values()->first();
            });

            $keyword->positions_data_table = $grouped;
        }

        // Колонки дат — по всему проекту/региону/диапазону, не по текущей странице.
        // Иначе при пагинации набор col_* меняется, DataTables ждёт col_N из init и падает с tn/4.
        $columnCollection = $this->collectProjectDateColumns($dates);

        switch ($this->mode) {
            case "dates":
                $this->setColumns(collect([
                    'col_0' => __('First of find'),
                    'col_1' => __('Last of find'),
                ]));

                $this->queries->transform(function ($item) {
                    $item->positions_view = collect([
                        'col_0' => $item->positions_data_table->first(),
                        'col_1' => $item->positions_data_table->last(),
                    ]);

                    return $item;
                });
                break;
            case "randWeek":
            case "randMonth":
            $this->queries->transform(function ($item) {
                    $positionsRange = collect([]);
                    foreach ($item->positions_data_table as $p) {
                        if ($this->mode === "randWeek")
                            $positionsRange->put($p->created_at->week(), $p);
                        else
                            $positionsRange->put($p->created_at->month, $p);
                    }

                    $item->positions_view = $positionsRange;

                    return $item;
                });

                $getDateForColumns = collect([]);
                foreach ($this->queries as $keyword)
                    $getDateForColumns = $getDateForColumns->merge($keyword->positions_view->pluck('created_at'));

                $getDateForColumns = $getDateForColumns->sortByDesc(null)->unique(function ($item) {
                    return $item->format('d.m.Y');
                });

                $dateOfColumns = collect([]);
                foreach ($getDateForColumns as $i => $m)
                    $dateOfColumns->put('col_' . $i, $m->format(self::MONITORING_TABLE_DATE_FORMAT));

                $this->setColumns($dateOfColumns);

                foreach ($this->queries as $keyword) {
                    $lastPosition = collect([]);
                    foreach ($dateOfColumns as $col => $name) {
                        if ($keyword->positions_data_table->has($name))
                            $lastPosition->put($col, $keyword->positions_data_table[$name]);
                    }
                    $keyword->positions_view = $lastPosition;
                }
                break;
            case "finance":
                $this->financeExtension($columnCollection);
                break;
            default;
                $this->setColumns($columnCollection);
                $this->queries->transform(function ($item) use ($columnCollection) {

                    $positions = collect([]);
                    foreach ($columnCollection as $col => $name)
                        if ($item->positions_data_table->has($name))
                            $positions->put($col, $item->positions_data_table[$name]);

                    $this->diffPositionExtension($positions);

                    $item->positions_view = $positions;

                    return $item;
                });
        }

        return $this;
    }

    private function financeExtension($columns)
    {
        $this->setColumns($columns);

        $this->queries->transform(function ($item) use ($columns) {

            $positions = collect([]);
            foreach ($columns as $col => $name)
                if ($item->positions_data_table->has($name))
                    $positions->put($col, $item->positions_data_table[$name]);

            $this->diffPositionExtension($positions);

            $item->positions_view = $positions;

            return $item;
        });

        $fields = ['top_1', 'top_3', 'top_5', 'top_10', 'top_20', 'top_50', 'top_100'];

        $price = collect([]);
        $days = collect([]);

        foreach($fields as $field){
            $price->put('price_' . $field, __('Price') . ' ' . str_replace("_", "-", $field));
            $days->put('days_' . $field, __('Days') . ' ' . str_replace("_", "-", $field));
        }

        $this->setColumns($price);
        $this->setColumns($days);

        $this->setColumns(collect(['mastered' => __('Mastered')]));
    }

    private function diffPositionExtension(&$positions)
    {
        if ($positions->isEmpty())
            return false;

        $pre = 0;

        foreach ($positions->reverse() as $p) {
            if ($pre > 0)
                $p->diffPosition = ($pre - $p->position);
            else
                $p->diffPosition = null;

            $pre = $p->position;
        }
    }

    private function getColumns()
    {
        $columns = collect([
            'checkbox' => '',
            'btn' => '',
            'query' => view('monitoring.partials.show.header.query')->render(),
            'url' => __('URL'),
            'group' => __('Group'),
            'target_url' => __('Target URL'),
            'target' => __('Target'),
            'dynamics' => __('Dynamics'),
            'base' => view('monitoring.partials.show.header.yw', ['ext' => ''])->render(),
            'phrasal' => view('monitoring.partials.show.header.yw', ['ext' => '"[]"'])->render(),
            'exact' => view('monitoring.partials.show.header.yw', ['ext' => '"[!]"'])->render(),
        ]);

        return $columns;
    }

    /**
     * Стабильный набор дат для заголовков col_* (весь проект + текущий регион + диапазон).
     *
     * @return \Illuminate\Support\Collection<string, string> col_N => d.m.y
     */
    private function collectProjectDateColumns(?array $dates): Collection
    {
        [$start, $end] = $this->positionsDateBounds($dates);
        $regionIds = $this->regions->pluck('id')->filter()->values()->all();
        $projectId = (int) $this->getProjectID();

        $cacheKey = 'mon.date_cols.' . $projectId . '.' . md5(json_encode([$start, $end, $regionIds]));

        /** @var list<string> $rawDates */
        $rawDates = Cache::remember($cacheKey, 90, static function () use ($start, $end, $regionIds, $projectId) {
            // Не JOIN'ить keywords на 13M positions — только engine_id (короткий IN, префикс индекса).
            $engineIds = $regionIds;
            if ($engineIds === []) {
                $engineIds = DB::table('monitoring_searchengines')
                    ->where('monitoring_project_id', $projectId)
                    ->pluck('id')
                    ->map(static function ($id) {
                        return (int) $id;
                    })
                    ->all();
            }

            if ($engineIds === []) {
                return [];
            }

            // Лёгкая таблица дней съёма (с авто-прогревом из positions при первом miss).
            return MonitoringPositionDates::datesForEngines($engineIds, $start, $end);
        });

        $columnCollection = collect([]);
        foreach ($rawDates as $colIdx => $rawDate) {
            $columnCollection->put(
                'col_' . $colIdx,
                Carbon::parse($rawDate)->format(self::MONITORING_TABLE_DATE_FORMAT)
            );
        }

        return $columnCollection;
    }

    private function setOccurrence()
    {
        $keywordIds = $this->queries->pluck('id');
        $regionIds = $this->regions->pluck('id');

        $occurrencesByKey = collect();
        if ($keywordIds->isNotEmpty() && $regionIds->isNotEmpty()) {
            $occurrencesByKey = MonitoringOccurrence::query()
                ->whereIn('monitoring_keyword_id', $keywordIds)
                ->whereIn('monitoring_searchengine_id', $regionIds)
                ->get()
                ->keyBy(function (MonitoringOccurrence $occurrence) {
                    return $occurrence->monitoring_keyword_id . ':' . $occurrence->monitoring_searchengine_id;
                });
        }

        $regions = $this->regions;
        $this->queries->transform(function ($item) use ($regions, $occurrencesByKey) {
            foreach ($regions as $region) {
                $occurrence = $occurrencesByKey->get($item->id . ':' . $region['id']);
                if ($occurrence) {
                    $item->base = (int) $occurrence->base;
                    $item->phrasal = (int) $occurrence->phrasal;
                    $item->exact = (int) $occurrence->exact;
                    $item->occurrenceCreateAt = $occurrence->updated_at;
                }
            }

            return $item;
        });
    }

    private function persistPageLengthIfChanged(int $length): void
    {
        $projectId = $this->getProjectID();
        $current = MonitoringProjectSettings::query()
            ->where('monitoring_project_id', $projectId)
            ->where('name', 'length')
            ->value('value');

        if ((string) $current === (string) $length) {
            return;
        }

        $this->setSetting($projectId, 'length', (string) $length);
    }

    private function loadKeywordPricesForTable(): void
    {
        $regionIds = $this->regions->pluck('id')->filter();

        $this->queries->load(['prices' => function ($query) use ($regionIds) {
            $query->select([
                'id',
                'monitoring_keyword_id',
                'monitoring_searchengine_id',
                'top1',
                'top3',
                'top5',
                'top10',
                'top20',
                'top50',
                'top100',
            ]);

            if ($regionIds->isNotEmpty()) {
                $query->whereIn('monitoring_searchengine_id', $regionIds);
            }
        }]);
    }

    private function positionsDateBounds(?array $dates): array
    {
        $start = Carbon::now()->subMonth()->startOfDay();
        $end = Carbon::now()->endOfDay();

        if ($dates && count($dates) >= 2) {
            $start = Carbon::parse($dates[0])->startOfDay();
            $end = Carbon::parse($dates[1])->endOfDay();
        }

        return [$start, $end];
    }

    /**
     * Для /table на странице ключей (десятки–сотни id) нужен индекс
     * (engine, keyword, created_at). FORCE INDEX (engine, created_at) на полугоде
     * сканирует сотни тысяч строк региона и тормозит на десятки секунд.
     */
    private function positionsForceEngineCreatedIndex(array $regionIds): bool
    {
        if ($regionIds === []) {
            return false;
        }

        static $hasIndex = null;
        if ($hasIndex === null) {
            try {
                $hasIndex = DB::table('information_schema.statistics')
                    ->where('table_schema', DB::getDatabaseName())
                    ->where('table_name', 'monitoring_positions')
                    ->where('index_name', 'mon_pos_engine_created_idx')
                    ->exists();
            } catch (\Throwable $e) {
                $hasIndex = false;
            }
        }

        return (bool) $hasIndex;
    }

    private function positionsHasEngineKwCreatedIndex(): bool
    {
        static $hasIndex = null;
        if ($hasIndex === null) {
            try {
                $hasIndex = DB::table('information_schema.statistics')
                    ->where('table_schema', DB::getDatabaseName())
                    ->where('table_name', 'monitoring_positions')
                    ->where('index_name', 'mon_pos_engine_kw_created_idx')
                    ->exists();
            } catch (\Throwable $e) {
                $hasIndex = false;
            }
        }

        return (bool) $hasIndex;
    }

    /**
     * FROM-клауза для loadPositions: при списке keyword id — kw-индекс.
     */
    private function positionsMpFromClause(array $regionIds, int $keywordCount): string
    {
        if ($keywordCount > 0 && $keywordCount <= 2000 && $this->positionsHasEngineKwCreatedIndex()) {
            return 'monitoring_positions as mp FORCE INDEX (mon_pos_engine_kw_created_idx)';
        }

        if ($this->positionsForceEngineCreatedIndex($regionIds)) {
            return 'monitoring_positions as mp FORCE INDEX (mon_pos_engine_created_idx)';
        }

        return 'monitoring_positions as mp';
    }

    private function setUrls(?array $dates = null)
    {
        $region = $this->regions->first();
        if (!$region) {
            return;
        }

        $regionId = (int) $region['id'];

        // Без отдельного GROUP BY по monitoring_positions (на крупных регионах 1–3+ с):
        // URL уже есть в loadPositions (по дню). Собираем unique url на странице в PHP.
        $this->queries->transform(function ($item) use ($regionId) {
            $byUrl = [];
            $positions = $item->positions ?? collect();
            foreach ($positions as $pos) {
                if ((int) $pos->monitoring_searchengine_id !== $regionId) {
                    continue;
                }
                $url = isset($pos->url) ? trim((string) $pos->url) : '';
                if ($url === '') {
                    continue;
                }
                $ts = $pos->created_at ? $pos->created_at->getTimestamp() : 0;
                if (!isset($byUrl[$url]) || $ts > $byUrl[$url]->_ts) {
                    $row = (object) [
                        'monitoring_keyword_id' => (int) $item->id,
                        'url' => $url,
                        'created_at' => $pos->created_at,
                        '_ts' => $ts,
                    ];
                    $byUrl[$url] = $row;
                }
            }

            $urls = collect(array_values($byUrl))
                ->sortByDesc('_ts')
                ->values()
                ->each(static function ($row) {
                    unset($row->_ts);
                });

            $item->urls = $urls;

            return $item;
        });
    }

    /**
     * URL в выдаче при lazy-позициях: лёгкий GROUP BY (keyword, url), без дневных рядов.
     */
    private function loadUrlsFromDb(?array $dates = null): void
    {
        $region = $this->regions->first();
        if (!$region) {
            return;
        }

        $regionId = (int) $region['id'];
        $keywordIds = $this->queries->pluck('id')->values()->all();
        if ($keywordIds === []) {
            return;
        }

        [$start, $end] = $this->positionsDateBounds($dates);
        $fromMp = $this->positionsMpFromClause([$regionId], count($keywordIds));

        $rows = DB::table(DB::raw($fromMp))
            ->whereIn('mp.monitoring_keyword_id', $keywordIds)
            ->where('mp.monitoring_searchengine_id', $regionId)
            ->where('mp.created_at', '>=', $start)
            ->where('mp.created_at', '<=', $end)
            ->whereNotNull('mp.url')
            ->where('mp.url', '!=', '')
            ->selectRaw('mp.monitoring_keyword_id, mp.url, MAX(mp.created_at) as last_at')
            ->groupBy('mp.monitoring_keyword_id', 'mp.url')
            ->get()
            ->groupBy('monitoring_keyword_id');

        $this->queries->each(function ($keyword) use ($rows) {
            $items = ($rows->get($keyword->id) ?? collect())
                ->map(function ($row) {
                    return (object) [
                        'monitoring_keyword_id' => (int) $row->monitoring_keyword_id,
                        'url' => (string) $row->url,
                        'created_at' => Carbon::parse($row->last_at),
                    ];
                })
                ->sortByDesc(function ($row) {
                    return $row->created_at->getTimestamp();
                })
                ->values();

            $keyword->urls = $items;
        });
    }

    private function loadPositions($dates)
    {
        if ($this->mode === 'datesFind') {
            $this->loadPositionsEager($dates);

            return;
        }

        $regionIds = $this->regions->pluck('id')->filter()->values()->all();
        $keywordIds = $this->queries->pluck('id')->values()->all();

        if ($keywordIds === []) {
            return;
        }

        [$start, $end] = $this->positionsDateBounds($dates);

        $fromMp = $this->positionsMpFromClause($regionIds, count($keywordIds));

        $latestIdsQuery = DB::table(DB::raw($fromMp))
            ->selectRaw('MAX(mp.id) as id')
            ->whereIn('mp.monitoring_keyword_id', $keywordIds)
            ->where('mp.created_at', '>=', $start)
            ->where('mp.created_at', '<=', $end);

        if ($regionIds !== []) {
            $latestIdsQuery->whereIn('mp.monitoring_searchengine_id', $regionIds);
        }

        $latestIdsQuery->groupBy(
            'mp.monitoring_keyword_id',
            'mp.monitoring_searchengine_id',
            DB::raw('DATE(mp.created_at)')
        );

        $rows = DB::table('monitoring_positions as p')
            ->select([
                'p.id',
                'p.monitoring_keyword_id',
                'p.monitoring_searchengine_id',
                'p.position',
                'p.url',
                'p.target',
                'p.created_at',
            ])
            ->joinSub($latestIdsQuery, 'latest', static function ($join) {
                $join->on('p.id', '=', 'latest.id');
            })
            ->orderBy('p.created_at')
            ->get()
            ->groupBy('monitoring_keyword_id');

        $this->queries->each(function ($keyword) use ($rows) {
            $items = ($rows->get($keyword->id) ?? collect())->map(function ($row) {
                $model = new MonitoringPosition();
                $model->exists = true;
                $model->setRawAttributes([
                    'id' => (int) $row->id,
                    'monitoring_keyword_id' => (int) $row->monitoring_keyword_id,
                    'monitoring_searchengine_id' => (int) $row->monitoring_searchengine_id,
                    'position' => $row->position,
                    'url' => $row->url,
                    'target' => $row->target,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->created_at,
                ], true);
                $model->syncOriginal();

                return $model;
            })->values();

            $keyword->setRelation('positions', $items);
        });
    }

    private function loadPositionsEager($dates)
    {
        $regionIds = $this->regions->pluck('id')->filter();

        $this->queries->load(['positions' => function ($query) use ($regionIds, $dates) {
            $query->select([
                'id',
                'monitoring_keyword_id',
                'monitoring_searchengine_id',
                'position',
                'url',
                'target',
                'created_at',
            ]);

            if ($regionIds->isNotEmpty()) {
                $query->whereIn('monitoring_searchengine_id', $regionIds);
            }

            $query->dateFind($dates);
        }]);
    }

    public function setSetting(int $idProject, string $name, string $value)
    {
        MonitoringProjectSettings::updateOrCreate(
            ['monitoring_project_id' => $idProject, 'name' => $name],
            ['value' => $value]
        );
    }

    private function order($order = null, $columns = [])
    {
        $column = 'query';
        $direction = 'asc';

        if (is_array($order)) {
            $order = collect($order)->collapse();

            if ($column = $columns[$order['column']]['data']) {
                if ($column == 'group') {
                    $column = self::GROUP_NAME;
                }

                $direction = $order['dir'];
            }
        }

        $this->queries->orderBy($column, $direction)->orderBy('query', 'asc');

        return $this;
    }

    private function filter(array $columns)
    {
        $project = $this->project;
        $model = $this->queries;
        $region = $this->regions->first();

        foreach ($columns as $column) {

            switch ($column['data']) {
                case 'query':
                    if (!empty($column['search']['value'])) {
                        $this->applyQuerySmartSearch($model, $column['search']['value']);
                    }
                    break;
                case 'group':
                    if ($column['search']['value'])
                        $model->whereIn('monitoring_group_id', explode(',', $column['search']['value']));
                    break;
                case 'url':
                    if ($column['search']['value']) {
                        $ids = $this->getKeywordIdsWithNotValidateUrl($project->id, $region->id);
                        if ($ids->isEmpty()) {
                            $model->whereRaw('0 = 1');
                        } else {
                            $model->whereIn('monitoring_keywords.id', $ids);
                        }
                    }
                    break;
                case 'dynamics':
                    if ($column['search']['value']) {
                        if ($column['search']['value'] == 'positive')
                            $model->where('dynamic', '>', 0);
                        elseif ($column['search']['value'] == 'negative')
                            $model->where('dynamic', '<', 0);
                    }
                    break;
            }
        }

        return $this;
    }

    /**
     * Умный поиск запроса: клиент шлёт regex-альтернативы раскладки (pote|зщеу).
     */
    private function applyQuerySmartSearch($builder, string $value): void
    {
        $terms = $this->parseSmartSearchTerms($value);
        if ($terms === []) {
            return;
        }

        $builder->where(function ($query) use ($terms) {
            foreach ($terms as $term) {
                $query->orWhere('monitoring_keywords.query', 'like', '%' . $this->escapeLike($term) . '%');
            }
        });
    }

    private function parseSmartSearchTerms(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $unescaped = preg_replace('/\\\\(.)/u', '$1', $value);
        $parts = preg_split('/\|/', $unescaped);
        $terms = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '') {
                $terms[] = $part;
            }
        }

        return array_values(array_unique($terms));
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }

    private function updateDynamics()
    {
        foreach ($this->queries as $keyword) {
            $dynamics = 0;
            $model = $keyword->positions_view;
            if ($model && $model->count() > 1) {
                $dynamics = $model->last()->position - $model->first()->position;
            }
            // Только для отображения в строке; фильтр dynamics — до paginate, по колонке в БД.
            $keyword->setAttribute('dynamic', $dynamics);
        }

        return $this;
    }

    private function getKeywordIdsWithNotValidateUrl(int $projectId, int $regionId)
    {
        $lastDateUrlPosition = DB::table('monitoring_positions')
            ->select('monitoring_keyword_id', 'monitoring_searchengine_id', DB::raw('MAX(created_at) created_max'))
            ->whereNotNull('url')
            ->where('monitoring_searchengine_id', $regionId)
            ->groupBy('monitoring_keyword_id');

        $lastUrlPosition = DB::table('monitoring_positions')
            ->joinSub($lastDateUrlPosition, 'latest_url', function ($join) {
                $join->on('monitoring_positions.monitoring_keyword_id', '=', 'latest_url.monitoring_keyword_id')
                    ->on('monitoring_positions.created_at', '=', 'latest_url.created_max');
            })
            ->join('monitoring_keywords', function ($join) {
                $join->on('monitoring_positions.monitoring_keyword_id', '=', 'monitoring_keywords.id')
                    ->on('monitoring_positions.url', '!=', 'monitoring_keywords.page');
            })
            ->where('monitoring_keywords.monitoring_project_id', $projectId)
            ->where('monitoring_positions.monitoring_searchengine_id', $regionId)
            ->distinct()
            ->pluck('monitoring_positions.monitoring_keyword_id');

        return $lastUrlPosition;
    }

    public function showControlsPanel($id)
    {
        apply_team_permissions($id);

        $project = MonitoringProject::query()->withCount('searchengines')->findOrFail($id);
        $columnSettings = MonitoringProjectColumnsSetting::visibilityMapForProject((int) $id);
        $isMultiRegionView = !request('region') && $project->searchengines_count > 1;

        return view('monitoring.keywords.controls', compact('columnSettings', 'isMultiRegionView'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create($id)
    {
        $project = MonitoringProject::findOrFail($id);

        return view('monitoring.keywords.create', compact('project'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    public function store(Request $request)
    {
        $id = $request->input('monitoring_project_id');
        $queries = preg_split("/\r\n|\n|\r/", $request->input('query'));

        $project = MonitoringProject::findOrFail($id);

        foreach ($queries as $query) {

            $project->keywords()->create([
                'monitoring_group_id' => $request->input('monitoring_group_id'),
                'target' => $request->input('target'),
                'page' => $request->input('page'),
                'query' => $query,
            ]);
        }

        return $project;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        /** @var User $user */
        $user = $this->user;

        $keyword = MonitoringKeyword::findOrFail($id);

        if($keyword->project->users->find($user->id)){

            apply_team_permissions($keyword->project->id);

            return view('monitoring.keywords.edit', compact('keyword'));
        }
        else
            return abort('404');
    }

    public function editPlural($id)
    {
        apply_team_permissions($id);

        $project = MonitoringProject::findOrFail($id);

        return view('monitoring.keywords.edit_plural', compact('project'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        /** @var User $user */
        $user = $this->user;

        $keyword = MonitoringKeyword::findOrFail($id);
        if($keyword->project->users->find($user->id)){

            $keyword->update($request->all());
            return $keyword;
        }
        else
            return abort('404');
    }

    public function updatePlural(Request $request)
    {
        $data = [];

        foreach (['monitoring_group_id', 'target', 'page'] as $key) {
            if ($request->filled($key)) {
                $data[$key] = $request->input($key);
            }
        }

        if ($data) {
            return MonitoringKeyword::whereIn('id', $request->input('id', []))->update($data);
        }

        return false;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        /** @var User $user */
        $user = $this->user;

        $keyword = MonitoringKeyword::findOrFail($id);
        if($keyword->project->users->find($user['id']))
            $keyword->delete();

        return $keyword;
    }

    public function setTestPositions(Request $request, $id_project)
    {
        $project = MonitoringProject::findOrFail($id_project);
        $search = $request->input('search');
        $dates = explode(' - ', $request->input('date'));

        $startDate = Carbon::createFromFormat('Y-m-d', $dates[0]);
        $endDate = Carbon::createFromFormat('Y-m-d', $dates[1]);

        $dateRange = CarbonPeriod::create($startDate, $endDate)->toArray();

        $project->keywords->each(function($key) use ($search, $dateRange){

            foreach($dateRange as $date){

                factory(MonitoringPosition::class)->create([
                    'monitoring_keyword_id' => $key->id,
                    'monitoring_searchengine_id' => $search,
                    'created_at' => $date,
                    'updated_at' => $date,
                ]);
            }
        });

        return redirect()->back();
    }

    public function showEmptyModal()
    {
        return view('monitoring.keywords.empty');
    }
}
