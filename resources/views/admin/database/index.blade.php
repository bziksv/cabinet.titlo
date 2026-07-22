@extends('layouts.app')

@section('title', __('Database management'))

@section('css')
    <link rel="stylesheet" href="{{ asset('css/cabinet-database-admin.css') }}?v={{ @filemtime(public_path('css/cabinet-database-admin.css')) ?: time() }}">
@endsection

@section('content')
    @php
        $summary = $snapshot['summary'] ?? [];
        $allTables = $snapshot['tables'] ?? [];
        $filter = $filter ?? 'all';
        $largeMb = (int) config('cabinet-database-admin.large_table_mb', 100);

        $clearableTables = array_flip(config('cabinet-database-admin.clearable_tables', []));

        $filtered = array_filter($allTables, static function (array $t) use ($filter, $largeMb) {
            if ($filter === 'orphan') {
                return ($t['status'] ?? '') === 'orphan';
            }
            if ($filter === 'large') {
                return ($t['size_mb'] ?? 0) >= $largeMb;
            }
            if ($filter === 'system') {
                return ($t['status'] ?? '') === 'system';
            }
            if ($filter === 'nostale') {
                return empty($t['data_max']);
            }

            return true;
        });
    @endphp

    <div class="cabinet-database-admin-page">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
            <div>
                <h2 class="h4 mb-2 d-flex flex-wrap align-items-center gap-1">
                    <i class="bi bi-database-gear text-primary" aria-hidden="true"></i>
                    <span>{{ __('Database management') }}</span>
                    @include('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-database-admin'])
                </h2>
                <p class="text-secondary small mb-0" style="max-width: 48rem;">
                    {{ __('Inventory of MySQL tables: size, module mapping, Eloquent models, code references, foreign keys. Date scan: :batch tables per click; over :mb MB — light mode (by id, not full MIN/MAX).', [
                        'batch' => config('cabinet-database-admin.date_probe_batch_size', 5),
                        'mb' => config('cabinet-database-admin.date_probe_light_above_mb', 500),
                    ]) }}
                </p>
                @if(isset($dateProbeRemaining) && $dateProbeRemaining > 0)
                    <p class="small text-warning mb-0 mt-1">{{ __('Database date scan remaining', ['count' => $dateProbeRemaining]) }}</p>
                @endif
            </div>
            <div class="d-flex flex-wrap gap-2">
                <form action="{{ route('admin.database.refresh') }}" method="post" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>{{ __('Refresh metadata') }}
                    </button>
                </form>
                <form action="{{ route('admin.database.probe-dates') }}" method="post" class="d-inline"
                      onsubmit='return confirm(@json(__('Run MIN/MAX on the next batch of tables? Sequential, safe for large DB.')));'>
                    @csrf
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-calendar-range me-1" aria-hidden="true"></i>
                        @if(isset($dateProbeRemaining) && $dateProbeRemaining > 0)
                            {{ __('Scan data dates continue', ['count' => $dateProbeRemaining]) }}
                        @else
                            {{ __('Scan data dates') }}
                        @endif
                    </button>
                </form>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
            </div>
        @endif

        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="card shadow-sm db-summary-card h-100">
                    <div class="card-body py-3">
                        <div class="text-secondary small">{{ __('Total size') }}</div>
                        <div class="h4 mb-0">{{ $summary['total_gb'] ?? '—' }} <span class="fs-6 text-secondary">GB</span></div>
                        <div class="small text-secondary">{{ $summary['total_mb'] ?? '' }} MB · {{ $summary['tables'] ?? 0 }} {{ __('tables') }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card shadow-sm db-summary-card h-100">
                    <div class="card-body py-3">
                        <div class="text-secondary small">{{ __('Database') }}</div>
                        <div class="h6 mb-0 text-break">{{ $snapshot['database'] ?? '' }}</div>
                        <div class="small text-secondary">{{ $snapshot['host'] ?? '' }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card shadow-sm db-summary-card db-summary-card--warn h-100">
                    <div class="card-body py-3">
                        <div class="text-secondary small">{{ __('Suspected orphan tables') }}</div>
                        <div class="h4 mb-0 text-warning">{{ $summary['orphan'] ?? 0 }}</div>
                        <div class="small text-secondary">{{ __('No model and no code refs') }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card shadow-sm db-summary-card db-summary-card--danger h-100">
                    <div class="card-body py-3">
                        <div class="text-secondary small">{{ __('Tables ≥ :mb MB', ['mb' => $largeMb]) }}</div>
                        <div class="h4 mb-0 text-danger">{{ $summary['large'] ?? 0 }}</div>
                        <div class="small text-secondary">{{ __('Models mapped') }}: {{ $summary['models_mapped'] ?? 0 }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="db-sticky-toolbar mb-2">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div class="btn-group db-filter-pills" role="group" aria-label="{{ __('Filters') }}">
                    @foreach([
                        'all' => __('All'),
                        'large' => __('Large'),
                        'orphan' => __('Orphans'),
                        'system' => __('System'),
                        'nostale' => __('No dates yet'),
                    ] as $key => $label)
                        <a href="{{ route('admin.database.index', ['filter' => $key]) }}"
                           class="btn btn-sm {{ $filter === $key ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $label }}</a>
                    @endforeach
                </div>
                <div class="small text-secondary">
                    {{ __('Snapshot') }}: {{ $snapshot['generated_at'] ?? '—' }}
                    @if(!empty($snapshot['dates_probed_at']))
                        · {{ __('Dates') }}: {{ $snapshot['dates_probed_at'] }}
                    @endif
                    · {{ __('Showing') }} {{ count($filtered) }}/{{ count($allTables) }}
                </div>
            </div>
            <input type="search" class="form-control form-control-sm mt-2" id="db-table-search"
                   placeholder="{{ __('Search table name…') }}" autocomplete="off">
        </div>

        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0" id="db-inventory-table">
                    <thead class="table-light">
                    <tr>
                        <th>{{ __('Table') }}</th>
                        <th class="text-end">{{ __('Size') }}</th>
                        <th class="text-end">{{ __('Rows ~') }}</th>
                        <th>{{ __('Module') }}</th>
                        <th>{{ __('Models / code') }}</th>
                        <th>{{ __('Data range') }}</th>
                        <th>{{ __('Optimized') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($filtered as $t)
                        @php
                            $isOrphan = ($t['status'] ?? '') === 'orphan';
                            $isLarge = ($t['size_mb'] ?? 0) >= $largeMb;
                            $rowClass = trim(($isOrphan ? 'db-row-orphan' : '') . ($isLarge ? ' db-row-large' : ''));
                            $moduleNote = $t['modules'][0]['note'] ?? ($t['orphan_note'] ?? null);
                            $previewId = 'db-preview-row-' . preg_replace('/[^a-z0-9_-]/', '-', $t['name']);
                        @endphp
                        <tr class="{{ $rowClass }}" data-table-name="{{ $t['name'] }}" data-inventory-row="1">
                            <td>
                                <div class="db-table-name fw-semibold">{{ $t['name'] }}</div>
                                @if(!empty($t['comment']))
                                    <div class="small text-secondary">{{ $t['comment'] }}</div>
                                @endif
                                @if(!empty($t['fk_out']) || !empty($t['fk_in']))
                                    <details class="mt-1">
                                        <summary class="small text-secondary">{{ __('FK') }}</summary>
                                        @if(!empty($t['fk_out']))
                                            <ul class="db-fk-list">
                                                @foreach($t['fk_out'] as $fk)
                                                    <li>{{ $fk['column'] }} → {{ $fk['ref_table'] }}.{{ $fk['ref_column'] }}</li>
                                                @endforeach
                                            </ul>
                                        @endif
                                        @if(!empty($t['fk_in']))
                                            <ul class="db-fk-list">
                                                @foreach($t['fk_in'] as $fk)
                                                    <li>{{ $fk['ref_table'] }}.{{ $fk['ref_column'] }} → {{ $fk['column'] }}</li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </details>
                                @endif
                            </td>
                            <td class="text-end text-nowrap db-size-cell">{{ number_format($t['size_mb'], 1, '.', ' ') }} MB</td>
                            <td class="text-end text-nowrap">{{ number_format($t['rows_estimate'], 0, '.', ' ') }}</td>
                            <td>
                                @if(!empty($t['system']))
                                    <span class="badge text-bg-secondary">{{ $t['system']['title'] ?? __('System') }}</span>
                                @elseif(!empty($t['modules']))
                                    @foreach($t['modules'] as $mod)
                                        <div>
                                            @if(!empty($mod['uri']))
                                                <a href="{{ url($mod['uri']) }}">{{ $mod['title'] }}</a>
                                            @else
                                                {{ $mod['title'] }}
                                            @endif
                                        </div>
                                    @endforeach
                                    @if($moduleNote)
                                        <div class="small text-warning">{{ $moduleNote }}</div>
                                    @endif
                                @else
                                    <span class="text-secondary">—</span>
                                @endif
                                @if($isOrphan && !empty($t['orphan_note']) && !$moduleNote)
                                    <div class="small text-warning">{{ $t['orphan_note'] }}</div>
                                @endif
                            </td>
                            <td class="small">
                                @if(!empty($t['models']))
                                    <div><i class="bi bi-box me-1" aria-hidden="true"></i>{{ implode(', ', $t['models']) }}</div>
                                @endif
                                <div class="text-secondary">{{ __('Files') }}: {{ $t['code_refs'] ?? 0 }}@if(!empty($t['code_refs_migrations'])) <span title="{{ __('Migrations only') }}">(+{{ $t['code_refs_migrations'] }} {{ __('migrations abbrev') }})</span>@endif</div>
                            </td>
                            <td class="small text-nowrap">
                                @include('admin.database.partials.date-range', ['t' => $t])
                            </td>
                            <td class="small db-optimize-cell" data-table="{{ $t['name'] }}">
                                @php
                                    $opt = $t['optimize'] ?? null;
                                    $freeMb = (float) ($t['data_free_mb'] ?? 0);
                                    $denyOptimize = isset(array_flip(config('cabinet-database-admin.optimize_deny_tables', []))[$t['name']]);
                                    $syncMax = (int) config('cabinet-database-admin.optimize_sync_max_mb', 500);
                                    $willQueue = ($t['size_mb'] ?? 0) >= $syncMax;
                                @endphp
                                <div class="db-optimize-status">
                                @if($opt && ($opt['status'] ?? '') === 'ok' && !empty($opt['optimized_at']))
                                    <div class="text-nowrap">{{ \Illuminate\Support\Carbon::parse($opt['optimized_at'])->format('d.m.Y H:i') }}</div>
                                    @if(isset($opt['freed_mb']))
                                        @php
                                            $freedMb = (float) $opt['freed_mb'];
                                            $freedAbs = abs($freedMb);
                                            $freedLabel = $freedAbs >= 1024
                                                ? number_format($freedAbs / 1024, 2, '.', ' ') . ' GB'
                                                : number_format($freedAbs, 1, '.', ' ') . ' MB';
                                            $freedSign = $freedMb > 0 ? '−' : ($freedMb < 0 ? '+' : '');
                                        @endphp
                                        <div class="text-success">{{ $freedSign . $freedLabel }}</div>
                                    @endif
                                @elseif($opt && in_array($opt['status'] ?? '', ['queued', 'running'], true))
                                    <span class="badge text-bg-info">{{ $opt['status'] === 'queued' ? __('Database optimize status queued') : __('Database optimize status running') }}</span>
                                @elseif($opt && ($opt['status'] ?? '') === 'failed')
                                    <div class="text-danger" title="{{ $opt['message'] ?? '' }}">{{ __('Database optimize status failed') }}</div>
                                @else
                                    <span class="text-secondary">—</span>
                                @endif
                                </div>
                                <div class="db-optimize-free text-secondary text-nowrap {{ $freeMb >= 1 ? '' : 'd-none' }}" title="{{ __('Database optimize data free hint') }}">
                                    {{ __('Database optimize reclaimable') }}:
                                    <span class="db-optimize-free-val">{{ $freeMb >= 1024 ? number_format($freeMb / 1024, 2, '.', ' ') . ' GB' : number_format($freeMb, 1, '.', ' ') . ' MB' }}</span>
                                </div>
                            </td>
                            <td>
                                @if($isOrphan)
                                    <span class="badge text-bg-warning">{{ __('Orphan') }}</span>
                                @elseif(($t['status'] ?? '') === 'system')
                                    <span class="badge text-bg-secondary">{{ __('System') }}</span>
                                @else
                                    <span class="badge text-bg-success">{{ __('Linked') }}</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                    <button type="button"
                                            class="btn btn-sm btn-outline-secondary db-preview-btn"
                                            data-table="{{ $t['name'] }}"
                                            data-preview-target="{{ $previewId }}"
                                            title="{{ __('Database preview last rows', ['n' => config('cabinet-database-admin.row_preview_limit', 10)]) }}">
                                        <i class="bi bi-list-ul" aria-hidden="true"></i>
                                        <span class="d-none d-md-inline ms-1">{{ __('Preview') }}</span>
                                    </button>
                                    @unless($denyOptimize)
                                        @php
                                            $optConfirm = $willQueue
                                                ? __('Database optimize confirm queue', ['table' => $t['name'], 'size' => number_format($t['size_mb'], 1, '.', ' ') . ' MB'])
                                                : __('Database optimize confirm sync', ['table' => $t['name'], 'size' => number_format($t['size_mb'], 1, '.', ' ') . ' MB']);
                                        @endphp
                                        <button type="button"
                                                class="btn btn-sm btn-outline-primary db-optimize-btn"
                                                data-table="{{ $t['name'] }}"
                                                data-confirm="{{ $optConfirm }}"
                                                data-size-mb="{{ $t['size_mb'] }}"
                                                title="{{ __('Database optimize action') }}">
                                            <i class="bi bi-lightning-charge" aria-hidden="true"></i>
                                            <span class="d-none d-lg-inline ms-1">{{ __('Database optimize action') }}</span>
                                        </button>
                                    @endunless
                                    @if(isset($clearableTables[$t['name']]))
                                        @php
                                            $clearConfirm = __('Database clear table confirm', ['table' => $t['name']]);
                                        @endphp
                                        <form action="{{ route('admin.database.clear', ['table' => $t['name']]) }}" method="post" class="d-inline"
                                              onsubmit='return confirm(@json($clearConfirm));'>
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                                    title="{{ __('Database clear table', ['table' => $t['name']]) }}">
                                                <i class="bi bi-trash3" aria-hidden="true"></i>
                                                <span class="d-none d-md-inline ms-1">{{ __('Database clear table action') }}</span>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        <tr class="db-preview-row d-none" id="{{ $previewId }}" data-preview-for="{{ $t['name'] }}">
                            <td colspan="9" class="bg-body-tertiary p-3">
                                <div class="db-preview-panel small text-secondary">
                                    <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                                    <span class="db-preview-placeholder">{{ __('Database preview click load') }}</span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        (function () {
            var previewUrlTemplate = @json(route('admin.database.preview', ['table' => '__TABLE__']));
            var optimizeUrlTemplate = @json(route('admin.database.optimize', ['table' => '__TABLE__']));
            var optimizeStatusUrlTemplate = @json(route('admin.database.optimize.status', ['table' => '__TABLE__']));
            var csrf = document.querySelector('meta[name="csrf-token"]');
            var csrfToken = csrf ? csrf.getAttribute('content') : '';
            var labels = {
                empty: @json(__('Database preview empty')),
                error: @json(__('Database preview error')),
                order: @json(__('Database preview order')),
                excerpt: @json(__('Exception excerpt')),
                loading: @json(__('Database preview loading')),
                queued: @json(__('Database optimize status queued')),
                running: @json(__('Database optimize status running')),
                failed: @json(__('Database optimize status failed')),
                reclaimable: @json(__('Database optimize reclaimable')),
                optimizeError: @json(__('Database optimize status failed')),
                optimizeDoneTpl: @json(__('Database optimize done', ['table' => ':table', 'freed' => ':freed', 'after' => ':after']))
            };
            var pollTimers = {};

            function escapeHtml(s) {
                return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }

            function formatMb(mb) {
                mb = Number(mb) || 0;
                if (Math.abs(mb) >= 1024) {
                    return (Math.round(mb / 1024 * 100) / 100).toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' GB';
                }
                return (Math.round(mb * 10) / 10).toLocaleString('ru-RU', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' MB';
            }

            function formatFreed(mb) {
                mb = Number(mb) || 0;
                var sign = mb > 0 ? '−' : (mb < 0 ? '+' : '');
                return sign + formatMb(Math.abs(mb));
            }

            function formatDateTime(iso) {
                if (!iso) return '';
                var d = new Date(String(iso).replace(' ', 'T'));
                if (isNaN(d.getTime())) return String(iso);
                var pad = function (n) { return n < 10 ? '0' + n : '' + n; };
                return pad(d.getDate()) + '.' + pad(d.getMonth() + 1) + '.' + d.getFullYear() + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
            }

            function renderOptimizeCell(opt, dataFreeMb) {
                var html = '<div class="db-optimize-status">';
                if (opt && opt.status === 'ok' && opt.optimized_at) {
                    html += '<div class="text-nowrap">' + escapeHtml(formatDateTime(opt.optimized_at)) + '</div>';
                    if (opt.freed_mb != null) {
                        html += '<div class="text-success">' + escapeHtml(formatFreed(opt.freed_mb)) + '</div>';
                    }
                } else if (opt && (opt.status === 'queued' || opt.status === 'running')) {
                    html += '<span class="badge text-bg-info">' + escapeHtml(opt.status === 'queued' ? labels.queued : labels.running) + '</span>';
                } else if (opt && opt.status === 'failed') {
                    html += '<div class="text-danger" title="' + escapeHtml(opt.message || '') + '">' + escapeHtml(labels.failed) + '</div>';
                } else {
                    html += '<span class="text-secondary">—</span>';
                }
                html += '</div>';
                var free = Number(dataFreeMb) || 0;
                html += '<div class="db-optimize-free text-secondary text-nowrap' + (free >= 1 ? '' : ' d-none') + '">';
                html += escapeHtml(labels.reclaimable) + ': <span class="db-optimize-free-val">' + escapeHtml(formatMb(free)) + '</span></div>';
                return html;
            }

            function findRow(table) {
                return document.querySelector('#db-inventory-table tbody tr[data-inventory-row="1"][data-table-name="' + table + '"]');
            }

            function updateRowOptimize(table, payload) {
                var row = findRow(table);
                if (!row) return;
                var cell = row.querySelector('.db-optimize-cell');
                if (cell) {
                    cell.innerHTML = renderOptimizeCell(payload.optimize || null, payload.data_free_mb);
                }
                if (payload.size_mb != null) {
                    var sizeCell = row.querySelector('.db-size-cell');
                    if (sizeCell) {
                        sizeCell.textContent = formatMb(payload.size_mb);
                    }
                }
                var btn = row.querySelector('.db-optimize-btn');
                if (btn) {
                    var st = payload.optimize && payload.optimize.status;
                    var busy = st === 'queued' || st === 'running';
                    btn.disabled = !!busy;
                    if (busy) {
                        btn.setAttribute('aria-busy', 'true');
                    } else {
                        btn.removeAttribute('aria-busy');
                    }
                }
            }

            function showFlash(type, message) {
                var existing = document.querySelector('.cabinet-database-admin-page .db-ajax-flash');
                if (existing) existing.remove();
                var el = document.createElement('div');
                el.className = 'alert alert-' + (type === 'error' ? 'danger' : 'success') + ' alert-dismissible fade show db-ajax-flash';
                el.setAttribute('role', 'alert');
                el.innerHTML = escapeHtml(message) +
                    '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                var page = document.querySelector('.cabinet-database-admin-page');
                var toolbar = page ? page.querySelector('.db-sticky-toolbar') : null;
                if (toolbar && toolbar.parentNode) {
                    toolbar.parentNode.insertBefore(el, toolbar);
                } else if (page) {
                    page.insertBefore(el, page.firstChild);
                }
            }

            function stopPoll(table) {
                if (pollTimers[table]) {
                    clearInterval(pollTimers[table]);
                    delete pollTimers[table];
                }
            }

            function startPoll(table) {
                stopPoll(table);
                pollTimers[table] = setInterval(function () {
                    fetch(optimizeStatusUrlTemplate.replace('__TABLE__', encodeURIComponent(table)), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin'
                    })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (!data || !data.ok) return;
                            updateRowOptimize(table, data);
                            var st = data.optimize && data.optimize.status;
                            if (!st || st === 'ok' || st === 'failed') {
                                stopPoll(table);
                                if (st === 'ok') {
                                    showFlash('success', labels.optimizeDoneTpl
                                        .replace(':table', table)
                                        .replace(':freed', formatFreed((data.optimize && data.optimize.freed_mb) || 0))
                                        .replace(':after', formatMb(data.size_mb || 0)));
                                } else if (st === 'failed') {
                                    showFlash('error', (data.optimize && data.optimize.message) || labels.failed);
                                }
                            }
                        })
                        .catch(function () { /* keep polling */ });
                }, 5000);
            }

            function renderPreview(data) {
                if (!data.rows || !data.rows.length) {
                    return '<p class="mb-0 text-secondary">' + escapeHtml(labels.empty) + '</p>';
                }
                var html = '<p class="mb-2 text-secondary">' + escapeHtml(labels.order) + ': <code>' + escapeHtml(data.order_column) + '</code>';
                if (data.note) {
                    html += ' · ' + escapeHtml(data.note);
                }
                html += '</p><div class="table-responsive"><table class="table table-sm table-bordered bg-white mb-0"><thead><tr>';
                data.columns.forEach(function (col) {
                    html += '<th>' + escapeHtml(col) + '</th>';
                });
                if (data.table === 'failed_jobs') {
                    html += '<th>' + escapeHtml(labels.excerpt) + '</th>';
                }
                html += '</tr></thead><tbody>';
                data.rows.forEach(function (row) {
                    html += '<tr>';
                    data.columns.forEach(function (col) {
                        html += '<td class="text-break">' + escapeHtml(row[col] != null ? row[col] : '—') + '</td>';
                    });
                    if (data.table === 'failed_jobs' && row.exception_excerpt) {
                        html += '<td class="text-break"><details><summary class="small">' + escapeHtml(labels.excerpt) + '</summary><pre class="small mb-0 mt-1" style="white-space:pre-wrap;max-height:8rem;overflow:auto;">' + escapeHtml(row.exception_excerpt) + '</pre></details></td>';
                    } else if (data.table === 'failed_jobs') {
                        html += '<td>—</td>';
                    }
                    html += '</tr>';
                });
                html += '</tbody></table></div>';
                return html;
            }

            document.querySelectorAll('.db-preview-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var table = btn.getAttribute('data-table');
                    var targetId = btn.getAttribute('data-preview-target');
                    var previewRow = document.getElementById(targetId);
                    if (!previewRow) return;
                    var panel = previewRow.querySelector('.db-preview-panel');
                    var spinner = panel.querySelector('.spinner-border');
                    var isOpen = !previewRow.classList.contains('d-none');
                    if (isOpen && panel.getAttribute('data-loaded') === '1') {
                        previewRow.classList.add('d-none');
                        return;
                    }
                    previewRow.classList.remove('d-none');
                    if (panel.getAttribute('data-loaded') === '1') {
                        return;
                    }
                    btn.disabled = true;
                    btn.setAttribute('aria-busy', 'true');
                    spinner.classList.remove('d-none');
                    panel.querySelector('.db-preview-placeholder').textContent = labels.loading;
                    fetch(previewUrlTemplate.replace('__TABLE__', encodeURIComponent(table)), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    })
                        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, json: j }; }); })
                        .then(function (res) {
                            spinner.classList.add('d-none');
                            btn.disabled = false;
                            btn.removeAttribute('aria-busy');
                            if (!res.ok) {
                                panel.innerHTML = '<p class="text-danger mb-0">' + escapeHtml(res.json.error || labels.error) + '</p>';
                                return;
                            }
                            panel.innerHTML = renderPreview(res.json);
                            panel.setAttribute('data-loaded', '1');
                        })
                        .catch(function () {
                            spinner.classList.add('d-none');
                            btn.disabled = false;
                            btn.removeAttribute('aria-busy');
                            panel.innerHTML = '<p class="text-danger mb-0">' + escapeHtml(labels.error) + '</p>';
                        });
                });
            });

            document.querySelectorAll('.db-optimize-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var table = btn.getAttribute('data-table');
                    var confirmMsg = btn.getAttribute('data-confirm') || '';
                    if (confirmMsg && !window.confirm(confirmMsg)) {
                        return;
                    }
                    btn.disabled = true;
                    btn.setAttribute('aria-busy', 'true');
                    updateRowOptimize(table, {
                        optimize: { status: 'queued' },
                        data_free_mb: (function () {
                            var cell = document.querySelector('.db-optimize-cell[data-table="' + table + '"]');
                            var freeEl = cell && cell.querySelector('.db-optimize-free');
                            if (!freeEl || freeEl.classList.contains('d-none')) return 0;
                            return 1; // keep free line visible; exact value refreshed from response
                        })()
                    });
                    fetch(optimizeUrlTemplate.replace('__TABLE__', encodeURIComponent(table)), {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ _token: csrfToken })
                    })
                        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, status: r.status, json: j }; }); })
                        .then(function (res) {
                            if (!res.ok || !res.json.ok) {
                                btn.disabled = false;
                                btn.removeAttribute('aria-busy');
                                showFlash('error', (res.json && res.json.error) || labels.optimizeError);
                                // refresh status cell
                                fetch(optimizeStatusUrlTemplate.replace('__TABLE__', encodeURIComponent(table)), {
                                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                                }).then(function (r) { return r.json(); }).then(function (d) {
                                    if (d && d.ok) updateRowOptimize(table, d);
                                }).catch(function () {});
                                return;
                            }
                            updateRowOptimize(table, res.json);
                            showFlash('success', res.json.message || labels.queued);
                            var st = res.json.optimize && res.json.optimize.status;
                            if (st === 'queued' || st === 'running') {
                                startPoll(table);
                            } else {
                                btn.disabled = false;
                                btn.removeAttribute('aria-busy');
                            }
                        })
                        .catch(function () {
                            btn.disabled = false;
                            btn.removeAttribute('aria-busy');
                            showFlash('error', labels.optimizeError);
                        });
                });
            });

            // Resume polling for rows already in progress
            document.querySelectorAll('.db-optimize-cell').forEach(function (cell) {
                var table = cell.getAttribute('data-table');
                if (!table) return;
                if (cell.querySelector('.badge.text-bg-info')) {
                    startPoll(table);
                    var row = findRow(table);
                    var btn = row && row.querySelector('.db-optimize-btn');
                    if (btn) {
                        btn.disabled = true;
                        btn.setAttribute('aria-busy', 'true');
                    }
                }
            });

            var input = document.getElementById('db-table-search');
            var rows = document.querySelectorAll('#db-inventory-table tbody tr[data-inventory-row="1"]');
            if (input && rows.length) {
                input.addEventListener('input', function () {
                    var q = input.value.toLowerCase().trim();
                    rows.forEach(function (row) {
                        var name = (row.getAttribute('data-table-name') || '').toLowerCase();
                        var show = !q || name.indexOf(q) !== -1;
                        row.style.display = show ? '' : 'none';
                        var previewId = row.querySelector('.db-preview-btn');
                        if (previewId) {
                            var pr = document.getElementById(previewId.getAttribute('data-preview-target'));
                            if (pr) {
                                pr.style.display = show ? '' : 'none';
                                if (!show) {
                                    pr.classList.add('d-none');
                                }
                            }
                        }
                    });
                });
            }
        })();
    </script>
@endsection
