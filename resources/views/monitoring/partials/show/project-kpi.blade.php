@php
    $kpiHasValues = isset($kpiSummary) && is_array($kpiSummary)
        && (
            (($kpiSummary['top1'] ?? null) !== null && ($kpiSummary['top1'] ?? '') !== '')
            || ((int) ($kpiSummary['words'] ?? 0)) > 0
        );
    $kpiScopeLabel = $kpiHasValues ? ($kpiSummary['scope_label'] ?? null) : null;
    $kpiFmt = function ($value) {
        return ($value !== null && $value !== '') ? $value : '—';
    };
    $kpiDeltaFmt = function ($value) {
        if ($value === null || $value === '') {
            return '';
        }
        $n = (float) $value;
        if ($n == 0.0) {
            return '';
        }
        return ($n > 0 ? '+' : '') . $value;
    };
    $kpiDeltaClass = function ($value) {
        $n = (float) $value;
        if ($n == 0.0) {
            return '';
        }
        return $n > 0 ? 'is-up' : 'is-down';
    };
    $kpiSnapshotHint = '';
    if ($kpiHasValues) {
        $kpiSnapshotHint = ($kpiSummary['snapshot_scope'] ?? '') === 'region'
            ? __('Monitoring show kpi snapshot region hint')
            : __('Monitoring show kpi snapshot project hint');
    }
@endphp
<section
    class="cabinet-mon-project-kpis{{ $kpiHasValues ? '' : ' is-loading' }}"
    id="cabinetMonProjectKpis"
    aria-label="{{ __('Monitoring show kpi strip') }}"
    @if(!$kpiHasValues) aria-busy="true" @endif
