<?php

return [
    'version' => '1.6.9s',

    /** Последних проверок в PDF и публичной ссылке. */
    'report_export_log_limit' => 100,

    /** Срок публичной ссылки (дней); 0 — бессрочно. */
    'public_share_ttl_days' => [30, 90, 180, 365, 0],

    /** Тариф Free: только этот интервал cron (минуты). */
    'free_timing_minutes' => 60,

    /** Записей истории проверок на страницу в модалке статистики. */
    'stats_log_per_page' => 25,

    'demo' => [
        'module' => 'monitoring-saytov',
        'max_runs_per_day' => 5,
        'default_waiting_time' => 15,
    ],

  /** Значения по умолчанию до первой записи в site_monitoring_configs (админка модуля). */
    'notifications' => [
        'repeat_broken_minutes' => 360,
        'default_send_notification' => true,
        'email_enabled' => true,
        'telegram_enabled' => true,
    ],
];
