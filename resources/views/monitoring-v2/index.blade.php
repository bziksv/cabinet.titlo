@component('component.card', [
    'title' => __('Monitoring position'),
    'titleHtml' => e(__('Monitoring position')) . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-monitoring'])->render(),
])
    @slot('css')
        @include('layouts.partials.vendor-datatables-css', ['bundle' => 'monitoring-index'])
        <link rel="stylesheet" href="{{ asset('plugins/toastr/toastr.min.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.min.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/cabinet-monitoring-v2.css') }}?v={{ @filemtime(public_path('css/cabinet-monitoring-v2.css')) ?: time() }}">
    @endslot

    <div class="cabinet-mon-v2-page cabinet-mon-v2-page--shell" id="cabinet-mon-v2-root" data-ui-build="3.2">
        <span class="cabinet-mon-v2-build-tag" title="UI build">v{{ config('cabinet-monitoring.version') }}</span>

        @include('monitoring-v2.partials.module-nav', ['isMonitoringAdmin' => $isMonitoringAdmin])

        @include('partials.cabinet-telegram-notify-notice', ['extraClass' => 'mb-0'])

        @include('monitoring-v2.partials.portfolio', ['count' => $count])

        @include('monitoring-v2.partials.workspace', [
            'count' => $count,
            'statusOptions' => $statusOptions,
            'listColumns' => $listColumns ?? [],
        ])

        @if($isMonitoringAdmin)
            <div class="cabinet-mon-v2-admin-strip text-secondary small">
                <i class="bi bi-shield-check me-1" aria-hidden="true"></i>{{ __('Monitoring v2 admin strip') }}
            </div>
            @include('monitoring-v2.partials.admin-debug-log', ['isMonitoringAdmin' => $isMonitoringAdmin])
        @endif
    </div>

    @include('monitoring.keywords.modal.main')

    @slot('js')
        <script src="{{ asset('plugins/datatables/jquery.dataTables.min.js') }}"></script>
        @include('layouts.partials.vendor-datatables-js', ['bundle' => 'monitoring-index'])
        <script src="{{ asset('plugins/chart.js/3.9.1/chart.js') }}"></script>
        <script src="{{ asset('plugins/toastr/toastr.min.js') }}"></script>
        <script src="{{ asset('plugins/bootstrap-modal-form-templates/bootstrap-modal-form-templates.js') }}"></script>
        <script src="{{ asset('plugins/moment/moment-with-locales.min.js') }}"></script>
        <script src="{{ asset('plugins/papaparse/papaparse.min.js') }}"></script>
        <script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>
        <script src="{{ asset('plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js') }}"></script>
        @php
            $monV2DashBuckets = [
                __('Monitoring v2 dash bucket 0 20'),
                __('Monitoring v2 dash bucket 20 40'),
                __('Monitoring v2 dash bucket 40 60'),
                __('Monitoring v2 dash bucket 60 80'),
                __('Monitoring v2 dash bucket 80 100'),
            ];
        @endphp
        <script>
            window.cabinetMonV2Config = {
                projectCount: {{ (int) $count }},
                listUrl: @json(route('monitoring.v2.projects.list')),
                saveColumnsUrl: @json(route('monitoring.v2.preferences.list-columns')),
                fillSnapshotsUrl: @json(route('monitoring.v2.snapshots.fill')),
                fillFaviconsUrl: @json(route('monitoring.v2.favicons.fill')),
                listColumns: @json($listColumns ?? []),
                showUrlTemplate: @json(url('/monitoring/__ID__')),
                faviconProjectUrlTemplate: @json(url('/monitoring-v2/favicon?project=__ID__')),
                faviconHostUrlTemplate: @json(url('/monitoring-v2/favicon?host=__HOST__')),
                childRowsUrlTemplate: @json(url('/monitoring/__ID__/child-rows/get')),
                csrf: @json(csrf_token()),
                i18n: {
                    loading: @json(__('Loading projects')),
                    loadingList: @json(__('Monitoring v2 loading list')),
                    loadedOf: @json(__('Monitoring v2 loaded of')),
                    openProject: @json(__('Monitoring v2 open project')),
                    openPositions: @json(__('Monitoring v2 open positions')),
                    projectColumn: @json(__('Monitoring v2 project column')),
                    topBuckets: @json(__('Monitoring v2 top buckets')),
                    expandRegions: @json(__('Monitoring v2 expand regions')),
                    loadingRegions: @json(__('Monitoring v2 loading regions')),
                    collapseRegions: @json(__('Monitoring v2 collapse regions')),
                    top: @json(__('TOP')),
                    position: @json(__('Position')),
                    words: @json(__('Words')),
                    budget: @json(__('Budget')),
                    mastered: @json(__('Mastered')),
                    selectOne: @json(__('Select at least one project')),
                    confirmDelete: @json(__('Do you really want to delete?')),
                    topTooltip: @json(__('Percentage of keys in the top')),
                    loadError: @json(__('Monitoring v2 regions load error')),
                    listLoadError: @json(__('Monitoring v2 list load error')),
                    snapshotsLoading: @json(__('Monitoring v2 snapshots loading')),
                    snapshotsPartial: @json(__('Monitoring v2 snapshots partial')),
                    snapshotsFillTimeout: @json(__('Monitoring v2 snapshots fill timeout')),
                    faviconRefreshClick: @json(__('Monitoring v2 favicon refresh click')),
                    faviconRefreshed: @json(__('Monitoring v2 favicon refreshed')),
                    faviconRefreshFailed: @json(__('Monitoring v2 favicon refresh failed')),
                    faviconsLoading: @json(__('Monitoring v2 favicons loading')),
                    sessionExpired: @json(__('Session expired please refresh')),
                    viewCards: @json(__('Monitoring v2 view cards')),
                    viewTable: @json(__('Monitoring v2 view table')),
                    users: @json(__('Users')),
                    projectsCount: @json(__('Projects count')),
                    noResults: @json(__('Monitoring v2 no search results')),
                    searchPlaceholder: @json(__('Monitoring v2 search placeholder')),
                    dashHintAll: @json(__('Monitoring v2 dash hint all')),
                    dashHintFiltered: @json(__('Monitoring v2 dash hint filtered')),
                    dashAvgLabel: @json(__('Monitoring v2 dash avg label')),
                    dashBuckets: @json($monV2DashBuckets),
                    columnsMenu: @json(__('Monitoring v2 columns menu')),
                    colTop3: @json(__('Monitoring v2 top col 3')),
                    colTop5: @json(__('Monitoring v2 top col 5')),
                    colTop10: @json(__('Monitoring v2 top col 10')),
                    colTop30: @json(__('Monitoring v2 top col 30')),
                    colTop100: @json(__('Monitoring v2 top col 100')),
                    colMiddle: @json(__('Position')),
                    colWords: @json(__('Words')),
                    colUsers: @json(__('Users')),
                    colEngines: @json(__('Monitoring v2 engines column')),
                    colBudget: @json(__('Budget')),
                },
                defaultView: @json('table'),
                adminDebug: @json($isMonitoringAdmin),
                debugSessionId: @json($debugSessionId ?? ''),
            };
        </script>
        <script src="{{ asset('js/cabinet-monitoring-v2-dashboard.js') }}?v={{ @filemtime(public_path('js/cabinet-monitoring-v2-dashboard.js')) ?: time() }}"></script>
        <script src="{{ asset('js/cabinet-monitoring-v2-list.js') }}?v={{ @filemtime(public_path('js/cabinet-monitoring-v2-list.js')) ?: time() }}"></script>
        @include('monitoring-v2.partials.project-interactions')
    @endslot
@endcomponent
