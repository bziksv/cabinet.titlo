@php
    /** @var \App\Support\DomainInformationListSummary $summary */
@endphp
<p class="text-secondary cabinet-di-kpi-hint mb-3 mb-md-4">
    {{ __('Domain information list kpi hint') }}
</p>

<div class="row g-3 mb-4 cabinet-di-kpi cabinet-module-kpi" aria-live="polite">
    <div class="col-6 col-lg-3">
        <div class="info-box shadow-sm h-100">
            <span class="info-box-icon text-bg-secondary shadow-sm">
                <i class="bi bi-grid-3x3-gap" aria-hidden="true"></i>
            </span>
            <div class="info-box-content">
                <span class="info-box-text">{{ __('Domain information kpi total') }}</span>
                <span class="info-box-number">{{ $summary->total }}</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="info-box shadow-sm h-100">
            <span class="info-box-icon text-bg-success shadow-sm">
                <i class="bi bi-shield-check" aria-hidden="true"></i>
            </span>
            <div class="info-box-content">
                <span class="info-box-text">{{ __('Domain information kpi ok') }}</span>
                <span class="info-box-number @if($summary->ok > 0) text-success @endif">{{ $summary->ok }}</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="info-box shadow-sm h-100">
            <span class="info-box-icon text-bg-danger shadow-sm">
                <i class="bi bi-exclamation-octagon" aria-hidden="true"></i>
            </span>
            <div class="info-box-content">
                <span class="info-box-text">{{ __('Domain information kpi with issues') }}</span>
                <span class="info-box-number @if($summary->withIssues > 0) text-danger @endif">{{ $summary->withIssues }}</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="info-box shadow-sm h-100">
            <span class="info-box-icon text-bg-warning shadow-sm">
                <i class="bi bi-calendar-event" aria-hidden="true"></i>
            </span>
            <div class="info-box-content">
                <span class="info-box-text">{{ __('Domain information kpi expiring soon') }}</span>
                <span class="info-box-number @if($summary->expiringSoon > 0) text-warning @endif">{{ $summary->expiringSoon }}</span>
            </div>
        </div>
    </div>
</div>

@if($summary->dnsMonitoring > 0)
    <p class="small text-secondary mb-3">
        <i class="bi bi-hdd-network me-1" aria-hidden="true"></i>
        {{ __('Domain information kpi dns monitoring', ['count' => $summary->dnsMonitoring]) }}
    </p>
@endif
