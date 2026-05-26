@php
    /** @var \App\DomainMonitoring $project */
    $pending = $project->status === \App\DomainMonitoring::STATUS_AFTER_RESET;
@endphp
<div class="cabinet-sm-uptime small text-end text-nowrap">
    @if($pending || $project->uptime_percent === null)
        <span class="text-secondary">—</span>
    @else
        <strong class="text-body">{{ $project->uptime_percent }}%</strong>
    @endif
</div>
