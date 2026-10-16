@php
    $returnTo = $returnTo ?? null;
    $showChecklistAssign = isset($showChecklistAssign) ? (bool) $showChecklistAssign : true;
    $teamsLead = $teamsLead ?? __('SEO checklist teams lead');
@endphp

@if(session('success'))
    <div class="alert alert-success py-2 px-3 small">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger py-2 px-3 small">{{ session('error') }}</div>
@endif
@if(session('status') && ($returnTo ?? '') === 'profile')
    <div class="alert alert-success py-2 px-3 small">{{ session('status') }}</div>
@endif

@php
    $teamCreateModalId = 'cabinet-team-create-modal';
@endphp

<div class="cabinet-sc-panel cabinet-sc-panel--create mb-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <div class="cabinet-sc-panel__title mb-1">{{ __('Create team') }}</div>
            <p class="small text-secondary mb-0">{{ $teamsLead }}</p>
        </div>
        <button type="button"
                class="btn btn-primary btn-sm"
                data-bs-toggle="modal"
                data-bs-target="#{{ $teamCreateModalId }}">
            <i class="bi bi-plus-lg" aria-hidden="true"></i>
            {{ __('Create team') }}
        </button>
    </div>
</div>

@include('pages.partials.team-create-modal', [
    'modalId' => $teamCreateModalId,
    'returnTo' => $returnTo,
    'showManageLink' => false,
    'lead' => $teamsLead,
    'teamCandidates' => $teamCandidates ?? collect(),
    'teamRoleLabels' => $teamRoleLabels ?? \App\SeoChecklist\SeoChecklistTeam::roleLabels(),
])

@if(isset($teams) && $teams->isEmpty())
    <div class="cabinet-sc-empty mb-3">
        <i class="bi bi-people display-6 d-block mb-2 opacity-50"></i>
        <p class="fw-semibold mb-1">{{ __('No teams yet') }}</p>
        <p class="small text-secondary mb-2">{{ __('No teams yet hint') }}</p>
        <button type="button"
                class="btn btn-primary btn-sm"
                data-bs-toggle="modal"
                data-bs-target="#{{ $teamCreateModalId }}">
            {{ __('Create team') }}
        </button>
    </div>
