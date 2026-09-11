{{-- Ссылка URL + кнопка копирования. $url; опционально $label, $warn, $isIgn, $isFixed. --}}
@php
    $url = trim((string) ($url ?? ''));
    if (! isset($label) || $label === null || $label === '') {
        $label = $url;
    } else {
        $label = (string) $label;
    }
    $warn = $warn ?? null;
    $isIgn = !empty($isIgn);
    $isFixed = !empty($isFixed);
@endphp
@if($url !== '')
    <div class="cabinet-sa-url-block{{ !empty($warn) ? ' cabinet-sa-url-block--warn' : '' }}">
        <div class="cabinet-sa-url-line">
            @if(preg_match('#^https?://#i', $url))
                <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="cabinet-sa-url-break">{{ $label }}</a>
            @else
                <span class="cabinet-sa-url-break">{{ $label }}</span>
            @endif
            <button type="button"
                    class="cabinet-sa-url-copy"
                    data-sa-copy-url="{{ $url }}"
                    aria-label="Копировать URL">
                <i class="fa fa-copy" aria-hidden="true"></i>
                <span class="cabinet-sa-url-copy__tip" aria-hidden="true">Копировать</span>
            </button>
        </div>
        @if(!empty($warn))
            <div class="cabinet-sa-url-block__warn">{{ $warn }}</div>
        @endif
        @if($isIgn)
            <span class="badge text-bg-light border">игнор</span>
        @endif
        @if($isFixed)
            <span class="badge text-bg-success">исправлено</span>
        @endif
    </div>
@endif
