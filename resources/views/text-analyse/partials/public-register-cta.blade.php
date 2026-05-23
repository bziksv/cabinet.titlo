@php
    $registerUrl = \App\Support\TextAnalyzerPdfBranding::registerUrl('text-analyzer-public-share');
    $loginUrl = \App\Support\TextAnalyzerPdfBranding::loginUrl();
@endphp
<aside class="cabinet-ta-public-register" aria-label="{{ __('Register for free') }}">
    <div class="cabinet-ta-public-register__card">
        <img src="{{ asset('img/logo-icon.svg') }}"
             alt=""
             class="cabinet-ta-public-register__logo"
             width="48"
             height="48">
        <div class="cabinet-ta-public-register__body">
            <h2 class="cabinet-ta-public-register__title">{{ __('Text analyzer public register title') }}</h2>
            <p class="cabinet-ta-public-register__lead">{{ __('Text analyzer public register lead') }}</p>
            <ul class="cabinet-ta-public-register__list">
                <li>{{ __('Text analyzer public register benefit 1') }}</li>
                <li>{{ __('Text analyzer public register benefit 2') }}</li>
                <li>{{ __('Text analyzer public register benefit 3') }}</li>
            </ul>
        </div>
        <div class="cabinet-ta-public-register__actions">
            <a href="{{ $registerUrl }}" class="btn btn-primary cabinet-ta-public-register__btn">
                {{ __('Register for free') }}
            </a>
            <p class="cabinet-ta-public-register__login mb-0">
                {{ __('Already have an account') }}?
                <a href="{{ $loginUrl }}">{{ __('Login') }}</a>
            </p>
        </div>
    </div>
</aside>
