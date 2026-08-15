@component('component.card', [
    'titleHtml' => cabinet_sc_module_title_html(),
    'documentTitle' => cabinet_sc_document_title(__('Team')),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-seo-checklist.css') }}?v={{ @filemtime(public_path('css/cabinet-seo-checklist.css')) ?: time() }}">
    @endslot

    <div class="cabinet-sc-page" data-sc-hub="team">
        @include('pages.partials.seo-checklist-nav', [
            'scTab' => 'team',
            'scMyTasksCount' => $myTasksCount ?? null,
            'scReviewCount' => $reviewCount ?? null,
            'scShowReviewTab' => $showReviewTab ?? false,
            'scUnreadNotesCount' => $unreadNotesCount ?? null,
            'scProjectsCount' => $projectsCount ?? null,
            'scTeamCount' => isset($teams) ? $teams->count() : null,
            'scTemplatesCount' => $templatesCount ?? null,
        ])

        <p class="small text-secondary mb-3">
            {{ __('Team also in profile') }}
            <a href="{{ route('profile.index') }}#team">{{ __('My team') }}</a>
        </p>

        @include('pages.partials.seo-checklist-team-body', [
            'teams' => $teams ?? collect(),
            'projects' => $projects ?? collect(),
            'teamRoleLabels' => $teamRoleLabels ?? [],
            'teamCandidates' => $teamCandidates ?? collect(),
            'showChecklistAssign' => true,
        ])
    </div>

    @slot('js')
        <script src="{{ asset('js/cabinet-seo-checklist-hub.js') }}?v={{ @filemtime(public_path('js/cabinet-seo-checklist-hub.js')) ?: time() }}"></script>
    @endslot
@endcomponent
