@php
    $subOverdue = $child->isOverdue();
    $subAssignee = $child->relationLoaded('assigneeUser') && $child->assigneeUser
        ? (trim(($child->assigneeUser->name ?? '') . ' ' . ($child->assigneeUser->last_name ?? '')) ?: $child->assigneeUser->email)
        : null;
    $subNotes = $child->relationLoaded('notes') ? $child->notes->sortByDesc('id')->values() : collect();
    $subProject = $project ?? null;
    $subEditable = $subProject && $subProject->status !== 'archived';
    $subAssigneeOptions = $subEditable
        ? app(\App\Services\SeoChecklist\SeoChecklistService::class)->projectAssigneeOptions($subProject)
        : [];
@endphp
<div class="cabinet-sc-subtask__meta"
     data-sc-sub-meta
     @if($subEditable)
         data-update-url="{{ route('pages.seo-checklist.item.update', ['id' => $subProject->id, 'itemId' => $child->id]) }}"
         data-note-url="{{ route('pages.seo-checklist.item.notes', ['id' => $subProject->id, 'itemId' => $child->id]) }}"
     @endif>
    @if($child->due_at || $subAssignee || $subEditable)
        <div class="cabinet-sc-subtask__chips">
            @if($child->due_at)
                <span class="cabinet-sc-plan__due @if($subOverdue) is-overdue @endif">
                    @if($subOverdue)
                        {{ __('Overdue') }} · {{ $child->due_at->format('d.m') }}
                    @else
                        {{ __('Due') }} {{ $child->due_at->format('d.m') }}
                    @endif
                </span>
            @endif
            @if($subAssignee)
                <span class="cabinet-sc-subtask__assignee">
                    <i class="bi bi-person" aria-hidden="true"></i>
                    {{ $subAssignee }}
                </span>
            @endif
            @if($subEditable)
                <button type="button"
                        class="cabinet-sc-subtask__meta-edit @if(!$child->due_at && !$subAssignee) is-empty @endif"
                        data-sc-sub-meta-edit
                        aria-label="{{ __('Deadline and assignee') }}">
                    <i class="bi bi-pencil" aria-hidden="true"></i>
                    @if(!$child->due_at && !$subAssignee)
                        <span>{{ __('Deadline and assignee') }}</span>
                    @endif
                </button>
                <button type="button"
                        class="cabinet-sc-subtask__meta-edit is-empty"
                        data-sc-sub-comment-toggle
                        aria-label="{{ __('Add comment') }}">
                    <i class="bi bi-chat-left-text" aria-hidden="true"></i>
                    <span>{{ __('Add comment') }}</span>
                </button>
            @endif
        </div>
    @endif
    @if($subEditable)
        <div class="cabinet-sc-subtask__meta-form" data-sc-sub-meta-form hidden>
            <label class="cabinet-sc-subtask-form__field">
                <span>{{ __('Checklist item deadline') }}</span>
                <input type="date"
                       class="form-control form-control-sm"
                       data-sc-sub-meta-due
                       value="{{ $child->due_at ? $child->due_at->format('Y-m-d') : '' }}">
            </label>
            <label class="cabinet-sc-subtask-form__field">
                <span>{{ __('Checklist item assignee') }}</span>
                <select class="form-select form-select-sm" data-sc-sub-meta-assignee>
                    <option value="">{{ __('Checklist item not assigned') }}</option>
                    @foreach($subAssigneeOptions as $opt)
                        <option value="{{ $opt['id'] }}" @if((int) $child->assignee_user_id === $opt['id']) selected @endif>{{ $opt['name'] }}</option>
                    @endforeach
                </select>
            </label>
            <div class="cabinet-sc-subtask__meta-actions">
                <button type="button" class="btn btn-sm btn-primary" data-sc-sub-meta-save>{{ __('Save') }}</button>
                <button type="button" class="btn btn-sm btn-light" data-sc-sub-meta-cancel>{{ __('Cancel') }}</button>
            </div>
        </div>
    @endif
    @if($subEditable)
        <div class="cabinet-sc-subtask__comment-form" data-sc-sub-comment-form hidden>
            <textarea class="form-control form-control-sm" rows="2" data-sc-sub-comment-body placeholder="{{ __('Add a note') }}…"></textarea>
            @include('pages.partials.seo-checklist-attach-input')
            <div class="cabinet-sc-subtask__meta-actions">
                <button type="button" class="btn btn-sm btn-primary" data-sc-sub-comment-save>{{ __('Save') }}</button>
                <button type="button" class="btn btn-sm btn-light" data-sc-sub-comment-cancel>{{ __('Cancel') }}</button>
            </div>
        </div>
    @endif
    @if($subNotes->isNotEmpty())
        @php $subVisible = 5; @endphp
        <div class="cabinet-sc-subtask__comments" data-sc-sub-comments>
            @foreach($subNotes as $subIdx => $subNote)
                @include('pages.partials.seo-checklist-note-sub', [
                    'note' => $subNote,
                    'projectId' => $child->project_id,
                    'authId' => (int) auth()->id(),
                    'noteEditable' => $subEditable,
                    'hiddenExtra' => $subIdx >= $subVisible,
                ])
            @endforeach
            @if($subNotes->count() > $subVisible)
                <button type="button"
                        class="cabinet-sc-subtask__comments-more"
                        data-sc-sub-comments-more
                        data-label-more="{{ __('Show :count more comments', ['count' => $subNotes->count() - $subVisible]) }}"
                        data-label-less="{{ __('Hide older comments') }}">
                    {{ __('Show :count more comments', ['count' => $subNotes->count() - $subVisible]) }}
                </button>
            @endif
        </div>
    @endif
</div>