>
    <article class="cabinet-mon-project-kpi">
        <span class="cabinet-mon-project-kpi__label">{{ __('Monitoring show kpi top1') }}@if($kpiScopeLabel)<span class="cabinet-mon-project-kpi__scope">{{ $kpiScopeLabel }}</span>@endif</span>
        <span class="cabinet-mon-project-kpi__value" data-kpi="top1">{{ $kpiHasValues ? $kpiFmt($kpiSummary['top1'] ?? null) : '—' }}</span>
        <span class="cabinet-mon-project-kpi__delta {{ $kpiHasValues ? $kpiDeltaClass($kpiSummary['diff_top1'] ?? null) : '' }}" data-kpi-delta="top1">{{ $kpiHasValues ? $kpiDeltaFmt($kpiSummary['diff_top1'] ?? null) : '' }}</span>
    </article>
    <article class="cabinet-mon-project-kpi">
        <span class="cabinet-mon-project-kpi__label">{{ __('Monitoring show kpi top3') }}@if($kpiScopeLabel)<span class="cabinet-mon-project-kpi__scope">{{ $kpiScopeLabel }}</span>@endif</span>
        <span class="cabinet-mon-project-kpi__value" data-kpi="top3">{{ $kpiHasValues ? $kpiFmt($kpiSummary['top3'] ?? null) : '—' }}</span>
        <span class="cabinet-mon-project-kpi__delta {{ $kpiHasValues ? $kpiDeltaClass($kpiSummary['diff_top3'] ?? null) : '' }}" data-kpi-delta="top3">{{ $kpiHasValues ? $kpiDeltaFmt($kpiSummary['diff_top3'] ?? null) : '' }}</span>
    </article>
    <article class="cabinet-mon-project-kpi">
        <span class="cabinet-mon-project-kpi__label">{{ __('Monitoring show kpi top10') }}@if($kpiScopeLabel)<span class="cabinet-mon-project-kpi__scope">{{ $kpiScopeLabel }}</span>@endif</span>
        <span class="cabinet-mon-project-kpi__value" data-kpi="top10">{{ $kpiHasValues ? $kpiFmt($kpiSummary['top10'] ?? null) : '—' }}</span>
        <span class="cabinet-mon-project-kpi__delta {{ $kpiHasValues ? $kpiDeltaClass($kpiSummary['diff_top10'] ?? null) : '' }}" data-kpi-delta="top10">{{ $kpiHasValues ? $kpiDeltaFmt($kpiSummary['diff_top10'] ?? null) : '' }}</span>
    </article>
    <article class="cabinet-mon-project-kpi">
        <span class="cabinet-mon-project-kpi__label">{{ __('Monitoring show kpi top30') }}@if($kpiScopeLabel)<span class="cabinet-mon-project-kpi__scope">{{ $kpiScopeLabel }}</span>@endif</span>
        <span class="cabinet-mon-project-kpi__value" data-kpi="top30">{{ $kpiHasValues ? $kpiFmt($kpiSummary['top30'] ?? null) : '—' }}</span>
        <span class="cabinet-mon-project-kpi__delta {{ $kpiHasValues ? $kpiDeltaClass($kpiSummary['diff_top30'] ?? null) : '' }}" data-kpi-delta="top30">{{ $kpiHasValues ? $kpiDeltaFmt($kpiSummary['diff_top30'] ?? null) : '' }}</span>
    </article>
    <article class="cabinet-mon-project-kpi">
        <span class="cabinet-mon-project-kpi__label">{{ __('Monitoring show kpi top100') }}@if($kpiScopeLabel)<span class="cabinet-mon-project-kpi__scope">{{ $kpiScopeLabel }}</span>@endif</span>
        <span class="cabinet-mon-project-kpi__value" data-kpi="top100">{{ $kpiHasValues ? $kpiFmt($kpiSummary['top100'] ?? null) : '—' }}</span>
        <span class="cabinet-mon-project-kpi__delta {{ $kpiHasValues ? $kpiDeltaClass($kpiSummary['diff_top100'] ?? null) : '' }}" data-kpi-delta="top100">{{ $kpiHasValues ? $kpiDeltaFmt($kpiSummary['diff_top100'] ?? null) : '' }}</span>
    </article>
    <article class="cabinet-mon-project-kpi">
        <span class="cabinet-mon-project-kpi__label">{{ __('Monitoring show kpi avg position') }}@if($kpiScopeLabel)<span class="cabinet-mon-project-kpi__scope">{{ $kpiScopeLabel }}</span>@endif</span>
        <span class="cabinet-mon-project-kpi__value" data-kpi="middle">{{ $kpiHasValues ? $kpiFmt($kpiSummary['middle'] ?? null) : '—' }}</span>
    </article>
    <article class="cabinet-mon-project-kpi">
        <span class="cabinet-mon-project-kpi__label">{{ __('Monitoring show kpi keywords') }}@if($kpiScopeLabel)<span class="cabinet-mon-project-kpi__scope">{{ __('Monitoring show kpi keywords scope') }}</span>@else<span class="cabinet-mon-project-kpi__scope" aria-hidden="true">&nbsp;</span>@endif</span>
        <span class="cabinet-mon-project-kpi__value" data-kpi="words">{{ $kpiHasValues ? $kpiFmt($kpiSummary['words'] ?? null) : '—' }}</span>
    </article>
    <article class="cabinet-mon-project-kpi cabinet-mon-project-kpi--muted">
        <span class="cabinet-mon-project-kpi__label">{{ __('Monitoring show kpi snapshot') }}</span>
        <span class="cabinet-mon-project-kpi__value cabinet-mon-project-kpi__value--small" data-kpi="snapshot_at">{{ $kpiHasValues ? $kpiFmt($kpiSummary['snapshot_at'] ?? null) : '—' }}</span>
        <span class="cabinet-mon-project-kpi__hint text-secondary" data-kpi-hint="snapshot">{{ $kpiSnapshotHint }}</span>
    </article>
    @if(!$kpiHasValues)
        <div class="cabinet-mon-project-kpis__loader" id="cabinetMonProjectKpisLoader">
            @include('monitoring.partials.show.loader', ['label' => __('Monitoring show kpi loading'), 'size' => 'sm'])
        </div>
    @endif
</section>
