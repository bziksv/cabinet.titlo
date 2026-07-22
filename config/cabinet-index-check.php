<?php

return [
    'version' => '1.1.1',

    /** Стоимость одной проверки URL в одной ПС (лимитных единиц). */
    'cost_per_engine' => 1,

    'default_yandex_lr' => '213',
    'default_google_lr' => '213',

    /** Сколько URL из выдачи site: разбираем при сопоставлении (главная часто не в топ-10). */
    'serp_depth' => 100,

    'google_domains' => [
        'google.ru' => '213',
        'google.com' => '2840',
        'google.com.ua' => '187',
        'google.by' => '149',
        'google.kz' => '159',
    ],

    'demo' => [
        'module' => 'proverka-indeksacii',
        'max_runs_per_day' => 5,
        'max_urls_per_run' => 1,
    ],
];
