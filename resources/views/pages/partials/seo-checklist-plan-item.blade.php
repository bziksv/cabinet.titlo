@php
    $project = $item->project;
    $isOver = method_exists($item, 'isOverdue') && $item->isOverdue();
    $roleLabel = $roleLabels[$item->role] ?? $item->role;
    $projectArchived = $project && $project->status === 'archived';
    $runningLog = $item->timeLogs->first();
    $timerRunning = (bool) $runningLog;
    $displaySeconds = (int) $item->time_spent_seconds + ($runningLog ? $runningLog->elapsedSeconds() : 0);
    $projectUrl = $project
        ? route('pages.seo-checklist.show', ['id' => $project->id]) . '#sc-item-' . $item->id
        : route('pages.seo-checklist');
    $canApprove = !empty($canApprove);
    $canManage = !empty($canManage);
    $authId = (int) auth()->id();
    $notesCount = $item->relationLoaded('notes') ? $item->notes->count() : 0;
    $unreadNoteIds = [];
    if ($item->relationLoaded('notes') && $notesCount > 0) {
        foreach ($item->notes as $note) {
            if ((int) $note->user_id === $authId) {
                continue;
            }
            $isRead = $note->relationLoaded('reads')
                ? $note->reads->where('user_id', $authId)->isNotEmpty()
                : true;
            if (!$isRead) {
                $unreadNoteIds[] = (int) $note->id;
            }
        }
    }
    $unreadNotesCount = count($unreadNoteIds);
    $helpText = trim((string) ($item->help ?? ''));
    $infoLinks = is_array($item->links_json) ? $item->links_json : [];
    $createdByName = $item->relationLoaded('createdByUser') && $item->createdByUser
        ? (trim(($item->createdByUser->name ?? '') . ' ' . ($item->createdByUser->last_name ?? '')) ?: $item->createdByUser->email)
        : null;
    $doneByName = $item->relationLoaded('doneByUser') && $item->doneByUser
        ? (trim(($item->doneByUser->name ?? '') . ' ' . ($item->doneByUser->last_name ?? '')) ?: $item->doneByUser->email)
        : null;
    $createdAtLabel = $item->created_at
        ? $item->created_at->format('d.m.Y') . "\xc2\xa0" . $item->created_at->format('H:i')
        : null;
    $createdLabel = $createdAtLabel
        ? (
            $createdByName
                ? __('Created by :name on :date', [
                    'name' => $createdByName,
                    'date' => $createdAtLabel,
                ])
                : __('Created on :date', ['date' => $createdAtLabel])
        )
        : null;
    $doneLabel = $item->done_at
        ? __('Completed by :name on :date', [
            'name' => $doneByName ?: '—',
            'date' => $item->done_at->format('d.m.Y') . "\xc2\xa0" . $item->done_at->format('H:i'),
        ])
        : null;
    $statusLabel = $item->status_audit_label ?? null;
    $repeatLabel = !empty($item->repeat_rule)
        ? \App\Support\SeoChecklistDefaultTemplate::repeatRuleLabel($item->repeat_rule)
        : null;
    $hasInfo = $helpText !== ''
        || $infoLinks !== []
        || $createdLabel
        || $doneLabel
        || $statusLabel
        || $repeatLabel;
