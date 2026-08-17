<?php

namespace App\Services\SeoReports;

use App\SeoReports\SeoReport;
use App\SeoReports\SeoReportBrandColor;
use App\SeoReports\SeoReportKpiGoals;
use App\SeoReports\SeoReportProject;
use App\SeoReports\SeoReportSectionRegistry;
use App\SeoReports\SeoReportTemplate;
use Carbon\Carbon;

/**
 * Полный демо-снимок HTML-отчёта для пресетов мастера (без записи в БД).
 */
class SeoReportPresetDemoFactory
{
    /**
     * @return array{project:SeoReportProject,report:SeoReport,snapshot:array<string,mixed>,sections:list<array<string,mixed>>}
     */
    public function make(string $preset): array
    {
        if (!in_array($preset, ['seo_only', 'seo_ads', 'complex'], true)) {
            $preset = 'seo_only';
        }

        $from = Carbon::today()->subMonthNoOverflow()->startOfMonth();
        $to = $from->copy()->endOfMonth()->startOfDay();
        $cFrom = $from->copy()->subMonthNoOverflow()->startOfMonth();
        $cTo = $from->copy()->subMonthNoOverflow()->endOfMonth()->startOfDay();

        $toggles = SeoReportSectionRegistry::togglesForPreset($preset);
        $project = new SeoReportProject([
            'id' => 0,
            'user_id' => 0,
            'domain' => 'demo-shop.titlo.ru',
            'title' => 'Демо · ' . $this->presetTitle($preset),
            'status' => 'active',
            'section_toggles' => $toggles,
            'settings_json' => [
                'kpi_goals' => [
                    'visits' => ['enabled' => true, 'target' => 15000],
                    'users' => ['enabled' => true, 'target' => 9000],
                    'top10' => ['enabled' => true, 'target' => 40],
                    'conversions' => ['enabled' => true, 'target' => 200],
                ],
            ],
            'agency_name' => 'Агентство Titlo',
            'agency_address' => 'Москва',
            'agency_email' => 'hello@titlo.ru',
            'agency_phone' => '+7 (495) 000-00-00',
            'brand_color' => '#1d4ed8',
            'manager_name' => 'Анна Менеджерова',
            'manager_phone' => '+7 (999) 123-45-67',
            'manager_email' => 'anna@agency.example',
            'metrika_counter_id' => 48001034,
            'monitoring_project_id' => 744,
        ]);

        $report = new SeoReport([
            'id' => 0,
            'project_id' => 0,
            'status' => SeoReport::STATUS_READY,
            'period_from' => $from,
            'period_to' => $to,
            'compare_from' => $cFrom,
            'compare_to' => $cTo,
            'summary_text' => $this->summaryFor($preset),
            'work_done_text' => "• Оптимизация title/description на 18 посадочных\n• Расширение семантики TOP-20 (+42 фразы)\n• Правки сниппетов по выгрузке GSC",
            'work_plan_text' => "• Усилить быстрые победы (позиции 8–20)\n• Пересобрать коммерческие посадочные из risk-листа\n• Проверить конверсии с рекламы и соцсетей",
            'generated_at' => now(),
            'comments_json' => [
                'workflow_status' => 'client',
                'recommendations' => "P1: усилить упавшие запросы\nP2: снизить отказы на /services",
            ],
            'public_token' => 'demo',
        ]);
        $report->setRelation('project', $project);

        $snapshot = $this->snapshot($project, $report, $preset);
        $sections = $this->sections($toggles);

        return [
            'project' => $project,
            'report' => $report,
            'snapshot' => $snapshot,
            'sections' => $sections,
            'preset' => $preset,
            'preset_title' => $this->presetTitle($preset),
        ];
    }

    public function presetTitle(string $preset): string
    {
        $map = [
            'seo_only' => 'Только SEO',
            'seo_ads' => 'SEO + реклама',
            'complex' => 'Комплексный',
        ];

        return $map[$preset] ?? $preset;
    }

