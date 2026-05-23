@php
    $active = $activity['active'] ?? [];
    $chart = $activity['chart'] ?? ['labels' => [], 'values' => []];
    $activeCards = [
        ['key' => 'today', 'label' => __('Active users today'), 'icon' => 'bi-brightness-high', 'bg' => 'text-bg-success'],
        ['key' => 'days_3', 'label' => __('Active users 3 days'), 'icon' => 'bi-calendar3', 'bg' => 'text-bg-info'],
        ['key' => 'days_7', 'label' => __('Active users 7 days'), 'icon' => 'bi-calendar-week', 'bg' => 'text-bg-primary'],
        ['key' => 'days_14', 'label' => __('Active users 14 days'), 'icon' => 'bi-calendar2-range', 'bg' => 'text-bg-warning'],
        ['key' => 'days_30', 'label' => __('Active users 30 days'), 'icon' => 'bi-calendar-month', 'bg' => 'text-bg-secondary'],
    ];
@endphp

<div class="cabinet-users-activity mb-3">
    <div class="row g-2 g-md-3 mb-3 cabinet-users-activity-kpi">
        @foreach($activeCards as $card)
            <div class="col-6 col-md-4 col-xl">
                <div class="info-box mb-0 h-100">
                    <span class="info-box-icon {{ $card['bg'] }} shadow-sm">
                        <i class="bi {{ $card['icon'] }}"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text text-wrap">{{ $card['label'] }}</span>
                        <span class="info-box-number">{{ number_format($active[$card['key']] ?? 0, 0, ',', ' ') }}</span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card shadow-sm">
        <div class="card-header py-2">
            <h3 class="card-title h6 mb-0">
                <i class="bi bi-graph-up-arrow me-1 text-primary"></i>{{ __('Active users per day (30 days)') }}
            </h3>
        </div>
        <div class="card-body">
            <p class="text-secondary small mb-3">
                {{ __('Unique users per day (visit statistics). Counters above — users with last visit in the period.') }}
            </p>
            <div class="cabinet-users-activity-chart-wrap" style="height: 220px;">
                <canvas id="cabinet-users-activity-chart" aria-label="{{ __('Active users chart') }}"></canvas>
                <p id="cabinet-users-activity-chart-empty" class="text-secondary small mb-0 d-none text-center py-5">
                    {{ __('Chart data is not available. Reload the page or clear cache: php artisan cache:clear') }}
                </p>
            </div>
        </div>
    </div>
</div>
