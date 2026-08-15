@component('component.card', [
    'titleHtml' => cabinet_sc_module_title_html(),
    'documentTitle' => cabinet_sc_document_title(__('My tasks')),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.min.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/cabinet-seo-checklist.css') }}?v={{ @filemtime(public_path('css/cabinet-seo-checklist.css')) ?: time() }}">
    @endslot

    @php
        $groups = $plan['groups'] ?? [];
        $stages = $plan['stages'] ?? [];
        $planSections = !empty($stages) ? $stages : array_values(array_filter($groups, function ($g) {
            return ($g['items'] ?? collect())->count() > 0;
        }));
        $hasAny = count($planSections) > 0;
        $statusLabels = $statusLabels ?? [];
        $filterProjects = $filterProjects ?? collect();
        $planCount = (int) ($plan['count'] ?? $myTasksCount ?? 0);
        $overdueCount = (int) ($plan['overdue'] ?? 0);
        $useStages = !empty($stages);
    @endphp

    <div class="cabinet-sc-page cabinet-sc-plan-v2"
         id="cabinetSeoChecklistPlan"
         data-plan-visual="v2"
         data-sc-hub="my-tasks"
         data-csrf="{{ csrf_token() }}"
         data-status-url-template="{{ url('/checklist/__PROJECT__/items/__ID__/status') }}"
         data-note-url-template="{{ url('/checklist/__PROJECT__/items/__ID__/notes') }}"
         data-subtask-url-template="{{ url('/checklist/__PROJECT__/items/__ID__/subtasks') }}"
         data-subtask-reorder-url-template="{{ url('/checklist/__PROJECT__/items/__ID__/subtasks/reorder') }}"
         data-update-url-template="{{ url('/checklist/__PROJECT__/items/__ID__') }}"
         data-mark-read-url="{{ route('pages.seo-checklist.chronicle.read') }}"
         data-mark-unread-url="{{ route('pages.seo-checklist.chronicle.unread') }}"
         data-timer-start-url-template="{{ url('/checklist/__PROJECT__/items/__ID__/timer/start') }}"
         data-timer-stop-url-template="{{ url('/checklist/__PROJECT__/items/__ID__/timer/stop') }}"
         data-timer-stop-active-url="{{ route('pages.seo-checklist.timer.stop-active') }}"
         data-i18n-comment-required="{{ e(__('Comment required for this status')) }}"
         data-i18n-choose-status="{{ e(__('Choose task status')) }}"
         data-i18n-timer-start="{{ e(__('Start timer')) }}"
         data-i18n-timer-stop="{{ e(__('Stop timer')) }}"
         data-i18n-timer-start-short="{{ e(__('Timer start')) }}"
         data-i18n-timer-stop-short="{{ e(__('Timer stop')) }}"
         data-i18n-waiting-review="{{ e(__('Waiting for review')) }}"
         data-i18n-mark-read="{{ e(__('Mark as read')) }}"
         data-i18n-mark-unread="{{ e(__('Mark note unread')) }}"
         data-i18n-mark-unread-short="{{ e(__('Mark unread short')) }}"
         data-i18n-unread="{{ e(__('Unread')) }}"
         data-i18n-send-review-first="{{ e(__('Send to review first')) }}"
         data-i18n-close-subs-first="{{ e(__('Close open checklist items first')) }}"
         data-i18n-include-report="{{ e(__('Include in SEO reports')) }}"
         data-i18n-include-report-hint="{{ e(__('Include in SEO reports hint')) }}">
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

        <div class="cabinet-sc-plan-topbar">
            <div class="cabinet-sc-plan-topbar__main">
                <div>
                    <p class="cabinet-sc-plan-topbar__eyebrow">{{ __('My tasks') }}</p>
                    <h1 class="cabinet-sc-plan-topbar__title">{{ __('Work plan') }}</h1>
                    <p class="cabinet-sc-plan-topbar__meta">
                        {{ number_format($planCount, 0, '', ' ') }} {{ __('Tasks') }}
                        @if($overdueCount > 0)
                            · <span class="cabinet-sc-plan-topbar__hot">{{ number_format($overdueCount, 0, '', ' ') }} {{ __('Overdue') }}</span>
                        @endif
                        · {{ number_format(count($planSections), 0, '', ' ') }} {{ $useStages ? __('Stages') : __('Groups') }}
                    </p>
                </div>
                <div class="cabinet-sc-plan-topbar__actions">
                    <a href="{{ route('pages.seo-checklist') }}" class="cabinet-sc-plan-chip-btn">{{ __('Projects') }}</a>
                </div>
            </div>
        </div>

        @if(!$hasAny)
            <div class="cabinet-sc-empty">
                <i class="bi bi-calendar2-check display-6 d-block mb-2 opacity-50"></i>
                <p class="fw-semibold mb-1">{{ __('No upcoming tasks') }}</p>
                <p class="small text-secondary mb-3">{{ __('No upcoming tasks hint') }}</p>
                <a href="{{ route('pages.seo-checklist') }}" class="btn btn-primary btn-sm">{{ __('Open projects') }}</a>
            </div>
        @else
            <div class="cabinet-sc-plan-layout">
                <div class="cabinet-sc-plan-rail-slot" data-sc-plan-rail-slot>
                    <nav class="cabinet-sc-plan-rail" data-sc-plan-rail aria-label="{{ $useStages ? __('Stages') : __('Groups') }}">
                        <div class="cabinet-sc-plan-rail__head">{{ $useStages ? __('Stages') : __('Groups') }}</div>
                        <ul class="cabinet-sc-plan-rail__list">
                            @foreach($planSections as $railGroup)
                                <li>
                                    <div class="cabinet-sc-plan-rail__item">
                                        <a class="cabinet-sc-plan-rail__link"
                                           href="#sc-plan-{{ $railGroup['key'] }}"
                                           data-sc-plan-rail-link="{{ $railGroup['key'] }}">
                                            <span class="cabinet-sc-plan-rail__name">{{ $railGroup['title'] }}</span>
                                            <span class="cabinet-sc-plan-rail__count">{{ number_format($railGroup['count'] ?? ($railGroup['items'] ?? collect())->count(), 0, '', ' ') }}</span>
                                        </a>
                                        @if(!empty($railGroup['chips']))
                                            <div class="cabinet-sc-plan-rail__chips" role="group" aria-label="{{ __('Status') }}">
                                                @foreach($railGroup['chips'] as $chip)
                                                    <button type="button"
                                                            class="cabinet-sc-plan-rail__chip cabinet-sc-plan-rail__chip--{{ $chip['key'] }}"
                                                            data-sc-plan-rail-chip="{{ $chip['key'] }}"
                                                            data-sc-plan-rail-chip-stage="{{ $railGroup['key'] }}"
                                                            data-tip="{{ $chip['title'] }} — {{ number_format($chip['count'], 0, '', ' ') }}"
                                                            aria-label="{{ $chip['title'] }} — {{ number_format($chip['count'], 0, '', ' ') }}">
                                                        <i class="bi {{ $chip['icon'] }}" aria-hidden="true"></i>
                                                        <span>{{ number_format($chip['count'], 0, '', ' ') }}</span>
                                                    </button>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </nav>
                </div>

                <div class="cabinet-sc-plan-workspace">
                    <div class="cabinet-sc-plan-filters cabinet-sc-plan-filters--sticky" data-sc-plan-filters>
                        <div class="cabinet-sc-plan-filters__bar">
                            <label class="cabinet-sc-plan-filters__project">
                                <span class="visually-hidden">{{ __('Filter by project') }}</span>
                                <select class="form-select form-select-sm"
                                        data-sc-plan-project
                                        data-placeholder="{{ __('All projects') }}">
                                    <option value=""></option>
                                    @foreach($filterProjects as $fp)
                                        @php
                                            $fpDomain = trim((string) ($fp->domain ?? ''));
                                            $fpTitle = trim((string) ($fp->title ?? ''));
                                            $fpTitleUseful = $fpTitle !== ''
                                                && strcasecmp($fpTitle, $fpDomain) !== 0
                                                && ($fpDomain === '' || mb_stripos($fpTitle, $fpDomain) === false);
                                        @endphp
                                        <option value="{{ $fp->id }}">
                                            {{ $fpDomain }}@if($fpTitleUseful) · {{ $fpTitle }}@endif
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                            <button type="button"
                                    class="cabinet-sc-plan-search__toggle"
                                    data-sc-plan-search-toggle
                                    data-tip="{{ __('Search tasks and checklist items') }}"
                                    aria-label="{{ __('Search tasks and checklist items') }}"
                                    aria-expanded="false"
                                    aria-controls="cabinetScPlanSearch">
                                <i class="bi bi-search" aria-hidden="true"></i>
                            </button>
                            <div class="cabinet-sc-filters">
                                <button type="button" class="btn btn-sm btn-outline-secondary active" data-sc-plan-preset="all" aria-pressed="true">{{ __('All') }}</button>
                                <button type="button"
                                        class="btn btn-sm btn-outline-secondary"
                                        data-sc-plan-preset="overdue"
                                        data-tip="{{ __('Overdue filter hint') }}"
                                        aria-pressed="false">{{ __('Overdue') }}</button>
                                <button type="button"
                                        class="btn btn-sm btn-outline-secondary"
                                        data-sc-plan-preset="due-soon"
                                        data-tip="{{ __('Due soon filter hint') }}"
                                        aria-pressed="false">{{ __('Due soon') }}</button>
                                <button type="button"
                                        class="btn btn-sm btn-outline-secondary"
                                        data-sc-plan-preset="no-review"
                                        data-tip="{{ __('Without review filter hint') }}"
                                        aria-pressed="false">{{ __('Without review') }}</button>
                                <button type="button"
                                        class="btn btn-sm btn-outline-secondary"
                                        data-sc-plan-preset="important"
                                        data-tip="{{ __('Important filter hint') }}"
                                        aria-pressed="false">{{ __('Important') }}</button>
                                <button type="button"
                                        class="btn btn-sm btn-outline-secondary"
                                        data-sc-plan-preset="unread-notes"
                                        data-tip="{{ __('Unread notes filter hint') }}"
                                        aria-pressed="false">{{ __('Unread notes') }}</button>
                                @if(!empty($showReviewTab) && (int) ($reviewCount ?? 0) > 0)
                                    <button type="button"
                                            class="btn btn-sm btn-outline-secondary"
                                            data-sc-plan-preset="review"
                                            data-tip="{{ __('For review filter hint') }}"
                                            aria-pressed="false">
                                        {{ __('Status review') }}
                                        <span class="cabinet-sc-filters__badge">{{ number_format((int) $reviewCount, 0, '', ' ') }}</span>
                                    </button>
                                @endif
                                <button type="button"
                                        class="btn btn-sm btn-outline-secondary"
                                        data-sc-plan-preset="done"
                                        data-tip="{{ __('Completed tasks filter hint') }}"
                                        aria-pressed="false">{{ __('Completed tasks') }}</button>
                            </div>
                        </div>
                        <div class="cabinet-sc-plan-search-panel" data-sc-plan-search-panel hidden>
                            <label class="visually-hidden" for="cabinetScPlanSearch">{{ __('Search tasks') }}</label>
                            <input type="search"
                                   id="cabinetScPlanSearch"
                                   class="form-control form-control-sm cabinet-sc-plan-search__input"
                                   data-sc-plan-search
                                   placeholder="{{ __('Search tasks') }}…"
                                   autocomplete="off">
                        </div>
                    </div>

                    <div class="cabinet-sc-plan-filter-empty d-none" data-sc-plan-filter-empty>
                        <p class="small text-secondary mb-0">{{ __('No tasks match filters') }}</p>
                    </div>

                    <div class="cabinet-sc-plan">
                        @foreach($planSections as $group)
                            @php $items = $group['items'] ?? collect(); @endphp
                            @if($items->count() === 0)
                                @continue
                            @endif
                            <section class="cabinet-sc-plan__group cabinet-sc-plan__group--stage"
                                     id="sc-plan-{{ $group['key'] }}"
                                     data-sc-plan-group="{{ $group['key'] }}">
                                <header class="cabinet-sc-plan__group-head">
                                    <h3 class="cabinet-sc-plan__group-title">{{ $group['title'] }}</h3>
                                    <span class="cabinet-sc-plan__group-count" data-sc-plan-count>{{ number_format($items->count(), 0, '', ' ') }}</span>
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
                </div>
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

