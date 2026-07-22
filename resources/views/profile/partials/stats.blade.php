<div class="row g-3 mb-3 cabinet-profile-stats align-items-stretch">
    <div class="col-12 col-md-4 d-flex">
        <a href="{{ route('balance.index') }}" class="info-box mb-0 flex-fill">
            <span class="info-box-icon text-bg-success shadow-sm">
                <i class="bi bi-wallet2"></i>
            </span>
            <div class="info-box-content">
                <span class="info-box-text">{{ __('Your balance') }}</span>
                <span class="info-box-number">{{ $balanceFormatted }} ₽</span>
                <span class="info-box-meta text-secondary">{{ __('Top up your balance') }}</span>
            </div>
        </a>
    </div>
    @if($name)
        <div class="col-12 col-md-4 d-flex">
            <a href="{{ route('tariff.index') }}" class="info-box mb-0 flex-fill">
                <span class="info-box-icon text-bg-primary shadow-sm">
                    <i class="bi bi-tag"></i>
                </span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('Your tariff') }}</span>
                    <span class="info-box-number text-truncate cabinet-profile-stat-tariff">{{ $name }}</span>
                    <span class="info-box-meta text-secondary">
                        {{ $tariffValidUntil ?? __('Tariffs') }}
                    </span>
                </div>
            </a>
        </div>
    @endif
    <div class="col-12 col-md-4 d-flex">
        <a href="{{ route('menu.config') }}" class="info-box mb-0 flex-fill">
            <span class="info-box-icon text-bg-secondary shadow-sm">
                <i class="bi bi-list-ul"></i>
            </span>
            <div class="info-box-content">
                <span class="info-box-text">{{ __('Setting menu') }}</span>
                <span class="info-box-number cabinet-profile-stat-menu">{{ __('Configure') }}</span>
                <span class="info-box-meta text-secondary">{{ __('Setting up menu items') }}</span>
            </div>
        </a>
    </div>
</div>
