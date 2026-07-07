<?php

/**
 * Админ-страница /admin/queues — мониторинг Laravel jobs.
 *
 * @see App\Services\Queue\QueueInventoryService
 */
return [
    'version' => '1.0.4s',

    /** Суточная статистика очередей (сэмплы + JobProcessed/JobFailed) */
    'stats_enabled' => filter_var(env('CABINET_QUEUE_STATS_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    /** Интервал сэмпла воркеров/очереди, секунды (cron everyFiveMinutes) */
    'stats_sample_interval' => (int) env('CABINET_QUEUE_STATS_SAMPLE_INTERVAL', 300),

    /** Хранение сырых сэмплов, дней */
    'stats_sample_retention_days' => (int) env('CABINET_QUEUE_STATS_SAMPLE_RETENTION', 8),

    /** Хранение почасовых счётчиков jobs, дней */
    'stats_hourly_retention_days' => (int) env('CABINET_QUEUE_STATS_HOURLY_RETENTION', 14),

    /** Хранение суточных агрегатов, дней */
    'stats_daily_retention_days' => (int) env('CABINET_QUEUE_STATS_DAILY_RETENTION', 90),

    /** Кэш снимка, секунды */
    'snapshot_cache_seconds' => (int) env('CABINET_QUEUE_ADMIN_CACHE', 30),

    /** Сколько недавних failed_jobs показывать */
    'failed_jobs_limit' => 25,

    /** Сколько активных отчётов «Динамика конкурентов» */
    'monitoring_reports_limit' => 30,

    /**
     * Известные очереди: подпись, модуль, порог предупреждения (jobs).
     * Неизвестные очереди из jobs тоже попадают в таблицу.
     */
    'queue_labels' => [
        'monitoring_helper' => [
            'label' => 'Мониторинг: legacy helper',
            'module' => 'Мониторинг позиций',
            'warn_above' => 500,
        ],
        'monitoring_change_dates' => [
            'label' => 'Динамика конкурентов (bulk)',
            'module' => 'Мониторинг позиций',
            'warn_above' => 20,
        ],
        'cluster_wait' => [
            'label' => 'Кластер: ожидание фраз',
            'module' => 'Кластеризатор',
            'warn_above' => 3,
        ],
        'child_cluster' => [
            'label' => 'Кластер: обработка фраз',
            'module' => 'Кластеризатор',
            'warn_above' => 200,
        ],
        'main_cluster' => [
            'label' => 'Кластер: старт анализа',
            'module' => 'Кластеризатор',
            'warn_above' => 5,
        ],
        'default' => [
            'label' => 'Общая очередь',
            'module' => 'Laravel',
            'warn_above' => 100,
        ],
    ],
];
