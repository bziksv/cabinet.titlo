@php
    $registerUrl = \App\HtmlEditorPublicShare::registerUrl();
    $loginUrl = \App\Support\TextAnalyzerPdfBranding::loginUrl();
@endphp
<aside class="cabinet-he-public-register mb-4" aria-label="{{ __('Register for free') }}">
    <div class="cabinet-he-public-register-card border rounded p-3 p-md-4">
        <div class="d-flex flex-column flex-md-row align-items-md-start gap-3">
            <img src="{{ asset('img/logo-icon.svg') }}" alt="" width="48" height="48" class="flex-shrink-0">
            <div class="flex-grow-1">
                <h2 class="h6 fw-semibold mb-1">{{ __('HTML editor public register title') }}</h2>
                <p class="small text-secondary mb-2">{{ __('HTML editor public register lead') }}</p>
                <ul class="small text-secondary mb-0 ps-3 cabinet-he-features-list">
                    <li>{{ __('HTML editor feature projects') }}</li>
                    <li>{{ __('HTML editor feature presets') }}</li>
                    <li>{{ __('HTML editor feature public share') }}</li>
                </ul>
            </div>
            <div class="d-flex flex-wrap gap-2 flex-shrink-0">
                <a href="{{ $registerUrl }}" class="btn btn-primary btn-sm">{{ __('Register for free') }}</a>
                <a href="{{ $loginUrl }}" class="btn btn-outline-secondary btn-sm">{{ __('Login') }}</a>
            </div>
        </div>
    </div>
</aside>
