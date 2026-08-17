{{--
  Кликабельная посадочная / URL в таблице отчёта.
  @param string|null $value  Текст (путь или полный URL)
  @param string|null $domain Домен проекта (для путей вида /services)
  @param string|null $empty  Заглушка, по умолчанию —
--}}
@php
    $value = isset($value) ? trim((string) $value) : '';
    $empty = $empty ?? '—';
    $href = \App\SeoReports\SeoReportLandingUrl::href(
        $value !== '' ? $value : null,
        $domain ?? null
    );
@endphp
@if($href)
    <a class="cabinet-sr-url__link" href="{{ $href }}" target="_blank" rel="noopener noreferrer">{{ $value !== '' ? $value : $href }}</a>
@elseif($value !== '')
    {{ $value }}
@else
    {{ $empty }}
@endif
