<div class="card card-outline card-success h-100">
    <div class="card-header">
        <h3 class="card-title mb-0">
            <i class="bi bi-check-circle me-1"></i>{{ __('Active subscription') }}
        </h3>
    </div>
    <div class="card-body">
        <p class="text-secondary small mb-3">{{ __('Tariff plan you are subscribed to.') }}</p>
        @include('tariff.partials._table', ['id' => 'subscription-info', 'total' => $actual['info']])
        @if(!empty($actual['data']) && (int) $actual['data']->sum > 0)
            <div class="alert alert-light border small mb-0 mt-3">
                <i class="bi bi-arrow-repeat me-1"></i>{{ __('Tariff auto renew notice') }}
            </div>
        @endif
    </div>
    <div class="card-footer">
        <a href="javascript:void(0)" class="btn btn-outline-danger w-100" id="unsubscribe">
            <i class="bi bi-x-circle me-1"></i>{{ __('Cancel subscription') }}
        </a>
    </div>
</div>
