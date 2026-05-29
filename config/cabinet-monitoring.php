<?php

/**
 * Мониторинг позиций — UI v2 (/monitoring-v2).
 *
 * @see App\Http\Controllers\MonitoringV2Controller
 */
return [
    'version' => '3.4.76-dev',

    /** После скольких часов считать локальный/серверный снимок тренда устаревшим (кнопка «Пересчитать»). */
    'trend_stale_hours' => (int) env('MONITORING_TREND_STALE_HOURS', 24),

    'debug_log' => env('MONITORING_V2_DEBUG_LOG', true),
    'debug_log_ttl_minutes' => (int) env('MONITORING_V2_DEBUG_LOG_TTL', 120),
    'debug_log_max_entries' => (int) env('MONITORING_V2_DEBUG_LOG_MAX', 250),
];
