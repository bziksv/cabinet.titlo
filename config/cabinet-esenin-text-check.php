<?php

return [
    'version' => '1.3.4',
    /** Версия логики подсветки/метрик; при смене старые сохранённые отчёты просят перепроверку. */
    'analyzer_version' => 4,

    /** Лимит символов за одну проверку. */
    'max_chars' => 20000,

    /** Стоимость одной проверки в месячном лимите кабинета. */
    'cost_per_check' => 1,

    /** Режим по умолчанию: risk — общий риск со всеми вкладками. */
    'default_mode' => 'risk',

    /** Лимиты заданий и автосохранения (расширяются по тарифу). */
    'limits' => [
        'max_versions_per_session' => 3,
        'max_saved_sessions' => 50,
        'autosave_debounce_ms' => 2500,
    ],

    /** Сроки публичной ссылки (дни; 0 = бессрочно). */
    'public_share_ttl_days' => [30, 90, 180, 365, 0],

    'demo' => [
        'module' => 'proverka-teksta-esenin',
        'landing_url' => 'https://titlo.ru/proverka-teksta-esenin/',
        'max_runs_per_day' => 2,
        'max_chars' => 5000,
        'min_chars' => 100,
        'full_max_chars' => 20000,
    ],
];
