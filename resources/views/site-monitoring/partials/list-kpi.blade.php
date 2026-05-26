@php
    /** @var \App\Support\SiteMonitoringListSummary $summary */
@endphp
<p class="text-secondary cabinet-sm-kpi-hint mb-3 mb-md-4">
    {{ __('Site monitoring list kpi hint') }}
</p>

<div class="row g-3 mb-4 cabinet-sm-kpi cabinet-module-kpi" aria-live="polite">
    <div class="col-6 col-lg-3">
        <div class="info-box shadow-sm h-100">
            <span class="info-box-icon text-bg-secondary shadow-sm">
                <i class="bi bi-grid-3x3-gap" aria-hidden="true"></i>
            </span>
            <div class="info-box-content">
                <span class="info-box-text">{{ __('Site monitoring kpi total') }}</span>
                <span class="info-box-number">{{ $summary->total }}</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="info-box shadow-sm h-100">
            <span class="info-box-icon text-bg-success shadow-sm">
                <i class="bi bi-check-circle" aria-hidden="true"></i>
            </span>
            <div class="info-box-content">
                <span class="info-box-text">{{ __('Site monitoring kpi available') }}</span>
                <span class="info-box-number @if($summary->available > 0) text-success @endif">{{ $summary->available }}</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="info-box shadow-sm h-100">
            <span class="info-box-icon text-bg-danger shadow-sm">
                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
            </span>
            <div class="info-box-content">
                <span class="info-box-text">{{ __('Site monitoring kpi with issues') }}</span>
                <span class="info-box-number @if($summary->withIssues > 0) text-danger @endif">{{ $summary->withIssues }}</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="info-box shadow-sm h-100">
            <span class="info-box-icon text-bg-primary shadow-sm">
                <i class="bi bi-speedometer2" aria-hidden="true"></i>
            </span>
            <div class="info-box-content">
                <span class="info-box-text">{{ __('Site monitoring kpi avg uptime') }}</span>
                <span class="info-box-number">{{ $summary->formatAvgUptime() }}</span>
            </div>
        </div>
    </div>
</div>

@if($summary->awaitingCheck > 0)
    <p class="small text-secondary mb-3">
        <i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>
        {{ __('Site monitoring kpi awaiting check', ['count' => $summary->awaitingCheck]) }}
    </p>
@endif
