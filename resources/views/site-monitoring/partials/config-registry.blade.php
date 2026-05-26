@php
    $summary = $registry['summary'] ?? [];
    $rows = $registry['rows'] ?? [];
    $timing = $summary['timing_breakdown'] ?? [];
@endphp

<div class="cabinet-sm-registry mt-4">
    <div class="mb-3">
        <h3 class="h5 mb-1">{{ __('Site monitoring registry title') }}</h3>
        <p class="text-secondary small mb-0">{{ __('Site monitoring registry lead') }}</p>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="cabinet-sm-registry-kpi card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="cabinet-sm-registry-kpi__icon text-bg-primary">
                        <i class="bi bi-globe2" aria-hidden="true"></i>
                    </div>
                    <div class="cabinet-sm-registry-kpi__value">{{ number_format($summary['projects_total'] ?? 0, 0, ',', ' ') }}</div>
                    <div class="cabinet-sm-registry-kpi__label">{{ __('Monitored domains') }}</div>
                    <div class="cabinet-sm-registry-kpi__meta text-secondary small">
                        {{ __('With notifications on') }}: {{ number_format($summary['projects_notify_on'] ?? 0, 0, ',', ' ') }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="cabinet-sm-registry-kpi card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="cabinet-sm-registry-kpi__icon text-bg-secondary">
                        <i class="bi bi-people" aria-hidden="true"></i>
                    </div>
                    <div class="cabinet-sm-registry-kpi__value">{{ number_format($summary['users_with_projects'] ?? 0, 0, ',', ' ') }}</div>
                    <div class="cabinet-sm-registry-kpi__label">{{ __('Site monitoring active users') }}</div>
                    <div class="cabinet-sm-registry-kpi__meta text-secondary small">
                        {{ __('Users with Telegram') }}: {{ number_format($summary['users_telegram'] ?? 0, 0, ',', ' ') }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="cabinet-sm-registry-kpi card h-100 border-0 shadow-sm @if(($summary['projects_broken'] ?? 0) > 0) cabinet-sm-registry-kpi--alert @endif">
                <div class="card-body">
                    <div class="cabinet-sm-registry-kpi__icon text-bg-danger">
                        <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                    </div>
                    <div class="cabinet-sm-registry-kpi__value">{{ number_format($summary['projects_broken'] ?? 0, 0, ',', ' ') }}</div>
                    <div class="cabinet-sm-registry-kpi__label">{{ __('Site monitoring broken now') }}</div>
                    <div class="cabinet-sm-registry-kpi__meta text-secondary small">{{ __('On check right now') }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="cabinet-sm-registry-kpi card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="cabinet-sm-registry-kpi__label mb-2">{{ __('Site monitoring by interval') }}</div>
                    <div class="cabinet-sm-registry-timing d-flex flex-wrap gap-1">
                        @foreach([5, 10, 15, 20, 30, 60] as $min)
                            <span class="badge rounded-pill text-bg-light text-dark border">
                                {{ $min }} {{ __('min') }}
                                <strong class="ms-1">{{ $timing[$min] ?? 0 }}</strong>
                            </span>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            @if(count($rows) === 0)
                <div class="alert alert-secondary m-3 mb-0">{{ __('Site monitoring registry empty') }}</div>
            @else
                <div class="cabinet-sm-datatable cabinet-sm-registry-datatable p-3 pt-2">
                    <table id="cabinet-sm-registry-table"
                           class="table table-sm table-bordered table-striped align-middle cabinet-sm-table cabinet-sm-registry-table w-100 mb-0">
                        <thead>
                        <tr>
                            <th>{{ __('User') }}</th>
                            <th class="text-nowrap">{{ __('Last visit') }}</th>
                            <th>{{ __('Tariff') }}</th>
                            <th>{{ __('Project name') }}</th>
                            <th>{{ __('Link') }}</th>
                            <th class="text-nowrap">{{ __('Monitoring frequency') }}</th>
                            <th class="text-center">{{ __('Notifications') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="text-nowrap">{{ __('Last check') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($rows as $row)
                            <tr class="@if($row['broken']) table-danger @endif">
                                <td data-order="{{ $row['email'] }} {{ $row['name'] }}">
                                    <div class="cabinet-sm-registry-user">
                                        <div class="fw-semibold text-break">{{ $row['email'] }}</div>
                                        @if($row['name'])
                                            <div class="text-secondary small">{{ $row['name'] }}</div>
                                        @endif
                                        <div class="text-secondary small">ID {{ $row['user_id'] }}</div>
                                    </div>
                                </td>
                                <td data-order="{{ $row['last_online_sort'] }}" class="text-nowrap small">
                                    @if($row['last_online_at'])
                                        <div>{{ $row['last_online_at'] }}</div>
                                        <div class="text-secondary">{{ $row['last_online_human'] }}</div>
                                    @else
                                        <span class="text-secondary">—</span>
                                    @endif
                                </td>
                                <td data-order="{{ $row['tariff_sort'] }} {{ $row['tariff_label'] }}">
                                    <div class="d-flex flex-wrap align-items-center gap-1">
                                        <span class="badge text-bg-secondary">{{ $row['tariff_label'] }}</span>
                                        @if($row['telegram'])
                                            <span class="badge text-bg-info" title="Telegram">
                                                <i class="bi bi-telegram" aria-hidden="true"></i>
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td data-order="{{ $row['project_name'] }}">
                                    <span class="fw-medium">{{ $row['project_name'] }}</span>
                                </td>
                                <td data-order="{{ $row['link'] }}">
                                    <a href="{{ $row['link'] }}" target="_blank" rel="noopener noreferrer" class="text-break small">
                                        {{ \Illuminate\Support\Str::limit($row['link'], 48) }}
                                    </a>
                                </td>
                                <td data-order="{{ $row['timing'] }}" class="text-nowrap">
                                    {{ $row['timing'] }} {{ __('min') }}
                                    <span class="text-secondary">· {{ $row['waiting_time'] }} {{ __('sec') }}</span>
                                </td>
                                <td data-order="{{ $row['send_notification'] ? 1 : 0 }}" class="text-center">
                                    @if($row['send_notification'])
                                        <i class="bi bi-bell-fill text-success" title="{{ __('On') }}"></i>
                                    @else
                                        <i class="bi bi-bell-slash text-secondary" title="{{ __('Off') }}"></i>
                                    @endif
                                </td>
                                <td data-order="{{ $row['broken'] ? 0 : 1 }} {{ $row['status_label'] }}">
                                    @if($row['status'])
                                        @if($row['broken'])
                                            <span class="badge text-bg-danger">{{ $row['status_label'] }}</span>
                                        @else
                                            <span class="badge text-bg-success">{{ $row['status_label'] }}</span>
                                        @endif
                                        @if($row['code'])
                                            <span class="text-secondary small ms-1">HTTP {{ $row['code'] }}</span>
                                        @endif
                                    @else
                                        <span class="text-secondary">—</span>
                                    @endif
                                </td>
                                <td data-order="{{ $row['last_check_sort'] }}" class="text-nowrap small text-secondary">
                                    {{ $row['last_check'] ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
