<?php

namespace App\Http\Controllers;

use App\Exports\Monitoring\AttributeExport;
use App\Exports\Monitoring\ColumnEditor;
use App\Exports\Monitoring\Format\IFormat;
use App\Exports\Monitoring\PositionsExportFactory;
use App\Helpers\CollectionHelper;
use App\MonitoringProject;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class MonitoringExportsController extends MonitoringKeywordsController
{
    const QUERY_INDEX = 1;
    const GROUP_INDEX = 2;
    const CREATED_AT_INDEX = 3;

    private $removeColumns = [
        'checkbox',
        'btn',
        'url',
        'group',
        'target_url',
        'dynamics',
        'base',
        'phrasal',
        'exact',
        'price_top_1',
        'price_top_3',
        'price_top_5',
        'price_top_10',
        'price_top_20',
        'price_top_50',
        'price_top_100',
        'days_top_1',
        'days_top_3',
        'days_top_5',
        'days_top_10',
        'days_top_20',
        'days_top_50',
        'days_top_100',
    ];

    /**
     * @var IFormat
     */
    private IFormat $format;

    public function setFormat(IFormat $format): void
    {
        $this->format = $format;
    }

    public function downloadFile($data, $fileName, $extension = 'pdf')
    {
        $export = new PositionsExportFactory();
        $this->setFormat($export->createExport($extension));

        return $this->format->download($data, $fileName);
    }

    public function download(Request $request, $id)
    {
        @set_time_limit(300);
        @ini_set('memory_limit', '768M');

        $date = implode(' - ', [
            Carbon::parse($request['startDate'])->locale('ru')->toDateString(),
            Carbon::parse($request['endDate'])->locale('ru')->toDateString()
        ]);

        // Пустой region = свод по всем ПС/регионам проекта (как таблица «Все поисковые системы»).
        $regionId = $request->input('region');
        if ($regionId === '' || $regionId === null || $regionId === 'all') {
            $regionId = null;
        }

        $mode = (string) $request->input('mode', 'range');
        if ($regionId === null && $mode === 'finance') {
            // Финансовый отчёт только по одному региону (цены привязаны к ПС).
            $mode = 'range';
        }
        $request->merge(['mode' => $mode]);

        $wantUrlCount = $request->has('urlCol');
        $wantUrlLinks = $request->has('url_linksCol');

        $params = collect([
            'length' => 0,
            // Экспорт — полный снимок: lazy-чанки только для UI, иначе finance/mastered падает 500.
            'lazy_positions' => false,
            'mode_range' => $mode,
            'region_id' => $regionId,
            'dates_range' => $date,
            'columns' => [
                self::GROUP_INDEX => [
                    'data' => 'group',
                    'search' => [
                        'value' => ($request['group']) ? implode(',', $request['group']) : null
                    ]
                ],
                self::QUERY_INDEX => [
                    'data' => 'query',
                    'search' => [
                        'value' => null
                    ]
                ],
                self::CREATED_AT_INDEX => [
                    'data' => 'created_at',
                    'search' => [
                        'value' => null
                    ]
                ],
            ],
            'order' => [
                [
                    'column' => $request['order']['column'],
                    'dir' => $request['order']['dir'],
                ]
            ],
            'offset' => $request['offset'] ?? [],
        ]);

        foreach ($this->removeColumns as $col) {
            if ($request->has($col . 'Col')) {
                unset($this->removeColumns[array_search($col, $this->removeColumns)]);
            }
        }

        $this->setProjectID($id)
            ->dataPrepare($params);

        $dates = strlen($date) > 1 ? explode(' - ', $date, 2) : null;
        if ($wantUrlCount || $wantUrlLinks) {
            $this->ensureUrlsForExport($dates);
        }

        $this->columns->forget($this->removeColumns);

        $response = $this->generateDataTable();
        $this->applyExportUrlColumns($response, $wantUrlCount, $wantUrlLinks);

        $editor = (new ColumnEditor($response))->setColumns($request);

        $response['columns'] = $editor->getColumns();
        $response['data'] = $editor->getData();

        $attribute = new AttributeExport($response, $request);
        $attribute->setBudget($this->project->budget);
        $attribute->execute();

        $file = $this->project['url'] . ' ' . $params['dates_range'];
        return $this->downloadFile($response, $file, $request['format']);
    }

    /**
     * Подгрузить URL выдачи для колонок экспорта (даже если в UI колонка скрыта).
     *
     * @param list<string>|null $dates
     */
    protected function ensureUrlsForExport(?array $dates): void
    {
        if ($this->queries->isEmpty()) {
            return;
        }

        $sample = $this->queries->first();
        $positions = $sample->positions ?? null;
        if ($positions instanceof Collection && $positions->isNotEmpty()) {
            $this->assignUrlsFromLoadedPositions();

            return;
        }

        $this->loadUrlsFromDb($dates);
    }

    /**
     * «URL в выдаче (раз)» = число уникальных URL; «URL в выдаче ссылки» = список через \\n.
     *
     * @param \Illuminate\Support\Collection|array $response
     */
    protected function applyExportUrlColumns(&$response, bool $wantCount, bool $wantLinks): void
    {
        if (!$wantCount && !$wantLinks) {
            return;
        }

        $columns = $response['columns'] instanceof Collection
            ? $response['columns']
            : collect($response['columns']);
        $data = $response['data'] instanceof Collection
            ? $response['data']
            : collect($response['data']);

        if ($wantCount && $columns->has('url')) {
            $columns->put('url', __('Monitoring export col url count'));
        }

        if ($wantLinks) {
            $label = __('Monitoring export col url links');
            if ($columns->has('url')) {
                $columns = CollectionHelper::appendAfter($columns, 'url_links', $label, 'url');
            } elseif ($columns->has('query')) {
                $columns = CollectionHelper::appendAfter($columns, 'url_links', $label, 'query');
            } else {
                $columns->put('url_links', $label);
            }
        }

        $keywords = $this->queries->values();

        $data = $data->values()->map(function ($row, $idx) use ($keywords, $wantCount, $wantLinks) {
            $row = $row instanceof Collection ? $row : collect($row);
            $kw = $keywords->get($idx);
            $urls = collect();
            if ($kw && isset($kw->urls)) {
                $urls = collect($kw->urls)
                    ->map(static function ($u) {
                        return trim((string) ($u->url ?? ''));
                    })
                    ->filter(static function ($u) {
                        return $u !== '';
                    })
                    ->unique()
                    ->values();
            }

            if ($wantCount && $row->has('url')) {
                $row->put('url', (string) $urls->count());
            }

            if ($wantLinks) {
                $links = $urls->implode("\n");
                if ($row->has('url')) {
                    $row = CollectionHelper::appendAfter($row, 'url_links', $links, 'url');
                } elseif ($row->has('query')) {
                    $row = CollectionHelper::appendAfter($row, 'url_links', $links, 'query');
                } else {
                    $row->put('url_links', $links);
                }
            }

            return $row;
        });

        $response['columns'] = $columns;
        $response['data'] = $data;
    }

    public function edit($id)
    {
        $project = MonitoringProject::query()
            ->with(['searchengines.location', 'groups'])
            ->findOrFail($id);

        if (request()->ajax()) {
            return view('monitoring.export.edit', compact('project'));
        }

        return view('monitoring.export.page', compact('project'));
    }
}
