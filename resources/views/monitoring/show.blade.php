@component('component.card', [
    'title' => $project->name,
    'titleHtml' => '<span class="visually-hidden">' . e($project->name) . '</span>',
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('plugins/toastr/toastr.min.css') }}">
        @include('layouts.partials.vendor-datatables-css', ['bundle' => 'rb-css'])
        <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.min.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/daterangepicker/daterangepicker.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/datatables-fixedcolumns/css/fixedColumns.bootstrap4.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/cabinet-monitoring-show.css') }}?v={{ @filemtime(public_path('css/cabinet-monitoring-show.css')) ?: time() }}">
    @endslot

    <div class="cabinet-mon-project-page" id="cabinet-mon-project-root" data-view="keywords">
        <script>
            (function () {
                var el = document.getElementById('cabinet-mon-project-root');
                if (!el) {
                    return;
                }
                var mode = 'keywords';
                var hash = window.location.hash;
                if (hash === '#overview') {
                    mode = 'overview';
                } else if (hash !== '#keywords' && hash !== '#detailed') {
                    try {
                        var saved = localStorage.getItem('cabinet-mon-project-view-v2');
                        if (saved === 'overview') {
                            mode = 'overview';
                        }
                    } catch (e) {}
                }
                el.setAttribute('data-view', mode);
            })();
        </script>
        @include('monitoring.partials.show.project-chrome', ['project' => $project])

        <div class="cabinet-mon-project-page__body">
            @include('monitoring.partials.show.project-kpi', ['kpiSummary' => $kpiSummary ?? null])

            @include('monitoring.partials.show.project-toolbar')

            <div data-mon-view-panel="overview" class="cabinet-mon-view-panel--overview">
                @include('monitoring.partials.show.charts')
            </div>

            <div class="cabinet-mon-project-table-panel card-table is-table-booting" id="cabinet-mon-show-table-host" data-mon-view-panel="keywords">
                <div class="cabinet-mon-project-table-panel__loader" id="cabinetMonShowTableLoader">
                    @include('monitoring.partials.show.loader', ['label' => __('Monitoring show table loading')])
                </div>
                <table class="table table-hover table-bordered text-center w-100 mb-0" id="monitoringTable"></table>
            </div>
        </div>
    </div>

    @include('monitoring.keywords.modal.main')
    @include('monitoring.keywords.modal.delete-confirm')

    <div id="cabinetMonTableControlsTpl" hidden aria-hidden="true">
        @include('monitoring.keywords.controls', [
            'columnSettings' => $columnSettings ?? [],
            'isMultiRegionView' => !request('region') && $project->searchengines->count() > 1,
        ])
    </div>

    @slot('js')
        @php
            $monChartRegion = null;
            if (request('region')) {
                $monChartRegion = $project->searchengines->firstWhere('id', (int) request('region'));
            } elseif ($project->searchengines->count() === 1) {
                $monChartRegion = $project->searchengines->first();
            }
            $monBaseRegion = null;
            if ($monChartRegion) {
                $monBaseRegion = [
                    'id' => (int) $monChartRegion->id,
                    'engine' => (string) $monChartRegion->engine,
                    'lr' => (string) $monChartRegion->lr,
                    'label' => \App\Classes\Monitoring\MonitoringLocationLabel::filterOption($monChartRegion),
                ];
            }
        @endphp
        <script>
            window.cabinetMonProjectConfig = {
                projectId: {{ (int) $project->id }},
                baseGroupId: @json(request('group') ? (int) request('group') : null),
                baseRegion: @json($monBaseRegion),
                multiRegionView: @json(!request('region') && $project->searchengines->count() > 1),
                initialSummary: @json($kpiSummary ?? null),
                columnSettings: @json($columnSettings ?? []),
                statsUrl: @json(route('monitoring.v2.project.stats')),
                projectsListUrl: @json(route('monitoring.v2.projects.list')),
                groupsUrl: @json(url('/monitoring/creator/groups')),
                csrf: @json(csrf_token()),
                i18n: {
                    kpiSnapshotRegion: @json(__('Monitoring show kpi snapshot region hint')),
                    kpiSnapshotProject: @json(__('Monitoring show kpi snapshot project hint')),
                    kpiLoadError: @json(__('Monitoring show kpi load error')),
                    compareNone: @json(__('Monitoring show compare none')),
                    compareAllGroups: @json(__('Monitoring show compare all groups')),
                    compareSearchPlaceholder: @json(__('Monitoring show compare search placeholder')),
                    compareNoResults: @json(__('Monitoring show compare no results')),
                    compareSearching: @json(__('Monitoring show compare searching')),
                    compareNeedBaseRegion: @json(__('Monitoring show compare need base region')),
                    compareMissingRegionLead: @json(__('Monitoring show compare missing region lead')),
                    compareMissingRegionAvailable: @json(__('Monitoring show compare missing region available')),
                    compareIntersectHint: @json(__('Monitoring show compare intersect hint')),
                    compareIntersectEmpty: @json(__('Monitoring show compare intersect empty')),
                    deleteConfirmSingle: @json(__('Monitoring keyword delete confirm single')),
                    deleteConfirmPlural: @json(__('Monitoring keyword delete confirm plural')),
                },
            };
        </script>
        <script src="{{ asset('js/cabinet-monitoring-chart-scales.js') }}?v={{ @filemtime(public_path('js/cabinet-monitoring-chart-scales.js')) ?: time() }}"></script>
        @include('monitoring.partials.smart-search-script')
        <script src="{{ asset('js/cabinet-monitoring-show-charts.js') }}?v={{ @filemtime(public_path('js/cabinet-monitoring-show-charts.js')) ?: time() }}"></script>
        <script src="{{ asset('js/cabinet-monitoring-show-compare.js') }}?v={{ @filemtime(public_path('js/cabinet-monitoring-show-compare.js')) ?: time() }}"></script>
        <script src="{{ asset('plugins/toastr/toastr.min.js') }}"></script>
        <!-- Bootstrap 4 -->
        <script src="{{ asset('plugins/bootstrap-modal-form-templates/bootstrap-modal-form-templates.js') }}"></script>
        <!-- DataTables  & Plugins -->
        <script src="{{ asset('plugins/datatables/jquery.dataTables.min.js') }}"></script>
        @include('layouts.partials.vendor-datatables-js', ['bundle' => 'rb-min'])
        <script src="{{ asset('plugins/datatables-fixedcolumns/js/dataTables.fixedColumns.min.js') }}"></script>
        <script src="{{ asset('plugins/datatables-fixedcolumns/js/fixedColumns.bootstrap4.min.js') }}"></script>
        <script src="{{ asset('js/cabinet-monitoring-show-chrome.js') }}?v={{ @filemtime(public_path('js/cabinet-monitoring-show-chrome.js')) ?: time() }}"></script>
        <!-- Select2 -->
        <script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>
        <script src="{{ asset('js/cabinet-select2-defaults.js') }}?v={{ @filemtime(public_path('js/cabinet-select2-defaults.js')) ?: time() }}"></script>
        <!-- InputMask -->
        <script src="{{ asset('plugins/moment/moment.min.js') }}"></script>
        <script src="{{ asset('plugins/inputmask/jquery.inputmask.min.js') }}"></script>
        <!-- date-range-picker -->
        <script src="{{ asset('plugins/daterangepicker/daterangepicker.js') }}"></script>
        <!-- Papa parse -->
        <script src="{{ asset('plugins/papaparse/papaparse.min.js') }}"></script>

        <!-- Charts -->
        <script src="{{ asset('plugins/chart.js/3.9.1/chart.js') }}"></script>
        <script src="{{ asset('plugins/chart.js/3.9.1/plugins/chartjs-plugin-crosshair.js') }}"></script>
        <script src="{{ asset('plugins/chart.js/3.9.1/plugins/chartjs-plugin-datalabels.js') }}"></script>

        <script>
            $(document).ready(function () {
                let $buttonElement = $('button.change-tag');
                if ($buttonElement.length === 0) {
                    return;
                }
                let $aElement = $('<a></a>');
                $aElement.attr('href', $buttonElement.attr('href'));
                $aElement.attr('target', $buttonElement.attr('target'));
                $aElement.addClass($buttonElement.attr('class'));
                $aElement.text($buttonElement.text());
                $buttonElement.replaceWith($aElement);
            })
        </script>

        <script>
            const PROJECT_ID = '{{ $project->id }}';
            const PROJECT_NAME = @json($project->name);
            const REGION_ID = '{{ $monChartRegion ? $monChartRegion->id : '' }}';
            const REGION_ENGINE = @json($monChartRegion ? $monChartRegion->engine : null);
            const REGION_LR = @json($monChartRegion ? $monChartRegion->lr : null);
            const GROUP_ID = '{{ request('group', null) }}';
            const DATES = '{{ request('dates', null) }}';
            const MODE = '{{ request('mode', null) }}';
            const PAGE_LENGTH = '{{ $length }}';
            const LENGTH_MENU = JSON.parse('{{ $lengthMenu }}');
            const MAIN_COLUMNS_COUNT = 7;
            const MON_KEYWORD_COUNT = {{ (int) ($kpiSummary['words'] ?? 0) }};

            function monModalSetBusy($modal, busy, busyLabel) {
                const $btn = $modal.find('.modal-footer .btn-success');
                if (!$btn.length) {
                    return;
                }
                if (busy) {
                    if (!$btn.data('orig-text')) {
                        $btn.data('orig-text', $btn.text());
                    }
                    $btn.prop('disabled', true).addClass('disabled');
                    if (busyLabel) {
                        $btn.text(busyLabel);
                    }
                    return;
                }
                $btn.prop('disabled', false).removeClass('disabled');
                if ($btn.data('orig-text')) {
                    $btn.text($btn.data('orig-text'));
                }
            }

            function monRegionOptions(yandexOnly) {
                const opts = [];
                $('#searchengines option[value!=""]').each(function () {
                    const $opt = $(this);
                    if (yandexOnly && $opt.data('engine') !== 'yandex') {
                        return;
                    }
                    opts.push({
                        val: $opt.val(),
                        text: $opt.text(),
                        selected: $opt.prop('selected'),
                    });
                });
                return opts;
            }

            function monYandexRegionOptions() {
                return monRegionOptions(true);
            }

            function monPickRegion(onPick, options) {
                options = options || {};
                const yandexOnly = !!options.yandexOnly;
                const title = options.title || @json(__('Monitoring position pick region'));

                const current = $('#searchengines').val();
                if (current && String(current).length) {
                    onPick(String(current));
                    return;
                }

                const params = monRegionOptions(yandexOnly);
                if (!params.length) {
                    toastr.error(yandexOnly
                        ? @json(__('Monitoring occurrence yandex only'))
                        : @json(__('Monitoring parse select region')));
                    return;
                }

                if (params.length === 1) {
                    onPick(String(params[0].val));
                    return;
                }

                $('.modal.general').modal('show').BootstrapModalFormTemplates({
                    title: title,
                    fields: [
                        {
                            type: 'select',
                            name: 'region',
                            label: @json(__('Search engine')),
                            params: params,
                        },
                    ],
                    onAgree: function (m) {
                        const region = m.find('select[name=region]').val();
                        if (!region) {
                            toastr.error(@json(__('Monitoring parse select region single')));
                            return;
                        }
                        m.modal('hide');
                        onPick(String(region));
                    },
                });
            }

            function monPickYandexRegion(onPick) {
                monPickRegion(onPick, {
                    yandexOnly: true,
                    title: @json(__('Monitoring occurrence pick region')),
                });
            }

            function monOccurrenceBtnBusy($btn, busy) {
                if (busy) {
                    if (!$btn.data('orig-html')) {
                        $btn.data('orig-html', $btn.html());
                    }
                    $btn.data('busy', true).prop('disabled', true).addClass('disabled');
                    $btn.html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' + @json(__('Monitoring parse queue enqueueing')));
                    return;
                }
                $btn.data('busy', false).prop('disabled', false).removeClass('disabled');
                if ($btn.data('orig-html')) {
                    $btn.html($btn.data('orig-html'));
                }
            }

            function chartDateRange() {
                if (DATES && String(DATES).trim()) {
                    return DATES;
                }
                return moment().subtract(29, 'days').format('YYYY-MM-DD') + ' - ' + moment().format('YYYY-MM-DD');
            }

            function cabinetMonWirePopovers(root) {
                if (typeof bootstrap === 'undefined' || !bootstrap.Popover) {
                    return;
                }
                var scope = root || document;
                scope.querySelectorAll('[data-bs-toggle="popover"]').forEach(function (el) {
                    if (el.dataset.cabinetMonPopoverWired === '1') {
                        return;
                    }
                    el.dataset.cabinetMonPopoverWired = '1';
                    var pop = new bootstrap.Popover(el, {
                        trigger: 'manual',
                        placement: 'auto',
                        html: true,
                        sanitize: false,
                        container: 'body',
                        customClass: 'cabinet-mon-url-popover',
                        popperConfig: function (defaultConfig) {
                            defaultConfig.modifiers = defaultConfig.modifiers || [];
                            defaultConfig.modifiers.push({
                                name: 'preventOverflow',
                                options: {
                                    boundary: 'viewport',
                                    padding: 8,
                                },
                            });
                            defaultConfig.modifiers.push({
                                name: 'flip',
                                options: {
                                    fallbackPlacements: ['left', 'top', 'bottom'],
                                },
                            });
                            return defaultConfig;
                        },
                    });
                    el.addEventListener('mouseenter', function () {
                        pop.show();
                    });
                    el.addEventListener('mouseleave', function () {
                        var anchor = el;
                        var timeout = setTimeout(function () {
                            pop.hide();
                        }, 300);
                        document.querySelectorAll('.popover').forEach(function (popEl) {
                            popEl.addEventListener('mouseenter', function () {
                                clearTimeout(timeout);
                            });
                            popEl.addEventListener('mouseleave', function () {
                                var instance = bootstrap.Popover.getInstance(anchor);
                                if (instance) {
                                    instance.hide();
                                }
                            });
                        });
                    });
                });
            }

            let table = $('#monitoringTable');
            var monTableBoot = { controlsReady: false, revealed: false };

            function monitoringTableHasBodyRows() {
                return $('#monitoringTable_wrapper').find(
                    '.dataTables_scrollBody tbody tr, .DTFC_LeftBodyLiner tbody tr, #monitoringTable_wrapper tbody tr'
                ).filter(function () {
                    return $(this).find('td').length > 0;
                }).length > 0;
            }

            function monitoringTableHasLoadedData(api) {
                if (!api) {
                    return monitoringTableHasBodyRows();
                }
                try {
                    if (api.page.info().recordsTotal > 0) {
                        return true;
                    }
                } catch (e) {}
                return monitoringTableHasBodyRows();
            }

            function monitoringTableShowLoadErrorToast() {
                var api = window.__cabinetMonKeywordsTableApi;
                if (!api && $.fn.dataTable.isDataTable('#monitoringTable')) {
                    api = $('#monitoringTable').DataTable();
                }
                if (monitoringTableHasLoadedData(api)) {
                    return;
                }
                cabinetMonShowTableLoadError();
                toastr.error(@json(__('Monitoring show table load error')));
            }

            function monitoringTableHideProcessing() {
                $('#monitoringTable_wrapper .dataTables_processing').hide();
            }

            function tryRevealMonitoringTable(api) {
                if (monTableBoot.revealed || !monTableBoot.controlsReady || !api) {
                    return;
                }
                monitoringTableHideProcessing();
                var info = api.page.info();
                if (info.recordsTotal > 0 && !monitoringTableHasLoadedData(api)) {
                    return;
                }

                monTableBoot.revealed = true;

                var unveil = function () {
                    api.columns.adjust();
                    var done = function () {
                        monitoringTableHideProcessing();
                        $('#cabinet-mon-show-table-host').removeClass('is-table-booting');
                        $('#cabinetMonShowTableLoader').remove();
                        if (window.cabinetMonitoringShowChrome) {
                            window.cabinetMonitoringShowChrome.onTableReady(api, { skipRelayout: true });
                        }
                    };
                    if (window.cabinetMonitoringShowChrome && window.cabinetMonitoringShowChrome.finalizeMonTableLayout) {
                        window.cabinetMonitoringShowChrome.finalizeMonTableLayout(api);
                        done();
                    } else if (window.cabinetMonitoringShowChrome && window.cabinetMonitoringShowChrome.relayoutKeywordsTable) {
                        window.cabinetMonitoringShowChrome.relayoutKeywordsTable(done, {
                            adjustColumns: true,
                            rebuildFixedColumns: true,
                        });
                    } else {
                        window.requestAnimationFrame(function () {
                            window.requestAnimationFrame(done);
                        });
                    }
                };

                unveil();
            }

            function cabinetMonShowTableLoadError() {
                var loader = $('#cabinetMonShowTableLoader');
                loader.addClass('is-error');
                loader.find('.cabinet-mon-loader__icon').remove();
                loader.find('.cabinet-mon-loader__label').text(@json(__('Monitoring show table load error')));
            }

            function monColumnWidth(name) {
                var widths = {
                    checkbox: '46px',
                    btn: '62px',
                    query: '380px',
                    url: '140px',
                    group: '140px',
                    target_url: '160px',
                    target: '112px',
                    dynamics: '104px',
                    base: '36px',
                    phrasal: '56px',
                    exact: '60px',
                };
                if (widths[name]) {
                    return widths[name];
                }
                if (String(name).indexOf('col_') === 0) {
                    return '88px';
                }
                if (String(name).indexOf('engine_') === 0) {
                    return '96px';
                }
                return null;
            }

            function monColumnClassName(name) {
                var map = {
                    query: 'cabinet-mon-col-query',
                    url: 'cabinet-mon-col-url',
                    group: 'cabinet-mon-col-group',
                    target_url: 'cabinet-mon-col-target-url',
                    target: 'cabinet-mon-col-target',
                    dynamics: 'cabinet-mon-col-dynamics',
                    base: 'cabinet-mon-col-base',
                    phrasal: 'cabinet-mon-col-phrasal',
                    exact: 'cabinet-mon-col-exact',
                };
                if (map[name]) {
                    return map[name];
                }
                if (String(name).indexOf('col_') === 0) {
                    return 'cabinet-mon-col-date';
                }
                if (String(name).indexOf('engine_') === 0) {
                    return 'cabinet-mon-col-engine';
                }
                return '';
            }

            function monIsMultiRegionView() {
                if (REGION_ID && String(REGION_ID).length) {
                    return false;
                }
                if (window.cabinetMonProjectConfig && window.cabinetMonProjectConfig.multiRegionView) {
                    return true;
                }
                return (window.__cabinetMonTableRegions || []).length > 1;
            }

            function monColumnVisible(name) {
                if (monIsMultiRegionView() && ['dynamics', 'base', 'phrasal', 'exact'].indexOf(name) >= 0) {
                    return false;
                }
                var settings = (window.cabinetMonProjectConfig && window.cabinetMonProjectConfig.columnSettings) || {};
                if (Object.prototype.hasOwnProperty.call(settings, name)) {
                    return !!settings[name];
                }
                return true;
            }

            function cabinetMonMountTableControls(container) {
                var tpl = document.getElementById('cabinetMonTableControlsTpl');
                if (tpl && tpl.innerHTML.trim()) {
                    container.html(tpl.innerHTML);
                    return Promise.resolve();
                }
                return axios.get('/monitoring/keywords/show/controls/' + PROJECT_ID).then(function (response) {
                    container.html(response.data);
                });
            }

            var monTablePrefetch = null;
            var monTablePrefetchUsed = false;

            function monTableAjaxPayload(payload) {
                payload = payload || {};
                payload.region_id = REGION_ID;
                payload.dates_range = DATES;
                payload.mode_range = MODE;
                if (GROUP_ID) {
                    payload.columns = payload.columns || [];
                    var groupCol = null;
                    payload.columns.forEach(function (col) {
                        if (col.data === 'group') {
                            groupCol = col;
                        }
                    });
                    if (groupCol) {
                        groupCol.search = groupCol.search || {};
                        groupCol.search.value = String(GROUP_ID);
                    } else {
                        payload.columns.push({
                            data: 'group',
                            name: 'group',
                            searchable: true,
                            orderable: true,
                            search: { value: String(GROUP_ID) },
                        });
                    }
                }
                return payload;
            }

            function monTableBootstrapPayload() {
                return monTableAjaxPayload({
                    draw: 1,
                    start: 0,
                    length: parseInt(PAGE_LENGTH, 10) || 100,
                    search: { value: '' },
                    columns: [],
                });
            }

            function monTableFetch(payload) {
                return axios.post('/monitoring/' + PROJECT_ID + '/table', monTableAjaxPayload(payload));
            }

            toastr.options = {
                "preventDuplicates": true,
                "timeOut": "5000"
            };

            axios.post('/monitoring/' + PROJECT_ID + '/table', monTableBootstrapPayload()).then(function (response) {
                monTablePrefetch = response.data;

                let tableRegions = response.data.region || [];
                window.__cabinetMonTableRegions = tableRegions;
                let columns = [];

                $.each(response.data.columns, function (i, item) {
                    if ('{{ !auth()->user()->can('edit_query_monitoring') }}' && '{{ !auth()->user()->can('delete_query_monitoring') }}' && i === "btn") {
                        return;
                    }

                    let width = monColumnWidth(i);
                    let orderable = false;

                    if (i === 'query') {
                        orderable = true;
                    }

                    if (i === 'group') {
                        orderable = true;
                    }

                    columns.push({
                        'title': item,
                        'name': i,
                        'data': i,
                        'width': width,
                        'orderable': orderable,
                        'visible': monColumnVisible(i),
                        'className': monColumnClassName(i),
                    });
                });

                let monColumnWidthTargets = [];
                columns.forEach(function (col, idx) {
                    if (col.width) {
                        monColumnWidthTargets.push({ width: col.width, targets: idx });
                    }
                });

                let monFixedLeftCols = (function () {
                    let idx = columns.findIndex(function (col) {
                        return col.name === 'query';
                    });
                    return idx >= 0 ? idx + 1 : 3;
                })();

                let dTable;
                try {
                dTable = table.DataTable({
                    dom: '<"card-header d-flex align-items-center"<"card-title"><"float-right"l>><"card-body p-0"<"mailbox-controls">rt><"card-footer clearfix"p><"clear">',
                    scrollX: true,
                    scrollY: '1020px',
                    scrollCollapse: false,
                    fixedColumns: {
                        leftColumns: monFixedLeftCols,
                        heightMatch: 'auto',
                    },
                    columnDefs: monColumnWidthTargets.concat([
                        { orderable: false, targets: '_all' },
                    ]),
                    lengthMenu: LENGTH_MENU,
                    pageLength: PAGE_LENGTH,
                    pagingType: "simple_numbers",
                    language: {
                        lengthMenu: "_MENU_",
                        search: "_INPUT_",
                        searchPlaceholder: @json(__('Monitoring show table search')),
                        emptyTable: @json(__('Monitoring show table empty')),
                        zeroRecords: @json(__('Monitoring show table empty')),
                        processing: @json(__('Monitoring show table loading')),
                        paginate: {
                            first: "«",
                            last: "»",
                            next: "»",
                            previous: "«"
                        },
                    },
                    processing: true,
                    serverSide: true,
                    ajax: function (data, callback) {
                        if (monTablePrefetch && !monTablePrefetchUsed) {
                            monTablePrefetchUsed = true;
                            var cached = monTablePrefetch;
                            monTablePrefetch = null;
                            cached.draw = data.draw;
                            callback(cached);
                            return;
                        }
                        monTableFetch(data).then(function (resp) {
                            callback(resp.data);
                        }).catch(function (err) {
                            console.error('monitoring table fetch failed', err);
                            monitoringTableHideProcessing();
                            if (!monitoringTableHasLoadedData(window.__cabinetMonKeywordsTableApi)) {
                                cabinetMonShowTableLoadError();
                            }
                            callback({
                                data: [],
                                draw: data.draw,
                                recordsTotal: 0,
                                recordsFiltered: 0,
                            });
                        });
                    },
                    columns: columns,
                    //rowReorder: true,
                    order: [
                        [columns.findIndex((elem) => elem.data === 'query'), 'asc'],
                    ],
                    initComplete: function () {
                        let api = this.api();

                        if (window.cabinetMonitoringShowChrome && window.cabinetMonitoringShowChrome.ensureFixedColumns) {
                            window.cabinetMonitoringShowChrome.ensureFixedColumns(api);
                        }

                        if (window.cabinetMonitoringSearch) {
                            window.cabinetMonitoringSearch.wireGlobalDataTableSearch(api);
                        }

                        let url = new URL(window.location.href);
                        let params = new URLSearchParams(url.search);

                        cabinetMonMountTableControls($('.mailbox-controls').first()).then(function () {
                            let container = $('.mailbox-controls').first();

                            let checkbox = container.find('.checkbox-toggle');

                            //Enable check and uncheck all functionality
                            checkbox.click(function () {
                                let clicks = $(this).data('clicks');
                                if (clicks) {
                                    //Uncheck all checkboxes
                                    $('.table tbody tr').find('input[type="checkbox"]').prop('checked', false);
                                    $('.far.fa-check-square', checkbox).removeClass('fa-check-square').addClass('fa-square');
                                } else {
                                    //Check all checkboxes
                                    $('.table tbody tr').find('input[type="checkbox"]').prop('checked', true);
                                    $('.far.fa-square', checkbox).removeClass('fa-square').addClass('fa-check-square');
                                }
                                $(this).data('clicks', !clicks)
                            });

                            let deletes = container.find('.delete-multiple');

                            deletes.click(function () {

                                let checkboxes = $('.table tbody tr').find('input[type="checkbox"]:checked');
                                if (!checkboxes.length) {
                                    toastr.error(@json(__('Monitoring keyword delete select one')));
                                    return;
                                }

                                let ids = [];
                                checkboxes.each(function () {
                                    ids.push(parseInt($(this).val(), 10));
                                });
                                openKeywordDeleteConfirm({ ids: ids });
                            });

                            container.find('.parse-positions').click(function () {
                                let select = $('#searchengines');

                                let params = [];
                                select.find('option[value!=""]').map((i, item) => {
                                    let region = $(item);
                                    params.push({
                                        val: region.val(),
                                        text: region.text(),
                                        checked: region.prop('selected')
                                    });
                                });

                                $('.modal.general').modal('show').BootstrapModalFormTemplates({
                                    title: @json(__('Monitoring position pick regions')),
                                    fields: [
                                        {
                                            type: 'checkbox',
                                            name: 'regions',
                                            label: '',
                                            params: params
                                        },
                                    ],
                                    onAgree: function (m) {
                                        if (m.data('monParseBusy')) {
                                            return;
                                        }

                                        const formData = new FormData(m.find('form').get(0));
                                        let regions = formData.getAll('regions');

                                        if (!regions.length) {
                                            toastr.error(@json(__('Monitoring parse select region')));
                                            return;
                                        }

                                        const limits = (MON_KEYWORD_COUNT || 0) * regions.length;

                                        if (!limits || !window.confirm(@json(__('Monitoring parse limits confirm')) + ' ' + limits)) {
                                            return;
                                        }

                                        m.data('monParseBusy', true);
                                        monModalSetBusy(m, true, @json(__('Monitoring parse queue enqueueing')));

                                        axios.post('/monitoring/parse/positions/project', {
                                            projectId: PROJECT_ID,
                                            regions: regions,
                                        }).then(function (response) {
                                            m.modal('hide');
                                            if (response.data.status) {
                                                toastr.success(response.data.msg + ' -' + response.data.count);
                                            } else {
                                                toastr.error(response.data.error || @json(__('Monitoring parse queue error')));
                                            }
                                        }).catch(function () {
                                            toastr.error(@json(__('Monitoring parse queue error')));
                                        }).finally(function () {
                                            m.data('monParseBusy', false);
                                            monModalSetBusy(m, false);
                                        });
                                    }
                                });
                            });

                            container.find('.parse-occurrence-all').click(function () {
                                const $btn = $(this);
                                if ($btn.data('busy')) {
                                    return;
                                }

                                const YW_COUNT = 3;

                                axios.get('/monitoring/' + PROJECT_ID + '/count').then(function (response) {
                                    const yandexRegions = response.data.region_yandex || 0;
                                    const queries = response.data.queries || 0;

                                    if (!yandexRegions) {
                                        toastr.error(@json(__('Monitoring occurrence yandex only')));
                                        return;
                                    }

                                    const limits = queries * yandexRegions * YW_COUNT;
                                    const confirmMsg = @json(__('Monitoring parse limits confirm')) + ' ' + limits
                                        + '\n' + @json(__('Monitoring occurrence limits hint'))
                                        + '\n' + @json(__('Monitoring occurrence queue async hint'));

                                    if (!limits || !window.confirm(confirmMsg)) {
                                        return;
                                    }

                                    monOccurrenceBtnBusy($btn, true);

                                    axios.post('/monitoring/occurrence', {
                                        id: PROJECT_ID,
                                    }, { timeout: 45000 }).then(function (postResponse) {
                                        if (postResponse.data.status) {
                                            toastr.success(postResponse.data.msg + ' -' + postResponse.data.count);
                                        } else {
                                            toastr.error(postResponse.data.error || @json(__('Monitoring parse queue error')));
                                        }
                                    }).catch(function (err) {
                                        toastr.error((err.response && err.response.data && err.response.data.error)
                                            || @json(__('Monitoring parse queue error')));
                                    }).finally(function () {
                                        monOccurrenceBtnBusy($btn, false);
                                    });
                                }).catch(function () {
                                    toastr.error(@json(__('Monitoring parse queue error')));
                                });
                            });

                            container.find('.parse-occurrence-keys').click(function () {
                                const $btn = $(this);
                                if ($btn.data('busy')) {
                                    return;
                                }

                                const YW_COUNT = 3;
                                const arrKeys = [];
                                const seen = {};
                                $('#monitoringTable_wrapper input[type="checkbox"]:checked').each(function () {
                                    const id = parseInt($(this).val(), 10);
                                    if (id > 0 && !seen[id]) {
                                        seen[id] = true;
                                        arrKeys.push(String(id));
                                    }
                                });

                                if (!arrKeys.length) {
                                    toastr.error(@json(__('Monitoring keyword delete select one')));
                                    return false;
                                }

                                monPickYandexRegion(function (region) {
                                    const limits = arrKeys.length * YW_COUNT;
                                    const confirmMsg = @json(__('Monitoring parse limits confirm')) + ' ' + limits
                                        + '\n' + @json(__('Monitoring occurrence limits hint'))
                                        + '\n' + @json(__('Monitoring occurrence queue async hint'));

                                    if (!limits || !window.confirm(confirmMsg)) {
                                        return;
                                    }

                                    monOccurrenceBtnBusy($btn, true);

                                    axios.post('/monitoring/occurrence/keys', {
                                        projectId: PROJECT_ID,
                                        keys: arrKeys,
                                        region: region,
                                    }, { timeout: 45000 }).then(function (response) {
                                        if (response.data.status) {
                                            toastr.success(response.data.msg + ' -' + response.data.count);
                                            $('#monitoringTable_wrapper input[type="checkbox"]:checked').prop('checked', false);
                                        } else {
                                            toastr.error(response.data.error || @json(__('Monitoring parse queue error')));
                                        }
                                    }).catch(function (err) {
                                        toastr.error((err.response && err.response.data && err.response.data.error)
                                            || @json(__('Monitoring parse queue error')));
                                    }).finally(function () {
                                        monOccurrenceBtnBusy($btn, false);
                                    });
                                });
                            });

                            container.find('.parse-positions-keys').click(function () {
                                const $btn = $(this);
                                if ($btn.data('busy')) {
                                    return;
                                }

                                const arrKeys = [];
                                const seen = {};
                                $('#monitoringTable_wrapper input[type="checkbox"]:checked').each(function () {
                                    const id = parseInt($(this).val(), 10);
                                    if (id > 0 && !seen[id]) {
                                        seen[id] = true;
                                        arrKeys.push(String(id));
                                    }
                                });

                                if (!arrKeys.length) {
                                    toastr.error(@json(__('Monitoring keyword delete select one')));
                                    return false;
                                }

                                monPickRegion(function (region) {
                                    if (!window.confirm(@json(__('Monitoring parse limits confirm')) + ' ' + arrKeys.length)) {
                                        return false;
                                    }

                                    $btn.data('busy', true).prop('disabled', true).addClass('disabled');
                                    const origHtml = $btn.html();
                                    $btn.html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' + @json(__('Monitoring parse queue enqueueing')));

                                    axios.post('/monitoring/parse/positions/project/keys', {
                                        projectId: PROJECT_ID,
                                        keys: arrKeys,
                                        region: region,
                                    }).then(function (response) {
                                        if (response.data.status) {
                                            toastr.success(response.data.msg + ' -' + response.data.count);
                                            $('#monitoringTable_wrapper input[type="checkbox"]:checked').prop('checked', false);
                                        } else {
                                            toastr.error(response.data.error || @json(__('Monitoring parse queue error')));
                                        }
                                    }).catch(function () {
                                        toastr.error(@json(__('Monitoring parse queue error')));
                                    }).finally(function () {
                                        $btn.data('busy', false).prop('disabled', false).removeClass('disabled').html(origHtml);
                                    });
                                });
                            });

                            container.find('.tooltip-on').tooltip({
                                animation: false,
                                trigger: 'hover',
                            });

                            function setColumnToggleState(name, visible) {
                                container
                                    .find('.column-visible[data-column="' + name + '"]')
                                    .toggleClass('is-on', visible)
                                    .toggleClass('is-off', !visible)
                                    .attr('aria-pressed', visible ? 'true' : 'false');
                            }

                            function monApplyMultiRegionColumnRules(isMultiRegion) {
                                if (!isMultiRegion) {
                                    return;
                                }

                                ['dynamics', 'base', 'phrasal', 'exact'].forEach(function (name) {
                                    const col = api.column(name + ':name');
                                    if (col.length) {
                                        col.visible(false, false);
                                        setColumnToggleState(name, false);
                                    }
                                });

                                const hints = {
                                    dynamics: @json(__('Monitoring column dynamics multi region')),
                                    base: @json(__('Monitoring column occurrence multi region')),
                                    phrasal: @json(__('Monitoring column occurrence multi region')),
                                    exact: @json(__('Monitoring column occurrence multi region')),
                                };

                                Object.keys(hints).forEach(function (name) {
                                    container.find('.column-visible[data-column="' + name + '"]')
                                        .prop('disabled', true)
                                        .addClass('disabled')
                                        .attr('title', hints[name]);
                                });

                                if (window.cabinetMonitoringShowChrome && window.cabinetMonitoringShowChrome.queueColumnVisibilityRelayout) {
                                    window.cabinetMonitoringShowChrome.queueColumnVisibilityRelayout(api);
                                } else {
                                    api.draw(false);
                                }
                            }

                            monApplyMultiRegionColumnRules(monIsMultiRegionView());

                            container.find('.column-visible').click(function () {
                                if ($(this).prop('disabled')) {
                                    return;
                                }

                                let name = $(this).data('column');
                                if (monIsMultiRegionView() && ['dynamics', 'base', 'phrasal', 'exact'].indexOf(name) >= 0) {
                                    toastr.info(@json(__('Monitoring column occurrence multi region')));
                                    return;
                                }
                                let column = api.column(name + ':name');
                                if (!column.length) {
                                    toastr.error(@json(__('Monitoring column unavailable')));
                                    return;
                                }
                                let visible = column.visible();
                                let nextVisible = !visible;

                                column.visible(nextVisible, false);
                                setColumnToggleState(name, nextVisible);

                                axios.post('/monitoring/project/set/column/settings', {
                                    monitoring_project_id: PROJECT_ID,
                                    name: name,
                                    state: nextVisible,
                                });
                                if (window.cabinetMonProjectConfig && window.cabinetMonProjectConfig.columnSettings) {
                                    window.cabinetMonProjectConfig.columnSettings[name] = nextVisible;
                                }

                                if (window.cabinetMonitoringShowChrome && window.cabinetMonitoringShowChrome.queueColumnVisibilityRelayout) {
                                    window.cabinetMonitoringShowChrome.queueColumnVisibilityRelayout(api);
                                } else if (window.cabinetMonitoringShowChrome && window.cabinetMonitoringShowChrome.relayoutAfterColumnToggle) {
                                    api.draw(false);
                                    window.cabinetMonitoringShowChrome.relayoutAfterColumnToggle(api);
                                } else {
                                    api.draw(false);
                                }
                            });

                        $('.search-button').click(function () {
                            let a = $(this);
                            let span = a.parent();
                            let b = span.find('b');
                            let input = span.find('input');

                            let toggleClass = 'd-none';

                            a.addClass(toggleClass);
                            b.addClass(toggleClass);

                            input.unbind("blur");

                            input.removeClass(toggleClass).focus().blur(function () {
                                $(this).addClass(toggleClass);
                                a.removeClass(toggleClass);
                                b.removeClass(toggleClass);
                            });
                        });

                        api.columns().every(function () {
                            let that = this;

                            $('input', this.header()).each(function () {
                                let $input = $(this);
                                if (window.cabinetMonitoringSearch) {
                                    window.cabinetMonitoringSearch.wireColumnDataTableSearch(that, $input);
                                    return;
                                }
                                $input.on('keyup change', function () {
                                    if (that.search() !== this.value) {
                                        that.search(this.value).draw();
                                    }
                                });
                            });
                        });

                        let filter = $('#filter');
                        filter.unbind('filtered');
                        filter.on('filtered', function (e, start, end) {

                            let form = $(this);

                            $.each(form.serializeArray(), function (i, item) {
                                let col = item.name;
                                let val = item.value;

                                api.column(col + ':name').search(val).draw();
                            });
                        });

                        let notValidateUrl = $('<div />', {
                            class: 'cabinet-mon-filter-switch custom-control custom-switch custom-switch-off-danger custom-switch-on-success',
                        });

                        notValidateUrl.append(
                            $('<input />', {
                                type: 'checkbox',
                                id: 'notValidateUrl',
                                class: 'custom-control-input',
                            }).on('change', function () {
                                var active = $(this).is(':checked');
                                var prevTotal = api.page.info().recordsTotal;
                                $(this).closest('.cabinet-mon-filter-switch').toggleClass('is-active', active);
                                api.column('url:name').search(active ? '1' : '').draw();
                                if (active) {
                                    api.one('draw', function () {
                                        var info = api.page.info();
                                        if (prevTotal > 0 && info.recordsTotal === 0) {
                                            toastr.info(@json(__('Monitoring show filter non target urls empty')));
                                        }
                                    });
                                }
                            })
                        );

                        notValidateUrl.append(
                            $('<label />', {
                                for: 'notValidateUrl',
                                class: 'custom-control-label',
                                title: @json(__('Monitoring show filter non target urls hint')),
                            }).text(@json(__('Monitoring show filter non target urls')))
                        );

                        let dynamic = $('<div />', {
                            class: 'form-group'
                        }).css({
                            "margin-bottom": "0px",
                            "margin-right": "15px",
                        });

                        let dynamicOptions = [
                            {val: '', text: @json(__('Monitoring show filter dynamics all'))},
                            {val: 'positive', text: @json(__('Monitoring show filter dynamics positive'))},
                            {val: 'negative', text: @json(__('Monitoring show filter dynamics negative'))},
                        ];

                        let dynamicSelect = $('<select />', {
                            class: 'form-select',
                            name: 'dynamics'
                        });
                        $.each(dynamicOptions, function () {
                            dynamicSelect.append($("<option />").attr('value', this.val).text(this.text));
                        });

                        dynamicSelect.change(function () {
                            let self = $(this);
                            api.column(self.attr('name') + ':name').search(self.val()).draw();
                        });

                        dynamic.append(dynamicSelect);

                        var $dtHeader = $(api.table().container()).closest('.dataTables_wrapper').children('.card-header').first();
                        if (!$dtHeader.length) {
                            $dtHeader = $('#cabinet-mon-show-table-host').find('.card-header').first();
                        }
                        var $tableTitle = $dtHeader.find('.card-title').first();

                        if (tableRegions.length === 1) {
                            var $headerFilters = $('<div />', {
                                class: 'cabinet-mon-table-header-filters ms-auto d-flex align-items-center flex-wrap',
                            });
                            $headerFilters.append(notValidateUrl);
                            $headerFilters.append(dynamic);
                            $tableTitle.after($headerFilters);
                        }

                        $dtHeader.find('label').css('margin-bottom', 0);
                        $('.dataTables_length').find('select').removeClass('form-select form-select-sm');
                        $tableTitle.addClass('flex-grow-1').text(PROJECT_NAME);
                        }).finally(function () {
                            monTableBoot.controlsReady = true;
                            tryRevealMonitoringTable(api);
                        });
                    },
                    drawCallback: function () {
                        let api = this.api();
                        monitoringTableHideProcessing();
                        if (!monTableBoot.revealed && monTableBoot.controlsReady) {
                            tryRevealMonitoringTable(api);
                        }
                        let data = api.data();
                        let $bodyRows = $('#monitoringTable_wrapper .dataTables_scrollBody tbody tr');

                        $bodyRows.each(function (i, item) {
                            let target = 0;
                            if ('target' in data[i]) {
                                target = $('<div />').html(data[i].target).text();
                            }
                            let positions = $(item).find('td span[data-position]');

                            $.each(positions, function (i, item) {
                                let current = $(item).data('position');
                                let nextTo = $(positions[i + 1]).data('position');

                                if (target >= current)
                                    $(item).closest('td').css('background-color', '#99e4b9');
                                else {
                                    if (target >= nextTo)
                                        $(item).closest('td').css('background-color', '#fbe1df');
                                }
                            });
                        });

                        $('.pagination').addClass('pagination-sm');

                        cabinetMonWirePopovers(table[0]);

                        if (window.cabinetMonitoringShowChrome && window.cabinetMonitoringShowChrome.afterMonTableDraw) {
                            try {
                                window.cabinetMonitoringShowChrome.afterMonTableDraw(api);
                            } catch (drawLayoutErr) {
                                console.error('monitoring table layout failed', drawLayoutErr);
                            }
                        }
                    },
                });
                } catch (tableInitErr) {
                    console.error('monitoring DataTable init failed', tableInitErr);
                    monitoringTableShowLoadErrorToast();
                    return;
                }

                $('#cabinetMonKeywordsModal').on('show.bs.modal', function (event) {
                    let button = $(event.relatedTarget);
                    if (!button.length) {
                        button = $($(this).data('monTriggerEl'));
                    }

                    let type = button.data('type');

                    let modal = $(this);

                    let request = null;

                    switch (type) {
                        case "edit_singular":

                            let id = button.data('id');

                            request = axios.get(`/monitoring/keywords/${id}/edit`).then(function (response) {

                                let content = response.data;

                                modal.find('.modal-content').html(content);
                            });
                            break;
                        case "edit_plural":

                            let checkboxes = $('.table tbody tr').find('input[type="checkbox"]:checked');

                            if (checkboxes.length) {

                                request = axios.get(`/monitoring/keywords/${PROJECT_ID}/edit-plural`).then(function (response) {

                                    let content = response.data;

                                    modal.find('.modal-content').html(content);
                                });

                            } else {
                                axios.get('/monitoring/keywords/empty/modal').then(function (response) {

                                    let content = response.data;

                                    modal.find('.modal-content').html(content);

                                    modal.find('.cabinet-mon-keyword-modal__alert-title').text('Выберите хотя бы один элемент.');
                                    modal.find('.cabinet-mon-keyword-modal__alert-text').text('Чтобы массово отредактировать элементы, нужно выбрать хотя бы один элемент.');
                                });
                            }
                            break;
                        case "create_keywords":

                            request = axios.get(`/monitoring/keywords/${PROJECT_ID}/create`).then(function (response) {

                                let content = response.data;

                                modal.find('.modal-content').html(content);

                                modal.find('#upload-queries').click(function () {

                                    let self = $(this);
                                    let csv = self.closest('.input-group').find('#upload');

                                    if (csv[0].files.length && csv[0].files[0].type === 'text/csv') {

                                        csv.parse({
                                            config: {
                                                skipEmptyLines: 'greedy',
                                                complete: function (result) {

                                                    let value = '';
                                                    $.each(result.data, function (i, item) {

                                                        if (item[0])
                                                            value += item[0] + '\r\n';
                                                    });

                                                    modal.find('textarea[name="query"]').val(value);
                                                },
                                                download: 0
                                            }
                                        });

                                    } else {

                                        toastr.error('Загрузите файл формата .csv');
                                    }
                                });
                            });

                            break;
                    }

                    if (request) {
                        request.then(function () {

                            let group = modal.find('.form-select[name="monitoring_group_id"]');
                            if (group.length) {

                                group.select2({});

                                modal.find('#create-group').click(function () {
                                    let el = $(this);
                                    let input = el.closest('.input-group').find('input');

                                    if (input.val()) {

                                        let id_project = input.data('id');

                                        axios.post('/monitoring/groups', {
                                            monitoring_project_id: id_project,
                                            type: "keyword",
                                            name: input.val(),
                                        }).then(function (response) {

                                            let newOption = new Option(response.data.name, response.data.id, false, true);
                                            group.append(newOption).trigger('change');

                                            toastr.success('Добавленно');

                                            input.val(null);
                                        }).catch(function (error) {

                                            toastr.error(@json(__('Something is going wrong')));
                                        });
                                    }
                                });
                            }

                            modal.find('.save-modal').click(function (e) {
                                let self = $(this);
                                let form = self.closest('.modal-content').find('form');
                                let action = form.attr('action');
                                let method = form.attr('method');
                                let data = {};

                                $.each(form.serializeArray(), function (inc, item) {
                                    $.extend(data, {[item.name]: item.value});
                                });

                                if (data.hasOwnProperty('query') && data.query.length < 1) {
                                    e.preventDefault();
                                    form.find('.invalid-feedback.query').fadeIn().delay(3000).fadeOut();
                                    return false;
                                }

                                let checkboxes = $('.table tbody tr').find('input[type="checkbox"]:checked');

                                if (checkboxes.length && method === 'POST') {
                                    $.extend(data, {id: []});
                                    $.each(checkboxes, function (i, checkbox) {
                                        data.id.push($(checkbox).val());
                                    });
                                }

                                axios({
                                    method: method,
                                    url: action,
                                    data: data
                                }).then(function (response) {

                                    dTable.draw(false);

                                    self.closest('.modal').modal('hide');
                                }).catch(function (error) {
                                    console.log(error);
                                });
                            });

                        });
                    }
                });
            }).catch(function (err) {
                console.error('monitoring table bootstrap request failed', err);
                monitoringTableShowLoadErrorToast();
            });

            $('#reservation').daterangepicker({
                locale: {
                    format: 'YYYY-MM-DD'
                }
            });

            let startDate = null;
            let endDate = null;
            if (DATES) {

                let dates = DATES.split(" - ");
                startDate = moment(dates[0]);
                endDate = moment(dates[1]);
            }

            let range = $('#date-range');
            range.daterangepicker({
                opens: 'left',
                startDate: startDate ?? moment().subtract(30, 'days'),
                endDate: endDate ?? moment(),
                ranges: {
                    [@json(__('Monitoring show date last 7 days'))]: [moment().subtract(6, 'days'), moment()],
                    [@json(__('Monitoring show date last 30 days'))]: [moment().subtract(29, 'days'), moment()],
                    [@json(__('Monitoring show date last 60 days'))]: [moment().subtract(59, 'days'), moment()],
                    [@json(__('Monitoring show date last 90 days'))]: [moment().subtract(89, 'days'), moment()],
                    [@json(__('Monitoring show date last 180 days'))]: [moment().subtract(179, 'days'), moment()],
                    [@json(__('Monitoring show date last 365 days'))]: [moment().subtract(364, 'days'), moment()],
                    [@json(__('Monitoring show date last month'))]: [
                        moment().subtract(1, 'month').startOf('month'),
                        moment().subtract(1, 'month').endOf('month'),
                    ],
                },
                alwaysShowCalendars: true,
                showCustomRangeLabel: false,
                locale: {
                    format: 'DD-MM-YYYY',
                    applyLabel: @json(__('Apply')),
                    cancelLabel: @json(__('Cancel')),
                    daysOfWeek: [
                        "Вс",
                        "Пн",
                        "Вт",
                        "Ср",
                        "Чт",
                        "Пт",
                        "Сб"
                    ],
                    monthNames: [
                        "Январь",
                        "Февраль",
                        "Март",
                        "Апрель",
                        "Май",
                        "Июнь",
                        "Июль",
                        "Август",
                        "Сентябрь",
                        "Октябрь",
                        "Ноябрь",
                        "Декабрь"
                    ],
                    firstDay: 1,
                }
            });

            range.on('apply.daterangepicker', function (ev, picker) {

                let dates = picker.startDate.format('YYYY-MM-DD') + ' - ' + picker.endDate.format('YYYY-MM-DD');

                let url = new URL(window.location.href);
                let params = new URLSearchParams(url.search);

                params.set('dates', dates);

                let mode = picker.container.find('input[name="mode"]:checked', '.mode').val();

                params.set('mode', mode);

                window.location.search = params.toString();
            });

            range.on('show.daterangepicker', function (ev, picker) {
                //do something, like clearing an input
                let container = picker.container;

                if (container.find('.mode').length === 0) {

                    let ranges = $('<div />', {
                        class: "mode"
                    });
                    let ul = $('<ul />');

                    let settings = [
                        {id: 'range', name: 'Все дни', value: 'range', checked: true},
                        {id: 'datesFind', name: 'Две даты (фиксированные)', value: 'datesFind', checked: false},
                        {id: 'dates', name: 'Две даты (плавающие)', value: 'dates', checked: false},
                        {id: 'randWeek', name: 'Случайная дата 1 за неделю', value: 'randWeek', checked: false},
                        {id: 'randMonth', name: 'Случайная дата 1 за месяц', value: 'randMonth', checked: false},
                    ];

                    $.each(settings, function (i, item) {

                        let label = $('<label />', {class: "form-check-label", for: item.id}).text(item.name);
                        let radio = $('<input />', {
                            class: "form-check-input",
                            id: item.id,
                            type: "radio",
                            name: "mode",
                            value: item.value,
                            checked: item.checked
                        }).css('margin-top', 'auto');
                        let formCheck = $('<div />', {
                            class: "form-check"
                        });

                        ul.append($('<li />').html(formCheck.prepend(radio, label)));
                    });

                    if (MODE) {
                        ul.find('input[name="mode"]').prop('checked', false);
                        ul.find('input[value="' + MODE + '"]').prop('checked', true);
                    }

                    container.prepend(ranges.html(ul));
                }
            });

            range.on('updateCalendar.daterangepicker', function (ev, picker) {

                let container = picker.container;

                let leftCalendarEl = container.find('.drp-calendar.left tbody tr');
                let rightCalendarEl = container.find('.drp-calendar.right tbody tr');

                let leftCalendarData = picker.leftCalendar.calendar;
                let rightCalendarData = picker.rightCalendar.calendar;

                let showDates = [];

                for (let rows = 0; rows < leftCalendarData.length; rows++) {

                    let leftCalendarRowEl = $(leftCalendarEl[rows]);
                    $.each(leftCalendarData[rows], function (i, item) {

                        let leftCalendarDaysEl = $(leftCalendarRowEl.find('td').get(i));
                        if (!leftCalendarDaysEl.hasClass('off')) {

                            showDates.push({
                                date: item.format('YYYY-MM-DD'),
                                el: leftCalendarDaysEl,
                            });
                        }
                    });

                    let rightCalendarRowEl = $(rightCalendarEl[rows]);
                    $.each(rightCalendarData[rows], function (i, item) {

                        let rightCalendarDaysEl = $(rightCalendarRowEl.find('td').get(i));
                        if (!rightCalendarDaysEl.hasClass('off')) {

                            showDates.push({
                                date: item.format('YYYY-MM-DD'),
                                el: rightCalendarDaysEl,
                            });
                        }
                    });
                }

                axios.post('/monitoring/projects/get-positions-for-calendars', {
                    projectId: PROJECT_ID,
                    regionId: REGION_ID,
                    dates: showDates,
                }).then(function (response) {

                    $.each(response.data, function (i, item) {

                        let found = showDates.find(function (elem) {
                            if (elem.date === item.dateOnly)
                                return true;
                        });

                        if (!found.el.hasClass('exist-position'))
                            found.el.addClass('exist-position');
                    });
                }).catch(function (error) {

                    toastr.error(@json(__('Something is going wrong')));
                });
            });

            function openMonKeywordModal(triggerEl) {
                var modalEl = document.getElementById('cabinetMonKeywordsModal');
                if (!modalEl) {
                    return;
                }
                $(modalEl).data('monTriggerEl', triggerEl);
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                } else {
                    $(modalEl).modal('show');
                }
            }

            var pendingKeywordDelete = null;

            function hideKeywordDeleteModal() {
                var modalEl = document.getElementById('cabinetMonKeywordDeleteModal');
                if (!modalEl) {
                    return;
                }
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    var instance = bootstrap.Modal.getInstance(modalEl);
                    if (instance) {
                        instance.hide();
                    }
                } else {
                    $(modalEl).modal('hide');
                }
            }

            function refreshKeywordsTableAfterDelete() {
                if ($.fn.dataTable.isDataTable('#monitoringTable')) {
                    $('#monitoringTable').DataTable().draw(false);
                }
            }

            function runPendingKeywordDelete() {
                if (!pendingKeywordDelete || !pendingKeywordDelete.ids || !pendingKeywordDelete.ids.length) {
                    return;
                }
                var ids = pendingKeywordDelete.ids.slice();
                var closeEditModal = !!pendingKeywordDelete.closeEditModal;
                pendingKeywordDelete = null;

                Promise.all(ids.map(function (id) {
                    return axios.delete('/monitoring/keywords/' + id);
                })).then(function () {
                    hideKeywordDeleteModal();
                    if (closeEditModal) {
                        var editModalEl = document.getElementById('cabinetMonKeywordsModal');
                        if (editModalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                            var editInstance = bootstrap.Modal.getInstance(editModalEl);
                            if (editInstance) {
                                editInstance.hide();
                            }
                        } else if (editModalEl) {
                            $(editModalEl).modal('hide');
                        }
                    }
                    refreshKeywordsTableAfterDelete();
                }).catch(function () {
                    toastr.error(@json(__('Something is going wrong')));
                });
            }

            function openKeywordDeleteConfirm(options) {
                var modalEl = document.getElementById('cabinetMonKeywordDeleteModal');
                var textEl = document.getElementById('cabinetMonKeywordDeleteText');
                if (!modalEl || !textEl || !options || !options.ids || !options.ids.length) {
                    return;
                }
                var i18n = (window.cabinetMonProjectConfig && window.cabinetMonProjectConfig.i18n) || {};
                var count = options.ids.length;
                textEl.textContent = count === 1
                    ? (i18n.deleteConfirmSingle || @json(__('Monitoring keyword delete confirm single')))
                    : (i18n.deleteConfirmPlural || @json(__('Monitoring keyword delete confirm plural'))).replace(':count', String(count));
                pendingKeywordDelete = options;
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                } else {
                    $(modalEl).modal('show');
                }
            }

            $('#cabinetMonKeywordDeleteConfirm').on('click', function () {
                runPendingKeywordDelete();
            });

            $('#cabinetMonKeywordsModal').on('click', '.cabinet-mon-keyword-delete', function () {
                var id = $(this).data('id');
                if (!id) {
                    return;
                }
                openKeywordDeleteConfirm({
                    ids: [parseInt(id, 10)],
                    closeEditModal: true,
                });
            });

            $('#monitoringTable').on('click', '.cabinet-mon-keyword-edit', function (e) {
                e.preventDefault();
                e.stopPropagation();
                openMonKeywordModal(this);
            });

            $('#monitoringTable').on('click', '.delete-keyword', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var id = $(this).data('id');
                if (!id) {
                    return;
                }
                openKeywordDeleteConfirm({
                    ids: [parseInt(id, 10)],
                });
            });

            let charts = {};
            var monPositionYScale = window.cabinetMonitoringChartScales
                ? window.cabinetMonitoringChartScales.lineY()
                : { reverse: true, ticks: { stepSize: 5 } };

            var monLegendPlugin = window.cabinetMonitoringChartScales
                ? window.cabinetMonitoringChartScales.legendPlugin()
                : { display: true };

            if ($('#topPercent').length) {
                $.extend(charts, {
                    'top': {
                        el: $('#topPercent').get(0).getContext('2d'),
                        type: 'line',
                        chart: 'top',
                        options: {
                            title: {
                                display: true,
                                text: '% Ключевых слов в ТОП',
                                position: 'left',
                            },
                            maintainAspectRatio: false,
                            scales: {
                                x: {
                                    grid: {
                                        display: false,
                                    }
                                },
                                y: {
                                    min: 0,
                                    max: 100,
                                    ticks: {
                                        stepSize: 10,
                                    },
                                },
                            },
                            plugins: {
                                legend: monLegendPlugin,
                                crosshair: {
                                    sync: {
                                        enabled: false
                                    },
                                    snapping: {
                                        enabled: true,
                                    },
                                    zoom: {
                                        enabled: true,
                                        zoomButtonText: @json(__('Reset')),
                                        zoomButtonClass: 'reset-zoom btn btn-default btn-sm',
                                    },
                                    callbacks: {
                                        afterZoom: function () {
                                            charts.top.options.plugins.crosshair.zoom.enabled = false;
                                        }
                                    }
                                },
                                tooltip: {
                                    animation: false,
                                    mode: "index",
                                    intersect: false,
                                }
                            }
                        }
                    }
                });
            }

            if ($('#middlePosition').length) {
                $.extend(charts, {
                    'middle': {
                        el: $('#middlePosition').get(0).getContext('2d'),
                        type: 'line',
                        chart: 'middle',
                        options: {
                            title: {
                                display: true,
                                text: 'Средняя позиция',
                                position: 'left',
                            },
                            maintainAspectRatio: false,
                            scales: {
                                x: {
                                    grid: {
                                        display: false,
                                    }
                                },
                                y: monPositionYScale,
                            },
                            plugins: {
                                legend: monLegendPlugin,
                                crosshair: {
                                    sync: {
                                        enabled: false
                                    },
                                    snapping: {
                                        enabled: true,
                                    },
                                    zoom: {
                                        enabled: true,
                                        zoomButtonText: @json(__('Reset')),
                                        zoomButtonClass: 'reset-zoom btn btn-default btn-sm',
                                    },
                                    callbacks: {
                                        afterZoom: function () {
                                            charts.middle.options.plugins.crosshair.zoom.enabled = false;
                                        }
                                    }
                                },
                                tooltip: {
                                    animation: false,
                                    mode: "index",
                                    intersect: false,
                                }
                            }
                        }
                    }
                });
            }

            if ($('#middlePositionRegions').length) {
                $.extend(charts, {
                    'regions_middle': {
                        el: $('#middlePositionRegions').get(0).getContext('2d'),
                        type: 'line',
                        chart: 'regions_middle',
                        options: {
                            title: {
                                display: true,
                                text: 'Средняя позиция',
                                position: 'left',
                            },
                            maintainAspectRatio: false,
                            scales: {
                                x: {
                                    grid: {
                                        display: false,
                                    }
                                },
                                y: monPositionYScale,
                            },
                            plugins: {
                                legend: monLegendPlugin,
                                crosshair: {
                                    sync: {
                                        enabled: false
                                    },
                                    snapping: {
                                        enabled: true,
                                    },
                                    zoom: {
                                        enabled: true,
                                        zoomButtonText: @json(__('Reset')),
                                        zoomButtonClass: 'reset-zoom btn btn-default btn-sm',
                                    },
                                    callbacks: {
                                        afterZoom: function () {
                                            charts.regions_middle.options.plugins.crosshair.zoom.enabled = false;
                                        }
                                    }
                                },
                                tooltip: {
                                    animation: false,
                                    mode: "index",
                                    intersect: false,
                                }
                            }
                        }
                    }
                });
            }

            if ($('#topPercentRegions').length) {
                $.extend(charts, {
                    'regions_top': {
                        el: $('#topPercentRegions').get(0).getContext('2d'),
                        type: 'line',
                        chart: 'regions_top',
                        options: {
                            title: {
                                display: true,
                                text: '% Ключевых слов в ТОП',
                                position: 'left',
                            },
                            maintainAspectRatio: false,
                            scales: {
                                x: {
                                    grid: {
                                        display: false,
                                    }
                                },
                                y: {
                                    min: 0,
                                    max: 100,
                                    ticks: {
                                        stepSize: 10,
                                    },
                                },
                            },
                            plugins: {
                                legend: monLegendPlugin,
                                crosshair: {
                                    sync: {
                                        enabled: false
                                    },
                                    snapping: {
                                        enabled: true,
                                    },
                                    zoom: {
                                        enabled: true,
                                        zoomButtonText: @json(__('Reset')),
                                        zoomButtonClass: 'reset-zoom btn btn-default btn-sm',
                                    },
                                    callbacks: {
                                        afterZoom: function () {
                                            charts.regions_top.options.plugins.crosshair.zoom.enabled = false;
                                        }
                                    }
                                },
                                tooltip: {
                                    animation: false,
                                    mode: "index",
                                    intersect: false,
                                }
                            }
                        }
                    }
                });
            }

            let chartFilterPeriod = $('#chartFilterPeriod');
            var topChartRawPayload = null;
            var topChartRawBase = null;
            var topChartRawCompare = null;
            var topChartRef = null;
            var chartLoadsPending = 0;
            var chartLoadFailed = false;
            var chartLoadingLabel = @json(__('Monitoring show chart loading'));
            var chartRenderingLabel = @json(__('Monitoring show chart rendering'));
            var chartLoadErrorLabel = @json(__('Monitoring show chart load error'));

            function chartLoaderLabelEl() {
                return $('.cabinet-mon-project-charts .progress-spinner .cabinet-mon-loader__label');
            }

            function chartLoadingStart() {
                if (chartLoadsPending === 0) {
                    chartLoadFailed = false;
                }
                chartLoadsPending += 1;
                var $sp = $('.cabinet-mon-project-charts .progress-spinner');
                $sp.removeClass('d-none is-error');
                chartLoaderLabelEl().text(chartLoadingLabel);
                if (!$sp.find('.cabinet-mon-loader__icon').length) {
                    $sp.html(
                        '<div class="cabinet-mon-loader" role="status" aria-live="polite">' +
                        '<i class="fas fa-circle-notch cabinet-mon-loader__icon" aria-hidden="true"></i>' +
                        '<span class="cabinet-mon-loader__label"></span></div>'
                    );
                    chartLoaderLabelEl().text(chartLoadingLabel);
                }
            }

            function chartLoadingEnd(failed) {
                chartLoadsPending = Math.max(0, chartLoadsPending - 1);
                if (failed) {
                    chartLoadFailed = true;
                }
                if (chartLoadsPending > 0) {
                    return;
                }
                var $sp = $('.cabinet-mon-project-charts .progress-spinner');
                if (chartLoadFailed) {
                    $sp.removeClass('d-none').addClass('is-error');
                    chartLoaderLabelEl().text(chartLoadErrorLabel);
                    return;
                }
                $sp.addClass('d-none');
            }

            function applyChartDataDeferred(chart, payload, obj) {
                return new Promise(function (resolve) {
                    chartLoaderLabelEl().text(chartRenderingLabel);
                    window.requestAnimationFrame(function () {
                        window.requestAnimationFrame(function () {
                            setTimeout(function () {
                                chart.data = normalizeChartPayload(payload, obj);
                                chart.update('none');
                                window.requestAnimationFrame(resolve);
                            }, 0);
                        });
                    });
                });
            }

            function chartParamsForProject(projectId, groupId, range, chartType) {
                var params = {
                    projectId: projectId,
                    regionId: REGION_ID,
                    dateRange: chartDateRange(),
                    chart: chartType,
                };
                if (range) {
                    params.range = range;
                }
                if (groupId) {
                    params.group = groupId;
                }
                if (REGION_ENGINE) {
                    params.matchEngine = REGION_ENGINE;
                }
                if (REGION_LR) {
                    params.matchLr = REGION_LR;
                }
                if (chartType === 'regions_top') {
                    params.topN = window.cabinetMonitoringShowCharts
                        ? window.cabinetMonitoringShowCharts.regionsTopPresetNumber()
                        : 10;
                }
                return params;
            }

            function fetchChartPayload(params) {
                return axios.get('/monitoring/charts', { params: params }).then(function (response) {
                    return response.data;
                });
            }

            function buildChartPayload(basePayload, comparePayload, chartType) {
                var compareApi = window.cabinetMonitoringShowCompare;
                if (
                    comparePayload &&
                    compareApi &&
                    compareApi.canFetchCompareCharts &&
                    compareApi.canFetchCompareCharts() &&
                    chartType !== 'distribution'
                ) {
                    return compareApi.mergeChartPayloads(
                        basePayload,
                        comparePayload,
                        PROJECT_NAME,
                        compareApi.getState().projectName
                    );
                }
                return basePayload;
            }

            function applyTopChartFromRaw() {
                if (!topChartRef || !topChartRawBase || !window.cabinetMonitoringShowCharts) {
                    return;
                }
                var preset = window.cabinetMonitoringShowCharts.getPreset();
                var base = window.cabinetMonitoringShowCharts.applyTopPreset(topChartRawBase, preset);
                var cmp = topChartRawCompare
                    ? window.cabinetMonitoringShowCharts.applyTopPreset(topChartRawCompare, preset)
                    : null;
                var payload = buildChartPayload(base, cmp, 'top');
                if (window.cabinetMonitoringChartScales) {
                    payload = window.cabinetMonitoringChartScales.applySpanGaps(payload);
                    payload = window.cabinetMonitoringChartScales.applyDistinctLineColors(payload);
                }
                topChartRawPayload = payload;
                topChartRef.data = payload;
                topChartRef.update('none');
            }

            function reloadAllCharts() {
                var range = chartFilterPeriod.val();
                $.each(chartInstances, function (key, chart) {
                    loadChartData(chart, charts[key], range);
                });
                loadDistributionChart();
            }

            var distributionChartBase = null;
            var distributionChartCompare = null;

            function distributionChartParams() {
                var params = chartParamsForProject(PROJECT_ID, GROUP_ID, null, 'distribution');
                var compareApi = window.cabinetMonitoringShowCompare;
                if (compareApi && compareApi.appendIntersectParams) {
                    params = compareApi.appendIntersectParams(params, true);
                }
                return params;
            }

            function distributionCompareParams() {
                var compareApi = window.cabinetMonitoringShowCompare;
                if (!compareApi || !compareApi.canFetchCompareCharts || !compareApi.canFetchCompareCharts()) {
                    return null;
                }
                return compareApi.getChartParams(
                    chartParamsForProject(PROJECT_ID, GROUP_ID, null, 'distribution')
                );
            }

            function distributionChartOptions() {
                return {
                    maintainAspectRatio: false,
                    cutout: '62%',
                    plugins: {
                        crosshair: false,
                        datalabels: {
                            anchor: 'center',
                            color: function (context) {
                                var bg = context.dataset.backgroundColor[context.dataIndex];
                                if (window.cabinetMonitoringChartScales) {
                                    return window.cabinetMonitoringChartScales.distributionLabelColor(bg);
                                }
                                return bg === '#ffc107' || bg === '#adb5bd' ? '#212529' : '#ffffff';
                            },
                            font: {
                                size: 12,
                                weight: '600',
                            },
                            formatter: function (value) {
                                if (!value) {
                                    return null;
                                }
                                return value + '%';
                            },
                        },
                        legend: window.cabinetMonitoringChartScales
                            ? window.cabinetMonitoringChartScales.legendPlugin({
                                position: 'left',
                                labels: {
                                    boxWidth: 14,
                                    padding: 10,
                                    usePointStyle: true,
                                    pointStyle: 'circle',
                                    font: {
                                        size: 13,
                                    },
                                    generateLabels: function (chart) {
                                        return window.cabinetMonitoringChartScales.distributionLegendLabels(chart);
                                    },
                                },
                            }, true)
                            : {
                                position: 'left',
                                display: true,
                            },
                    },
                };
            }

            function prepareDistributionPayload(payload) {
                if (!payload) {
                    return payload;
                }
                if (window.cabinetMonitoringChartScales) {
                    return window.cabinetMonitoringChartScales.applyDistributionStyle(payload);
                }
                return payload;
            }

            function renderDistributionDoughnut(canvasEl, payload) {
                if (!canvasEl || !payload) {
                    return null;
                }
                return new Chart(canvasEl.getContext('2d'), {
                    type: 'doughnut',
                    data: payload,
                    plugins: [ChartDataLabels],
                    options: distributionChartOptions(),
                });
            }

            function loadDistributionChart() {
                if (!$('#distributionByTop').length) {
                    return;
                }
                chartLoadingStart();

                var compareApi = window.cabinetMonitoringShowCompare;
                var requests = [fetchChartPayload(distributionChartParams())];
                var compareParams = distributionCompareParams();
                if (compareParams) {
                    requests.push(fetchChartPayload(compareParams));
                }

                Promise.all(requests)
                    .then(function (results) {
                        return new Promise(function (resolve) {
                            window.requestAnimationFrame(function () {
                                if (distributionChartBase) {
                                    distributionChartBase.destroy();
                                    distributionChartBase = null;
                                }
                                if (distributionChartCompare) {
                                    distributionChartCompare.destroy();
                                    distributionChartCompare = null;
                                }

                                var basePayload = results[0];
                                var comparePayload = results[1] || null;
                                var $colBase = $('#distributionColBase');
                                var $colCompare = $('#distributionColCompare');
                                var $titleBase = $('#distributionBaseTitle');
                                var $titleCompare = $('#distributionCompareTitle');

                                if (comparePayload && compareApi && compareApi.canFetchCompareCharts()) {
                                    $colBase.removeClass('col-12').addClass('col-12 col-lg-6');
                                    $colCompare.removeClass('d-none');
                                    $titleBase.text(PROJECT_NAME).removeClass('d-none');
                                    $titleCompare
                                        .text((compareApi.getState() && compareApi.getState().projectName) || '')
                                        .removeClass('d-none');
                                    distributionChartCompare = renderDistributionDoughnut(
                                        $('#distributionByTopCompare').get(0),
                                        prepareDistributionPayload(comparePayload)
                                    );
                                } else {
                                    $colBase.removeClass('col-lg-6').addClass('col-12');
                                    $colCompare.addClass('d-none');
                                    $titleBase.addClass('d-none').text('');
                                    $titleCompare.addClass('d-none').text('');
                                }

                                distributionChartBase = renderDistributionDoughnut(
                                    $('#distributionByTop').get(0),
                                    prepareDistributionPayload(basePayload)
                                );

                                if (compareApi && compareApi.setIntersectMeta && basePayload && basePayload._meta) {
                                    compareApi.setIntersectMeta(basePayload._meta);
                                }

                                window.requestAnimationFrame(resolve);
                            });
                        });
                    })
                    .then(function () {
                        chartLoadingEnd(false);
                    })
                    .catch(function () {
                        chartLoadingEnd(true);
                    });
            }

            function normalizeChartPayload(payload, obj) {
                if (!payload) {
                    return payload;
                }
                if (obj.chart === 'top' && window.cabinetMonitoringShowCharts) {
                    payload = window.cabinetMonitoringShowCharts.applyTopPreset(
                        payload,
                        window.cabinetMonitoringShowCharts.getPreset()
                    );
                }
                if (window.cabinetMonitoringChartScales) {
                    payload = window.cabinetMonitoringChartScales.applySpanGaps(payload);
                    if (
                        obj.chart === 'top' ||
                        obj.chart === 'middle' ||
                        obj.chart === 'regions_middle' ||
                        obj.chart === 'regions_top'
                    ) {
                        payload = window.cabinetMonitoringChartScales.applyDistinctLineColors(payload);
                    }
                }
                return payload;
            }

            function loadChartData(chart, obj, range) {
                chartLoadingStart();

                var baseParams = chartParamsForProject(PROJECT_ID, GROUP_ID, range, obj.chart);
                var compareApi = window.cabinetMonitoringShowCompare;
                if (compareApi && compareApi.appendIntersectParams) {
                    baseParams = compareApi.appendIntersectParams(baseParams, true);
                }
                var compareParams =
                    compareApi && compareApi.canFetchCompareCharts && compareApi.canFetchCompareCharts() && obj.chart !== 'distribution'
                        ? compareApi.getChartParams(baseParams)
                        : null;

                var requests = [fetchChartPayload(baseParams)];
                if (compareParams) {
                    requests.push(fetchChartPayload(compareParams));
                }

                return Promise.all(requests)
                    .then(function (results) {
                        var basePayload = results[0];
                        var comparePayload = results[1] || null;
                        if (compareApi && compareApi.setIntersectMeta && basePayload && basePayload._meta) {
                            compareApi.setIntersectMeta(basePayload._meta);
                        }
                        if (obj.chart === 'top' && topChartRef && window.cabinetMonitoringShowCharts) {
                            topChartRawBase = basePayload;
                            topChartRawCompare = comparePayload;
                            applyTopChartFromRaw();
                            return Promise.resolve();
                        }
                        var payload = buildChartPayload(basePayload, comparePayload, obj.chart);
                        return applyChartDataDeferred(chart, payload, obj);
                    })
                    .then(function () {
                        chartLoadingEnd(false);
                    })
                    .catch(function () {
                        chartLoadingEnd(true);
                        toastr.error(chartLoadErrorLabel);
                    });
            }

            var chartInstances = {};

            $.each(charts, function (key, obj) {

                let chart = new Chart(obj.el, {
                    type: obj.type,
                    data: {},
                    options: obj.options
                });

                if (obj.chart === 'top') {
                    topChartRef = chart;
                }
                chartInstances[key] = chart;

                chartFilterPeriod.change(function () {
                    loadChartData(chart, obj, $(this).val());
                });
            });

            if (window.cabinetMonitoringShowCharts && $('.cabinet-mon-top-presets').length) {
                if ($('.cabinet-mon-project-charts[data-many-regions="1"]').length) {
                    window.cabinetMonitoringShowCharts.setPreset('10');
                }
                window.cabinetMonitoringShowCharts.wirePresets($('.cabinet-mon-top-presets'), function () {
                    if ($('#topPercentRegions').length && chartInstances.regions_top) {
                        loadChartData(chartInstances.regions_top, charts.regions_top, chartFilterPeriod.val());
                    } else if (topChartRawBase) {
                        applyTopChartFromRaw();
                    } else if (topChartRef && chartFilterPeriod.length) {
                        loadChartData(topChartRef, { chart: 'top' }, chartFilterPeriod.val());
                    }
                });
            }


            $('.cabinet-mon-project-charts .nav-pills a[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
                var target = $(e.target).attr('href') || '';
                var showPeriod = target === '#tab_1' || target === '#tab_2'
                    || target === '#tab_regions_middle' || target === '#tab_regions_top';
                chartFilterPeriod.toggleClass('d-none', !showPeriod);
            });

            if (window.cabinetMonitoringShowCompare) {
                window.cabinetMonitoringShowCompare.init().then(function () {
                    window.cabinetMonitoringShowCompare.onChange(function () {
                        reloadAllCharts();
                    });
                    chartFilterPeriod.trigger('change');
                    loadDistributionChart();
                });
            } else {
                chartFilterPeriod.trigger('change');
                loadDistributionChart();
            }

            $('#occurrence-update').click(function () {
                $('.mailbox-controls .parse-occurrence-all').first().trigger('click');
            });
        </script>
    @endslot

@endcomponent
