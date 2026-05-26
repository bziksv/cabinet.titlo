<?php

/**
 * XML-провайдеры кабинета и привязка к модулям.
 * Балансы: App\Services\Xml\XmlProviderBalanceService
 */
return [
    'version' => '1.0.0s',

    'balance_cache_seconds' => (int) env('CABINET_XML_BALANCE_CACHE', 90),

    'providers' => [
        'xmlstock' => [
            'title' => 'XMLStock',
            'site_url' => 'https://xmlstock.com/',
            'cabinet_url' => 'https://xmlstock.com/?do=login',
            'config_user' => 'xmlstock.user',
            'config_key' => 'xmlstock.key',
            'balance' => [
                'type' => 'json',
                'url' => 'https://xmlstock.com/api/?user={user}&key={key}',
                'balance_field' => 'balance',
            ],
        ],
        'xmlproxy' => [
            'title' => 'XMLProxy',
            'site_url' => 'https://xmlproxy.ru/',
            'cabinet_url' => 'https://xmlproxy.ru/',
            'config_user' => 'xmlproxy.user',
            'config_key' => 'xmlproxy.key',
            'balance' => [
                'type' => 'json',
                'url' => 'https://xmlproxy.ru/balance.php?user={user}&key={key}',
                'balance_field' => 'data',
            ],
        ],
        'xmlriver' => [
            'title' => 'XMLRiver',
            'site_url' => 'https://xmlriver.com/',
            'cabinet_url' => 'https://xmlriver.com/',
            'config_user' => 'xmlriver.user',
            'config_key' => 'xmlriver.key',
            'balance' => [
                'type' => 'plain',
                'url' => 'https://xmlriver.com/api/get_balance/?user={user}&key={key}',
            ],
        ],
    ],

    /**
     * Какие модули какими провайдерами пользуются (по коду).
     * providers — порядок fallback (SimplifiedXmlFacade) или единственный (Positions).
     */
    'modules' => [
        [
            'key' => 'competitor-analysis',
            'title' => 'Анализ конкурентов по ключевым словам',
            'route' => 'competitor.analysis',
            'class' => 'App\\SearchCompetitors',
            'facade' => 'SimplifiedXmlFacade',
            'providers' => ['xmlstock', 'xmlproxy', 'xmlriver'],
            'engines' => ['yandex', 'google'],
            'usage' => 'SERP XML: fallback xmlstock → xmlproxy → xmlriver (Яндекс); Google: xmlstock → xmlriver',
        ],
        [
            'key' => 'relevance-analysis',
            'title' => 'Анализатор релевантности страницы',
            'route' => 'relevance-analysis',
            'class' => 'App\\Relevance',
            'facade' => 'SimplifiedXmlFacade',
            'providers' => ['xmlstock', 'xmlproxy', 'xmlriver'],
            'engines' => ['yandex', 'google'],
            'usage' => 'Позиция фразы в выдаче (getPosition)',
        ],
        [
            'key' => 'cluster',
            'title' => 'Кластеризатор',
            'route' => 'cluster',
            'class' => 'App\\Cluster',
            'facade' => 'SimplifiedXmlFacade + RiverFacade',
            'providers' => ['xmlstock', 'xmlproxy', 'xmlriver'],
            'engines' => ['yandex'],
            'usage' => 'SERP — SimplifiedXmlFacade; Wordstat — XMLRiver (wordstat/new/json)',
            'extra_providers' => [
                [
                    'provider' => 'xmlriver',
                    'api' => 'https://xmlriver.com/wordstat/new/json',
                    'note' => 'RiverFacade / ClusterQueue (pagetype=history, totalValue)',
                ],
            ],
        ],
        [
            'key' => 'monitoring-positions',
            'title' => 'Мониторинг позиций',
            'route' => 'monitoring.index',
            'class' => 'App\\Classes\\Position\\Positions',
            'facade' => 'XmlFacade',
            'providers' => ['xmlstock'],
            'engines' => ['yandex', 'google'],
            'usage' => 'Только XMLStock: yandex/xml и google/xml',
        ],
        [
            'key' => 'monitoring-occurrence',
            'title' => 'Мониторинг (вхождения / wordstat)',
            'route' => 'site.monitoring',
            'class' => 'App\\Classes\\Services\\XmlRiver',
            'facade' => 'XmlRiver (wordstat)',
            'providers' => ['xmlriver'],
            'engines' => [],
            'usage' => 'XMLRiver Wordstat JSON (OccurrenceQueue)',
        ],
    ],
];
