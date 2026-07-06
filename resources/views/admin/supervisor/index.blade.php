@extends('layouts.app')

@section('title', __('Supervisor management'))

@section('css')
    <link rel="stylesheet" href="{{ asset('css/cabinet-supervisor-admin.css') }}?v={{ @filemtime(public_path('css/cabinet-supervisor-admin.css')) ?: time() }}">
@endsection

@section('content')
    <div class="cabinet-supervisor-admin-page">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
            <div>
                <h2 class="h4 mb-2 d-flex flex-wrap align-items-center gap-1">
                    <i class="bi bi-cpu text-primary" aria-hidden="true"></i>
                    <span>{{ __('Supervisor management') }}</span>
                    @include('partials.cabinet-module-version-badge', ['configKey' => 'cabinet-supervisor-admin'])
                </h2>
                <p class="text-secondary small mb-0" style="max-width: 52rem;">
                    {{ __('Supervisor admin intro') }}
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('admin.queue.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-list-task me-1" aria-hidden="true"></i>{{ __('Queue management') }}
                </a>
                <a href="{{ route('admin.supervisor.index') }}" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>{{ __('Refresh') }}
                </a>
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

        @if(app()->environment('local') && !in_array(request()->getHost(), ['cabinet.titlo.ru', 'www.cabinet.titlo.ru'], true))
            <div class="alert alert-info">
                <strong>{{ __('Supervisor local notice title') }}</strong>
                <p class="mb-0 small">
                    {{ __('Supervisor local notice body') }}
                    <a href="https://cabinet.titlo.ru/admin/supervisor" target="_blank" rel="noopener">cabinet.titlo.ru/admin/supervisor</a>.
                    {{ __('Supervisor local notice dev') }}
                </p>
            </div>
        @endif

        @if(!empty($logProgram) && $logTail)
            @include('admin.supervisor.partials.log-panel', ['logProgram' => $logProgram, 'logTail' => $logTail])
        @endif

        @if(!($probe['enabled'] ?? false))
            <div class="alert alert-warning">
                <strong>{{ __('Supervisor not configured') }}</strong>
                <p class="mb-2 small">{{ $probe['message'] ?? '' }}</p>
                <ul class="small mb-0">
                    <li>{{ __('Supervisor setup step env') }}</li>
                    <li>{{ __('Supervisor setup step conf', ['path' => $probe['config_hint'] ?? '']) }}</li>
                    <li>{{ __('Supervisor setup step fastpanel') }}</li>
                </ul>
            </div>
        @elseif(!($probe['ok'] ?? false))
            <div class="alert alert-danger">
                <strong>{{ __('Supervisor unavailable') }}</strong>
                <p class="mb-1 small">{{ $probe['message'] ?? '' }}</p>
                <p class="mb-0 small text-secondary">{{ __('Supervisor ctl path') }}: <code>{{ $probe['supervisorctl'] ?? '' }}</code></p>
            </div>
        @endif

        @if($capacity)
            @php
                $capTotals = $capacity['totals'] ?? [];
                $capPrograms = $capacity['programs'] ?? [];
            @endphp
            <div class="card mb-3">
                <div class="card-header py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <strong>{{ __('Supervisor capacity title') }}</strong>
                    <span class="small text-secondary">
                        {{ __('Supervisor capacity snapshot at', ['time' => $capacity['generated_at'] ?? '—']) }}
                        · <a href="{{ route('admin.queue.index') }}">{{ __('Queue management') }}</a>
                    </span>
                </div>
                <div class="card-body pb-2">
                    <p class="text-secondary small mb-3">{{ __('Supervisor capacity intro') }}</p>
                    <div class="row g-3 mb-3">
                        <div class="col-6 col-md-3">
                            <div class="cabinet-supervisor-cap-card">
                                <div class="small text-secondary">{{ __('Supervisor capacity workers') }}</div>
                                <div class="h5 mb-0">{{ $capTotals['workers_running'] ?? 0 }}<span class="text-secondary fs-6"> / {{ $capTotals['workers_total'] ?? 0 }}</span></div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="cabinet-supervisor-cap-card">
                                <div class="small text-secondary">{{ __('Supervisor capacity jobs pending') }}</div>
                                <div class="h5 mb-0">{{ number_format($capTotals['jobs_pending'] ?? 0, 0, '.', ' ') }}</div>
                                <div class="small text-secondary">{{ __('Reserved') }}: {{ number_format($capTotals['jobs_reserved'] ?? 0, 0, '.', ' ') }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="cabinet-supervisor-cap-card cabinet-supervisor-cap-card--warn">
                                <div class="small text-secondary">{{ __('Supervisor capacity programs backlog') }}</div>
                                <div class="h5 mb-0 text-warning">{{ $capTotals['programs_backlog'] ?? 0 }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="cabinet-supervisor-cap-card">
                                <div class="small text-secondary">{{ __('Supervisor capacity programs idle') }}</div>
                                <div class="h5 mb-0">{{ $capTotals['programs_idle'] ?? 0 }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0 cabinet-supervisor-cap-table">
                            <thead>
                                <tr>
                                    <th>{{ __('Supervisor program') }}</th>
                                    <th>{{ __('Supervisor module') }}</th>
                                    <th>{{ __('Supervisor capacity workers') }}</th>
                                    <th>{{ __('Supervisor capacity lk ref') }}</th>
                                    <th>{{ __('Supervisor capacity reserved') }}</th>
                                    <th>{{ __('Supervisor capacity pending') }}</th>
                                    <th>{{ __('Supervisor capacity utilization') }}</th>
                                    <th>{{ __('Supervisor capacity hint col') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($capPrograms as $cap)
                                    @php
                                        $load = $cap['load'] ?? 'ok';
                                        $rowClass = $load === 'backlog' ? 'cabinet-supervisor-cap-row--danger' : ($load === 'idle' ? 'cabinet-supervisor-cap-row--muted' : ($load === 'busy' ? 'cabinet-supervisor-cap-row--ok' : ''));
                                        $barClass = $load === 'backlog' ? 'bg-danger' : ($cap['utilization'] ?? 0) >= 75 ? 'bg-success' : 'bg-primary';
                                    @endphp
                                    <tr class="{{ $rowClass }}">
                                        <td class="font-monospace small">{{ $cap['program'] ?? '' }}</td>
                                        <td>
                                            @if(!empty($cap['module_url']))
                                                <a href="{{ $cap['module_url'] }}" class="cabinet-supervisor-admin-module-link">{{ $cap['module_label'] ?? '—' }}</a>
                                            @else
                                                {{ $cap['module_label'] ?? '—' }}
                                            @endif
                                        </td>
                                        <td>{{ $cap['workers_running'] ?? 0 }}/{{ $cap['workers_total'] ?? 0 }}</td>
                                        <td class="text-secondary">{{ $cap['numprocs_lk'] ?? '—' }}</td>
                                        <td>{{ number_format($cap['jobs_reserved'] ?? 0, 0, '.', ' ') }}</td>
                                        <td>{{ number_format($cap['jobs_pending'] ?? 0, 0, '.', ' ') }}</td>
                                        <td style="min-width: 7rem;">
                                            <div class="cabinet-supervisor-cap-bar" title="{{ $cap['utilization'] ?? 0 }}%">
                                                <div class="cabinet-supervisor-cap-bar__fill {{ $barClass }}" style="width: {{ min(100, max(0, (int) ($cap['utilization'] ?? 0))) }}%"></div>
                                            </div>
                                            <span class="small text-secondary">{{ $cap['utilization'] ?? 0 }}%</span>
                                        </td>
                                        <td class="small">{{ $cap['hint'] ?? '' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        <div class="card mb-3">
            <div class="card-header py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
                <strong>{{ __('Supervisor processes') }}</strong>
                @if(($probe['ok'] ?? false) && count($processes) > 0)
                    <div class="d-flex flex-wrap gap-1">
                        @foreach([
                            'start' => ['label' => __('Supervisor action all start'), 'class' => 'btn-outline-success'],
                            'stop' => ['label' => __('Supervisor action all stop'), 'class' => 'btn-outline-danger'],
                            'restart' => ['label' => __('Supervisor action all restart'), 'class' => 'btn-outline-warning'],
                        ] as $bulkAction => $bulkMeta)
                            @php
                                $bulkConfirm = __('Supervisor confirm action all', ['action' => $bulkAction]);
                            @endphp
                            <form action="{{ route('admin.supervisor.action-all') }}" method="post" class="d-inline"
                                  onsubmit='return confirm(@json($bulkConfirm));'>
                                @csrf
                                <input type="hidden" name="action" value="{{ $bulkAction }}">
                                <button type="submit" class="btn btn-sm {{ $bulkMeta['class'] }}">{{ $bulkMeta['label'] }}</button>
                            </form>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="card-body p-0">
                @if(($probe['ok'] ?? false) && count($processes) > 0)
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0 cabinet-supervisor-admin-table">
                            <thead>
                                <tr>
                                    <th>{{ __('Supervisor program') }}</th>
                                    <th>{{ __('Supervisor module') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('PID') }}</th>
                                    <th>{{ __('Uptime') }}</th>
                                    <th class="text-end">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($processes as $proc)
                                    @php
                                        $status = strtoupper($proc['status'] ?? '');
                                        $badge = $status === 'RUNNING' ? 'success' : ($status === 'STOPPED' ? 'secondary' : 'warning');
                                    @endphp
                                    <tr>
                                        <td class="font-monospace">{{ $proc['name'] }}</td>
                                        <td>
                                            @if(!empty($proc['module_url']))
                                                <a href="{{ $proc['module_url'] }}" class="cabinet-supervisor-admin-module-link">{{ $proc['module_label'] ?? '—' }}</a>
                                            @else
                                                <span class="text-secondary">{{ $proc['module_label'] ?? '—' }}</span>
                                            @endif
                                        </td>
                                        <td><span class="badge bg-{{ $badge }}">{{ $status }}</span></td>
                                        <td>{{ $proc['pid'] ?: '—' }}</td>
                                        <td>{{ $proc['uptime'] ?: '—' }}</td>
                                        <td class="text-end text-nowrap">
                                            @if($proc['controllable'] ?? false)
                                                @foreach(['start' => __('Supervisor action start'), 'stop' => __('Supervisor action stop'), 'restart' => __('Supervisor action restart')] as $action => $actionLabel)
                                                    @php
                                                        $supervisorConfirm = __('Supervisor confirm action', ['action' => $action, 'program' => $proc['name']]);
                                                    @endphp
                                                    <form action="{{ route('admin.supervisor.action') }}" method="post" class="d-inline"
                                                          onsubmit='return confirm(@json($supervisorConfirm));'>
                                                        @csrf
                                                        <input type="hidden" name="program" value="{{ $proc['name'] }}">
                                                        <input type="hidden" name="action" value="{{ $action }}">
                                                        <button type="submit" class="btn btn-xs btn-outline-secondary btn-sm">
                                                            {{ $actionLabel }}
                                                        </button>
                                                    </form>
                                                @endforeach
                                                @php
                                                    $logProgramKey = preg_replace('/:.*$/', '', $proc['name'] ?? '') ?: ($proc['name'] ?? '');
                                                @endphp
                                                <a href="{{ route('admin.supervisor.index', ['log' => $logProgramKey]) }}#supervisor-log-panel" class="btn btn-sm btn-link">
                                                    {{ __('Log') }}
                                                </a>
                                            @else
                                                <span class="text-secondary small">{{ __('Supervisor read only') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-secondary small p-3 mb-0">{{ __('Supervisor no processes') }}</p>
                @endif
            </div>
        </div>

        <div class="alert alert-light border small mb-0">
            <strong>{{ __('Supervisor fastpanel note title') }}</strong>
            <p class="mb-0">{{ __('Supervisor fastpanel note body') }}</p>
        </div>
    </div>
@endsection
