<span class="cabinet-mon-query-cell">
<span class="query-string">
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
       title="Целевой URL">
        <i class="fas fa-link" aria-hidden="true"></i>
        <span class="visually-hidden">Целевой URL</span>
    </a>
@endif
</span>
