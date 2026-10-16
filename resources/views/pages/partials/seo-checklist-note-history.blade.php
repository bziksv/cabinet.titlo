@php
    $historyRevisions = $note->relationLoaded('revisions') ? $note->revisions : $note->revisions()->with('user')->get();
@endphp
@if($historyRevisions->isNotEmpty())
    <div class="cabinet-sc-note-history">
        <button type="button" class="cabinet-sc-note-history__toggle" data-sc-note-history-toggle aria-expanded="false">
            {{ __('Previous note text') }}
            <span class="cabinet-sc-note-history__count">{{ $historyRevisions->count() }}</span>
        </button>
        <div class="cabinet-sc-note-history__list d-none" data-sc-note-history>
            @foreach($historyRevisions as $revision)
                <div class="cabinet-sc-note-history__item">
                    <div class="cabinet-sc-note-history__meta">
                        {{ __('Was before :date', ['date' => optional($revision->created_at)->format('d.m.Y H:i') ?: '—']) }}
                        · {{ $revision->authorLabel() }}
                    </div>
                    <div class="cabinet-sc-note-history__body">{!! \App\Support\TextAutoLinker::format((string) $revision->body) !!}</div>
                </div>
            @endforeach
        </div>
    </div>
@endif
