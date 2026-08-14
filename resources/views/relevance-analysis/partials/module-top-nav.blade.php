@php
    $admin = $admin ?? \App\User::isUserAdmin();
    $active = $active ?? 'though';
    $project = $project ?? null;
@endphp
<div class="border-bottom d-flex p-0 justify-content-between w-100 flex-wrap">
    <ul class="nav nav-pills p-2 flex-wrap">
        <li class="nav-item">
            <a class="nav-link {{ $active === 'analyzer' ? 'active' : '' }}"
               href="{{ route('relevance-analysis') }}">{{ __('Analyzer') }}</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $active === 'queue' ? 'active' : '' }}"
               href="{{ route('create.queue.view') }}">{{ __('Create page analysis tasks') }}</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $active === 'history' ? 'active' : '' }}"
               href="{{ route('relevance.history') }}">{{ __('History') }}</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $active === 'sharing' ? 'active' : '' }}"
               href="{{ route('sharing.view') }}">{{ __('Share your projects') }}</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $active === 'access' ? 'active' : '' }}"
               href="{{ route('access.project') }}">{{ __('Projects available to you') }}</a>
        </li>
        @if($admin)
            <li class="nav-item">
                <a class="nav-link admin-link {{ $active === 'stats' ? 'active' : '' }}"
                   href="{{ route('all.relevance.projects') }}">{{ __('Statistics') }}</a>
            </li>
            <li class="nav-item">
                <a class="nav-link admin-link {{ $active === 'config' ? 'active' : '' }}"
                   href="{{ route('show.config') }}">{{ __('Module administration') }}</a>
            </li>
        @endif
        @if($active === 'though')
            <li class="nav-item">
                <a class="nav-link active" href="{{ url()->current() }}">{{ __('Result through analyse') }}</a>
            </li>
        @elseif($active === 'details')
            <li class="nav-item">
                <a class="nav-link active" href="{{ url()->current() }}">{{ __('Show details') }}</a>
            </li>
        @endif
    </ul>
</div>
@if($active === 'though')
    <div class="px-3 pt-3 pb-0">
        <a href="{{ route('relevance.history') }}" class="btn btn-sm btn-outline-secondary">
            &larr; {{ __('History') }}
            @if($project && !empty($project->name))
                · {{ $project->name }}
            @endif
        </a>
    </div>
@endif
