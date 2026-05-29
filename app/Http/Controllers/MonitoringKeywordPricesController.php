<?php

namespace App\Http\Controllers;

use App\Classes\Monitoring\MonitoringLocationLabel;
use App\MonitoringKeywordPrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MonitoringKeywordPricesController extends Controller
{
    protected $user;
    protected $project;
    protected $request;
    protected $regions;

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();

            return $next($request);
        });
    }

    private function initField(Request $request): void
    {
        apply_team_permissions($request['id']);

        $user = $this->user;

        $this->project = $user->monitoringProjects()->find($request['id']);
        if (!$this->project) {
            abort(404);
        }

        $this->regions = $this->project->searchengines()
            ->with(['location:id,lr,name'])
            ->get();
        $this->request = $request;
    }

    public function index(Request $request)
    {
        $this->initField($request);

        if ($request->ajax()) {
            return $this->getDataTable();
        }

        $project = $this->project->load(['searchengines.location']);
        $regions = $this->formatRegions($this->regions);

        return view('monitoring.price.index', [
            'project' => $project,
            'regions' => $regions,
            'canEditPrice' => $this->user->can('update_price_monitoring'),
            'canEditBudget' => $this->user->can('update_budget_monitoring'),
        ]);
    }

    public function action(Request $request)
    {
        $this->initField($request);

        if (!$this->user->can('update_price_monitoring')) {
            abort(403);
        }

        if ($request->input('action') === 'edit') {
            return $this->updateOrCreate();
        }

        return collect(['data' => []]);
    }

    public function updateOrCreate()
    {
        $request = $this->request;

        $region = $request->input('region', null);
        $data = $request->input('data', []);

        foreach ($data as $id => $val) {
            $collect = collect($val)->filter(function ($val) {
                return is_numeric($val);
            });

            MonitoringKeywordPrice::updateOrCreate(
                ['monitoring_keyword_id' => $id, 'monitoring_searchengine_id' => $region],
                $collect->toArray()
            );
        }

        return collect([
            'data' => [],
        ]);
    }

    private function format($keywords)
    {
        $collection = collect([]);

        foreach ($keywords as $keyword) {
            $data = [];

            $data['DT_RowId'] = $keyword->id;
            $data['query'] = $keyword->query;

            $data['top1'] = '';
            $data['top3'] = '';
            $data['top5'] = '';
            $data['top10'] = '';
            $data['top20'] = '';
            $data['top50'] = '';
            $data['top100'] = '';

            if ($keyword->price) {
                foreach ($keyword->price->toArray() as $key => $price) {
                    if (isset($data[$key])) {
                        $data[$key] = $price;
                    }
                }
            }

            $collection->push($data);
        }

        return $collection;
    }

    public function getDataTable()
    {
        $request = $this->request;
        $region = $request->input('region', $this->regions->first()['id']);

        $model = $this->project->keywords()->with(['price' => function ($query) use ($region) {
            $query->where('monitoring_searchengine_id', $region);
        }]);

        if ($search = $request->input('search')['value']) {
            $model->where('query', 'like', '%' . $search . '%');
        }

        $page = ($request->input('start') / $request->input('length')) + 1;
        $keywords = $model->paginate($request->input('length', 1), ['*'], 'page', $page);

        $data = $this->format($keywords);

        $collection = collect([]);
        $collection->put('data', $data);
        $collection->put('regions', $this->formatRegions($this->regions));
        $collection->put('draw', $this->request->input('draw'));

        $records = $keywords->total();
        $collection->put('recordsFiltered', $records);
        $collection->put('recordsTotal', $records);

        return $collection;
    }

    public function storeBudget(Request $request)
    {
        apply_team_permissions($request['id']);

        if (!$this->user->can('update_budget_monitoring')) {
            abort(403);
        }

        $project = $this->user->monitoringProjects()->findOrFail($request['id']);

        if ($project->update(['budget' => $request->input('budget')])) {
            return response()->json(['ok' => true]);
        }

        return response()->json(['ok' => false], 422);
    }

    private function formatRegions($regions)
    {
        return collect($regions)->map(function ($se) {
            return [
                'id' => $se->id,
                'name' => MonitoringLocationLabel::filterOption($se),
            ];
        })->values();
    }
}
