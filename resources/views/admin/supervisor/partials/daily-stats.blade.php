@if(!empty($dailyReport))
    @php
        $global = $dailyReport['global'] ?? [];
        $programs = $dailyReport['programs'] ?? [];
        $recent = $dailyRecent ?? [];
        $statsDateValue = $statsDay ? $statsDay->format('Y-m-d') : now()->format('Y-m-d');
        $fmtDuration = [\App\Services\Queue\QueueDailyStatsService::class, 'formatDuration'];
    @endphp
    <div class="card mb-3 cabinet-supervisor-daily-stats">
        <div class="card-header py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
            <strong>{{ __('Supervisor stats daily title') }}</strong>
            <form method="get" action="{{ route('admin.supervisor.index') }}" class="d-flex flex-wrap align-items-center gap-2">
                @if(!empty($logProgram))
                    <input type="hidden" name="log" value="{{ $logProgram }}">
                @endif
                <label class="small text-secondary mb-0" for="cabinet-supervisor-stats-date">{{ __('Supervisor stats date') }}</label>
                <input type="date"
                       id="cabinet-supervisor-stats-date"
                       name="stats_date"
                       class="form-control form-control-sm"
                       value="{{ $statsDateValue }}"
                       max="{{ now()->format('Y-m-d') }}">
                <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('Show') }}</button>
            </form>
        </div>
        <div class="card-body pb-2">
            <p class="text-secondary small mb-3">
                {{ __('Supervisor stats daily intro', ['interval' => (int) ($dailyReport['sample_interval_seconds'] ?? 300) / 60]) }}
                @if(!empty($dailyReport['is_partial']))
                    <span class="badge text-bg-info ms-1">{{ __('Supervisor stats partial') }}</span>
                @endif
            </p>

            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3">
                    <div class="cabinet-supervisor-cap-card">
                        <div class="small text-secondary">{{ __('Supervisor stats processed') }}</div>
                        <div class="h5 mb-0">{{ number_format($global['jobs_processed'] ?? 0, 0, '.', ' ') }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="cabinet-supervisor-cap-card cabinet-supervisor-cap-card--warn">
                        <div class="small text-secondary">{{ __('Supervisor stats failed') }}</div>
                        <div class="h5 mb-0">{{ number_format($global['jobs_failed'] ?? 0, 0, '.', ' ') }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="cabinet-supervisor-cap-card">
                        <div class="small text-secondary">{{ __('Supervisor stats peak queue') }}</div>
                        <div class="h5 mb-0">{{ number_format($global['peak_total'] ?? 0, 0, '.', ' ') }}</div>
                        <div class="small text-secondary">{{ __('Supervisor stats peak detail', ['pending' => number_format($global['peak_pending'] ?? 0, 0, '.', ' '), 'reserved' => number_format($global['peak_reserved'] ?? 0, 0, '.', ' ')]) }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="cabinet-supervisor-cap-card">
                        <div class="small text-secondary">{{ __('Supervisor stats idle') }}</div>
                        <div class="h5 mb-0">{{ $fmtDuration((int) ($global['idle_seconds'] ?? 0)) }}</div>
                        <div class="small text-secondary">{{ __('Supervisor stats stopped backlog', ['time' => $fmtDuration((int) ($global['stopped_seconds'] ?? 0))]) }}</div>
                    </div>
                </div>
            </div>

            @if(count($programs) > 0)
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-hover mb-0 cabinet-supervisor-cap-table">
                        <thead>
                            <tr>
                                <th>{{ __('Supervisor program') }}</th>
                                <th>{{ __('Supervisor module') }}</th>
                                <th>{{ __('Supervisor stats processed') }}</th>
                                <th>{{ __('Supervisor stats failed') }}</th>
                                <th>{{ __('Supervisor stats peak queue') }}</th>
                                <th>{{ __('Supervisor stats idle') }}</th>
                                <th>{{ __('Supervisor stats backlog time') }}</th>
                                <th>{{ __('Supervisor capacity workers') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($programs as $row)
                                @if(($row['samples_count'] ?? 0) === 0 && ($row['jobs_processed'] ?? 0) === 0 && ($row['jobs_failed'] ?? 0) === 0)
                                    @continue
                                @endif
                                <tr>
                                    <td class="font-monospace small">{{ $row['program'] ?? '' }}</td>
                                    <td>
                                        @if(!empty($row['module_url']))
                                            <a href="{{ $row['module_url'] }}" class="cabinet-supervisor-admin-module-link">{{ $row['module_label'] ?? '—' }}</a>
                                        @else
                                            {{ $row['module_label'] ?? '—' }}
                                        @endif
                                    </td>
                                    <td>{{ number_format($row['jobs_processed'] ?? 0, 0, '.', ' ') }}</td>
                                    <td>{{ number_format($row['jobs_failed'] ?? 0, 0, '.', ' ') }}</td>
                                    <td>{{ number_format($row['peak_total'] ?? 0, 0, '.', ' ') }}</td>
                                    <td>{{ $fmtDuration((int) ($row['idle_seconds'] ?? 0)) }}</td>
                                    <td>{{ $fmtDuration((int) ($row['backlog_seconds'] ?? 0)) }}</td>
                                    <td>{{ number_format($row['workers_running_avg'] ?? 0, 1, '.', ' ') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-secondary small mb-3">{{ __('Supervisor stats no samples') }}</p>
            @endif

            @if(count($recent) > 1)
                <h3 class="h6 mb-2">{{ __('Supervisor stats recent days') }}</h3>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 cabinet-supervisor-stats-recent">
                        <thead>
                            <tr>
                                <th>{{ __('Supervisor stats date') }}</th>
                                <th>{{ __('Supervisor stats processed') }}</th>
                                <th>{{ __('Supervisor stats failed') }}</th>
                                <th>{{ __('Supervisor stats peak queue') }}</th>
                                <th>{{ __('Supervisor stats idle') }}</th>
                                <th>{{ __('Supervisor stats downtime') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recent as $day)
                                <tr class="{{ !empty($day['is_today']) ? 'cabinet-supervisor-stats-recent__today' : '' }}">
                                    <td>
                                        @if(($day['date'] ?? '') === $statsDateValue)
                                            <strong>{{ $day['date'] }}</strong>
                                        @else
                                            <a href="{{ route('admin.supervisor.index', array_filter(['stats_date' => $day['date'], 'log' => $logProgram ?: null])) }}">{{ $day['date'] }}</a>
                                        @endif
                                        @if(!empty($day['is_partial']))
                                            <span class="badge text-bg-light border ms-1">{{ __('Supervisor stats partial short') }}</span>
                                        @endif
                                    </td>
                                    <td>{{ number_format($day['jobs_processed'] ?? 0, 0, '.', ' ') }}</td>
                                    <td>{{ number_format($day['jobs_failed'] ?? 0, 0, '.', ' ') }}</td>
                                    <td>{{ number_format($day['peak_total'] ?? 0, 0, '.', ' ') }}</td>
                                    <td>{{ $fmtDuration((int) ($day['idle_seconds'] ?? 0)) }}</td>
                                    <td>{{ $fmtDuration((int) ($day['stopped_seconds'] ?? 0)) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endif
