@php
    /** @var \App\DomainMonitoring $project */
    $pending = $project->status === \App\DomainMonitoring::STATUS_AFTER_RESET;
    $badgeClass = $pending
        ? 'text-bg-secondary'
        : ($project->broken ? 'text-bg-danger' : 'text-bg-success');
@endphp
<div class="cabinet-sm-status small mb-0">
    <span class="badge {{ $badgeClass }} fw-normal">{{ __($project->status) }}</span>
    @if($pending)
        <div class="text-secondary mt-1">{{ __('Site monitoring status reset hint') }}</div>
    @endif
</div>
