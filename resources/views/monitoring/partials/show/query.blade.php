<span class="cabinet-mon-query-cell">
<span class="query-string" title="{{ $key->query }}">
    {{ $key->query }}
</span>

@if($key->page)
    <a href="{{ $key->page }}"
       class="cabinet-mon-query-target"
       target="_blank"
       rel="noopener noreferrer"
       data-bs-toggle="popover"
       data-bs-title="Целевой URL"
       data-bs-html="true"
       data-bs-content="{{ view('monitoring.partials.show.popover.url', ['url' => $key->page])->render() }}"
       title="Целевой URL"
       aria-label="Целевой URL">
        <i class="fas fa-link" aria-hidden="true"></i>
    </a>
@endif
</span>
