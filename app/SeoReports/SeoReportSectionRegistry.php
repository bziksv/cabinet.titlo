<?php

namespace App\SeoReports;

/**
 * Каталог секций отчёта + привязка к источникам данных.
 * Клиентский вид скрывает секции с source_status in (not_connected, error, empty),
 * если менеджер не включил «показывать пустое» (позже).
 */
class SeoReportSectionRegistry
{
    public const SOURCE_STATUS_OK = 'ok';
    public const SOURCE_STATUS_NOT_CONNECTED = 'not_connected';
    public const SOURCE_STATUS_ERROR = 'error';
    public const SOURCE_STATUS_EMPTY = 'empty';
    public const SOURCE_STATUS_MANUAL = 'manual';

    /**
     * @return array<string, array{
     *   title:string,
     *   hint:string,
     *   origin:string,
     *   origin_kind:string,
     *   group:string,
     *   source:string,
     *   default:bool,
     *   mvp:bool
     * }>
     */
    public static function all(): array
    {
        return [
            'cover' => [
                'title' => 'Обложка',
                'hint' => 'Титул отчёта: период, логотип агентства, контакты менеджера.',
                'origin' => 'Текст менеджера',
                'origin_kind' => 'manual',
                'group' => 'core',
                'source' => 'manual',
                'default' => true,
                'mvp' => true,
            ],
            'summary' => [
                'title' => 'Резюме и выводы',
                'hint' => 'Короткий текст для клиента: что выросло, что упало, главный фокус месяца.',
                'origin' => 'Текст менеджера',
                'origin_kind' => 'manual',
                'group' => 'core',
                'source' => 'manual',
                'default' => true,
                'mvp' => true,
            ],
            'kpi_goals' => [
                'title' => 'Цели и KPI',
                'hint' => 'Светофор по целям: визиты, TOP-10, конверсии, выручка — факт vs план.',
                'origin' => 'Текст менеджера',
                'origin_kind' => 'manual',
                'group' => 'core',
                'source' => 'manual',
                'default' => true,
                'mvp' => true,
            ],
            'traffic' => [
                'title' => 'Трафик',
                'hint' => 'Визиты, пользователи, отказы, каналы, устройства, гео и топ посадочных из Метрики.',
                'origin' => 'Яндекс.Метрика · API',
                'origin_kind' => 'api',
                'group' => 'seo',
                'source' => 'metrika',
                'default' => true,
                'mvp' => true,
            ],
            'positions' => [
                'title' => 'Позиции',
                'hint' => 'Видимость, TOP-10, выросшие/упавшие, почти в TOP-10 и сильно просевшие запросы из мониторинга.',
                'origin' => 'Мониторинг позиций Titlo',
                'origin_kind' => 'titlo',
                'group' => 'seo',
                'source' => 'monitoring',
                'default' => true,
                'mvp' => true,
            ],
            'work_done' => [
                'title' => 'Выполненные работы',
                'hint' => 'Закрытые задачи из SEO-чеклиста за период + комментарий менеджера.',
                'origin' => 'SEO-чеклист Titlo + текст менеджера',
                'origin_kind' => 'manual',
                'group' => 'core',
                'source' => 'manual',
                'default' => true,
                'mvp' => true,
            ],
            'work_plan' => [
                'title' => 'План работ',
                'hint' => 'Ближайшие задачи из SEO-чеклиста + план менеджера на следующий период.',
                'origin' => 'SEO-чеклист Titlo + текст менеджера',
                'origin_kind' => 'manual',
                'group' => 'core',
                'source' => 'manual',
                'default' => true,
                'mvp' => true,
            ],
            'gsc' => [
                'title' => 'Google Search Console',
                'hint' => 'Клики, показы, CTR и топ запросов/страниц из Search Console через API.',
                'origin' => 'Google Search Console · API',
                'origin_kind' => 'api',
                'group' => 'seo',
                'source' => 'gsc',
                'default' => false,
                'mvp' => false,
            ],
            'webmaster' => [
                'title' => 'Яндекс.Вебмастер',
                'hint' => 'Данные Вебмастера по запросам и страницам через API.',
                'origin' => 'Яндекс.Вебмастер · API',
                'origin_kind' => 'api',
                'group' => 'seo',
                'source' => 'webmaster',
                'default' => false,
                'mvp' => false,
            ],
            'conversions' => [
                'title' => 'Конверсии',
                'hint' => 'Цели Метрики: достижения, конверсия по каналам поиска / рекламы / соцсетей.',
                'origin' => 'Яндекс.Метрика · API',
                'origin_kind' => 'api',
                'group' => 'seo',
                'source' => 'metrika',
                'default' => true,
                'mvp' => true,
            ],
            'direct' => [
                'title' => 'Яндекс.Директ',
                'hint' => 'Рекламный трафик Директа: визиты, отказы, посадочные и заметки по кампаниям.',
                'origin' => 'Яндекс.Директ · API',
                'origin_kind' => 'api',
                'group' => 'ads',
                'source' => 'direct',
                'default' => false,
                'mvp' => false,
            ],
            'google_ads' => [
                'title' => 'Google Ads',
                'hint' => 'Кампании Google Ads: визиты, посадочные, фразы и конверсии из среза.',
                'origin' => 'Google Ads · API',
                'origin_kind' => 'api',
                'group' => 'ads',
                'source' => 'google_ads',
                'default' => false,
                'mvp' => false,
            ],
            'vk_ads' => [
                'title' => 'Реклама VK',
                'hint' => 'Таргет VK: охваты, клики и эффективность через API.',
                'origin' => 'VK Реклама · API',
                'origin_kind' => 'api',
                'group' => 'ads',
                'source' => 'vk_ads',
                'default' => false,
                'mvp' => false,
            ],
            'vk_smm' => [
                'title' => 'Сообщество VK',
                'hint' => 'Сообщество VK: охват, вовлечённость и топ-посты за период.',
                'origin' => 'VK · API',
                'origin_kind' => 'api',
                'group' => 'smm',
                'source' => 'vk_smm',
                'default' => false,
                'mvp' => false,
            ],
            'ecommerce' => [
                'title' => 'Электронная коммерция',
                'hint' => 'Покупки и выручка из электронной коммерции Яндекс.Метрики — для интернет-магазинов.',
                'origin' => 'Яндекс.Метрика · API',
                'origin_kind' => 'api',
                'group' => 'commerce',
                'source' => 'metrika',
                'default' => false,
                'mvp' => false,
            ],
            'calls' => [
                'title' => 'Звонки',
                'hint' => 'Динамика звонков (коллтрекинг / ручной импорт) рядом с SEO и рекламой.',
                'origin' => 'Коллтрекинг / импорт',
                'origin_kind' => 'api',
                'group' => 'commerce',
                'source' => 'calls',
                'default' => false,
                'mvp' => false,
            ],
            'titlo_audit' => [
                'title' => 'Аудит сайта',
                'hint' => 'Сводка аудита сайта: критичные / предупреждения / инфо за период.',
                'origin' => 'Модуль Titlo',
                'origin_kind' => 'titlo',
                'group' => 'titlo',
                'source' => 'site_audit',
                'default' => true,
                'mvp' => true,
            ],
            'titlo_checklist' => [
                'title' => 'SEO-чеклист',
                'hint' => 'Прогресс чеклиста: закрыто за период, просрочки, общий % — связка с работой команды.',
                'origin' => 'Модуль Titlo',
                'origin_kind' => 'titlo',
                'group' => 'titlo',
                'source' => 'seo_checklist',
                'default' => true,
                'mvp' => true,
            ],
            'titlo_relevance' => [
                'title' => 'Релевантность',
                'hint' => 'Средний балл релевантности и позиция по проверкам модуля Релевантность.',
                'origin' => 'Модуль Titlo',
                'origin_kind' => 'titlo',
                'group' => 'titlo',
                'source' => 'relevance',
                'default' => true,
                'mvp' => true,
            ],
            'titlo_uptime' => [
                'title' => 'Доступность сайта',
                'hint' => 'Аптайм, инциденты и срок домена из мониторинга доступности.',
                'origin' => 'Модуль Titlo',
                'origin_kind' => 'titlo',
                'group' => 'titlo',
                'source' => 'site_monitoring',
                'default' => true,
                'mvp' => true,
            ],
            'insights' => [
                'title' => 'Инсайты',
                'hint' => 'Автоматические выводы P1–P3 по трафику, позициям и модулям Titlo.',
                'origin' => 'Считается в Titlo автоматически',
                'origin_kind' => 'auto',
                'group' => 'titlo',
                'source' => 'computed',
                'default' => true,
                'mvp' => true,
            ],
        ];
    }