@elseif(isset($teams))
    <div class="cabinet-sc-section-label">
        <span>{{ __('Teams') }}</span>
        <span class="cabinet-sc-section-label__count">{{ $teams->count() }}</span>
    </div>
    <div class="cabinet-sc-toolbar mb-3">
        <input type="search"
               class="form-control form-control-sm cabinet-sc-search"
               placeholder="{{ __('Search teams') }}…"
               data-sc-team-search
               autocomplete="off">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-sc-teams-expand>{{ __('Expand all') }}</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-sc-teams-collapse>{{ __('Collapse all') }}</button>
    </div>
    <div class="cabinet-sc-team-list mb-4" data-sc-team-list>
        @foreach($teams as $team)
            @php
                $memberNames = $team->members->map(function ($member) {
                    $u = $member->user;
                    if (!$u) {
                        return '';
                    }
                    return trim(($u->name ?? '') . ' ' . ($u->last_name ?? '')) . ' ' . ($u->email ?? '');
                })->filter()->implode(' ');
                $teamSearch = mb_strtolower(trim($team->title . ' ' . ($team->description ?? '') . ' ' . $memberNames));
                $previewByRole = [];
                foreach (($teamRoleLabels ?? []) as $roleKey => $roleLabel) {
                    $previewByRole[$roleKey] = $team->members->where('role', $roleKey)->map(function ($member) {
                        $u = $member->user;
                        if (!$u) {
                            return null;
                        }
                        $name = trim(($u->name ?? '') . ' ' . ($u->last_name ?? ''));
                        return $name !== '' ? $name : $u->email;
                    })->filter()->values();
                }
            @endphp
            <details class="cabinet-sc-team-card"
                     data-sc-team-card
                     data-team-id="{{ $team->id }}"
                     data-search="{{ e($teamSearch) }}">
                <summary class="cabinet-sc-team-card__head">
                    <span class="cabinet-sc-team-card__chevron" aria-hidden="true"></span>
                    <div class="cabinet-sc-team-card__summary-main">
                        <strong class="cabinet-sc-team-card__name">{{ $team->title }}</strong>
                        <div class="cabinet-sc-team-card__meta">
                            <span>{{ $team->members_count }} {{ __('members') }}</span>
                            <span>·</span>
                            <span>{{ __('Used in :count projects', ['count' => (int) $team->projects_count]) }}</span>
                        </div>
                        <div class="cabinet-sc-team-card__preview">
                            @foreach(($teamRoleLabels ?? []) as $roleKey => $roleLabel)
                                @php $names = $previewByRole[$roleKey] ?? collect(); @endphp
                                @if($names->isNotEmpty())
                                    <span class="cabinet-sc-team-chip cabinet-sc-team-chip--{{ $roleKey === 'auditor' || $roleKey === 'participant' ? 'shared' : $roleKey }}">
                                        <span class="cabinet-sc-team-chip__role">{{ $roleLabel }}</span>
                                        <span class="cabinet-sc-team-chip__people">{{ $names->take(2)->implode(', ') }}@if($names->count() > 2) +{{ $names->count() - 2 }}@endif</span>
                                    </span>
                                @endif
                            @endforeach
                            @if($team->members_count === 0)
                                <span class="cabinet-sc-team-chip cabinet-sc-team-chip--empty">{{ __('No one yet') }}</span>
                            @endif
                        </div>
                    </div>
                    <form method="post"
                          action="{{ route('pages.seo-checklist.teams.delete', ['teamId' => $team->id]) }}"
                          class="cabinet-sc-team-card__summary-action"
                          data-sc-stop-toggle
                          data-cabinet-confirm="{{ __('Delete this team?') }}"
                          data-cabinet-confirm-title="Удалить команду"
                          data-cabinet-confirm-ok="Удалить"
                          data-cabinet-confirm-danger="1">
                        @csrf
                        @if($returnTo)<input type="hidden" name="return_to" value="{{ $returnTo }}">@endif
                        <button type="submit" class="btn btn-sm btn-outline-danger" @if($team->projects_count > 0) disabled aria-label="{{ __('Team is used by projects') }}" @endif>
                            {{ __('Delete') }}
                        </button>
                    </form>
                </summary>

                <div class="cabinet-sc-team-card__body">
                    <div class="cabinet-sc-team-card__block">
                        <div class="cabinet-sc-team-card__block-title">{{ __('Team settings') }}</div>
                        <form method="post" action="{{ route('pages.seo-checklist.teams.update', ['teamId' => $team->id]) }}" class="cabinet-sc-create__row">
                            @csrf
                            @if($returnTo)<input type="hidden" name="return_to" value="{{ $returnTo }}">@endif
                            <input type="text" name="title" class="form-control form-control-sm" required value="{{ $team->title }}" placeholder="{{ __('Team name') }}">
                            <button type="submit" class="btn btn-sm btn-outline-primary">{{ __('Save') }}</button>
                        </form>
                    </div>

                    <div class="cabinet-sc-team-card__block">
                        <div class="cabinet-sc-team-card__block-title">{{ __('Members by role') }}</div>
                        <div class="cabinet-sc-role-groups">
                            @foreach(($teamRoleLabels ?? []) as $roleKey => $roleLabel)
                                @php
                                    $roleMembers = $team->members->where('role', $roleKey);
                                @endphp
                                <div class="cabinet-sc-role-group cabinet-sc-role-group--{{ $roleKey === 'auditor' || $roleKey === 'participant' ? 'shared' : $roleKey }}">
                                    <div class="cabinet-sc-role-group__title">
                                        <span class="cabinet-sc-role cabinet-sc-role--{{ $roleKey === 'auditor' || $roleKey === 'participant' ? 'shared' : $roleKey }}">{{ $roleLabel }}</span>
                                        <span class="small text-secondary">{{ $roleMembers->count() }}</span>
                                    </div>
                                    <ul class="cabinet-sc-role-group__list">
                                        @forelse($roleMembers as $member)
                                            @php
                                                $u = $member->user;
                                                $name = $u ? (trim(($u->name ?? '') . ' ' . ($u->last_name ?? '')) ?: $u->email) : '—';
                                                $initial = $u ? mb_strtoupper(mb_substr(trim(($u->name ?? '') ?: ($u->email ?? '?')), 0, 1)) : '?';
                                            @endphp
                                            <li>
                                                <span class="cabinet-sc-member">
                                                    <span class="cabinet-sc-avatar cabinet-sc-avatar--sm" aria-hidden="true">{{ $initial }}</span>
                                                    <span class="cabinet-sc-member__text">
                                                        <span class="cabinet-sc-member__name">{{ $name }}</span>
                                                        <span class="cabinet-sc-member__email">{{ optional($u)->email }}</span>
                                                    </span>
                                                </span>
                                                <span class="cabinet-sc-role-group__actions">
                                                    <form method="post" action="{{ route('pages.seo-checklist.teams.members.update', ['teamId' => $team->id, 'memberId' => $member->id]) }}">
                                                        @csrf
                                                        @if($returnTo)<input type="hidden" name="return_to" value="{{ $returnTo }}">@endif
                                                        <select name="role" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="{{ __('Role') }}">
                                                            @foreach(($teamRoleLabels ?? []) as $rk => $rl)
                                                                <option value="{{ $rk }}" @if($member->role === $rk) selected @endif>{{ $rl }}</option>
                                                            @endforeach
                                                        </select>
                                                    </form>
                                                    <form method="post" action="{{ route('pages.seo-checklist.teams.members.delete', ['teamId' => $team->id, 'memberId' => $member->id]) }}"
                                                          data-cabinet-confirm="{{ __('Remove member?') }}"
                                                          data-cabinet-confirm-title="Удалить участника"
                                                          data-cabinet-confirm-ok="Удалить"
                                                          data-cabinet-confirm-danger="1">
                                                        @csrf
                                                        @if($returnTo)<input type="hidden" name="return_to" value="{{ $returnTo }}">@endif
                                                        <button type="submit" class="btn btn-sm btn-outline-danger cabinet-sc-icon-btn" title="{{ __('Remove member') }}">×</button>
                                                    </form>
                                                </span>
                                            </li>
                                        @empty
                                            <li class="cabinet-sc-role-group__empty">{{ __('No one yet') }}</li>
                                        @endforelse
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="cabinet-sc-team-card__add">
                        <div class="cabinet-sc-team-card__block-title">{{ __('Add member') }}</div>
                        <form method="post" action="{{ route('pages.seo-checklist.teams.members.store', ['teamId' => $team->id]) }}" class="cabinet-sc-create__row">
                            @csrf
                            @if($returnTo)<input type="hidden" name="return_to" value="{{ $returnTo }}">@endif
                            <select name="user_id" class="form-select form-select-sm">
                                <option value="">{{ __('From known users') }}…</option>
                                @foreach(($teamCandidates ?? []) as $cand)
                                    @php
                                        $candLabel = trim(($cand->name ?? '') . ' ' . ($cand->last_name ?? '')) ?: $cand->email;
                                    @endphp
                                    <option value="{{ $cand->id }}">{{ $candLabel }} · {{ $cand->email }}</option>
                                @endforeach
                            </select>
                            <input type="email" name="email" class="form-control form-control-sm" placeholder="{{ __('Invite by email') }}">
                            <select name="role" class="form-select form-select-sm">
                                @foreach(($teamRoleLabels ?? []) as $rk => $rl)
                                    <option value="{{ $rk }}" @if($rk === 'participant') selected @endif>{{ $rl }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('Add') }}</button>
                        </form>
                    </div>
                </div>
            </details>
        @endforeach
    </div>
    <p class="cabinet-sc-empty-filter small text-secondary d-none mb-3" data-sc-team-empty>{{ __('No teams match filters') }}</p>
