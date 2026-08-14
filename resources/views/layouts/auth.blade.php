<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="shortcut icon" href="{{ asset('img/favicon.svg') }}"/>
    @include('layouts.partials.document-title')
    @include('layouts.partials.lte4-head')
    @if(config('app.env') !== 'local')
        <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.css') }}">
        <link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.css') }}">
    @endif
    <link rel="stylesheet" href="{{ asset('css/cabinet-auth.css') }}">
    @yield('css')
    <script src="{{ asset('plugins/jquery/jquery.min.js') }}"></script>
</head>
<body class="login-page bg-body-secondary">
{{-- Счётчик и цели сразу: после register/verify flash не должен ждать конец страницы --}}
@include('layouts.partials.yandex-metrika')
@include('layouts.partials.yandex-metrika-goals')
@include('layouts.partials.top-mail-ru')
@yield('content')

@if(config('app.env') !== 'local')
    <script src="{{ asset('plugins/select2/js/select2.js') }}"></script>
@endif
@include('layouts.partials.lte4-scripts')
<script src="{{ asset('js/cabinet-jquery-modal-bridge.js') }}"></script>
<script src="{{ asset('js/cabinet-bs5-shim.js') }}"></script>
<script src="{{ asset('js/cabinet-select2-defaults.js') }}"></script>
@yield('js')
</body>
</html>
