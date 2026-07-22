@extends('layouts.app')

@section('title', __('Users'))

@section('css')
    @include('layouts.partials.vendor-datatables-css', ['bundle' => 'rb-min'])
    <link rel="stylesheet" href="{{ asset('plugins/toastr/toastr.min.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.css') }}">
    <link rel="stylesheet" href="{{ asset('css/cabinet-users.css') }}">
@endsection

@section('content')
    <div class="cabinet-users-page">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
            <div>
                <h2 class="h4 mb-2 d-flex flex-wrap align-items-center gap-1">
                    <i class="bi bi-people text-primary" aria-hidden="true"></i>
                    <span>{{ __('Users') }}</span>
                    @include('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-users'])
                </h2>
                <p class="text-secondary small mb-0">{{ __('Accounts, tariffs, roles. Use filters or table search.') }}</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('users.statistics') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-graph-up me-1"></i>{{ __('General statistics users') }}
                </a>
                <a href="{{ route('statistics.modules') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-grid me-1"></i>{{ __('General statistics modules') }}
                </a>
            </div>
        </div>

        <div class="row g-2 g-md-3 mb-3 cabinet-users-stat">
            <div class="col-6 col-lg-3 d-flex">
                <div class="info-box mb-0 flex-fill">
                    <span class="info-box-icon text-bg-primary shadow-sm"><i class="bi bi-people"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Total users') }}</span>
                        <span class="info-box-number">{{ number_format($stats['total'], 0, ',', ' ') }}</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3 d-flex">
                <div class="info-box mb-0 flex-fill">
                    <span class="info-box-icon text-bg-success shadow-sm"><i class="bi bi-patch-check"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Verified') }}</span>
                        <span class="info-box-number">{{ number_format($stats['verified'], 0, ',', ' ') }}</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3 d-flex">
                <a href="{{ route('users.index', ['telegram' => '1']) }}" class="info-box mb-0 flex-fill text-body text-decoration-none cabinet-users-stat-link">
                    <span class="info-box-icon text-bg-info shadow-sm"><i class="bi bi-telegram"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Telegram connected') }}</span>
                        <span class="info-box-number">{{ number_format($stats['telegram'], 0, ',', ' ') }}</span>
                        @if($stats['total'] > 0)
                            <span class="info-box-text small">{{ round($stats['telegram'] / $stats['total'] * 100, 1) }}%</span>
                        @endif
                    </div>
                </a>
            </div>
            <div class="col-6 col-lg-3 d-flex">
                <div class="info-box mb-0 flex-fill">
                    <span class="info-box-icon text-bg-warning shadow-sm"><i class="bi bi-tag"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('With active tariff') }}</span>
                        <span class="info-box-number">{{ number_format($stats['with_tariff'], 0, ',', ' ') }}</span>
                    </div>
                </div>
            </div>
        </div>

        @include('users.partials.filters')

        @include('partials.monitoring-stale-schedules', [
            'staleMonitoring' => $staleMonitoring,
            'staleIdPrefix' => 'cabinet-users-stale',
            'staleExpanded' => false,
            'staleShowLogic' => false,
            'staleTitle' => __('Users stale monitoring title'),
            'staleFilterOnUsersPage' => true,
        ])

        @include('users.partials.storage-footprint', ['footprintRefreshedAt' => $footprintRefreshedAt ?? null])

        <div class="card cabinet-users-card shadow-sm">
            <div class="card-header">
                <h3 class="card-title">{{ __('User list') }}</h3>
                <div class="card-tools">
                    <div class="dropdown">
                        <button type="button"
                                class="btn btn-sm btn-outline-secondary dropdown-toggle"
                                data-bs-toggle="dropdown"
                                aria-expanded="false">
                            <i class="bi bi-download me-1"></i>{{ __('Export and actions') }}
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="{{ route('get.verified.users', 'xls') }}">
                                    <i class="bi bi-file-earmark-excel me-2 text-success"></i>Excel
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="{{ route('get.verified.users', 'csv') }}">
                                    <i class="bi bi-filetype-csv me-2 text-primary"></i>CSV
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#exportModal">
                                    <i class="bi bi-funnel me-2"></i>{{ __('User Upload Filter') }}
                                </button>
                            </li>
                            <li>
                                <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#assignTariffModal">
                                    <i class="bi bi-tag me-2"></i>{{ __('Assign tariff') }}
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="card-body border-bottom py-3 cabinet-users-search-bar">
                <label class="form-label small fw-semibold mb-1" for="cabinet-users-q">
                    <i class="bi bi-search me-1"></i>{{ __('Smart search') }}
                </label>
                <div class="input-group">
                    <span class="input-group-text text-secondary"><i class="bi bi-person-lines-fill" aria-hidden="true"></i></span>
                    <input type="search"
                           class="form-control"
                           id="cabinet-users-q"
                           name="filter_q"
                           autocomplete="off"
                           spellcheck="false"
                           placeholder="{{ __('Name, surname, email or user ID') }}"
                           aria-describedby="cabinet-users-q-hint">
                    <button type="button"
                            class="btn btn-outline-secondary d-none"
                            id="cabinet-users-q-clear"
                            title="{{ __('Clear search') }}">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div id="cabinet-users-q-hint" class="form-text">
                    {{ __('Examples: Ivanov, ivan@mail.ru, Ivan Petrov. Several words — all must match.') }}
                </div>
            </div>
            <div class="card-body p-0 cabinet-users-table-wrap">
                <table class="table table-striped table-hover align-middle mb-0 w-100" id="service-users" width="100%"></table>
            </div>
        </div>

        @include('users.partials.activity-dashboard', ['activity' => $activity])
    </div>

    @include('users.modal.index', ['id' => 'exportModal', 'action' => route('filter.exports.users'), 'title' => __('User Upload Filter')])
    @include('users.modal.index', ['id' => 'assignTariffModal', 'action' => route('users.tariff'), 'title' => __('Assign tariff')])
    @include('users.partials.inactive-purge-modal')
