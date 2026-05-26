@php
    $summary = $registry['summary'] ?? [];
    $rows = $registry['rows'] ?? [];
@endphp

<div id="cabinet-mt-admin-registry" class="cabinet-mt-registry mt-4">
    <div class="mb-3">
        <h3 class="h5 mb-1">{{ __('Meta tags registry title') }}</h3>
        <p class="text-secondary small mb-0">{{ __('Meta tags registry lead') }}</p>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="cabinet-mt-registry-kpi card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="cabinet-mt-registry-kpi__icon text-bg-primary">
                        <i class="bi bi-folder2-open" aria-hidden="true"></i>
                    </div>
                    <div class="cabinet-mt-registry-kpi__value">{{ number_format($summary['projects_total'] ?? 0, 0, ',', ' ') }}</div>
                    <div class="cabinet-mt-registry-kpi__label">{{ __('Meta tags stat projects') }}</div>
                    <div class="cabinet-mt-registry-kpi__meta text-secondary small">
                        {{ __('Meta tags stat active projects') }}: {{ number_format($summary['projects_active'] ?? 0, 0, ',', ' ') }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="cabinet-mt-registry-kpi card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="cabinet-mt-registry-kpi__icon text-bg-secondary">
                        <i class="bi bi-people" aria-hidden="true"></i>
                    </div>
                    <div class="cabinet-mt-registry-kpi__value">{{ number_format($summary['users_with_projects'] ?? 0, 0, ',', ' ') }}</div>
                    <div class="cabinet-mt-registry-kpi__label">{{ __('Meta tags stat users') }}</div>
                    <div class="cabinet-mt-registry-kpi__meta text-secondary small">
                        {{ __('Users with Telegram') }}: {{ number_format($summary['users_telegram'] ?? 0, 0, ',', ' ') }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="cabinet-mt-registry-kpi card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="cabinet-mt-registry-kpi__icon text-bg-info">
                        <i class="bi bi-link-45deg" aria-hidden="true"></i>
                    </div>
                    <div class="cabinet-mt-registry-kpi__value">{{ number_format($summary['pages_total'] ?? 0, 0, ',', ' ') }}</div>
                    <div class="cabinet-mt-registry-kpi__label">{{ __('Meta tags stat pages') }}</div>
                    <div class="cabinet-mt-registry-kpi__meta text-secondary small">
                        {{ __('Meta tags stat snapshots 7d') }}: {{ number_format($summary['snapshots_7d'] ?? 0, 0, ',', ' ') }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="cabinet-mt-registry-kpi card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="cabinet-mt-registry-kpi__icon text-bg-warning text-dark">
                        <i class="bi bi-clock-history" aria-hidden="true"></i>
                    </div>
                    <div class="cabinet-mt-registry-kpi__value">{{ number_format($summary['snapshots_total'] ?? 0, 0, ',', ' ') }}</div>
                    <div class="cabinet-mt-registry-kpi__label">{{ __('Meta tags stat snapshots total') }}</div>
                    <div class="cabinet-mt-registry-kpi__meta text-secondary small">
                        {{ __('Meta tags settings retention') }}: {{ ($summary['retention_days'] ?? 0) > 0 ? ($summary['retention_days'] . ' ' . __('days')) : __('Meta tags retention off') }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            @if(count($rows) === 0)
                <div class="alert alert-secondary m-3 mb-0">{{ __('Meta tags registry empty') }}</div>
            @else
                <div class="cabinet-mt-datatable cabinet-mt-registry-datatable p-3 pt-2">
                    <table id="cabinet-mt-registry-table"
                           class="table table-sm table-bordered table-striped align-middle cabinet-mt-registry-table w-100 mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>{{ __('User') }}</th>
                            <th class="text-nowrap">{{ __('Last visit') }}</th>
                            <th>{{ __('Tariff') }}</th>
                            <th>{{ __('Project name') }}</th>
                            <th class="text-center">{{ __('Meta tags stat pages') }}</th>
                            <th class="text-nowrap">{{ __('Period') }}</th>
                            <th class="text-center">{{ __('Status') }}</th>
                            <th class="text-center">{{ __('Meta tags stat snapshots col') }}</th>
                            <th class="text-nowrap">{{ __('Meta tags stat last snapshot') }}</th>
                            <th class="text-center">{{ __('Meta tags histories errors') }}</th>
                            <th class="text-nowrap">{{ __('Actions') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($rows as $row)
                            <tr class="@if(!$row['status']) table-secondary @endif">
                                <td data-order="{{ $row['email'] }} {{ $row['name'] }}">
                                    <div class="cabinet-mt-registry-user">
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
                                    <div class="text-secondary small">#{{ $row['project_id'] }}</div>
                                </td>
                                <td data-order="{{ $row['links_count'] }}" class="text-center text-num">
                                    {{ $row['links_count'] }}
                                </td>
                                <td data-order="{{ $row['period'] }}" class="text-nowrap small">
                                    {{ __('Meta tags period 24h') }}
                                </td>
                                <td data-order="{{ $row['status'] ? 1 : 0 }}" class="text-center">
                                    @if($row['status'])
                                        <span class="badge text-bg-success">{{ __('On') }}</span>
                                    @else
                                        <span class="badge text-bg-secondary">{{ __('Off') }}</span>
                                    @endif
                                </td>
                                <td data-order="{{ $row['histories_count'] }}" class="text-center text-num">
                                    {{ $row['histories_count'] }}
                                    @if($row['has_ideal'])
                                        <span class="badge text-bg-light text-dark border ms-1" title="{{ __('Meta tags histories ideal title') }}">★</span>
                                    @endif
                                </td>
                                <td data-order="{{ $row['last_snapshot_sort'] }}" class="text-nowrap small">
                                    @if($row['last_snapshot_at'])
                                        <div>{{ $row['last_snapshot_at'] }}</div>
                                        <div class="text-secondary">{{ $row['last_snapshot_human'] }}</div>
                                    @else
                                        <span class="text-secondary">—</span>
                                    @endif
                                </td>
                                <td data-order="{{ $row['last_errors_count'] ?? -1 }}" class="text-center">
                                    @if($row['last_errors_count'] === null)
                                        <span class="text-secondary">—</span>
                                    @elseif($row['last_errors_count'] > 0)
                                        <span class="badge text-bg-danger">{{ $row['last_errors_count'] }}</span>
                                    @else
                                        <span class="text-secondary">0</span>
                                    @endif
                                </td>
                                <td class="text-nowrap">
                                    <a href="{{ url('/meta-tags/histories/' . $row['project_id']) }}"
                                       class="btn btn-outline-secondary btn-sm"
                                       target="_blank"
                                       rel="noopener">
                                        <i class="bi bi-clock-history me-1" aria-hidden="true"></i>{{ __('History') }}
                                    </a>
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
