@extends('layouts.public-module')

@section('title', __('Domain information report'))

@section('css')
    <link rel="stylesheet" href="{{ asset('css/cabinet-domain-information.css') }}?v={{ @filemtime(public_path('css/cabinet-domain-information.css')) ?: time() }}">
@endsection

@section('content')
    <div class="alert alert-info cabinet-di-public-banner mb-3">
        <div class="fw-semibold mb-1">{{ __('Public project access') }}</div>
        <div class="small mb-0">
            @if($share->expires_at)
                {{ __('View-only access without registration. Link expires on') }}
                <strong>{{ $share->expires_at->format('d.m.Y H:i') }}</strong>.
            @else
                {{ __('View-only access without registration.') }}
                <strong>{{ __('Domain information share ttl unlimited') }}</strong>.
            @endif
            @if(!empty($shareMeta['source_label']))
                <span class="d-block mt-1 text-secondary">{{ __('Source') }}: {{ $shareMeta['source_label'] }}</span>
            @endif
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header py-2 d-flex flex-wrap align-items-center gap-2">
            <h1 class="card-title h5 mb-0">
                <i class="bi bi-calendar-event me-1 text-primary"></i>{{ __('Tracking the domain registration period') }}
            </h1>
            <span class="badge text-bg-secondary">v{{ $shareMeta['version'] ?? config('cabinet-domain-information.version', '1.0') }}</span>
            @if(!empty($shareMeta['generated_at']))
                <span class="small text-secondary ms-auto">{{ __('Generated') }}: {{ $shareMeta['generated_at'] }}</span>
            @endif
        </div>
        <div class="card-body cabinet-di-page p-3">
            <p class="mb-3"><strong>{{ $report['project']['domain'] ?? '—' }}</strong></p>
            @include('domain-information.partials.stats-report-body', [
                'report' => $report,
                'isPublicView' => true,
            ])
        </div>
    </div>

    <div class="text-center mb-4">
        <a href="{{ \App\DomainInformationPublicShare::registerUrl() }}" class="btn btn-primary">
            {{ __('Register for free') }}
        </a>
    </div>
@endsection
