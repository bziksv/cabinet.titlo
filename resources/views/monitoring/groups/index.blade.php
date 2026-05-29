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

                @if($canCreate || $canEdit)
                    <div class="cabinet-mon-groups-workspace__actions">
                        <div class="cabinet-mon-groups-workspace__action-btns" id="groups-dt-actions"></div>
                        <p class="cabinet-mon-groups-workspace__hint mb-0">{{ __('Monitoring groups actions hint') }}</p>
                    </div>
                @endif

                <div class="cabinet-mon-groups-dt-bar">
                    <div id="groups-dt-filter"></div>
                </div>

                <div class="cabinet-mon-groups-table-host" id="groups-table-host">
                    <div class="cabinet-mon-groups-table-host__loader d-none" id="groupsLoader" role="status" aria-live="polite">
                        @include('monitoring.partials.show.loader', ['label' => __('Monitoring groups table loading')])
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0 w-100 cabinet-mon-groups-table" id="groups"></table>
                    </div>
                </div>
            </section>
        </div>
    </div>

    @slot('js')
        <script src="{{ asset('plugins/toastr/toastr.min.js') }}"></script>
        <script src="{{ asset('plugins/datatables/jquery.dataTables.min.js') }}"></script>
        @include('layouts.partials.vendor-datatables-js', ['bundle' => 'rb-min-editor'])
        @include('monitoring.partials.smart-search-script')
        <script>
            window.cabinetMonGroupsConfig = {
                projectId: {{ (int) $project->id }},
                canCreate: @json($canCreate),
                canEdit: @json($canEdit),
                canDelete: @json($canDelete),
                csrf: @json(csrf_token()),
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
                    deleteTitle: @json(__('Monitoring groups delete title')),
                    deleteSubmit: @json(__('Delete')),
                    deleteConfirm: @json(__('Monitoring groups delete confirm')),
                    deleteConfirmOne: @json(__('Monitoring groups delete confirm one')),
                    groupLabel: @json(__('Group')),
                    groupFieldInfo: @json(__('Monitoring groups name hint')),
                    moveQueriesLabel: @json(__('Monitoring groups move queries')),
                    usersLabel: @json(__('Users')),
                    multiTitle: @json(__('Monitoring groups multi title')),
                    multiInfo: @json(__('Monitoring groups multi info')),
                    multiRestore: @json(__('Monitoring groups multi restore')),
                    multiNoMulti: @json(__('Monitoring groups multi no multi')),
                },
            };
        </script>
        <script src="{{ asset('js/cabinet-monitoring-groups.js') }}?v={{ @filemtime(public_path('js/cabinet-monitoring-groups.js')) ?: time() }}"></script>
    @endslot
@endcomponent
