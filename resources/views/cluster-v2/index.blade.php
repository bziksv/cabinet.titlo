@component('component.card', [
    'title' => __('Cluster'),
    'titleHtml' => e(__('Cluster')) . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-cluster'])->render(),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('plugins/keyword-generator/css/font-awesome-4.7.0/css/font-awesome.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/common/css/datatable.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/toastr/toastr.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.min.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/cabinet-cluster-v2.css') }}?v={{ @filemtime(public_path('css/cabinet-cluster-v2.css')) ?: time() }}">
    @endslot

    <div class="cabinet-cluster-v2-page" id="cabinet-cluster-v2-root">
        @include('cluster.partials.module-nav', ['active' => 'analyzer', 'admin' => $admin])

        <div id="toast-container" class="toast-top-right success-message">
            <div class="toast toast-success" aria-live="polite" style="display:none;">
                <div class="toast-message success-msg"></div>
            </div>
        </div>
        <div id="toast-container" class="toast-top-right error-message" style="z-index:99999;">
            <div class="toast toast-error" aria-live="assertive" style="display:none;">
                <div class="toast-message error-msg">{{ __('An unexpected error has occurred, please contact the administrator') }}</div>
            </div>
        </div>
        <div id="toast-container" class="toast-top-right success-message history-notification" style="display:none;">
            <div class="toast toast-info" aria-live="polite">
                <div class="toast-message">
                    {{ __('You can close the page or start a new analysis, when your results are ready, you can view them') }}
                    <a href="{{ route('cluster.projects') }}" target="_blank"><u>{{ __('here') }}</u></a>
                </div>
            </div>
        </div>

        <nav class="cabinet-cluster-v2-steps-nav mb-4" aria-label="{{ __('Cluster analysis steps') }}">
            <ol class="cabinet-cluster-v2-steps-nav__list">
                <li class="cabinet-cluster-v2-steps-nav__item is-active"><span>1</span> {{ __('Phrases') }}</li>
                <li class="cabinet-cluster-v2-steps-nav__item"><span>2</span> {{ __('Region and clustering') }}</li>
                <li class="cabinet-cluster-v2-steps-nav__item"><span>3</span> {{ __('Options and launch') }}</li>
            </ol>
        </nav>

        <div class="cabinet-cluster-v2-steps">
            <section class="cabinet-cluster-v2-step" id="clv2-step-1">
                @include('cluster-v2.partials.step-head', [
                    'num' => 1,
                    'title' => __('Key phrases'),
                    'desc' => __('One phrase per line. Empty lines are ignored.'),
                ])
                <div class="cabinet-cluster-v2-step__body">
                    <div class="cabinet-cluster-v2-step__toolbar">
                        <label class="form-label mb-0 visually-hidden" for="clv2-phrases">{{ __('Key phrases') }}</label>
                        @if(!empty($clusterV2PresetKawe))
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="clv2-preset-kawe">
                                Пресет Демо
                            </button>
                        @endif
                        <span class="text-muted small cabinet-cluster-v2-step__phrase-count">{{ __('Count phrases') }}: <strong id="clv2-phrase-count">0</strong></span>
                    </div>
                    <textarea id="clv2-phrases" class="form-control cabinet-cluster-v2-phrases" rows="10" placeholder="{{ __('One phrase per line') }}"></textarea>
                </div>
            </section>

            <section class="cabinet-cluster-v2-step" id="clv2-step-2">
                @include('cluster-v2.partials.step-head', [
                    'num' => 2,
                    'title' => __('Region and clustering'),
                    'desc' => __('Choose region, clustering strictness and analysis mode.'),
                ])
                <div class="cabinet-cluster-v2-step__body">
                    <div class="cabinet-cluster-v2-mode-switch btn-group w-100 mb-3" role="group">
                        <button type="button" class="btn btn-primary" data-mode="classic" id="clv2-mode-classic">{{ __('Classic mode') }}</button>
                        <button type="button" class="btn btn-outline-primary" data-mode="professional" id="clv2-mode-pro">{{ __('Pro mode') }}</button>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="clv2-region">{{ __('Region') }}</label>
                            <div class="cabinet-cluster-v2-region-field">
                                @include('cluster-v2.partials.region-select', [
                                    'name' => 'region',
                                    'id' => 'clv2-region',
                                    'selectedRegion' => $clusterV2DefaultRegion,
                                ])
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="clv2-clustering-level">
                                {{ __('clustering level') }}
                                <i class="fa fa-question-circle text-secondary ms-1"
                                   data-bs-toggle="tooltip"
                                   data-bs-placement="top"
                                   title="{{ __('the higher the clustering level, the more groups you will get') }}"
                                   aria-hidden="true"></i>
                            </label>
                            {!! Form::select('clustering_level', [
                                'light' => __('light - 40%'),
                                'soft' => __('soft - 50%'),
                                'pre-hard' => __('pre-hard - 60%'),
                                'hard' => __('hard - 70%'),
                            ], $config_classic->clustering_level, ['class' => 'form-select', 'id' => 'clv2-clustering-level']) !!}
                        </div>
                    </div>
                    <div id="clv2-pro-panel" class="cabinet-cluster-v2-pro-block d-none mt-3">
                        <p class="small text-secondary mb-2">{{ __('Pro settings') }}</p>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label" for="clv2-top">{{ __('TOP') }}</label>
                                {!! Form::select('count', ['10'=>10,'20'=>20,'30'=>30,'40'=>40,'50'=>50], $config->count ?? 30, ['class'=>'form-select','id'=>'clv2-top']) !!}
                            </div>
                            <div class="col-md-8">
                                <label class="form-label" for="clv2-ignored-domains">{{ __('Ignored domains') }}</label>
                                <textarea id="clv2-ignored-domains" class="form-control form-control-sm" rows="2">{{ $config->ignored_domains }}</textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="clv2-ignored-words">{{ __('Ignored words') }}</label>
                                <textarea id="clv2-ignored-words" class="form-control form-control-sm" rows="2">{{ $config->ignored_words }}</textarea>
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="clv2-brut-force">
                                    <label class="form-check-label" for="clv2-brut-force">{{ __('Additional bulkhead') }}</label>
                                </div>
                                <div id="clv2-brut-force-fields" class="row g-2 mt-2 d-none">
                                    <div class="col-md-4">
                                        <label class="form-label" for="clv2-gain-factor">{{ __('Gain factor(%)') }}</label>
                                        <input type="number" class="form-control form-control-sm" id="clv2-gain-factor" value="{{ $config->gain_factor }}">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="clv2-brut-force-count">{{ __('Minimum cluster size for re-bulkhead') }}</label>
                                        <input type="number" class="form-control form-control-sm" id="clv2-brut-force-count" value="{{ $config->brut_force_count }}">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="clv2-reduction-ratio">{{ __('Minimum multiplier') }}</label>
                                        {!! Form::select('reductionRatio', ['pre-hard'=>'pre-hard','soft'=>'soft'], $config->reduction_ratio, ['class'=>'form-select form-select-sm','id'=>'clv2-reduction-ratio']) !!}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="cabinet-cluster-v2-step" id="clv2-step-3">
                @include('cluster-v2.partials.step-head', [
                    'num' => 3,
                    'title' => __('Options and launch'),
                    'desc' => __('Frequency, relevance, saving — then start analysis.'),
                ])
                <div class="cabinet-cluster-v2-step__body">
                    <div class="row g-4">
                        <div class="col-lg-7">
                            <div class="cabinet-cluster-v2-options">
                                <section class="cabinet-cluster-v2-option-block">
                                    <h3 class="cabinet-cluster-v2-option-block__title">{{ __('Frequency analysis') }}</h3>
                                    <div class="cabinet-cluster-v2-option-block__body cabinet-cluster-v2-check-row">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="clv2-search-base">
                                            <label class="form-check-label" for="clv2-search-base">{{ __('Base frequency analysis') }}</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="clv2-search-phrases">
                                            <label class="form-check-label" for="clv2-search-phrases">{{ __('Phrase frequency analysis') }}</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="clv2-search-target">
                                            <label class="form-check-label" for="clv2-search-target">{{ __('Accurate frequency analysis') }}</label>
                                        </div>
                                    </div>
                                </section>

                                <section class="cabinet-cluster-v2-option-block">
                                    <h3 class="cabinet-cluster-v2-option-block__title">{{ __('Relevance and domain') }}</h3>
                                    <div class="cabinet-cluster-v2-option-block__body">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label" for="clv2-domain">{{ __('Domain') }}</label>
                                                <textarea id="clv2-domain" class="form-control form-control-sm" rows="3" placeholder="{{ __('site.ru or https://site.ru') }}"></textarea>
                                                <p class="form-text mb-0">{{ __('https:// is added automatically if the protocol is omitted.') }}</p>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label" for="clv2-comment">{{ __('Comment') }}</label>
                                                <textarea id="clv2-comment" class="form-control form-control-sm" rows="3"></textarea>
                                            </div>
                                        </div>
                                        <div class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" id="clv2-search-relevance">
                                            <label class="form-check-label" for="clv2-search-relevance">{{ __('Select a relevant page for the domain') }}</label>
                                        </div>
                                        <p class="form-text mb-0 mt-1">{{ __('Relevance page selection uses Yandex search results only.') }}</p>
                                    </div>
                                </section>

                                <section class="cabinet-cluster-v2-option-block">
                                    <h3 class="cabinet-cluster-v2-option-block__title">{{ __('Saving') }}</h3>
                                    <div class="cabinet-cluster-v2-option-block__body">
                                        <label class="form-label" for="clv2-save">{{ __('Save results') }}</label>
                                        {!! Form::select('save', ['1'=>__('Save'),'0'=>__('Do not save')], $config_classic->save_results, ['class'=>'form-select','id'=>'clv2-save']) !!}
                                        <div class="mt-2" id="clv2-telegram-block">
                                            <label class="form-label" for="clv2-send-message">{{ __('Notify in a telegram upon completion') }}</label>
                                            {!! Form::select('sendMessage', ['0' => __('No'), '1' => __('Yes')], $config_classic->send_message ? '1' : '0', ['class' => 'form-select form-select-sm', 'id' => 'clv2-send-message']) !!}
                                            <div id="clv2-telegram-hint" class="alert alert-warning py-2 px-3 mt-2 mb-0 small{{ ($telegramConnected ?? false) ? ' d-none' : '' }}">
                                                {{ __('Subscribe to notifications in Telegram first.') }}
                                                <a href="{{ route('profile.index') }}" target="_blank" class="alert-link">{{ __('Connect Telegram bot') }}</a>
                                            </div>
                                        </div>
                                    </div>
                                </section>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <aside class="cabinet-cluster-v2-run-panel">
                                <dl class="cabinet-cluster-v2-stats mb-3">
                                    <div class="cabinet-cluster-v2-stats__row">
                                        <dt>{{ __('Count phrases') }}</dt>
                                        <dd id="clv2-stats-phrases">0</dd>
                                    </div>
                                    <div class="cabinet-cluster-v2-stats__row">
                                        <dt>{{ __('Limits multiplier') }}</dt>
                                        <dd id="clv2-stats-mult">×1</dd>
                                    </div>
                                    <div class="cabinet-cluster-v2-stats__row cabinet-cluster-v2-stats__row--total">
                                        <dt>{{ __('It will be written off') }}</dt>
                                        <dd><span id="clv2-limit-cost">0</span> {{ __('limits') }}</dd>
                                    </div>
                                </dl>
                                <div class="mb-3">
                                    <div class="cabinet-cluster-v2-chips-label text-muted small mb-1">{{ __('Selected options') }}</div>
                                    <div id="clv2-option-chips" class="cabinet-cluster-v2-chips">
                                        <span class="badge text-bg-light text-dark border">{{ __('Clustering only') }}</span>
                                    </div>
                                </div>
                                <div id="clv2-progress-wrap" class="cabinet-cluster-v2-progress mb-3 d-none" aria-live="polite">
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span id="clv2-progress-label">{{ __('Analysis in progress') }}</span>
                                        <span id="clv2-total-phrases" class="text-muted"></span>
                                    </div>
                                    <div class="progress" role="progressbar">
                                        <div id="clv2-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" style="width:12%"></div>
                                    </div>
                                    <span id="rendered-clusters" class="visually-hidden">0</span>
                                </div>
                                <button type="button" class="btn btn-primary btn-lg w-100" id="clv2-start">{{ __('Analyse') }}</button>
                                <p class="form-text mt-2 mb-0">{{ __('Analysis runs in the background; keep this tab open for instant results.') }}</p>
                            </aside>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        @include('cluster-v2.partials.admin-debug-log', ['admin' => $admin ?? false])

        <section id="cabinet-cluster-v2-results" class="cabinet-cluster-v2-results-wrap mt-4 d-none" aria-live="polite">
            <div class="card cabinet-cluster-v2-results-card shadow-sm">
                <div class="card-header cabinet-cluster-v2-results-head d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
                    <div>
                        <h3 class="h5 mb-0">{{ __('Cluster table') }}</h3>
                        <p class="small text-muted mb-0 mt-1" id="clv2-results-meta"></p>
                    </div>
                    <div id="files-downloads" class="cabinet-cluster-v2-results-downloads d-flex flex-wrap gap-1"></div>
                </div>
                <div class="card-body p-0">
                    <div id="result-table" class="cabinet-cluster-v2-results-scroll table-responsive" style="display:none;">
                        <table id="clusters-table" class="table table-sm mb-0 cabinet-cluster-v2-clusters-table">
                            <thead class="visually-hidden">
                            <tr>
                                <th>{{ __('Clusters') }}</th>
                                <th>{{ __('Competitors') }}</th>
                            </tr>
                            </thead>
                            <tbody id="clusters-table-tbody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        @include('cluster-v2.partials.relevance-modal')
        <textarea id="hiddenForCopy" style="display:none;"></textarea>
    </div>

    @slot('js')
        <script>
            window.cabinetClusterV2 = {
                adminDebug: @json(!empty($admin)),
                routes: {
                    startProgress: @json(route('start.cluster.progress')),
                    analyse: @json(route('analysis.cluster')),
                    progress: @json(url('/get-cluster-progress')),
                    telegramStatus: @json(route('cluster.telegram.status')),
                    profile: @json(route('profile.index')),
                    regions: @json(route('cluster.regions')),
                    getClusterRequest: @json(route('get.cluster.request')),
                },
                defaultRegion: @json($clusterV2DefaultRegion),
                defaults: {
                    classic: @json($clusterV2DefaultsClassic),
                    pro: @json($clusterV2DefaultsPro),
                },
                @if(!empty($clusterV2PresetKawe))
                presets: {
                    kawe: @json($clusterV2PresetKawe),
                },
                @endif
                i18n: {
                    classicMode: @json(__('Classic mode')),
                    proMode: @json(__('Pro mode')),
                    freqBase: @json(__('Base')),
                    freqPhrase: @json(__('Phrasal')),
                    freqExact: @json(__('Exact frequency')),
                    relevance: @json(__('Relevance')),
                    clusteringOnly: @json(__('Clustering only')),
                    phrasesRequired: @json(__('Add key phrases')),
                    genericError: @json(__('An unexpected error has occurred, please contact the administrator')),
                    progressError: @json(__('Progress polling failed')),
                    started: @json(__('Analysis started…')),
                    queue: @json(__('In queue')),
                    waitingQueue: @json('Ожидание воркера'),
                    rendering: @json(__('Render data')),
                    historyHint: @json(__('The analysis has been successfully launched, the results will be automatically added to the table')),
                    telegramRequired: @json(__('Subscribe to notifications in Telegram first.')),
                    connectTelegram: @json(__('Connect Telegram bot')),
                    regionPlaceholder: @json(__('Search city or region')),
                    regionSearchMin: @json(__('Enter at least 1 character to search')),
                    regionNotFound: @json(__('No regions found')),
                    regionSearching: @json(__('Searching…')),
                    copyUrls: @json('Копировать URL'),
                    viewLinks: @json(__('View links phrases')),
                    resultsMeta: @json('Кластеров: :clusters · Фраз: :phrases'),
                    freqZeroHint: @json('Частотность 0: проверьте локальный queue worker (scripts/dev-cluster-queue.sh) и Wordstat New в XMLRiver. Перезапустите анализ после правки.'),
                    presetApplied: @json('Пресет Демо применён'),
                    projectLoaded: @json('Параметры сохранённого проекта подставлены в форму'),
                },
            };
        </script>
        <script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>
        <script src="{{ asset('js/cabinet-select2-defaults.js') }}"></script>
        <script src="{{ asset('plugins/cluster/js/common_v2.min.js') }}"></script>
        <script src="{{ asset('plugins/cluster/js/render-result-table_v2.min.js') }}"></script>
        <script src="{{ asset('plugins/datatables/jquery.dataTables.min.js') }}"></script>
        @include('layouts.partials.vendor-datatables-js', ['bundle' => 'rb-min'])
        <script src="{{ asset('js/cabinet-cluster-v2.js') }}?v={{ @filemtime(public_path('js/cabinet-cluster-v2.js')) ?: time() }}"></script>
    @endslot
@endcomponent
