@component('component.card', [
    'title' => __('Monitored domains'),
    'titleHtml' => e(__('Monitored domains')) . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-site-monitoring'])->render(),
])
    @slot('css')
        <link rel="stylesheet" type="text/css"
              href="{{ asset('plugins/list-comparison/css/font-awesome-4.7.0/css/font-awesome.css') }}"/>
        <link rel="stylesheet" type="text/css" href="{{ asset('plugins/toastr/toastr.css') }}"/>
        <link rel="stylesheet" type="text/css" href="{{ asset('plugins/common/css/common.css') }}"/>
        <link rel="stylesheet" type="text/css" href="{{ asset('plugins/common/css/datatable.css') }}"/>
        <link rel="stylesheet" type="text/css" href="{{ asset('plugins/site-monitoring/css/site-monitoring.css') }}"/>
        <link rel="stylesheet" href="{{ asset('css/cabinet-site-monitoring.css') }}?v={{ @filemtime(public_path('css/cabinet-site-monitoring.css')) ?: time() }}">
    @endslot
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <div id="toast-container" class="toast-top-right success-message" style="display:none;">
        <div class="toast toast-success" aria-live="polite">
            <div class="toast-message">{{ __('Successfully changed') }}</div>
        </div>
    </div>
    <div id="toast-container" class="toast-top-right delete-success-message" style="display:none;">
        <div class="toast toast-success" aria-live="polite">
            <div class="toast-message">{{ __('Successfully deleted') }}</div>
        </div>
    </div>
    <div id="toast-container" class="toast-top-right error-message" style="display:none;">
        <div class="toast toast-error" aria-live="assertive">
            <div class="toast-message error-msg">{{ __('The field must contain more than 0 characters') }}</div>
        </div>
    </div>
    <div id="toast-container" class="toast-top-right delete-error-message" style="display:none;">
        <div class="toast toast-error" aria-live="assertive">
            <div class="toast-message error-msg">{{ __('You need to select the projects you want to delete') }}</div>
        </div>
    </div>
    <div class="cabinet-site-mon-page">
        @include('site-monitoring.partials.module-nav', ['active' => 'projects', 'admin' => $admin ?? false])
        @include('site-monitoring.partials.free-tariff-email-notice')

        <div class="d-flex flex-wrap align-items-center cabinet-site-mon-toolbar">
            <a href="{{ route('add.site.monitoring.view') }}" class="btn btn-primary text-nowrap">
                <i class="fa fa-plus me-1" aria-hidden="true"></i>{{ __('Add monitoring project') }}
            </a>
            <button type="button"
                    class="btn btn-outline-warning"
                    id="cabinetSmResetAllStats"
                    @if(($countProjects ?? 0) < 1) disabled @endif
                    title="{{ __('Site monitoring reset all stats') }}">
                <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>{{ __('Site monitoring reset all stats') }}
            </button>
            <button type="button" class="btn btn-outline-danger" id="selectedProjects">
                {{ __('Delete selected projects') }}
            </button>
            <span class="text-secondary small ms-auto cabinet-site-mon-toolbar__count">
                {{ __('Count tracked projects') }}: <strong id="count-projects">{{ $countProjects }}</strong>
            </span>
        </div>
        <input type="hidden" class="checked-projects">

        <div class="cabinet-sm-datatable">
    <table id="table" class="table table-sm table-bordered table-striped align-middle cabinet-sm-table w-100 mb-0">
        <thead>
        <tr>
            <th class="cabinet-sm-th-check" aria-label="{{ __('Select') }}"></th>
            <th class="col-2">{{ __('Project name') }} </th>
            <th class="col-2">{{ __('Link') }} </th>
            <th class="col-2">{{ __('Keyword') }} </th>
            <th class="col-1">{{ __('Monitoring frequency') }}</th>
            <th class="col-1">{{ __('Response timeout') }}</th>
            <th class="col-2">{{ __('Status') }}</th>
            <th class="text-center">{{ __('Notifications') }}</th>
            <th class="col-1"></th>
        </tr>
        </thead>
        <tbody>
        @foreach($projects as $project)
            <tr id="{{ $project->id }}">
                <td class="cabinet-sm-td-check checbox-for-remove-project">
                    <input type="checkbox"
                           id="project-{{ $project->id }}"
                           class="cabinet-sm-row-select__input"
                           name="enums"
                           aria-label="{{ __('Select project') }}: {{ $project->project_name }}">
                    <label for="project-{{ $project->id }}" class="cabinet-sm-row-select__hit" aria-hidden="true"></label>
                </td>
                <td>
                    <div class="cabinet-sm-cell">
                        {!! Form::text('project_name', $project->project_name, ['class' => 'form-control form-control-sm monitoring', 'data-order' => $project->project_name]) !!}
                    </div>
                </td>
                <td>
                    <div class="cabinet-sm-cell">
                        {!! Form::text('link', $project->link, ['class' => 'form-control form-control-sm monitoring', 'data-order' => $project->link]) !!}
                    </div>
                </td>
                <td>
                    <div class="cabinet-sm-cell">
                        {!! Form::text('phrase', $project->phrase, [
                            'class' => 'form-control form-control-sm monitoring',
                            'placeholder' => __('If the phrase is not selected, the server will wait for the 200 response code'),
                            'data-order' => $project->phrase,
                        ]) !!}
                    </div>
                </td>
                <td data-order="{{ $project->timing }}">
                    <div class="cabinet-sm-cell">
                        @php
                            $timingSelectAttrs = ['class' => 'form-select form-select-sm monitoring'];
                            if ($onFreeTariff ?? false) {
                                $timingSelectAttrs['disabled'] = 'disabled';
                            }
                        @endphp
                        {!! Form::select('timing', $timingOptions ?? [], $project->timing, $timingSelectAttrs) !!}
                    </div>
                </td>
                <td data-order="{{ $project->waiting_time }}">
                    <div class="cabinet-sm-cell">
                        {!! Form::select('waiting_time', [
                        '10' => '10 ' . __('sec'),
                        '15' => '15 ' . __('sec'),
                        '20' => '20 ' . __('sec'),
                        ], $project->waiting_time, ['class' => 'form-select form-select-sm monitoring']) !!}
                    </div>
                </td>
                <td data-order="{{ $project->isPendingResetStatus() ? 2 : ($project->broken ? 1 : 0) }}">
                    <div class="cabinet-sm-cell">
                        @include('site-monitoring.partials.status-cell', ['project' => $project])
                    </div>
                </td>
                <td data-order="{{ $project->send_notification }}" class="cabinet-sm-td-notify">
                    <div class="cabinet-sm-cell cabinet-sm-cell--center">
                        <div class="custom-control custom-switch custom-switch-off-danger custom-switch-on-success mb-0">
                            <input type="checkbox"
                                   class="custom-control-input send-notification-switch"
                                   @if($project->send_notification) checked @endif
                                   id="customSwitch{{ $project->id }}">
                            <label class="custom-control-label" for="customSwitch{{ $project->id }}"></label>
                        </div>
                    </div>
                </td>
                <td class="cabinet-sm-actions">
                    <div class="cabinet-sm-cell cabinet-sm-cell--center cabinet-sm-cell--actions">
                        <button class="btn btn-outline-secondary btn-sm __helper-link ui_tooltip_w check" type="button"
                                data-target="{{ $project->id }}">
                            <i aria-hidden="true" class="fa fa-search"></i>
                            <span class="ui_tooltip __left __l">
                                <span class="ui_tooltip_content" style="width: 250px !important;">
                                    {{ __('Run the check manually') }}
                                </span>
                            </span>
                        </button>
                        <button class="btn btn-outline-primary btn-sm __helper-link ui_tooltip_w cabinet-sm-stats-log" type="button"
                                data-project-id="{{ $project->id }}"
                                data-project-name="{{ $project->project_name }}">
                            <i class="bi bi-bar-chart-line" aria-hidden="true"></i>
                            <span class="ui_tooltip __left __l">
                                <span class="ui_tooltip_content" style="width: 250px !important;">
                                    {{ __('Site monitoring stats log') }}
                                </span>
                            </span>
                        </button>
                        <button class="btn btn-outline-warning btn-sm __helper-link ui_tooltip_w cabinet-sm-stats-reset" type="button"
                                data-project-id="{{ $project->id }}">
                            <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                            <span class="ui_tooltip __left __l">
                                <span class="ui_tooltip_content" style="width: 250px !important;">
                                    {{ __('Site monitoring reset stats') }}
                                </span>
                            </span>
                        </button>
                        <button class="btn btn-outline-danger btn-sm __helper-link ui_tooltip_w" type="button"
                                data-bs-toggle="modal" data-bs-target="#remove-project-id-{{ $project->id }}">
                            <i class="fa fa-trash" aria-hidden="true"></i>
                            <span class="ui_tooltip __left __l">
                                <span class="ui_tooltip_content" style="width: 250px !important;">
                                    {{ __('Delete a project') }}
                                </span>
                            </span>
                        </button>
                    </div>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
        </div>

        @foreach($projects as $project)
            <div class="modal fade" id="remove-project-id-{{ $project->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-body">
                            <p class="mb-1">{{ __('Delete a project') }} «{{ $project->project_name }}»</p>
                            <p class="mb-0 text-secondary">{{ __('Are you sure?') }}</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Back') }}</button>
                            <a href="{{ route('delete.site.monitoring', $project->id) }}" class="btn btn-danger">
                                {{ __('Delete a project') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach

    @if(!auth()->user()->isTelegramConnected())
        <div class="mt-3">
            @include('site-monitoring.partials.cabinet-only-notify-notice')
        </div>
    @endif

        @include('site-monitoring.partials.stats-modal')
    </div>
    @slot('js')
        <script src="{{ asset('plugins/toastr/toastr.min.js') }}"></script>
        <script src="{{ asset('plugins/common/js/common.js') }}"></script>
        <script src="{{ asset('plugins/datatables/jquery.dataTables.min.js') }}"></script>
        @include('layouts.partials.vendor-datatables-js', ['bundle' => 'rb-min'])
        <script src="{{ asset('plugins/datatables/buttons/buttons.min.js') }}"></script>
        <script src="{{ asset('plugins/datatables/buttons/jszip.min.js') }}"></script>
        <script src="{{ asset('plugins/datatables/buttons/vfs_fonts.min.js') }}"></script>
        <script src="{{ asset('plugins/datatables/buttons/html5.min.js') }}"></script>
        <script src="{{ asset('plugins/datatables/search.js') }}"></script>

        @php
            $cabinetSmI18n = [
                'labels' => [
                    'httpCode' => __('http code'),
                    'uptime' => __('Uptime'),
                    'resetConfirm' => __('Site monitoring reset stats confirm'),
                    'resetAllConfirm' => __('Site monitoring reset all stats confirm', [
                        'count' => (int) ($countProjects ?? 0),
                    ]),
                    'resetAllWorking' => __('Site monitoring reset all stats working'),
                    'noHistory' => __('Site monitoring stats no history'),
                    'ongoing' => __('Ongoing'),
                    'checks' => __('checks'),
                    'min' => __('min'),
                    'statsPageInfo' => __('Site monitoring stats page info'),
                    'successChanged' => __('Successfully changed'),
                    'errorGeneric' => __('An error has occurred'),
                    'loading' => __('Loading'),
                    'statusResetHint' => __('Site monitoring status reset hint'),
                    'shareCreate' => __('Create public link'),
                    'shareRefresh' => __('Refresh public link'),
                    'shareRevokeConfirm' => __('Revoke public link') . '?',
                    'shareCopied' => __('Copied'),
                    'validUntil' => __('Valid until'),
                    'publicLinkCreated' => __('Public link created'),
                    'publicLinkRevoked' => __('Public link revoked'),
                ],
                'modal' => [
                    'checkHistory' => __('Site monitoring check history'),
                    'incidents' => __('Site monitoring incidents'),
                    'kpiTotal' => __('Site monitoring stats total checks'),
                    'kpiFailed' => __('Site monitoring stats failures'),
                    'kpiSuccess' => __('Site monitoring stats success rate'),
                    'colDate' => __('Date'),
                    'colStatus' => __('Status'),
                    'colUptime' => __('Uptime'),
                    'since' => __('Site monitoring since'),
                    'lastCheck' => __('Last check'),
                    'downtime' => __('Current downtime'),
                ],
                'dt' => [
                    'search' => __('Search') . ':',
                    'lengthMenu' => __('show') . ' _MENU_ ' . __('records'),
                    'emptyTable' => __('No records'),
                    'info' => __('Showing') . ' ' . __('from') . ' _START_ ' . __('to') . ' _END_ ' . __('of') . ' _TOTAL_ ' . __('entries'),
                ],
            ];
        @endphp

        <script>
            const cabinetSmLabels = @json($cabinetSmI18n['labels']);
            const cabinetSmModal = @json($cabinetSmI18n['modal']);
            const cabinetSmDt = @json($cabinetSmI18n['dt']);

            let statsModalProjectId = null;

            function renderStatusCell(response) {
                const pending = !!response.pending
                const statusClass = pending
                    ? 'cabinet-sm-status--pending'
                    : (response.broken ? 'cabinet-sm-status--bad' : 'cabinet-sm-status--ok')
                let html = '<div class="cabinet-sm-cell"><div class="cabinet-sm-status ' + statusClass + ' small">' +
                    '<div>' + escapeHtml(response.status) + '</div>'
                if (pending) {
                    html += '<div class="text-secondary">' + escapeHtml(cabinetSmLabels.statusResetHint) + '</div>'
                } else if (response.code != null) {
                    html += '<div>' + cabinetSmLabels.httpCode + ': ' + response.code + '</div>' +
                        '<div>' + cabinetSmLabels.uptime + ': ' + response.uptime + '%</div>'
                }
                html += '</div></div>'
                return html
            }

            function escapeHtml(text) {
                return $('<div>').text(text == null ? '' : String(text)).html()
            }

            function renderTimelinePagination(pagination) {
                if (!pagination || pagination.total <= pagination.per_page) {
                    return '';
                }
                const page = pagination.page;
                const last = pagination.last_page;
                const info = cabinetSmLabels.statsPageInfo
                    .replace(':from', String(pagination.from))
                    .replace(':to', String(pagination.to))
                    .replace(':total', String(pagination.total));

                let html = '<nav class="cabinet-sm-stats-pagination d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2" aria-label="' + escapeHtml(cabinetSmModal.checkHistory) + '">';
                html += '<small class="text-secondary mb-0">' + escapeHtml(info) + '</small>';
                html += '<ul class="pagination pagination-sm mb-0">';
                html += '<li class="page-item' + (page <= 1 ? ' disabled' : '') + '"><button type="button" class="page-link cabinet-sm-stats-page-btn" data-page="' + (page - 1) + '">«</button></li>';
                html += '<li class="page-item disabled"><span class="page-link text-secondary bg-transparent border-0">' + page + ' / ' + last + '</span></li>';
                html += '<li class="page-item' + (page >= last ? ' disabled' : '') + '"><button type="button" class="page-link cabinet-sm-stats-page-btn" data-page="' + (page + 1) + '">»</button></li>';
                html += '</ul></nav>';
                return html;
            }

            function renderStatsModal(data) {
                const s = data.summary
                const kpis = [
                    { label: cabinetSmModal.kpiTotal, value: s.total_checks, tone: '' },
                    { label: cabinetSmModal.kpiFailed, value: s.failed_checks, tone: s.failed_checks > 0 ? 'danger' : '' },
                    { label: cabinetSmModal.kpiSuccess, value: s.success_rate != null ? s.success_rate + '%' : '—', tone: 'success' },
                    { label: cabinetSmLabels.uptime, value: s.uptime_percent != null ? s.uptime_percent + '%' : '—', tone: s.currently_broken ? 'danger' : 'success' },
                ]

                let kpiHtml = '<div class="row g-2 mb-4">'
                kpis.forEach(function (k) {
                    const toneClass = k.tone ? ' cabinet-sm-stats-kpi--' + k.tone : ''
                    kpiHtml += '<div class="col-6 col-md-3"><div class="cabinet-sm-stats-kpi' + toneClass + '">' +
                        '<div class="cabinet-sm-stats-kpi__value">' + escapeHtml(k.value) + '</div>' +
                        '<div class="cabinet-sm-stats-kpi__label">' + escapeHtml(k.label) + '</div></div></div>'
                })
                kpiHtml += '</div>'

                let incidentsHtml = ''
                if (data.incidents && data.incidents.length) {
                    incidentsHtml = '<h6 class="fw-semibold mb-2">' + escapeHtml(cabinetSmModal.incidents) + '</h6><div class="list-group list-group-flush cabinet-sm-stats-incidents mb-4">'
                    data.incidents.forEach(function (inc) {
                        const badge = inc.ongoing
                            ? '<span class="badge text-bg-danger">' + escapeHtml(cabinetSmLabels.ongoing) + '</span>'
                            : '<span class="badge text-bg-secondary">' + escapeHtml(inc.duration_minutes) + ' ' + escapeHtml(cabinetSmLabels.min) + '</span>'
                        incidentsHtml += '<div class="list-group-item px-0">' +
                            '<div class="d-flex flex-wrap justify-content-between gap-2 mb-1">' +
                            '<strong class="small">' + escapeHtml(inc.started_at) + ' → ' + escapeHtml(inc.ended_at || '…') + '</strong>' + badge + '</div>' +
                            '<div class="small text-secondary">' + escapeHtml(inc.started_status) + (inc.started_code ? ' · HTTP ' + inc.started_code : '') +
                            ' · ' + inc.checks_while_down + ' ' + escapeHtml(cabinetSmLabels.checks) + '</div></div>'
                    })
                    incidentsHtml += '</div>'
                }

                let timelineHtml = '<h6 class="fw-semibold mb-2">' + escapeHtml(cabinetSmModal.checkHistory) + '</h6>'
                if (!s.has_history) {
                    timelineHtml += '<p class="text-secondary small">' + escapeHtml(cabinetSmLabels.noHistory) + '</p>'
                } else {
                    timelineHtml += '<div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0 cabinet-sm-stats-table">' +
                        '<thead class="table-light"><tr>' +
                        '<th>' + escapeHtml(cabinetSmModal.colDate) + '</th><th>' + escapeHtml(cabinetSmModal.colStatus) + '</th><th>HTTP</th><th>' + escapeHtml(cabinetSmModal.colUptime) + '</th><th></th></tr></thead><tbody>'
                    data.timeline.forEach(function (row) {
                        const rowClass = row.broken ? 'table-danger' : ''
                        timelineHtml += '<tr class="' + rowClass + '">' +
                            '<td class="text-nowrap small">' + escapeHtml(row.at) + '</td>' +
                            '<td class="small">' + escapeHtml(row.status) + '</td>' +
                            '<td>' + (row.http_code != null ? row.http_code : '—') + '</td>' +
                            '<td>' + (row.uptime_percent != null ? row.uptime_percent + '%' : '—') + '</td>' +
                            '<td class="text-end"><span class="badge text-bg-light text-dark border">' + escapeHtml(row.source_label) + '</span></td>' +
                            '</tr>'
                    })
                    timelineHtml += '</tbody></table></div>'
                    timelineHtml += renderTimelinePagination(data.timeline_pagination)
                }

                const meta = '<p class="small text-secondary mb-3">' +
                    escapeHtml(cabinetSmModal.since) + ': ' + escapeHtml(data.project.created_at) +
                    (s.last_check ? ' · ' + escapeHtml(cabinetSmModal.lastCheck) + ': ' + escapeHtml(s.last_check) : '') +
                    (s.downtime_minutes != null ? ' · ' + escapeHtml(cabinetSmModal.downtime) + ': ' + s.downtime_minutes + ' ' + escapeHtml(cabinetSmLabels.min) : '') +
                    '</p>'

                $('#cabinetSmStatsModalBody').html(kpiHtml + meta + incidentsHtml + timelineHtml)
            }
            let table
            $(document).ready(function () {
                table = $('#table').DataTable({
                    dom: '<"row align-items-center g-2 cabinet-sm-dt-controls"<"col-sm-auto"l><"col-sm-auto ms-auto"f>>rt<"row align-items-center g-2 cabinet-sm-dt-footer"<"col-sm-auto"i><"col-sm-auto ms-auto"p>>',
                    autoWidth: false,
                    columnDefs: [
                        { targets: 0, orderable: false, searchable: false, width: '3.5rem', className: 'cabinet-sm-td-check' },
                    ],
                    language: {
                        paginate: {
                            first: '«',
                            last: '»',
                            next: '»',
                            previous: '«',
                        },
                    },
                    oLanguage: {
                        sSearch: cabinetSmDt.search,
                        sLengthMenu: cabinetSmDt.lengthMenu,
                        sEmptyTable: cabinetSmDt.emptyTable,
                        sInfo: cabinetSmDt.info,
                    },
                });

                search(table)

                function applyStatusCellsById(byId) {
                    table.rows({ page: 'all' }).every(function () {
                        var node = this.node()
                        if (!node || !node.id || !byId[node.id]) {
                            return
                        }
                        $('td:eq(6)', node).html(renderStatusCell(byId[node.id]))
                    })
                    $('#table tbody tr[id]').each(function () {
                        var id = this.id
                        if (byId[id]) {
                            $('td:eq(6)', this).html(renderStatusCell(byId[id]))
                        }
                    })
                }

                $('#cabinetSmResetAllStats').on('click', function () {
                    if (!window.confirm(cabinetSmLabels.resetAllConfirm)) {
                        return
                    }
                    const btn = $(this)
                    const btnHtml = btn.html()
                    btn.prop('disabled', true)
                        .attr('aria-busy', 'true')
                        .html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' + escapeHtml(cabinetSmLabels.resetAllWorking))
                    $.ajax({
                        type: 'POST',
                        dataType: 'json',
                        timeout: 120000,
                        url: "{{ route('site.monitoring.reset.all.stats') }}",
                        data: { _token: $('meta[name="csrf-token"]').attr('content') },
                        success: function (response) {
                            const byId = {}
                            if (response.projects && response.projects.length) {
                                response.projects.forEach(function (row) {
                                    byId[String(row.id)] = row
                                })
                                applyStatusCellsById(byId)
                            }
                            const msg = response.message || cabinetSmLabels.successChanged
                            $('.toast-top-right.success-message .toast-message').text(msg)
                            $('.toast-top-right.success-message').show(300)
                            if (typeof toastr !== 'undefined') {
                                toastr.success(msg)
                            }
                            setTimeout(function () { $('.toast-top-right.success-message').hide(300) }, 5000)
                        },
                        error: function (xhr) {
                            const msg = xhr.responseJSON && xhr.responseJSON.message
                                ? xhr.responseJSON.message
                                : cabinetSmLabels.errorGeneric
                            $('.toast-top-right.error-message .toast-message').text(msg)
                            $('.toast-top-right.error-message').show(300)
                            if (typeof toastr !== 'undefined') {
                                toastr.error(msg)
                            }
                            setTimeout(function () { $('.toast-top-right.error-message').hide(300) }, 5000)
                        },
                        complete: function () {
                            btn.prop('disabled', false)
                                .removeAttr('aria-busy')
                                .html(btnHtml)
                        }
                    })
                })
            });


            let oldValue = ''
            let oldProjectName = ''

            $('input.send-notification-switch').click(function () {
                $.ajax({
                    type: "POST",
                    dataType: "json",
                    url: "{{ route('edit.domain') }}",
                    data: {
                        id: $(this).closest('tr').attr('id'),
                        name: 'send_notification',
                        option: $(this).is(':checked') ? 1 : 0,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function () {
                        $('.toast-top-right.success-message').show(300)
                        setTimeout(() => {
                            $('.toast-top-right.success-message').hide(300)
                        }, 4000)
                    },
                    error: function () {
                        $('.toast-top-right.error-message').show()
                        setTimeout(() => {
                            $('.toast-top-right.error-message').hide(300)
                        }, 4000)
                    }
                });
            })
            $(".monitoring").focus(function () {
                oldValue = $(this).val()
            })
            $(".monitoring").blur(function () {
                if (oldValue !== $(this).val() || $(this).attr('name') === 'phrase' && oldValue !== $(this).val()) {
                    $.ajax({
                        type: "POST",
                        dataType: "json",
                        url: "{{ route('edit.domain') }}",
                        data: {
                            id: $(this).closest('tr').attr('id'),
                            name: $(this).attr('name'),
                            option: $(this).val(),
                            _token: $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function () {
                            $('.toast-top-right.success-message').show(300)
                            setTimeout(() => {
                                $('.toast-top-right.success-message').hide(300)
                            }, 4000)
                        },
                        error: function () {
                            $('.toast-top-right.error-message').show()
                            setTimeout(() => {
                                $('.toast-top-right.error-message').hide(300)
                            }, 4000)
                        }
                    });
                }
            });
            $(document).on('change', '.checbox-for-remove-project .cabinet-sm-row-select__input', function () {
                let $checkbox = $(this)
                let selectedId = $checkbox.attr('id').substr(8)
                let text = $('.checked-projects').text();
                let $row = $checkbox.closest('tr')
                if ($checkbox.is(':checked')) {
                    $row.attr('data-select', true)
                    $('.checked-projects').text(text + selectedId + ', ')
                } else {
                    $row.attr('data-select', false)
                    text = text.replace(selectedId + ', ', '')
                    $('.checked-projects').text(text)
                }
            })
            $('#selectedProjects').click(function () {
                $.ajax({
                    type: "post",
                    dataType: "json",
                    url: "{{ route('delete.sites.monitoring') }}",
                    data: {
                        ids: $('.checked-projects').text(),
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function () {
                        let iterator = 0;
                        $('[data-select=true]').each(function () {
                            iterator++
                            $(this).remove();
                        })
                        $('#count-projects').text($('#count-projects').text() - iterator)
                        if ($('#count-projects').text() == 0) {
                            window.location.replace('{{ route('add.site.monitoring.view') }}');
                        }
                        $('.toast-top-right.delete-success-message').show(300)
                        setTimeout(() => {
                            $('.toast-top-right.delete-success-message').hide(300)
                        }, 4000)
                    },
                    error: function () {
                        $('.toast-top-right.delete-error-message').show()
                        setTimeout(() => {
                            $('.toast-top-right.delete-error-message').hide(300)
                        }, 4000)
                    }
                });
            });

            $(document).on('click', '.check', function () {
                let targetButton = $(this)
                const icon = targetButton.find('i')
                icon.attr('class', 'fa-solid fa-clock')

                let parentRow = $(this).closest('tr')
                $.ajax({
                    type: "POST",
                    url: "{{ route('check.domain') }}",
                    data: {
                        projectId: $(this).attr('data-target'),
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function (response) {
                        icon.attr('class', 'fa fa-search')
                        parentRow.children('td').eq(6).html(renderStatusCell(response))
                    },
                    error: function () {
                        icon.attr('class', 'fa fa-search')
                    }
                });
            })

            $(document).on('click', '.cabinet-sm-stats-reset', function () {
                if (!window.confirm(cabinetSmLabels.resetConfirm)) {
                    return
                }
                const btn = $(this)
                const projectId = btn.data('project-id')
                const parentRow = btn.closest('tr')
                btn.prop('disabled', true)
                $.ajax({
                    type: 'POST',
                    url: "{{ route('site.monitoring.reset.stats') }}",
                    data: { projectId: projectId, _token: $('meta[name="csrf-token"]').attr('content') },
                    success: function (response) {
                        parentRow.children('td').eq(6).html(renderStatusCell(response))
                        $('.toast-top-right.success-message .toast-message').text(response.message || cabinetSmLabels.successChanged)
                        $('.toast-top-right.success-message').show(300)
                        setTimeout(function () { $('.toast-top-right.success-message').hide(300) }, 4000)
                    },
                    complete: function () { btn.prop('disabled', false) }
                })
            })

            let statsModalEl = document.getElementById('cabinetSmStatsModal')
            let statsModal = statsModalEl && typeof bootstrap !== 'undefined' ? new bootstrap.Modal(statsModalEl) : null

            function loadStatsModal(projectId, page) {
                page = page || 1
                statsModalProjectId = projectId
                $('#cabinetSmStatsModalBody').html(
                    '<div class="text-center py-5 text-secondary"><div class="spinner-border spinner-border-sm text-primary"></div><p class="mt-2 mb-0 small">' + escapeHtml(cabinetSmLabels.loading) + '…</p></div>'
                )
                $.get("{{ route('site.monitoring.project.stats') }}", { projectId: projectId, page: page }, function (data) {
                    $('#cabinetSmStatsModalSubtitle').text(data.project.name + ' · ' + data.project.link)
                    renderStatsModal(data)
                    updateStatsSharePanel(data.share || {})
                }).fail(function () {
                    $('#cabinetSmStatsModalBody').html('<p class="text-danger mb-0">' + escapeHtml(cabinetSmLabels.errorGeneric) + '</p>')
                })
            }

            $(document).on('click', '.cabinet-sm-stats-log', function () {
                const projectId = $(this).data('project-id')
                const projectName = $(this).data('project-name')
                $('#cabinetSmStatsModalSubtitle').text(projectName)
                $('#cabinetSmStatsShareTtl').prop('disabled', false).removeAttr('disabled')
                if (statsModal) statsModal.show()
                loadStatsModal(projectId, 1)
            })

            $(document).on('click', '.cabinet-sm-stats-page-btn', function () {
                const page = parseInt($(this).data('page'), 10)
                if (!statsModalProjectId || !page || $(this).closest('.page-item').hasClass('disabled')) {
                    return
                }
                loadStatsModal(statsModalProjectId, page)
            })

            function updateStatsSharePanel(share) {
                const $panel = $('#cabinetSmStatsShare')
                const $url = $('#cabinetSmStatsShareUrl')
                const $copy = $('#cabinetSmStatsShareCopy')
                const $revoke = $('#cabinetSmStatsShareRevoke')
                const $create = $('#cabinetSmStatsShareCreate')
                const $expires = $('#cabinetSmStatsShareExpires')
                const $ttl = $('#cabinetSmStatsShareTtl')
                const $unavailable = $('#cabinetSmStatsShareUnavailable')
                if (!$panel.length) {
                    return
                }
                share = share || {}
                const shareBackendOn = share.available !== false
                    && $panel.data('feature-available') !== 0
                    && $panel.data('feature-available') !== '0'

                if ($ttl.length) {
                    $ttl.prop('disabled', false).removeAttr('disabled')
                    if (share.ttl_days !== undefined && share.ttl_days !== null) {
                        $ttl.val(String(share.ttl_days))
                    }
                }

                if ($unavailable.length) {
                    $unavailable.toggleClass('d-none', shareBackendOn)
                }

                if (!shareBackendOn) {
                    $create.prop('disabled', true)
                    $copy.prop('disabled', true)
                    $revoke.prop('disabled', true)
                    return
                }

                $create.prop('disabled', false)
                const hasLink = !!share.url
                $url.val(share.url || '')
                $copy.prop('disabled', !hasLink)
                $revoke.prop('disabled', !hasLink)
                $create.html('<i class="bi bi-link-45deg me-1" aria-hidden="true"></i>' +
                    (hasLink ? escapeHtml(cabinetSmLabels.shareRefresh) : escapeHtml(cabinetSmLabels.shareCreate)))
                if (hasLink && (share.expires_label || share.expires_at)) {
                    const label = share.expires_label
                        || (cabinetSmLabels.validUntil + ' ' + share.expires_at)
                    $expires.removeClass('d-none text-bg-secondary').addClass('text-bg-success')
                        .text(label)
                } else {
                    $expires.addClass('d-none').removeClass('text-bg-success')
                }
            }

            $('#cabinetSmStatsShareCopy').on('click', function () {
                const $url = $('#cabinetSmStatsShareUrl')
                if (!$url.val()) {
                    return
                }
                const done = function () {
                    const $btn = $('#cabinetSmStatsShareCopy')
                    const html = $btn.html()
                    $btn.html('<i class="bi bi-check2" aria-hidden="true"></i>')
                    setTimeout(function () { $btn.html(html) }, 1500)
                }
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText($url.val()).then(done).catch(function () {
                        $url[0].select()
                        document.execCommand('copy')
                        done()
                    })
                } else {
                    $url[0].select()
                    document.execCommand('copy')
                    done()
                }
            })

            $('#cabinetSmStatsShareCreate').on('click', function () {
                if (!statsModalProjectId) {
                    return
                }
                const $btn = $(this)
                const createUrl = $('#cabinetSmStatsShare').data('create-url')
                $btn.prop('disabled', true)
                $.ajax({
                    type: 'POST',
                    url: createUrl,
                    dataType: 'json',
                    data: {
                        projectId: statsModalProjectId,
                        ttl_days: $('#cabinetSmStatsShareTtl').val(),
                        _token: $('meta[name="csrf-token"]').attr('content'),
                    },
                    success: function (data) {
                        updateStatsSharePanel({
                            available: true,
                            url: data.url,
                            expires_at: data.expires_at,
                            expires_label: data.expires_label,
                            ttl_days: data.ttl_days,
                        })
                        $('.toast-top-right.success-message .toast-message').text(data.message || cabinetSmLabels.publicLinkCreated)
                        $('.toast-top-right.success-message').show(300)
                        setTimeout(function () { $('.toast-top-right.success-message').hide(300) }, 4000)
                    },
                    error: function (xhr) {
                        const msg = xhr.responseJSON && xhr.responseJSON.message
                            ? xhr.responseJSON.message
                            : cabinetSmLabels.errorGeneric
                        $('.toast-top-right.error-message .toast-message').text(msg)
                        $('.toast-top-right.error-message').show(300)
                        setTimeout(function () { $('.toast-top-right.error-message').hide(300) }, 5000)
                    },
                    complete: function () { $btn.prop('disabled', false) },
                })
            })

            $('#cabinetSmStatsPdfBtn').on('click', function () {
                if (!statsModalProjectId) {
                    return
                }
                const $btn = $(this)
                const pdfUrl = $('#cabinetSmStatsShare').data('pdf-url')
                const token = $('meta[name="csrf-token"]').attr('content')
                const originalHtml = $btn.html()
                $btn.prop('disabled', true).html(
                    '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' +
                    escapeHtml(cabinetSmLabels.loading) + '…'
                )
                fetch(pdfUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/pdf',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: new URLSearchParams({
                        projectId: String(statsModalProjectId),
                        _token: token,
                    }),
                }).then(function (response) {
                    if (!response.ok) {
                        return response.json().catch(function () { return {} }).then(function (data) {
                            throw new Error(data.message || cabinetSmLabels.errorGeneric)
                        })
                    }
                    const disposition = response.headers.get('Content-Disposition') || ''
                    const match = disposition.match(/filename="?([^";]+)"?/)
                    const fileName = match ? match[1] : 'site-monitoring-report.pdf'
                    return response.blob().then(function (blob) {
                        const url = URL.createObjectURL(blob)
                        const link = document.createElement('a')
                        link.href = url
                        link.download = fileName
                        document.body.appendChild(link)
                        link.click()
                        link.remove()
                        URL.revokeObjectURL(url)
                    })
                }).catch(function (error) {
                    $('.toast-top-right.error-message .toast-message').text(error.message || cabinetSmLabels.errorGeneric)
                    $('.toast-top-right.error-message').show(300)
                    setTimeout(function () { $('.toast-top-right.error-message').hide(300) }, 5000)
                }).finally(function () {
                    $btn.prop('disabled', false).html(originalHtml)
                })
            })

            $('#cabinetSmStatsShareRevoke').on('click', function () {
                if (!statsModalProjectId || !window.confirm(cabinetSmLabels.shareRevokeConfirm)) {
                    return
                }
                const revokeUrl = $('#cabinetSmStatsShare').data('revoke-url')
                $.ajax({
                    type: 'POST',
                    url: revokeUrl,
                    dataType: 'json',
                    data: {
                        projectId: statsModalProjectId,
                        _token: $('meta[name="csrf-token"]').attr('content'),
                    },
                    success: function (data) {
                        updateStatsSharePanel({ available: true, url: null, expires_at: null })
                        $('.toast-top-right.success-message .toast-message').text(data.message || cabinetSmLabels.publicLinkRevoked)
                        $('.toast-top-right.success-message').show(300)
                        setTimeout(function () { $('.toast-top-right.success-message').hide(300) }, 4000)
                    },
                })
            })
        </script>
        <script defer src="{{ asset('plugins/site-monitoring/js/localstorage.js') }}"></script>
    @endslot
@endcomponent
