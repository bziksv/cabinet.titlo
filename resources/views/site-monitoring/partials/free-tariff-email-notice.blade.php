@if($onFreeTariff ?? false)
    <div class="alert alert-warning border-warning mb-0 cabinet-sm-free-email-notice" role="note">
        <p class="mb-1 fw-semibold">
            <i class="bi bi-envelope-x me-1" aria-hidden="true"></i>{{ __('Site monitoring free tariff email notice title') }}
        </p>
        <p class="mb-0 small">
            {{ __('Site monitoring free tariff email notice body') }}
            <a href="{{ route('tariff.index') }}">{{ __('Tariff') }}</a>.
        </p>
    </div>
@endif
