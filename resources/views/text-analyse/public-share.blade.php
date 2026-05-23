@extends('layouts.public-module')

@section('title', __('Text Analyse'))

@section('css')
    <link rel="stylesheet" href="{{ asset('css/cabinet-text-analyzer.css') }}?v={{ @filemtime(public_path('css/cabinet-text-analyzer.css')) ?: time() }}">
@endsection

@section('content')
    @include('text-analyse.partials.public-register-cta')

    <div class="alert alert-info cabinet-ta-public-banner mb-3">
        <div class="fw-semibold mb-1">{{ __('Public project access') }}</div>
        <div class="small mb-0">
            {{ __('View-only access without registration. Link expires on') }}
            <strong>{{ $share->expires_at->format('d.m.Y H:i') }}</strong>.
            @if(!empty($shareMeta['source_label']))
                <span class="d-block mt-1 text-secondary">{{ __('Source') }}: {{ $shareMeta['source_label'] }}</span>
            @endif
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header py-2">
            <h1 class="card-title h5 mb-0">
                <i class="bi bi-file-text me-1 text-primary"></i>{{ __('Text Analyse') }}
                <span class="badge text-bg-secondary ms-1">v{{ $shareMeta['version'] ?? config('cabinet-text-analyzer.version', '1.0') }}</span>
            </h1>
        </div>
        <div class="card-body cabinet-text-analyzer-page p-3">
            @include('text-analyse.partials.results', [
                'response' => $response,
                'request' => $request,
                'isPublicView' => true,
            ])
        </div>
    </div>
@endsection

@section('js')
    <script src="{{ asset('plugins/chart.js/3.9.1/chart.js') }}"></script>
    @include('text-analyse.partials.scripts', [
        'response' => $response,
        'scrollToResults' => false,
        'isPublicView' => true,
    ])
@endsection
