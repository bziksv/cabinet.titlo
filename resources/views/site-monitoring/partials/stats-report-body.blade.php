@php
    $project = $report['project'] ?? [];
    $summary = $report['summary'] ?? [];
    $incidents = $report['incidents'] ?? [];
    $timeline = $report['timeline'] ?? [];
@endphp

@php
    $kpis = [
        ['label' => __('Site monitoring stats total checks'), 'value' => $summary['total_checks'] ?? 0, 'tone' => ''],
        ['label' => __('Site monitoring stats failures'), 'value' => $summary['failed_checks'] ?? 0, 'tone' => ($summary['failed_checks'] ?? 0) > 0 ? 'danger' : ''],
        ['label' => __('Site monitoring stats success rate'), 'value' => isset($summary['success_rate']) ? $summary['success_rate'] . '%' : '—', 'tone' => 'success'],
        ['label' => __('Uptime'), 'value' => isset($summary['uptime_percent']) ? $summary['uptime_percent'] . '%' : '—', 'tone' => !empty($summary['currently_broken']) ? 'danger' : 'success'],
    ];
@endphp

<div class="row g-2 mb-4">
    @foreach($kpis as $k)
        @php $toneClass = $k['tone'] ? ' cabinet-sm-stats-kpi--' . $k['tone'] : ''; @endphp
        <div class="col-6 col-md-3">
            <div class="cabinet-sm-stats-kpi{{ $toneClass }}">
                <div class="cabinet-sm-stats-kpi__value">{{ $k['value'] }}</div>
                <div class="cabinet-sm-stats-kpi__label">{{ $k['label'] }}</div>
            </div>
        </div>
    @endforeach
</div>

<p class="small text-secondary mb-3">
    {{ __('Site monitoring since') }}: {{ $project['created_at'] ?? '—' }}
    @if(!empty($summary['last_check']))
        · {{ __('Last check') }}: {{ $summary['last_check'] }}
    @endif
    @if(!empty($summary['downtime_minutes']))
        · {{ __('Current downtime') }}: {{ $summary['downtime_minutes'] }} {{ __('min') }}
    @endif
</p>

<p class="small mb-4">
    <strong>{{ __('Status') }}:</strong> {{ $summary['current_status'] ?? '—' }}
    @if(isset($summary['current_code']) && $summary['current_code'] !== null)
        · HTTP {{ $summary['current_code'] }}
    @endif
</p>

@if(count($incidents) > 0)
    <h6 class="fw-semibold mb-2">{{ __('Site monitoring incidents') }}</h6>
    <div class="list-group list-group-flush cabinet-sm-stats-incidents mb-4">
        @foreach($incidents as $inc)
            <div class="list-group-item px-0">
                <div class="d-flex flex-wrap justify-content-between gap-2 mb-1">
                    <strong class="small">{{ $inc['started_at'] ?? '—' }} → {{ $inc['ended_at'] ?? '…' }}</strong>
                    @if(!empty($inc['ongoing']))
                        <span class="badge text-bg-danger">{{ __('Ongoing') }}</span>
                    @else
                        <span class="badge text-bg-secondary">{{ $inc['duration_minutes'] ?? 0 }} {{ __('min') }}</span>
                    @endif
                </div>
                <div class="small text-secondary">
                    {{ $inc['started_status'] ?? '—' }}
                    @if(!empty($inc['started_code']))
                        · HTTP {{ $inc['started_code'] }}
                    @endif
                    · {{ $inc['checks_while_down'] ?? 0 }} {{ __('checks') }}
                </div>
            </div>
        @endforeach
    </div>
@endif

<h6 class="fw-semibold mb-2">{{ __('Site monitoring check history') }}</h6>
@if(empty($summary['has_history']))
    <p class="text-secondary small">{{ __('Site monitoring stats no history') }}</p>
@else
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0 cabinet-sm-stats-table">
            <thead class="table-light">
            <tr>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Status') }}</th>
                <th>HTTP</th>
                <th>{{ __('Uptime') }}</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @foreach($timeline as $row)
                <tr class="{{ !empty($row['broken']) ? 'table-danger' : '' }}">
                    <td class="text-nowrap small">{{ $row['at'] ?? '—' }}</td>
                    <td class="small">{{ $row['status'] ?? '—' }}</td>
                    <td>{{ $row['http_code'] ?? '—' }}</td>
                    <td>{{ isset($row['uptime_percent']) ? $row['uptime_percent'] . '%' : '—' }}</td>
                    <td class="text-end">
                        <span class="badge text-bg-light text-dark border">{{ $row['source_label'] ?? '' }}</span>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @if(!empty($isPublicView) && count($timeline) >= (int) config('cabinet-site-monitoring.report_export_log_limit', 100))
        <p class="small text-secondary mt-2 mb-0">{{ __('Site monitoring report history limit', ['count' => count($timeline)]) }}</p>
    @endif
@endif
