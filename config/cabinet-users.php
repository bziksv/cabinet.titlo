<?php

/** Админ: /users */
return [
    'version' => '1.2.8s',

    /** Неактивность владельца (дней) для отчёта «зависшие расписания мониторинга». */
    'stale_monitoring_inactive_days' => (int) env('CABINET_USERS_STALE_MONITORING_DAYS', 90),

    /** Роли платных тарифов (для фильтра «только Free»). */
    'paid_tariff_role_codes' => ['Optimal', 'Ultimate', 'Maximum'],

    /** Кэш снимка объёма данных пользователя, минуты. */
    'storage_cache_ttl_minutes' => (int) env('CABINET_USERS_STORAGE_CACHE_MINUTES', 1440),

    /**
     * Модули для подсчёта строк пользователя.
     * type: column | monitoring_keywords_by_creator | monitoring_positions_by_creator
     */
    'storage_modules' => [
        [
            'key' => 'monitoring_projects',
            'label' => 'Мониторинг: проекты',
            'table' => 'monitoring_projects',
            'column' => 'creator',
            'avg_row_bytes' => 512,
        ],
        [
            'key' => 'monitoring_keywords',
            'label' => 'Мониторинг: ключи',
            'type' => 'monitoring_keywords_by_creator',
            'table' => 'monitoring_keywords',
            'avg_row_bytes' => 180,
        ],
        [
            'key' => 'monitoring_positions',
            'label' => 'Мониторинг: позиции',
            'type' => 'monitoring_positions_by_creator',
            'table' => 'monitoring_positions',
            'avg_row_bytes' => 96,
        ],
        [
            'key' => 'relevance_projects',
            'label' => 'Релевантность: проекты',
            'table' => 'project_relevance_history',
            'column' => 'user_id',
            'avg_row_bytes' => 640,
        ],
        [
            'key' => 'relevance_history',
            'label' => 'Релевантность: проверки',
            'table' => 'relevance_history',
            'column' => 'user_id',
            'avg_row_bytes' => 420,
        ],
        [
            'key' => 'cluster_results',
            'label' => 'Кластеризатор',
            'table' => 'cluster_results',
            'column' => 'user_id',
            'avg_row_bytes' => 8192,
        ],
        [
            'key' => 'meta_tags',
            'label' => 'Мета-теги',
            'table' => 'meta_tags',
            'column' => 'user_id',
            'avg_row_bytes' => 512,
        ],
        [
            'key' => 'domain_monitoring',
            'label' => 'Мониторинг сайта',
            'table' => 'domain_monitoring',
            'column' => 'user_id',
            'avg_row_bytes' => 400,
        ],
        [
            'key' => 'html_projects',
            'label' => 'HTML-редактор',
            'table' => 'projects',
            'column' => 'user_id',
            'avg_row_bytes' => 800,
        ],
        [
            'key' => 'backlinks',
            'label' => 'Ссылки',
            'table' => 'project_tracking',
            'column' => 'user_id',
            'avg_row_bytes' => 350,
        ],
    ],
];
