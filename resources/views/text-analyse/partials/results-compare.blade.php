@php
    $competitor = $response['competitor'] ?? [];
    $comparison = $response['comparison'] ?? [];
    $competitorUrl = $comparison['competitor_url'] ?? ($competitor['url'] ?? '');
    $competitorHost = $comparison['competitor_host'] ?? \App\TextAnalyzer::urlHost($competitorUrl);
    $competitorLabel = \App\TextAnalyzer::competitorLabel($competitorUrl);
    $compareWords = $comparison['totalWords'] ?? [];
    $comparePhrases = $comparison['phrases'] ?? [];
@endphp

<div class="alert alert-secondary py-2 mb-3 small">
    <i class="bi bi-arrow-left-right me-1"></i>
    {{ __('Comparison mode active') }} —
    <span class="text-muted">{{ __('Your page') }}</span> vs
    <a href="{{ $competitorUrl }}" target="_blank" rel="noopener" class="alert-link" title="{{ $competitorUrl }}">{{ $competitorHost ?: $competitorUrl }}</a>
</div>

<div class="row g-2 g-md-3 mb-3 cabinet-ta-kpi cabinet-ta-kpi--compare">
    <div class="col-12">
        <span class="badge text-bg-primary me-1">{{ __('Your page') }}</span>
    </div>
    @include('text-analyse.partials.results-kpi-row', ['general' => $response['general']])
    <div class="col-12 mt-2">
        <span class="badge text-bg-warning text-dark me-1">{{ $competitorLabel }}</span>
    </div>
    @include('text-analyse.partials.results-kpi-row', ['general' => $competitor['general'] ?? []])
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header py-2">
        <h3 class="card-title h6 mb-0">
            <i class="bi bi-graph-up me-1 text-primary"></i>{{ __('Text analysis according to Zipfs law') }}
        </h3>
    </div>
    <div class="card-body">
        <p class="text-secondary small mb-2">{{ __('Zipf compare hint') }}</p>
        <div class="cabinet-ta-chart-wrap">
            <canvas id="cabinet-ta-zipf-chart"
                    role="img"
                    aria-label="{{ __('Text analysis according to Zipfs law') }}"></canvas>
        </div>
        @include('text-analyse.partials.zipf-table', [
            'graph' => $response['graph'] ?? [],
            'competitorGraph' => $competitor['graph'] ?? [],
            'hasCompare' => true,
            'competitorUrl' => $competitorUrl,
            'competitorLabel' => $competitorLabel,
        ])
    </div>
</div>

<div class="card shadow-sm mb-3 cabinet-ta-cloud-card">
    <div class="card-header py-2">
        <h3 class="card-title h6 mb-0">
            <i class="bi bi-cloud me-1 text-primary"></i>{{ __('The clouds') }}
        </h3>
    </div>
    <div class="card-body cabinet-ta-cloud-panel">
        <p class="text-secondary small cabinet-ta-cloud-panel__hint">{{ __('Word cloud display limit hint') }}</p>
        @foreach([
            ['title' => __('Text Area'), 'main' => 'text', 'suffix' => 'text'],
            ['title' => __('Link Zone'), 'main' => 'links', 'suffix' => 'links'],
            ['title' => __('Text and Link zone'), 'main' => 'both', 'suffix' => 'both'],
        ] as $zone)
            <div class="cabinet-ta-cloud-compare-zone mb-3">
                <h4 class="cabinet-ta-cloud-block__title h6">{{ $zone['title'] }}</h4>
                <div class="row g-2">
                    <div class="col-md-6">
                        <p class="small text-primary fw-semibold mb-1">{{ __('Your page') }}</p>
                        <div id="cabinet-ta-cloud-{{ $zone['suffix'] }}-host" class="cabinet-ta-cloud generated-cloud"></div>
                    </div>
                    <div class="col-md-6">
                        <p class="small text-warning fw-semibold mb-1">{{ $competitorLabel }}</p>
                        <div id="cabinet-ta-cloud-{{ $zone['suffix'] }}-competitor-host" class="cabinet-ta-cloud generated-cloud cabinet-ta-cloud--competitor"></div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

