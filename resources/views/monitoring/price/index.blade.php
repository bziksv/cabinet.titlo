@component('component.card', [
    'title' => $project->name,
    'titleHtml' => '<span class="visually-hidden">' . e($project->name) . '</span>',
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('plugins/toastr/toastr.min.css') }}">
        @include('layouts.partials.vendor-datatables-css', ['bundle' => 'rb-css'])
        <link rel="stylesheet" href="{{ asset('css/cabinet-monitoring-show.css') }}?v={{ @filemtime(public_path('css/cabinet-monitoring-show.css')) ?: time() }}">
    @endslot

    <div class="cabinet-mon-project-page cabinet-mon-prices-page" id="cabinet-mon-prices-root">
        @include('monitoring.partials.show.project-chrome', [
            'project' => $project,
            'activeModule' => 'prices',
            'showViewTabs' => false,
            'pageHint' => __('Monitoring prices page hint'),
        ])

        <div class="cabinet-mon-project-page__body">
            <section class="cabinet-mon-prices-workspace card" aria-labelledby="prices-workspace-title">
                <div class="cabinet-mon-prices-workspace__head">
                    <div class="cabinet-mon-prices-workspace__region">
                        <label class="cabinet-mon-prices-workspace__label" for="select-region">{{ __('Region') }}</label>
                        <select id="select-region" class="form-select form-select-sm">
                            @foreach($regions as $region)
                                <option value="{{ $region['id'] }}">{{ $region['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    @can('update_budget_monitoring')
                        <div class="cabinet-mon-prices-workspace__budget">
                            <label class="cabinet-mon-prices-workspace__label" for="project-budget">{{ __('Budget') }}</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="bi bi-wallet2" aria-hidden="true"></i></span>
                                <input type="number" min="0" step="0.01" id="project-budget" class="form-control"
                                       value="{{ $project->budget }}" placeholder="{{ __('Projects budget') }}">
                                <button type="button" class="btn btn-primary" id="save-budget">{{ __('Save') }}</button>
                            </div>
                        </div>
                    @endcan
                </div>

                @if($canEditPrice)
                    <div class="cabinet-mon-prices-workspace__actions">
                        <div class="cabinet-mon-prices-workspace__action-btns">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="prices-select-all">
                                {{ __('Monitoring prices select all page') }}
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="prices-select-none">
                                {{ __('Monitoring prices clear selection') }}
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="prices-bulk-edit" disabled
                                    data-bs-toggle="modal" data-bs-target="#pricesBulkEditModal">
                                {{ __('Monitoring prices bulk edit') }}
                                <span class="badge text-bg-secondary ms-1" id="prices-selected-count">0</span>
                            </button>
                        </div>
                        <p class="cabinet-mon-prices-workspace__hint mb-0">{{ __('Monitoring prices inline hint') }}</p>
                    </div>
                @endif

                <div class="cabinet-mon-prices-dt-bar">
                    <div id="prices-dt-filter"></div>
                    <div id="prices-dt-length"></div>
                </div>

                <div class="cabinet-mon-prices-table-host" id="cabinet-mon-prices-table-host">
                    <div class="cabinet-mon-prices-table-host__loader d-none" id="cabinetMonPricesLoader" role="status" aria-live="polite">
                        @include('monitoring.partials.show.loader', ['label' => __('Monitoring prices table loading')])
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0 w-100 cabinet-mon-prices-table" id="prices"></table>
                    </div>
                </div>
            </section>
        </div>
    </div>

    @if($canEditPrice)
        <div class="modal fade" id="pricesBulkEditModal" tabindex="-1" aria-labelledby="pricesBulkEditModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="pricesBulkEditModalLabel">{{ __('Monitoring prices bulk edit title') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-secondary small">{{ __('Monitoring prices bulk edit hint') }}</p>
                        <div class="row g-3" id="prices-bulk-fields">
                            @foreach(['top1' => 1, 'top3' => 3, 'top5' => 5, 'top10' => 10, 'top20' => 20, 'top50' => 50, 'top100' => 100] as $field => $top)
                                <div class="col-md-4 col-6">
                                    <label class="form-label" for="bulk-{{ $field }}">TOP {{ $top }}</label>
                                    <input type="number" min="0" step="0.01" class="form-control form-control-sm prices-bulk-field"
                                           id="bulk-{{ $field }}" data-field="{{ $field }}" placeholder="—">
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="button" class="btn btn-primary" id="prices-bulk-save">
                            <i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ __('Save') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @slot('js')
        <script src="{{ asset('plugins/toastr/toastr.min.js') }}"></script>
        <script src="{{ asset('plugins/datatables/jquery.dataTables.min.js') }}"></script>
        @include('layouts.partials.vendor-datatables-js', ['bundle' => 'rb-min'])
        @include('monitoring.partials.smart-search-script')
        <script>
            window.cabinetMonPricesConfig = {
                projectId: {{ (int) $project->id }},
                canEditPrice: @json($canEditPrice),
                canEditBudget: @json($canEditBudget),
                csrf: @json(csrf_token()),
                routes: {
                    list: @json(route('prices.index', $project->id)),
                    action: @json(route('prices.action', $project->id)),
                    budget: @json(route('prices.budget', $project->id)),
                },
                priceFields: ['top1', 'top3', 'top5', 'top10', 'top20', 'top50', 'top100'],
                i18n: {
                    query: @json(__('Phrase')),
                    search: @json(__('Search')),
                    saved: @json(__('Saved')),
                    saveError: @json(__('Monitoring prices save error')),
                    selectRows: @json(__('Monitoring prices select rows first')),
                    bulkEmpty: @json(__('Monitoring prices bulk empty')),
                },
            };
        </script>
        <script src="{{ asset('js/cabinet-monitoring-prices.js') }}?v={{ @filemtime(public_path('js/cabinet-monitoring-prices.js')) ?: time() }}"></script>
    @endslot
@endcomponent
