@php
    $cabinetTaPayload = [
        'clouds' => $response['clouds'] ?? ['text' => [], 'links' => [], 'both' => []],
        'graph' => $response['graph'] ?? [],
    ];
    $cabinetTaWordForms = [];
    foreach ($response['totalWords'] as $wordIndex => $word) {
        $hasWordForms = !empty($word['wordForms']['inLink'] ?? null)
            || !empty($word['wordForms']['inText'] ?? null);
        if ($hasWordForms) {
            $cabinetTaWordForms['w' . $wordIndex] = view('text-analyse.partials.word-forms-panel', ['word' => $word])->render();
        }
    }
@endphp
<script type="application/json" id="cabinet-ta-payload">{!! json_encode($cabinetTaPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
@if(!empty($cabinetTaWordForms))
<script type="application/json" id="cabinet-ta-word-forms">{!! json_encode($cabinetTaWordForms, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
@endif

<div class="cabinet-ta-results">
    <div class="row g-2 g-md-3 mb-3 cabinet-ta-kpi">
        <div class="col-6 col-lg-3 d-flex min-w-0">
            <div class="info-box mb-0 flex-fill h-100">
                <span class="info-box-icon text-bg-primary shadow-sm"><i class="bi bi-fonts"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-wrap">{{ __('Number of words') }}</span>
                    <span class="info-box-number">{{ number_format($response['general']['countWords'], 0, ',', ' ') }}</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3 d-flex min-w-0">
            <div class="info-box mb-0 flex-fill h-100">
                <span class="info-box-icon text-bg-info shadow-sm"><i class="bi bi-textarea-t"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-wrap">{{ __('Number of characters') }}</span>
                    <span class="info-box-number">{{ number_format($response['general']['textLength'], 0, ',', ' ') }}</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3 d-flex min-w-0">
            <div class="info-box mb-0 flex-fill h-100">
                <span class="info-box-icon text-bg-secondary shadow-sm"><i class="bi bi-distribute-vertical"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-wrap">{{ __('Number of spaces') }}</span>
                    <span class="info-box-number">{{ number_format($response['general']['countSpaces'], 0, ',', ' ') }}</span>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3 d-flex min-w-0">
            <div class="info-box mb-0 flex-fill h-100">
                <span class="info-box-icon text-bg-success shadow-sm"><i class="bi bi-type"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-wrap">{{ __('Number of characters without spaces') }}</span>
                    <span class="info-box-number">{{ number_format($response['general']['lengthWithOutSpaces'], 0, ',', ' ') }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header py-2">
            <h3 class="card-title h6 mb-0">
                <i class="bi bi-graph-up me-1 text-primary"></i>{{ __('Text analysis according to Zipfs law') }}
            </h3>
        </div>
        <div class="card-body">
            <p class="text-secondary small mb-2">
                @if(!empty($request['conjunctionsPrepositionsPronouns']))
                    {{ __('Zipf chart stop words hint') }}
                @else
                    {{ __('Zipf chart includes stop words hint') }}
                @endif
            </p>
            <div class="cabinet-ta-chart-wrap">
                <canvas id="cabinet-ta-zipf-chart"
                        role="img"
                        aria-label="{{ __('Text analysis according to Zipfs law') }}"></canvas>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3 cabinet-ta-words-table-card">
        <div class="card-header py-2">
            <h3 class="card-title h6 mb-0">
                <i class="bi bi-table me-1 text-primary"></i>{{ __('General word analysis') }}
            </h3>
        </div>
        <div class="card-body p-0 cabinet-ta-dt-card">
            <div class="table-responsive cabinet-ta-table-wrap">
                <table id="totalTable" class="table table-sm table-striped table-hover align-middle mb-0 w-100">
                    <thead class="table-light">
                    <tr>
                        <th>{{ __('Word') }}</th>
                        <th class="text-end">{{ __('Density') }}</th>
                        <th class="text-end">{{ __('Common area') }}</th>
                        <th class="text-end">{{ __('Text Area') }}</th>
                        <th class="text-end">{{ __('Link Zone') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($response['totalWords'] as $word)
                        @php
                            $wordRowId = 'w' . $loop->index;
                            $hasWordForms = !empty($word['wordForms']['inLink'] ?? null)
                                || !empty($word['wordForms']['inText'] ?? null);
                        @endphp
                        <tr class="cabinet-ta-word-row{{ $hasWordForms ? ' cabinet-ta-word-row--expandable' : '' }}"
                            data-cabinet-ta-word-id="{{ $wordRowId }}">
                            <td data-order="{{ $word['text'] }}" class="cabinet-ta-word-cell">
                                @if($hasWordForms)
                                    <button type="button"
                                            class="cabinet-ta-word-toggle btn btn-link btn-sm p-0 text-start text-body text-decoration-none d-inline-flex align-items-center gap-1"
                                            aria-expanded="false"
                                            title="{{ __('Word forms') }}">
                                        <span class="fw-medium">{{ $word['text'] }}</span>
                                        <i class="bi bi-chevron-down small text-secondary cabinet-ta-word-toggle__icon" aria-hidden="true"></i>
                                    </button>
                                @else
                                    <span class="fw-medium">{{ $word['text'] }}</span>
                                @endif
                            </td>
                            <td data-order="{{ $word['density'] }}" class="text-end font-monospace">{{ $word['density'] }}</td>
                            <td data-order="{{ $word['total'] }}" class="text-end font-monospace">{{ $word['total'] }}</td>
                            <td data-order="{{ $word['inText'] }}" class="text-end font-monospace">{{ $word['inText'] }}</td>
                            <td data-order="{{ $word['inLink'] }}" class="text-end font-monospace">{{ $word['inLink'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3 cabinet-ta-cloud-card">
        <div class="card-header p-0 border-bottom-0">
            <ul class="nav nav-tabs card-header-tabs px-2 pt-2" id="cabinet-ta-cloud-tabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="cabinet-ta-cloud-text-tab" data-bs-toggle="tab"
                            data-bs-target="#cabinet-ta-cloud-text" type="button" role="tab"
                            aria-controls="cabinet-ta-cloud-text" aria-selected="true">
                        {{ __('Text Area') }}
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="cabinet-ta-cloud-links-tab" data-bs-toggle="tab"
                            data-bs-target="#cabinet-ta-cloud-links" type="button" role="tab"
                            aria-controls="cabinet-ta-cloud-links" aria-selected="false">
                        {{ __('Link Zone') }}
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="cabinet-ta-cloud-both-tab" data-bs-toggle="tab"
                            data-bs-target="#cabinet-ta-cloud-both" type="button" role="tab"
                            aria-controls="cabinet-ta-cloud-both" aria-selected="false">
                        {{ __('Text and Link zone') }}
                    </button>
                </li>
            </ul>
        </div>
        <div class="card-body cabinet-ta-cloud-panel">
            <p class="text-secondary small px-2 pt-1 mb-2">{{ __('Word cloud display limit hint') }}</p>
            <div class="tab-content">
                <div class="tab-pane fade show active" id="cabinet-ta-cloud-text" role="tabpanel"
                     aria-labelledby="cabinet-ta-cloud-text-tab" tabindex="0">
                    <div id="cabinet-ta-cloud-text-host" class="cabinet-ta-cloud generated-cloud" style="height:350px;width:100%;"></div>
                </div>
                <div class="tab-pane fade" id="cabinet-ta-cloud-links" role="tabpanel"
                     aria-labelledby="cabinet-ta-cloud-links-tab" tabindex="0">
                    <div id="cabinet-ta-cloud-links-host" class="cabinet-ta-cloud generated-cloud" style="height:350px;width:100%;"></div>
                </div>
                <div class="tab-pane fade" id="cabinet-ta-cloud-both" role="tabpanel"
                     aria-labelledby="cabinet-ta-cloud-both-tab" tabindex="0">
                    <div id="cabinet-ta-cloud-both-host" class="cabinet-ta-cloud generated-cloud" style="height:350px;width:100%;"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header py-2 cabinet-ta-card-header-stacked">
            <h3 class="card-title h6 mb-1">
                <i class="bi bi-quote me-1 text-primary"></i>{{ __('Phrases of 2 words') }}
            </h3>
            <p class="cabinet-ta-card-header-stacked__hint text-secondary small mb-0">
                @if(!empty($request['conjunctionsPrepositionsPronouns']))
                    {{ __('Phrases of 2 words without stop words hint') }}
                @else
                    {{ __('Phrases of 2 words with stop words hint') }}
                @endif
            </p>
        </div>
        <div class="card-body p-0 cabinet-ta-dt-card">
            <div class="table-responsive cabinet-ta-table-wrap">
                <table id="phrasesTable" class="table table-sm table-striped table-hover align-middle mb-0 w-100">
                    <thead class="table-light">
                    <tr>
                        <th>{{ __('Phrase') }}</th>
                        <th class="text-end">{{ __('Repetitions') }}</th>
                        <th class="text-end">{{ __('Density') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($response['phrases'] as $phrase)
                        <tr>
                            <td data-order="{{ $phrase['phrase'] }}"
                                title="{{ __('Repetitions') }}: {{ $phrase['count'] }}">
                                {{ trim($phrase['phrase']) }}
                            </td>
                            <td data-order="{{ $phrase['count'] }}" class="text-end font-monospace">{{ $phrase['count'] }}</td>
                            <td data-order="{{ $phrase['density'] }}" class="text-end font-monospace">{{ $phrase['density'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
