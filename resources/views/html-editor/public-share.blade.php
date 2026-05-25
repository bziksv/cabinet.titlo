@extends('layouts.public-module')

@section('title', __('HTML editor'))

@section('css')
    <link rel="stylesheet" href="{{ asset('css/cabinet-html-editor.css') }}?v={{ @filemtime(public_path('css/cabinet-html-editor.css')) ?: time() }}">
@endsection

@section('content')
    @include('html-editor.partials.public-register-cta')

    <div class="alert alert-info mb-3">
        <div class="fw-semibold mb-1">{{ __('Public HTML text view') }}</div>
        <div class="small mb-0">
            {{ __('View-only access without registration. Link expires on') }}
            <strong>{{ $share->expires_at->format('d.m.Y H:i') }}</strong>.
            @if(!empty($shareMeta['project_name']))
                <span class="d-block mt-1 text-secondary">{{ __('Project') }}: {{ $shareMeta['project_name'] }}</span>
            @endif
            @if(!empty($shareMeta['text_excerpt']))
                <span class="d-block text-secondary">{{ __('Text preview') }}: {{ $shareMeta['text_excerpt'] }}</span>
            @endif
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h1 class="card-title h5 mb-0">
                <i class="bi bi-code-square me-1 text-primary" aria-hidden="true"></i>{{ __('HTML editor') }}
                @if(!empty($shareMeta['version']))
                    <span class="badge text-bg-secondary ms-1">v{{ $shareMeta['version'] }}</span>
                @endif
            </h1>
        </div>
        <div class="card-body cabinet-html-editor-page">
            <div class="row g-3">
                <div class="col-lg-6">
                    <div class="cabinet-he-pane">
                        <div class="cabinet-he-pane-head">{{ __('Visual preview') }}</div>
                        <div class="cabinet-he-pane-body cabinet-he-public-preview">
                            {!! $html !!}
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="cabinet-he-pane">
                        <div class="cabinet-he-pane-head">{{ __('HTML code') }}</div>
                        <div class="cabinet-he-pane-body">
                            <textarea class="form-control font-monospace cabinet-he-html-source" rows="18" readonly>{{ $html }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
