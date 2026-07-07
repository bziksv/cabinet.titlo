<span class="cabinet-mon-cell-clip">
    <a href="#"
       class="cabinet-mon-url-badge"
       @if($urls->count())
           data-bs-toggle="popover"
           data-bs-title="URL"
           data-bs-html="true"
           data-bs-content="{{ view('monitoring.partials.show.popover.urls', ['urls' => $urls, 'page' => $page ?? null])->render() }}"
           onclick="return false;"
       @endif>
        <i class="fas fa-link" aria-hidden="true"></i>
        <span class="{{ $textClass }}">{{ $urls->count() }}</span>
    </a>
</span>
