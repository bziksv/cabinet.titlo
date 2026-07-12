@php
    $marketingBase = 'https://titlo.ru';
    $legalLinks = [
        ['url' => $marketingBase . '/legal/doc/privacy-policy/', 'label' => 'Политика обработки персональных данных'],
        ['url' => $marketingBase . '/legal/doc/cookies-policy/', 'label' => 'Политика использования cookie-файлов'],
        ['url' => $marketingBase . '/legal/doc/recommendation-rules/', 'label' => 'Правила применения рекомендательных технологий'],
    ];
@endphp
<nav class="cabinet-footer-legal" aria-label="Юридические документы">
    @foreach($legalLinks as $link)
        <a href="{{ $link['url'] }}" target="_blank" rel="noopener">{{ $link['label'] }}</a>
    @endforeach
</nav>