    /**
     * Демо HTML по живому шаблону: секции, KPI, брендинг и менеджер из шаблона.
     *
     * @return array{project:SeoReportProject,report:SeoReport,snapshot:array<string,mixed>,sections:list<array<string,mixed>>,preset:string,preset_title:string,template_id:int}
     */
    public function makeFromTemplate(SeoReportTemplate $template): array
    {
        $toggles = $template->resolvedSectionToggles();
        $settings = $template->reportSettings();
        // Полный снимок: у включённых в шаблон разделов не должно быть пустых KPI/таблиц.
        $demo = $this->make('complex');

        /** @var SeoReportProject $project */
        $project = $demo['project'];
        $project->fill([
            'title' => 'Демо · ' . $template->title,
            'section_toggles' => $toggles,
            'settings_json' => array_merge(
                is_array($project->settings_json) ? $project->settings_json : [],
                [
                    'kpi_goals' => $settings['kpi_goals'] ?? ($project->settings_json['kpi_goals'] ?? []),
                    'traffic_mode' => $settings['traffic_mode'] ?? 'all',
                    'auto_compare' => !empty($settings['auto_compare']),
                    'section_order' => $settings['section_order'] ?? SeoReportSectionRegistry::defaultOrder(),
                    'metric_toggles' => \App\SeoReports\SeoReportMetricRegistry::normalize(
                        $settings['metric_toggles'] ?? null
                    ),
                ]
            ),
            'agency_name' => $template->agency_name,
            'agency_address' => $template->agency_address,
            'agency_email' => $template->agency_email,
            'agency_phone' => $template->agency_phone,
            'agency_logo_path' => $template->agency_logo_path,
            'brand_color' => $template->brand_color ?: '#1d4ed8',
            'manager_name' => $template->manager_name,
            'manager_phone' => $template->manager_phone,
            'manager_email' => $template->manager_email,
            'manager_avatar_path' => $template->manager_avatar_path,
        ]);

        $sections = $this->sections($toggles, $settings);
        $snapshot = $demo['snapshot'];
        $snapshot['demo_preset'] = 'template:' . $template->id;
        $snapshot['demo_template_id'] = (int) $template->id;
        $snapshot['cover']['title'] = 'SEO-отчёт · ' . $project->domain;
        $snapshot['cover']['agency'] = [
            'name' => $template->agency_name,
            'address' => $template->agency_address,
            'email' => $template->agency_email,
            'phone' => $template->agency_phone,
            'logo_url' => $template->agencyLogoUrl(),
            'brand_color' => SeoReportBrandColor::normalize($template->brand_color ?: '#1d4ed8'),
        ];
        $snapshot['cover']['manager'] = [
            'name' => $template->manager_name,
            'email' => $template->manager_email,
            'phone' => $template->manager_phone,
            'avatar_url' => $template->managerAvatarUrl(),
        ];
        if (($settings['traffic_mode'] ?? 'all') === 'search_only' && isset($snapshot['traffic'])) {
            $snapshot['traffic']['mode'] = 'search_only';
        }
        $snapshot['kpi_goals'] = SeoReportKpiGoals::evaluate(
            SeoReportKpiGoals::fromSettings($project->settings_json),
            $snapshot
        );

        return [
            'project' => $project,
            'report' => $demo['report'],
            'snapshot' => $snapshot,
            'sections' => $sections,
            'preset' => 'template',
            'preset_title' => (string) $template->title,
            'template_id' => (int) $template->id,
        ];
    }

    private function summaryFor(string $preset): string
    {
        if ($preset === 'complex') {
            return "Комплексный отчёт по продвижению demo-shop.titlo.ru показывает результат сразу по нескольким направлениям: SEO, контекст, таргет, SMM, конверсии, звонки и продажи.\n\n"
                . "• SEO: выросли поисковый трафик, видимость и позиции по коммерческим запросам.\n"
                . "• Контекст: Директ и Google Ads дают заявки, но часть посадочных с высоким отказом.\n"
                . "• Таргет и SMM: VK Ads + сообщество усиливают охват и вовлечённость (ER 4,2%).\n"
                . "• Продажи: ecommerce +11% выручки, звонки стабильны.\n\n"
                . "Главный вывод: рост складывается из связки каналов. Следующий фокус — посадочные из рекламы и risk-лист позиций.";
        }
        if ($preset === 'seo_ads') {
            return "Отчёт SEO + реклама для demo-shop.titlo.ru: органика держит рост, контекст требует точечных правок.\n\n"
                . "• Поисковый трафик и TOP-10 растут.\n"
                . "• Директ: отказы выше нормы — проверить соответствие объявлений и посадочных.\n"
                . "• Google Ads и GSC дают дополнительные клики по коммерческим запросам.\n\n"
                . "Фокус следующего месяца — снизить bounce в рекламе и усилить быстрые победы 8–20.";
        }

        return "SEO-отчёт по demo-shop.titlo.ru за период: органика, позиции и выполненные работы.\n\n"
            . "• Визиты +8,3%, поисковый канал — основной драйвер роста.\n"
            . "• В TOP-10 сейчас 42 запроса (+5).\n"
            . "• Приоритет: усилить посадочные из risk-листа и быстрые победы на позициях 8–20.";
    }

    /**
     * @param array<string,bool> $toggles
     * @param array<string,mixed>|null $settings
     * @return list<array<string,mixed>>
     */
    private function sections(array $toggles, ?array $settings = null): array
    {
        $catalog = SeoReportSectionRegistry::all();
        $out = [];
        foreach (SeoReportSectionRegistry::orderedKeys($settings) as $key) {
            if (empty($toggles[$key])) {
                continue;
            }
            $meta = $catalog[$key] ?? null;
            if (!$meta) {
                continue;
            }
            $out[] = [
                'key' => $key,
                'title' => $meta['title'],
                'group' => $meta['group'],
                'source' => $meta['source'],
                'enabled' => true,
                'source_status' => SeoReportSectionRegistry::SOURCE_STATUS_OK,
                'client_visible' => true,
            ];
        }

        return $out;
    }

