@php
    /** @var \App\DomainMonitoring $project */
    $pending = $project->status === \App\DomainMonitoring::STATUS_AFTER_RESET;
    $hasPhrase = trim((string) ($project->phrase ?? '')) !== '';
    $phraseFail = $hasPhrase && $project->broken && $project->status === 'Keyword not found';
@endphp
<div class="cabinet-sm-check-type small">
    @if($pending || !isset($project->code))
        <span class="text-secondary">—</span>
    @elseif(!$hasPhrase)
        <span class="@if((int) $project->code === 200) text-success @else text-danger @endif">
            {{ __('Site monitoring check code', ['code' => $project->code]) }}
        </span>
    @elseif($phraseFail)
        <span class="text-danger fw-semibold">{{ __('Site monitoring phrase check fail') }}</span>
    @else
        <span class="text-success">{{ __('Site monitoring phrase check ok') }}</span>
    @endif
</div>