@endif

@if($showChecklistAssign && isset($projects) && $projects->isNotEmpty())
    <div class="cabinet-sc-section-label">
        <span>{{ __('Assign team to project') }}</span>
        <span class="cabinet-sc-section-label__count">{{ $projects->count() }}</span>
    </div>
    <div class="cabinet-sc-panel cabinet-sc-panel--assign" data-sc-assign-panel>
        <div class="cabinet-sc-panel__head">
            <p class="small text-secondary mb-0">{{ __('SEO checklist assign team hint') }}</p>
            <div class="cabinet-sc-toolbar cabinet-sc-toolbar--compact">
                <input type="search"
                       class="form-control form-control-sm cabinet-sc-search"
                       placeholder="{{ __('Search projects') }}…"
                       data-sc-assign-project-search
                       autocomplete="off">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-sc-assign-expand>{{ __('Expand') }}</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-sc-assign-collapse>{{ __('Collapse') }}</button>
            </div>
        </div>
        <div class="cabinet-sc-assign-list" data-sc-assign-list>
            @foreach($projects as $project)
                @php
                    $assignSearch = mb_strtolower(trim($project->domain . ' ' . optional($project->team)->title));
                @endphp
                <form method="post"
                      action="{{ route('pages.seo-checklist.assign-team', ['id' => $project->id]) }}"
                      class="cabinet-sc-assign-row cabinet-sc-assign-row--team"
                      data-sc-assign-row
                      data-search="{{ e($assignSearch) }}">
                    @csrf
                    @if($returnTo)<input type="hidden" name="return_to" value="{{ $returnTo }}">@endif
                    <div class="cabinet-sc-assign-row__domain">
                        <a href="{{ route('pages.seo-checklist.show', ['id' => $project->id]) }}">{{ $project->domain }}</a>
                        @if($project->team)
                            <span class="cabinet-sc-role cabinet-sc-role--any">{{ $project->team->title }}</span>
                        @else
                            <span class="cabinet-sc-role cabinet-sc-role--shared">{{ __('No team') }}</span>
                        @endif
                    </div>
                    <label>
                        <span>{{ __('Team') }}</span>
                        <select name="team_id" class="form-select form-select-sm">
                            <option value="">{{ __('Optional') }}</option>
                            @foreach(($teams ?? []) as $team)
                                <option value="{{ $team->id }}" @if((int) $project->team_id === (int) $team->id) selected @endif>
                                    {{ $team->title }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('Save') }}</button>
                </form>
            @endforeach
        </div>
        <p class="cabinet-sc-empty-filter small text-secondary d-none mb-0 mt-2" data-sc-assign-empty>{{ __('No projects match filters') }}</p>
    </div>
@endif

@once
    @include('partials.cabinet-confirm-modal')
@endonce