@endphp
<li class="cabinet-sc-plan__item @if($isOver) is-overdue @endif @if($item->status === 'doing') is-doing @endif @if($item->status === 'review') is-review @endif @if($item->is_important) is-important @endif @if($timerRunning) is-timing @endif"
    data-sc-plan-item
    data-id="{{ $item->id }}"
    data-project-id="{{ $item->project_id }}"
    data-domain="{{ $project ? $project->domain : '' }}"
    data-status="{{ $item->status }}"
    data-stage-key="{{ $item->stage_key === 'connect' ? 'access' : $item->stage_key }}"
    data-important="{{ $item->is_important ? '1' : '0' }}"
    data-overdue="{{ $isOver ? '1' : '0' }}"
    data-due-soon="{{ (method_exists($item, 'isDueSoon') && $item->isDueSoon()) ? '1' : '0' }}"
    data-later="{{ (!$isOver && $item->due_at && !(method_exists($item, 'isDueSoon') && $item->isDueSoon()) && $item->due_at->gt(now()->addDays(7)->endOfDay())) ? '1' : '0' }}"
    data-can-approve="{{ $canApprove ? '1' : '0' }}"
    data-time-spent="{{ (int) $item->time_spent_seconds }}"
    data-timer-running="{{ $timerRunning ? '1' : '0' }}"
    data-timer-started-at="{{ $timerRunning && $runningLog->started_at ? $runningLog->started_at->toIso8601String() : '' }}"
    data-notes-count="{{ $notesCount }}"
    data-unread-notes-count="{{ $unreadNotesCount }}"
    data-unread-note-ids="{{ implode(',', $unreadNoteIds) }}">
    <div class="cabinet-sc-plan__shell">
        <div class="cabinet-sc-plan__body">
            <div class="cabinet-sc-plan__row">
                @if($project)
                    <a href="{{ $projectUrl }}" class="cabinet-sc-plan__domain">{{ $project->domain }}</a>
                @endif
                <span class="cabinet-sc-plan__task {{ $item->status === 'review' ? 'is-review-text' : '' }}">{{ $item->title }}</span>
                <div class="cabinet-sc-plan__meta">
                    @if($item->is_important)
                        <span class="cabinet-sc-plan__flag"
                              data-tip="{{ __('Important task hint') }}"
                              aria-label="{{ __('Important task hint') }}"
                              role="img"
                              tabindex="0">!</span>
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
                        <span class="cabinet-sc-plan__due @if($isOver) is-overdue @endif">
                            @if($isOver)
                                {{ __('Overdue') }} · {{ $item->due_at->format('d.m') }}
                            @else
                                {{ __('Due') }} {{ $item->due_at->format('d.m') }}
                            @endif
                        </span>
                    @endif
                    <span class="cabinet-sc-time @if($timerRunning) is-running @endif"
                          data-sc-time
                          aria-label="{{ __('Time spent') }}">
                        {{ \App\Services\SeoChecklist\SeoChecklistService::formatDuration($displaySeconds) }}
                    </span>
                    <span class="cabinet-sc-role cabinet-sc-role--{{ $item->role }}">{{ $roleLabel }}</span>
                    <label class="cabinet-sc-report-flag cabinet-sc-report-flag--meta"
                           data-tip="{{ __('Include in SEO reports') }}">
                        <input type="checkbox"
                               class="visually-hidden"
                               data-sc-include-report
                               value="1"
                               @if($item->include_in_report) checked @endif
                               @if($projectArchived) disabled @endif>
                        <i class="bi bi-file-earmark-bar-graph" aria-hidden="true"></i>
                        <span class="visually-hidden">{{ __('Include in SEO reports') }}</span>
                    </label>
                </div>
                <div class="cabinet-sc-plan__controls">
                    <div class="cabinet-sc-plan__actions">
                        @if(!$projectArchived)
                            <button type="button"
                                    class="btn btn-sm @if($timerRunning) btn-danger @else btn-outline-success @endif cabinet-sc-plan__timer"
                                    data-sc-timer
                                    data-tip="{{ $timerRunning ? __('Stop timer') : __('Start timer') }}">
                                {{ $timerRunning ? __('Timer stop') : __('Timer start') }}
                            </button>
                        @endif
                        @if(!$projectArchived)
                            <select class="form-select form-select-sm cabinet-sc-plan__status"
                                    data-sc-status
                                    aria-label="{{ __('Status') }}">
                                @foreach($statusLabels as $value => $label)
                                    @php
                                        // done/skip: только PM/аудитор; из «На проверку».
                                        // Опции всегда в DOM у canApprove (hidden), иначе после смены на review
                                        // без reload «Выполнено» не появится в селекте.
                                        $isClosedOpt = in_array($value, ['done', 'skip'], true);
                                        $omitClosed = $isClosedOpt && !$canApprove && $item->status !== $value;
                                        $softHideClosed = $isClosedOpt && $canApprove
                                            && $item->status !== $value
                                            && $item->status !== 'review';
                                    @endphp
                                    @if(!$omitClosed)
                                        <option value="{{ $value }}"
                                                @if($item->status === $value) selected @endif
                                                @if($softHideClosed) hidden disabled @endif>
                                            {{ $label }}
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                        @endif
                    </div>
                </div>
            </div>
            @if($hasInfo)
                <div class="cabinet-sc-plan__info d-none" data-sc-info>
                    @if($helpText !== '')
                        <p class="cabinet-sc-plan__info-help">{{ $helpText }}</p>
                    @endif
                    @if($infoLinks !== [])
                        <div class="cabinet-sc-plan__info-links">
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
                    <p class="cabinet-sc-task__audit cabinet-sc-plan__info-audit" data-sc-audit @if(!$createdLabel && !$doneLabel && !$statusLabel) hidden @endif>
                        @if($createdLabel)
                            <span data-sc-audit-created>{{ $createdLabel }}</span>
                        @else
                            <span data-sc-audit-created hidden></span>
                        @endif
                        @if($statusLabel)
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
            @if(!$projectArchived)
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
            <a href="{{ $projectUrl }}"
               class="cabinet-sc-plan__side-btn cabinet-sc-plan__open-side"
               aria-label="{{ __('Open in project') }}">
                <i class="bi bi-folder2-open" aria-hidden="true"></i>
                <span class="cabinet-sc-plan__side-btn-label">{{ __('To project') }}</span>
            </a>
        </div>
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
            @foreach(($item->relationLoaded('notes') ? $item->notes : collect()) as $note)
                @php
                    $noteIsOwn = (int) $note->user_id === $authId;
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
        @if(!$projectArchived)
            <div class="cabinet-sc-notes-form">
                <textarea class="form-control form-control-sm" rows="2" data-sc-note-body placeholder="{{ __('Add a note') }}…"></textarea>
                <button type="button" class="btn btn-sm btn-primary" data-sc-note-save>{{ __('Save') }}</button>
            </div>
        @endif
    </div>
    <div class="cabinet-sc-plan__block-msg" data-sc-status-block hidden role="alert"></div>
    @php
        $children = $item->relationLoaded('children') ? $item->children : collect();
        $openChildren = $children->filter(function ($c) {
            return !in_array($c->status, \App\SeoChecklist\SeoChecklistItem::CLOSED_STATUSES, true);
        })->count();
        $canAddSubs = !$projectArchived;
        $showSubsBlock = $children->isNotEmpty() || $canAddSubs;
    @endphp
    @if($showSubsBlock)
        <div class="cabinet-sc-plan__subs cabinet-sc-subtasks-block @if($children->isEmpty()) is-empty d-none @endif"
             data-sc-plan-subs>
            <div class="cabinet-sc-plan__subs-head cabinet-sc-subtasks-block__head @if($children->isEmpty()) d-none @endif" data-sc-plan-subs-head>
                <span class="cabinet-sc-subtasks-block__title">{{ __('Subtasks') }}</span>
                <span class="cabinet-sc-subtasks-block__count" data-sc-plan-subs-count>{{ $openChildren }}/{{ $children->count() }}</span>
            </div>
            <p class="cabinet-sc-plan__subs-hint @if($item->status !== 'review' || $openChildren < 1) d-none @endif"
               data-sc-subs-close-hint>
                {{ __('Close open checklist items first', ['count' => $openChildren]) }}
            </p>
            <ul class="cabinet-sc-plan__subs-list cabinet-sc-subtasks" data-sc-plan-subs-list @if($children->isEmpty()) hidden @endif>
                @foreach($children as $child)
                    @php
                        $childDone = in_array($child->status, \App\SeoChecklist\SeoChecklistItem::CLOSED_STATUSES, true);
                        $childRunningLog = $child->relationLoaded('timeLogs') ? $child->timeLogs->first() : null;
                        $childTimerRunning = (bool) $childRunningLog;
                        $childDisplaySeconds = (int) $child->time_spent_seconds
                            + ($childRunningLog ? $childRunningLog->elapsedSeconds() : 0);
                        $canCloseChild = $canApprove
                            || ((int) $child->created_by > 0 && (int) $child->created_by === $authId);
                        $childCreatedBy = $child->createdByUser
                            ? (trim(($child->createdByUser->name ?? '') . ' ' . ($child->createdByUser->last_name ?? '')) ?: $child->createdByUser->email)
                            : null;
                        $childCreatedLabel = ($childCreatedBy || $child->created_at)
                            ? __('Created by :name on :date', [
                                'name' => $childCreatedBy ?: '—',
                                'date' => $child->created_at
                                    ? $child->created_at->format('d.m.Y') . "\xc2\xa0" . $child->created_at->format('H:i')
                                    : '—',
                            ])
                            : null;
                        $childDoneBy = $child->doneByUser
                            ? (trim(($child->doneByUser->name ?? '') . ' ' . ($child->doneByUser->last_name ?? '')) ?: $child->doneByUser->email)
                            : null;
                        $childDoneLabel = $child->done_at
                            ? __('Completed by :name on :date', [
                                'name' => $childDoneBy ?: '—',
                                'date' => $child->done_at->format('d.m.Y') . "\xc2\xa0" . $child->done_at->format('H:i'),
                            ])
                            : null;
                        $childStatusLabel = $child->status_audit_label ?? null;
                    @endphp
                    <li class="cabinet-sc-subtask cabinet-sc-plan__sub @if($childTimerRunning) is-timing @endif @if($child->status === 'review') is-review @endif @if($childDone) is-done @endif"
                        data-sc-plan-sub
                        data-id="{{ $child->id }}"
                        data-project-id="{{ $item->project_id }}"
                        data-status="{{ $child->status }}"
                        data-can-close="{{ $canCloseChild ? '1' : '0' }}"
                        data-time-spent="{{ (int) $child->time_spent_seconds }}"
                        data-timer-running="{{ $childTimerRunning ? '1' : '0' }}"
                        data-timer-started-at="{{ $childTimerRunning && $childRunningLog->started_at ? $childRunningLog->started_at->toIso8601String() : '' }}">
                        @if(!$projectArchived)
                            <span class="cabinet-sc-sub-drag"
                                  data-sc-sub-drag
                                  draggable="true"
                                  aria-label="{{ __('Drag to reorder') }}">⋮⋮</span>
                        @endif
                        <label class="cabinet-sc-check cabinet-sc-check--sub">
                            <input type="checkbox"
                                   data-sc-plan-sub-done
                                   @if($childDone) checked @endif
                                   @if($projectArchived || (!$canCloseChild && !$childDone)) disabled @endif>
                        </label>
                        <div class="cabinet-sc-subtask__body">
                            <span class="cabinet-sc-subtask__title {{ $childDone ? 'is-done-text' : '' }} {{ $child->status === 'review' ? 'is-review-text' : '' }}"
                                  data-sc-title>{{ $child->title }}</span>
                        </div>
                        <div class="cabinet-sc-subtask__controls">
                            <span class="cabinet-sc-review-hint" data-sc-review-hint @if($child->status !== 'review') hidden @endif>{{ __('Waiting for review') }}</span>
                            <div class="cabinet-sc-subtask__time">
                                <span class="cabinet-sc-time cabinet-sc-time--sub @if($childTimerRunning) is-running @endif"
                                      data-sc-time
                                      aria-label="{{ __('Time spent') }}">
                                    {{ \App\Services\SeoChecklist\SeoChecklistService::formatDuration($childDisplaySeconds) }}
                                </span>
                                @if(!$projectArchived)
                                    <button type="button"
                                            class="btn btn-sm @if($childTimerRunning) btn-danger @else btn-outline-success @endif cabinet-sc-subtask__timer"
                                            data-sc-timer
                                            data-tip="{{ $childTimerRunning ? __('Stop timer') : __('Start timer') }}">
                                        {{ $childTimerRunning ? __('Timer stop') : __('Timer start') }}
                                    </button>
                                @endif
                            </div>
                            @if(!$projectArchived)
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
                                       @if($projectArchived) disabled @endif>
                                <i class="bi bi-file-earmark-bar-graph" aria-hidden="true"></i>
                                <span class="visually-hidden">{{ __('Include in SEO reports') }}</span>
                            </label>
                        </div>
                        <p class="cabinet-sc-task__audit cabinet-sc-task__audit--sub" data-sc-audit @if(!$childCreatedLabel && !$childDoneLabel && !$childStatusLabel) hidden @endif>
                            @if($childCreatedLabel)
                                <span data-sc-audit-created>{{ $childCreatedLabel }}</span>
                            @else
                                <span data-sc-audit-created hidden></span>
                            @endif
                            @if($childStatusLabel)
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
                <div class="cabinet-sc-subtask-form cabinet-sc-plan__sub-form d-none" data-sc-plan-sub-form>
                    <input type="text"
                           class="cabinet-sc-subtask-form__input"
                           data-sc-plan-subtask-title
                           placeholder="{{ __('Add subtask') }}…"
                           aria-label="{{ __('Add subtask') }}">
                    <label class="cabinet-sc-report-flag cabinet-sc-report-flag--form"
                           data-tip="{{ __('Include in SEO reports') }}">
                        <input type="checkbox" class="visually-hidden" data-sc-plan-subtask-include-report value="1">
                        <i class="bi bi-file-earmark-bar-graph" aria-hidden="true"></i>
                        <span class="visually-hidden">{{ __('Include in SEO reports') }}</span>
                    </label>
                    <button type="button" class="cabinet-sc-subtask-form__add" data-sc-plan-subtask-add>
                        <i class="bi bi-plus-lg" aria-hidden="true"></i>
                        {{ __('Add') }}
                    </button>
                </div>
            @endif
        </div>
    @endif
</li>
