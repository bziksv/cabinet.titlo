@php
    /** @var \App\DomainMonitoring $project */
    $pending = $project->status === \App\DomainMonitoring::STATUS_AFTER_RESET;
    $statusClass = $pending
        ? 'cabinet-sm-status--pending'
        : ($project->broken ? 'cabinet-sm-status--bad' : 'cabinet-sm-status--ok');
@endphp
<div class="cabinet-sm-status {{ $statusClass }} small mb-0">
    <div>{{ __($project->status) }}</div>
    @if($pending)
        <div class="text-secondary">{{ __('Site monitoring status reset hint') }}</div>
    @elseif(isset($project->code))
        <div>{{ __('http code') }}: {{ $project->code }}</div>
        <div>{{ __('Uptime') }}: {{ $project->uptime_percent }}%</div>
    @endif
</div>
