@php
    $noteAuthId = (int) ($authId ?? 0);
    $noteIsOwn = (int) $note->user_id === $noteAuthId;
    $noteIsUnread = !empty($noteIsUnread);
    $noteEditable = !empty($noteEditable);
    $noteProjectId = (int) ($projectId ?? 0);
@endphp
<li class="cabinet-sc-note @if($noteIsUnread) is-unread @endif"
    data-sc-note
    @if($noteIsUnread) data-sc-note-unread="1" @endif
    data-note-id="{{ $note->id }}"
    data-note-own="{{ $noteIsOwn ? '1' : '0' }}"
    @if($noteEditable)
        data-note-update-url="{{ route('pages.seo-checklist.note.update', ['id' => $noteProjectId, 'noteId' => $note->id]) }}"
        data-note-delete-url="{{ route('pages.seo-checklist.note.delete', ['id' => $noteProjectId, 'noteId' => $note->id]) }}"
    @endif>
    @include('pages.partials.seo-checklist-note-avatar', ['user' => $note->user, 'name' => $note->authorLabel()])
    <div class="cabinet-sc-notes-list__main">
        <div class="cabinet-sc-notes-list__meta">
            <strong class="cabinet-sc-notes-list__author">{{ $note->authorLabel() }}</strong>
            <span class="text-secondary small">{{ $note->created_at->format('d.m.Y H:i') }}</span>
            @if($note->wasEdited() && $note->updated_at)
                <span class="cabinet-sc-note-edited">{{ __('Edited :date', ['date' => $note->updated_at->format('d.m.Y H:i')]) }}</span>
            @endif
            @if($noteIsUnread)
                <span class="cabinet-sc-notes-list__unread-badge">{{ __('Unread') }}</span>
            @endif
        </div>
        <div data-sc-note-view>
            @if(trim((string) $note->body) !== '')
                <div class="cabinet-sc-notes-list__body">{!! \App\Support\TextAutoLinker::format((string) $note->body) !!}</div>
            @endif
            @include('pages.partials.seo-checklist-note-attachments', ['note' => $note, 'projectId' => $noteProjectId])
            @include('pages.partials.seo-checklist-note-history', ['note' => $note])
        </div>
        @if($noteEditable)
            <div class="cabinet-sc-note-editor d-none" data-sc-note-editor>
                <textarea class="d-none" data-sc-note-raw>{{ $note->body }}</textarea>
                <textarea class="form-control form-control-sm" rows="3" data-sc-note-editor-body></textarea>
                <div class="cabinet-sc-note-editor__actions">
                    <button type="button" class="btn btn-sm btn-primary" data-sc-note-editor-save>{{ __('Save') }}</button>
                    <button type="button" class="btn btn-sm btn-light" data-sc-note-editor-cancel>{{ __('Cancel') }}</button>
                </div>
            </div>
        @endif
    </div>
    @if($noteEditable || !$noteIsOwn)
        <div class="cabinet-sc-notes-list__side" data-sc-note-side>
            @if($noteEditable)
                <button type="button"
                        class="cabinet-sc-plan__side-btn"
                        data-sc-note-edit
                        aria-label="{{ __('Edit note') }}">
                    <i class="bi bi-pencil" aria-hidden="true"></i>
                    <span class="cabinet-sc-plan__side-btn-label">{{ __('Edit') }}</span>
                </button>
                <button type="button"
                        class="cabinet-sc-plan__side-btn cabinet-sc-note-delete"
                        data-sc-note-delete
                        data-confirm-title="{{ __('Delete note') }}"
                        data-confirm-text="{{ __('Delete this note? The text stays in the activity log.') }}"
                        data-confirm-ok="{{ __('Delete') }}"
                        aria-label="{{ __('Delete note') }}">
                    <i class="bi bi-trash" aria-hidden="true"></i>
                    <span class="cabinet-sc-plan__side-btn-label">{{ __('Delete') }}</span>
                </button>
            @endif
            @if(!$noteIsOwn)
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
            @endif
        </div>
    @endif
</li>