    /**
     * @return array<string,mixed>
     */
    private function snapshot(SeoReportProject $project, SeoReport $report, string $preset): array
    {
        $series = [];
        $searchSeries = [];
        $day = optional($report->period_from)->copy() ?: Carbon::today()->startOfMonth();
        $end = optional($report->period_to)->copy() ?: Carbon::today();
        $i = 0;
        while ($day->lte($end) && $i < 31) {
            $series[$day->format('Y-m-d')] = 120 + ($i % 7) * 18 + ($i * 3);
            $searchSeries[$day->format('Y-m-d')] = 70 + ($i % 7) * 12 + ($i * 2);
            $day->addDay();
            $i++;
        }

        $snap = [
            'generated_at' => now()->toIso8601String(),
            'is_demo' => true,
            'demo_preset' => $preset,
            'quality' => 'full',
            'requires_publish' => false,
            'published_at' => now()->toIso8601String(),
            'cover' => [
                'title' => 'SEO-отчёт · ' . $project->domain,
                'domain' => $project->domain,
                'period_label' => optional($report->period_from)->format('d.m.Y')
                    . ' — ' . optional($report->period_to)->format('d.m.Y'),
                'compare_label' => optional($report->compare_from)->format('d.m.Y')
                    . ' — ' . optional($report->compare_to)->format('d.m.Y'),
                'agency' => [
                    'name' => $project->agency_name,
                    'brand_color' => SeoReportBrandColor::normalize($project->brand_color),
                    'email' => $project->agency_email,
                    'phone' => $project->agency_phone,
                ],
                'manager' => [
                    'name' => $project->manager_name,
                    'email' => $project->manager_email,
                    'phone' => $project->manager_phone,
                ],
                'data_as_of' => now()->toIso8601String(),
            ],
            'progress' => [
                'metrika' => 'ok',
                'monitoring' => 'ok',
                'conversions' => 'ok',
            ],
            'traffic' => [
                'mode' => 'all',
                'kpis' => [
                    'visits' => ['value' => 12480, 'prev' => 11520, 'delta_pct' => 8.3],
                    'users' => ['value' => 9820, 'prev' => 9250, 'delta_pct' => 6.1],
                    'pageviews' => ['value' => 28600, 'prev' => 26100, 'delta_pct' => 9.6],
                    'bounce_rate' => ['value' => 34.2, 'prev' => 36.1, 'delta_pct' => -5.3],
                    'page_depth' => ['value' => 2.4, 'prev' => 2.3, 'delta_pct' => 4.3],
                    'avg_visit_duration' => ['value' => 145, 'prev' => 140, 'delta_pct' => 3.6],
                ],
                'series_users' => $series,
                'channels' => [
                    ['name' => 'Поисковые системы', 'visits' => 7200, 'visits_prev' => 6600, 'users' => 6100, 'bounce_rate' => 28.4, 'page_depth' => 2.6, 'avg_visit_duration' => 168],
                    ['name' => 'Прямые заходы', 'visits' => 2100, 'visits_prev' => 2050, 'users' => 1780, 'bounce_rate' => 41.2, 'page_depth' => 2.1, 'avg_visit_duration' => 132],
                    ['name' => 'Социальные сети', 'visits' => 980, 'visits_prev' => 870, 'users' => 820, 'bounce_rate' => 52.8, 'page_depth' => 1.7, 'avg_visit_duration' => 95],
                    ['name' => 'Реклама', 'visits' => 1640, 'visits_prev' => 1720, 'users' => 1390, 'bounce_rate' => 38.6, 'page_depth' => 2.0, 'avg_visit_duration' => 118],
                ],
                'sources' => [
                    ['name' => 'yandex / organic', 'visits' => 4800, 'users' => 4100],
                    ['name' => 'google / organic', 'visits' => 2400, 'users' => 2000],
                    ['name' => '(direct)', 'visits' => 2100, 'users' => 1780],
                    ['name' => 'yandex / cpc', 'visits' => 920, 'users' => 780],
                    ['name' => 'google / cpc', 'visits' => 720, 'users' => 610],
                    ['name' => 'vk.com / social', 'visits' => 540, 'users' => 460],
                ],
                'channel_months' => [
                    ['month' => optional($report->period_from)->copy()->subMonthsNoOverflow(2)->format('Y-m'), 'channels' => [['name' => 'Поисковые системы', 'visits' => 6100]]],
                    ['month' => optional($report->period_from)->copy()->subMonthsNoOverflow(1)->format('Y-m'), 'channels' => [['name' => 'Поисковые системы', 'visits' => 6600]]],
                    ['month' => optional($report->period_from)->format('Y-m'), 'channels' => [['name' => 'Поисковые системы', 'visits' => 7200]]],
                ],
                'devices' => [
                    ['name' => 'Смартфоны', 'visits' => 7800, 'users' => 6400, 'bounce_rate' => 38.5, 'page_depth' => 2.1, 'avg_visit_duration' => 118],
                    ['name' => 'ПК', 'visits' => 3900, 'users' => 2900, 'bounce_rate' => 26.2, 'page_depth' => 2.9, 'avg_visit_duration' => 196],
                    ['name' => 'Планшеты', 'visits' => 780, 'users' => 520, 'bounce_rate' => 33.8, 'page_depth' => 2.3, 'avg_visit_duration' => 142],
                ],
                'geo' => [
                    ['name' => 'Москва', 'visits' => 4200, 'users' => 3400, 'bounce_rate' => 31.2, 'page_depth' => 2.5, 'avg_visit_duration' => 158],
                    ['name' => 'Санкт-Петербург', 'visits' => 1800, 'users' => 1450, 'bounce_rate' => 33.8, 'page_depth' => 2.3, 'avg_visit_duration' => 149],
                    ['name' => 'Казань', 'visits' => 640, 'users' => 510, 'bounce_rate' => 36.4, 'page_depth' => 2.1, 'avg_visit_duration' => 131],
                    ['name' => 'Екатеринбург', 'visits' => 520, 'users' => 410, 'bounce_rate' => 35.1, 'page_depth' => 2.2, 'avg_visit_duration' => 136],
                    ['name' => 'Новосибирск', 'visits' => 410, 'users' => 330, 'bounce_rate' => 37.0, 'page_depth' => 2.0, 'avg_visit_duration' => 124],
                ],
                'landings' => [
                    ['name' => '/', 'visits' => 3200, 'visits_delta_pct' => 12.0, 'bounce_rate' => 29.4],
                    ['name' => '/services', 'visits' => 1450, 'visits_delta_pct' => 28.0, 'bounce_rate' => 24.8],
                    ['name' => '/prices', 'visits' => 920, 'visits_delta_pct' => -4.0, 'bounce_rate' => 41.2],
                    ['name' => '/contacts', 'visits' => 610, 'visits_delta_pct' => 6.5, 'bounce_rate' => 22.1],
                ],
                'landings_search' => [
                    ['name' => '/', 'visits' => 2100],
                    ['name' => '/services', 'visits' => 980],
                    ['name' => '/prices', 'visits' => 640],
                    ['name' => '/blog/windows', 'visits' => 310],
                ],
                'landings_social' => [
                    ['name' => '/promo', 'visits' => 420],
                    ['name' => '/blog/case', 'visits' => 210],
                    ['name' => '/services', 'visits' => 180],
                ],
                'social' => [
                    'kpis' => [
                        'visits' => ['value' => 980, 'prev' => 870, 'delta_pct' => 12.6],
                        'users' => ['value' => 820, 'prev' => 740, 'delta_pct' => 10.8],
                        'bounce_rate' => ['value' => 52.8, 'prev' => 55.1, 'delta_pct' => -4.2],
                        'page_depth' => ['value' => 1.7, 'prev' => 1.6, 'delta_pct' => 6.3],
                        'avg_visit_duration' => ['value' => 95, 'prev' => 88, 'delta_pct' => 8.0],
                    ],
                ],
                'search' => [
                    'kpis' => [
                        'visits' => ['value' => 7200, 'prev' => 6600, 'delta_pct' => 9.1],
                        'users' => ['value' => 6100, 'prev' => 5680, 'delta_pct' => 7.4],
                        'pageviews' => ['value' => 17200, 'prev' => 15400, 'delta_pct' => 11.7],
                        'bounce_rate' => ['value' => 28.4, 'prev' => 30.1, 'delta_pct' => -5.6],
                        'page_depth' => ['value' => 2.6, 'prev' => 2.5, 'delta_pct' => 4.0],
                        'avg_visit_duration' => ['value' => 168, 'prev' => 160, 'delta_pct' => 5.0],
                    ],
                    'series_visits' => $searchSeries,
                    'engines' => [
                        ['name' => 'Яндекс', 'visits' => 4800, 'visits_prev' => 4400, 'visits_delta_pct' => 9.1, 'bounce_rate' => 27.2],
                        ['name' => 'Google', 'visits' => 2100, 'visits_prev' => 1950, 'visits_delta_pct' => 7.7, 'bounce_rate' => 30.8],
                        ['name' => 'Другие', 'visits' => 300, 'visits_prev' => 250, 'visits_delta_pct' => 20.0, 'bounce_rate' => 34.5],
                    ],
                ],
                'auto_comment' => 'Поисковый трафик растёт, рекламный канал чуть просел — смотрите блок рекламы.',
            ],
            'positions' => [
                'summary' => [
                    'top3' => 12,
                    'top10' => 42,
                    'top30' => 120,
                    'top100' => 210,
                    'diff_top10' => '+5',
                ],
                'dynamics' => [
                    'improved' => 34,
                    'unchanged' => 80,
                    'worsened' => 18,
                    'pairs' => 2,
                    'date_from' => optional($report->compare_to)->format('Y-m-d'),
                    'date_to' => optional($report->period_to)->format('Y-m-d'),
                ],
                'phrases' => [
                    'improved' => [
                        [
                            'query' => 'купить окна москва',
                            'engine' => 'yandex',
                            'pos_from' => 14,
                            'pos_to' => 8,
                            'delta' => -6,
                            'url' => 'https://demo-shop.titlo.ru/services',
                        ],
                        [
                            'query' => 'окна пвх цена',
                            'engine' => 'google',
                            'pos_from' => 22,
                            'pos_to' => 11,
                            'delta' => -11,
                            'url' => 'https://demo-shop.titlo.ru/prices',
                        ],
                        [
                            'query' => 'замер окон бесплатно',
                            'engine' => 'yandex',
                            'pos_from' => 17,
                            'pos_to' => 9,
                            'delta' => -8,
                            'url' => 'https://demo-shop.titlo.ru/contacts',
                        ],
                    ],
                    'worsened' => [
                        [
                            'query' => 'пластиковые окна',
                            'engine' => 'google',
                            'pos_from' => 9,
                            'pos_to' => 15,
                            'delta' => 6,
                            'url' => 'https://demo-shop.titlo.ru/',
                        ],
                        [
                            'query' => 'окна недорого',
                            'engine' => 'yandex',
                            'pos_from' => 11,
                            'pos_to' => 18,
                            'delta' => 7,
                            'url' => 'https://demo-shop.titlo.ru/prices',
                        ],
                    ],
                ],
                'top_baskets' => [
                    ['label' => 'TOP-3', 'value' => 12, 'diff' => '+2'],
                    ['label' => 'TOP-10', 'value' => 42, 'diff' => '+5'],
                    ['label' => 'TOP-30', 'value' => 120, 'diff' => '+11'],
                ],
                'visibility_by_engine' => [
                    ['engine' => 'yandex', 'region' => 'Москва', 'top10' => 38, 'words' => 180, 'pct' => 21.1],
                    ['engine' => 'google', 'region' => 'Россия', 'top10' => 29, 'words' => 160, 'pct' => 18.1],
                ],
                'visibility_series' => [
                    ['date' => optional($report->period_from)->format('Y-m-d'), 'pct' => 18.4, 'top10' => 33, 'words' => 180],
                    ['date' => optional($report->period_from)->copy()->addDays(14)->format('Y-m-d'), 'pct' => 20.0, 'top10' => 36, 'words' => 180],
                    ['date' => optional($report->period_to)->format('Y-m-d'), 'pct' => 21.1, 'top10' => 38, 'words' => 180],
                ],
                'by_engine' => [
                    ['engine' => 'yandex', 'region' => 'Москва', 'words' => 180, 'top10' => 38, 'top100' => 140],
                    ['engine' => 'google', 'region' => 'Россия', 'words' => 160, 'top10' => 29, 'top100' => 120],
                ],
                'quick_wins' => [
                    [
                        'query' => 'окна под ключ',
                        'engine' => 'yandex',
                        'pos_from' => 18,
                        'pos_to' => 12,
                        'delta' => -6,
                        'url' => 'https://demo-shop.titlo.ru/services',
                    ],
                    [
                        'query' => 'установка окон цена',
                        'engine' => 'google',
                        'pos_from' => 19,
                        'pos_to' => 15,
                        'delta' => -4,
                        'url' => 'https://demo-shop.titlo.ru/prices',
                    ],
                ],
                'risk' => [
                    [
                        'query' => 'ремонт окон',
                        'engine' => 'yandex',
                        'pos_from' => 7,
                        'pos_to' => 19,
                        'delta' => 12,
                        'url' => 'https://demo-shop.titlo.ru/repair',
                    ],
                ],
                'groups' => [
                    ['id' => 1, 'name' => 'Коммерция', 'words' => 80, 'top3' => 12, 'top10' => 28, 'top30' => 55, 'top100' => 72],
                    ['id' => 2, 'name' => 'Инфо', 'words' => 40, 'top3' => 6, 'top10' => 14, 'top30' => 26, 'top100' => 35],
                ],
                'competitors' => [
                    'count' => 2,
                    'urls' => ['competitor-a.example', 'competitor-b.example'],
                ],
                'note' => 'Демо-данные мониторинга позиций',
                'data_as_of' => now()->toIso8601String(),
            ],
            'conversions' => [
                'goals' => [[
                    'id' => 1,
                    'name' => 'Заявка',
                    'reaches' => ['value' => 186, 'prev' => 178, 'delta_pct' => 4.5],
                    'conversion_rate' => ['value' => 1.5, 'prev' => 1.5, 'delta_pct' => 0.0],
                    'cost_per_conversion' => 420,
                ]],
                'search_goals' => [[
                    'id' => 1,
                    'name' => 'Заявка',
                    'reaches' => 120,
                    'conversion_rate' => 1.7,
                ]],
                'social_goals' => [[
                    'id' => 1,
                    'name' => 'Заявка',
                    'reaches' => 12,
                    'conversion_rate' => 1.1,
                ]],
                'ad_goals' => [[
                    'id' => 1,
                    'name' => 'Заявка',
                    'reaches' => 54,
                    'conversion_rate' => 1.9,
                ]],
                'comment' => 'Конверсии растут вместе с поисковым трафиком.',
            ],
            'gsc' => [
                'source' => 'demo',
                'note' => 'Демо Google Search Console',
                'kpis' => ['clicks' => 4200, 'impressions' => 88000, 'ctr' => 4.8, 'position' => 12.4],
                'queries' => [
                    ['name' => 'купить окна', 'clicks' => 320, 'impressions' => 8200, 'ctr' => 3.9, 'position' => 8.2],
                    ['name' => 'окна пвх', 'clicks' => 210, 'impressions' => 6100, 'ctr' => 3.4, 'position' => 11.1],
                ],
                'pages' => [
                    ['name' => '/', 'clicks' => 900, 'impressions' => 18000],
                    ['name' => '/services', 'clicks' => 420, 'impressions' => 9000],
                ],
            ],
            'webmaster' => [
                'source' => 'demo',
                'note' => 'Демо Яндекс.Вебмастер',
                'kpis' => ['clicks' => 5100, 'impressions' => 96000, 'ctr' => 5.3, 'position' => 9.8],
                'queries' => [
                    ['name' => 'окна москва', 'clicks' => 410, 'impressions' => 7000, 'ctr' => 5.9, 'position' => 6.4],
                ],
                'pages' => [
                    ['name' => '/', 'clicks' => 1100, 'impressions' => 20000],
                ],
                'diagnostics' => [
                    ['code' => 'URL_ALERT_4XX', 'label' => 'Страницы отвечают 4xx', 'severity' => 'CRITICAL', 'state' => 'PRESENT', 'last_state_update' => now()->subDays(2)->toIso8601String()],
                    ['code' => 'NO_SITEMAPS', 'label' => 'Нет Sitemap', 'severity' => 'POSSIBLE_PROBLEM', 'state' => 'PRESENT', 'last_state_update' => now()->subDays(5)->toIso8601String()],
                ],
                'meta_duplicates' => [
                    ['code' => 'DUPLICATE_CONTENT_ATTRS', 'label' => 'Одинаковые title и Description', 'severity' => 'POSSIBLE_PROBLEM', 'state' => 'PRESENT'],
                    ['code' => 'DOCUMENTS_MISSING_DESCRIPTION', 'label' => 'Нет Description на многих страницах', 'severity' => 'POSSIBLE_PROBLEM', 'state' => 'PRESENT'],
                ],
                'filtered_pages' => [
                    'summary' => [
                        ['status' => 'LOW_QUALITY', 'label' => 'Малополезная / низкокачественная', 'count' => 18],
                        ['status' => 'DUPLICATE', 'label' => 'Дубль', 'count' => 7],
                        ['status' => 'NO_INDEX', 'label' => 'noindex', 'count' => 3],
                    ],
                    'low_quality' => [
                        ['url' => 'https://example.com/tag/old', 'title' => 'Старый тег', 'status' => 'LOW_QUALITY', 'status_label' => 'Малополезная / низкокачественная', 'event_date' => now()->subDays(3)->toIso8601String()],
                        ['url' => 'https://example.com/print/item', 'title' => 'Версия для печати', 'status' => 'LOW_QUALITY', 'status_label' => 'Малополезная / низкокачественная', 'event_date' => now()->subDays(8)->toIso8601String()],
                    ],
                    'samples' => [],
                ],
            ],
            'insights' => [
                'Визиты за период: 12 480 (+8,3% к прошлому периоду)',
                'Позиции: ↑34 / →80 / ↓18 запросов',
                'В TOP-10 сейчас: 42 (+5)',
            ],
            'anomalies' => [
                ['date' => optional($report->period_from)->copy()->addDays(12)->format('Y-m-d'), 'value' => 980, 'z' => 2.6, 'direction' => 'up'],
            ],
            'recommendations' => [
                ['priority' => 'P1', 'text' => 'Risk: «ремонт окон» упал с 7 до 19 — разобрать посадочную и выдачу.'],
                ['priority' => 'P2', 'text' => 'Быстрые победы: 2 запроса на 8–20 — усилить title/контент.'],
                ['priority' => 'P3', 'text' => 'Проверить конверсию посадочной /services при росте трафика.'],
            ],
            'scorecard' => [
                ['key' => 'visits', 'label' => 'Визиты', 'value' => '12 480', 'delta' => '+8,3%', 'delta_class' => 'is-up', 'tone' => 'green'],
                ['key' => 'top10', 'label' => 'TOP-10', 'value' => '42', 'delta' => '+5', 'delta_class' => 'is-up', 'tone' => 'green'],
                ['key' => 'conv', 'label' => 'Заявки', 'value' => '186', 'delta' => '+4,5%', 'delta_class' => 'is-up', 'tone' => 'yellow'],
            ],
            'work_facts' => [
                'Site Audit: средний балл 78, критичных 3',
                'SEO-чеклист: закрыто 12 задач за период',
                'Аптайм: 99,92%',
            ],
            'titlo_audit' => [
                'project_id' => 1,
                'crawl_id' => 1,
                'finished_at' => now()->toIso8601String(),
                'buckets' => [
                    'critical' => 3,
                    'other' => 7,
                    'warning' => 11,
                    'info' => 24,
                ],
                'note' => 'Демо Site Audit',
            ],
            'titlo_checklist' => [
                'project_id' => 1,
                'progress_done' => 42,
                'progress_total' => 58,
                'closed_in_period' => 12,
                'overdue' => 2,
                'note' => 'Демо SEO-чеклист',
            ],
            'work_done' => [
                'checklist_project_id' => 1,
                'from_checklist' => [
                    ['id' => 1, 'title' => 'Оптимизация title/description на посадочных', 'status' => 'done', 'done_at' => now()->subDays(10)->toDateString()],
                    ['id' => 2, 'title' => 'Расширение семантики TOP-20', 'status' => 'done', 'done_at' => now()->subDays(6)->toDateString()],
                ],
            ],
            'work_plan' => [
                'checklist_project_id' => 1,
                'from_checklist' => [
                    ['id' => 3, 'title' => 'Усилить быстрые победы (позиции 8–20)', 'status' => 'todo', 'due_at' => now()->addDays(5)->toDateString(), 'overdue' => false],
                    ['id' => 4, 'title' => 'Пересобрать коммерческие посадочные', 'status' => 'doing', 'due_at' => now()->addDays(12)->toDateString(), 'overdue' => false],
                ],
            ],
            'titlo_relevance' => [
                'project_id' => 1,
                'count_checks' => 4,
                'count_sites' => 12,
                'avg_points' => 72.4,
                'avg_position' => 9.8,
                'last_check' => now()->toDateString(),
                'note' => 'Демо релевантность',
            ],
            'titlo_uptime' => [
                'project_id' => 1,
                'uptime_percent' => 99.92,
                'broken' => false,
                'last_check' => now()->toDateTimeString(),
                'domain_days_left' => 214,
                'note' => 'Демо аптайм',
            ],
            'data_quality' => [
                'level' => 'full',
                'flags' => [],
                'generated_at' => now()->toIso8601String(),
            ],
        ];

        $snap['kpi_goals'] = SeoReportKpiGoals::evaluate(
            SeoReportKpiGoals::fromSettings($project->settings_json),
            $snap
        );

        if (in_array($preset, ['seo_ads', 'complex'], true)) {
            $adSeries = [];
            $adDay = optional($report->period_from)->copy() ?: Carbon::today()->startOfMonth();
            $adEnd = optional($report->period_to)->copy() ?: Carbon::today();
            $ai = 0;
            while ($adDay->lte($adEnd) && $ai < 31) {
                $adSeries[$adDay->format('Y-m-d')] = 18 + ($ai % 5) * 4 + ($ai % 3);
                $adDay->addDay();
                $ai++;
            }

            $snap['direct'] = [
                'source' => 'demo',
                'note' => 'Демо Яндекс.Директ (из среза Метрики)',
                'kpis' => [
                    'visits' => ['value' => 920, 'prev' => 950, 'delta_pct' => -3.2],
                    'users' => ['value' => 780, 'prev' => 797, 'delta_pct' => -2.1],
                    'bounce_rate' => ['value' => 41.0, 'prev' => 38.0, 'delta_pct' => 8.0],
                    'page_depth' => ['value' => 1.6, 'prev' => 1.7, 'delta_pct' => -4.0],
                    'avg_visit_duration' => ['value' => 98, 'prev' => 103, 'delta_pct' => -5.0],
                ],
                'spend' => [
                    'cost' => 86400,
                    'clicks' => 4120,
                    'impressions' => 186000,
                    'cpc' => 21.0,
                    'ctr' => 2.22,
                ],
                'series_visits' => $adSeries,
                'engines' => [
                    ['name' => 'Яндекс', 'visits' => 720, 'bounce_rate' => 39.5],
                    ['name' => 'РСЯ', 'visits' => 200, 'bounce_rate' => 46.2],
                ],
                'campaigns' => [
                    ['name' => 'Бренд · Москва', 'visits' => 340, 'bounce_rate' => 28.4],
                    ['name' => 'Общие · окна', 'visits' => 410, 'bounce_rate' => 44.8],
                    ['name' => 'Ретаргет', 'visits' => 170, 'bounce_rate' => 36.1],
                ],
                'platforms' => [
                    ['name' => 'Поиск', 'visits' => 620],
                    ['name' => 'Сети', 'visits' => 300],
                ],
                'phrases' => [
                    ['name' => 'купить окна', 'visits' => 120],
                    ['name' => 'окна пвх москва', 'visits' => 95],
                    ['name' => 'установка окон цена', 'visits' => 70],
                ],
                'landings' => [
                    ['name' => '/services', 'visits' => 310, 'bounce_rate' => 42.0],
                    ['name' => '/prices', 'visits' => 180, 'bounce_rate' => 48.5],
                    ['name' => '/', 'visits' => 140, 'bounce_rate' => 35.2],
                ],
                'notes' => [
                    'Высокий отказ в рекламном трафике — проверить посадочные и соответствие объявлений.',
                ],
            ];
            $snap['google_ads'] = [
                'source' => 'demo',
                'note' => 'Демо Google Ads',
                'kpis' => [
                    'visits' => ['value' => 720, 'prev' => 703, 'delta_pct' => 2.4],
                    'users' => ['value' => 610, 'prev' => 599, 'delta_pct' => 1.8],
                    'bounce_rate' => ['value' => 38.0, 'prev' => 38.4, 'delta_pct' => -1.0],
                    'page_depth' => ['value' => 1.8, 'prev' => 1.76, 'delta_pct' => 2.0],
                    'avg_visit_duration' => ['value' => 110, 'prev' => 109, 'delta_pct' => 1.0],
                ],
                'campaigns' => [
                    ['name' => 'Brand · RU', 'visits' => 280, 'users' => 240, 'bounce_rate' => 29.5],
                    ['name' => 'Generic · Windows', 'visits' => 440, 'users' => 370, 'bounce_rate' => 42.1],
                ],
                'landings' => [
                    ['name' => '/promo', 'visits' => 190, 'bounce_rate' => 36.8],
                    ['name' => '/services', 'visits' => 160, 'bounce_rate' => 40.2],
                ],
                'phrases' => [
                    ['name' => 'окна купить', 'visits' => 90],
                    ['name' => 'pvc windows moscow', 'visits' => 55],
                ],
                'conversions' => [
                    ['name' => 'Заявка', 'reaches' => 22, 'conversion_rate' => 3.1],
                    ['name' => 'Звонок', 'reaches' => 9, 'conversion_rate' => 1.3],
                ],
            ];
            $snap['progress']['direct'] = 'ok';
            $snap['progress']['google_ads'] = 'ok';
            $snap['progress']['gsc'] = 'ok';
            $snap['progress']['webmaster'] = 'ok';
        }

        if ($preset === 'complex') {
            $snap['vk_ads'] = [
                'source' => 'demo',
                'note' => 'Демо VK Реклама',
                'kpis' => [
                    'reach' => 42000,
                    'impressions' => 118000,
                    'clicks' => 2400,
                    'ctr' => 2.03,
                    'cpc' => 18.5,
                    'cpm' => 376,
                    'spend' => 44400,
                ],
                'campaigns' => [
                    ['name' => 'Лиды · Москва', 'impressions' => 70000, 'clicks' => 1500, 'ctr' => 2.1, 'spend' => 28000],
                    ['name' => 'Ретаргет', 'impressions' => 48000, 'clicks' => 900, 'ctr' => 1.9, 'spend' => 16400],
                ],
                'ads' => [
                    ['name' => 'Креатив A', 'impressions' => 40000, 'clicks' => 980, 'ctr' => 2.45],
                    ['name' => 'Креатив B', 'impressions' => 35000, 'clicks' => 720, 'ctr' => 2.06],
                ],
                'demography' => [
                    ['name' => 'ж 25–34', 'clicks' => 820, 'impressions' => 30000],
                    ['name' => 'м 25–34', 'clicks' => 740, 'impressions' => 28000],
                ],
            ];
            $snap['vk_smm'] = [
                'source' => 'demo',
                'note' => 'Демо сообщества VK',
                'kpis' => [
                    'subscribers' => 12400,
                    'reach' => 8600,
                    'impressions' => 42000,
                    'visitors' => 5100,
                    'likes' => 980,
                    'comments' => 140,
                    'shares' => 95,
                    'posts' => 18,
                    'er' => 4.2,
                ],
                'dynamics' => [
                    ['date' => optional($report->period_from)->format('Y-m-d'), 'subscribers' => 12100, 'reach' => 900, 'views' => 4200],
                    ['date' => optional($report->period_to)->format('Y-m-d'), 'subscribers' => 12400, 'reach' => 1100, 'views' => 5100],
                ],
                'engagement' => ['likes' => 980, 'comments' => 140, 'shares' => 95, 'er' => 4.2],
                'top_posts' => [
                    ['name' => 'Кейс: рост заявок на 18%', 'likes' => 210, 'comments' => 28, 'shares' => 19, 'views' => 6400],
                    ['name' => 'Акция на замер', 'likes' => 160, 'comments' => 22, 'shares' => 14, 'views' => 5200],
                ],
                'demography' => [
                    ['name' => 'ж 25–34', 'clicks' => 3200],
                    ['name' => 'м 25–34', 'clicks' => 2800],
                ],
                'post_stats' => [],
            ];
            $snap['ecommerce'] = [
                'available' => true,
                'users' => 4200,
                'purchases' => 186,
                'cr' => 4.4,
                'revenue' => 1840000,
                'aov' => 9892,
                'rpv' => 438,
                'note' => 'Демо ecommerce',
                'by_source' => [
                    ['name' => 'Поиск', 'purchases' => 110, 'revenue' => 980000],
                    ['name' => 'Реклама', 'purchases' => 54, 'revenue' => 520000],
                    ['name' => 'Соцсети', 'purchases' => 22, 'revenue' => 340000],
                ],
                'categories' => [
                    ['name' => 'Окна ПВХ', 'purchases' => 96, 'revenue' => 920000],
                    ['name' => 'Фурнитура', 'purchases' => 40, 'revenue' => 180000],
                ],
                'products' => [
                    ['name' => 'Окно стандарт', 'purchases' => 64, 'revenue' => 520000],
                    ['name' => 'Окно премиум', 'purchases' => 28, 'revenue' => 610000],
                ],
            ];
            $snap['calls'] = [
                'source' => 'demo',
                'note' => 'Демо коллтрекинг Метрики',
                'total' => 94,
                'first' => 61,
                'missed' => 8,
                'talk_avg' => 186,
                'hold_avg' => 22,
                'by_channel' => [
                    ['name' => 'Поиск', 'calls' => 48, 'missed' => 3],
                    ['name' => 'Реклама', 'calls' => 31, 'missed' => 4],
                    ['name' => 'Соцсети', 'calls' => 15, 'missed' => 1],
                ],
            ];
            $snap['traffic']['ecommerce'] = $snap['ecommerce'];
            $snap['progress']['vk_ads'] = 'ok';
            $snap['progress']['vk_smm'] = 'ok';
            $snap['progress']['ecommerce'] = 'ok';
            $snap['progress']['calls'] = 'ok';
            $snap['scorecard'][] = [
                'key' => 'revenue',
                'label' => 'Выручка',
                'value' => '1,8 млн',
                'delta' => '+11%',
                'delta_class' => 'is-up',
                'tone' => 'green',
            ];
        }

        return $snap;
    }
}