    public static function hint(string $key): string
    {
        $all = self::all();

        return (string) ($all[$key]['hint'] ?? '');
    }

    /**
     * @return array<string, bool>
     */
    public static function defaultToggles(): array
    {
        $out = [];
        foreach (self::all() as $key => $meta) {
            $out[$key] = (bool) $meta['default'];
        }

        return $out;
    }

    /**
     * @return array<string, array{title:string,group:string,source:string,default:bool,mvp:bool}>
     */
    public static function mvpSections(): array
    {
        return array_filter(self::all(), static function (array $meta) {
            return !empty($meta['mvp']);
        });
    }

    public static function title(string $key): string
    {
        $all = self::all();

        return $all[$key]['title'] ?? $key;
    }

    /**
     * @return array<string, string>
     */
    public static function groupLabels(): array
    {
        return [
            'core' => 'Основа отчёта',
            'seo' => 'SEO',
            'ads' => 'Реклама',
            'smm' => 'SMM',
            'commerce' => 'Продажи',
            'titlo' => 'Модули Titlo',
        ];
    }

    public static function sourceLabel(string $source): string
    {
        $map = [
            'manual' => 'Текст менеджера',
            'metrika' => 'Яндекс.Метрика',
            'monitoring' => 'Мониторинг позиций Titlo',
            'gsc' => 'Google Search Console',
            'webmaster' => 'Яндекс.Вебмастер',
            'direct' => 'Яндекс.Директ',
            'google_ads' => 'Google Ads',
            'vk_ads' => 'VK Реклама',
            'vk_smm' => 'VK',
            'calls' => 'Коллтрекинг',
            'site_audit' => 'Аудит сайта Titlo',
            'seo_checklist' => 'SEO-чеклист Titlo',
            'relevance' => 'Релевантность Titlo',
            'site_monitoring' => 'Доступность Titlo',
            'computed' => 'Автоматически в Titlo',
        ];

        return $map[$source] ?? $source;
    }

