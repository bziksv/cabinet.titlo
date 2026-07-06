<?php

/**
 * Telegram Bot API: реестр модулей с исходящими уведомлениями.
 * Все sendMessage → TelegramBotService → прокси из таблицы telegram_proxies.
 * UI: partial resources/views/partials/cabinet-telegram-notify-notice.blade.php (если !isTelegramConnected()).
 */
return [
    'debug_log' => true,
    'debug_log_max_entries' => 80,

    /** Бонус на баланс при первой привязке Telegram через бота. */
    'connect_bonus_enabled' => true,
    'connect_bonus_amount' => 500,

    /** getUpdates по cron — на VPS входящий webhook от Telegram часто недоступен. */
    'poll_updates' => true,

    /** Не пробовать direct к api.telegram.org, если настроен прокси (VPS). */
    'skip_direct_outbound' => env('TELEGRAM_SKIP_DIRECT', null),

    'modules' => [
        [
            'slug' => 'profile',
            'title' => 'Telegram admin module profile',
            'notify_hint' => 'Telegram admin notify profile',
            'route' => 'profile.index',
            'route_fragment' => 'telegram',
            'cron' => null,
            'entry' => 'TelegramBot::sendTestNotify / profile.test-telegram-notify',
        ],
        [
            'slug' => 'backlink',
            'title' => 'Telegram admin module backlink',
            'notify_hint' => 'Telegram admin notify backlink',
            'route' => 'backlink',
            'cron' => 'GET /api/backlink/scan-broken-links',
            'entry' => 'User::sendBrokenLinkProjectTelegram, TelegramBot::brokenLinkProjectNotification',
        ],
        [
            'slug' => 'site-monitoring',
            'title' => 'Telegram admin module site monitoring',
            'notify_hint' => 'Telegram admin notify site monitoring',
            'route' => 'site.monitoring',
            'cron' => 'domain monitoring cron',
            'entry' => 'DomainMonitoring → TelegramBot::brokenDomainNotification, repairedDomainNotification',
        ],
        [
            'slug' => 'domain-information',
            'title' => 'Telegram admin module domain information',
            'notify_hint' => 'Telegram admin notify domain information',
            'route' => 'domain.information',
            'cron' => 'domain information cron',
            'entry' => 'DomainInformation → change DNS, registration expiry',
        ],
        [
            'slug' => 'meta-tags',
            'title' => 'Telegram admin module meta tags',
            'notify_hint' => 'Telegram admin notify meta tags',
            'route' => 'meta-tags.index',
            'cron' => 'MetaTags cron',
            'entry' => 'App\\Classes\\Cron\\MetaTags',
        ],
        [
            'slug' => 'cluster',
            'title' => 'Telegram admin module cluster',
            'notify_hint' => 'Telegram admin notify cluster',
            'route' => 'cluster',
            'cron' => null,
            'entry' => 'Cluster::sendNotification (on job complete)',
        ],
        [
            'slug' => 'monitoring-limits',
            'title' => 'Telegram admin module monitoring limits',
            'notify_hint' => 'Telegram admin notify monitoring limits',
            'route' => 'monitoring.projects.get',
            'cron' => null,
            'entry' => 'App\\Classes\\Monitoring\\Limits',
        ],
        [
            'slug' => 'telegram-webhook',
            'title' => 'Telegram admin module webhook',
            'notify_hint' => 'Telegram admin notify webhook',
            'route' => null,
            'cron' => 'POST /api/bot',
            'entry' => 'TelegramBotController → subscribe / ошибка команды',
        ],
    ],
];
