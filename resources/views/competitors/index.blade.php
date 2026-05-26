@component('component.card', [
    'title' => __('Competitor analysis'),
    'titleHtml' => e(__('Competitor analysis')) . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-competitor-analysis'])->render(),
])
    @slot('css')
        <link rel="stylesheet"
              type="text/css"
              href="{{ asset('plugins/common/css/datatable.css') }}"/>
        <link rel="stylesheet"
              type="text/css"
              href="{{ asset('plugins/list-comparison/css/font-awesome-4.7.0/css/font-awesome.css') }}"/>
        <link rel="stylesheet"
              type="text/css"
              href="{{ asset('plugins/toastr/toastr.css') }}"/>
        <link rel="stylesheet" href="{{ asset('plugins/bootstrap4-duallistbox/bootstrap-duallistbox.css') }}">
        <link rel="stylesheet" href="{{ asset('css/cabinet-competitor-analysis.css') }}?v={{ @filemtime(public_path('css/cabinet-competitor-analysis.css')) ?: time() }}">
        <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.min.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
        <style>
            #header-nav-bar .cabinet-header-limits-menu tr.CompetitorAnalysisPhrases {
                background: oldlace;
            }

            #header-nav-bar .cabinet-header-limits-menu tr.CompetitorAnalysisPhrases.danger {
                background: rgb(255, 193, 7);
            }
        </style>
    @endslot

    <div class="cabinet-competitor-analysis-page">
        @include('competitors.partials.module-nav', ['active' => 'analyzer', 'admin' => $admin ?? false])
        @include('competitors.partials.limit-banner')

        <div class="row g-3 cabinet-ca-form-row">
            <div class="col-lg-6">
                <div class="mb-3 required">
                    <div class="d-flex justify-content-between">
                        <label class="form-label">{{ __('List of phrases') }}</label>
                        <div class="text-muted">{{__('count phrases')}}: <span id="countAddedPhrases">0</span></div>
                    </div>
                    {!! Form::textarea("phrases", null ,["class" => "form-control phrases","required" => "required", 'id' => 'phrasesList']) !!}
                    <span class="text-muted">{{ __('The maximum number of phrases is 40') }}</span>
                </div>
                <div class="mb-3 required">
                    <label class="form-label">{{ __('Top 10/20/30') }}</label>
                    {!! Form::select('count', [
                            '30' => __('30 (recommended)'),
                            '20' => 20,
                            '10' => 10,
                            ], '30', ['class' => 'form-select count']) !!}
                </div>
                <div class="mb-3 required">
                    <label class="form-label d-block">{{ __('Search engines') }}</label>
                    <div class="cabinet-ca-engines d-flex flex-wrap gap-3">
                        <div class="form-check">
                            <input class="form-check-input cabinet-ca-engine-check"
                                   type="checkbox"
                                   name="search_engines[]"
                                   value="yandex"
                                   id="cabinet-ca-engine-yandex"
                                   checked>
                            <label class="form-check-label" for="cabinet-ca-engine-yandex">{{ __('Yandex') }}</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input cabinet-ca-engine-check"
                                   type="checkbox"
                                   name="search_engines[]"
                                   value="google"
                                   id="cabinet-ca-engine-google">
                            <label class="form-check-label" for="cabinet-ca-engine-google">{{ __('Google') }}</label>
                        </div>
                    </div>
                    <div class="form-text">{{ __('Select one or both. Parsing runs separately for each search engine and region.') }}</div>
                </div>
                @php
                    $regionDefaults = [
                        'yandex' => $defaultRegion ?? null,
                        'google' => $defaultGoogleRegion ?? null,
                    ];
                @endphp
                @foreach($regionDefaults as $engineKey => $defaultReg)
                    <div class="mb-3 required cabinet-ca-region-field cabinet-ca-region-field--{{ $engineKey }}"
                         data-engine="{{ $engineKey }}"
                         @if($engineKey === 'google') style="display: none" @endif>
                        <label class="form-label" for="cabinet-ca-region-{{ $engineKey }}">
                            {{ __('Regions') }}
                            ({{ $engineKey === 'google' ? 'Google' : __('Yandex') }})
                        </label>
                        <select id="cabinet-ca-region-{{ $engineKey }}"
                                name="regions_{{ $engineKey }}[]"
                                class="form-select cabinet-ca-region-select region"
                                multiple="multiple"
                                data-engine="{{ $engineKey }}"
                                data-max-regions="{{ (int) config('cabinet-competitor-analysis.max_regions', 5) }}"
                                data-placeholder="{{ __('Add cities (up to 5)') }}">
                            @if(!empty($defaultReg))
                                <option value="{{ $defaultReg['id'] }}" selected>
                                    {{ $defaultReg['text'] }}
                                </option>
                            @endif
                        </select>
                    </div>
                @endforeach
                <div class="form-text cabinet-ca-region-hint mb-3">
                    {{ __('Select 1 to 5 cities per search engine. Parsing runs separately for each region.') }}
                </div>
                <div class="cabinet-ca-form-actions">
                    <button class="btn btn-secondary" type="button"
                            id="start-analyse">{{ __('Analyze') }}</button>
                </div>
            </div>
        </div>

            <div id="toast-container" class="toast-top-right broken-script-message" style="display: none">
                <div class="toast toast-error" aria-live="assertive">
                    <div class="toast-message"></div>
                </div>
            </div>

            <div id="progress-bar" class="cabinet-ca-progress mt-4" style="display: none" aria-live="polite">
                <div class="d-flex justify-content-between align-items-center gap-2 mb-1">
                    <span class="small fw-semibold mb-0">{{ __('Analysis in progress') }}</span>
                    <span class="small text-muted mb-0" id="cabinet-ca-progress-percent">0%</span>
                </div>
                <div class="progress">
                    <div id="cabinet-ca-progress-bar"
                         class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
                         role="progressbar"
                         style="width: 0%"
                         aria-valuenow="0"
                         aria-valuemin="0"
                         aria-valuemax="100">0%</div>
                </div>
                <div id="stage" class="text-muted small mt-2">{{ __('Processing the XML service response') }}</div>
            </div>

            @include('competitors.partials.admin-debug-log', ['admin' => $admin ?? false])

            <div class="mt-4" id="render-bar" style="display: none">
                <img src="{{ asset('/img/1485.gif') }}" alt="preloader_gif">
                <p>{{ __('Render data') }}</p>
            </div>

            <div id="cabinet-ca-region-results-tabs" class="cabinet-ca-region-tabs nav nav-pills flex-wrap gap-1 mt-4" style="display: none" role="tablist"></div>

            <div id="cabinet-ca-geo-verdict" class="cabinet-ca-geo-verdict-wrap mt-3" style="display: none" aria-live="polite"></div>

            <div id="sites-block" class="cabinet-ca-results-section mt-5" style="display:none;">
                <div class="cabinet-ca-results-section__head cabinet-ca-results-section__head--serp">
                    <h2 class="cabinet-ca-section-title mb-0">{{ __('Top sites based on your keywords') }}</h2>
                    <div id="cabinet-ca-serp-compare-bar" class="cabinet-ca-serp-compare-bar alert alert-info border-2 mb-0 py-2 px-3" style="display: none" role="region" aria-label="{{ __('Compare cities in SERP') }}">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="fw-semibold text-nowrap">
                                <i class="fas fa-city me-1" aria-hidden="true"></i>{{ __('City comparison') }}:
                            </span>
                            <span class="small">{{ __('Showing') }}</span>
                            <span id="cabinet-ca-serp-active-label" class="badge text-bg-primary"></span>
                            <span class="small cabinet-ca-serp-compare-plus">+</span>
                            <div id="cabinet-ca-serp-compare-buttons" class="btn-group btn-group-sm flex-wrap" role="group"></div>
                            <button type="button" class="btn btn-sm btn-outline-dark" id="cabinet-ca-serp-compare-clear" style="display: none">
                                {{ __('Hide second city') }}
                            </button>
                        </div>
                        <p class="small mb-0 mt-2">{{ __('Click a second city to show two SERPs side by side in each phrase column. Then press Highlight identical urls to match URLs between cities.') }}</p>
                    </div>
                </div>
                <div class="card cabinet-ca-results-card border-0 shadow-sm">
                    <div class="card-body">
                <div class="site-block-buttons cabinet-ca-toolbar mb-3 d-flex flex-wrap align-items-center gap-2" role="toolbar">
                    <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="{{ __('Highlight') }}">
                    <button class="btn btn-outline-secondary colored-button click_tracking"
                            type="button"
                            data-click="Highlight identical urls" id="coloredEloquentUrls"
                            data-bs-toggle="tooltip" data-bs-placement="bottom"
                            data-bs-title="{{ __('Highlight URLs that appear in two or more query columns (same color per URL)') }}">
                        {{ __('Highlight identical urls') }}
                    </button>

                    <button class="btn btn-outline-secondary colored-button click_tracking"
                            type="button"
                            data-click="Highlight the same domains" id="coloredEloquentDomains"
                            data-bs-toggle="tooltip" data-bs-placement="bottom"
                            data-bs-title="{{ __('Highlight domains that appear in two or more query columns') }}">
                        {{ __('Highlight the same domains') }}
                    </button>

                    <button class="btn btn-outline-secondary colored-button click_tracking"
                            type="button"
                            data-click="Highlight all main pages" id="coloredMainPages"
                            data-bs-toggle="tooltip" data-bs-placement="bottom"
                            data-bs-title="{{ __('Highlight main pages in the SERP') }}">
                        {{ __('Highlight all main pages') }}
                    </button>

                    <button type="button" class="btn btn-outline-secondary click_tracking" data-click="Highlight your"
                            data-bs-toggle="modal"
                            data-bs-target="#coloredEloquentMyTextModal">
                        {{ __('Highlight your') }}
                    </button>

                    <div class="modal fade" id="coloredEloquentMyTextModal" tabindex="-1"
                         aria-labelledby="coloredEloquentMyTextModalLabel"
                         aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="coloredEloquentMyTextModalLabel">
                                        {{ __('Highlighting the domains you need') }}
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <label for="search">{{ __('Your line') }}</label>
                                    <textarea name="search" id="search-textarea" cols="30" rows="10"
                                              class="form form-control"
                                              placeholder="{{ __('The substring is searched in the string') }}"></textarea>
                                </div>
                                <div class="modal-footer">
                                    <button class="btn btn-outline-secondary colored-button"
                                            id="coloredEloquentMyText"
                                            data-bs-dismiss="modal">
                                        {{ __('Highlight your') }}
                                    </button>
                                    <button type="button" class="btn btn-secondary"
                                            data-bs-dismiss="modal">{{ __('Close') }}</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="button" class="btn btn-outline-secondary click_tracking" data-click="Highlight site aggregators"
                            data-bs-toggle="modal"
                            data-bs-target="#coloredAgrigators">
                        {{ __('Highlight site aggregators') }}
                    </button>

                    <div class="modal fade" id="coloredAgrigators" tabindex="-1"
                         aria-labelledby="coloredAgrigatorsLabel"
                         aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title"
                                        id="coloredAgrigatorsLabel">{{ __('Highlighting aggregators') }}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <label for="search">{{ __('List of aggregator sites') }}</label>
                                    <textarea disabled name="search" id="search-agrigators" cols="30" rows="10"
                                              class="form form-control">{{ $config->agrigators }}</textarea>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-primary colored-button"
                                            id="coloredAgrigatorsButton"
                                            data-bs-dismiss="modal">
                                        {{ __('Highlight aggregators') }}
                                    </button>
                                    <button type="button" class="btn btn-secondary"
                                            data-bs-dismiss="modal">{{ __('Close') }}</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button class="btn btn-outline-secondary btn-sm" id="exportXLS">
                        <i class="fas fa-file-excel me-1" aria-hidden="true"></i>Экспорт XLS
                    </button>
                    </div>
                </div>

                <div id="sites-tables" class="cabinet-ca-serp-grid"></div>
                    </div>
                </div>
            </div>

            <div class="top-sites cabinet-ca-results-section mt-5" style="display: none">
                <div class="cabinet-ca-results-section__head">
                    <h2 class="cabinet-ca-section-title mb-0">{{ __('Top sites based on your keywords') }} {{ __('(headers and meta tags)') }}</h2>
                </div>
                <div class="card cabinet-ca-results-card border-0 shadow-sm">
                    <div class="card-body p-0 table-responsive">
                <table class="table table-hover table-sm mb-0 dataTable dtr-inline top-sites-table cabinet-ca-meta-table"
                       >
                    <thead>
                    <tr>
                        <th class="row-width">{{ __('Phrase') }}</th>
                        <th class="row-width">{{ __('First place') }}</th>
                        <th class="row-width">{{ __('Second place') }}</th>
                        <th class="row-width">{{ __('Third place') }}</th>
                        <th class="row-width">{{ __('Fourth place') }}</th>
                        <th class="row-width">{{ __('Fifth place') }}</th>
                        <th class="row-width">{{ __('Sixth place') }}</th>
                        <th class="row-width">{{ __('Seventh place') }}</th>
                        <th class="row-width">{{ __('Eighth place') }}</th>
                        <th class="row-width">{{ __('Ninth place') }}</th>
                        <th class="row-width">{{ __('Tenth place') }}</th>
                        @php
                            $metaExtraPlaces = [
                                11 => __('Eleventh place'),
                                12 => __('Twelfth place'),
                                13 => __('Thirteenth place'),
                                14 => __('Fourteenth place'),
                                15 => __('Fifteenth place'),
                                16 => __('Sixteenth place'),
                                17 => __('Seventeenth place'),
                                18 => __('Eighteenth place'),
                                19 => __('Nineteenth place'),
                                20 => __('Twentieth place'),
                            ];
                        @endphp
                        @foreach ($metaExtraPlaces as $place => $label)
                            <th class="extra-th row-width" data-place="{{ $place }}">{{ $label }}</th>
                        @endforeach
                        @for ($place = 21; $place <= 30; $place++)
                            <th class="extra-th row-width" data-place="{{ $place }}">{{ __('Place :n', ['n' => $place]) }}</th>
                        @endfor
                    </tr>
                    </thead>
                    <tbody id="top-sites-body">
                    </tbody>
                </table>
                    </div>
                </div>
            </div>

            <div class="nested cabinet-ca-results-section mt-5" style="display:none;">
                <div class="cabinet-ca-results-section__head">
                    <h2 class="cabinet-ca-section-title mb-0">{{ __('Analysis of page nesting') }}</h2>
                </div>
                <div class="row g-3 cabinet-ca-nesting-stats mb-3">
                    <div class="col-sm-6">
                        <div class="cabinet-ca-stat-pill">
                            <span class="cabinet-ca-stat-pill__label">{{ __('Main') }}</span>
                            <span class="cabinet-ca-stat-pill__value mainPageCounter">0</span>
                            <span class="cabinet-ca-stat-pill__pct mainPagePercent">0%</span>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="cabinet-ca-stat-pill cabinet-ca-stat-pill--accent">
                            <span class="cabinet-ca-stat-pill__label">{{ __('Nested') }}</span>
                            <span class="cabinet-ca-stat-pill__value nestedPageCounter">0</span>
                            <span class="cabinet-ca-stat-pill__pct nestedPagePercent">0%</span>
                        </div>
                    </div>
                </div>
                <table class="table table-sm table-bordered d-none cabinet-ca-nesting-table dataTable dtr-inline">
                    <thead>
                    <tr>
                        <th>{{ __('Page') }}</th>
                        <th>{{ __('Count') }}</th>
                        <th>{{ __('Ratio') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td class="dtr-control sorting_1">{{ __('Main') }}</td>
                        <td class="mainPageCounter"></td>
                        <td class="mainPagePercent"></td>
                    </tr>
                    <tr>
                        <td class="dtr-control sorting_1">{{ __('Nested') }}</td>
                        <td class="nestedPageCounter"></td>
                        <td class="nestedPagePercent"></td>
                    </tr>
                    </tbody>
                </table>
            </div>

            <div class="urls cabinet-ca-results-section mt-5" style="display: none">
                <div class="cabinet-ca-results-section__head">
                    <h2 class="cabinet-ca-section-title mb-0">{{ __('Landing Page analysis') }}</h2>
                </div>
                <div class="card cabinet-ca-results-card border-0 shadow-sm">
                    <div class="card-body p-0 table-responsive">
                <table class="table table-hover table-sm mb-0 dataTable dtr-inline cabinet-ca-urls-table" id="urls-table">
                    <thead>
                    <tr>
                        <th class="cabinet-ca-urls-col-link">{{ __('Links') }}</th>
                        <th class="cabinet-ca-urls-col-phrases text-center">{{ __('The phrase in which the link occurs') }}</th>
                        <th class="cabinet-ca-urls-col-count text-center">{{ __('Number of repetitions') }}</th>
                    </thead>
                    <tbody id="urls-tbody">
                    </tbody>
                </table>
                    </div>
                </div>
            </div>

            <div class="positions cabinet-ca-results-section mt-5" style="display: none">
                <div class="cabinet-ca-results-section__head">
                    <h2 class="cabinet-ca-section-title mb-0">{{ __('Analysis by the percentage of getting into the top and middle positions') }}</h2>
                </div>
                <div class="card cabinet-ca-results-card border-0 shadow-sm">
                    <div class="card-body p-0 table-responsive">
                <table class="table table-hover table-sm mb-0 dataTable dtr-inline" id="positions">
                    <thead>
                    <tr>
                        <th>{{ __('Domain') }}</th>
                        <th>{{ __('Percentage of getting into the top') }}</th>
                        <th>{{ __('Middle position') }}</th>
                    </tr>
                    </thead>
                    <tbody id="positions-tbody">

                    </tbody>
                </table>
                    </div>
                </div>
            </div>

            <div class="tag-analysis cabinet-ca-results-section mt-5" style="display: none">
                <div class="cabinet-ca-results-section__head">
                    <h2 class="cabinet-ca-section-title mb-0">{{ __('Tag Analysis') }}</h2>
                </div>
                <div class="card cabinet-ca-results-card border-0 shadow-sm">
                    <div class="card-body p-0 table-responsive">
                <table class="table table-sm mb-0 dataTable dtr-inline cabinet-ca-tags-table" id="tag-analysis"
                       >
                    <thead>
                    <tr id="tag-analysis-row">
                        <th style="min-width:200px; max-width: 200px">{{ __("Phrase") }}</th>
                        <th style="min-width:200px; max-width: 200px">title</th>
                        <th style="min-width:200px; max-width: 200px">H1</th>
                        <th style="min-width:200px; max-width: 200px">H2</th>
                        <th style="min-width:200px; max-width: 200px">H3</th>
                        <th style="min-width:200px; max-width: 200px">H4</th>
                        <th style="min-width:200px; max-width: 200px">H5</th>
                        <th style="min-width:200px; max-width: 200px">H6</th>
                        <th style="min-width:200px; max-width: 200px">description</th>
                    </tr>
                    </thead>
                    <tbody id="tag-analysis-tbody">
                    </tbody>
                </table>
                    </div>
                </div>
            </div>

            <div id="recommendations-block" class="cabinet-ca-results-section mt-5" style="display: none">
                <div class="cabinet-ca-results-section__head cabinet-ca-results-section__head--actions">
                    <div>
                        <h2 class="cabinet-ca-section-title mb-0">{{ __('Recommendations by query groups') }}</h2>
                        <p class="text-muted small mb-0 cabinet-ca-rec-hint">{{ __('Phrases with similar landing pages in SERP are grouped automatically; meta tag words are aggregated per group.') }}</p>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" id="cabinet-ca-build-recommendations">
                        {{ __('Build recommendations') }}
                    </button>
                </div>
                <div class="cabinet-ca-rec-tags-filter mb-3" id="cabinet-ca-rec-tags-filter">
                    <span class="small text-muted me-2">{{ __('Tags') }}:</span>
                    @foreach(['title', 'h1', 'h2', 'description'] as $recTag)
                        <label class="form-check form-check-inline mb-0">
                            <input class="form-check-input cabinet-ca-rec-tag-check" type="checkbox"
                                   value="{{ $recTag }}" @if(in_array($recTag, ['title', 'h1', 'description'], true)) checked @endif>
                            <span class="form-check-label">{{ $recTag }}</span>
                        </label>
                    @endforeach
                </div>
                <div id="cabinet-ca-recommendations-root" class="cabinet-ca-recommendations-root">
                    <div class="text-muted small" id="cabinet-ca-recommendations-placeholder">
                        {{ __('Recommendations will appear after analysis completes.') }}
                    </div>
                </div>
            </div>
    </div>

    @slot('js')
        <script src="{{ asset('plugins/datatables/jquery.dataTables.min.js') }}"></script>
        @include('layouts.partials.vendor-datatables-js', ['bundle' => 'rb-min'])
        <script src="{{ asset('plugins/datatables/buttons/jszip.min.js') }}"></script>
        <script src="{{ asset('plugins/datatables/buttons/html5.min.js') }}"></script>
        <script src="{{ asset('plugins/pdfmake/pdfmake.min.js') }}"></script>
        <script src="{{ asset('plugins/pdfmake/vfs_fonts.js') }}"></script>

        <script src="{{ asset('plugins/common/js/common.js') }}"></script>

        <script src="{{ asset('plugins/bootstrap4-duallistbox/jquery.bootstrap-duallistbox.js') }}"></script>
        <script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>
        <script src="{{ asset('plugins/competitor-analysis/js/render-top-sites-table.js') }}?v={{ @filemtime(public_path('plugins/competitor-analysis/js/render-top-sites-table.js')) ?: time() }}"></script>
        <script src="{{ asset('plugins/competitor-analysis/js/render-nesting-table.js') }}"></script>
        <script src="{{ asset('plugins/competitor-analysis/js/render-site-positions-table.js') }}?v={{ @filemtime(public_path('plugins/competitor-analysis/js/render-site-positions-table.js')) ?: time() }}"></script>
        <script src="{{ asset('plugins/competitor-analysis/js/render-tags-table.js') }}?v={{ @filemtime(public_path('plugins/competitor-analysis/js/render-tags-table.js')) ?: time() }}"></script>
        <script src="{{ asset('plugins/competitor-analysis/js/render-urls-table.js') }}?v={{ @filemtime(public_path('plugins/competitor-analysis/js/render-urls-table.js')) ?: time() }}"></script>
        <script src="{{ asset('plugins/competitor-analysis/js/refresh-all.js') }}?v={{ @filemtime(public_path('plugins/competitor-analysis/js/refresh-all.js')) ?: time() }}"></script>
        <script src="{{ asset('plugins/competitor-analysis/js/render-recommendations.js') }}?v={{ @filemtime(public_path('plugins/competitor-analysis/js/render-recommendations.js')) ?: time() }}"></script>
        <script src="{{ asset('plugins/competitor-analysis/js/render-geo-dependency.js') }}?v={{ @filemtime(public_path('plugins/competitor-analysis/js/render-geo-dependency.js')) ?: time() }}"></script>

        <script>
            $(function () {
            if (typeof $.fn.bootstrapDualListbox === 'function' && $('#duallistbox_tags').length) {
                $('#duallistbox_tags').bootstrapDualListbox();
            }

            window.competitorRecommendationsUrl = @json(route('competitor.get.recommendations'));
            window.competitorSerpCompareStrings = {
                showCity: @json(__('Show :city')),
                hideSecond: @json(__('Hide second city')),
                needTwoRegions: @json(__('Select at least two regions before analysis to compare cities here.')),
            };

            window.competitorHighlightStrings = {
                noAggregators: @json(__('No rows match aggregator domains in this SERP')),
                noDuplicateUrls: @json(__('No identical URLs across two or more query columns')),
                noDuplicateDomains: @json(__('No identical domains across two or more query columns')),
                tipHighlightUrls: @json(__('Highlight URLs that appear in two or more query columns (same color per URL)')),
                tipHighlightDomains: @json(__('Highlight domains that appear in two or more query columns')),
                tipHighlightMain: @json(__('Highlight main pages in the SERP')),
                tipHighlightUrlsCompare: @json(__('Highlight identical URLs in the same query column between the two cities')),
                tipHighlightDomainsCompare: @json(__('Highlight identical domains in the same query column between the two cities')),
                noCrossRegionUrls: @json(__('No identical URLs between cities in the same query column')),
                noCrossRegionDomains: @json(__('No identical domains between cities in the same query column')),
            };

            window.competitorGeoExcludedPreset = @json(\App\Support\CompetitorSerpDomainFilter::excludedDomainsBreakdown());

            window.competitorGeoStrings = {
                title_geo_independent: @json(__('Queries are geo-independent')),
                title_geo_dependent: @json(__('Queries are geo-dependent')),
                title_mixed: @json(__('Queries are partially geo-dependent')),
                body_geo_independent: @json(__('Top URLs are largely the same in the selected cities. One landing page may work across regions.')),
                body_geo_dependent: @json(__('Top URLs differ noticeably between cities. Consider separate pages or local signals per region.')),
                body_mixed: @json(__('Some queries match across cities, others do not. Check the table below before scaling one page nationwide.')),
                comparedRegions: @json(__('Regions compared')),
                avgOverlap: @json(__('Average top URL overlap')),
                byPhrase: @json(__('Per query')),
                columnPhrase: @json(__('Query')),
                columnOverlap: @json(__('Overlap')),
                columnVerdict: @json(__('Conclusion')),
                badge_geo_independent: @json(__('Geo-independent')),
                badge_geo_dependent: @json(__('Geo-dependent')),
                badge_partial: @json(__('Partial')),
                badge_skipped: @json(__('Not scored (only aggregators/marketplaces in top)')),
                sharedPages: @json(__('Shared pages')),
                noSharedUrls: @json(__('No shared URLs in top')),
                topCountHint: @json(__('top')),
                moreSharedUrls: @json(__('more')),
                overlapPerRegion: @json(__('Share of shared URLs in each region’s top')),
                footnote: @json(__('Overlap % is the average of (shared ÷ top in city A) and (shared ÷ top in city B). Example: 7 common of 15+15 → ~47%, not 7÷23. Aggregators/marketplaces excluded.')),
                excludedTitle: @json(__('Domains excluded from geo calculation')),
                excludedSourcesBoth: @json(__('From module “Aggregator list” settings and the default marketplace list')),
                excludedSourcesSettings: @json(__('From module “Aggregator list” settings')),
                excludedSourcesDefaults: @json(__('Default marketplace and aggregator list')),
            };

            window.competitorRecStrings = {
                loading: @json(__('Building recommendations…')),
                error: @json(__('Failed to build recommendations')),
                empty: @json(__('No data for recommendations')),
                placeholder: @json(__('Recommendations will appear after analysis completes.')),
                group: @json(__('Group')),
                similarity: @json(__('SERP similarity')),
                shared: @json(__('Shared URLs')),
                sharedUrls: @json(__('Sample shared landing pages')),
                noWords: @json(__('No words above threshold for selected tags')),
                chipInPhrase: @json(__('Present in the group query text')),
                chipScore: @json(__('How often competitors use this word in the tag')),
            };

            window.session = String(new Date()).shuffle();
            localStorage.setItem("sessionCompetitors", window.session);
            onStorage = function (e) {
                if (e.key === 'sessionCompetitors' && e.newValue !== window.session)
                    localStorage.setItem("multitab", window.session);
                if (e.key === "multitab" && e.newValue && e.newValue !== window.session) {
                    window.removeEventListener("storage", onStorage);
                    localStorage.setItem("sessionCompetitors", localStorage.getItem("multitab"));
                    localStorage.removeItem("multitab");
                }
            };
            window.addEventListener('storage', onStorage);

            if (typeof $.fn.select2 !== 'function') {
                return;
            }

            const defaultRegionsByEngine = {
                yandex: @json($defaultRegion ?? null),
                google: @json($defaultGoogleRegion ?? null),
            };
            let competitorResultBundle = null;

            /** Общий bundle для inline-скрипта и render-top-sites-table.js (вкладки регионов, сравнение городов в SERP). */
            function setCompetitorResultBundle(data) {
                competitorResultBundle = data;
                window.competitorResultBundle = data;
            }

            let activeCompetitorRegionKey = null;
            let competitorProgressTimer = null;
            let competitorLastProgressPercent = 0;
            const competitorAdminDebug = @json(!empty($admin));
            let competitorDebugPollCount = 0;
            let competitorClientDebugLines = [];
            let competitorRunFinished = false;
            let competitorPollGeneration = 0;
            window.competitorActiveRegionKey = null;
            window.competitorSerpCompareRegionKey = '';

            function selectedEngines() {
                const engines = [];
                $('.cabinet-ca-engine-check:checked').each(function () {
                    engines.push($(this).val());
                });
                return engines;
            }

            function regionSelectData(engine) {
                const def = defaultRegionsByEngine[engine];
                return def && def.id ? [def] : [];
            }

            function syncEngineRegionFields() {
                const engines = selectedEngines();
                $('.cabinet-ca-region-field').each(function () {
                    const engine = $(this).data('engine');
                    if (engines.indexOf(engine) >= 0) {
                        $(this).show();
                    } else {
                        $(this).hide();
                        const $sel = $('.cabinet-ca-region-select[data-engine="' + engine + '"]');
                        $sel.val(null).trigger('change');
                    }
                });
            }

            function initRegionSelect2($regionSelect) {
                const engine = $regionSelect.data('engine');
                const maxRegions = parseInt($regionSelect.data('max-regions'), 10) || 5;

                if ($regionSelect.hasClass('select2-hidden-accessible')) {
                    $regionSelect.select2('destroy');
                }

                $regionSelect.select2({
                    placeholder: $regionSelect.data('placeholder'),
                    allowClear: false,
                    minimumInputLength: 0,
                    maximumSelectionLength: maxRegions,
                    width: '100%',
                    data: regionSelectData(engine),
                    language: {
                        inputTooShort: function () {
                            return "{{ __('Enter at least 1 character to search') }}";
                        },
                        noResults: function () {
                            return "{{ __('No regions found') }}";
                        },
                        searching: function () {
                            return "{{ __('Searching…') }}";
                        },
                        maximumSelected: function () {
                            return "{{ __('You can select up to 5 regions') }}";
                        }
                    },
                    ajax: {
                        delay: 250,
                        url: "{{ route('competitor.analysis.regions') }}",
                        dataType: 'json',
                        data: function (params) {
                            return {
                                q: params.term || '',
                                engine: engine,
                            };
                        },
                        processResults: function (data) {
                            return {
                                results: $.map(data.results || [], function (item) {
                                    return {
                                        id: item.id,
                                        text: item.text,
                                    };
                                })
                            };
                        }
                    }
                });
            }

            $('.cabinet-ca-engine-check').on('change', function () {
                syncEngineRegionFields();
                updateTariffEstimate();
            });
            $('.cabinet-ca-region-select').each(function () {
                initRegionSelect2($(this));
            });
            $('.cabinet-ca-region-select').on('change', updateTariffEstimate);
            $('#phrasesList').on('input', updateTariffEstimate);
            syncEngineRegionFields();
            updateTariffEstimate();

            function countPhrases() {
                const raw = $.trim($('.form-control.phrases').val());
                if (!raw.length) {
                    return 0;
                }
                const lines = raw.split('\n').map(function (line) {
                    return $.trim(line);
                }).filter(function (line) {
                    return line.length > 0;
                });
                return lines.length;
            }

            function countSelectedRegions() {
                let total = 0;
                selectedEngines().forEach(function (engine) {
                    total += ($('.cabinet-ca-region-select[data-engine="' + engine + '"]').val() || []).length;
                });
                return total;
            }

            function updateTariffEstimate() {
                const $estimate = $('#cabinet-ca-tariff-estimate');
                if (!$estimate.length) {
                    return;
                }
                const phrases = countPhrases();
                const regions = countSelectedRegions();
                $estimate.text(phrases * regions);
            }

            function resolveRegionKey(region) {
                if (region.key) {
                    return String(region.key);
                }
                if (region.engine && region.id) {
                    return region.engine + '|' + region.id;
                }
                return String(region.id);
            }

            function getRegionPayload(result, regionKey) {
                if (result.byRegion && result.byRegion[regionKey]) {
                    return result.byRegion[regionKey];
                }
                return result;
            }

            function buildRegionTabs(regions) {
                const $tabs = $('#cabinet-ca-region-results-tabs');
                let list = (typeof getCompetitorRegionsList === 'function')
                    ? getCompetitorRegionsList()
                    : [];
                if (!list || list.length < 2) {
                    list = regions || [];
                }
                $tabs.empty();
                if (!list || list.length < 2) {
                    $tabs.hide();
                    if (typeof syncSerpCompareRegionBar === 'function') {
                        syncSerpCompareRegionBar(window.competitorActiveRegionKey || '');
                    }
                    return;
                }
                list.forEach(function (region, index) {
                    const active = index === 0 ? ' active' : '';
                    const regionKey = resolveRegionKey(region);
                    const label = region.tabLabel || region.name || region.text || region.id || regionKey;
                    $tabs.append(
                        '<button type="button" class="nav-link' + active + '" data-region-id="' + regionKey + '">' +
                        label + '</button>'
                    );
                });
                $tabs.show();
            }

            function clearCompetitorResults() {
                if (typeof resetSerpResultsDom === 'function') {
                    resetSerpResultsDom();
                }
                $('.top-sites').hide();
                $('.nested').hide();
                $('.positions').hide();
                $('.tag-analysis').hide();
                $('#sites-block').hide();
                $('.urls.mt-5').hide();
                $('#recommendations-block').hide();
                $('.render').remove();
                if ($.fn.dataTable && $('#positions').length && $.fn.dataTable.isDataTable('#positions')) {
                    $('#positions').DataTable().destroy();
                }
                if ($.fn.dataTable && $('#urls-table').length && $.fn.dataTable.isDataTable('#urls-table')) {
                    $('#urls-table').DataTable().destroy();
                }
            }

            async function renderCompetitorRegion(regionKey, count, localization) {
                if (!competitorResultBundle) {
                    return;
                }
                activeCompetitorRegionKey = regionKey;
                window.competitorActiveRegionKey = regionKey;
                const payload = getRegionPayload(competitorResultBundle, regionKey);

                if (window.competitorSerpCompareRegionKey === regionKey) {
                    window.competitorSerpCompareRegionKey = '';
                }
                clearCompetitorResults();
                $('#cabinet-ca-region-results-tabs .nav-link').removeClass('active');
                $('#cabinet-ca-region-results-tabs .nav-link[data-region-id="' + regionKey + '"]').addClass('active');

                if (typeof syncSerpCompareRegionBar === 'function') {
                    syncSerpCompareRegionBar(regionKey);
                }

                const serpOptions = buildSerpRenderOptions(regionKey);
                await renderTopSites(payload.analysedSites, localization, count);
                await renderTopSitesV2(payload.analysedSites, localization, serpOptions);
                await renderNestingTable(payload.pagesCounter);
                await renderSitePositionsTable(payload.domainsPosition, {{ $config->positions_length }});
                await renderTagsTable(payload.totalMetaTags);
                await renderUrlsTable(payload.urls, {{ $config->urls_length }});
                initCompetitorRecommendations(payload, count);

                if (typeof renderGeoDependencyVerdict === 'function' && competitorResultBundle) {
                    renderGeoDependencyVerdict(competitorResultBundle.geoDependency || null);
                }
            }

            function buildSerpRenderOptions(activeRegionKey) {
                const options = {
                    primaryLabel: typeof getCompetitorRegionLabel === 'function'
                        ? getCompetitorRegionLabel(activeRegionKey)
                        : activeRegionKey,
                };
                const compareKey = window.competitorSerpCompareRegionKey || '';
                if (!compareKey || compareKey === activeRegionKey || !competitorResultBundle) {
                    return options;
                }
                const comparePayload = getRegionPayload(competitorResultBundle, compareKey);
                options.compareSites = comparePayload.analysedSites || {};
                options.compareLabel = typeof getCompetitorRegionLabel === 'function'
                    ? getCompetitorRegionLabel(compareKey)
                    : compareKey;

                return options;
            }

            $(document).on('click', '#cabinet-ca-region-results-tabs .nav-link', function () {
                const regionKey = $(this).data('region-id');
                if (!regionKey || regionKey === activeCompetitorRegionKey) {
                    return;
                }
                const count = resolveAnalysisCount(competitorResultBundle, $('.form-select.count').val());
                const localization = window.competitorLocalization || {};
                renderCompetitorRegion(String(regionKey), count, localization);
            });

            function resolveAnalysisCount(result, fallback) {
                if (result && result.analysisCount) {
                    return String(result.analysisCount);
                }

                return String(fallback || $('.form-select.count').val() || '30');
            }

            $('#start-analyse').click(() => {
                let phrases = $.trim($('.form-control.phrases').val())
                let count = $('.form-select.count').val()
                const engines = selectedEngines()
                let token = $('meta[name="csrf-token"]').attr('content')
                if (!engines.length) {
                    getBrokenScriptMessage(null, "{{ __('Select at least one search engine') }}")
                    return
                }
                let hasRegions = true
                engines.forEach(function (engine) {
                    const vals = $('.cabinet-ca-region-select[data-engine="' + engine + '"]').val() || []
                    if (!vals.length) {
                        hasRegions = false
                    }
                })
                if (!hasRegions) {
                    getBrokenScriptMessage(null, "{{ __('Select regions for each search engine') }}")
                    return
                }
                if (phrases.length > 0) {
                    stopCompetitorProgressPolling();
                    competitorPollGeneration++;
                    setCompetitorResultBundle(null);
                    competitorLastProgressPercent = 0;
                    competitorDebugLine('info', 'analyse.click', {
                        phrases: phrases.length,
                        count: count,
                        engines: engines,
                        pageHash: window.session
                    });

                    $.ajax({
                        type: "POST",
                        dataType: "json",
                        url: "{{ route('start.competitor.progress') }}",
                        data: {
                            _token: token,
                            pageHash: window.session,
                        },
                    }).always(function () {
                        try {
                            refreshAll();
                        } catch (e) {
                            console.error('competitor refreshAll', e);
                            $('#start-analyse').prop('disabled', true);
                            $('#progress-bar').show(300);
                            if (typeof setProgressBarStyles === 'function') {
                                setProgressBarStyles(1);
                            }
                        }

                        $.ajax({
                            type: "POST",
                            dataType: "json",
                            url: "{{ route('analysis.sites') }}",
                            data: {
                                _token: token,
                                phrases: phrases,
                                count: count,
                                search_engines: engines,
                                regions_yandex: $('.cabinet-ca-region-select[data-engine="yandex"]').val() || [],
                                regions_google: $('.cabinet-ca-region-select[data-engine="google"]').val() || [],
                                pageHash: window.session,
                            },
                            beforeSend: function () {
                                startCompetitorProgressPolling(token, count);
                            },
                            success: function () {
                            },
                            error: function (response) {
                                stopCompetitorProgressPolling();
                                setTimeout(() => {
                                    $("#progress-bar").hide(300)
                                    $('#start-analyse').prop('disabled', false);
                                }, 1000)
                                var message = (response.responseJSON && response.responseJSON.message)
                                    ? response.responseJSON.message
                                    : "{{ __('An unexpected error has occurred, please contact the administrator') }}";
                                getBrokenScriptMessage(null, message)
                            }
                        });
                    });
                } else {
                    getBrokenScriptMessage(null, "{{ __('The list of keywords should not be empty') }}")
                }
            });

            function competitorDebugLine(level, message, context) {
                if (!competitorAdminDebug) {
                    return;
                }
                var t = new Date();
                var ts = t.toLocaleTimeString('ru-RU') + '.' + String(t.getMilliseconds()).padStart(3, '0');
                var ctx = context ? ' ' + JSON.stringify(context) : '';
                competitorClientDebugLines.push('[' + ts + '] [' + level + '] [client] ' + message + ctx);
                if (competitorClientDebugLines.length > 80) {
                    competitorClientDebugLines = competitorClientDebugLines.slice(-80);
                }
            }

            function renderCompetitorDebugLog(serverLog, debugState) {
                if (!competitorAdminDebug) {
                    return;
                }
                var $panel = $('#cabinet-ca-admin-debug');
                var $pre = $('#cabinet-ca-debug-log');
                $panel.show();
                $('#cabinet-ca-debug-session').text(window.session || '—');
                $('#cabinet-ca-debug-poll').text(String(competitorDebugPollCount));

                var lines = [];
                if (debugState) {
                    lines.push('--- state ---');
                    lines.push(JSON.stringify(debugState, null, 2));
                }
                if (Array.isArray(serverLog) && serverLog.length) {
                    lines.push('--- server ---');
                    serverLog.forEach(function (row) {
                        var ctx = row.context && Object.keys(row.context).length
                            ? ' ' + JSON.stringify(row.context)
                            : '';
                        lines.push('[' + row.t + '] [' + row.level + '] ' + row.message + ctx);
                    });
                }
                if (competitorClientDebugLines.length) {
                    lines.push('--- browser ---');
                    lines = lines.concat(competitorClientDebugLines);
                }
                $pre.text(lines.join('\n'));
                $pre.scrollTop($pre[0].scrollHeight);
            }

            function finishCompetitorRun() {
                competitorRunFinished = true;
                stopCompetitorProgressPolling();
                $('#start-analyse').prop('disabled', false);
            }

            function startCompetitorProgressPolling(token, count) {
                stopCompetitorProgressPolling();
                const pollGen = competitorPollGeneration;
                competitorDebugPollCount = 0;
                competitorClientDebugLines = [];
                competitorRunFinished = false;
                competitorDebugLine('info', 'polling.start', {pageHash: window.session, pollGen: pollGen});
                competitorProgressTimer = setInterval(function () {
                    getProgressPercent(token, count, pollGen);
                }, 1000);
                getProgressPercent(token, count, pollGen);
            }

            function stopCompetitorProgressPolling() {
                if (competitorProgressTimer) {
                    clearInterval(competitorProgressTimer);
                    competitorProgressTimer = null;
                }
            }

            function getProgressPercent(token, count, pollGen) {
                if (pollGen !== undefined && pollGen !== competitorPollGeneration) {
                    return;
                }

                $.ajax({
                    type: "POST",
                    dataType: "json",
                    url: "{{ route('get.competitor.progress') }}",
                    data: {
                        _token: token,
                        pageHash: window.session,
                    },
                    success: async function (response) {
                        if (pollGen !== undefined && pollGen !== competitorPollGeneration) {
                            return;
                        }
                        if (competitorRunFinished) {
                            return;
                        }

                        competitorDebugPollCount++;
                        var percent = parseInt(response.percent, 10);
                        if (isNaN(percent)) {
                            percent = 0;
                        }
                        var serverPercent = percent;

                        if (response.failed) {
                            finishCompetitorRun();
                            $('#progress-bar').hide(300);
                            getBrokenScriptMessage(null, response.message || "{{ __('An unexpected error has occurred, please contact the administrator') }}");
                            if (response.debug_admin && response.debug_log) {
                                renderCompetitorDebugLog(response.debug_log, response.debug_state || null);
                            }
                            return;
                        }

                        if (percent < competitorLastProgressPercent && !response.failed) {
                            competitorDebugLine('warn', 'ui.regression_blocked', {
                                server: serverPercent,
                                ui_kept: competitorLastProgressPercent
                            });
                            percent = competitorLastProgressPercent;
                        } else if (percent > competitorLastProgressPercent) {
                            competitorLastProgressPercent = percent;
                        } else if (percent < competitorLastProgressPercent) {
                            competitorDebugLine('warn', 'percent_dropped', {
                                server: serverPercent,
                                ui: competitorLastProgressPercent
                            });
                        }

                        if (response.debug_admin && response.debug_log) {
                            renderCompetitorDebugLog(response.debug_log, response.debug_state || null);
                        }

                        competitorDebugLine('info', 'poll', {
                            percent: serverPercent,
                            ui: percent,
                            failed: !!response.failed,
                            row: response.debug_state ? response.debug_state.row_exists : null
                        });

                        if (percent >= 100) {
                            if (response.result && response.result.error) {
                                finishCompetitorRun();
                                $('#progress-bar').hide(300);
                                getBrokenScriptMessage(null, response.result.message || "{{ __('An unexpected error has occurred, please contact the administrator') }}");
                                return;
                            }

                            let localization = {
                                'protected': "{{ __('The site is protected from information collection, we recommend analyzing it manually') }}",
                                'fetchFailed': "{{ __('Could not load the page (timeout or network error)') }}",
                                'metaEmpty': "{{ __('Page loaded but meta tags were not found in HTML (non-standard markup or JS rendering)') }}",
                                'domain': "{{ __('domain') }}",
                                'mainPage': "{{ __('Go to the landing page') }}",
                                'site': "{{ __('Go to site') }}",
                                'analyzeText': "{{ __('Analyze the text') }}",
                                'SelectPhrases': "{{ __('Select phrases') }}",
                            }
                            window.competitorLocalization = localization;

                            setCompetitorResultBundle(response.result);
                            const resultCount = resolveAnalysisCount(response.result, count);
                            const regionList = response.result.regions || [];
                            const firstRegionKey = regionList.length
                                ? resolveRegionKey(regionList[0])
                                : '';

                            finishCompetitorRun();
                            setProgressBarStyles(100);
                            $('#render-bar').show(300);

                            buildRegionTabs(regionList);
                            const regionKeysForUi = (typeof getCompetitorRegionsList === 'function')
                                ? getCompetitorRegionsList()
                                : regionList;
                            const uiFirstKey = regionKeysForUi.length
                                ? resolveRegionKey(regionKeysForUi[0])
                                : firstRegionKey;
                            if (uiFirstKey) {
                                await renderCompetitorRegion(uiFirstKey, resultCount, localization)
                            } else if (typeof renderGeoDependencyVerdict === 'function') {
                                renderGeoDependencyVerdict(response.result.geoDependency || null);
                            }

                            setTimeout(function () {
                                $('#render-bar').hide(300);
                                $('#progress-bar').hide(300);
                            }, 800);

                        } else {
                            setProgressBarStyles(percent);
                        }
                    },
                    error: function (xhr) {
                        competitorDebugLine('error', 'poll.http_error', {
                            status: xhr.status,
                            statusText: xhr.statusText
                        });
                        stopCompetitorProgressPolling();
                        $('#start-analyse').prop('disabled', false);
                        getBrokenScriptMessage(null, "{{ __('Failed to load progress. Try again.') }}");
                    }
                });
            }

            if (competitorAdminDebug) {
                $('#cabinet-ca-debug-clear').on('click', function () {
                    competitorClientDebugLines = [];
                    $('#cabinet-ca-debug-log').text('');
                });
                $('#cabinet-ca-debug-copy').on('click', function () {
                    var text = $('#cabinet-ca-debug-log').text();
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(text);
                    }
                });
            }

            function getBrokenScriptMessage(interval, message) {
                stopCompetitorProgressPolling();
                setProgressBarStyles(0);
                setTimeout(function () {
                    $('#progress-bar').hide(300);
                    $('#start-analyse').prop('disabled', false);
                    $('#render-bar').hide(300);
                }, 400);

                $('.toast-top-right.broken-script-message').show(300)
                $('.toast-message').html(message)
                setTimeout(() => {
                    $('.toast-top-right.broken-script-message').hide(300)
                }, 10000)
            }

            function getErrorMessage() {
                return "{{ __('The site is protected from information collection, we recommend analyzing it manually') }}"
            }

            function getStringDomain() {
                return "{{ __('Domain') }}"
            }

            function getStringPercent() {
                return "{{ __('Percentage of getting into the top') }}"
            }

            function getStringPosition() {
                return "{{ __('Middle position') }}"
            }

            function setProgressBarStyles(percent) {
                percent = Math.max(0, Math.min(100, parseInt(percent, 10) || 0));
                var $bar = $('#cabinet-ca-progress-bar');
                $bar.css('width', percent + '%');
                $bar.attr('aria-valuenow', percent);
                $bar.text(percent + '%');
                $('#cabinet-ca-progress-percent').text(percent + '%');

                if (percent < 30) {
                    $('#stage').html("{{ __('Processing the XML service response') }}");
                } else if (percent <= 90) {
                    $('#stage').html("{{ __('Parse') }}");
                } else {
                    $('#stage').html("{{ __('To processing') }}");
                }
            }

            function getXMLMessage() {
                return "{{ __('Processing the XML service response') }}"
            }

            function stringGoToPage() {
                return "{{ __('Go to the landing page') }}"
            }

            function stringGoToSite() {
                return "{{ __('Go to site') }}"
            }

            function stringGoToAnalyse() {
                return "{{ __('Analyze the text') }}"
            }

            document.getElementById('phrasesList').addEventListener('keyup', function () {
                let countAddedPhrases = $('#countAddedPhrases')
                let numberLineBreaksInFirstList = 0;
                let phrasesList = $('#phrasesList').val().split('\n');
                for (let i = 0; i < phrasesList.length; i++) {
                    if (phrasesList[i] !== '') {
                        numberLineBreaksInFirstList++
                    }
                }

                countAddedPhrases.html(numberLineBreaksInFirstList)

                if (numberLineBreaksInFirstList > 40) {
                    countAddedPhrases.css({
                        'color': '#dc3545'
                    })
                    $('#start-analyse').attr('disabled', true);
                } else {
                    countAddedPhrases.css({
                        'color': '#6c757d'
                    })
                    $('#start-analyse').attr('disabled', false);
                }
            });

            console.clear()
            });
        </script>
        <script>
            $(document).ready(function () {
                let phrases = localStorage.getItem('lk_redbox_phrases_for_analyse')

                if (phrases !== null) {
                    $('#phrasesList').val(phrases)
                    localStorage.removeItem('lk_redbox_phrases_for_analyse')
                }
            })
        </script>
    @endslot
@endcomponent
