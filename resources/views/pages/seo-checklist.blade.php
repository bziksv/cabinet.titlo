@component('component.card', [
    'titleHtml' => cabinet_sc_module_title_html(),
    'documentTitle' => cabinet_sc_document_title(__('Projects')),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.min.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/cabinet-seo-checklist.css') }}?v={{ @filemtime(public_path('css/cabinet-seo-checklist.css')) ?: time() }}">
    @endslot

    <div class="cabinet-sc-page" data-sc-hub="projects">
        @include('pages.partials.seo-checklist-nav', [
            'scTab' => 'projects',
            'scMyTasksCount' => $myTasksCount ?? null,
            'scReviewCount' => $reviewCount ?? null,
            'scShowReviewTab' => $showReviewTab ?? false,
            'scUnreadNotesCount' => $unreadNotesCount ?? null,
            'scProjectsCount' => $projects->count(),
            'scTeamCount' => $teamCount ?? null,
            'scTemplatesCount' => isset($templates) ? $templates->count() : null,
        ])

        @if(session('success'))
            <div class="alert alert-success py-2 px-3 small">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger py-2 px-3 small">{{ session('error') }}</div>
        @endif

        @if($projects->isEmpty())
            <div class="cabinet-sc-empty">
                <i class="bi bi-clipboard-check display-6 d-block mb-2 opacity-50"></i>
                <p class="fw-semibold mb-1">{{ __('No SEO checklists yet') }}</p>
                <p class="small text-secondary mb-3">{{ __('No SEO checklists yet hint') }}</p>
                @if(count($availableDomains) > 0)
                    <button type="button"
                            class="btn btn-primary btn-sm"
                            data-bs-toggle="modal"
                            data-bs-target="#cabinetScCreateModal">
                        {{ __('Create checklist') }}
                    </button>
                @endif
            </div>
        @else
            <div class="cabinet-sc-toolbar mb-3">
                <input type="search"
                       class="form-control form-control-sm cabinet-sc-search"
                       placeholder="{{ __('Search projects') }}…"
                       data-sc-project-search
                       autocomplete="off">
                <select class="form-select form-select-sm cabinet-sc-filter-select" data-sc-project-role-filter>
                    <option value="">{{ __('All roles') }}</option>
                    <option value="no-pm">{{ __('Without PM') }}</option>
                </select>
                <select class="form-select form-select-sm cabinet-sc-filter-select" data-sc-project-sort>
                    <option value="activity">{{ __('Sort by activity') }}</option>
                    <option value="progress">{{ __('Sort by progress') }}</option>
                    <option value="domain">{{ __('Sort by domain') }}</option>
                </select>
                @if(count($availableDomains) > 0)
                    <button type="button"
                            class="btn btn-primary btn-sm ms-auto"
                            data-bs-toggle="modal"
                            data-bs-target="#cabinetScCreateModal">
                        {{ __('Create checklist') }}
                    </button>
                @endif
            </div>
            <div class="cabinet-sc-grid" data-sc-project-grid>
                @foreach($projects as $project)
                    @php
                        $pct = $project->progress_total > 0
                            ? (int) round(100 * $project->progress_done / $project->progress_total)
                            : 0;
                        $owner = $project->ownerUser;
                        $pm = $project->pmUser;
                        $ownerName = $owner
                            ? (trim(($owner->name ?? '') . ' ' . ($owner->last_name ?? '')) ?: $owner->email)
                            : null;
                        $pmName = $pm
                            ? (trim(($pm->name ?? '') . ' ' . ($pm->last_name ?? '')) ?: $pm->email)
                            : null;
                        $ownerInitials = $owner
                            ? mb_strtoupper(mb_substr(trim(($owner->name ?? '') ?: ($owner->email ?? '?')), 0, 1))
                            : null;
                        $tpl = $project->template;
                        $team = $project->team;
                        $teamRoleLabelsLocal = $teamRoleLabels ?? \App\SeoChecklist\SeoChecklistTeam::roleLabels();
                        $previewByRole = [];
                        $teamMemberNames = [];
                        if ($team) {
                            foreach ($teamRoleLabelsLocal as $roleKey => $roleLabel) {
                                $previewByRole[$roleKey] = $team->members->where('role', $roleKey)->map(function ($member) use (&$teamMemberNames) {
                                    $u = $member->user;
                                    if (!$u) {
                                        return null;
                                    }
                                    $name = trim(($u->name ?? '') . ' ' . ($u->last_name ?? '')) ?: ($u->email ?? '');
                                    if ($name !== '') {
                                        $teamMemberNames[] = $name;
                                    }

                                    return $name !== '' ? $name : null;
                                })->filter()->values();
                            }
                        }
                        $activityTs = $project->last_activity_at ? $project->last_activity_at->getTimestamp() : 0;
                        $searchBlob = strtolower(implode(' ', array_filter(array_merge([
                            $project->domain,
                            $ownerName,
                            $pmName,
                            $owner ? $owner->email : null,
                            $pm ? $pm->email : null,
                            $tpl ? $tpl->title : null,
                            $team ? $team->title : null,
                        ], $teamMemberNames))));
                    @endphp
                    <a href="{{ route('pages.seo-checklist.show', ['id' => $project->id]) }}"
                       class="cabinet-sc-card cabinet-sc-card--project"
                       data-sc-project-card
                       data-search="{{ e($searchBlob) }}"
                       data-has-pm="{{ $pm ? '1' : '0' }}"
                       data-has-team="{{ $team ? '1' : '0' }}"
                       data-sort-activity="{{ $activityTs }}"
                       data-sort-progress="{{ $pct }}"
                       data-sort-domain="{{ e(mb_strtolower($project->domain)) }}">
                        <div class="cabinet-sc-card__top">
                            <strong class="cabinet-sc-card__domain">{{ $project->domain }}</strong>
                            <span class="cabinet-sc-card__top-right">
                                @php $kind = $accessKinds[$project->id] ?? 'account'; @endphp
                                @if($kind === 'pm')
                                    <span class="cabinet-sc-role cabinet-sc-role--pm">{{ __('Shared as PM') }}</span>
                                @elseif($kind === 'owner')
                                    <span class="cabinet-sc-role cabinet-sc-role--owner">{{ __('Shared as owner') }}</span>
                                @elseif($kind === 'auditor')
                                    <span class="cabinet-sc-role cabinet-sc-role--shared">{{ __('Shared as auditor') }}</span>
                                @elseif($kind === 'participant')
                                    <span class="cabinet-sc-role cabinet-sc-role--shared">{{ __('Shared as participant') }}</span>
                                @endif
                                <span class="cabinet-sc-card__pct">{{ $pct }}%</span>
                            </span>
                        </div>
                        <div class="cabinet-sc-card__bar" aria-hidden="true">
                            <span style="width: {{ $pct }}%"></span>
                        </div>
                        <div class="cabinet-sc-card__people">
                            @if($ownerInitials)
                                <span class="cabinet-sc-avatar" aria-hidden="true">{{ $ownerInitials }}</span>
                            @endif
                            <div class="cabinet-sc-card__people-text">
                                @if($team)
                                    <span class="cabinet-sc-card__team-name">
                                        {{ __('Team') }}: <strong>{{ $team->title }}</strong>
                                        <span class="cabinet-sc-card__team-count">{{ $team->members->count() }} {{ __('members') }}</span>
                                    </span>
                                @else
                                    <span>{{ __('SEO role owner') }}: <strong>{{ $ownerName ?: '—' }}</strong></span>
                                    <span>
                                        {{ __('SEO role PM') }}:
                                        @if($pmName)
                                            <strong>{{ $pmName }}</strong>
                                        @else
                                            <span class="cabinet-sc-role cabinet-sc-role--shared">{{ __('Without PM') }}</span>
                                        @endif
                                    </span>
                                    <span class="cabinet-sc-team-chip cabinet-sc-team-chip--empty">{{ __('No team') }}</span>
                                @endif
                            </div>
                        </div>
                        @if($team)
                            <div class="cabinet-sc-card__team">
                                @foreach($teamRoleLabelsLocal as $roleKey => $roleLabel)
                                    @php $names = $previewByRole[$roleKey] ?? collect(); @endphp
                                    @if($names->isNotEmpty())
                                        <span class="cabinet-sc-team-chip cabinet-sc-team-chip--{{ in_array($roleKey, ['auditor', 'participant'], true) ? 'shared' : $roleKey }}">
                                            <span class="cabinet-sc-team-chip__role">{{ $roleLabel }}</span>
                                            <span class="cabinet-sc-team-chip__people">{{ $names->take(2)->implode(', ') }}@if($names->count() > 2) +{{ $names->count() - 2 }}@endif</span>
                                        </span>
                                    @endif
                                @endforeach
                                @if($team->members->isEmpty())
                                    <span class="cabinet-sc-team-chip cabinet-sc-team-chip--empty">{{ __('No one yet') }}</span>
                                @endif
                            </div>
                        @endif
                        <div class="cabinet-sc-card__meta text-secondary small">
                            {{ $project->progress_done }}/{{ $project->progress_total }}
                            ·
                            {{ $project->last_activity_at ? $project->last_activity_at->format('d.m.Y H:i') : '—' }}
                            @if($tpl && !$tpl->is_system)
                                · <span class="cabinet-sc-card__tpl">{{ $tpl->title }}</span>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
            <p class="cabinet-sc-empty-filter small text-secondary d-none mb-0" data-sc-project-empty>{{ __('No projects match filters') }}</p>
        @endif

        @if(isset($archived) && $archived->isNotEmpty())
            <details class="cabinet-sc-archive mt-4">
                <summary>{{ __('Archive') }} ({{ $archived->count() }})</summary>
                <div class="cabinet-sc-grid mt-2">
                    @foreach($archived as $project)
                        <div class="cabinet-sc-card cabinet-sc-card--archived">
                            <div class="cabinet-sc-card__top">
                                <strong class="cabinet-sc-card__domain">{{ $project->domain }}</strong>
                                <span class="cabinet-sc-card__pct">{{ $project->progress_total > 0 ? (int) round(100 * $project->progress_done / $project->progress_total) : 0 }}%</span>
                            </div>
                            <div class="cabinet-sc-card__meta text-secondary small mb-2">
                                {{ $project->progress_done }}/{{ $project->progress_total }}
                            </div>
                            <div class="cabinet-sc-archive__actions">
                                <a href="{{ route('pages.seo-checklist.show', ['id' => $project->id]) }}" class="btn btn-sm btn-outline-secondary">{{ __('Open') }}</a>
                                <form method="post" action="{{ route('pages.seo-checklist.restore', ['id' => $project->id]) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-primary">{{ __('Restore') }}</button>
                                </form>
                                <button type="button"
                                        class="btn btn-sm btn-outline-danger"
                                        data-sc-delete-project
                                        data-url="{{ route('pages.seo-checklist.delete', ['id' => $project->id]) }}"
                                        data-domain="{{ $project->domain }}">
                                    {{ __('Delete') }}
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </details>
        @endif
    </div>

    @if(count($availableDomains) > 0)
        <div class="modal fade" id="cabinetScCreateModal" tabindex="-1" aria-labelledby="cabinetScCreateModalTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="post" action="{{ route('pages.seo-checklist.store') }}" data-sc-create-form>
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title" id="cabinetScCreateModalTitle">{{ __('Create checklist') }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <label class="cabinet-sc-create__label" for="cabinet-sc-domain">{{ __('Create checklist for site') }}</label>
                            <select name="domain"
                                    id="cabinet-sc-domain"
                                    class="form-select form-select-sm mb-3"
                                    data-sc-domain-select
                                    required
                                    data-placeholder="{{ __('Choose a site') }}…">
                                <option value=""></option>
                                @foreach($availableDomains as $domain)
                                    <option value="{{ $domain }}">{{ $domain }}</option>
                                @endforeach
                            </select>
                            @if(isset($templates) && $templates->count() > 0)
                                <label class="cabinet-sc-create__label" for="cabinet-sc-template">{{ __('Template') }}</label>
                                <select name="template_id" id="cabinet-sc-template" class="form-select form-select-sm" data-sc-template-select>
                                    @foreach($templates as $tpl)
                                        <option value="{{ $tpl->id }}" @if($tpl->is_system) selected @endif>
                                            {{ $tpl->title }}@if($tpl->is_system) ({{ __('System') }})@endif
                                            · {{ $tpl->tasks_count }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="small text-secondary mb-0 mt-2">{{ __('SEO checklist default template hint') }}</p>
                            @endif
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary btn-sm">{{ __('Create') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @include('pages.partials.seo-checklist-delete-project-modal')

    @slot('js')
        <script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>
        <script src="{{ asset('js/cabinet-seo-checklist-hub.js') }}?v={{ @filemtime(public_path('js/cabinet-seo-checklist-hub.js')) ?: time() }}"></script>
    @endslot
@endcomponent
