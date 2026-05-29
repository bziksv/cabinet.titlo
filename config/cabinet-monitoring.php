<?php

/**
 * Мониторинг позиций — UI v2 (/monitoring-v2).
 *
 * @see App\Http\Controllers\MonitoringV2Controller
 */
return [
    'version' => '3.5.92-dev',

    /** Подбор конкурентов из таблицы («Подобрать из топ-10»): сколько доменов предложить. */
    'competitors_suggest_limit' => 10,

    /** Дополнительно к `monitoring_settings.ignored_domains` — всегда исключаются из подбора. */
    'competitors_ignored_domains' => [
        'yandex.ru',
    ],

    /** Сроки публичной ссылки (дни; 0 — бессрочно). */
    'public_share_ttl_days' => [30, 90, 180, 365, 0],

    /** После скольких часов считать локальный/серверный снимок тренда устаревшим (кнопка «Пересчитать»). */
    'trend_stale_hours' => (int) env('MONITORING_TREND_STALE_HOURS', 24),

    'debug_log' => env('MONITORING_V2_DEBUG_LOG', true),
    'debug_log_ttl_minutes' => (int) env('MONITORING_V2_DEBUG_LOG_TTL', 120),
    'debug_log_max_entries' => (int) env('MONITORING_V2_DEBUG_LOG_MAX', 250),
];
