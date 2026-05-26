<?php

/**
 * Telegram Bot API: прокси и реестр модулей с уведомлениями.
 * Все исходящие sendMessage идут через TelegramBotService (+ TELEGRAM_PROXY).
 */
return [
    /** Список прокси (секреты): storage/app/telegram-proxies.json, не в git */
    'proxies_file' => 'app/telegram-proxies.json',

    'debug_log' => true,
    'debug_log_max_entries' => 80,

    'modules' => [
        [
            'slug' => 'profile',
            'title' => 'Telegram admin module profile',
            'route' => 'profile.index',
            'route_fragment' => 'telegram',
            'cron' => null,
            'entry' => 'TelegramBot::sendTestNotify / profile.test-telegram-notify',
        ],
        [
            'slug' => 'backlink',
            'title' => 'Telegram admin module backlink',
            'route' => 'backlink',
            'cron' => 'GET /api/backlink/scan-broken-links',
            'entry' => 'User::sendBrokenLinkProjectTelegram, TelegramBot::brokenLinkProjectNotification',
        ],
        [
            'slug' => 'site-monitoring',
            'title' => 'Telegram admin module site monitoring',
            'route' => 'site.monitoring',
            'cron' => 'domain monitoring cron',
            'entry' => 'DomainMonitoring → TelegramBot::brokenDomainNotification, repairedDomainNotification',
        ],
        [
            'slug' => 'domain-information',
            'title' => 'Telegram admin module domain information',
            'route' => 'domain.information',
            'cron' => 'domain information cron',
            'entry' => 'DomainInformation → change DNS, registration expiry',
        ],
        [
            'slug' => 'meta-tags',
            'title' => 'Telegram admin module meta tags',
            'route' => 'meta-tags.index',
            'cron' => 'MetaTags cron',
            'entry' => 'App\\Classes\\Cron\\MetaTags',
        ],
        [
            'slug' => 'cluster',
            'title' => 'Telegram admin module cluster',
            'route' => 'cluster',
            'cron' => null,
            'entry' => 'Cluster::sendNotification (on job complete)',
        ],
        [
            'slug' => 'monitoring-limits',
            'title' => 'Telegram admin module monitoring limits',
            'route' => 'monitoring.projects.get',
            'cron' => null,
            'entry' => 'App\\Classes\\Monitoring\\Limits',
        ],
        [
            'slug' => 'telegram-webhook',
            'title' => 'Telegram admin module webhook',
            'route' => null,
            'cron' => null,
            'entry' => 'POST /api/bot → TelegramBotController (subscribe)',
        ],
    ],
];
