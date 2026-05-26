<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SiteMonitoringConfig extends Model
{
    protected $table = 'site_monitoring_configs';

    protected $guarded = [];

    protected $casts = [
        'default_send_notification' => 'boolean',
        'email_notifications_enabled' => 'boolean',
        'telegram_notifications_enabled' => 'boolean',
    ];

    public static function instance(): self
    {
        $row = static::query()->first();
        if ($row) {
            return $row;
        }

        return static::query()->create([
            'repeat_broken_notification_minutes' => (int) config('cabinet-site-monitoring.notifications.repeat_broken_minutes', 360),
            'default_send_notification' => (bool) config('cabinet-site-monitoring.notifications.default_send_notification', true),
            'email_notifications_enabled' => (bool) config('cabinet-site-monitoring.notifications.email_enabled', true),
            'telegram_notifications_enabled' => (bool) config('cabinet-site-monitoring.notifications.telegram_enabled', true),
        ]);
    }

    public static function repeatBrokenMinutes(): int
    {
        $minutes = (int) static::instance()->repeat_broken_notification_minutes;

        return max(60, min(10080, $minutes));
    }

    public static function defaultSendNotification(): bool
    {
        return (bool) static::instance()->default_send_notification;
    }

    public static function emailEnabled(): bool
    {
        return (bool) static::instance()->email_notifications_enabled;
    }

    public static function telegramEnabled(): bool
    {
        return (bool) static::instance()->telegram_notifications_enabled;
    }
}