<div class="card shadow-sm mb-3 cabinet-ta-words-table-card">
    <div class="card-header py-2">
        <h3 class="card-title h6 mb-0">
            <i class="bi bi-table me-1 text-primary"></i>{{ __('General word analysis') }} — {{ __('Compare with competitor') }}
        </h3>
    </div>
    <div class="card-body p-0 cabinet-ta-dt-card">
        <div class="cabinet-ta-table-toolbar px-2 pt-2 pb-1 d-flex flex-wrap align-items-center gap-2">
            <input type="search"
                   class="form-control form-control-sm cabinet-ta-table-search"
                   data-table="#totalTable"
                   data-row=".cabinet-ta-word-row"
                   autocomplete="off"
                   placeholder="{{ __('Search') }}">
            <span class="cabinet-ta-table-count text-secondary small"></span>
        </div>
        <div class="table-responsive cabinet-ta-table-wrap cabinet-ta-table-scroll">
            <table id="totalTable" class="table table-sm table-striped table-hover align-middle mb-0 w-100 cabinet-ta-compare-table">
                <thead class="table-light">
                <tr>
                    <th rowspan="2">{{ __('Word') }}</th>
                    <th colspan="3" class="text-center border-start">{{ __('Your page') }}</th>
                    <th colspan="3" class="text-center border-start">{{ $competitorLabel }}</th>
                    <th rowspan="2" class="text-end border-start">Δ</th>
                </tr>
                <tr>
                    <th class="text-end border-start">{{ __('Common area') }}</th>
                    <th class="text-end">{{ __('Text Area') }}</th>
                    <th class="text-end">{{ __('Link Zone') }}</th>
                    <th class="text-end border-start">{{ __('Common area') }}</th>
                    <th class="text-end">{{ __('Text Area') }}</th>
                    <th class="text-end">{{ __('Link Zone') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach($compareWords as $row)
                    @php
                        $main = $row['main'] ?? null;
                        $comp = $row['competitor'] ?? null;
                        $delta = (int) ($row['delta_total'] ?? 0);
                        $deltaClass = $delta > 0 ? 'text-success' : ($delta < 0 ? 'text-danger' : 'text-secondary');
                    @endphp
                    <tr class="cabinet-ta-word-row">
                        <td class="fw-medium">{{ $row['text'] }}</td>
                        <td class="text-end font-monospace border-start">{{ $main['total'] ?? '—' }}</td>
                        <td class="text-end font-monospace">{{ $main['inText'] ?? '—' }}</td>
                        <td class="text-end font-monospace">{{ $main['inLink'] ?? '—' }}</td>
                        <td class="text-end font-monospace border-start">{{ $comp['total'] ?? '—' }}</td>
                        <td class="text-end font-monospace">{{ $comp['inText'] ?? '—' }}</td>
                        <td class="text-end font-monospace">{{ $comp['inLink'] ?? '—' }}</td>
                        <td class="text-end font-monospace border-start {{ $deltaClass }}">
                            @if($delta > 0)+@endif{{ $delta !== 0 ? $delta : '0' }}
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header py-2">
        <h3 class="card-title h6 mb-0">
            <i class="bi bi-quote me-1 text-primary"></i>{{ __('Phrases of 2 words') }} — {{ __('Compare with competitor') }}
        </h3>
    </div>
    <div class="card-body p-0 cabinet-ta-dt-card">
        <div class="cabinet-ta-table-toolbar px-2 pt-2 pb-1 d-flex flex-wrap align-items-center gap-2">
            <input type="search"
                   class="form-control form-control-sm cabinet-ta-table-search"
                   data-table="#phrasesTable"
                   data-row="tbody tr"
                   autocomplete="off"
                   placeholder="{{ __('Search') }}">
            <span class="cabinet-ta-table-count text-secondary small"></span>
        </div>
        <div class="table-responsive cabinet-ta-table-wrap cabinet-ta-table-scroll">
            <table id="phrasesTable" class="table table-sm table-striped table-hover align-middle mb-0 w-100">
                <thead class="table-light">
                <tr>
                    <th>{{ __('Phrase') }}</th>
                    <th class="text-end">{{ __('Your page') }}</th>
                    <th class="text-end">{{ $competitorLabel }}</th>
                    <th class="text-end">Δ</th>
                </tr>
                </thead>
                <tbody>
                @forelse($comparePhrases as $row)
                    @php
                        $delta = (int) ($row['delta_count'] ?? 0);
                        $deltaClass = $delta > 0 ? 'text-success' : ($delta < 0 ? 'text-danger' : 'text-secondary');
                    @endphp
                    <tr>
                        <td>{{ $row['phrase'] }}</td>
                        <td class="text-end font-monospace">{{ $row['main']['count'] ?? '—' }}</td>
                        <td class="text-end font-monospace">{{ $row['competitor']['count'] ?? '—' }}</td>
                        <td class="text-end font-monospace {{ $deltaClass }}">
                            @if($delta > 0)+@endif{{ $delta !== 0 ? $delta : '0' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-secondary text-center py-4">{{ __('No records') }}</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
