@php
    $project = $report['project'] ?? [];
    $summary = $report['summary'] ?? [];
    $timeline = $report['timeline'] ?? [];
@endphp

@php
    $kpis = [
        ['label' => __('Domain information stats total checks'), 'value' => $summary['total_checks'] ?? 0, 'tone' => ''],
        ['label' => __('Domain information stats failures'), 'value' => $summary['failed_checks'] ?? 0, 'tone' => ($summary['failed_checks'] ?? 0) > 0 ? 'danger' : ''],
        ['label' => __('Domain information stats success rate'), 'value' => isset($summary['success_rate']) ? $summary['success_rate'] . '%' : '—', 'tone' => 'success'],
    ];
@endphp

<div class="row g-2 mb-4">
    @foreach($kpis as $k)
        @php $toneClass = $k['tone'] ? ' cabinet-di-stats-kpi--' . $k['tone'] : ''; @endphp
        <div class="col-6 col-md-4">
            <div class="cabinet-di-stats-kpi{{ $toneClass }}">
                <div class="cabinet-di-stats-kpi__value">{{ $k['value'] }}</div>
                <div class="cabinet-di-stats-kpi__label">{{ $k['label'] }}</div>
            </div>
        </div>
    @endforeach
</div>

<p class="small text-secondary mb-3">
    {{ __('Domain information since') }}: {{ $project['created_at'] ?? '—' }}
    @if(!empty($summary['last_check']))
        · {{ __('Last check') }}: {{ $summary['last_check'] }}
    @endif
</p>

<p class="small mb-4">
    <strong>{{ __('Status') }}:</strong> {{ $summary['current_status'] ?? '—' }}
</p>

<h6 class="fw-semibold mb-2">{{ __('Domain information check history') }}</h6>
@if(empty($summary['has_history']))
    <p class="text-secondary small">{{ __('Domain information stats no history') }}</p>
@else
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0 cabinet-di-stats-table">
            <thead class="table-light">
            <tr>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Domain information') }}</th>
                <th>DNS</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @foreach($timeline as $row)
                <tr class="{{ !empty($row['broken']) ? 'table-danger' : '' }}">
                    <td class="text-nowrap small">{{ $row['at'] ?? '—' }}</td>
                    <td class="small">{{ $row['status'] ?? '—' }}</td>
                    <td class="small">{{ $row['info_preview'] ?? '—' }}</td>
                    <td>
                        @if(!empty($row['dns_changed']))
                            <span class="badge text-bg-warning">{{ __('Changed') }}</span>
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-end">
                        <span class="badge text-bg-light text-dark border">{{ $row['source_label'] ?? '' }}</span>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @if(!empty($isPublicView) && count($timeline) >= (int) config('cabinet-domain-information.report_export_log_limit', 100))
        <p class="small text-secondary mt-2 mb-0">{{ __('Domain information public share log truncated') }}</p>
    @endif
@endif