@endsection

@section('js')
    <script src="{{ asset('plugins/chart.js/3.9.1/chart.js') }}"></script>
    <script src="{{ asset('plugins/toastr/toastr.min.js') }}"></script>
    <script src="{{ asset('plugins/select2/js/select2.js') }}"></script>
    <script src="{{ asset('plugins/datatables/jquery.dataTables.min.js') }}"></script>
    @include('layouts.partials.vendor-datatables-js', ['bundle' => 'rb-min'])
    <script>
        $(function () {
            var activityChartData = @json($activity['chart'] ?? ['labels' => [], 'values' => []]);
            var activityCanvas = document.getElementById('cabinet-users-activity-chart');
            var activityChartEmpty = document.getElementById('cabinet-users-activity-chart-empty');
            if (activityCanvas && typeof Chart !== 'undefined' && activityChartData.labels && activityChartData.labels.length) {
                if (activityChartEmpty) {
                    activityChartEmpty.classList.add('d-none');
                }
                activityCanvas.classList.remove('d-none');
                new Chart(activityCanvas.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: activityChartData.labels,
                        datasets: [{
                            label: @json(__('Active users')),
                            data: activityChartData.values,
                            borderColor: '#0d6efd',
                            backgroundColor: 'rgba(13, 110, 253, 0.12)',
                            fill: true,
                            tension: 0.3,
                            pointRadius: 2,
                            pointHoverRadius: 5,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {display: false},
                        },
                        scales: {
                            x: {
                                grid: {display: false},
                                ticks: {maxTicksLimit: 10, maxRotation: 0},
                            },
                            y: {
                                beginAtZero: true,
                                ticks: {precision: 0},
                            },
                        },
                    },
                });
            } else if (activityCanvas && activityChartEmpty) {
                activityCanvas.classList.add('d-none');
                activityChartEmpty.classList.remove('d-none');
            }

            var routes = {
                login: @json(url('/users/__ID__/login')),
                edit: @json(url('/users/__ID__/edit')),
                stats: @json(url('/visit-statistics/__ID__')),
                destroy: @json(url('/users/__ID__')),
                inactivePurgePreview: @json(route('users.inactive-purge.preview')),
                inactivePurge: @json(route('users.inactive-purge')),
            };

            function userUrl(template, id) {
                return template.replace('__ID__', id);
            }

            function cabinetUsersFilterPayload(d) {
                d.filter_q = $('#cabinet-users-q').val().trim();
                d.filter_verify = $('#filter-verify').val() || '';
                d.filter_role = $('#filter-role').val() || '';
                d.filter_active_tariffs = $('#filter-active-tariffs').val() || [];
                d.filter_online = $('#filter-online').val() || '';
                d.filter_statistic = $('#filter-statistic').val() || '';
                d.filter_telegram = $('#filter-telegram').val() || '';
                d.filter_id_from = $('#filter-id-from').val() || '';
                d.filter_id_to = $('#filter-id-to').val() || '';
                d.filter_stale_monitoring = $('#filter-stale-monitoring').val() || '';
                if (d.search) {
                    d.search.value = d.filter_q;
                }
            }

            var searchDebounceTimer;

            function cabinetUsersToggleSearchClear() {
                var has = ($('#cabinet-users-q').val() || '').trim().length > 0;
                $('#cabinet-users-q-clear').toggleClass('d-none', !has);
            }

            function cabinetUsersReloadFromSearch() {
                usersTable.ajax.reload();
                cabinetUsersToggleSearchClear();
            }

            function cabinetUsersUpdateFilterBadge() {
                var n = 0;
                $('[data-filter]').each(function () {
                    var $el = $(this);
                    if ($el.data('filter') === 'multi') {
                        if (($el.val() || []).length > 0) {
                            n++;
                        }
                    } else if (($el.val() || '').toString().trim() !== '') {
                        n++;
                    }
                });
                var $badge = $('#cabinet-users-filters-active');
                if (n > 0) {
                    $badge.text(n).removeClass('d-none');
                } else {
                    $badge.addClass('d-none');
                }
            }

            var urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('verify') === 'verified') {
                $('#filter-verify').val('verified');
            }
            if (urlParams.get('telegram') === '1') {
                $('#filter-telegram').val('1');
            }
            if (urlParams.get('verify') || urlParams.get('telegram')) {
                cabinetUsersUpdateFilterBadge();
            }

            var usersTable = $('#service-users').DataTable({
                dom: '<"row align-items-center justify-content-end g-2 cabinet-users-dt-top"<"col-sm-auto"l>>rt<"row align-items-center g-2"<"col-sm-auto"i><"col-sm"p>>',
                autoWidth: false,
                lengthMenu: [10, 25, 50, 100],
                pageLength: 50,
                pagingType: 'simple_numbers',
                searching: false,
                language: {
                    lengthMenu: '_MENU_',
                    paginate: {
                        first: '«',
                        last: '»',
                        next: '›',
                        previous: '‹',
                    },
                    processing: '<div class="spinner-border spinner-border-sm text-primary" role="status"></div>',
                    emptyTable: @json(__('No data available in table')),
                    zeroRecords: @json(__('No matching records found')),
                    info: @json(__('Showing _START_–_END_ of _TOTAL_')),
                    infoFiltered: @json(__('(filtered from _MAX_ total)')),
                },
                processing: true,
                serverSide: true,
                ajax: {
                    url: @json(route('users.index')),
                    type: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    data: function (d) {
                        cabinetUsersFilterPayload(d);
                    },
                    error: function (xhr) {
                        console.error('users DataTable ajax error', xhr.status, xhr.responseText);
                        if (typeof toastr !== 'undefined') {
                            var msg = @json(__('Failed to load users. Refresh the page or reset filters.'));
                            if (xhr.status === 0 || xhr.status >= 500) {
                                msg = @json(__('Server timeout or error. Try reset filters or reload the page.'));
                            }
                            toastr.error(msg);
                        }
                    },
                },
                order: [[0, 'desc']],
                createdRow: function (row, data) {
                    $(row).find('td').eq(6).attr('data-order', data.telegram_sort || 0);
                    $(row).find('td').eq(7).attr('data-order', data.last_online_strtotime || 0);
                    var storageOrder = data.storage ? (data.storage.rows != null ? data.storage.rows : -1) : -1;
                    $(row).find('td').eq(8).attr('data-order', storageOrder);
                },
                columnDefs: [
                    {orderable: true, targets: [0, 1, 2, 3, 4, 5, 6, 7, 8]},
                    {orderable: false, targets: [9]},
                    {className: 'cabinet-users-storage-col text-nowrap', targets: [8]},
                    {className: 'cabinet-users-actions-col text-nowrap', targets: [9]},
                ],
                columns: [
                    {
                        name: 'id',
                        title: @json(__('ID')),
                        data: 'id',
                        className: 'text-nowrap',
                    },
                    {
                        name: 'name',
                        title: @json(__('Name')),
                        data: function (row) {
                            var name = ((row.name || '') + ' ' + (row.last_name || '')).trim();
                            return name || '—';
                        },
                    },
                    {
                        name: 'email',
                        title: @json(__('Email')),
                        data: function (row) {
                            var html = '<div class="text-break">' + $('<div>').text(row.email).html() + '</div>';
                            if (row.email_verified_at) {
                                html += '<span class="badge text-bg-success mt-1">' + @json(__('VERIFIED')) + '</span> ';
                            }
                            if (row.read_letter) {
                                html += '<span class="badge text-bg-primary mt-1">' + @json(__('The letter has been read')) + '</span>';
                            }
                            return html;
                        },
                    },
                    {
                        name: 'tariff',
                        title: @json(__('Tariff')),
                        data: function (row) {
                            var tariff = row.tariff || {};
                            if (!tariff.name) {
                                return '<span class="text-secondary">—</span>';
                            }
                            var badgeClass = tariff.is_free ? 'text-bg-secondary' : 'text-bg-warning';
                            var html = '<span class="badge ' + badgeClass + '">' + $('<div>').text(tariff.name).html() + '</span>';
                            if (tariff.active_to) {
                                html += '<br><small class="text-secondary">' + @json(__('Active until')) + ':</small><br>' +
                                    '<small>' + $('<div>').text(tariff.active_to).html() + '</small><br>' +
                                    '<small class="text-secondary">' + $('<div>').text(tariff.active_to_diffForHumans || '').html() + '</small>';
                            } else if (tariff.role_only) {
                                html += '<br><small class="text-secondary">' + @json(__('Users tariff role only')) + '</small>';
                            }
                            return html;
                        },
                    },
                    {
                        name: 'created_at',
                        title: @json(__('Created')),
                        data: function (row) {
                            return row.created + '<br><small class="text-secondary">' + row.created_diffForHumans + '</small>';
                        },
                        className: 'text-nowrap',
                    },
                    {
                        name: 'roles',
                        title: @json(__('Roles')),
                        data: function (row) {
                            var roles = row.roles || [];
                            if (!roles.length) {
                                return '<span class="text-secondary">—</span>';
                            }
                            var html = '';
                            $.each(roles, function (i, el) {
                                html += '<span class="badge text-bg-secondary me-1 mb-1">' + $('<div>').text(el.name).html() + '</span>';
                            });
                            return html;
                        },
                    },
                    {
                        name: 'telegram',
                        title: @json(__('Telegram')),
                        data: function (row) {
                            if (row.telegram_connected) {
                                var html = '<span class="badge text-bg-info"><i class="bi bi-telegram me-1" aria-hidden="true"></i>' +
                                    @json(__('Telegram connected')) + '</span>';
                                if (row.telegram_chat_id) {
                                    html += '<br><small class="text-secondary">ID ' +
                                        $('<div>').text(row.telegram_chat_id).html() + '</small>';
                                }
                                return html;
                            }
                            return '<span class="text-secondary small">' + @json(__('Telegram not connected')) + '</span>';
                        },
                        className: 'text-nowrap',
                    },
                    {
                        name: 'last_online_at',
                        title: @json(__('Was online')),
                        data: function (row) {
                            if (!row.last_online) {
                                return '<span class="text-secondary">' + @json(__('Never')) + '</span>';
                            }
                            return row.last_online + '<br><small class="text-secondary">' + row.last_online_diffForHumans + '</small>';
                        },
                        className: 'text-nowrap',
                    },
                    {
                        name: 'storage_footprint',
                        title: @json(__('Users storage footprint column')),
                        data: function (row) {
                            if (!row.storage) {
                                return '<span class="text-secondary small">' + @json(__('Users storage footprint not computed')) + '</span>';
                            }
                            return '<span class="small">' + $('<div>').text(row.storage.label).html() + '</span>';
                        },
                        className: 'text-nowrap',
                    },
                    {
                        title: @json(__('Actions')),
                        data: function (row) {
                            var html = '<div class="cabinet-users-actions">';
                            html += '<a class="btn btn-outline-secondary btn-sm" href="' + userUrl(routes.login, row.id) + '" title="' + @json(__('Login')) + '"><i class="bi bi-box-arrow-in-right"></i></a>';
                            html += '<a class="btn btn-outline-primary btn-sm" href="' + userUrl(routes.edit, row.id) + '" title="' + @json(__('Edit')) + '"><i class="bi bi-pencil"></i></a>';
                            html += '<a class="btn btn-outline-info btn-sm" href="' + userUrl(routes.stats, row.id) + '" title="' + @json(__('User statistic')) + '"><i class="bi bi-pie-chart"></i></a>';

                            if (row.metrics) {
                                html += '<a class="btn btn-outline-secondary btn-sm" data-bs-toggle="collapse" href="#metrics-' + row.id + '" title="' + @json(__('utm metrics')) + '"><i class="bi bi-share"></i></a>';
                            }

                            html += '<button type="button" class="btn btn-outline-danger btn-sm" onclick="deleteUser(' + row.id + ')" title="' + @json(__('Delete')) + '"><i class="bi bi-trash"></i></button>';
                            html += '</div>';

                            if (row.metrics) {
                                html += metricsBlock(row);
                            }

                            return html;
                        },
                    },
                ],
                initComplete: function () {
                    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                        document.querySelectorAll('#service-users [title]').forEach(function (el) {
                            if (!bootstrap.Tooltip.getInstance(el)) {
                                new bootstrap.Tooltip(el);
                            }
                        });
                    }
                },
                drawCallback: function () {
                    if (typeof window.cabinetUsersEnsureVisibleFootprints === 'function') {
                        window.cabinetUsersEnsureVisibleFootprints(this.api());
                    }
                },
            });

            (function applyStaleFilterFromQuery() {
                try {
                    var params = new URLSearchParams(window.location.search);
                    if (params.get('filter_stale_monitoring') === '1') {
                        $('#filter-stale-monitoring').val('1');
                        cabinetUsersUpdateFilterBadge();
                        usersTable.ajax.reload();
                    }
                } catch (e) {
                }
            })();

            $('#cabinet-users-filters-apply').on('click', function () {
                usersTable.ajax.reload();
                cabinetUsersUpdateFilterBadge();
            });

            $('#cabinet-users-filters-reset').on('click', function () {
                $('[data-filter]').not('[data-filter="multi"]').val('');
                $('#filter-active-tariffs').val(null).trigger('change');
                $('#cabinet-users-q').val('');
                usersTable.ajax.reload();
                cabinetUsersUpdateFilterBadge();
                cabinetUsersToggleSearchClear();
            });

            $('[data-filter]').on('change', cabinetUsersUpdateFilterBadge);

            if ($('#filter-active-tariffs').length && $.fn.select2) {
                $('#filter-active-tariffs').select2({
                    width: '100%',
                    theme: 'bootstrap4',
                    placeholder: $('#filter-active-tariffs').data('placeholder') || '',
                    allowClear: true,
                    closeOnSelect: false,
                });
            }

            $('#cabinet-users-q').on('input', function () {
                cabinetUsersToggleSearchClear();
                clearTimeout(searchDebounceTimer);
                searchDebounceTimer = setTimeout(cabinetUsersReloadFromSearch, 400);
            });

            $('#cabinet-users-q').on('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    clearTimeout(searchDebounceTimer);
                    cabinetUsersReloadFromSearch();
                }
            });

            $('#cabinet-users-q-clear').on('click', function () {
                $('#cabinet-users-q').val('').trigger('focus');
                cabinetUsersReloadFromSearch();
            });

            if ($('#select-users').length) {
            $('#select-users').select2({
                width: '100%',
                placeholder: $('#select-users').data('placeholder') || '',
                allowClear: true,
                minimumInputLength: 2,
                dropdownParent: $('#assignTariffModal'),
                ajax: {
                    url: @json(route('users.search-emails')),
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {q: params.term || ''};
                    },
                    processResults: function (data) {
                        return {results: data.results || []};
                    },
                    cache: true,
                },
            });
            }
        });

        function deleteUser(id) {
            if (!window.confirm(@json(__('Do you really want to delete?')))) {
                return false;
            }

            var token = document.querySelector('meta[name="csrf-token"]');
            var url = @json(url('/users/__ID__')).replace('__ID__', id);

            var reload = function () {
                var dt = $('#service-users').DataTable();
                if (dt) {
                    dt.ajax.reload(null, false);
                }
            };

            if (typeof axios !== 'undefined') {
                axios.post(url, {_method: 'DELETE'}).then(reload);
            } else {
                fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({_method: 'DELETE'}),
                }).then(reload);
            }

            return false;
        }

        function metricsBlock(user) {
            var collapse = $('<div />', {
                class: 'collapse cabinet-users-metrics text-start mt-2',
                id: 'metrics-' + user.id,
            });

            try {
                var info = typeof user.metrics === 'string' ? JSON.parse(user.metrics) : user.metrics;
                $.each(info, function (k, v) {
                    collapse.append(
                        $('<div />', {class: 'row-metrics'}).html(
                            '<strong>' + $('<div>').text(k).html() + '</strong>: ' + $('<div>').text(decodeURIComponent(v)).html()
                        )
                    );
                });
            } catch (e) {
            }

            return $('<div />').append(collapse)[0].outerHTML;
        }

        function cabinetUsersPurgeInactive(years) {
            var previewUrl = @json(route('users.inactive-purge.preview'));
            var purgeUrl = @json(route('users.inactive-purge'));
            var csrf = $('meta[name="csrf-token"]').attr('content');
            var $btns = $('#cabinet-users-purge-2y, #cabinet-users-purge-3y');
            var $modalEl = document.getElementById('cabinetUsersInactivePurgeModal');
            if (!$modalEl || typeof bootstrap === 'undefined') {
                alert(@json(__('Users admin action error')));
                return;
            }
            var modal = bootstrap.Modal.getOrCreateInstance($modalEl);
            var state = {years: years, code: 'DELETE ' + years + 'Y', count: 0};

            $btns.prop('disabled', true);
            $('#cabinet-users-purge-loading').removeClass('d-none');
            $('#cabinet-users-purge-body, #cabinet-users-purge-error').addClass('d-none');
            $('#cabinet-users-purge-confirm-btn').prop('disabled', true);
            $('#cabinet-users-purge-confirm-input').val('');
            modal.show();

            $.ajax({
                url: previewUrl,
                method: 'POST',
                data: {_token: csrf, years: years},
                success: function (preview) {
                    $('#cabinet-users-purge-loading').addClass('d-none');
                    state.count = preview.count || 0;
                    if (!state.count) {
                        $('#cabinet-users-purge-error')
                            .removeClass('d-none')
                            .text(@json(__('Users inactive purge empty')));
                        $btns.prop('disabled', false);
                        return;
                    }

                    var storage = preview.storage || {};
                    var summary = @json(__('Users inactive purge summary'))
                        .replace(':count', String(state.count))
                        .replace(':years', String(years));
                    $('#cabinet-users-purge-summary').text(summary);
                    $('#cabinet-users-purge-storage').html(
                        '<strong>' + @json(__('Users inactive purge storage title')) + '</strong>: '
                        + $('<div>').text(storage.est_label || '0').html()
                        + '<div class="mt-1 text-secondary">' + $('<div>').text(storage.note || '').html() + '</div>'
                    );

                    var $mods = $('#cabinet-users-purge-modules').empty();
                    (storage.modules || []).forEach(function (m) {
                        $mods.append(
                            $('<li/>').text(
                                (m.label || '') + ': '
                                + Number(m.rows || 0).toLocaleString('ru-RU')
                                + ' · ~' + Number(m.est_mb || 0).toLocaleString('ru-RU') + ' MB'
                            )
                        );
                    });
                    if (!(storage.modules || []).length) {
                        $mods.append($('<li/>').text('—'));
                    }

                    var sample = (preview.sample || []).map(function (u) {
                        return u.email + (u.last_online_at ? ' (' + u.last_online_at + ')' : '');
                    }).join('\n');
                    $('#cabinet-users-purge-sample').text(sample || '—');
                    $('#cabinet-users-purge-code').text(state.code);
                    $('#cabinet-users-purge-body').removeClass('d-none');
                    $('#cabinet-users-purge-confirm-input').trigger('focus');
                },
                error: function (xhr) {
                    $('#cabinet-users-purge-loading').addClass('d-none');
                    var m = (xhr.responseJSON && xhr.responseJSON.message)
                        ? xhr.responseJSON.message
                        : @json(__('Users admin action error'));
                    $('#cabinet-users-purge-error').removeClass('d-none').text(m);
                    $btns.prop('disabled', false);
                },
            });

            $('#cabinet-users-purge-confirm-input').off('input.purge').on('input.purge', function () {
                var ok = ($(this).val() || '') === state.code && state.count > 0;
                $('#cabinet-users-purge-confirm-btn').prop('disabled', !ok);
            });

            $('#cabinet-users-purge-confirm-btn').off('click.purge').on('click.purge', function () {
                if (($('#cabinet-users-purge-confirm-input').val() || '') !== state.code) {
                    return;
                }
                var $btn = $(this).prop('disabled', true);
                $('#cabinet-users-purge-loading').removeClass('d-none');
                $('#cabinet-users-purge-body').addClass('d-none');
                $.ajax({
                    url: purgeUrl,
                    method: 'POST',
                    data: {_token: csrf, years: state.years, confirm: state.code},
                    timeout: 0,
                    success: function (res) {
                        modal.hide();
                        var done = @json(__('Users inactive purge done'))
                            .replace(':deleted', String(res.deleted || 0))
                            .replace(':failed', String(res.failed || 0));
                        if (window.toastr) {
                            toastr.success(done);
                        } else {
                            alert(done);
                        }
                        if (window.usersTable) {
                            usersTable.ajax.reload(null, false);
                        }
                    },
                    error: function (xhr) {
                        $('#cabinet-users-purge-loading').addClass('d-none');
                        $('#cabinet-users-purge-body').removeClass('d-none');
                        var m = (xhr.responseJSON && xhr.responseJSON.message)
                            ? xhr.responseJSON.message
                            : @json(__('Users admin action error'));
                        $('#cabinet-users-purge-error').removeClass('d-none').text(m);
                        $btn.prop('disabled', false);
                    },
                    complete: function () {
                        $btns.prop('disabled', false);
                    },
                });
            });

            $modalEl.addEventListener('hidden.bs.modal', function onHide() {
                $btns.prop('disabled', false);
                $modalEl.removeEventListener('hidden.bs.modal', onHide);
            });
        }

        $(document).on('click', '#cabinet-users-purge-2y, #cabinet-users-purge-3y', function () {
            cabinetUsersPurgeInactive(parseInt($(this).data('years'), 10) || 0);
        });
    </script>
    <script>
        window.cabinetUsersAdminConfig = {
            usersTotal: {{ (int) ($stats['total'] ?? 0) }},
            footprintRefreshUrl: @json(route('users.storage-footprint.refresh')),
            usersEditUrlTemplate: @json(url('/users/__ID__/edit')),
            i18n: {
                confirmRefreshAll: @json(__('Users storage footprint confirm refresh all')),
                refreshedAll: @json(__('Users storage footprint refreshed all')),
                refreshProgress: @json(__('Users storage footprint refresh progress')),
                progressTitle: @json(__('Users storage footprint progress title')),
                progressStatus: @json(__('Users storage footprint progress status')),
                progressPhaseVisible: @json(__('Users storage footprint progress phase visible')),
                progressErrors: @json(__('Users storage footprint progress errors')),
                progressDone: @json(__('Users storage footprint progress done')),
                progressAlreadyRunning: @json(__('Users storage footprint progress already running')),
                error: @json(__('Users admin action error')),
            },
        };
    </script>
    <script src="{{ asset('js/cabinet-button-busy.js') }}?v={{ @filemtime(public_path('js/cabinet-button-busy.js')) ?: time() }}"></script>
    <script>
        window.cabinetMonitoringStaleSchedulesConfig = {
            idPrefix: 'cabinet-users-stale',
            staleInactiveDays: {{ (int) ($staleMonitoring['inactive_days'] ?? 90) }},
            staleListUrl: @json(route('monitoring.admin.stale-schedules.list')),
            staleDisableUrl: @json(route('monitoring.admin.stale-schedules.disable')),
            usersEditUrlTemplate: @json(url('/users/__ID__/edit')),
            reloadUsersTable: true,
            i18n: {
                staleEmpty: @json(__('Users stale monitoring empty')),
                never: @json(__('Never')),
                disableProject: @json(__('Users stale monitoring disable project')),
                disableUser: @json(__('Users stale monitoring disable user')),
                confirmDisable: @json(__('Users stale monitoring confirm disable')),
                disabled: @json(__('Users stale monitoring disabled toast')),
                disabling: @json(__('Monitoring admin stale schedules disabling')),
                error: @json(__('An error has occurred')),
            },
        };
    </script>
    <script src="{{ asset('js/cabinet-monitoring-stale-schedules.js') }}?v={{ @filemtime(public_path('js/cabinet-monitoring-stale-schedules.js')) ?: time() }}"></script>
    <script src="{{ asset('js/cabinet-users-admin.js') }}?v={{ @filemtime(public_path('js/cabinet-users-admin.js')) ?: time() }}"></script>
@endsection