    public static function origin(string $key): string
    {
        $all = self::all();

        return (string) ($all[$key]['origin'] ?? self::sourceLabel((string) ($all[$key]['source'] ?? '')));
    }

    public static function originKind(string $key): string
    {
        $all = self::all();

        return (string) ($all[$key]['origin_kind'] ?? 'manual');
    }

    /**
     * @return list<string>
     */
    public static function defaultOrder(): array
    {
        return array_keys(self::all());
    }

    /**
     * @param array<string,mixed>|null $settings
     * @return list<string>
     */
    public static function orderedKeys(?array $settings = null): array
    {
        $default = self::defaultOrder();
        $stored = is_array($settings['section_order'] ?? null) ? $settings['section_order'] : [];
        if ($stored === []) {
            return $default;
        }
        $stored = array_values(array_filter(array_map('strval', $stored)));
        $out = [];
        foreach ($stored as $key) {
            if (in_array($key, $default, true) && !in_array($key, $out, true)) {
                $out[] = $key;
            }
        }
        foreach ($default as $key) {
            if (!in_array($key, $out, true)) {
                $out[] = $key;
            }
        }

        return $out;
    }

    /**
     * Карточки пресетов для мастера создания (с демо-цифрами и списком разделов).
     *
     * @return list<array{
     *   key:string,
     *   title:string,
     *   lead:string,
     *   for_whom:string,
     *   demo:array{kpis:list<array{label:string,value:string,delta:?string}>,bullets:list<string>},
     *   sections:list<string>
     * }>
     */
    public static function presetCards(): array
    {
        $demos = [
            'seo_only' => [
                'title' => 'Только SEO',
                'lead' => 'Органика, позиции и работы — без рекламных блоков.',
                'for_whom' => 'Когда ведёте SEO без Директа/Ads в отчёте.',
                'demo' => [
                    'kpis' => [
                        ['label' => 'Визиты', 'value' => '12 480', 'delta' => '+8%'],
                        ['label' => 'TOP-10', 'value' => '42', 'delta' => '+5'],
                        ['label' => 'Цели', 'value' => '186', 'delta' => '+12%'],
                    ],
                    'bullets' => [
                        'Трафик вырос за счёт поиска',
                        '↑ 18 запросов в TOP-10',
                        'Приоритет: усилить посадочные из risk-листа',
                    ],
                ],
            ],
            'seo_ads' => [
                'title' => 'SEO + реклама',
                'lead' => 'SEO-ядро плюс Директ, Google Ads и поисковые консоли.',
                'for_whom' => 'Когда клиенту нужен контекст вместе с органикой.',
                'demo' => [
                    'kpis' => [
                        ['label' => 'Визиты', 'value' => '28 910', 'delta' => '+4%'],
                        ['label' => 'Реклама', 'value' => '9 240', 'delta' => '−3%'],
                        ['label' => 'CR рекламы', 'value' => '2,1%', 'delta' => '+0,3'],
                    ],
                    'bullets' => [
                        'Органика стабильна, Директ просел по отказам',
                        'GSC: рост кликов по 12 запросам',
                        'Что поправить: посадочные из Ads с высоким bounce',
                    ],
                ],
            ],
            'complex' => [
                'title' => 'Комплексный',
                'lead' => 'Все блоки: SEO, реклама, соцсети, продажи, звонки, модули Titlo.',
                'for_whom' => 'Полный отчёт агентства «под ключ» для крупного клиента.',
                'demo' => [
                    'kpis' => [
                        ['label' => 'Выручка', 'value' => '1,8 млн', 'delta' => '+11%'],
                        ['label' => 'Звонки', 'value' => '94', 'delta' => '+7'],
                        ['label' => 'ER VK', 'value' => '4,2%', 'delta' => '+0,5'],
                    ],
                    'bullets' => [
                        'Электронная коммерция и звонки в одном отчёте с SEO',
                        'Реклама VK + сообщество: охват и топ-посты',
                        'Инсайты + аудит + чеклист за период',
                    ],
                ],
            ],
        ];

        $out = [];
        foreach ($demos as $key => $meta) {
            $toggles = self::togglesForPreset($key);
            $sections = [];
            foreach (self::all() as $sectionKey => $sectionMeta) {
                if (empty($toggles[$sectionKey]) || $sectionKey === 'cover') {
                    continue;
                }
                $sections[] = (string) $sectionMeta['title'];
            }
            $out[] = [
                'key' => $key,
                'title' => $meta['title'],
                'lead' => $meta['lead'],
                'for_whom' => $meta['for_whom'],
                'demo' => $meta['demo'],
                'sections' => $sections,
                'sections_count' => count($sections),
            ];
        }

        return $out;
    }

