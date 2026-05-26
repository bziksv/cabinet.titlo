@component('component.card', [
    'title' => __('Tracking the domain registration period'),
    'titleHtml' => e(__('Tracking the domain registration period')) . view('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-domain-information'])->render(),
])
    @slot('css')
        <link rel="stylesheet" type="text/css" href="{{ asset('plugins/toastr/toastr.css') }}"/>
        <link rel="stylesheet" type="text/css" href="{{ asset('plugins/common/css/common.css') }}"/>
        <link rel="stylesheet" type="text/css" href="{{ asset('plugins/common/css/datatable.css') }}"/>
        <link rel="stylesheet" href="{{ asset('css/cabinet-module-kpi.css') }}?v={{ @filemtime(public_path('css/cabinet-module-kpi.css')) ?: time() }}">
        <link rel="stylesheet" href="{{ asset('css/cabinet-domain-information.css') }}?v={{ @filemtime(public_path('css/cabinet-domain-information.css')) ?: time() }}">
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
            <div class="toast-message error-msg">{{ __('The field cannot be empty') }}</div>
        </div>
    </div>
    <div id="toast-container" class="toast-top-right delete-error-message" style="display:none;">
        <div class="toast toast-error" aria-live="assertive">
            <div class="toast-message error-msg">{{ __('You need to select the projects you want to delete') }}</div>
        </div>
    </div>

    <div class="cabinet-di-page">
        @include('domain-information.partials.free-tariff-email-notice')

        @include('domain-information.partials.list-kpi', ['summary' => $listSummary])

        <p class="small text-secondary mb-2 mb-md-0">{{ __('Domain information dns compare hint') }}</p>

        <div class="d-flex flex-wrap align-items-center cabinet-di-toolbar gap-2">
            <a href="{{ route('add.domain.information.view') }}" class="btn btn-primary text-nowrap">
                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('Add tracking the registration period') }}
            </a>
            <button type="button" class="btn btn-outline-danger" id="selectedProjects">
                {{ __('Delete selected projects') }}
            </button>
            <button type="button" class="btn btn-outline-secondary" id="checkSelectedProjects">
                <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>{{ __('Domain information check selected') }}
            </button>
            <span class="text-secondary small ms-auto">
                {{ __('Count tracked projects') }}: <strong id="count-projects">{{ $countProjects }}</strong>
            </span>
        </div>

        <div class="cabinet-di-datatable">
            <table id="table" class="table table-sm table-bordered table-striped align-middle cabinet-di-table w-100 mb-0">
                <thead>
                <tr>
                    <th class="cabinet-di-th-check" aria-label="{{ __('Select') }}"></th>
                    <th>{{ __('Domain') }}</th>
                    <th>{{ __('Domain information notify dns') }}</th>
                    <th>{{ __('Domain information notify expiry') }}</th>
                    <th>{{ __('Last check') }}</th>
                    <th>{{ __('Domain information column dns') }}</th>
                    <th>{{ __('Domain information column registration') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($projects as $project)
                    <tr id="{{ $project->id }}">
                        <td class="cabinet-di-td-check checbox-for-remove-project">
                            <input type="checkbox"
                                   id="project-{{ $project->id }}"
                                   class="cabinet-di-row-select__input"
                                   name="enums"
                                   value="{{ $project->id }}"
                                   aria-label="{{ __('Select') }}: {{ $project->domain }}">
                            <label for="project-{{ $project->id }}" class="cabinet-di-row-select__hit" aria-hidden="true"></label>
                        </td>
                        <td data-order="{{ $project->domain }}">
                            <div class="cabinet-di-cell">
                                {!! Form::text('domain', $project->domain, ['class' => 'form-control form-control-sm information']) !!}
                            </div>
                        </td>
                        <td class="cabinet-di-td-notify-col">
                            <div class="cabinet-di-notify-group">
                                <div class="form-check form-switch form-check-sm mb-1">
                                    <input type="checkbox"
                                           name="check_dns"
                                           class="form-check-input notify"
                                           @if($project->check_dns) checked @endif
                                           id="dns-tg-{{ $project->id }}">
                                    <label class="form-check-label small" for="dns-tg-{{ $project->id }}">{{ __('Telegram') }}</label>
                                </div>
                                <div class="form-check form-switch form-check-sm">
                                    <input type="checkbox"
                                           name="check_dns_email"
                                           class="form-check-input notify"
                                           @if($project->check_dns_email) checked @endif
                                           @if(!($domainInformationEmailAvailable ?? true)) disabled @endif
                                           id="dns-email-{{ $project->id }}">
                                    <label class="form-check-label small @if(!($domainInformationEmailAvailable ?? true)) text-secondary @endif" for="dns-email-{{ $project->id }}">{{ __('Email') }}</label>
                                </div>
                            </div>
                        </td>
                        <td class="cabinet-di-td-notify-col">
                            <div class="cabinet-di-notify-group">
                                <div class="form-check form-switch form-check-sm mb-1">
                                    <input type="checkbox"
                                           name="check_registration_date"
                                           class="form-check-input notify"
                                           @if($project->check_registration_date) checked @endif
                                           id="registration-tg-{{ $project->id }}">
                                    <label class="form-check-label small" for="registration-tg-{{ $project->id }}">{{ __('Telegram') }}</label>
                                </div>
                                <div class="form-check form-switch form-check-sm">
                                    <input type="checkbox"
                                           name="check_registration_date_email"
                                           class="form-check-input notify"
                                           @if($project->check_registration_date_email) checked @endif
                                           @if(!($domainInformationEmailAvailable ?? true)) disabled @endif
                                           id="registration-email-{{ $project->id }}">
                                    <label class="form-check-label small @if(!($domainInformationEmailAvailable ?? true)) text-secondary @endif" for="registration-email-{{ $project->id }}">{{ __('Email') }}</label>
                                </div>
                            </div>
                        </td>
                        <td class="text-nowrap small" data-order="{{ $project->last_check }}">
                            {{ $project->last_check ?: '—' }}
                        </td>
                        @php
                            $diDns = \App\Support\DomainInformationDisplay::dnsBlock($project);
                            $diReg = \App\Support\DomainInformationDisplay::registrationBlock($project);
                        @endphp
                        <td data-order="{{ $diDns }}">
                            <pre class="cabinet-di-info-preview cabinet-di-info-preview--dns mb-0 small text-body-secondary">{{ $diDns }}</pre>
                        </td>
                        <td data-order="{{ $diReg }}">
                            <pre class="cabinet-di-info-preview mb-0 small {{ $project->broken ? 'text-danger' : 'text-body-secondary' }}">{{ $diReg }}</pre>
                        </td>
                        <td class="cabinet-di-actions">
                            <div class="cabinet-di-cell cabinet-di-cell--center cabinet-di-cell--actions">
                                <a href="{{ route('check.domain.information', $project->id) }}"
                                   class="btn btn-outline-secondary btn-sm"
                                   title="{{ __('Run the check manually') }}">
                                    <i class="bi bi-search" aria-hidden="true"></i>
                                </a>
                                <button class="btn btn-outline-primary btn-sm cabinet-di-stats-log" type="button"
                                        data-project-id="{{ $project->id }}"
                                        data-project-domain="{{ $project->domain }}"
                                        title="{{ __('Domain information stats log') }}">
                                    <i class="bi bi-bar-chart-line" aria-hidden="true"></i>
                                </button>
                                <button class="btn btn-outline-danger btn-sm" type="button"
                                        data-bs-toggle="modal"
                                        data-bs-target="#remove-project-id-{{ $project->id }}"
                                        title="{{ __('Delete a domain') }}">
                                    <i class="bi bi-trash" aria-hidden="true"></i>
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
                            <p class="mb-1">{{ __('Delete a project') }} «{{ $project->domain }}»</p>
                            <p class="mb-0 text-secondary">{{ __('Are you sure?') }}</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Back') }}</button>
                            <a href="{{ route('delete.domain.information', $project->id) }}" class="btn btn-danger">
                                {{ __('Delete a domain') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach

        @if(!auth()->user()->isTelegramConnected())
            <p class="small text-secondary mt-3 mb-0">
                {{ __('Want to') }}
                <a href="{{ route('profile.index') }}" target="_blank" rel="noopener noreferrer">
                    {{ __('receive notifications from our telegram bot') }}
                </a>?
            </p>
        @endif

        @include('domain-information.partials.stats-modal')
    </div>

    @slot('js')
        <script src="{{ asset('plugins/toastr/toastr.min.js') }}"></script>
        <script src="{{ asset('plugins/common/js/common.js') }}"></script>
        <script src="{{ asset('plugins/datatables/jquery.dataTables.min.js') }}"></script>
        @include('layouts.partials.vendor-datatables-js', ['bundle' => 'rb-min'])
        <script defer>
            const cabinetDiEmailAvailable = @json($domainInformationEmailAvailable ?? true);
            const cabinetDiLabels = {
                changed: @json(__('Changed')),
                status: @json(__('Status')),
                loading: @json(__('Loading')),
                errorGeneric: @json(__('An error occurred')),
                successChanged: @json(__('Successfully changed')),
                noHistory: @json(__('Domain information stats no history')),
                statsPageInfo: @json(__('Domain information stats page info')),
                publicLinkCreated: @json(__('Public link created')),
                publicLinkRevoked: @json(__('Public link revoked')),
                shareCreate: @json(__('Create public link')),
                shareRefresh: @json(__('Refresh public link')),
                shareRevokeConfirm: @json(__('Domain information share revoke confirm')),
                validUntil: @json(__('Valid until')),
            };
            const cabinetDiModal = {
                kpiTotal: @json(__('Domain information stats total checks')),
                kpiFailed: @json(__('Domain information stats failures')),
                kpiSuccess: @json(__('Domain information stats success rate')),
                checkHistory: @json(__('Domain information check history')),
                colDate: @json(__('Date')),
                colStatus: @json(__('Status')),
                colInfo: @json(__('Domain information')),
                colDns: 'DNS',
                since: @json(__('Domain information since')),
                lastCheck: @json(__('Last check')),
            };
            const cabinetDiDt = {
                search: @json(__('Search') . ':'),
                lengthMenu: @json(__('show') . ' _MENU_ ' . __('records')),
                emptyTable: @json(__('No records')),
                info: @json(__('Showing') . ' ' . __('from') . ' _START_ ' . __('to') . ' _END_ ' . __('of') . ' _TOTAL_ ' . __('entries')),
            };

            function escapeHtml(text) {
                return $('<div>').text(text == null ? '' : String(text)).html();
            }

            function showToastSuccess(msg) {
                $('.toast-top-right.success-message .toast-message').text(msg || cabinetDiLabels.successChanged);
                $('.toast-top-right.success-message').show(300);
                setTimeout(function () { $('.toast-top-right.success-message').hide(300); }, 4000);
            }

            function showToastError(msg) {
                $('.toast-top-right.error-message .toast-message').text(msg || cabinetDiLabels.errorGeneric);
                $('.toast-top-right.error-message').show(300);
                setTimeout(function () { $('.toast-top-right.error-message').hide(300); }, 5000);
            }

            let statsModalProjectId = null;
            let statsModalEl = document.getElementById('cabinetDiStatsModal');
            let statsModal = statsModalEl && typeof bootstrap !== 'undefined' ? new bootstrap.Modal(statsModalEl) : null;

            function renderTimelinePagination(pagination) {
                if (!pagination || pagination.total <= pagination.per_page) {
                    return '';
                }
                const page = pagination.page;
                const last = pagination.last_page;
                const info = cabinetDiLabels.statsPageInfo
                    .replace(':from', String(pagination.from))
                    .replace(':to', String(pagination.to))
                    .replace(':total', String(pagination.total));
                let html = '<nav class="cabinet-di-stats-pagination d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2">';
                html += '<small class="text-secondary mb-0">' + escapeHtml(info) + '</small>';
                html += '<ul class="pagination pagination-sm mb-0">';
                html += '<li class="page-item' + (page <= 1 ? ' disabled' : '') + '"><button type="button" class="page-link cabinet-di-stats-page-btn" data-page="' + (page - 1) + '">«</button></li>';
                html += '<li class="page-item disabled"><span class="page-link text-secondary bg-transparent border-0">' + page + ' / ' + last + '</span></li>';
                html += '<li class="page-item' + (page >= last ? ' disabled' : '') + '"><button type="button" class="page-link cabinet-di-stats-page-btn" data-page="' + (page + 1) + '">»</button></li>';
                html += '</ul></nav>';
                return html;
            }

            function renderStatsModal(data) {
                const s = data.summary;
                const kpis = [
                    { label: cabinetDiModal.kpiTotal, value: s.total_checks, tone: '' },
                    { label: cabinetDiModal.kpiFailed, value: s.failed_checks, tone: s.failed_checks > 0 ? 'danger' : '' },
                    { label: cabinetDiModal.kpiSuccess, value: s.success_rate != null ? s.success_rate + '%' : '—', tone: 'success' },
                ];
                let kpiHtml = '<div class="row g-2 mb-4">';
                kpis.forEach(function (k) {
                    const toneClass = k.tone ? ' cabinet-di-stats-kpi--' + k.tone : '';
                    kpiHtml += '<div class="col-6 col-md-4"><div class="cabinet-di-stats-kpi' + toneClass + '">' +
                        '<div class="cabinet-di-stats-kpi__value">' + escapeHtml(k.value) + '</div>' +
                        '<div class="cabinet-di-stats-kpi__label">' + escapeHtml(k.label) + '</div></div></div>';
                });
                kpiHtml += '</div>';

                let timelineHtml = '<h6 class="fw-semibold mb-2">' + escapeHtml(cabinetDiModal.checkHistory) + '</h6>';
                if (!s.has_history) {
                    timelineHtml += '<p class="text-secondary small">' + escapeHtml(cabinetDiLabels.noHistory) + '</p>';
                } else {
                    timelineHtml += '<div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0 cabinet-di-stats-table">' +
                        '<thead class="table-light"><tr>' +
                        '<th>' + escapeHtml(cabinetDiModal.colDate) + '</th>' +
                        '<th>' + escapeHtml(cabinetDiModal.colStatus) + '</th>' +
                        '<th>' + escapeHtml(cabinetDiModal.colInfo) + '</th>' +
                        '<th>' + escapeHtml(cabinetDiModal.colDns) + '</th><th></th></tr></thead><tbody>';
                    data.timeline.forEach(function (row) {
                        timelineHtml += '<tr class="' + (row.broken ? 'table-danger' : '') + '">' +
                            '<td class="text-nowrap small">' + escapeHtml(row.at) + '</td>' +
                            '<td class="small">' + escapeHtml(row.status) + '</td>' +
                            '<td class="small">' + escapeHtml(row.info_preview) + '</td>' +
                            '<td>' + (row.dns_changed ? '<span class="badge text-bg-warning">' + escapeHtml(cabinetDiLabels.changed) + '</span>' : '—') + '</td>' +
                            '<td class="text-end"><span class="badge text-bg-light text-dark border">' + escapeHtml(row.source_label) + '</span></td>' +
                            '</tr>';
                    });
                    timelineHtml += '</tbody></table></div>';
                    timelineHtml += renderTimelinePagination(data.timeline_pagination);
                }

                const meta = '<p class="small text-secondary mb-3">' +
                    escapeHtml(cabinetDiModal.since) + ': ' + escapeHtml(data.project.created_at) +
                    (s.last_check ? ' · ' + escapeHtml(cabinetDiModal.lastCheck) + ': ' + escapeHtml(s.last_check) : '') +
                    '</p><p class="small mb-3"><strong>' + escapeHtml(cabinetDiLabels.status) + ':</strong> ' + escapeHtml(s.current_status) + '</p>';

                $('#cabinetDiStatsModalBody').html(kpiHtml + meta + timelineHtml);
            }

            function updateStatsSharePanel(share) {
                const $panel = $('#cabinetDiStatsShare');
                const $url = $('#cabinetDiStatsShareUrl');
                const $copy = $('#cabinetDiStatsShareCopy');
                const $revoke = $('#cabinetDiStatsShareRevoke');
                const $create = $('#cabinetDiStatsShareCreate');
                const $expires = $('#cabinetDiStatsShareExpires');
                const $ttl = $('#cabinetDiStatsShareTtl');
                const $unavailable = $('#cabinetDiStatsShareUnavailable');
                share = share || {};
                const shareBackendOn = share.available !== false
                    && $panel.data('feature-available') !== 0
                    && $panel.data('feature-available') !== '0';
                if ($ttl.length && share.ttl_days !== undefined && share.ttl_days !== null) {
                    $ttl.val(String(share.ttl_days));
                }
                if ($unavailable.length) {
                    $unavailable.toggleClass('d-none', shareBackendOn);
                }
                if (!shareBackendOn) {
                    $create.prop('disabled', true);
                    $copy.prop('disabled', true);
                    $revoke.prop('disabled', true);
                    return;
                }
                $create.prop('disabled', false);
                const hasLink = !!share.url;
                $url.val(share.url || '');
                $copy.prop('disabled', !hasLink);
                $revoke.prop('disabled', !hasLink);
                $create.html('<i class="bi bi-link-45deg me-1"></i>' + (hasLink ? escapeHtml(cabinetDiLabels.shareRefresh) : escapeHtml(cabinetDiLabels.shareCreate)));
                if (hasLink && (share.expires_label || share.expires_at)) {
                    $expires.removeClass('d-none text-bg-secondary').addClass('text-bg-success')
                        .text(share.expires_label || (cabinetDiLabels.validUntil + ' ' + share.expires_at));
                } else {
                    $expires.addClass('d-none').removeClass('text-bg-success');
                }
            }

            function loadStatsModal(projectId, page) {
                page = page || 1;
                statsModalProjectId = projectId;
                $('#cabinetDiStatsModalBody').html(
                    '<div class="text-center py-5 text-secondary"><div class="spinner-border spinner-border-sm text-primary"></div>' +
                    '<p class="mt-2 mb-0 small">' + escapeHtml(cabinetDiLabels.loading) + '…</p></div>'
                );
                $.get("{{ route('domain.information.project.stats') }}", { projectId: projectId, page: page }, function (data) {
                    $('#cabinetDiStatsModalSubtitle').text(data.project.domain);
                    renderStatsModal(data);
                    updateStatsSharePanel(data.share || {});
                }).fail(function () {
                    $('#cabinetDiStatsModalBody').html('<p class="text-danger mb-0">' + escapeHtml(cabinetDiLabels.errorGeneric) + '</p>');
                });
            }

            let oldValue = '';
            $('input.notify').on('change', function () {
                const $input = $(this);
                if (!cabinetDiEmailAvailable && ($input.attr('name') === 'check_dns_email' || $input.attr('name') === 'check_registration_date_email')) {
                    $input.prop('checked', false);
                    showToastError(@json(__('Domain information free tariff email notice title')));
                    return;
                }
                $.ajax({
                    type: 'POST',
                    dataType: 'json',
                    url: "{{ route('edit.domain.information') }}",
                    data: {
                        id: $input.closest('tr').attr('id'),
                        name: $input.attr('name'),
                        option: $input.is(':checked') ? 1 : 0,
                        _token: $('meta[name="csrf-token"]').attr('content'),
                    },
                    success: function () { showToastSuccess(); },
                    error: function () { showToastError(); },
                });
            });

            $('.information').on('focus', function () { oldValue = $(this).val(); });
            $('.information').on('blur', function () {
                const $field = $(this);
                if (oldValue === $field.val()) {
                    return;
                }
                $.ajax({
                    type: 'POST',
                    dataType: 'json',
                    url: "{{ route('edit.domain.information') }}",
                    data: {
                        id: $field.closest('tr').attr('id'),
                        name: $field.attr('name'),
                        option: $field.val(),
                        _token: $('meta[name="csrf-token"]').attr('content'),
                    },
                    success: function (response) {
                        if (response.message) {
                            $field.val(response.message);
                        }
                        showToastSuccess();
                    },
                    error: function () { showToastError(); },
                });
            });

            $('#selectedProjects').on('click', function () {
                const ids = $('input[name=enums]:checked').map(function () { return $(this).val(); }).get().join(', ');
                if (!ids) {
                    $('.toast-top-right.delete-error-message').show(300);
                    setTimeout(function () { $('.toast-top-right.delete-error-message').hide(300); }, 4000);
                    return;
                }
                $.post("{{ route('delete.domain-information') }}", {
                    ids: ids,
                    _token: $('meta[name="csrf-token"]').attr('content'),
                }).done(function () { window.location.reload(); })
                    .fail(function () { showToastError(); });
            });

            $('#checkSelectedProjects').on('click', async function () {
                const $projects = $('input[name=enums]:checked');
                if ($projects.length === 0) {
                    showToastError(@json(__('You need to select the projects you want to delete')));
                    return;
                }
                for (let i = 0; i < $projects.length; i++) {
                    await fetch('/check-domain-information/' + $($projects[i]).val(), { credentials: 'same-origin' });
                }
                window.location.reload();
            });

            $(document).on('click', '.cabinet-di-stats-log', function () {
                const projectId = $(this).data('project-id');
                $('#cabinetDiStatsModalSubtitle').text($(this).data('project-domain'));
                if (statsModal) {
                    statsModal.show();
                }
                loadStatsModal(projectId, 1);
            });

            $(document).on('click', '.cabinet-di-stats-page-btn', function () {
                const page = parseInt($(this).data('page'), 10);
                if (!statsModalProjectId || !page || $(this).closest('.page-item').hasClass('disabled')) {
                    return;
                }
                loadStatsModal(statsModalProjectId, page);
            });

            $('#cabinetDiStatsShareCopy').on('click', function () {
                const $url = $('#cabinetDiStatsShareUrl');
                if (!$url.val()) {
                    return;
                }
                const done = function () {
                    const $btn = $('#cabinetDiStatsShareCopy');
                    const html = $btn.html();
                    $btn.html('<i class="bi bi-check2"></i>');
                    setTimeout(function () { $btn.html(html); }, 1500);
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText($url.val()).then(done).catch(function () {
                        $url[0].select();
                        document.execCommand('copy');
                        done();
                    });
                } else {
                    $url[0].select();
                    document.execCommand('copy');
                    done();
                }
            });

            $('#cabinetDiStatsShareCreate').on('click', function () {
                if (!statsModalProjectId) {
                    return;
                }
                const $btn = $(this);
                $btn.prop('disabled', true);
                $.ajax({
                    type: 'POST',
                    url: $('#cabinetDiStatsShare').data('create-url'),
                    dataType: 'json',
                    data: {
                        projectId: statsModalProjectId,
                        ttl_days: $('#cabinetDiStatsShareTtl').val(),
                        _token: $('meta[name="csrf-token"]').attr('content'),
                    },
                    success: function (data) {
                        updateStatsSharePanel({
                            available: true,
                            url: data.url,
                            expires_at: data.expires_at,
                            expires_label: data.expires_label,
                            ttl_days: data.ttl_days,
                        });
                        showToastSuccess(data.message || cabinetDiLabels.publicLinkCreated);
                    },
                    error: function (xhr) {
                        showToastError(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : cabinetDiLabels.errorGeneric);
                    },
                    complete: function () { $btn.prop('disabled', false); },
                });
            });

            $('#cabinetDiStatsPdfBtn').on('click', function () {
                if (!statsModalProjectId) {
                    return;
                }
                const $btn = $(this);
                const pdfUrl = $('#cabinetDiStatsShare').data('pdf-url');
                const token = $('meta[name="csrf-token"]').attr('content');
                const originalHtml = $btn.html();
                $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>' + escapeHtml(cabinetDiLabels.loading) + '…');
                fetch(pdfUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/pdf',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: new URLSearchParams({ projectId: String(statsModalProjectId), _token: token }),
                }).then(function (response) {
                    if (!response.ok) {
                        return response.json().catch(function () { return {}; }).then(function (data) {
                            throw new Error(data.message || cabinetDiLabels.errorGeneric);
                        });
                    }
                    const disposition = response.headers.get('Content-Disposition') || '';
                    const match = disposition.match(/filename="?([^";]+)"?/);
                    const fileName = match ? match[1] : 'domain-information-report.pdf';
                    return response.blob().then(function (blob) {
                        const url = URL.createObjectURL(blob);
                        const link = document.createElement('a');
                        link.href = url;
                        link.download = fileName;
                        document.body.appendChild(link);
                        link.click();
                        link.remove();
                        URL.revokeObjectURL(url);
                    });
                }).catch(function (error) {
                    showToastError(error.message);
                }).finally(function () {
                    $btn.prop('disabled', false).html(originalHtml);
                });
            });

            $('#cabinetDiStatsShareRevoke').on('click', function () {
                if (!statsModalProjectId || !window.confirm(cabinetDiLabels.shareRevokeConfirm)) {
                    return;
                }
                $.ajax({
                    type: 'POST',
                    url: $('#cabinetDiStatsShare').data('revoke-url'),
                    dataType: 'json',
                    data: {
                        projectId: statsModalProjectId,
                        _token: $('meta[name="csrf-token"]').attr('content'),
                    },
                    success: function (data) {
                        updateStatsSharePanel({ available: true, url: null, expires_at: null });
                        showToastSuccess(data.message || cabinetDiLabels.publicLinkRevoked);
                    },
                });
            });

            $(document).ready(function () {
                const table = $('#table').DataTable({
                    dom: '<"row align-items-center g-2 cabinet-di-dt-controls"<"col-sm-auto"l><"col-sm-auto ms-auto"f>>rt<"row align-items-center g-2 cabinet-di-dt-footer"<"col-sm-auto"i><"col-sm-auto ms-auto"p>>',
                    autoWidth: false,
                    order: [[1, 'asc']],
                    columnDefs: [
                        { targets: 0, orderable: false, searchable: false, width: '3.5rem', className: 'cabinet-di-td-check' },
                        { targets: [5, 6], orderable: true, searchable: true },
                        { targets: 7, orderable: false, searchable: false },
                    ],
                    language: { paginate: { first: '«', last: '»', next: '»', previous: '«' } },
                    oLanguage: {
                        sSearch: cabinetDiDt.search,
                        sLengthMenu: cabinetDiDt.lengthMenu,
                        sEmptyTable: cabinetDiDt.emptyTable,
                        sInfo: cabinetDiDt.info,
                    },
                });
                if (typeof search === 'function') {
                    search(table);
                }
            });
        </script>
    @endslot
@endcomponent
