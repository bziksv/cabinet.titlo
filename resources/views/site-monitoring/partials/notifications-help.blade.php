@php
    $settings = $notificationSettings ?? \App\SiteMonitoringConfig::instance();
    $repeatHours = round($settings->repeat_broken_notification_minutes / 60, 1);
    $user = auth()->user();
@endphp
<div class="alert alert-light border cabinet-sm-notify-help mb-3" role="note">
    <p class="fw-semibold mb-2">
        <i class="bi bi-bell me-1 text-primary" aria-hidden="true"></i>{{ __('Site monitoring notifications') }}
    </p>
    <ul class="small text-secondary mb-2 ps-3">
        <li>{{ __('Site monitoring notify email') }}</li>
        <li>{{ __('Site monitoring notify telegram') }}
            @if($user && $user->telegram_bot_active)
                <span class="badge text-bg-success ms-1">{{ __('Telegram connected') }}</span>
            @else
                — <a href="{{ route('profile.index') }}#telegram">{{ __('Connect Telegram in profile') }}</a>
            @endif
        </li>
        <li>{{ __('Site monitoring notify per project') }}</li>
        <li>{!! __('Site monitoring notify repeat', ['hours' => $repeatHours, 'minutes' => $settings->repeat_broken_notification_minutes]) !!}</li>
        <li>{{ __('Site monitoring notify cron') }}</li>
    </ul>
    @if($admin ?? false)
        <a href="{{ route('site.monitoring.config') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-sliders me-1" aria-hidden="true"></i>{{ __('Notification settings (admin)') }}
        </a>
    @endif
</div>
