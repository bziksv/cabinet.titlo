@php $html = asset('html'); @endphp
<link rel="stylesheet" href="{{ $html }}/npm/@fontsource/source-sans-3@5.0.12/index.css" crossorigin="anonymous" media="print" onload="this.media='all'">
<link rel="stylesheet" href="{{ $html }}/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css" crossorigin="anonymous">
<link rel="stylesheet" href="{{ $html }}/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" crossorigin="anonymous">
<link rel="stylesheet" href="{{ asset('plugins/fontawesome-free/css/all.min.css') }}">
<link rel="stylesheet" href="{{ $html }}/css/adminlte.min.css">
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
<link rel="stylesheet" href="{{ asset('css/cabinet-bs4-compat.css') }}">
<link rel="stylesheet" href="{{ asset('css/cabinet-select2-bs5.css') }}">
<link rel="stylesheet" href="{{ asset('css/cabinet-tempusdominus-bs5.css') }}">
<link rel="stylesheet" href="{{ asset('css/cabinet-modals-bs5.css') }}">
@php $customCssVer = @filemtime(public_path('css/custom.css')) ?: time(); @endphp
<link rel="stylesheet" href="{{ asset('css/custom.css') }}?v={{ $customCssVer }}">
