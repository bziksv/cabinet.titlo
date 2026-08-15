@component('component.card', [
    'titleHtml' => cabinet_sc_module_title_html(),
    'documentTitle' => cabinet_sc_document_title($project->domain ?: ($project->title ?: __('Projects'))),
])
    @slot('css')
        <link rel="stylesheet" href="{{ asset('css/cabinet-seo-checklist.css') }}?v={{ @filemtime(public_path('css/cabinet-seo-checklist.css')) ?: time() }}">
    @endslot

    @php
        $pct = $project->progress_total > 0
            ? (int) round(100 * $project->progress_done / $project->progress_total)
            : 0;
        $owner = $project->ownerUser;
        $pm = $project->pmUser;
        $ownerLabel = $owner
            ? trim(($owner->name ?? '') . ' ' . ($owner->last_name ?? '')) ?: $owner->email
            : '—';
        $pmLabel = $pm
            ? trim(($pm->name ?? '') . ' ' . ($pm->last_name ?? '')) ?: $pm->email
            : '—';
        $canManage = $canManage ?? true;
        $accessKind = $accessKind ?? 'account';
    @endphp

    <div class="cabinet-sc-page cabinet-sc-plan-v2 cabinet-sc-show-v2"
         id="cabinetSeoChecklist"
         data-project-id="{{ $project->id }}"
         data-can-manage="{{ $canManage ? '1' : '0' }}"
         data-status-url-template="{{ url('/checklist/'.$project->id.'/items/__ID__/status') }}"
         data-note-url-template="{{ url('/checklist/'.$project->id.'/items/__ID__/notes') }}"
         data-subtask-url-template="{{ url('/checklist/'.$project->id.'/items/__ID__/subtasks') }}"
         data-subtask-reorder-url-template="{{ url('/checklist/'.$project->id.'/items/__ID__/subtasks/reorder') }}"
         data-update-url-template="{{ url('/checklist/'.$project->id.'/items/__ID__') }}"
         data-delete-url-template="{{ url('/checklist/'.$project->id.'/items/__ID__/delete') }}"
         data-timer-start-url-template="{{ url('/checklist/'.$project->id.'/items/__ID__/timer/start') }}"
         data-timer-stop-url-template="{{ url('/checklist/'.$project->id.'/items/__ID__/timer/stop') }}"
         data-time-url-template="{{ url('/checklist/'.$project->id.'/items/__ID__/time') }}"
         data-timer-stop-active-url="{{ route('pages.seo-checklist.timer.stop-active') }}"
         data-mark-read-url="{{ route('pages.seo-checklist.chronicle.read') }}"
         data-mark-unread-url="{{ route('pages.seo-checklist.chronicle.unread') }}"
         data-i18n-time-by-day="{{ e(__('Time by day')) }}"
         data-i18n-no-time-logged="{{ e(__('No time logged yet')) }}"
         data-csrf="{{ csrf_token() }}"
         data-my-roles="{{ implode(',', $myRoles) }}"
         data-can-approve="{{ !empty($canApproveReview) ? '1' : '0' }}"
         data-status-options="{{ json_encode($statusLabels ?? [], JSON_UNESCAPED_UNICODE) }}"
         data-i18n-comment-required="{{ e(__('Comment required for this status')) }}"
         data-i18n-choose-status="{{ e(__('Choose task status')) }}"
         data-i18n-send-review-first="{{ e(__('Send to review first')) }}"
         data-i18n-close-subs-first="{{ e(__('Close open checklist items first')) }}"
         data-i18n-only-pm-auditor="{{ e(__('Only PM or auditor can approve')) }}"
         data-i18n-only-creator-close="{{ e(__('Only creator, PM or auditor can close checklist item')) }}"
         data-i18n-delete-confirm="{{ e(__('Delete this task?')) }}"
         data-i18n-delete-sub-confirm="{{ e(__('Delete this checklist item?')) }}"
         data-i18n-add-description="{{ e(__('Add description')) }}"
         data-i18n-timer-start="{{ e(__('Start timer')) }}"
         data-i18n-timer-stop="{{ e(__('Stop timer')) }}"
         data-i18n-timer-start-short="{{ e(__('Timer start')) }}"
         data-i18n-timer-stop-short="{{ e(__('Timer stop')) }}"
         data-i18n-focus-banner="{{ e(__('Checklist item from chronicle')) }}"
         data-i18n-waiting-review="{{ e(__('Waiting for review')) }}"
         data-i18n-mark-read="{{ e(__('Mark as read')) }}"
         data-i18n-mark-unread="{{ e(__('Mark note unread')) }}"
         data-i18n-mark-unread-short="{{ e(__('Mark unread short')) }}"
         data-i18n-mark-all-notes-read="{{ e(__('Mark all notes read')) }}"
         data-i18n-unread="{{ e(__('Unread')) }}"
         data-i18n-include-report="{{ e(__('Include in SEO reports')) }}"
         data-i18n-include-report-hint="{{ e(__('Include in SEO reports hint')) }}">

        @include('pages.partials.seo-checklist-nav', [
            'scTab' => 'project',
            'scContextProject' => $project,
            'scMyTasksCount' => $myTasksCount ?? null,
            'scReviewCount' => $reviewCount ?? null,
            'scShowReviewTab' => $showReviewTab ?? false,
            'scUnreadNotesCount' => $unreadNotesCount ?? null,
            'scProjectsCount' => $projectsCount ?? null,
            'scTeamCount' => $teamCount ?? null,
            'scTemplatesCount' => $templatesCount ?? null,
        ])

        @if(session('success'))
            <div class="alert alert-success py-2 px-3 small">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger py-2 px-3 small">{{ session('error') }}</div>
        @endif

        <div class="cabinet-sc-plan-topbar">
            <div class="cabinet-sc-plan-topbar__main">
                <div>
                    <p class="cabinet-sc-plan-topbar__eyebrow">
                        <a href="{{ route('pages.seo-checklist') }}" class="cabinet-sc-plan-topbar__back">{{ __('Projects') }}</a>
                        · {{ __('SEO Checklist') }}
                    </p>
                    <h1 class="cabinet-sc-plan-topbar__title">
                        <a href="https://{{ $project->domain }}" target="_blank" rel="noopener noreferrer" class="cabinet-sc-domain-link">
                            {{ $project->title ?: $project->domain }}
                        </a>
                        @if($accessKind === 'pm')
                            <span class="cabinet-sc-role cabinet-sc-role--pm">{{ __('Shared as PM') }}</span>
                        @elseif($accessKind === 'owner')
                            <span class="cabinet-sc-role cabinet-sc-role--owner">{{ __('Shared as owner') }}</span>
                        @elseif($accessKind === 'auditor')
                            <span class="cabinet-sc-role cabinet-sc-role--shared">{{ __('Shared as auditor') }}</span>
                        @elseif($accessKind === 'participant')
                            <span class="cabinet-sc-role cabinet-sc-role--shared">{{ __('Shared as participant') }}</span>
                        @endif
                    </h1>
                    <p class="cabinet-sc-plan-topbar__meta">
                        <span data-sc-progress-label>{{ number_format((int) $project->progress_done, 0, '', ' ') }}/{{ number_format((int) $project->progress_total, 0, '', ' ') }}</span>
                        · <span data-sc-progress-pct>{{ $pct }}%</span>
                        @if($project->status === 'archived')
                            · <span class="cabinet-sc-plan-topbar__hot">{{ __('Archive') }}</span>
                        @endif
                        · {{ number_format(count($stages), 0, '', ' ') }} {{ __('Stages') }}
                    </p>
                    <p class="cabinet-sc-team-line small mb-0">
                        @if($project->team)
                            <span>{{ __('Team') }}: <strong>{{ $project->team->title }}</strong>
                                <span class="text-secondary">({{ number_format($project->team->members->count(), 0, '', ' ') }} {{ __('members') }})</span>
                            </span>
                        @else
                            <span>{{ __('SEO role owner') }}: <strong>{{ $ownerLabel }}</strong></span>
                            <span class="cabinet-sc-team-line__sep">·</span>
                            <span>{{ __('SEO role PM') }}: <strong>{{ $pmLabel }}</strong></span>
                            <span class="cabinet-sc-team-line__sep">·</span>
                            <span class="text-secondary">{{ __('No team') }}</span>
                        @endif
                    </p>
                    @if($project->team)
                        <div class="cabinet-sc-card__team cabinet-sc-show-team mt-2">
                            @foreach(($teamRoleLabels ?? \App\SeoChecklist\SeoChecklistTeam::roleLabels()) as $roleKey => $roleLabel)
                                @php
                                    $names = $project->team->members->where('role', $roleKey)->map(function ($member) {
                                        $u = $member->user;
                                        if (!$u) {
                                            return null;
                                        }
                                        $name = trim(($u->name ?? '') . ' ' . ($u->last_name ?? '')) ?: ($u->email ?? '');

                                        return $name !== '' ? $name : null;
                                    })->filter()->values();
                                @endphp
                                @if($names->isNotEmpty())
                                    <span class="cabinet-sc-team-chip cabinet-sc-team-chip--{{ in_array($roleKey, ['auditor', 'participant'], true) ? 'shared' : $roleKey }}">
                                        <span class="cabinet-sc-team-chip__role">{{ $roleLabel }}</span>
                                        <span class="cabinet-sc-team-chip__people">{{ $names->take(3)->implode(', ') }}@if($names->count() > 3) +{{ $names->count() - 3 }}@endif</span>
                                    </span>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="cabinet-sc-plan-topbar__actions">
                    @if($project->status !== 'archived' && $project->progress_done < $project->progress_total)
                        <button type="button" class="cabinet-sc-plan-chip-btn is-primary" data-sc-continue>
                            {{ __('Continue work') }}
                        </button>
                    @endif
                    <a href="{{ route('pages.seo-checklist.pdf', ['id' => $project->id]) }}" class="cabinet-sc-plan-chip-btn">PDF</a>
                    @if($canManage)
                        @if($project->status === 'archived')
                            <form method="post" action="{{ route('pages.seo-checklist.restore', ['id' => $project->id]) }}">
                                @csrf
                                <button type="submit" class="cabinet-sc-plan-chip-btn is-primary">{{ __('Restore') }}</button>
                            </form>
                        @else
                            <form method="post" action="{{ route('pages.seo-checklist.archive', ['id' => $project->id]) }}"
                                  onsubmit='return confirm(@json(__("Archive this SEO checklist?")));'>
                                @csrf
                                <button type="submit" class="cabinet-sc-plan-chip-btn">{{ __('To archive') }}</button>
                            </form>
                        @endif
                        <button type="button"
                                class="cabinet-sc-plan-chip-btn is-danger"
                                data-sc-delete-project
                                data-url="{{ route('pages.seo-checklist.delete', ['id' => $project->id]) }}"
                                data-domain="{{ $project->domain }}">
                            {{ __('Delete') }}
                        </button>
                    @endif
                </div>
            </div>
            <div class="cabinet-sc-progress cabinet-sc-progress--show" aria-hidden="true">
                <span data-sc-progress-bar style="width: {{ $pct }}%"></span>
            </div>
        </div>

        <div class="cabinet-sc-plan-layout">
            @if(count($stages) > 0)
                <div class="cabinet-sc-plan-rail-slot" data-sc-show-rail-slot>
                    <nav class="cabinet-sc-plan-rail" data-sc-show-rail aria-label="{{ __('Stages') }}">
                        <div class="cabinet-sc-plan-rail__head">{{ __('Stages') }}</div>
                        <ul class="cabinet-sc-plan-rail__list">
                            @foreach($stages as $stage)
                                @php
                                    $stagePctNav = $stage['total'] > 0 ? (int) round(100 * $stage['done'] / $stage['total']) : 0;
                                    $stageDone = $stage['total'] > 0 && $stage['done'] >= $stage['total'];
                                    $stageOpen = max(0, (int) $stage['total'] - (int) $stage['done']);
                                @endphp
                                <li @if($stageDone) class="is-complete" @endif>
                                    <div class="cabinet-sc-plan-rail__item">
                                        <a class="cabinet-sc-plan-rail__link @if($stageDone) is-complete @endif"
                                           href="#sc-stage-{{ $stage['key'] }}"
                                           data-sc-stage-jump="{{ $stage['key'] }}"
                                           data-sc-show-rail-link="{{ $stage['key'] }}"
                                           data-tip="{{ $stage['title'] }} · {{ number_format((int) $stage['done'], 0, '', ' ') }}/{{ number_format((int) $stage['total'], 0, '', ' ') }}">
                                            <span class="cabinet-sc-plan-rail__name">{{ $stage['title'] }}</span>
                                            <span class="cabinet-sc-plan-rail__count">{{ $stagePctNav }}%</span>
                                        </a>
                                        <div class="cabinet-sc-plan-rail__chips" role="group" aria-label="{{ __('Progress') }}">
                                            @if($stageOpen > 0)
                                                <span class="cabinet-sc-plan-rail__chip cabinet-sc-plan-rail__chip--doing"
                                                      data-tip="{{ __('Open tasks') }} — {{ number_format($stageOpen, 0, '', ' ') }}">
                                                    <i class="bi bi-circle" aria-hidden="true"></i>
                                                    <span>{{ number_format($stageOpen, 0, '', ' ') }}</span>
                                                </span>
                                            @endif
                                            @if((int) $stage['done'] > 0)
                                                <span class="cabinet-sc-plan-rail__chip cabinet-sc-plan-rail__chip--done"
                                                      data-tip="{{ __('Status done') }} — {{ number_format((int) $stage['done'], 0, '', ' ') }}">
                                                    <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                                                    <span>{{ number_format((int) $stage['done'], 0, '', ' ') }}</span>
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </nav>
                </div>
            @endif

            <div class="cabinet-sc-plan-workspace">
                <div class="cabinet-sc-plan-filters cabinet-sc-plan-filters--sticky cabinet-sc-show-toolbar" data-sc-show-filters>
                    <div class="cabinet-sc-plan-filters__bar cabinet-sc-show-toolbar__bar" data-tip="{{ __('Filters can be combined') }}">
                        <label class="cabinet-sc-show-search">
                            <span class="visually-hidden">{{ __('Search tasks') }}</span>
                            <i class="bi bi-search" aria-hidden="true"></i>
                            <input type="search"
                                   id="cabinetScShowSearch"
                                   class="form-control form-control-sm"
                                   data-sc-task-search
                                   placeholder="{{ __('Search tasks') }}…"
                                   autocomplete="off">
                        </label>
                        <div class="cabinet-sc-filters cabinet-sc-filters--status">
                            <button type="button" class="btn btn-sm btn-outline-secondary active" data-sc-filter="all" aria-pressed="true">{{ __('All') }}</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-sc-filter="open" aria-pressed="false">{{ __('Open tasks') }}</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-sc-filter="overdue" data-tip="{{ __('Overdue filter hint') }}" aria-pressed="false">{{ __('Overdue') }}</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-sc-filter="due-soon" data-tip="{{ __('Due soon filter hint') }}" aria-pressed="false">{{ __('Due soon') }}</button>
                            <button type="button"
                                    class="btn btn-sm btn-outline-secondary"
                                    data-sc-filter="no-review"
                                    data-tip="{{ __('Without review filter hint') }}"
                                    aria-pressed="false">{{ __('Without review') }}</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-sc-filter="important" aria-pressed="false">{{ __('Important') }}</button>
                            <button type="button"
                                    class="btn btn-sm btn-outline-secondary"
                                    data-sc-filter="unread-notes"
                                    data-tip="{{ __('Unread notes filter hint') }}"
                                    aria-pressed="false">{{ __('Unread notes') }}</button>
                            @if((int) ($projectReviewCount ?? 0) > 0)
                                <button type="button"
                                        class="btn btn-sm btn-outline-secondary"
                                        data-sc-filter="review"
                                        data-tip="{{ __('For review filter hint') }}"
                                        aria-pressed="false">
                                    {{ __('Status review') }}
                                    <span class="cabinet-sc-filters__badge">{{ number_format((int) $projectReviewCount, 0, '', ' ') }}</span>
                                </button>
                            @endif
                            <button type="button"
                                    class="btn btn-sm btn-outline-secondary"
                                    data-sc-filter="done"
                                    data-tip="{{ __('Completed tasks filter hint') }}"
                                    aria-pressed="false">{{ __('Completed tasks') }}</button>
                            @if(count($myRoles) > 0)
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-sc-filter="mine" aria-pressed="false">{{ __('My tasks') }}</button>
                            @endif
                        </div>
                    </div>
                    <div class="cabinet-sc-show-toolbar__roles">
                        <div class="cabinet-sc-filters cabinet-sc-filters--role">
                            @foreach($roleLabels as $roleKey => $roleLabel)
                                @php $rs = $roleStats[$roleKey] ?? ['done' => 0, 'total' => 0]; @endphp
                                <button type="button"
                                        class="btn btn-sm btn-outline-secondary"
                                        data-sc-filter="role:{{ $roleKey }}"
                                        aria-pressed="false">
                                    {{ $roleLabel }}
                                    @if(($rs['total'] ?? 0) > 0)
                                        <span class="cabinet-sc-filters__badge">{{ number_format((int) $rs['done'], 0, '', ' ') }}/{{ number_format((int) $rs['total'], 0, '', ' ') }}</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                        <div class="cabinet-sc-stage-controls">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-sc-stages-expand>{{ __('Expand all') }}</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-sc-stages-collapse>{{ __('Collapse all') }}</button>
                        </div>
                    </div>
                </div>

        @if($canManage)
            <div class="cabinet-sc-actions mb-3">
                <details class="cabinet-sc-action cabinet-sc-action--team">
                    <summary class="cabinet-sc-action__summary">
                        <span class="cabinet-sc-action__icon" aria-hidden="true"><i class="bi bi-people"></i></span>
                        <span class="cabinet-sc-action__text">
                            <strong>{{ __('SEO checklist team') }}</strong>
                            @if($project->team)
                                <em>{{ $project->team->title }} · {{ $project->team->members->count() }} {{ __('members') }}</em>
                            @else
                                <em>{{ __('Click to open team settings') }}</em>
                            @endif
                        </span>
                        <span class="cabinet-sc-action__cta">
                            <span class="cabinet-sc-action__cta-open">{{ __('Expand') }}</span>
                            <span class="cabinet-sc-action__cta-close">{{ __('Collapse') }}</span>
                            <i class="bi bi-chevron-down" aria-hidden="true"></i>
                        </span>
                    </summary>
                    <div class="cabinet-sc-action__body">
                        <form method="post" action="{{ route('pages.seo-checklist.assign-team', ['id' => $project->id]) }}" class="cabinet-sc-team__form">
                            @csrf
                            <input type="hidden" name="return_to" value="show">
                            <div class="cabinet-sc-team__fields">
                                <label>
                                    <span>{{ __('Team') }}</span>
                                    <select name="team_id" class="form-select form-select-sm">
                                        <option value="">{{ __('Optional') }} — {{ __('No team') }}</option>
                                        @foreach(($teams ?? []) as $team)
                                            <option value="{{ $team->id }}" @if((int) $project->team_id === (int) $team->id) selected @endif>
                                                {{ $team->title }} · {{ $team->members_count ?? $team->members->count() }}
                                            </option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>
                            <p class="cabinet-sc-team__hint small text-secondary mb-2">
                                {{ __('SEO checklist assign team hint') }}
                                <a href="{{ route('profile.index') }}#team">{{ __('Manage teams') }}</a>
                            </p>
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('Save') }}</button>
                        </form>
                        @if($project->team)
                            <div class="cabinet-sc-role-groups mt-2">
                                @foreach(($teamRoleLabels ?? []) as $roleKey => $roleLabel)
                                    @php $roleMembers = $project->team->members->where('role', $roleKey); @endphp
                                    @if($roleMembers->isNotEmpty())
                                        <div class="cabinet-sc-role-group">
                                            <div class="cabinet-sc-role-group__title">
                                                <span class="cabinet-sc-role cabinet-sc-role--{{ in_array($roleKey, ['owner', 'pm'], true) ? $roleKey : 'shared' }}">{{ $roleLabel }}</span>
                                            </div>
                                            <ul class="cabinet-sc-role-group__list">
                                                @foreach($roleMembers as $member)
                                                    @php
                                                        $u = $member->user;
                                                        $name = $u ? (trim(($u->name ?? '') . ' ' . ($u->last_name ?? '')) ?: $u->email) : '—';
                                                    @endphp
                                                    <li><span>{{ $name }}</span></li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                </details>

                @if($project->status !== 'archived')
                    <details class="cabinet-sc-action cabinet-sc-action--add">
                        <summary class="cabinet-sc-action__summary">
                            <span class="cabinet-sc-action__icon" aria-hidden="true"><i class="bi bi-plus-lg"></i></span>
                            <span class="cabinet-sc-action__text">
                                <strong>{{ __('Add task') }}</strong>
                                <em>{{ __('Click to add a new task') }}</em>
                            </span>
                            <span class="cabinet-sc-action__cta">
                                <span class="cabinet-sc-action__cta-open">{{ __('Expand') }}</span>
                                <span class="cabinet-sc-action__cta-close">{{ __('Collapse') }}</span>
                                <i class="bi bi-chevron-down" aria-hidden="true"></i>
                            </span>
                        </summary>
                        <div class="cabinet-sc-action__body">
                            <form method="post" action="{{ route('pages.seo-checklist.items.store', ['id' => $project->id]) }}" class="cabinet-sc-team__form">
                                @csrf
                                <div class="cabinet-sc-team__fields">
                                    <label>
                                        <span>{{ __('Task') }}</span>
                                        <input type="text" name="title" class="form-control form-control-sm" required placeholder="{{ __('New task') }}…">
                                    </label>
                                    <label>
                                        <span>{{ __('Stage') }}</span>
                                        <select name="stage_key" class="form-select form-select-sm" required>
                                            @foreach(($stagesMeta ?? []) as $sk => $sm)
                                                <option value="{{ $sk }}">{{ $sm['title'] }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label>
                                        <span>{{ __('Role') }}</span>
                                        <select name="role" class="form-select form-select-sm">
                                            @foreach($roleLabels as $rk => $rl)
                                                <option value="{{ $rk }}" @if($rk === 'owner') selected @endif>{{ $rl }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                </div>
                                <div class="cabinet-sc-tpl-task__row mb-2">
                                    <select name="repeat_rule" class="form-select form-select-sm">
                                        @include('pages.partials.seo-checklist-repeat-options', ['selected' => ''])
                                    </select>
                                    <label class="small mb-0">
                                        <input type="checkbox" name="is_important" value="1">
                                        {{ __('Important') }}
                                    </label>
                                    <label class="small mb-0" data-tip="{{ __('Include in SEO reports hint') }}">
                                        <input type="checkbox" name="include_in_report" value="1" checked>
                                        {{ __('Include in SEO reports') }}
                                    </label>
                                </div>
                                <button type="submit" class="btn btn-sm btn-primary">{{ __('Add task') }}</button>
                            </form>
                        </div>
                    </details>
                @endif
            </div>
        @endif

        <div class="cabinet-sc-stages">
            @foreach($stages as $stage)
                @php
                    $stagePct = $stage['total'] > 0 ? (int) round(100 * $stage['done'] / $stage['total']) : 0;
                @endphp
                <details class="cabinet-sc-stage"
                         data-sc-stage
                         data-sc-stage-key="{{ $stage['key'] }}"
                         data-complete="{{ ($stage['total'] > 0 && $stage['done'] >= $stage['total']) ? '1' : '0' }}"
                         id="sc-stage-{{ $stage['key'] }}"
                         open>
                    <summary class="cabinet-sc-stage__summary">
                        <span class="cabinet-sc-stage__title-wrap">
                            <span class="cabinet-sc-stage__title">{{ $stage['title'] }}</span>
                            <span class="cabinet-sc-stage__bar" aria-hidden="true"><span style="width: {{ $stagePct }}%"></span></span>
                        </span>
                        <span class="cabinet-sc-stage__meta">{{ $stage['done'] }}/{{ $stage['total'] }} · {{ $stagePct }}%</span>
                    </summary>
                    <ul class="cabinet-sc-tasks">
                        @foreach($stage['items'] as $item)
                            @php
                                $role = in_array($item->role, ['owner', 'pm', 'shared', 'any'], true) ? $item->role : 'any';
                                $itemOverdue = $item->isOverdue();
                                $itemDueSoon = $item->isDueSoon(2);
                                $dueTitle = null;
                                if ($item->due_at) {
                                    $dueDate = $item->due_at->format('d.m.Y');
                                    if ($itemOverdue) {
                                        $dueTitle = __('Overdue tooltip', ['date' => $dueDate]);
                                    } elseif ($itemDueSoon) {
                                        $dueTitle = __('Due soon tooltip', ['date' => $dueDate]);
                                    } else {
                                        $dueTitle = __('Due date') . ': ' . $dueDate;
                                    }
                                }
                                $runningLog = $item->timeLogs->first();
                                $timerRunning = (bool) $runningLog;
                                $displaySeconds = (int) $item->time_spent_seconds + ($runningLog ? $runningLog->elapsedSeconds() : 0);
                                $unreadNoteIds = [];
                                $viewerId = (int) ($timerUserId ?? auth()->id());
                                foreach ($item->notes as $note) {
                                    if ((int) $note->user_id === $viewerId) {
                                        continue;
                                    }
                                    $isRead = $note->relationLoaded('reads')
                                        ? $note->reads->where('user_id', $viewerId)->isNotEmpty()
                                        : true;
                                    if (!$isRead) {
                                        $unreadNoteIds[] = (int) $note->id;
                                    }
                                }
                                $unreadNotesCount = count($unreadNoteIds);
                            @endphp
                            <li id="sc-item-{{ $item->id }}"
                                class="cabinet-sc-task cabinet-sc-plan__item @if($itemOverdue) is-overdue @elseif($itemDueSoon) is-due-soon @endif @if($item->is_important) is-important @endif @if($timerRunning) is-timing @endif @if($item->status === 'review') is-review @endif @if($item->status === 'doing') is-doing @endif @if(in_array($item->status, \App\SeoChecklist\SeoChecklistItem::CLOSED_STATUSES, true)) is-done @endif"
                                data-sc-item
                                data-id="{{ $item->id }}"
                                data-status="{{ $item->status }}"
                                data-role="{{ $role }}"
                                data-important="{{ $item->is_important ? '1' : '0' }}"
                                data-overdue="{{ $itemOverdue ? '1' : '0' }}"
                                data-due-soon="{{ $itemDueSoon ? '1' : '0' }}"
                                data-allows-subtasks="{{ $item->allows_subtasks ? '1' : '0' }}"
                                data-can-approve="{{ !empty($canApproveReview) ? '1' : '0' }}"
                                data-time-spent="{{ (int) $item->time_spent_seconds }}"
                                data-timer-running="{{ $timerRunning ? '1' : '0' }}"
                                data-timer-started-at="{{ $timerRunning && $runningLog->started_at ? $runningLog->started_at->toIso8601String() : '' }}"
                                data-notes-count="{{ $item->notes->count() }}"
                                data-unread-notes-count="{{ $unreadNotesCount }}"
                                data-unread-note-ids="{{ implode(',', $unreadNoteIds) }}"
                                data-search="{{ e(mb_strtolower($item->title . ' ' . ($item->help ?: ''))) }}">
                                @php
                                    $createdByName = $item->createdByUser
                                        ? (trim(($item->createdByUser->name ?? '') . ' ' . ($item->createdByUser->last_name ?? '')) ?: $item->createdByUser->email)
                                        : null;
                                    $doneByName = $item->doneByUser
                                        ? (trim(($item->doneByUser->name ?? '') . ' ' . ($item->doneByUser->last_name ?? '')) ?: $item->doneByUser->email)
                                        : null;
                                    $createdLabel = ($createdByName || $item->created_at)
                                        ? __('Created by :name on :date', [
                                            'name' => $createdByName ?: '—',
                                            'date' => $item->created_at
                                                ? $item->created_at->format('d.m.Y') . "\xc2\xa0" . $item->created_at->format('H:i')
                                                : '—',
                                        ])
                                        : null;
                                    $doneLabel = $item->done_at
                                        ? __('Completed by :name on :date', [
                                            'name' => $doneByName ?: '—',
                                            'date' => $item->done_at->format('d.m.Y') . "\xc2\xa0" . $item->done_at->format('H:i'),
                                        ])
                                        : null;
                                    $statusLabel = $item->status_audit_label ?? null;
                                    $helpText = trim((string) ($item->help ?? ''));
                                    $infoLinks = is_array($item->links_json) ? $item->links_json : [];
                                    $repeatLabel = !empty($item->repeat_rule)
                                        ? \App\Support\SeoChecklistDefaultTemplate::repeatRuleLabel($item->repeat_rule)
                                        : null;
                                    $hasInfo = $helpText !== ''
                                        || $infoLinks !== []
                                        || $createdLabel
                                        || $doneLabel
                                        || $statusLabel
                                        || $repeatLabel
                                        || $project->status !== 'archived';
                                    $notesCount = $item->notes->count();
                                    $children = $item->children;
                                    $openChildren = $children->filter(function ($c) {
                                        return !in_array($c->status, \App\SeoChecklist\SeoChecklistItem::CLOSED_STATUSES, true);
                                    })->count();
                                    $canAddSubs = $project->status !== 'archived';
                                    $showSubsBlock = $children->isNotEmpty() || $canAddSubs;
                                @endphp
                                <div class="cabinet-sc-plan__shell">
                                    <div class="cabinet-sc-plan__body">
                                        <div class="cabinet-sc-plan__row cabinet-sc-task__main">
                                            <button type="button"
                                                    class="cabinet-sc-plan__task cabinet-sc-task__title {{ $item->is_important ? 'is-important' : '' }} {{ $item->status === 'review' ? 'is-review-text' : '' }} {{ in_array($item->status, \App\SeoChecklist\SeoChecklistItem::CLOSED_STATUSES, true) ? 'is-done-text' : '' }}"
                                                    data-sc-title
                                                    @if($project->status === 'archived') disabled @endif
                                                    data-tip="{{ __('Click to edit') }}">
                                                {{ $item->title }}
                                            </button>
                                            <div class="cabinet-sc-plan__meta">
                                                <label class="cabinet-sc-check cabinet-sc-check--plan" data-tip="{{ __('Status') }}">
                                                    <input type="checkbox"
                                                           data-sc-done
                                                           {{ in_array($item->status, \App\SeoChecklist\SeoChecklistItem::CLOSED_STATUSES, true) ? 'checked' : '' }}>
                                                </label>
                                                @if($item->is_important)
                                                    <span class="cabinet-sc-plan__flag"
                                                          data-tip="{{ __('Important task hint') }}"
                                                          aria-label="{{ __('Important task hint') }}"
                                                          role="img">!</span>
                                                @endif
                                                <span class="cabinet-sc-review-hint cabinet-sc-review-hint--meta" data-sc-review-hint @if($item->status !== 'review') hidden @endif>{{ __('Waiting for review') }}</span>
                                                @if($hasInfo)
                                                    <button type="button"
                                                            class="cabinet-sc-plan__info-btn"
                                                            data-sc-toggle-info
                                                            aria-expanded="false"
                                                            aria-label="{{ __('Task info') }}">i</button>
                                                @endif
                                                @if($item->due_at)
                                                    <span class="cabinet-sc-plan__due @if($itemOverdue) is-overdue @elseif($itemDueSoon) is-due-soon @endif"
                                                          data-tip="{{ $dueTitle }}">
                                                        @if($itemOverdue)
                                                            {{ __('Overdue') }}
                                                        @elseif($itemDueSoon)
                                                            {{ __('Due soon') }}
                                                        @else
                                                            {{ __('Due') }} {{ $item->due_at->format('d.m') }}
                                                        @endif
                                                    </span>
                                                @endif
                                                <button type="button"
                                                        class="cabinet-sc-time @if($timerRunning) is-running @endif"
                                                        data-sc-time
                                                        data-sc-toggle-time
                                                        data-tip="{{ __('Time by day') }}">
                                                    {{ \App\Services\SeoChecklist\SeoChecklistService::formatDuration($displaySeconds) }}
                                                </button>
                                                <span class="cabinet-sc-role cabinet-sc-role--{{ $role }}">{{ $roleLabels[$role] ?? $role }}</span>
                                                <label class="cabinet-sc-report-flag cabinet-sc-report-flag--meta"
                                                       data-tip="{{ __('Include in SEO reports') }}">
                                                    <input type="checkbox"
                                                           class="visually-hidden"
                                                           data-sc-include-report
                                                           value="1"
                                                           @if($item->include_in_report) checked @endif
                                                           @if($project->status === 'archived') disabled @endif>
                                                    <i class="bi bi-file-earmark-bar-graph" aria-hidden="true"></i>
                                                    <span class="visually-hidden">{{ __('Include in SEO reports') }}</span>
                                                </label>
                                            </div>
                                            <div class="cabinet-sc-plan__controls">
                                                <div class="cabinet-sc-plan__actions cabinet-sc-task__actions">
                                                    @if($project->status !== 'archived')
                                                        <button type="button"
                                                                class="btn btn-sm @if($timerRunning) btn-danger @else btn-outline-success @endif cabinet-sc-plan__timer"
                                                                data-sc-timer
                                                                data-tip="{{ $timerRunning ? __('Stop timer') : __('Start timer') }}">
                                                            {{ $timerRunning ? __('Timer stop') : __('Timer start') }}
                                                        </button>
                                                    @endif
                                                    <select class="form-select form-select-sm cabinet-sc-plan__status"
                                                            data-sc-status
                                                            aria-label="{{ __('Status') }}">
                                                        @foreach($statusLabels as $value => $label)
                                                            @php
                                                                $isClosedOpt = in_array($value, ['done', 'skip'], true);
                                                                $canApprove = !empty($canApproveReview);
                                                                $omitClosed = $isClosedOpt && !$canApprove && $item->status !== $value;
                                                                $softHideClosed = $isClosedOpt && $canApprove
                                                                    && $item->status !== $value
                                                                    && $item->status !== 'review';
                                                            @endphp
                                                            @if(!$omitClosed)
                                                                <option value="{{ $value }}"
                                                                        @if($item->status === $value) selected @endif
                                                                        @if($softHideClosed) hidden disabled @endif>{{ $label }}</option>
                                                            @endif
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        @if($hasInfo)
                                            <div class="cabinet-sc-plan__info d-none" data-sc-info>
                                                <p class="cabinet-sc-task__help cabinet-sc-plan__info-help @if($helpText === '') is-empty @endif"
                                                   data-sc-help
                                                   data-raw-value="{{ e($helpText) }}"
                                                   @if($project->status !== 'archived') data-tip="{{ __('Click to edit') }}" tabindex="0" role="button" @endif>
                                                    {{ $helpText !== '' ? $helpText : __('Add description') }}
                                                </p>
                                                @if($infoLinks !== [])
                                                    <div class="cabinet-sc-plan__info-links cabinet-sc-task__links">
                                                        @foreach($infoLinks as $link)
                                                            @php
                                                                $href = $link['url'] ?? null;
                                                                if (!$href && !empty($link['path'])) {
                                                                    $href = url($link['path']);
                                                                }
                                                            @endphp
                                                            @if($href)
                                                                <a href="{{ $href }}" target="_blank" rel="noopener noreferrer">
                                                                    {{ $link['label'] ?? __('Open') }}
                                                                </a>
                                                            @endif
                                                        @endforeach
                                                    </div>
                                                @endif
                                                @if($repeatLabel)
                                                    <span class="cabinet-sc-plan__info-repeat">↻ {{ $repeatLabel }}</span>
                                                @endif
                                                <p class="cabinet-sc-task__audit cabinet-sc-plan__info-audit" data-sc-audit @if(!$createdLabel && !$doneLabel && empty($statusLabel)) hidden @endif>
                                                    @if($createdLabel)
                                                        <span data-sc-audit-created>{{ $createdLabel }}</span>
                                                    @else
                                                        <span data-sc-audit-created hidden></span>
                                                    @endif
                                                    @if(!empty($statusLabel))
                                                        <span data-sc-audit-status>{{ $statusLabel }}</span>
                                                    @else
                                                        <span data-sc-audit-status hidden></span>
                                                    @endif
                                                    @if($doneLabel)
                                                        <span data-sc-audit-done>{{ $doneLabel }}</span>
                                                    @else
                                                        <span data-sc-audit-done hidden></span>
                                                    @endif
                                                </p>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="cabinet-sc-plan__side">
                                        <button type="button"
                                                class="cabinet-sc-plan__side-btn cabinet-sc-plan__notes-side @if($notesCount > 0) has-notes @endif @if($unreadNotesCount > 0) has-unread @endif"
                                                data-sc-toggle-notes
                                                aria-expanded="false"
                                                aria-label="{{ __('Notes') }}">
                                            <i class="bi bi-chat-left-text" aria-hidden="true"></i>
                                            <span class="cabinet-sc-plan__side-btn-label">{{ __('Notes') }}</span>
                                            <span class="cabinet-sc-plan__side-btn-count @if($notesCount < 1) is-empty @elseif($unreadNotesCount > 0) is-unread @else is-read @endif"
                                                  data-sc-notes-count>{{ $unreadNotesCount > 0 ? $unreadNotesCount : ($notesCount > 0 ? $notesCount : '') }}</span>
                                        </button>
                                        @if($canAddSubs)
                                            <button type="button"
                                                    class="cabinet-sc-plan__side-btn cabinet-sc-plan__sub-side"
                                                    data-sc-toggle-sub-form
                                                    aria-expanded="false"
                                                    aria-label="{{ __('Add subtask') }}"
                                                    data-tip="{{ __('Add subtask') }}">
                                                <i class="bi bi-plus-lg" aria-hidden="true"></i>
                                                <span class="cabinet-sc-plan__side-btn-label">{{ __('Checklist item short') }}</span>
                                            </button>
                                        @endif
                                        @if($canManage && $project->status !== 'archived')
                                            <button type="button"
                                                    class="cabinet-sc-plan__side-btn cabinet-sc-plan__delete-side"
                                                    data-sc-delete
                                                    aria-label="{{ __('Delete') }}"
                                                    data-tip="{{ __('Delete') }}">
                                                <i class="bi bi-trash" aria-hidden="true"></i>
                                                <span class="cabinet-sc-plan__side-btn-label">{{ __('Delete') }}</span>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                                <div class="cabinet-sc-task__time cabinet-sc-plan__time-panel d-none" data-sc-time-panel>
                                    <div class="cabinet-sc-time-panel__head">
                                        <strong>{{ __('Time by day') }}</strong>
                                        <span class="text-secondary small" data-sc-time-total></span>
                                    </div>
                                    <ul class="cabinet-sc-time-panel__list" data-sc-time-list></ul>
                                </div>
                                <div class="cabinet-sc-task__notes cabinet-sc-plan__notes d-none" data-sc-notes>
                                    <div class="cabinet-sc-plan__notes-unread @if($unreadNotesCount < 1) d-none @endif" data-sc-notes-unread-bar>
                                        <button type="button"
                                                class="cabinet-sc-notes-ack-all"
                                                data-sc-mark-notes-read
                                                aria-label="{{ __('Mark all notes read') }}">
                                            <i class="bi bi-flag" aria-hidden="true"></i>
                                            <span>{{ __('Mark all notes read') }}</span>
                                        </button>
                                    </div>
                                    <ul class="cabinet-sc-notes-list" data-sc-notes-list>
                                        @foreach($item->notes as $note)
                                            @php
                                                $noteIsOwn = (int) $note->user_id === $viewerId;
                                                $noteIsUnread = in_array((int) $note->id, $unreadNoteIds, true);
                                            @endphp
                                            <li class="@if($noteIsUnread) is-unread @endif"
                                                @if($noteIsUnread) data-sc-note-unread="1" @endif
                                                data-note-id="{{ $note->id }}"
                                                data-note-own="{{ $noteIsOwn ? '1' : '0' }}">
                                                <div class="cabinet-sc-notes-list__main">
                                                    <div class="cabinet-sc-notes-list__meta">
                                                        <strong class="cabinet-sc-notes-list__author">{{ $note->authorLabel() }}</strong>
                                                        <span class="text-secondary small">{{ $note->created_at->format('d.m.Y H:i') }}</span>
                                                        @if($noteIsUnread)
                                                            <span class="cabinet-sc-notes-list__unread-badge">{{ __('Unread') }}</span>
                                                        @endif
                                                    </div>
                                                    <div class="cabinet-sc-notes-list__body">{!! \App\Support\TextAutoLinker::format((string) $note->body) !!}</div>
                                                </div>
                                                @if(!$noteIsOwn)
                                                    <div class="cabinet-sc-notes-list__side" data-sc-note-side>
                                                        @if($noteIsUnread)
                                                            <button type="button"
                                                                    class="cabinet-sc-plan__side-btn"
                                                                    data-sc-mark-note-read
                                                                    data-note-id="{{ $note->id }}"
                                                                    aria-label="{{ __('Mark as read') }}">
                                                                <i class="bi bi-flag" aria-hidden="true"></i>
                                                                <span class="cabinet-sc-plan__side-btn-label">{{ __('Mark as read') }}</span>
                                                            </button>
                                                        @else
                                                            <button type="button"
                                                                    class="cabinet-sc-plan__side-btn"
                                                                    data-sc-mark-note-unread
                                                                    data-note-id="{{ $note->id }}"
                                                                    aria-label="{{ __('Mark note unread') }}">
                                                                <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                                                                <span class="cabinet-sc-plan__side-btn-label">{{ __('Mark unread short') }}</span>
                                                            </button>
                                                        @endif
                                                    </div>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                    @if($project->status !== 'archived')
                                        <div class="cabinet-sc-notes-form">
                                            <textarea class="form-control form-control-sm" rows="2" data-sc-note-body placeholder="{{ __('Add a note') }}…"></textarea>
                                            <button type="button" class="btn btn-sm btn-primary" data-sc-note-save>{{ __('Save') }}</button>
                                        </div>
                                    @endif
                                </div>
                                @if($showSubsBlock)
                                    <div class="cabinet-sc-plan__subs cabinet-sc-subtasks-block @if($children->isEmpty()) is-empty d-none @endif"
                                         data-sc-subtasks-block
                                         data-sc-plan-subs>
                                        <div class="cabinet-sc-plan__subs-head cabinet-sc-subtasks-block__head @if($children->isEmpty()) d-none @endif" data-sc-subtasks-head>
                                            <span class="cabinet-sc-subtasks-block__title">{{ __('Subtasks') }}</span>
                                            <span class="cabinet-sc-subtasks-block__count" data-sc-subtasks-count>{{ $openChildren }}/{{ $children->count() }}</span>
                                        </div>
                                        <p class="cabinet-sc-plan__subs-hint @if($item->status !== 'review' || $openChildren < 1) d-none @endif"
                                           data-sc-subs-close-hint>
                                            {{ __('Close open checklist items first', ['count' => $openChildren]) }}
                                        </p>
                                        <ul class="cabinet-sc-plan__subs-list cabinet-sc-subtasks" data-sc-subtasks @if($children->isEmpty()) hidden @endif>
                                            @foreach($children as $child)
                                                @php
                                                    $childCreatedBy = $child->createdByUser
                                                        ? (trim(($child->createdByUser->name ?? '') . ' ' . ($child->createdByUser->last_name ?? '')) ?: $child->createdByUser->email)
                                                        : null;
                                                    $childDoneBy = $child->doneByUser
                                                        ? (trim(($child->doneByUser->name ?? '') . ' ' . ($child->doneByUser->last_name ?? '')) ?: $child->doneByUser->email)
                                                        : null;
                                                    $childCreatedLabel = ($childCreatedBy || $child->created_at)
                                                        ? __('Created by :name on :date', [
                                                            'name' => $childCreatedBy ?: '—',
                                                            'date' => $child->created_at
                                                                ? $child->created_at->format('d.m.Y') . "\xc2\xa0" . $child->created_at->format('H:i')
                                                                : '—',
                                                        ])
                                                        : null;
                                                    $childDoneLabel = $child->done_at
                                                        ? __('Completed by :name on :date', [
                                                            'name' => $childDoneBy ?: '—',
                                                            'date' => $child->done_at->format('d.m.Y') . "\xc2\xa0" . $child->done_at->format('H:i'),
                                                        ])
                                                        : null;
                                                    $childStatusLabel = $child->status_audit_label ?? null;
                                                    $childRunningLog = $child->timeLogs->first();
                                                    $childTimerRunning = (bool) $childRunningLog;
                                                    $childDisplaySeconds = (int) $child->time_spent_seconds
                                                        + ($childRunningLog ? $childRunningLog->elapsedSeconds() : 0);
                                                    $canCloseChild = !empty($canApproveReview)
                                                        || ((int) $child->created_by > 0 && (int) $child->created_by === (int) auth()->id());
                                                    $childDone = in_array($child->status, \App\SeoChecklist\SeoChecklistItem::CLOSED_STATUSES, true);
                                                @endphp
                                                <li class="cabinet-sc-subtask cabinet-sc-plan__sub @if($childTimerRunning) is-timing @endif @if($child->status === 'review') is-review @endif @if($childDone) is-done @endif"
                                                    data-sc-subitem
                                                    data-id="{{ $child->id }}"
                                                    data-status="{{ $child->status }}"
                                                    data-created-by="{{ (int) $child->created_by }}"
                                                    data-can-close="{{ $canCloseChild ? '1' : '0' }}"
                                                    data-time-spent="{{ (int) $child->time_spent_seconds }}"
                                                    data-timer-running="{{ $childTimerRunning ? '1' : '0' }}"
                                                    data-timer-started-at="{{ $childTimerRunning && $childRunningLog->started_at ? $childRunningLog->started_at->toIso8601String() : '' }}">
                                                    @if($project->status !== 'archived')
                                                        <span class="cabinet-sc-sub-drag"
                                                              data-sc-sub-drag
                                                              draggable="true"
                                                              aria-label="{{ __('Drag to reorder') }}">⋮⋮</span>
                                                    @endif
                                                    <label class="cabinet-sc-check cabinet-sc-check--sub">
                                                        <input type="checkbox"
                                                               data-sc-done
                                                               {{ $childDone ? 'checked' : '' }}
                                                               @if(!$canCloseChild && !$childDone) disabled @endif>
                                                    </label>
                                                    <div class="cabinet-sc-subtask__body">
                                                        <button type="button"
                                                                class="cabinet-sc-subtask__title {{ $childDone ? 'is-done-text' : '' }} {{ $child->status === 'review' ? 'is-review-text' : '' }}"
                                                                data-sc-title
                                                                @if($project->status === 'archived') disabled @endif
                                                                data-tip="{{ __('Click to edit') }}">
                                                            {{ $child->title }}
                                                        </button>
                                                    </div>
                                                    <div class="cabinet-sc-subtask__controls">
                                                        <span class="cabinet-sc-review-hint" data-sc-review-hint @if($child->status !== 'review') hidden @endif>{{ __('Waiting for review') }}</span>
                                                        <div class="cabinet-sc-subtask__time">
                                                            <span class="cabinet-sc-time cabinet-sc-time--sub @if($childTimerRunning) is-running @endif"
                                                                  data-sc-time
                                                                  aria-label="{{ __('Time spent') }}">
                                                                {{ \App\Services\SeoChecklist\SeoChecklistService::formatDuration($childDisplaySeconds) }}
                                                            </span>
                                                            @if($project->status !== 'archived')
                                                                <button type="button"
                                                                        class="btn btn-sm @if($childTimerRunning) btn-danger @else btn-outline-success @endif cabinet-sc-subtask__timer"
                                                                        data-sc-timer
                                                                        data-tip="{{ $childTimerRunning ? __('Stop timer') : __('Start timer') }}">
                                                                    {{ $childTimerRunning ? __('Timer stop') : __('Timer start') }}
                                                                </button>
                                                            @endif
                                                        </div>
                                                        @if($project->status !== 'archived')
                                                            <select class="form-select form-select-sm cabinet-sc-subtask__status"
                                                                    data-sc-status
                                                                    aria-label="{{ __('Status') }}">
                                                                @foreach($statusLabels as $value => $label)
                                                                    @php
                                                                        $hideClosedChild = in_array($value, ['done', 'skip'], true)
                                                                            && !$canCloseChild
                                                                            && $child->status !== $value;
                                                                    @endphp
                                                                    @if(!$hideClosedChild)
                                                                        <option value="{{ $value }}"
                                                                                @if($child->status === $value) selected @endif>{{ $label }}</option>
                                                                    @endif
                                                                @endforeach
                                                            </select>
                                                        @else
                                                            <span class="cabinet-sc-subtask__status-label">{{ $statusLabels[$child->status] ?? $child->status }}</span>
                                                        @endif
                                                        <label class="cabinet-sc-report-flag cabinet-sc-report-flag--sub"
                                                               data-tip="{{ __('Include in SEO reports') }}">
                                                            <input type="checkbox"
                                                                   class="visually-hidden"
                                                                   data-sc-include-report
                                                                   value="1"
                                                                   @if($child->include_in_report) checked @endif
                                                                   @if($project->status === 'archived') disabled @endif>
                                                            <i class="bi bi-file-earmark-bar-graph" aria-hidden="true"></i>
                                                            <span class="visually-hidden">{{ __('Include in SEO reports') }}</span>
                                                        </label>
                                                        @if($project->status !== 'archived')
                                                            <button type="button" class="btn btn-link btn-sm text-danger p-0 cabinet-sc-subtask__delete" data-sc-delete data-tip="{{ __('Delete') }}" aria-label="{{ __('Delete') }}">×</button>
                                                        @endif
                                                    </div>
                                                    <p class="cabinet-sc-task__audit cabinet-sc-task__audit--sub" data-sc-audit @if(!$childCreatedLabel && !$childDoneLabel && empty($childStatusLabel)) hidden @endif>
                                                        @if($childCreatedLabel)
                                                            <span data-sc-audit-created>{{ $childCreatedLabel }}</span>
                                                        @else
                                                            <span data-sc-audit-created hidden></span>
                                                        @endif
                                                        @if(!empty($childStatusLabel))
                                                            <span data-sc-audit-status>{{ $childStatusLabel }}</span>
                                                        @else
                                                            <span data-sc-audit-status hidden></span>
                                                        @endif
                                                        @if($childDoneLabel)
                                                            <span data-sc-audit-done>{{ $childDoneLabel }}</span>
                                                        @else
                                                            <span data-sc-audit-done hidden></span>
                                                        @endif
                                                    </p>
                                                </li>
                                            @endforeach
                                        </ul>
                                        @if($canAddSubs)
                                            <div class="cabinet-sc-subtask-form cabinet-sc-plan__sub-form d-none" data-sc-sub-form>
                                                <input type="text"
                                                       class="cabinet-sc-subtask-form__input"
                                                       data-sc-subtask-title
                                                       placeholder="{{ __('Add subtask') }}…"
                                                       aria-label="{{ __('Add subtask') }}">
                                                <label class="cabinet-sc-report-flag cabinet-sc-report-flag--form"
                                                       data-tip="{{ __('Include in SEO reports') }}">
                                                    <input type="checkbox" class="visually-hidden" data-sc-subtask-include-report value="1">
                                                    <i class="bi bi-file-earmark-bar-graph" aria-hidden="true"></i>
                                                    <span class="visually-hidden">{{ __('Include in SEO reports') }}</span>
                                                </label>
                                                <button type="button" class="cabinet-sc-subtask-form__add" data-sc-subtask-add>
                                                    <i class="bi bi-plus-lg" aria-hidden="true"></i>
                                                    {{ __('Add') }}
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endforeach
        </div>
            </div>{{-- workspace --}}
        </div>{{-- layout --}}
    </div>

    @include('pages.partials.seo-checklist-delete-project-modal')
    @include('pages.partials.seo-checklist-delete-item-modal')
    @include('pages.partials.seo-checklist-status-modal')

    @slot('js')
        <script src="{{ asset('js/cabinet-seo-checklist-hub.js') }}?v={{ @filemtime(public_path('js/cabinet-seo-checklist-hub.js')) ?: time() }}"></script>
        <script src="{{ asset('js/cabinet-seo-checklist-status-modal.js') }}?v={{ @filemtime(public_path('js/cabinet-seo-checklist-status-modal.js')) ?: time() }}"></script>
        <script src="{{ asset('js/cabinet-seo-checklist.js') }}?v={{ @filemtime(public_path('js/cabinet-seo-checklist.js')) ?: time() }}"></script>
    @endslot
@endcomponent
