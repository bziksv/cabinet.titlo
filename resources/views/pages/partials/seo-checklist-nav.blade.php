@php
    $scTab = $scTab ?? 'projects';
    $scModuleTitle = $seoChecklistModuleTitle
        ?? \App\SeoChecklist\SeoChecklistUserPreference::moduleTitleFor(auth()->id());
    $scContextProject = $scContextProject ?? null;
    $scContextTemplate = $scContextTemplate ?? null;
    $scProjectsTabActive = in_array($scTab, ['projects', 'project'], true);
    $scTemplatesTabActive = in_array($scTab, ['templates', 'template'], true);
@endphp
<div class="cabinet-sc-nav-shell">
    @include('pages.partials.module-beta-banner', [
        'moduleName' => $scModuleTitle,
    ])
    <nav class="cabinet-sc-tabs" aria-label="{{ $scModuleTitle }}">
        <a href="{{ route('pages.seo-checklist.chronicle', ((int) ($scUnreadNotesCount ?? 0) > 0) ? ['view' => 'unread'] : []) }}"
           class="cabinet-sc-tabs__item @if($scTab === 'chronicle') is-active @endif"
           @if($scTab === 'chronicle') aria-current="page" @endif>
            {{ __('Chronicle') }}
            <span class="cabinet-sc-tabs__count is-hot"
                  data-sc-unread-nav-count
                  @if((int) ($scUnreadNotesCount ?? 0) < 1) hidden @endif>{{ (int) ($scUnreadNotesCount ?? 0) > 99 ? '99+' : (int) ($scUnreadNotesCount ?? 0) }}</span>
        </a>
        <a href="{{ route('pages.seo-checklist.my-tasks') }}"
           class="cabinet-sc-tabs__item @if($scTab === 'my-tasks') is-active @endif"
           @if($scTab === 'my-tasks') aria-current="page" @endif>
            {{ __('My tasks') }}
            @if(isset($scMyTasksCount))
                <span class="cabinet-sc-tabs__count @if((int) $scMyTasksCount > 0) is-hot @endif">{{ $scMyTasksCount }}</span>
            @endif
        </a>
        @if(!empty($scShowReviewTab))
            <a href="{{ route('pages.seo-checklist.review') }}"
               class="cabinet-sc-tabs__item @if($scTab === 'review') is-active @endif"
               @if($scTab === 'review') aria-current="page" @endif>
                {{ __('For review') }}
                @if(isset($scReviewCount))
                    <span class="cabinet-sc-tabs__count @if((int) $scReviewCount > 0) is-hot @endif">{{ $scReviewCount }}</span>
                @endif
            </a>
        @endif
        <a href="{{ route('pages.seo-checklist.timesheet') }}"
           class="cabinet-sc-tabs__item @if($scTab === 'timesheet') is-active @endif"
           @if($scTab === 'timesheet') aria-current="page" @endif>
            {{ __('Time log') }}
        </a>
        <a href="{{ route('pages.seo-checklist') }}"
           class="cabinet-sc-tabs__item @if($scProjectsTabActive) is-active @endif"
           @if($scProjectsTabActive) aria-current="page" @endif>
            {{ __('Projects') }}
            @if(isset($scProjectsCount))
                <span class="cabinet-sc-tabs__count">{{ $scProjectsCount }}</span>
            @endif
        </a>
        <a href="{{ route('pages.seo-checklist.team') }}"
           class="cabinet-sc-tabs__item @if($scTab === 'team') is-active @endif"
           @if($scTab === 'team') aria-current="page" @endif>
            {{ __('Team') }}
            @if(isset($scTeamCount))
                <span class="cabinet-sc-tabs__count">{{ $scTeamCount }}</span>
            @endif
        </a>
        <a href="{{ route('pages.seo-checklist.templates') }}"
           class="cabinet-sc-tabs__item @if($scTemplatesTabActive) is-active @endif"
           @if($scTemplatesTabActive) aria-current="page" @endif>
            {{ __('Templates') }}
            @if(isset($scTemplatesCount))
                <span class="cabinet-sc-tabs__count">{{ $scTemplatesCount }}</span>
            @endif
        </a>
        <details class="cabinet-sc-module-rename">
            <summary class="cabinet-sc-module-rename__btn" title="{{ __('Rename section') }}">
                <i class="bi bi-pencil" aria-hidden="true"></i>
                <span>{{ __('Rename') }}</span>
            </summary>
            <form method="post"
                  action="{{ route('pages.seo-checklist.module-title') }}"
                  class="cabinet-sc-module-rename__form">
                @csrf
                <label class="cabinet-sc-module-rename__label" for="scModuleTitleInput">{{ __('Section name') }}</label>
                <input id="scModuleTitleInput"
                       type="text"
                       name="module_title"
                       class="form-control form-control-sm"
                       value="{{ $scModuleTitle }}"
                       maxlength="40"
                       placeholder="{{ __('SEO Checklist') }}"
                       autocomplete="off">
                <p class="cabinet-sc-module-rename__hint">{{ __('Section name hint') }}</p>
                <div class="cabinet-sc-module-rename__actions">
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('Save') }}</button>
                </div>
            </form>
        </details>
    </nav>

    @if($scContextProject)
        <div class="cabinet-sc-context" aria-label="{{ __('Project') }}">
            <div class="cabinet-sc-context__crumb">
                <a href="{{ route('pages.seo-checklist') }}">{{ __('Projects') }}</a>
                <span class="cabinet-sc-context__sep" aria-hidden="true">/</span>
                @php
                    $scCtxTitle = trim((string) ($scContextProject->title ?? ''));
                    $scCtxLabel = $scContextProject->domain;
                    if ($scCtxTitle !== '' && $scCtxTitle !== $scContextProject->domain) {
                        $scCtxLabel = $scContextProject->domain . ' — ' . $scCtxTitle;
                    }
                @endphp
                <span class="cabinet-sc-context__current">{{ $scCtxLabel }}</span>
            </div>
            <a href="{{ route('pages.seo-checklist') }}" class="cabinet-sc-context__back">
                ← {{ __('All checklists') }}
            </a>
        </div>
    @elseif($scContextTemplate)
        <div class="cabinet-sc-context" aria-label="{{ __('Templates') }}">
            <div class="cabinet-sc-context__crumb">
                <a href="{{ route('pages.seo-checklist.templates') }}">{{ __('Templates') }}</a>
                <span class="cabinet-sc-context__sep" aria-hidden="true">/</span>
                <span class="cabinet-sc-context__current">{{ $scContextTemplate->title }}</span>
            </div>
            <a href="{{ route('pages.seo-checklist.templates') }}" class="cabinet-sc-context__back">
                ← {{ __('SEO checklist templates') }}
            </a>
        </div>
    @endif
</div>
