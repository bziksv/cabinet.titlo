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
@endphp
<li class="cabinet-sc-plan__item @if($isOver) is-overdue @endif @if($item->status === 'doing') is-doing @endif @if($item->status === 'review') is-review @endif @if($item->is_important) is-important @endif @if($timerRunning) is-timing @endif"
    data-sc-plan-item
    data-id="{{ $item->id }}"
    data-project-id="{{ $item->project_id }}"
    data-domain="{{ $project ? $project->domain : '' }}"
    data-status="{{ $item->status }}"
    data-important="{{ $item->is_important ? '1' : '0' }}"
    data-overdue="{{ $isOver ? '1' : '0' }}"
    data-due-soon="{{ (method_exists($item, 'isDueSoon') && $item->isDueSoon()) ? '1' : '0' }}"
    data-can-approve="{{ $canApprove ? '1' : '0' }}"
    data-time-spent="{{ (int) $item->time_spent_seconds }}"
    data-timer-running="{{ $timerRunning ? '1' : '0' }}"
    data-timer-started-at="{{ $timerRunning && $runningLog->started_at ? $runningLog->started_at->toIso8601String() : '' }}">
    <div class="cabinet-sc-plan__row">
        <div class="cabinet-sc-plan__main">
            @if($project)
                <a href="{{ $projectUrl }}" class="cabinet-sc-plan__domain">{{ $project->domain }}</a>
            @endif
            <span class="cabinet-sc-plan__task {{ $item->status === 'review' ? 'is-review-text' : '' }}">{{ $item->title }}</span>
            <span class="cabinet-sc-review-hint" data-sc-review-hint @if($item->status !== 'review') hidden @endif>{{ __('Waiting for review') }}</span>
        </div>
        <div class="cabinet-sc-plan__controls">
            @if($item->is_important)
                <span class="cabinet-sc-plan__flag"
                      data-tip="{{ __('Important task hint') }}"
                      aria-label="{{ __('Important task hint') }}"
                      role="img"
                      tabindex="0">!</span>
            @endif
            @if(trim((string) ($item->help ?? '')) !== '')
                <span class="cabinet-sc-plan__help-tip"
                      data-tip="{{ e($item->help) }}"
                      aria-label="{{ __('Hint / help') }}"
                      role="img"
                      tabindex="0">?</span>
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
            @if(!$projectArchived)
                <button type="button"
                        class="btn btn-sm @if($timerRunning) btn-danger @else btn-outline-success @endif"
                        data-sc-timer
                        data-tip="{{ $timerRunning ? __('Stop timer') : __('Start timer') }}">
                    {{ $timerRunning ? __('Timer stop') : __('Timer start') }}
                </button>
            @endif
            <span class="cabinet-sc-role cabinet-sc-role--{{ $item->role }}">{{ $roleLabel }}</span>
            @if(!$projectArchived)
                <select class="form-select form-select-sm cabinet-sc-plan__status"
                        data-sc-status
                        aria-label="{{ __('Status') }}">
                    @foreach($statusLabels as $value => $label)
                        @php
                            $hideClosed = in_array($value, ['done', 'skip'], true)
                                && !$canApprove
                                && $item->status !== $value;
                        @endphp
                        @if(!$hideClosed)
                            <option value="{{ $value }}"
                                    @if($item->status === $value) selected @endif>
                                {{ $label }}
                            </option>
                        @endif
                    @endforeach
                </select>
            @endif
            <button type="button"
                    class="btn btn-sm btn-outline-secondary cabinet-sc-plan__notes-btn"
                    data-sc-toggle-notes
                    data-tip="{{ __('Notes') }}"
                    aria-label="{{ __('Notes') }}">
                <i class="bi bi-chat-left-text" aria-hidden="true"></i>
                @php
                    $notesCount = $item->relationLoaded('notes') ? $item->notes->count() : 0;
                @endphp
                @if($notesCount > 0)
                    <span class="cabinet-sc-plan__notes-count" data-sc-notes-count>{{ $notesCount }}</span>
                @endif
            </button>
            <a href="{{ $projectUrl }}"
               class="btn btn-sm cabinet-sc-plan__open"
               data-tip="{{ __('Open in project') }}">
                <i class="bi bi-folder2-open" aria-hidden="true"></i>
                {{ __('To project') }}
            </a>
        </div>
    </div>
    <div class="cabinet-sc-task__notes cabinet-sc-plan__notes d-none" data-sc-notes>
        <ul class="cabinet-sc-notes-list" data-sc-notes-list>
            @foreach(($item->relationLoaded('notes') ? $item->notes : collect()) as $note)
                <li>
                    <div class="cabinet-sc-notes-list__meta">
                        <strong class="cabinet-sc-notes-list__author">{{ $note->authorLabel() }}</strong>
                        <span class="text-secondary small">{{ $note->created_at->format('d.m.Y H:i') }}</span>
                    </div>
                    <div class="cabinet-sc-notes-list__body">{!! \App\Support\TextAutoLinker::format((string) $note->body) !!}</div>
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
    @php
        $children = $item->relationLoaded('children') ? $item->children : collect();
        $openChildren = $children->filter(function ($c) {
            return !in_array($c->status, \App\SeoChecklist\SeoChecklistItem::CLOSED_STATUSES, true);
        })->count();
    @endphp
    @if($children->isNotEmpty())
        <div class="cabinet-sc-plan__subs cabinet-sc-subtasks-block">
            <div class="cabinet-sc-plan__subs-head cabinet-sc-subtasks-block__head">
                <span class="cabinet-sc-subtasks-block__title">{{ __('Subtasks') }}</span>
                <span class="cabinet-sc-subtasks-block__count" data-sc-plan-subs-count>{{ $openChildren }}/{{ $children->count() }}</span>
            </div>
            <ul class="cabinet-sc-plan__subs-list cabinet-sc-subtasks">
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
                        <label class="cabinet-sc-check cabinet-sc-check--sub">
                            <input type="checkbox"
                                   data-sc-plan-sub-done
                                   @if($childDone) checked @endif
                                   @if($projectArchived || (!$canCloseChild && !$childDone)) disabled @endif>
                        </label>
                        <div class="cabinet-sc-subtask__body">
                            <span class="cabinet-sc-subtask__title {{ $childDone ? 'is-done-text' : '' }} {{ $child->status === 'review' ? 'is-review-text' : '' }}"
                                  data-sc-title>{{ $child->title }}</span>
                            <span class="cabinet-sc-review-hint" data-sc-review-hint @if($child->status !== 'review') hidden @endif>{{ __('Waiting for review') }}</span>
                        </div>
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
                        <p class="cabinet-sc-task__audit cabinet-sc-task__audit--sub" data-sc-audit @if(!$childCreatedLabel && !$childDoneLabel) hidden @endif>
                            @if($childCreatedLabel)
                                <span data-sc-audit-created>{{ $childCreatedLabel }}</span>
                            @else
                                <span data-sc-audit-created hidden></span>
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
        </div>
    @endif
</li>
