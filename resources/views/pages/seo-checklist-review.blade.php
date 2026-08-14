@component('component.card', [
    'title' => \App\SeoChecklist\SeoChecklistUserPreference::moduleTitleFor(auth()->id()),
    'documentTitle' => cabinet_sc_document_title(__('For review')),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-seo-checklist.css') }}?v={{ @filemtime(public_path('css/cabinet-seo-checklist.css')) ?: time() }}">
    @endslot

    <div class="cabinet-sc-page"
         id="cabinetSeoChecklistPlan"
         data-sc-hub="review"
         data-csrf="{{ csrf_token() }}"
         data-status-url-template="{{ url('/checklist/__PROJECT__/items/__ID__/status') }}"
         data-note-url-template="{{ url('/checklist/__PROJECT__/items/__ID__/notes') }}"
         data-timer-start-url-template="{{ url('/checklist/__PROJECT__/items/__ID__/timer/start') }}"
         data-timer-stop-url-template="{{ url('/checklist/__PROJECT__/items/__ID__/timer/stop') }}"
         data-timer-stop-active-url="{{ route('pages.seo-checklist.timer.stop-active') }}"
         data-can-approve="1"
         data-i18n-comment-required="{{ e(__('Comment required for this status')) }}"
         data-i18n-choose-status="{{ e(__('Choose task status')) }}"
         data-i18n-timer-start="{{ e(__('Start timer')) }}"
         data-i18n-timer-stop="{{ e(__('Stop timer')) }}"
         data-i18n-timer-start-short="{{ e(__('Timer start')) }}"
         data-i18n-timer-stop-short="{{ e(__('Timer stop')) }}">
        @include('pages.partials.seo-checklist-nav', [
            'scTab' => 'review',
            'scMyTasksCount' => $myTasksCount ?? null,
            'scReviewCount' => $reviewCount ?? null,
            'scShowReviewTab' => true,
            'scUnreadNotesCount' => $unreadNotesCount ?? null,
            'scProjectsCount' => $projectsCount ?? null,
            'scTeamCount' => $teamCount ?? null,
            'scTemplatesCount' => $templatesCount ?? null,
        ])

        <div class="cabinet-sc-plan-head">
            <div>
                <h2 class="cabinet-sc-plan-head__title">{{ __('For review') }}</h2>
                <p class="cabinet-sc-plan-head__hint">{{ __('For review hint') }}</p>
            </div>
        </div>

        @if($items->isEmpty())
            <div class="cabinet-sc-empty">
                <i class="bi bi-clipboard2-check display-6 d-block mb-2 opacity-50"></i>
                <p class="fw-semibold mb-1">{{ __('No tasks for review') }}</p>
                <p class="small text-secondary mb-0">{{ __('No tasks for review hint') }}</p>
            </div>
        @else
            <div class="cabinet-sc-plan">
                <section class="cabinet-sc-plan__group cabinet-sc-plan__group--review" data-sc-plan-group="review">
                    <header class="cabinet-sc-plan__group-head">
                        <h3 class="cabinet-sc-plan__group-title">{{ __('Status review') }}</h3>
                        <span class="cabinet-sc-plan__group-count" data-sc-plan-count>{{ $items->count() }}</span>
                    </header>
                    <ul class="cabinet-sc-plan__list">
                        @foreach($items as $item)
                            @include('pages.partials.seo-checklist-plan-item', [
                                'item' => $item,
                                'roleLabels' => $roleLabels,
                                'statusLabels' => $statusLabels,
                                'canApprove' => true,
                                'canManage' => $item->project
                                    ? app(\App\Services\SeoChecklist\SeoChecklistService::class)
                                        ->canManageProject($item->project, (int) auth()->id())
                                    : false,
                            ])
                        @endforeach
                    </ul>
                </section>
            </div>
        @endif
    </div>

    @include('pages.partials.seo-checklist-status-modal')

    @slot('js')
        <script src="{{ asset('js/cabinet-seo-checklist-status-modal.js') }}?v={{ @filemtime(public_path('js/cabinet-seo-checklist-status-modal.js')) ?: time() }}"></script>
        <script src="{{ asset('js/cabinet-seo-checklist-plan.js') }}?v={{ @filemtime(public_path('js/cabinet-seo-checklist-plan.js')) ?: time() }}"></script>
    @endslot
@endcomponent
