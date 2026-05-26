@php
    $user = auth()->user();
    $telegramConnected = $user && $user->isTelegramConnected();
@endphp
@if($user && !$telegramConnected)
    <div class="alert alert-info border-info mb-0 cabinet-sm-cabinet-only-notice" role="note">
        <p class="mb-1 fw-semibold">
            <i class="bi bi-info-circle me-1" aria-hidden="true"></i>{{ __('Site monitoring cabinet only notice title') }}
        </p>
        <p class="mb-2 small">
            {{ __('Site monitoring cabinet only notice body') }}
        </p>
        <a href="{{ route('profile.index') }}#telegram" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-telegram me-1" aria-hidden="true"></i>{{ __('Connect Telegram in profile') }}
        </a>
    </div>
@endif
