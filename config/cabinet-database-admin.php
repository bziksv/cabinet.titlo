<?php

/**
 * Админ-страница /admin/database — инвентаризация MySQL.
 *
 * @see App\Services\Database\DatabaseInventoryService
 */
return [
    'version' => '1.2.2s',

    /** Таймаут AJAX-превью строк, секунды */
    'row_preview_timeout_seconds' => 15,

    /**
     * Превью: ORDER BY id DESC (PK), не created_at — иначе на крупных таблицах минуты.
     */
    'row_preview_order_by_id_tables' => [
        'search_indices',
        'monitoring_positions',
        'monitoring_stats',
        'relevance_history',
        'relevance_history_result',
    ],

    /**
     * Таблицы, для которых на /admin/database показывается «Очистить» (TRUNCATE + OPTIMIZE).
     * Только явный whitelist — случайно не снести продуктовые данные.
     */
    'clearable_tables' => [
        'failed_jobs',
    ],

    /**
     * OPTIMIZE: синхронно из UI, если размер таблицы < этого порога (МБ).
     * Крупнее — в очередь (OptimizeDatabaseTableJob).
     */
    'optimize_sync_max_mb' => (int) env('CABINET_DB_OPTIMIZE_SYNC_MB', 500),

    /** Очередь для фонового OPTIMIZE */
    'optimize_queue' => env('CABINET_DB_OPTIMIZE_QUEUE', 'default'),

    /** TTL глобального lock на время OPTIMIZE, секунды */
    'optimize_lock_seconds' => (int) env('CABINET_DB_OPTIMIZE_LOCK', 7200),

    /** Таблицы, которые нельзя оптимизировать из UI */
    'optimize_deny_tables' => [
        // 'migrations',
    ],

    /**
     * Подписи колонок в колонке «Диапазон данных» (вместо сырого created_at и т.п.).
     */
    'date_column_labels' => [
        'created_at' => 'Дата создания',
        'updated_at' => 'Дата обновления',
        'deleted_at' => 'Дата удаления',
        'last_check' => 'Последняя проверка',
        'checked_at' => 'Дата проверки',
        'failed_at' => 'Дата сбоя',
        'available_at' => 'Доступно с',
        'reserved_at' => 'Зарезервировано',
        'email_verified_at' => 'Подтверждение e-mail',
        'paid_at' => 'Дата оплаты',
        'expires_at' => 'Истекает',
        'start_at' => 'Начало',
        'end_at' => 'Окончание',
        'date' => 'Дата',
        'published_at' => 'Дата публикации',
        'last_seen_at' => 'Последняя активность',
        'last_login_at' => 'Последний вход',
    ],

    /** Последние N строк в превью таблицы */
    'row_preview_limit' => 10,

    /** Обрезка длинных полей в превью (LEFT в SQL + truncate в PHP) */
    'row_preview_max_cell_chars' => 400,

    /** Не показывать в превью (секреты). Остальные колонки — все. */
    'row_preview_exclude_columns_global' => ['password', 'remember_token'],

    /** @deprecated больше не используется — все колонки показываются */
    'row_preview_exclude_columns' => [],

    /** Кэш снимка (information_schema + маппинг), секунды */
    'snapshot_cache_seconds' => (int) env('CABINET_DB_INVENTORY_CACHE', 3600),

    /** Порог «крупная таблица» для фильтра, МБ */
    'large_table_mb' => 100,

    /** Выше этого размера — не MIN/MAX, а «лёгкий» режим (ORDER BY id LIMIT 1) */
    'date_probe_light_above_mb' => (int) env('CABINET_DB_DATE_PROBE_LIGHT_MB', 500),

    /** Сколько таблиц обработать за один клик (последовательно, не параллельно) */
    'date_probe_batch_size' => (int) env('CABINET_DB_DATE_PROBE_BATCH', 5),

    /** Таймаут одного запроса MIN/MAX, секунды */
    'date_probe_timeout_seconds' => 8,

    /** Всегда лёгкий режим (даже если таблица < light_above_mb) */
    'date_probe_light_tables' => [
        'relevance_history',
        'relevance_history_result',
        'search_indices',
        'monitoring_positions',
        'monitoring_stats',
    ],

    /**
     * Доп. колонка «последняя активность» для лёгкого режима (берётся с строки max id).
     * relevance_history: last_check — дата проверки, не только created_at.
     */
    'date_probe_light_extra_column' => [
        'relevance_history' => 'last_check',
    ],

    /**
     * Системные / инфраструктурные таблицы (не «забытые» продуктовые).
     * category: laravel | spatie | pivot | infra
     */
    'system_tables' => [
        'migrations' => ['category' => 'laravel', 'title' => 'Laravel migrations'],
        'cache' => ['category' => 'laravel', 'title' => 'Laravel cache'],
        'jobs' => ['category' => 'laravel', 'title' => 'Очередь Laravel'],
        'failed_jobs' => ['category' => 'laravel', 'title' => 'Ошибки очереди'],
        'sessions' => ['category' => 'laravel', 'title' => 'Сессии'],
        'password_resets' => ['category' => 'laravel', 'title' => 'Сброс пароля'],
        'permissions' => ['category' => 'spatie', 'title' => 'Spatie Permission'],
        'roles' => ['category' => 'spatie', 'title' => 'Spatie Permission'],
        'model_has_permissions' => ['category' => 'spatie', 'title' => 'Spatie Permission'],
        'model_has_roles' => ['category' => 'spatie', 'title' => 'Spatie Permission'],
        'role_has_permissions' => ['category' => 'spatie', 'title' => 'Spatie Permission'],
        'monitoring_project_user' => ['category' => 'pivot', 'title' => 'Мониторинг: проект ↔ пользователь'],
        'monitoring_group_user' => ['category' => 'pivot', 'title' => 'Мониторинг: группа ↔ пользователь'],
    ],

    /**
     * Явная привязка таблицы → модуль кабинета (если префикса недостаточно).
     * title — как в меню; uri — путь без домена.
     */
    'table_modules' => [
        'search_indices' => ['title' => 'Мониторинг позиций', 'uri' => '/monitoring'],
        'analyze_relevance' => ['title' => 'Анализ релевантности', 'uri' => '/analyze-relevance'],
        'project_description' => ['title' => 'HTML-редактор / проекты', 'uri' => '/html-editor'],
        'projects' => ['title' => 'Проекты (общее)', 'uri' => '/'],
        'descriptions' => ['title' => 'Проекты (общее)', 'uri' => '/'],
        'balances' => ['title' => 'Биллинг', 'uri' => '/'],
        'users' => ['title' => 'Пользователи', 'uri' => '/users'],
        'main_projects' => ['title' => 'Управление меню', 'uri' => '/main-projects'],
        'menu_items_position' => ['title' => 'Настройка меню', 'uri' => '/configuration-menu'],
        'tariff_settings' => ['title' => 'Тарифы', 'uri' => '/tariff-settings'],
        'tariff_setting_values' => ['title' => 'Тарифы', 'uri' => '/tariff-settings'],
        'tariff_setting_user_values' => ['title' => 'Тарифы', 'uri' => '/tariff-settings'],
        'tariff_pays' => ['title' => 'Тарифы', 'uri' => '/tariff-settings'],
        'visit_statistics' => ['title' => 'Главная / аналитика', 'uri' => '/'],
        'users_statistics' => ['title' => 'Статистика пользователей', 'uri' => '/users'],
        'users_jobs' => ['title' => 'Фоновые задачи пользователей', 'uri' => '/'],
        'locations' => ['title' => 'Справочник регионов', 'uri' => '/monitoring'],
        'telegram_proxies' => ['title' => 'Telegram-прокси (админ)', 'uri' => '/admin/telegram-proxy'],
        'smtp_settings' => ['title' => 'SMTP (админ)', 'uri' => '/admin/smtp'],
    ],

    /** Подсказки для таблиц без привязки к модулю */
    'orphan_notes' => [
        'history_access' => 'Миграция 2022-05-19; в app/, routes/, views/ упоминаний нет — вероятно забытая таблица.',
    ],

    /** Префикс имени таблицы → модуль */
    'prefix_modules' => [
        'relevance_' => ['title' => 'Анализ релевантности', 'uri' => '/analyze-relevance'],
        'project_relevance_' => ['title' => 'Анализ релевантности', 'uri' => '/analyze-relevance'],
        'monitoring_' => ['title' => 'Мониторинг позиций', 'uri' => '/monitoring'],
        'meta_tags' => ['title' => 'Мониторинг мета-тегов', 'uri' => '/meta-tags'],
        'meta_tag' => ['title' => 'Мониторинг мета-тегов', 'uri' => '/meta-tags'],
        'cluster_' => ['title' => 'Кластеризатор', 'uri' => '/cluster'],
        'clusters_' => ['title' => 'Кластеризатор', 'uri' => '/cluster'],
        'competitor_' => ['title' => 'Анализ конкурентов', 'uri' => '/competitor-analysis'],
        'text_analy' => ['title' => 'Анализ текста', 'uri' => '/text-analyzer'],
        'html_editor_' => ['title' => 'HTML-редактор', 'uri' => '/html-editor'],
        'domain_information' => ['title' => 'Срок регистрации доменов', 'uri' => '/domain-information'],
        'domain_monitoring' => ['title' => 'Мониторинг сайтов', 'uri' => '/site-monitoring'],
        'site_monitoring_' => ['title' => 'Мониторинг сайтов', 'uri' => '/site-monitoring'],
        'http_header' => ['title' => 'HTTP-заголовки', 'uri' => '/http-headers'],
        'index_check_usage' => ['title' => 'Проверка индексации (лимиты)', 'uri' => '/index-check'],
        'esenin_text_check_usages' => ['title' => 'Проверка текста Есенин (лимиты)', 'uri' => '/esenin-text-check'],
        'link_tracking' => ['title' => 'Отслеживание ссылок', 'uri' => '/backlink'],
        'click_tracking' => ['title' => 'Отслеживание ссылок', 'uri' => '/backlink'],
        'generator_password' => ['title' => 'Генератор паролей', 'uri' => '/password-generator'],
        'ai_generation_' => ['title' => 'AI-генерация', 'uri' => '/ai-generation'],
        'feature_idea' => ['title' => 'Доска идей', 'uri' => '/ideas'],
        'support_ticket' => ['title' => 'Служба поддержки', 'uri' => '/support'],
        'news' => ['title' => 'Новости', 'uri' => '/news'],
        'partners_' => ['title' => 'Партнёры', 'uri' => '/'],
        'policy_and_terms' => ['title' => 'Политики', 'uri' => '/edit-policy-files'],
    ],
];
