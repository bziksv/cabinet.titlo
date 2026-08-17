{{--
  Сравнение трафика: интерактивный Chart.js (prev/cur) + KPI + качество.
  Ожидает: $traffic, $kpiLabels, $metricOn, $fmtNum, $fmtKpiMetric
--}}
@php
    $volumeKeys = ['users', 'visits', 'pageviews'];
    $qualityKeys = ['page_depth', 'avg_visit_duration', 'bounce_rate'];
    $kpiTitleKeys = [
        'users' => 'Users',
        'visits' => 'Visits',
        'pageviews' => 'Pageviews',
    ];
    $qualityTitleKeys = [
        'page_depth' => 'Page depth',
        'avg_visit_duration' => 'Avg. visit duration',
        'bounce_rate' => 'Bounce rate',
    ];
    $chartRows = [];
    foreach ($volumeKeys as $metric) {
        if (!$metricOn('traffic', $metric)) {
            continue;
        }
        $kpi = is_array($traffic['kpis'][$metric] ?? null) ? $traffic['kpis'][$metric] : null;
        if ($kpi === null) {
            continue;
        }
        $cur = isset($kpi['value']) ? (float) $kpi['value'] : null;
        $prev = array_key_exists('prev', $kpi) && $kpi['prev'] !== null ? (float) $kpi['prev'] : null;
        if ($cur === null && $prev === null) {
            continue;
        }
        $delta = $kpi['delta_pct'] ?? null;
        $deltaClass = '';
        if ($delta !== null) {
            $deltaClass = $delta > 0 ? 'is-up' : ($delta < 0 ? 'is-down' : '');
        }
        $chartRows[] = [
            'key' => $metric,
            'label' => $metric === 'users' ? __('Visitors') : ($kpiLabels[$metric] ?? $metric),
            'kpiTitle' => __($kpiTitleKeys[$metric] ?? ($kpiLabels[$metric] ?? $metric)),
            'cur' => $cur,
            'prev' => $prev,
            'delta' => $delta,
            'deltaClass' => $deltaClass,
            'curDisplay' => $fmtKpiMetric($metric, $cur),
            'prevDisplay' => $prev !== null ? $fmtKpiMetric($metric, $prev) : null,
        ];
    }
    $compareLabels = array_column($chartRows, 'label');
    $comparePrev = array_map(static function ($r) {
        return $r['prev'] !== null ? (float) $r['prev'] : null;
    }, $chartRows);
    $compareCur = array_map(static function ($r) {
        return $r['cur'] !== null ? (float) $r['cur'] : 0.0;
    }, $chartRows);
    $compareDeltas = array_map(static function ($r) {
        return $r['delta'];
    }, $chartRows);
@endphp
@if($chartRows !== [])
    <div class="cabinet-sr-traffic-hero cabinet-sr-compare-only" data-sr-compare-chart>
        <div class="cabinet-sr-traffic-hero__chart-wrap">
            <div class="cabinet-sr-traffic-hero__canvas-wrap">
                <canvas
                    data-sr-compare-labels='@json($compareLabels)'
                    data-sr-compare-prev='@json($comparePrev)'
                    data-sr-compare-cur='@json($compareCur)'
                    data-sr-compare-deltas='@json($compareDeltas)'
                    data-sr-compare-prev-label="{{ __('Previous period') }}"
                    data-sr-compare-cur-label="{{ __('Report period') }}"
                    role="img"
                    aria-label="{{ __('Traffic period compare chart') }}"
                    height="260"></canvas>
            </div>
            <div class="cabinet-sr-traffic-hero__legend" aria-hidden="true">
                <span class="is-prev">{{ __('Previous period') }}</span>
                <span class="is-cur">{{ __('Report period') }}</span>
            </div>
            <p class="cabinet-sr-traffic-hero__hint">{{ __('Hover bars to compare periods') }}</p>
        </div>

        <div class="cabinet-sr-traffic-hero__kpis">
            @foreach($chartRows as $row)
                <div class="cabinet-sr-traffic-hero__kpi">
                    <div class="cabinet-sr-traffic-hero__kpi-head">
                        <span class="cabinet-sr-traffic-hero__kpi-label">{{ $row['kpiTitle'] }}:</span>
                        @if($row['delta'] !== null)
                            <span class="cabinet-sr-traffic-hero__kpi-delta {{ $row['deltaClass'] }}">
                                {{ ($row['delta'] > 0 ? '+' : '') . $fmtNum($row['delta'], 0) }}%
                            </span>
                        @endif
                    </div>
                    <strong class="cabinet-sr-traffic-hero__kpi-num">{{ $row['curDisplay'] }}</strong>
                </div>
            @endforeach
        </div>

        @php
            $qualityRows = [];
            foreach ($qualityKeys as $metric) {
                if (!$metricOn('traffic', $metric)) {
                    continue;
                }
                $kpi = is_array($traffic['kpis'][$metric] ?? null) ? $traffic['kpis'][$metric] : null;
                if ($kpi === null || ($kpi['value'] ?? null) === null) {
                    continue;
                }
                $delta = $kpi['delta_pct'] ?? null;
                $deltaClass = '';
                $deltaDisplay = null;
                if ($delta !== null) {
                    $deltaClass = $delta > 0 ? 'is-up' : ($delta < 0 ? 'is-down' : '');
                    if ($metric === 'bounce_rate') {
                        $deltaClass = $delta < 0 ? 'is-up' : ($delta > 0 ? 'is-down' : '');
                        $deltaDisplay = ($delta > 0 ? '+' : '') . $fmtNum($delta, 0);
                    } else {
                        $deltaDisplay = ($delta > 0 ? '+' : '') . $fmtNum($delta, 0) . '%';
                    }
                }
                $icon = 'eye';
                if ($metric === 'avg_visit_duration') {
                    $icon = 'clock';
                } elseif ($metric === 'bounce_rate') {
                    $icon = 'exit';
                }
                $qualityRows[] = [
                    'label' => __($qualityTitleKeys[$metric] ?? ($kpiLabels[$metric] ?? $metric)),
                    'display' => $fmtKpiMetric($metric, $kpi['value']),
                    'deltaDisplay' => $deltaDisplay,
                    'deltaClass' => $deltaClass,
                    'icon' => $icon,
                ];
            }
        @endphp
        @if($qualityRows !== [])
            <div class="cabinet-sr-traffic-hero__quality">
                @foreach($qualityRows as $q)
                    <div class="cabinet-sr-traffic-hero__q">
                        <span class="cabinet-sr-traffic-hero__q-ico" aria-hidden="true">
                            @if($q['icon'] === 'clock')
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7">
                                    <circle cx="12" cy="12" r="8.5"/><path d="M12 8v4.5l3 1.5"/>
                                </svg>
                            @elseif($q['icon'] === 'exit')
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7">
                                    <path d="M10 7H7a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h3"/><path d="M14 12H8"/><path d="M12 9l3 3-3 3"/>
                                </svg>
                            @else
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7">
                                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>
                                </svg>
                            @endif
                        </span>
                        <div>
                            <div class="cabinet-sr-traffic-hero__q-label">{{ $q['label'] }}:</div>
                            <div class="cabinet-sr-traffic-hero__q-row">
                                <strong>{{ $q['display'] }}</strong>
                                @if($q['deltaDisplay'] !== null)
                                    <span class="cabinet-sr-traffic-hero__kpi-delta {{ $q['deltaClass'] }}">
                                        {{ $q['deltaDisplay'] }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endif
