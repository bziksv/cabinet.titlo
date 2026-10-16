@php
    $noteAuthId = (int) ($authId ?? 0);
    $noteIsOwn = (int) $note->user_id === $noteAuthId;
    $noteEditable = !empty($noteEditable);
    $noteProjectId = (int) ($projectId ?? 0);
    $subAuthor = $note->authorLabel();
@endphp
<div class="cabinet-sc-subtask__comment cabinet-sc-note"
     data-sc-note
     data-note-id="{{ $note->id }}"
     data-note-own="{{ $noteIsOwn ? '1' : '0' }}"
     @if($noteEditable)
         data-note-update-url="{{ route('pages.seo-checklist.note.update', ['id' => $noteProjectId, 'noteId' => $note->id]) }}"
         data-note-delete-url="{{ route('pages.seo-checklist.note.delete', ['id' => $noteProjectId, 'noteId' => $note->id]) }}"
     @endif
     @if(!empty($hiddenExtra)) hidden data-sc-sub-comment-extra @endif>
    @include('pages.partials.seo-checklist-note-avatar', ['user' => $note->user, 'name' => $subAuthor])
    <div class="cabinet-sc-subtask__comment-main">
        <div class="cabinet-sc-subtask__comment-head">
            <strong>{{ $subAuthor }}</strong>
            <span class="cabinet-sc-subtask__comment-date">{{ $note->created_at->format('d.m H:i') }}</span>
            @if($note->wasEdited() && $note->updated_at)
                <span class="cabinet-sc-note-edited">{{ __('Edited :date', ['date' => $note->updated_at->format('d.m H:i')]) }}</span>
            @endif
            @if($noteEditable)
                <span class="cabinet-sc-note-inline-actions">
                    <button type="button" data-sc-note-edit>{{ __('Edit') }}</button>
                    <button type="button"
                            class="is-danger"
                            data-sc-note-delete
                            data-confirm-title="{{ __('Delete note') }}"
                            data-confirm-text="{{ __('Delete this note? The text stays in the activity log.') }}"
                            data-confirm-ok="{{ __('Delete') }}">{{ __('Delete') }}</button>
                </span>
            @endif
        </div>
        <div data-sc-note-view>
            @if(trim((string) $note->body) !== '')
                <div class="cabinet-sc-subtask__comment-body">{!! \App\Support\TextAutoLinker::format((string) $note->body) !!}</div>
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
</div>
