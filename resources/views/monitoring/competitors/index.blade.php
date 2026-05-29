@component('component.card', [
    'title' => $project->name,
    'titleHtml' => '<span class="visually-hidden">' . e($project->name) . '</span>',
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('plugins/toastr/toastr.min.css') }}">
        @include('layouts.partials.vendor-datatables-css', ['bundle' => 'rb-css'])
        <link rel="stylesheet" href="{{ asset('css/cabinet-monitoring-show.css') }}?v={{ @filemtime(public_path('css/cabinet-monitoring-show.css')) ?: time() }}">
    @endslot

    <div class="cabinet-mon-project-page cabinet-mon-competitors-page" id="cabinet-mon-competitors-root">
        @include('monitoring.partials.show.project-chrome', [
            'project' => $project,
            'activeModule' => 'competitors',
            'showViewTabs' => false,
        ])

        <div class="cabinet-mon-project-page__body">
            <section class="cabinet-mon-competitors-workspace card" aria-labelledby="competitors-workspace-title">
                <div class="cabinet-mon-competitors-workspace__intro">
                    <h2 class="cabinet-mon-competitors-workspace__title h6 mb-0" id="competitors-workspace-title">
                        {{ __('Domains ranked in the top 10') }}
                    </h2>
                    <p class="cabinet-mon-competitors-workspace__hint text-secondary small mb-0">
                        {{ __('Monitoring competitors page hint') }}
                    </p>
                    <p class="cabinet-mon-competitors-workspace__date text-secondary small mb-0 d-none" id="competitors-date-line">
                        {{ __('The date of withdrawal of positions used') }}: <span id="dateOnly"></span>
                    </p>
                </div>

                <ol class="cabinet-mon-competitors-steps" id="competitors-steps" aria-label="{{ __('Monitoring competitors steps label') }}">
                    <li class="cabinet-mon-competitors-steps__item is-active" data-competitors-step="1">
                        <span class="cabinet-mon-competitors-steps__num" aria-hidden="true">1</span>
                        <span class="cabinet-mon-competitors-steps__body">
                            <span class="cabinet-mon-competitors-steps__title">{{ __('Monitoring competitors step1 title') }}</span>
                            <span class="cabinet-mon-competitors-steps__desc">{{ __('Monitoring competitors step1 desc') }}</span>
                        </span>
                    </li>
                    <li class="cabinet-mon-competitors-steps__item" data-competitors-step="2">
                        <span class="cabinet-mon-competitors-steps__num" aria-hidden="true">2</span>
                        <span class="cabinet-mon-competitors-steps__body">
                            <span class="cabinet-mon-competitors-steps__title">{{ __('Monitoring competitors step2 title') }}</span>
                            <span class="cabinet-mon-competitors-steps__desc">{{ __('Monitoring competitors step2 desc') }}</span>
                        </span>
                    </li>
                    <li class="cabinet-mon-competitors-steps__item" data-competitors-step="3">
                        <span class="cabinet-mon-competitors-steps__num" aria-hidden="true">3</span>
                        <span class="cabinet-mon-competitors-steps__body">
                            <span class="cabinet-mon-competitors-steps__title">{{ __('Monitoring competitors step3 title') }}</span>
                            <span class="cabinet-mon-competitors-steps__desc">{{ __('Monitoring competitors step3 desc') }}</span>
                        </span>
                    </li>
                </ol>

                <div class="cabinet-mon-competitors-workspace__form">
                    <div class="cabinet-mon-competitors-field cabinet-mon-competitors-field--region">
                        <label class="cabinet-mon-competitors-field__label" for="searchEngines">{{ __('Region') }}</label>
                        <select name="region" class="form-select form-select-sm" id="searchEngines">
                            @if($project->searchengines->count() > 1)
                                <option value="">{{ __('All search engine and regions') }}</option>
                            @endif
                            @foreach($project->searchengines as $search)
                                <option value="{{ $search->id }}" @if($search->id == request('region')) selected @endif>
                                    {{ \App\Classes\Monitoring\MonitoringLocationLabel::filterOption($search) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="cabinet-mon-competitors-field cabinet-mon-competitors-field--action">
                        <button type="button" class="btn btn-primary btn-sm w-100" id="start-analyse-region">
                            <i class="bi bi-play-fill me-1" aria-hidden="true"></i>{{ __('Analyse') }}
                        </button>
                    </div>
                </div>

                <div class="cabinet-mon-competitors-panel">
                    <div class="cabinet-mon-competitors-panel__head">
                        <div class="cabinet-mon-competitors-panel__title">
                            <i class="bi bi-people me-1" aria-hidden="true"></i>
                            {{ __('My competitors') }}
                            <span class="badge text-bg-secondary ms-1" id="counter-competitors" data-mon-competitors-count>{{ count($competitors) }}</span>
                        </div>
                        <div class="cabinet-mon-competitors-panel__actions">
                            @if(count($competitors) > 0)
                            <a class="btn btn-success btn-sm" id="compare-competitors-positions"
                               href="{{ route('monitoring.competitors.positions', $project->id) }}">
                                <i class="bi bi-bar-chart-line me-1" aria-hidden="true"></i>{{ __('Comparison with competitors') }}
                            </a>
                            @endif
                            <button type="button" class="btn btn-outline-primary btn-sm" id="add-competitor-manual"
                                    data-bs-toggle="modal" data-bs-target="#addCompetitorManualModal">
                                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('Monitoring competitors add manual') }}
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="searchCompetitors"
                                    title="{{ __('Monitoring competitors suggest disabled hint') }}">
                                <i class="bi bi-magic me-1" aria-hidden="true"></i>{{ __('Monitoring competitors suggest from top') }}
                            </button>
                            @if(count($competitors) === 0)
                            <a class="btn btn-outline-secondary btn-sm" id="compare-competitors-positions"
                               href="{{ route('monitoring.competitors.positions', $project->id) }}">
                                <i class="bi bi-bar-chart-line me-1" aria-hidden="true"></i>{{ __('Comparison with competitors') }}
                            </a>
                            @endif
                        </div>
                    </div>
                    <div class="cabinet-mon-competitors-panel__chips" id="competitors-chips">
                        @forelse($competitors as $competitor)
                            <span class="cabinet-mon-competitors-chip">
                                <span class="cabinet-mon-competitors-chip__domain">{{ $competitor['url'] }}</span>
                                <button type="button" class="btn btn-sm remove-competitor-button cabinet-mon-competitors-chip__remove"
                                        data-id="{{ $competitor['id'] }}"
                                        data-name="{{ $competitor['url'] }}"
                                        data-bs-toggle="modal"
                                        data-bs-target="#removeCompetitor"
                                        title="{{ __('Remove') }}">
                                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                                </button>
                            </span>
                        @empty
                            <span class="cabinet-mon-competitors-panel__empty text-secondary small" id="competitors-chips-empty">
                                {{ __('Monitoring competitors chips empty') }}
                            </span>
                        @endforelse
                    </div>
                </div>

                <div class="cabinet-mon-competitors-dt-bar d-none" id="competitors-dt-bar">
                    <div id="competitors-dt-filter"></div>
                    <div id="competitors-dt-length"></div>
                </div>

                <div class="cabinet-mon-competitors-workspace__body" id="competitors-workspace-body">
                    <div class="cabinet-mon-competitors-empty" id="competitors-empty-state">
                        <div class="cabinet-mon-competitors-empty__icon" aria-hidden="true">
                            <i class="bi bi-1-circle"></i>
                        </div>
                        <h3 class="cabinet-mon-competitors-empty__title h6">{{ __('Monitoring competitors empty title') }}</h3>
                        <p class="cabinet-mon-competitors-empty__text text-secondary">{{ __('Monitoring competitors empty text') }}</p>
                    </div>

                    <div class="cabinet-mon-competitors-ready d-none" id="competitors-ready-state">
                        <div class="cabinet-mon-competitors-ready__icon" aria-hidden="true">
                            <i class="bi bi-bar-chart-line"></i>
                        </div>
                        <h3 class="cabinet-mon-competitors-ready__title h5 mb-2">{{ __('Monitoring competitors ready title') }}</h3>
                        <p class="cabinet-mon-competitors-ready__text text-secondary mb-3">
                            {{ __('Monitoring competitors ready text') }}
                            <span class="fw-semibold" id="competitors-ready-count">{{ count($competitors) }}</span>
                        </p>
                        <div class="cabinet-mon-competitors-ready__actions">
                            <a class="btn btn-success" id="compare-competitors-ready"
                               href="{{ route('monitoring.competitors.positions', $project->id) }}">
                                <i class="bi bi-bar-chart-line me-1" aria-hidden="true"></i>{{ __('Monitoring competitors next cta button') }}
                            </a>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="competitors-run-analysis-from-ready">
                                <i class="bi bi-play-fill me-1" aria-hidden="true"></i>{{ __('Monitoring competitors ready analyze') }}
                            </button>
                        </div>
                    </div>

                    <div class="cabinet-mon-competitors-loading d-none" id="competitors-loading" role="status" aria-live="polite">
                        @include('monitoring.partials.show.loader', ['label' => __('loading results')])
                        <span id="render-state" class="cabinet-mon-competitors-loading__text">{{ __('loading results') }}</span>
                    </div>

                    <div class="cabinet-mon-competitors-table-host d-none" id="tableBlock">
                        <div class="cabinet-mon-competitors-table-hint" id="competitors-table-hint" role="note">
                            <i class="bi bi-check2-square me-1" aria-hidden="true"></i>
                            {{ __('Monitoring competitors table hint') }}
                        </div>
                        <div class="table-responsive">
                            <table id="table" class="table table-hover table-sm mb-0 w-100 cabinet-mon-competitors-table">
                                <thead>
                                <tr>
                                    <th scope="col" class="cabinet-mon-competitors-col-check" title="{{ __('Monitoring competitors col check hint') }}">
                                        {{ __('Monitoring competitors col check') }}
                                    </th>
                                    <th scope="col">{{ __('Domain') }}</th>
                                    <th scope="col">{{ __('Search engines') }}</th>
                                    <th scope="col" class="cabinet-mon-competitors-metric-col" title="{{ __('Monitoring competitors col intersection hint') }}">
                                        {{ __('Monitoring competitors col intersection') }}
                                    </th>
                                    <th scope="col" class="cabinet-mon-competitors-metric-col" title="{{ __('In the TOP columns, you will see the result as a percentage of how many words fall into the TOP 3/5/10/30/100. The higher the percentage of phrases, the better. Thanks to grouping by regions and days, you will be able to see its dynamics in comparison with 30/90/180/365 days earlier, if the result for this period is in the system.') }}">
                                        {{ __('Monitoring competitors col top3') }}
                                    </th>
                                    <th scope="col" class="cabinet-mon-competitors-metric-col">{{ __('Monitoring competitors col top10') }}</th>
                                    <th scope="col" class="cabinet-mon-competitors-metric-col">{{ __('Monitoring competitors col top100') }}</th>
                                    <th scope="col" class="cabinet-mon-competitors-metric-col" title="{{ __('In this column, the average position on the search engine of a certain region/city. We consider it in the classical way: the sum of all positions divided by the number of words. Thanks to grouping by region and day, you will be able to see its dynamics. The closer the average value is to 1, the better.') }}">
                                        {{ __('Average position') }}
                                    </th>
                                    <th scope="col">{{ __('Visibility by selected regions') }}</th>
                                </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <div class="cabinet-mon-competitors-next d-none" id="competitors-next-cta" role="region"
                 aria-label="{{ __('Monitoring competitors next cta region') }}">
                <div class="cabinet-mon-competitors-next__inner">
                    <span class="cabinet-mon-competitors-next__text">
                        <i class="bi bi-check-circle-fill text-success me-1" aria-hidden="true"></i>
                        {{ __('Monitoring competitors next cta text') }}
                    </span>
                    <a class="btn btn-success btn-sm cabinet-mon-competitors-next__btn"
                       id="compare-competitors-sticky"
                       href="{{ route('monitoring.competitors.positions', $project->id) }}">
                        {{ __('Monitoring competitors next cta button') }}
                        <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
                    </a>
                    <button type="button" class="btn-close cabinet-mon-competitors-next__close"
                            id="competitors-next-cta-close" aria-label="{{ __('Close') }}"></button>
                </div>
            </div>
        </div>
    </div>

    <div id="toast-container" class="toast-container position-fixed top-0 end-0 p-3 info-message d-none" style="z-index: 1080;">
        <div class="toast align-items-center text-bg-info border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body toast-message"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="{{ __('Close') }}"></button>
            </div>
        </div>
    </div>

    <div class="modal fade" id="removeCompetitor" tabindex="-1" aria-labelledby="removeCompetitorLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="removeCompetitorLabel">{{ __('Monitoring competitors remove confirm title') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body">
                    {{ __('Monitoring competitors remove confirm body') }} <strong id="competitor-name"></strong>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
                    <button type="button" class="btn btn-danger" id="remove-competitor" data-bs-dismiss="modal">{{ __('Remove') }}</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addCompetitorManualModal" tabindex="-1" aria-labelledby="addCompetitorManualModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCompetitorManualModalLabel">{{ __('Monitoring competitors add manual title') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body">
                    <p class="text-secondary small">{{ __('Monitoring competitors add manual help') }}</p>
                    <label class="form-label fw-semibold" for="competitor-manual-input">{{ __('Domain') }}</label>
                    <textarea id="competitor-manual-input" class="form-control" rows="5"
                              placeholder="{{ __('Monitoring competitors add manual placeholder') }}"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="button" class="btn btn-primary" id="save-competitor-manual">
                        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('Add') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="competitorsModal" tabindex="-1" aria-labelledby="competitorsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="competitorsModalLabel">{{ __('Monitoring competitors suggest modal title') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body">
                    <p class="text-secondary small">{{ __('Monitoring competitors suggest modal help') }}</p>
                    <div class="mb-3">
                        <label for="competitors-textarea" class="form-label fw-semibold">{{ __('Your closest competitors') }}</label>
                        <textarea name="competitors-textarea" id="competitors-textarea" class="form-control" rows="8"></textarea>
                    </div>
                    <div class="mb-3">
                        <button class="btn btn-outline-secondary btn-sm mb-2" type="button" data-bs-toggle="collapse"
                                data-bs-target="#collapseIgnoredDomains" aria-expanded="false"
                                aria-controls="collapseIgnoredDomains">
                            {{ __('Ignored domains') }}
                        </button>
                        <div class="collapse" id="collapseIgnoredDomains">
                            <textarea id="ignored-domains" name="ignored-domains" class="form-control" rows="6" readonly>{{ $ignoredDomains }}</textarea>
                        </div>
                    </div>
                    <div>
                        <p class="fw-semibold small mb-1">{{ __('Domain') }}: {{ __('How many times have I met') }}</p>
                        <div id="competitors-list" class="small text-secondary"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="button" id="add-competitors" class="btn btn-primary" data-bs-dismiss="modal">{{ __('Add') }}</button>
                </div>
            </div>
        </div>
    </div>

    @slot('js')
        <script src="{{ asset('plugins/toastr/toastr.min.js') }}"></script>
        <script src="{{ asset('plugins/datatables/jquery.dataTables.min.js') }}"></script>
        @include('layouts.partials.vendor-datatables-js', ['bundle' => 'rb-min'])
        @include('monitoring.partials.smart-search-script')
        <script src="{{ asset('plugins/datatables-buttons/js/buttons.excel.min.js') }}"></script>
        <script src="{{ asset('plugins/datatables-buttons/js/buttons.html5.js') }}"></script>
        <script>
            window.cabinetMonCompetitorsConfig = {
                projectId: {{ (int) $project->id }},
                initialCompetitorsCount: {{ count($competitors) }},
                countQuery: {{ (int) $countQuery }},
                tableColCount: 9,
                suggestLimit: {{ (int) config('cabinet-monitoring.competitors_suggest_limit', 10) }},
                ignoredDomainsExtra: @json(config('cabinet-monitoring.competitors_ignored_domains', [])),
                csrf: @json(csrf_token()),
                routes: {
                    addCompetitor: @json(route('monitoring.add.competitor')),
                    removeCompetitor: @json(route('monitoring.remove.competitor')),
                    addCompetitors: @json(route('monitoring.add.competitors')),
                    getCompetitors: @json(route('monitoring.get.competitors')),
                    getCompetitorsDomain: @json(route('monitoring.get.competitors.domain')),
                    competitorsInfo: @json(url('/monitoring-competitors/' . $project->id)),
                    competitorsArray: @json(url('/monitoring/get-competitors-array/' . $project->id)),
                    waitResult: @json(url('/monitoring/wait-result')),
                },
                i18n: {
                    addConfirm: @json(__('Are you going to add the domain')),
                    inCompetitors: @json(__('in competitors')),
                    removeConfirm: @json(__('Are you going to remove the domain')),
                    fromCompetitors: @json(__('from competitors')),
                    inProgress: @json(__('In progress')),
                    inQueue: @json(__('In queue')),
                    renderData: @json(__('Render data')),
                    newScanToast: @json(__('New withdrawals of positions were discovered.') . ' ' . __('The analysis of fresh data has been launched.')),
                    yourWebsite: @json(__('Your website')),
                    search: @json(__('Search')),
                    empty: @json(__('Empty')),
                    phrase: @json(__('Phrase')),
                    yandex: @json(__('Yandex')),
                    google: @json(__('Google')),
                    show: @json(__('show')),
                    chipsEmpty: @json(__('Monitoring competitors chips empty')),
                    added: @json(__('Monitoring competitors added')),
                    addError: @json(__('Monitoring competitors add error')),
                    suggestDisabled: @json(__('Monitoring competitors suggest disabled hint')),
                    suggestStarting: @json(__('Monitoring competitors suggest starting')),
                    coachAnalyzeTitle: @json(__('Monitoring competitors coach analyze title')),
                    coachAnalyzeBody: @json(__('Monitoring competitors coach analyze body')),
                    coachPickTitle: @json(__('Monitoring competitors coach pick title')),
                    coachPickBody: @json(__('Monitoring competitors coach pick body')),
                    coachCompareTitle: @json(__('Monitoring competitors coach compare title')),
                    coachCompareBody: @json(__('Monitoring competitors coach compare body')),
                    coachOk: @json(__('Got it')),
                    tableInfo: @json(__('Monitoring dt table info')),
                    tableInfoEmpty: @json(__('Monitoring dt table info empty')),
                    tableInfoFiltered: @json(__('Monitoring dt table info filtered')),
                },
            };
        </script>
        <script src="{{ asset('js/cabinet-monitoring-competitors.js') }}?v={{ @filemtime(public_path('js/cabinet-monitoring-competitors.js')) ?: time() }}"></script>
    @endslot
@endcomponent
