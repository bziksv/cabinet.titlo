@php
    $avatarUrl = $user->image ?: asset('img/user-icon.svg');
    $langLabel = $lang[$user->lang] ?? $user->lang;
@endphp
<div class="card cabinet-profile-aside">
    <div class="card-body text-center">
        <div class="cabinet-profile-avatar-wrap mx-auto mb-3 position-relative">
            <img src="{{ $avatarUrl }}"
                 alt="{{ $displayName }}"
                 class="rounded-circle shadow cabinet-profile-avatar"
                 id="cabinet-profile-avatar-main"
                 width="96"
                 height="96">
            @if($telegramConnected ?? false)
                <span class="cabinet-profile-telegram-badge position-absolute bottom-0 end-0 badge text-bg-info rounded-pill"
                      title="{{ __('Telegram connected') }}">
                    <i class="bi bi-telegram" aria-hidden="true"></i>
                </span>
            @endif
        </div>
        <h3 class="h5 mb-1 text-break">{{ $displayName }}</h3>
        <p class="text-secondary small mb-2 text-break">{{ $user->email }}</p>
        @if($emailPending)
            <span class="badge text-bg-warning mb-2">{{ __('Email not verified') }}</span>
        @else
            <span class="badge text-bg-success mb-2">{{ __('Email verified') }}</span>
        @endif
        @if($name)
            <div class="mb-2"><span class="badge text-bg-primary">{{ $name }}</span></div>
            @if(!empty($tariffValidUntil))
                <p class="text-secondary small mb-2">{{ $tariffValidUntil }}</p>
            @endif
        @endif
        <ul class="list-group list-group-flush text-start small mt-2">
            <li class="list-group-item d-flex justify-content-between px-0">
                <span class="text-secondary">{{ __('Balance') }}</span>
                <span class="fw-semibold">{{ $balanceFormatted }} ₽</span>
            </li>
            <li class="list-group-item d-flex justify-content-between px-0">
                <span class="text-secondary">{{ __('Lang') }}</span>
                <span class="fw-semibold">{{ $langLabel }}</span>
            </li>
            @if($user->last_online_at)
                <li class="list-group-item d-flex justify-content-between px-0">
                    <span class="text-secondary">{{ __('Last visit') }}</span>
                    <span class="fw-semibold text-end">{{ $user->last_online_at->diffForHumans() }}</span>
                </li>
            @endif
            @if($user->created_at)
                <li class="list-group-item d-flex justify-content-between px-0">
                    <span class="text-secondary">{{ __('Member since') }}</span>
                    <span class="fw-semibold">{{ $user->created_at->format('d.m.Y') }}</span>
                </li>
            @endif
        </ul>
        <div class="d-grid gap-2 mt-3">
            <a href="{{ route('balance.index') }}" class="btn btn-success btn-sm">
                <i class="bi bi-wallet2 me-1"></i>{{ __('Top up your balance') }}
            </a>
            <a href="{{ route('support.index') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-headset me-1"></i>{{ __('Support') }}
            </a>
        </div>
    </div>
</div>