    /**
     * @return array<string,bool>
     */
    public static function togglesForPreset(string $preset): array
    {
        $all = self::all();
        $toggles = [];
        foreach ($all as $key => $meta) {
            $toggles[$key] = false;
        }

        if ($preset === 'full' || $preset === 'complex') {
            foreach ($toggles as $key => $_v) {
                $toggles[$key] = true;
            }

            return $toggles;
        }

        if ($preset === 'seo_ads') {
            foreach ($all as $key => $meta) {
                $toggles[$key] = !empty($meta['mvp'])
                    || in_array($key, ['direct', 'google_ads', 'gsc', 'webmaster'], true);
            }

            return $toggles;
        }

        // mvp / seo_only
        foreach ($all as $key => $meta) {
            $toggles[$key] = !empty($meta['mvp']);
        }

        return $toggles;
    }

    /**
     * Секция видна клиенту, если включена менеджером и источник не «мёртвый».
     */
    public static function visibleForClient(bool $enabled, string $sourceStatus): bool
    {
        if (!$enabled) {
            return false;
        }

        return !in_array($sourceStatus, [
            self::SOURCE_STATUS_NOT_CONNECTED,
            self::SOURCE_STATUS_ERROR,
            self::SOURCE_STATUS_EMPTY,
        ], true);
    }
}
