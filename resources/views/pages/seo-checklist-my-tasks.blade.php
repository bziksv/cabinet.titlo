@component('component.card', [
    'title' => \App\SeoChecklist\SeoChecklistUserPreference::moduleTitleFor(auth()->id()),
    'documentTitle' => cabinet_sc_document_title(__('My tasks')),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.min.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/cabinet-seo-checklist.css') }}?v={{ @filemtime(public_path('css/cabinet-seo-checklist.css')) ?: time() }}">
    @endslot

    <div class="cabinet-sc-page"
         id="cabinetSeoChecklistPlan"
         data-sc-hub="my-tasks"
         data-csrf="{{ csrf_token() }}"
         data-status-url-template="{{ url('/checklist/__PROJECT__/items/__ID__/status') }}"
         data-note-url-template="{{ url('/checklist/__PROJECT__/items/__ID__/notes') }}"
         data-timer-start-url-template="{{ url('/checklist/__PROJECT__/items/__ID__/timer/start') }}"
         data-timer-stop-url-template="{{ url('/checklist/__PROJECT__/items/__ID__/timer/stop') }}"
         data-timer-stop-active-url="{{ route('pages.seo-checklist.timer.stop-active') }}"
         data-i18n-comment-required="{{ e(__('Comment required for this status')) }}"
         data-i18n-choose-status="{{ e(__('Choose task status')) }}"
         data-i18n-timer-start="{{ e(__('Start timer')) }}"
         data-i18n-timer-stop="{{ e(__('Stop timer')) }}"
         data-i18n-timer-start-short="{{ e(__('Timer start')) }}"
         data-i18n-timer-stop-short="{{ e(__('Timer stop')) }}">
        @include('pages.partials.seo-checklist-nav', [
            'scTab' => 'my-tasks',
            'scMyTasksCount' => $myTasksCount ?? null,
            'scReviewCount' => $reviewCount ?? null,
            'scShowReviewTab' => $showReviewTab ?? false,
            'scUnreadNotesCount' => $unreadNotesCount ?? null,
            'scProjectsCount' => $projectsCount ?? null,
            'scTeamCount' => $teamCount ?? null,
            'scTemplatesCount' => $templatesCount ?? null,
        ])

        <div class="cabinet-sc-plan-head">
            <div>
                <h2 class="cabinet-sc-plan-head__title">{{ __('Work plan') }}</h2>
                <p class="cabinet-sc-plan-head__hint">{{ __('Work plan hint') }}</p>
            </div>
            <a href="{{ route('pages.seo-checklist') }}" class="btn btn-outline-secondary btn-sm">{{ __('Projects') }}</a>
        </div>

        @php
            $groups = $plan['groups'] ?? [];
            $hasAny = false;
            foreach ($groups as $g) {
                if (($g['items'] ?? collect())->count() > 0) {
                    $hasAny = true;
                    break;
                }
            }
            $statusLabels = $statusLabels ?? [];
            $filterProjects = $filterProjects ?? collect();
        @endphp

        @if(!$hasAny)
            <div class="cabinet-sc-empty">
                <i class="bi bi-calendar2-check display-6 d-block mb-2 opacity-50"></i>
                <p class="fw-semibold mb-1">{{ __('No upcoming tasks') }}</p>
                <p class="small text-secondary mb-3">{{ __('No upcoming tasks hint') }}</p>
                <a href="{{ route('pages.seo-checklist') }}" class="btn btn-primary btn-sm">{{ __('Open projects') }}</a>
            </div>
        @else
            <div class="cabinet-sc-plan-filters" data-sc-plan-filters>
                <div class="cabinet-sc-plan-filters__label">{{ __('Filters') }}</div>
                <label class="cabinet-sc-plan-filters__project">
                    <span class="visually-hidden">{{ __('Filter by project') }}</span>
                    <select class="form-select form-select-sm"
                            data-sc-plan-project
                            data-placeholder="{{ __('All projects') }}">
                        <option value=""></option>
                        @foreach($filterProjects as $fp)
                            <option value="{{ $fp->id }}">
                                {{ $fp->domain }}@if(trim((string) ($fp->title ?? '')) !== '') · {{ $fp->title }}@endif
                            </option>
                        @endforeach
                    </select>
                </label>
                <div class="cabinet-sc-filters" title="{{ __('Filters can be combined') }}">
                    <button type="button" class="btn btn-sm btn-outline-secondary active" data-sc-plan-preset="all" aria-pressed="true">{{ __('All') }}</button>
                    <button type="button"
                            class="btn btn-sm btn-outline-secondary"
                            data-sc-plan-preset="overdue"
                            data-tip="{{ __('Overdue filter hint') }}"
                            title="{{ __('Overdue filter hint') }}"
                            aria-pressed="false">{{ __('Overdue') }}</button>
                    <button type="button"
                            class="btn btn-sm btn-outline-secondary"
                            data-sc-plan-preset="due-soon"
                            data-tip="{{ __('Due soon filter hint') }}"
                            title="{{ __('Due soon filter hint') }}"
                            aria-pressed="false">{{ __('Due soon') }}</button>
                    <button type="button"
                            class="btn btn-sm btn-outline-secondary"
                            data-sc-plan-preset="important"
                            title="{{ __('Important task hint') }}"
                            aria-pressed="false">{{ __('Important') }}</button>
                </div>
            </div>
            <div class="cabinet-sc-plan-filter-empty d-none" data-sc-plan-filter-empty>
                <p class="small text-secondary mb-0">{{ __('No tasks match filters') }}</p>
            </div>
            <div class="cabinet-sc-plan">
                @foreach($groups as $group)
                    @php $items = $group['items'] ?? collect(); @endphp
                    @if($items->count() === 0)
                        @continue
                    @endif
                    <section class="cabinet-sc-plan__group cabinet-sc-plan__group--{{ $group['key'] }}" data-sc-plan-group="{{ $group['key'] }}">
                        <header class="cabinet-sc-plan__group-head">
                            <h3 class="cabinet-sc-plan__group-title">{{ $group['title'] }}</h3>
                            <span class="cabinet-sc-plan__group-count" data-sc-plan-count>{{ $items->count() }}</span>
                        </header>
                        <ul class="cabinet-sc-plan__list">
                            @foreach($items as $item)
                                @include('pages.partials.seo-checklist-plan-item', [
                                    'item' => $item,
                                    'roleLabels' => $roleLabels,
                                    'statusLabels' => $statusLabels,
                                    'canApprove' => $item->project
                                        ? app(\App\Services\SeoChecklist\SeoChecklistService::class)
                                            ->canApproveReview($item->project, (int) auth()->id())
                                        : false,
                                    'canManage' => $item->project
                                        ? app(\App\Services\SeoChecklist\SeoChecklistService::class)
                                            ->canManageProject($item->project, (int) auth()->id())
                                        : false,
                                ])
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            </div>
        @endif
    </div>

    @include('pages.partials.seo-checklist-status-modal')

    @slot('js')
        <script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>
        <script src="{{ asset('js/cabinet-seo-checklist-status-modal.js') }}?v={{ @filemtime(public_path('js/cabinet-seo-checklist-status-modal.js')) ?: time() }}"></script>
        <script src="{{ asset('js/cabinet-seo-checklist-plan.js') }}?v={{ @filemtime(public_path('js/cabinet-seo-checklist-plan.js')) ?: time() }}"></script>
        <script>
            (function () {
                if (!window.jQuery || !jQuery.fn.select2) return;
                var $el = jQuery('[data-sc-plan-project]');
                if (!$el.length) return;
                $el.select2({
                    theme: 'bootstrap4',
                    width: '100%',
                    placeholder: $el.data('placeholder') || '',
                    allowClear: true,
                    language: {
                        noResults: function () { return @json(__('Nothing found')); },
                        searching: function () { return @json(__('Searching')); }
                    }
                });
            })();
        </script>
    @endslot
@endcomponent
