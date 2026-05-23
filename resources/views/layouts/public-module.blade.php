@php
    $brand = \App\Support\TextAnalyzerPdfBranding::BRAND_NAME;
    $brandSite = \App\Support\TextAnalyzerPdfBranding::BRAND_SITE;
    $brandTagline = \App\Support\TextAnalyzerPdfBranding::BRAND_TAGLINE;
    $loginUrl = \App\Support\TextAnalyzerPdfBranding::loginUrl();
    $registerUrl = \App\Support\TextAnalyzerPdfBranding::registerUrl();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" href="{{ asset('img/favicon.svg') }}"/>
    <title>@yield('title') — {{ $brand }}</title>
    @include('layouts.partials.lte4-head')
    <link rel="stylesheet" href="{{ asset('css/cabinet-public-module.css') }}?v={{ @filemtime(public_path('css/cabinet-public-module.css')) ?: time() }}">
    @yield('css')
    <script src="{{ asset('plugins/jquery/jquery.min.js') }}"></script>
</head>
<body class="cabinet-public-module-page">
<header class="cabinet-public-module-header">
    <div class="container-fluid cabinet-public-module-header__inner">
        <a href="{{ $brandSite }}" class="cabinet-public-module-brand text-decoration-none" target="_blank" rel="noopener">
            <img src="{{ asset('img/logo-icon.svg') }}"
                 alt=""
                 class="cabinet-public-module-brand__icon"
                 width="44"
                 height="44">
            <span class="cabinet-public-module-brand__text">
                <span class="cabinet-public-module-brand__name">{{ $brand }}</span>
                <span class="cabinet-public-module-brand__tagline">{{ $brandTagline }}</span>
            </span>
        </a>
        <div class="cabinet-public-module-header__actions">
            <a href="{{ $loginUrl }}" class="btn btn-sm cabinet-public-module-btn-login">
                {{ __('Login') }}
            </a>
            <a href="{{ $registerUrl }}" class="btn btn-sm btn-primary cabinet-public-module-btn-register">
                {{ __('Register for free') }}
            </a>
        </div>
    </div>
</header>
<main class="container-fluid cabinet-public-module-main">
    @yield('content')
</main>
<footer class="cabinet-public-module-footer">
    <div class="container-fluid d-flex flex-wrap align-items-center justify-content-between gap-2">
        <span class="d-inline-flex align-items-center gap-2">
            <img src="{{ asset('img/logo-icon.svg') }}" alt="" width="20" height="20" class="cabinet-public-module-footer__icon">
            &copy; {{ date('Y') }}
            <a href="{{ $brandSite }}" target="_blank" rel="noopener">{{ $brand }}</a>
        </span>
        <a href="{{ $registerUrl }}" class="cabinet-public-module-footer__cta">{{ __('Register for free') }}</a>
    </div>
</footer>
@include('layouts.partials.lte4-scripts')
@yield('js')
</body>
</html>
