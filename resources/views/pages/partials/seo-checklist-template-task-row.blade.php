@php
    $taskLinks = is_array($task->links_json) ? $task->links_json : [];
    $linkBlob = collect($taskLinks)->map(function ($link) {
        return trim(($link['label'] ?? '') . ' ' . ($link['path'] ?? ''));
    })->implode(' ');
    $roleLabel = $roleLabels[$task->role] ?? $task->role;
    $stageSearch = $stageSearch ?? '';
    $taskSearch = mb_strtolower(trim(implode(' ', array_filter([
        $task->title,
        $task->help,
        $task->code,
        $task->stage_key,
        $task->role,
        $roleLabel,
        $task->repeat_rule,
        $task->is_important ? 'важн important' : null,
        $task->repeat_rule ? 'повтор recurring monthly weekly' : null,
        $linkBlob,
        $stageSearch,
    ]))));
    $childCount = method_exists($task, 'relationLoaded') && $task->relationLoaded('children')
        ? $task->children->count()
        : (int) ($task->children ? $task->children->count() : 0);
    $isFirstInStage = !empty($isFirstInStage);
    $isLastInStage = !empty($isLastInStage);
    $taskIndex = (int) ($taskIndex ?? 0);
    $readOnly = !empty($readOnly);
@endphp
<li id="tpl-task-{{ $task->id }}"
    class="cabinet-sc-task cabinet-sc-task--tpl {{ $task->is_important ? 'is-important-card' : '' }} {{ $task->repeat_rule ? 'is-repeat-card' : '' }}"
    data-sc-tpl-task
    data-search="{{ e($taskSearch) }}"
    data-important="{{ $task->is_important ? '1' : '0' }}"
    data-repeat="{{ $task->repeat_rule ? '1' : '0' }}"
    data-include-report="{{ $task->include_in_report ? '1' : '0' }}">
    @if($readOnly)
        <div class="cabinet-sc-task__main">
            <span class="cabinet-sc-task__title {{ $task->is_important ? 'is-important' : '' }}">{{ $task->title }}</span>
            <span class="cabinet-sc-role cabinet-sc-role--{{ $task->role }}">{{ $roleLabel }}</span>
        </div>
        @if($task->help)
            <p class="cabinet-sc-task__help cabinet-sc-task__help--tpl">{{ $task->help }}</p>
        @endif
        @if(count($taskLinks) > 0)
            <div class="cabinet-sc-task__links cabinet-sc-task__links--tpl">
                @foreach($taskLinks as $link)
                    <a href="{{ $link['path'] ?? '#' }}" class="cabinet-sc-task__link">{{ $link['label'] ?? $link['path'] }}</a>
                @endforeach
            </div>
        @endif
    @else
        <div class="cabinet-sc-tpl-compact-row">
            <span class="cabinet-sc-tpl-drag"
                  data-sc-tpl-drag
                  draggable="true"
                  aria-label="{{ __('Drag to reorder') }}">⋮⋮</span>
            <button type="button"
                    class="cabinet-sc-tpl-compact"
                    data-sc-tpl-compact-toggle
                    aria-expanded="false">
                <span class="cabinet-sc-tpl-compact__index">{{ $taskIndex + 1 }}</span>
                <span class="cabinet-sc-tpl-compact__main">
                    <span class="cabinet-sc-tpl-compact__title" data-sc-tpl-compact-title>{{ $task->title }}</span>
                    @if($task->help)
                        <span class="cabinet-sc-tpl-compact__help">{{ \Illuminate\Support\Str::limit($task->help, 110) }}</span>
                    @endif
                </span>
                <span class="cabinet-sc-tpl-compact__meta">
                    <span class="cabinet-sc-tpl-pill cabinet-sc-tpl-pill--role">{{ $roleLabel }}</span>
                    @if($task->due_days_from_start)
                        <span class="cabinet-sc-tpl-pill">{{ __('Due day') }} {{ $task->due_days_from_start }}</span>
                    @endif
                    @if($task->repeat_rule)
                        <span class="cabinet-sc-tpl-pill">{{ __('Recurring') }}</span>
                    @endif
                    @if($task->is_important)
                        <span class="cabinet-sc-tpl-pill cabinet-sc-tpl-pill--hot">{{ __('Important') }}</span>
                    @endif
                    @if($task->include_in_report)
                        <span class="cabinet-sc-tpl-pill cabinet-sc-tpl-pill--report">{{ __('Include in SEO reports') }}</span>
                    @endif
                    @if($childCount > 0)
                        <span class="cabinet-sc-tpl-pill">{{ $childCount }}</span>
                    @endif
                </span>
                <span class="cabinet-sc-tpl-compact__chev" aria-hidden="true"></span>
            </button>
        </div>
        <div class="cabinet-sc-tpl-task-body" data-sc-tpl-task-body>
            <form method="post" action="{{ route('pages.seo-checklist.templates.task.update', ['templateId' => $template->id, 'taskId' => $task->id]) }}" class="cabinet-sc-tpl-task">
                @csrf
                <label class="cabinet-sc-tpl-task__field">
                    <span class="cabinet-sc-tpl-task__label">{{ __('Task') }}</span>
                    <input type="text" name="title" class="form-control form-control-sm" value="{{ $task->title }}" required data-sc-tpl-title-input>
                </label>
                <label class="cabinet-sc-tpl-task__field">
                    <span class="cabinet-sc-tpl-task__label">{{ __('Hint / help') }}</span>
                    <textarea name="help" class="form-control form-control-sm" rows="2" placeholder="{{ __('What to do and how to check') }}">{{ $task->help }}</textarea>
                </label>
                @if(count($taskLinks) > 0)
                    <div class="cabinet-sc-task__links cabinet-sc-task__links--tpl">
                        @foreach($taskLinks as $link)
                            <a href="{{ $link['path'] ?? '#' }}" class="cabinet-sc-task__link">{{ $link['label'] ?? $link['path'] }}</a>
                        @endforeach
                    </div>
                @endif
                <div class="cabinet-sc-tpl-task__footer">
                    <div class="cabinet-sc-tpl-task__row">
                        <select name="role" class="form-select form-select-sm" aria-label="{{ __('Role') }}">
                            @foreach($roleLabels as $rk => $rl)
                                <option value="{{ $rk }}" @if($task->role === $rk) selected @endif>{{ $rl }}</option>
                            @endforeach
                        </select>
                        <select name="repeat_rule" class="form-select form-select-sm" aria-label="{{ __('Recurring') }}">
                            @include('pages.partials.seo-checklist-repeat-options', ['selected' => $task->repeat_rule])
                        </select>
                        <label class="cabinet-sc-tpl-task__check small mb-0">
                            <span class="text-secondary">{{ __('Due day') }}</span>
                            <input type="number"
                                   name="due_days_from_start"
                                   class="form-control form-control-sm cabinet-sc-due-input"
                                   min="1"
                                   max="365"
                                   placeholder="—"
                                   value="{{ $task->due_days_from_start }}">
                        </label>
                        <label class="cabinet-sc-tpl-task__check small mb-0">
                            <input type="checkbox" name="is_important" value="1" @if($task->is_important) checked @endif>
                            {{ __('Important') }}
                        </label>
                    </div>
                    <div class="cabinet-sc-tpl-task__actions">
                        <label class="cabinet-sc-tpl-task__check small mb-0" data-tip="{{ __('Include in SEO reports hint') }}">
                            <input type="checkbox" name="include_in_report" value="1" @if($task->include_in_report) checked @endif>
                            {{ __('Include in SEO reports') }}
                        </label>
                        <button type="submit" class="btn btn-sm btn-primary">{{ __('Save') }}</button>
                    </div>
                </div>
            </form>
            <div class="cabinet-sc-tpl-subtasks" data-sc-tpl-subtasks>
                <div class="cabinet-sc-tpl-subtasks__head">{{ __('Subtasks') }}</div>
                <ul class="cabinet-sc-tpl-subtasks__list" data-sc-tpl-subtasks-list>
                    @foreach(($task->children ?? []) as $child)
                        <li data-sc-tpl-subtask
                            data-id="{{ $child->id }}"
                            data-update-url="{{ route('pages.seo-checklist.templates.task.update', ['templateId' => $template->id, 'taskId' => $child->id]) }}">
                            <span class="cabinet-sc-tpl-subtasks__title">{{ $child->title }}</span>
                            <label class="cabinet-sc-tpl-subtasks__report small mb-0"
                                   data-tip="{{ __('Include in SEO reports hint') }}">
                                <input type="checkbox"
                                       data-sc-tpl-include-report
                                       value="1"
                                       @if($child->include_in_report) checked @endif>
                                <span>{{ __('Include in SEO reports') }}</span>
                            </label>
                            <button type="button"
                                    class="cabinet-sc-tpl-subtasks__remove"
                                    data-sc-tpl-subtask-delete
                                    data-url="{{ route('pages.seo-checklist.templates.task.delete', ['templateId' => $template->id, 'taskId' => $child->id]) }}"
                                    aria-label="{{ __('Delete') }}">×</button>
                        </li>
                    @endforeach
                </ul>
                <div class="cabinet-sc-tpl-subtasks__form">
                    <input type="text"
                           class="cabinet-sc-tpl-subtasks__input"
                           data-sc-tpl-subtask-title
                           placeholder="{{ __('New subtask') }}…">
                    <label class="cabinet-sc-tpl-subtasks__report small mb-0"
                           data-tip="{{ __('Include in SEO reports hint') }}">
                        <input type="checkbox" data-sc-tpl-subtask-include-report value="1" checked>
                        <span>{{ __('Include in SEO reports') }}</span>
                    </label>
                    <button type="button"
                            class="cabinet-sc-tpl-subtasks__add"
                            data-sc-tpl-subtask-add
                            data-url="{{ route('pages.seo-checklist.templates.task.subtasks', ['templateId' => $template->id, 'taskId' => $task->id]) }}">
                        + {{ __('Add') }}
                    </button>
                </div>
            </div>
            <div class="cabinet-sc-tpl-task__toolbar">
                <div class="cabinet-sc-tpl-task__order">
                    <span class="cabinet-sc-tpl-drag cabinet-sc-tpl-drag--toolbar"
                          data-sc-tpl-drag
                          draggable="true"
                          aria-label="{{ __('Drag to reorder') }}">⋮⋮</span>
                    <button type="button"
                            class="btn btn-sm btn-outline-secondary"
                            data-sc-tpl-move="up"
                            @if($isFirstInStage) disabled @endif
                            aria-label="{{ __('Move up') }}">↑</button>
                    <button type="button"
                            class="btn btn-sm btn-outline-secondary"
                            data-sc-tpl-move="down"
                            @if($isLastInStage) disabled @endif
                            aria-label="{{ __('Move down') }}">↓</button>
                </div>
                <form method="post" action="{{ route('pages.seo-checklist.templates.task.delete', ['templateId' => $template->id, 'taskId' => $task->id]) }}"
                      class="cabinet-sc-tpl-task__delete"
                      onsubmit='return confirm(@json(__("Delete this task?")));'>
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Delete task') }}</button>
                </form>
            </div>
        </div>
    @endif
</li>
