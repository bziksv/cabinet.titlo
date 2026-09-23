@component('component.card', [
    'title' => $project->name,
    'titleHtml' => '<span class="visually-hidden">' . e($project->name) . '</span>',
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('plugins/toastr/toastr.min.css') }}">
        @include('layouts.partials.vendor-datatables-css', ['bundle' => 'rb-css-editor'])
        <link rel="stylesheet" href="{{ asset('css/cabinet-monitoring-show.css') }}?v={{ @filemtime(public_path('css/cabinet-monitoring-show.css')) ?: time() }}">
    @endslot

    <div class="cabinet-mon-project-page cabinet-mon-groups-page" id="cabinet-mon-groups-root">
        @include('monitoring.partials.show.project-chrome', [
            'project' => $project,
            'activeModule' => 'groups',
            'showViewTabs' => false,
            'pageHint' => __('Monitoring groups page hint'),
        ])

        <div class="cabinet-mon-project-page__body">
            <section class="cabinet-mon-groups-workspace card" aria-labelledby="groups-workspace-title">
                <div class="cabinet-mon-groups-workspace__head">
                    <div class="cabinet-mon-groups-workspace__intro">
                        <h2 class="cabinet-mon-groups-workspace__title h6 mb-0" id="groups-workspace-title">
                            {{ __('Monitoring groups workspace title') }}
                        </h2>
                        <p class="cabinet-mon-groups-workspace__stats text-secondary small mb-0">
                            {{ __('Monitoring groups stats groups') }}:
                            <span class="fw-semibold text-body" id="groups-stats-groups">0</span>
                            <span class="text-secondary mx-1">·</span>
                            {{ __('Monitoring groups stats queries') }}:
                            <span class="fw-semibold text-body" id="groups-stats-queries">0</span>
                        </p>
                    </div>
                </div>

                <div class="cabinet-mon-groups-workspace__actions">
                    <div class="cabinet-mon-groups-workspace__action-btns" id="groups-dt-actions">
                        @if($canEdit || $canDelete)
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="groups-select-all">
                                {{ __('Monitoring groups select all') }}
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="groups-select-none">
                                {{ __('Monitoring groups clear selection') }}
                            </button>
                        @endif
                        @if($canCreate)
                            <button type="button" class="btn btn-primary btn-sm" id="groups-create-btn">
                                {{ __('Monitoring groups create button') }}
                            </button>
                        @endif
                        @if($canEdit)
                            <button type="button" class="btn btn-outline-primary btn-sm" id="groups-edit-selected-btn">
                                {{ __('Monitoring groups edit selected') }}
                            </button>
                        @endif
                    </div>
                    <p class="cabinet-mon-groups-workspace__hint mb-0">{{ __('Monitoring groups actions hint') }}</p>
                </div>

                <div class="cabinet-mon-groups-dt-bar">
                    <div id="groups-dt-filter"></div>
                </div>

                <div class="cabinet-mon-groups-table-host" id="groups-table-host">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0 w-100 cabinet-mon-groups-table" id="groups"></table>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <div class="modal fade" id="groupsFormModal" tabindex="-1" aria-labelledby="groupsFormModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="groupsFormModalTitle">{{ __('Monitoring groups edit title') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="groups-form-mode" value="edit">
                    <input type="hidden" id="groups-form-ids" value="">
                    <div class="mb-3">
                        <label class="form-label" for="groups-form-name">{{ __('Group') }}</label>
                        <input type="text" class="form-control" id="groups-form-name" autocomplete="off">
                        <div class="form-text">{{ __('Monitoring groups name hint') }}</div>
                        <div class="invalid-feedback" id="groups-form-name-error"></div>
                    </div>
                    <div class="mb-3 d-none" id="groups-form-move-wrap">
                        <label class="form-label" for="groups-form-move">{{ __('Monitoring groups move queries') }}</label>
                        <select class="form-select" id="groups-form-move">
                            <option value="0">{{ __('Monitoring groups move none') }}</option>
                        </select>
                    </div>
                    <div class="mb-0 d-none" id="groups-form-users-wrap">
                        <div class="form-label">{{ __('Users') }}</div>
                        <div id="groups-form-users" class="cabinet-mon-groups-form-users"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="button" class="btn btn-primary" id="groups-form-submit">{{ __('Update') }}</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="groupsDeleteModal" tabindex="-1" aria-labelledby="groupsDeleteModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="groupsDeleteModalTitle">{{ __('Monitoring groups delete title') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0" id="groups-delete-message">{{ __('Monitoring groups delete confirm one') }}</p>
                    <input type="hidden" id="groups-delete-id" value="">
                    <input type="hidden" id="groups-delete-name" value="">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="button" class="btn btn-danger" id="groups-delete-submit">{{ __('Delete') }}</button>
                </div>
            </div>
        </div>
    </div>

    @slot('js')
        <script src="{{ asset('plugins/toastr/toastr.min.js') }}"></script>
        <script src="{{ asset('plugins/datatables/jquery.dataTables.min.js') }}"></script>
        @include('layouts.partials.vendor-datatables-js', ['bundle' => 'rb-min-editor'])
        @include('monitoring.partials.smart-search-script')
        <script src="{{ asset('plugins/chart.js/3.9.1/chart.js') }}"></script>
        <script src="{{ asset('js/cabinet-monitoring-chart-scales.js') }}?v={{ @filemtime(public_path('js/cabinet-monitoring-chart-scales.js')) ?: time() }}"></script>
        <script>
            window.cabinetMonitoringChildChartsConfig = {
                chartsUrl: @json(url('/monitoring/charts')),
                i18n: {
                    childChartShow: @json(__('Monitoring child chart show')),
                    childChartHide: @json(__('Monitoring child chart hide')),
                    loadError: @json(__('Monitoring show chart load error')),
                },
            };
        </script>
        <script src="{{ asset('js/cabinet-monitoring-child-charts.js') }}?v={{ @filemtime(public_path('js/cabinet-monitoring-child-charts.js')) ?: time() }}"></script>
        <script>
            window.cabinetMonGroupsConfig = {
                projectId: {{ (int) $project->id }},
                canCreate: @json($canCreate),
                canEdit: @json($canEdit),
                canDelete: @json($canDelete),
                csrf: @json(csrf_token()),
                chartsUrl: @json(url('/monitoring/charts')),
                routes: {
                    list: @json(route('groups.index', $project->id)),
                    action: @json(route('groups.action', $project->id)),
                    childRows: @json(url('/monitoring/__PROJECT__/child-rows/get/__GROUP__')),
                },
                i18n: {
                    search: @json(__('Monitoring groups search')),
                    emptyTable: @json(__('Monitoring groups empty table')),
                    zeroRecords: @json(__('Monitoring groups zero records')),
                    expand: @json(__('Monitoring groups expand stats')),
                    colId: @json(__('ID')),
                    colGroup: @json(__('Groups')),
                    colQueries: @json(__('Queries')),
                    colCreated: @json(__('Added')),
                    colUsers: @json(__('Users')),
                    colActions: @json(__('Actions')),
                    openGroup: @json(__('Monitoring groups open')),
                    editGroup: @json(__('Edit')),
                    deleteGroup: @json(__('Delete')),
                    selectAll: @json(__('Monitoring groups select all')),
                    selectNone: @json(__('Monitoring groups clear selection')),
                    createButton: @json(__('Monitoring groups create button')),
                    createTitle: @json(__('Monitoring groups create title')),
                    createSubmit: @json(__('Create')),
                    editTitle: @json(__('Monitoring groups edit title')),
                    editSubmit: @json(__('Update')),
                    editSelected: @json(__('Monitoring groups edit selected')),
                    selectRowsFirst: @json(__('Monitoring groups select rows first')),
                    deleteTitle: @json(__('Monitoring groups delete title')),
                    deleteSubmit: @json(__('Delete')),
                    deleteConfirm: @json(__('Monitoring groups delete confirm')),
                    deleteConfirmOne: @json(__('Monitoring groups delete confirm one')),
                    groupLabel: @json(__('Group')),
                    groupFieldInfo: @json(__('Monitoring groups name hint')),
                    moveQueriesLabel: @json(__('Monitoring groups move queries')),
                    moveNone: @json(__('Monitoring groups move none')),
                    usersLabel: @json(__('Users')),
                    cancel: @json(__('Cancel')),
                    childChartShow: @json(__('Monitoring child chart show')),
                    childChartHide: @json(__('Monitoring child chart hide')),
                    loadError: @json(__('Monitoring show chart load error')),
                    saved: @json(__('Saved')),
                },
            };
        </script>
        <script src="{{ asset('js/cabinet-monitoring-groups.js') }}?v={{ @filemtime(public_path('js/cabinet-monitoring-groups.js')) ?: time() }}"></script>
    @endslot
@endcomponent
