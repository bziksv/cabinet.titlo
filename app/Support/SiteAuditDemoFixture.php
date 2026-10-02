<?php

namespace App\Support;

/**
 * Богатая демо-проверка Site Audit: 10–100 находок на КАЖДЫЙ код из каталога
 * (кроме external-модулей и virtual-сводок — сводки считаются из children).
 * HTML — ≥5 паттернов, один сквозной.
 */
class SiteAuditDemoFixture
{
    public const DOMAIN = 'demo-audit.titlo.ru';
    public const PROJECT_NAME = 'Демо: полный аудит (фикстура)';
    public const DEMO_VERSION = 42;
    public const SHARE_TOKEN = 'demo-site-audit-rich';

    /**
     * Все seedable-коды (не external / не virtual). severity — как в config/site_audit.php.
     *
     * @var array<string, string>
     */
    private const CODES = [
        'http_4xx' => 'critical',
        'http_5xx' => 'critical',
        'unreachable' => 'critical',
        'redirect' => 'warning',
        'redirect_chain_long' => 'other',
        'redirect_loop' => 'critical',
        'duplicate_title' => 'critical',
        'duplicate_description' => 'critical',
        'duplicate_content' => 'critical',
        'similar_pages' => 'important',
        'empty_title' => 'critical',
        'empty_description' => 'critical',
        'multiple_title_or_description' => 'critical',
        'noindex' => 'warning',
        'canonical_foreign' => 'other',
        'canonical_not_self' => 'warning',
        'canonical_empty' => 'info',
        'multiple_canonical' => 'warning',
        'page_too_large' => 'warning',
        'missing_h1' => 'warning',
        'multiple_h1' => 'critical',
        'thin_content' => 'warning',
        'title_too_short' => 'important',
        'title_too_long' => 'important',
        'description_too_short' => 'warning',
        'description_too_long' => 'warning',
        'title_equals_h1' => 'important',
        'title_equals_description' => 'important',
        'description_equals_h1' => 'warning',
        'h1_equals_h2' => 'warning',
        'heading_hierarchy' => 'important',
        'too_many_strong' => 'warning',
        'duplicate_links' => 'warning',
        'external_links' => 'info',
        'meta_spam' => 'warning',
        'h1_spam' => 'warning',
        'text_nausea' => 'warning',
        'text_bigram_spam' => 'warning',
        'text_trigram_spam' => 'warning',
        'no_unique_images' => 'info',
        'text_in_noindex' => 'warning',
        'images_without_alt' => 'warning',
        'robots_txt_error' => 'warning',
        'robots_txt_closed' => 'critical',
        'robots_blocked' => 'warning',
        'pages_with_iframe' => 'info',
        'mixed_content' => 'warning',
        'insecure_form' => 'critical',
        'bad_doctype' => 'info',
        'meta_nofollow' => 'warning',
        'links_nofollow' => 'info',
        'external_assets' => 'info',
        'soft_404' => 'other',
        'orphan_pages' => 'warning',
        'sitemap_missing' => 'important',
        'sitemap_error' => 'warning',
        'not_in_sitemap' => 'info',
        'sitemap_not_crawled' => 'info',
        'landing_not_in_sitemap' => 'warning',
        'landing_not_crawled' => 'warning',
        'landing_url_changed' => 'warning',
        'duplicate_url_variants' => 'other',
        'www_both_available' => 'critical',
        'http_https_both_available' => 'critical',
        'page_has_broken_links' => 'warning',
        'broken_internal_link' => 'critical',
        'page_has_broken_external_links' => 'important',
        'broken_external_link' => 'important',
        'page_has_bad_links' => 'warning',
        'lost_file' => 'warning',
        'adult_content' => 'warning',
        'negative_content' => 'warning',
        'word_repeat_in_sentence' => 'info',
        'landing_plagiarism_external' => 'warning',
        'landing_no_inbound_internal' => 'warning',
        'keyword_cannibalization' => 'warning',
        'ad_cannibalization' => 'warning',
        'serp_snippet_cannibalization' => 'warning',
        'landing_query_mismatch' => 'warning',
        'commercial_missing_contacts' => 'info',
        'commercial_missing_price' => 'info',
        'commercial_missing_cta' => 'info',
        'commercial_missing_delivery' => 'info',
        'commercial_missing_payment' => 'info',
        'commercial_missing_stock' => 'info',
        'commercial_missing_reviews' => 'info',
        'broken_image' => 'warning',
        'heavy_image' => 'info',
        'error_spike' => 'warning',
        'psi_mobile' => 'info',
        'psi_desktop' => 'info',
        'deep_pages' => 'info',
        'site_availability' => 'warning',
        'index_count_mismatch' => 'warning',
        'serp_snippets' => 'info',
        'serp_title_mismatch' => 'warning',
        'serp_snippet_source' => 'info',
        'probable_affiliate' => 'info',
        'no_outbound_internal' => 'info',
        'risky_query_params' => 'warning',
        'pagination_param' => 'info',
        'missing_hsts' => 'warning',
        'missing_csp' => 'info',
        'missing_x_frame_options' => 'info',
        'missing_referrer_policy' => 'info',
        'missing_x_content_type_options' => 'info',
        'missing_permissions_policy' => 'info',
        'missing_coop' => 'info',
        'missing_coep' => 'info',
        'missing_corp' => 'info',
        'missing_charset' => 'info',
    ];

    /**
     * @return array{
     *   project: array<string,mixed>,
     *   crawl: array<string,mixed>,
     *   pages: list<array<string,mixed>>,
     *   findings: list<array<string,mixed>>,
     *   stats: list<array<string,mixed>>,
     *   summary: array{pages:int,findings:int,codes:int}
     * }
     */
    public static function build(int $userId, ?string $now = null): array
    {
        $now = $now ?: date('Y-m-d H:i:s');
        $base = 'https://' . self::DOMAIN;
        $paths = self::pagePaths();
        $pages = [];
        foreach ($paths as $i => $path) {
            $url = $base . $path;
            $title = 'Демо страница ' . ($i + 1) . ' — ' . trim($path, '/');
            if ($title === 'Демо страница 1 — ') {
                $title = 'Демо-аудит — главная';
            }
            $pages[] = [
                'url' => $url,
                'url_hash' => hash('sha256', $url),
                'final_url' => $url,
                'status_code' => 200,
                'redirect_chain' => null,
                'size_bytes' => 8000 + ($i * 37) % 40000,
                'content_type' => 'text/html; charset=utf-8',
                'charset' => 'utf-8',
                'title' => $title,
                'title_hash' => hash('sha256', mb_strtolower($title)),
                'description' => 'Описание демо-страницы ' . ($i + 1) . ' для витрины аудита.',
                'description_hash' => hash('sha256', 'desc-' . $i),
                'h1' => 'H1 демо ' . ($i + 1),
                'h1_count' => 1,
                'h2_count' => 2 + ($i % 3),
                'canonical' => $url,
                'robots_meta' => null,
                'noindex' => 0,
                'word_count' => 120 + ($i * 11) % 800,
                'text_len' => 800 + ($i * 40) % 5000,
                'content_hash' => hash('sha256', 'content-' . $i),
                'content_unchanged' => 0,
                'simhash' => null,
                // Ссылки на /missing/… — чтобы в 4xx / битых была колонка «Страница со ссылкой».
                'out_links_json' => json_encode(self::demoOutLinks($base, $i), JSON_UNESCAPED_UNICODE),
                'img_srcs_json' => json_encode(self::demoImgSrcs($i), JSON_UNESCAPED_UNICODE),
                'asset_srcs_json' => null,
                'click_depth' => min(8, (int) floor($i / 15)),
                'discovered_via' => $i === 0 ? 'seed' : (($i % 5 === 0) ? 'sitemap' : 'link'),
                'discovered_from' => $i === 0 ? null : $base . '/',
                'img_count' => 2 + ($i % 5),
                'img_without_alt' => $i % 4,
                'unique_img_src_count' => 2,
                'strong_count' => $i % 7,
                'em_count' => 0,
                'nausea_classic' => 0,
                'nausea_academic' => 0,
                'top_word' => null,
                'top_word_count' => 0,
                'top_bigram' => null,
                'top_bigram_count' => 0,
                'top_trigram' => null,
                'top_trigram_count' => 0,
                'noindex_text_len' => 0,
                'html_storage_key' => null,
                'html_bytes_gz' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $urls = array_column($pages, 'url');
        $findings = [];
        $counts = [];
        $buckets = ['critical' => 0, 'other' => 0, 'important' => 0, 'warning' => 0, 'info' => 0];

        $push = static function (string $code, string $severity, string $url, ?array $meta) use (&$findings, &$counts, &$buckets, $now) {
            $findings[] = [
                'code' => $code,
                'severity' => $severity,
                'url' => $url,
                'url_hash' => hash('sha256', $url),
                'meta_json' => $meta === null ? null : json_encode($meta, JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $counts[$code] = ($counts[$code] ?? 0) + 1;
            if (isset($buckets[$severity])) {
                $buckets[$severity]++;
            }
        };

        // HTML: ≥5 паттернов
        $htmlPatterns = [
            ['message' => 'error parsing attribute name', 'n' => 72],
            ['message' => 'Unexpected end tag : ol', 'n' => 18],
            ['message' => 'Opening and ending tag mismatch: div and span', 'n' => 14],
            ['message' => 'Незакрытый HTML-комментарий <!--', 'n' => 12],
            ['message' => 'Несколько закрывающих тегов </body>', 'n' => 10],
            ['message' => 'error parsing attribute value', 'n' => 9],
            ['message' => 'Attribute ";" not allowed', 'n' => 8],
        ];
        $htmlOffset = 0;
        foreach ($htmlPatterns as $pi => $pat) {
            for ($j = 0; $j < $pat['n']; $j++) {
                $url = $urls[($htmlOffset + $j) % count($urls)];
                $line = 80 + ($pi * 40) + ($j % 30);
                $push('html_critical_errors', 'other', $url, [
                    'count' => 1 + ($j % 3),
                    'samples' => [
                        ['line' => $line, 'level' => 'error', 'message' => $pat['message']],
                    ],
                ]);
            }
            $htmlOffset += $pat['n'];
        }

        foreach (self::CODES as $code => $sev) {
            $n = self::countForCode($code);
            $brokenTarget = in_array($code, ['broken_internal_link', 'http_4xx'], true);
            for ($j = 0; $j < $n; $j++) {
                $url = $brokenTarget
                    ? ($base . '/missing/page-' . ($j + 1) . '/')
                    : $urls[$j % count($urls)];
                if ($code === 'risky_query_params') {
                    $url = self::riskyQueryDemoUrl($urls[$j % count($urls)], $j);
                }
                if ($code === 'ad_cannibalization') {
                    // URL слева = «лишняя» промо-страница, не случайный /catalog/item-N.
                    $promoPaths = [
                        '/promo/',
                        '/promo/offer-' . (($j % 15) + 1) . '/',
                        '/lp/sofa-' . (($j % 8) + 1) . '/',
                        '/offer/demo-' . (($j % 6) + 1) . '/',
                        '/actions/sale-' . (($j % 5) + 1) . '/',
                        '/ppc/divan-' . (($j % 4) + 1) . '/',
                    ];
                    $url = $base . $promoPaths[$j % count($promoPaths)];
                }
                if ($code === 'sitemap_missing'
                    || $code === 'www_both_available'
                    || $code === 'http_https_both_available'
                    || $code === 'site_availability'
                    || $code === 'error_spike'
                ) {
                    $url = $base . '/';
                }
                if ($code === 'robots_txt_closed' || $code === 'robots_txt_error') {
                    $url = $base . '/robots.txt';
                }
                if ($code === 'sitemap_error') {
                    $smPaths = ['/sitemap.xml', '/sitemap_index.xml', '/sitemap-news.xml'];
                    $url = $base . $smPaths[$j % count($smPaths)];
                }
                $push($code, $sev, $url, self::metaFor($code, $base, $url, $j));
            }
        }

        // Отчёт «Страницы с rel=canonical» берёт счётчик из counts_json, строки — из pages.
        $counts['pages_with_canonical'] = count($pages);

        $project = [
            'user_id' => $userId,
            'team_id' => null,
            'domain' => self::DOMAIN,
            'name' => self::PROJECT_NAME,
            'settings_json' => json_encode([
                'demo' => true,
                'demo_version' => self::DEMO_VERSION,
                'note' => 'Синтетическая проверка: находки на каждый код каталога',
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $crawl = [
            'user_id' => $userId,
            'status' => 'done',
            'pages_total' => count($pages),
            'pages_fetched' => count($pages),
            'pages_limit' => 500,
            'buckets_json' => json_encode($buckets, JSON_UNESCAPED_UNICODE),
            'counts_json' => json_encode($counts, JSON_UNESCAPED_UNICODE),
            'progress_json' => json_encode([
                'demo' => true,
                'demo_version' => self::DEMO_VERSION,
                'images_total' => array_sum(array_map(static function ($p) {
                    return (int) ($p['img_count'] ?? 0);
                }, $pages)),
                'robots' => ['ok' => true],
                'sitemap' => ['found' => true, 'url_count' => count($urls)],
                // Чтобы в дереве не было «не было» у probe-вкладок
                'psi' => ['ran' => true, 'ok' => true, 'checked' => 20],
                'serp_snippets' => [
                    'ran' => true,
                    'ok' => true,
                    'engines' => ['yandex', 'google'],
                    'sampled' => 30,
                    'max_urls' => 30,
                    'from_batch' => true,
                ],
                'serp_url_batch' => [
                    'skipped' => false,
                    'max_urls' => 30,
                    'engines' => ['yandex', 'google'],
                    'errors' => 0,
                    'sampled' => 30,
                    'rows' => [],
                ],
                'serp_cannibalization' => ['ran' => true, 'ok' => true],
                'serp_index' => [
                    'ran' => true,
                    'ok' => true,
                    'deep' => [
                        'source' => 'demo',
                        'serp_count' => 80,
                        'matched' => 50,
                        'crawl_count' => count($pages),
                        'missing_in_index' => (int) ($counts['index_count_mismatch'] ?? 0),
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE),
            'error' => null,
            'save_html' => '0',
            'share_token' => self::SHARE_TOKEN,
            'share_enabled_at' => $now,
            'share_white_label' => 0,
            'share_brand_name' => null,
            'share_brand_url' => null,
            'share_brand_logo' => null,
            'started_at' => date('Y-m-d H:i:s', strtotime($now) - 900),
            'finished_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $stats = [];
        foreach ($buckets as $bucket => $value) {
            $stats[] = [
                'bucket' => $bucket,
                'value' => $value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return [
            'project' => $project,
            'crawl' => $crawl,
            'pages' => $pages,
            'findings' => $findings,
            'stats' => $stats,
            'summary' => [
                'pages' => count($pages),
                'findings' => count($findings),
                'codes' => count($counts),
            ],
        ];
    }

    /** Стабильно 10…100 на код; SERP-выборки — как боевой лимит; site-level — одна запись. */
    private static function countForCode(string $code): int
    {
        if (in_array($code, [
            'sitemap_missing',
            'robots_txt_closed',
            'robots_txt_error',
            'www_both_available',
            'http_https_both_available',
            'site_availability',
            'error_spike',
        ], true)) {
            return 1;
        }
        if (in_array($code, ['serp_snippets', 'serp_title_mismatch', 'serp_snippet_source'], true)) {
            return 30;
        }
        if ($code === 'sitemap_error') {
            return 3;
        }
        $n = 10 + (abs(crc32($code)) % 91);

        return min(100, max(10, $n));
    }

    /**
     * @return list<string>
     */
    private static function pagePaths(): array
    {
        $paths = ['/', '/catalog/', '/about/', '/contacts/', '/delivery/', '/cart/', '/blog/'];
        for ($i = 1; $i <= 40; $i++) {
            $paths[] = '/catalog/item-' . $i . '/';
        }
        for ($i = 1; $i <= 35; $i++) {
            $paths[] = '/blog/post-' . $i . '/';
        }
        for ($i = 1; $i <= 25; $i++) {
            $paths[] = '/info/page-' . $i . '/';
        }
        for ($i = 1; $i <= 15; $i++) {
            $paths[] = '/promo/offer-' . $i . '/';
        }

        return $paths;
    }

    /**
     * Другие URL той же демо-группы дублей (как в emitDuplicates → peer_urls).
     *
     * @return list<string>
     */
    private static function demoPeerUrls(string $base, int $j, int $groupSize): array
    {
        $paths = self::pagePaths();
        $n = count($paths);
        $groupId = (int) floor($j / $groupSize);
        $selfIdx = $j % $n;
        $peers = [];
        for ($k = 0; $k < $groupSize; $k++) {
            $idx = ($groupId * $groupSize + $k) % $n;
            if ($idx === $selfIdx) {
                continue;
            }
            $peers[] = $base . $paths[$idx];
        }

        return $peers;
    }

    /**
     * Исходящие ссылки демо-страницы: меню + битые /missing/ для колонки «Страница со ссылкой».
     *
     * @return list<string>
     */
    private static function demoOutLinks(string $base, int $pageIndex): array
    {
        $out = [
            $base . '/',
            $base . '/catalog/',
            $base . '/blog/',
            $base . '/about/',
        ];
        // Покрываем missing/page-1…100 (http_4xx / broken_internal_link в демо).
        $a = 1 + ($pageIndex % 100);
        $b = 1 + (($pageIndex * 3 + 7) % 100);
        $out[] = $base . '/missing/page-' . $a . '/';
        if ($b !== $a) {
            $out[] = $base . '/missing/page-' . $b . '/';
        }
        // Пара «живых» внутренних для глубины/перелинковки.
        if ($pageIndex % 2 === 0) {
            $out[] = $base . '/contacts/';
        } else {
            $out[] = $base . '/delivery/';
        }

        return array_values(array_unique($out));
    }

    /**
     * Картинки страницы в формате SiteAuditImageItem (с has_alt).
     *
     * @return list<array{src:string,alt:?string,has_alt:bool,width:?int,height:?int}>
     */
    private static function demoImgSrcs(int $pageIndex): array
    {
        $without = $pageIndex % 4;
        $total = 2 + ($pageIndex % 5);
        $items = [];
        for ($k = 0; $k < $total; $k++) {
            $id = 10 + (($pageIndex * 3 + $k * 7) % 80);
            $hasAlt = $k >= $without;
            $items[] = [
                'src' => 'https://picsum.photos/id/' . $id . '/800/600',
                'alt' => $hasAlt ? ('Демо фото ' . ($k + 1)) : '',
                'has_alt' => $hasAlt,
                'width' => 800,
                'height' => 600,
            ];
        }

        return $items;
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function metaFor(string $code, string $base, string $url, int $j): ?array
    {
        switch ($code) {
            case 'broken_internal_link':
            case 'broken_external_link':
            case 'http_4xx':
                $fromPages = [
                    $base . '/',
                    $base . '/catalog/',
                    $base . '/blog/',
                    $base . '/about/',
                    $base . '/contacts/',
                ];
                $from = $fromPages[$j % count($fromPages)];
                $extra = $fromPages[($j + 2) % count($fromPages)];
                $refs = array_values(array_unique([$from, $extra]));

                return [
                    'status' => $code === 'broken_external_link' ? 404 : 404,
                    'from' => $from,
                    'referrers' => $refs,
                    'referrer_count' => count($refs),
                    'discovered_via' => 'link',
                    'discovered_from' => $from,
                ];
            case 'http_5xx':
                return [
                    'status' => 500 + ($j % 3),
                    'from' => $base . '/',
                    'referrers' => [$base . '/', $base . '/catalog/'],
                    'referrer_count' => 2,
                    'discovered_via' => 'link',
                    'discovered_from' => $base . '/',
                ];
            case 'unreachable':
                return [
                    'error' => 'timeout',
                    'from' => $base . '/blog/',
                    'referrers' => [$base . '/blog/', $base . '/'],
                    'referrer_count' => 2,
                    'discovered_via' => 'link',
                    'discovered_from' => $base . '/blog/',
                ];
            case 'redirect':
                return [
                    'to' => $base . '/catalog/',
                    'status' => 301,
                    'final' => $base . '/catalog/',
                    'chain' => [$base . '/catalog/'],
                ];
            case 'redirect_chain_long':
                return [
                    'final' => $base . '/',
                    'chain' => [$base . '/a/', $base . '/b/', $base . '/c/', $base . '/'],
                    'hops' => 4,
                ];
            case 'redirect_loop':
                return [
                    'chain' => [$base . '/loop-a/', $url],
                    'loop' => true,
                ];
            case 'duplicate_title':
                $groupSize = 8;
                $groupId = (int) floor($j / $groupSize);
                $titles = [
                    'Каталог насосов и комплектующих',
                    'Доставка по России — сроки и стоимость',
                    'Сервисный центр и гарантия',
                    'Акции и спецпредложения месяца',
                    'О компании и производство',
                ];
                $title = $titles[$groupId % count($titles)];
                $peers = self::demoPeerUrls($base, $j, $groupSize);

                return [
                    'title' => $title,
                    'hash' => hash('sha256', 'dup-title-' . $groupId . '|' . $title),
                    'group_size' => $groupSize,
                    'group_id' => $groupId + 1,
                    'peer_urls' => $peers,
                    'label' => $title,
                ];
            case 'duplicate_description':
                $groupSize = 6;
                $groupId = (int) floor($j / $groupSize);
                $descs = [
                    'Доставка и оплата — условия для клиентов',
                    'Официальная гарантия на оборудование',
                    'Подбор насоса под задачу и объект',
                    'Контакты офиса и схема проезда',
                    'Каталог с актуальными ценами',
                ];
                $desc = $descs[$groupId % count($descs)];
                $peers = self::demoPeerUrls($base, $j, $groupSize);

                return [
                    'description' => $desc,
                    'hash' => hash('sha256', 'dup-desc-' . $groupId . '|' . $desc),
                    'group_size' => $groupSize,
                    'group_id' => $groupId + 1,
                    'peer_urls' => $peers,
                    'label' => $desc,
                ];
            case 'duplicate_content':
                $groupSize = 4;
                $groupId = (int) floor($j / $groupSize);
                $labels = [
                    'Карточка товара: насос Прайм-100 (шаблон без уникального текста)',
                    'Страница доставки: копипаст из /delivery/ на языковые зеркала',
                    'Пустой листинг каталога: один и тот же HTML на фильтрах',
                    'Контакты: одинаковый блок реквизитов на /contacts/ и /about/',
                    'Промо-лендинг: клон оффера с другим URL',
                ];
                $label = $labels[$groupId % count($labels)];
                $hashKey = 'dup-content-' . $groupId;
                $peers = self::demoPeerUrls($base, $j, $groupSize);

                return [
                    'hash' => hash('sha256', $hashKey),
                    'group_size' => $groupSize,
                    'peer_urls' => $peers,
                    'label' => $label,
                    'title' => $label,
                ];
            case 'similar_pages':
                $w = 800 + ($j * 37) % 2200;
                $sw = max(200, $w + (($j % 7) - 3) * 80);
                $sharedPool = [
                    ['диван', 'мебель', 'доставка', 'москва', 'цена', 'каталог'],
                    ['матрас', 'ортопедический', 'кровать', 'доставка', 'размер'],
                    ['шкаф', 'купе', 'заказ', 'замер', 'москва', 'сборка'],
                    ['стол', 'письменный', 'офис', 'самовывоз', 'наличие'],
                    ['кресло', 'массив', 'дуб', 'гостиная', 'доставка'],
                ];
                $shared = $sharedPool[$j % count($sharedPool)];
                $shingleSamples = [
                    implode(' ', array_slice($shared, 0, 5)),
                    implode(' ', array_merge(array_slice($shared, 1, 4), ['качество'])),
                ];
                $overlap = 0.18 + (($j % 5) * 0.04);

                return [
                    'similar_url' => $base . '/catalog/item-' . (($j % 40) + 1) . '/',
                    'hamming' => 3 + ($j % 5),
                    'distance' => 3 + ($j % 5),
                    'word_count' => $w,
                    'similar_word_count' => $sw,
                    'shared_words' => $shared,
                    'shared_source' => 'body',
                    'shingle_size' => 5,
                    'shingle_overlap' => round($overlap, 4),
                    'shingle_shared' => 12 + ($j % 9),
                    'shared_shingles' => $shingleSamples,
                ];
            case 'thin_content':
                return ['word_count' => 40 + ($j % 30), 'threshold' => 150];
            case 'images_without_alt':
                $imgCount = 3 + ($j % 4);
                $without = max(1, 1 + ($j % $imgCount));
                $samples = [];
                for ($s = 0; $s < $without; $s++) {
                    $id = (($j + $s * 5) % 20) + 20;
                    $src = 'https://picsum.photos/id/' . $id . '/800/600';
                    $samples[] = [
                        'src' => $src,
                        'url' => $src,
                        'img' => $src,
                        'width' => 800,
                        'height' => 600,
                    ];
                }

                return [
                    'img_without_alt' => $without,
                    'img_count' => $imgCount,
                    'count' => $without,
                    'samples' => $samples,
                ];
            case 'page_has_broken_links':
                $count = 1 + ($j % 3);
                $samples = [];
                for ($s = 0; $s < min(2, $count); $s++) {
                    $samples[] = [
                        'url' => $base . '/missing/page-' . ($j + 1 + $s) . '/',
                        'status' => ($s === 0) ? 404 : 500,
                        'text' => $s === 0 ? 'битая ссылка' : 'ещё одна',
                    ];
                }

                return [
                    'count' => $count,
                    'samples' => $samples,
                ];
            case 'too_many_strong':
                return [
                    'strong_count' => 22 + ($j % 12),
                    'threshold' => 20,
                ];
            case 'duplicate_links':
                return [
                    'count' => 1 + ($j % 4),
                    'samples' => [
                        ['url' => $base . '/catalog/', 'count' => 2 + ($j % 3)],
                    ],
                ];
            case 'links_nofollow':
                return [
                    'count' => 2,
                    'samples' => [
                        [
                            'href' => $base . '/go/partner/',
                            'text' => 'Партнёр',
                            'scope' => 'external',
                        ],
                        [
                            'href' => 'https://example.com/old-offer/',
                            'text' => 'Старое предложение',
                            'scope' => 'external',
                        ],
                    ],
                ];
            case 'page_has_broken_external_links':
                return [
                    'count' => 1,
                    'samples' => [
                        [
                            'url' => 'https://example.com/gone/',
                            'text' => 'Битая внешняя',
                            'status' => 404,
                        ],
                    ],
                ];
            case 'external_links':
                return [
                    'count' => 2 + ($j % 3),
                    'samples' => [
                        ['url' => 'https://example.com/partner/', 'text' => 'Партнёр'],
                        ['url' => 'https://t.me/example', 'text' => 'Telegram'],
                    ],
                ];
            case 'broken_image':
                return [
                    'count' => 1,
                    'samples' => [[
                        'img' => $base . '/img/broken-' . ($j + 1) . '.jpg',
                        'status' => 404,
                    ]],
                ];
            case 'external_assets':
                return [
                    'count' => 3,
                    'samples' => [
                        [
                            'url' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
                            'kind' => 'script',
                        ],
                        [
                            'url' => 'https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap',
                            'kind' => 'css',
                        ],
                        [
                            'url' => 'https://mc.yandex.ru/metrika/tag.js',
                            'kind' => 'script',
                        ],
                    ],
                ];
            case 'lost_file':
                return [
                    'asset' => $base . '/assets/app-' . ($j % 5) . '.css',
                    'status' => 404,
                    'referrers' => [$url],
                ];
            case 'page_has_bad_links':
                $variants = [
                    [
                        'href' => null,
                        'reason' => 'missing_href',
                        'text' => 'клик',
                        'snippet' => '<a class="btn">клик</a>',
                    ],
                    [
                        'href' => '#',
                        'reason' => 'empty_or_hash',
                        'text' => 'Подробнее',
                        'snippet' => '<a href="#">Подробнее</a>',
                    ],
                    [
                        'href' => 'javascript:void(0)',
                        'reason' => 'javascript',
                        'text' => 'Открыть',
                        'snippet' => '<a href="javascript:void(0)">Открыть</a>',
                    ],
                ];
                $one = $variants[$j % count($variants)];

                return [
                    'count' => 1,
                    'samples' => [$one],
                ];
            case 'canonical_not_self':
                return ['canonical' => $base . '/'];
            case 'canonical_foreign':
                return ['canonical' => 'https://other-shop.example/page'];
            case 'title_too_short':
                $title = 'Диваны';
                if ($j > 0) {
                    $title = 'Купить · ' . ($j + 1);
                }

                return [
                    'length' => mb_strlen($title),
                    'min' => 30,
                    'title' => $title,
                ];
            case 'title_too_long':
                $title = 'Очень длинный демо TITLE для витрины аудита номер ' . ($j + 1)
                    . ' — интернет-магазин мебели, доставка и сборка по всей России сегодня';

                return [
                    'length' => mb_strlen($title),
                    'max' => 70,
                    'title' => $title,
                ];
            case 'description_too_short':
                $desc = $j === 0
                    ? 'Диваны и кресла.'
                    : ('Кратко о товаре #' . ($j + 1) . '.');

                return [
                    'length' => mb_strlen($desc),
                    'min' => 70,
                    'description' => $desc,
                ];
            case 'description_too_long':
                $desc = str_repeat('Подробное описание демо-товара для сниппета. ', 6)
                    . 'Артикул #' . ($j + 1) . '.';

                return [
                    'length' => mb_strlen($desc),
                    'max' => 160,
                    'description' => $desc,
                ];
            case 'title_equals_h1':
                $text = 'Купить диван в Москве — доставка за 1 день';

                return [
                    'title' => $text,
                    'h1' => $text,
                ];
            case 'description_equals_h1':
                $text = 'Интернет-магазин мебели с доставкой по РФ'
                    . ($j > 0 ? ' · серия ' . ($j + 1) : '');

                return [
                    'description' => $text,
                    'h1' => $text,
                ];
            case 'title_equals_description':
                // Реалистичный одинаковый текст (не «подсказка системы»).
                $products = [
                    'Купить угловой диван «Комфорт» — цена от 24 900 ₽',
                    'Матрас ортопедический 160×200 — бесплатная доставка',
                    'Кресло-качалка из массива дуба — в наличии',
                    'Шкаф-купе на заказ в Москве — замер бесплатно',
                    'Письменный стол с ящиками — самовывоз сегодня',
                ];
                $t = $products[$j % count($products)];
                if ($j >= count($products)) {
                    $t .= ' · вариант ' . ($j + 1);
                }

                return ['title' => $t, 'description' => $t];
            case 'multiple_title_or_description':
                $tc = 1 + ($j % 2);
                $dc = 2 + ($j % 2);
                $titles = ['Основной TITLE страницы'];
                for ($k = 2; $k <= $tc; $k++) {
                    $titles[] = 'Дубль TITLE из шаблона #' . $k;
                }
                $descs = ['Короткое описание страницы для сниппета'];
                for ($k = 2; $k <= $dc; $k++) {
                    $descs[] = 'Лишний description из плагина / блока #' . $k;
                }

                return [
                    'title_count' => $tc,
                    'description_count' => $dc,
                    'titles' => $titles,
                    'descriptions' => $descs,
                ];
            case 'missing_h1':
            case 'empty_title':
            case 'empty_description':
                return ['missing' => true];
            case 'h1_equals_h2':
                $h = 'Каталог мебели для гостиной'
                    . ($j > 0 ? ' · блок ' . (($j % 20) + 1) : '');

                return ['h1' => $h, 'h2' => $h];
            case 'heading_hierarchy':
                $variants = [
                    [
                        'issue_count' => 1,
                        'issues' => [
                            [
                                'type' => 'skip',
                                'from' => 1,
                                'to' => 3,
                                'text' => 'Подраздел без H2 #' . ($j + 1),
                            ],
                        ],
                        'outline_sample' => [
                            ['level' => 1, 'text' => 'H1: тема страницы #' . ($j + 1)],
                            ['level' => 3, 'text' => 'Подраздел без H2 #' . ($j + 1)],
                            ['level' => 3, 'text' => 'Ещё один H3'],
                        ],
                    ],
                    [
                        'issue_count' => 1,
                        'issues' => [
                            [
                                'type' => 'skip',
                                'from' => 2,
                                'to' => 4,
                                'text' => 'Слишком глубокий H4 #' . ($j + 1),
                            ],
                        ],
                        'outline_sample' => [
                            ['level' => 1, 'text' => 'Главный заголовок'],
                            ['level' => 2, 'text' => 'Раздел'],
                            ['level' => 4, 'text' => 'Слишком глубокий H4 #' . ($j + 1)],
                        ],
                    ],
                    [
                        'issue_count' => 1,
                        'issues' => [
                            [
                                'type' => 'before_h1',
                                'level' => 2,
                                'text' => 'Меню / блок до H1 #' . ($j + 1),
                            ],
                        ],
                        'outline_sample' => [
                            ['level' => 2, 'text' => 'Меню / блок до H1 #' . ($j + 1)],
                            ['level' => 1, 'text' => 'Настоящий H1 страницы'],
                            ['level' => 2, 'text' => 'Нормальный раздел'],
                        ],
                    ],
                ];

                return $variants[$j % count($variants)];
            case 'multiple_h1':
                $n = 2 + ($j % 2);
                $samples = ['Основной заголовок страницы'];
                for ($k = 2; $k <= $n; $k++) {
                    $samples[] = 'Лишний H1 из блока / виджета #' . $k;
                }

                return [
                    'count' => $n,
                    'samples' => $samples,
                ];
            case 'noindex':
                // Демо: явно meta и/или HTTP X-Robots-Tag — не путать с robots.txt.
                $variants = [
                    [
                        'robots' => 'noindex, follow',
                        'source' => 'meta',
                        'meta_html' => '<meta name="robots" content="noindex, follow">',
                    ],
                    [
                        'robots' => 'noindex, nofollow',
                        'source' => 'meta',
                        'meta_html' => '<meta name="robots" content="noindex, nofollow">',
                    ],
                    [
                        'x_robots' => 'noindex, follow',
                        'source' => 'header',
                    ],
                    [
                        'robots' => 'noindex, follow',
                        'x_robots' => 'noindex, follow',
                        'source' => 'meta+header',
                        'meta_html' => '<meta name="robots" content="noindex, follow">',
                    ],
                ];

                return $variants[$j % count($variants)];
            case 'text_in_noindex':
                return [
                    'noindex_text_len' => 5,
                    'sample' => 'VK TG',
                    'links' => [
                        ['href' => 'https://vk.com/demo', 'text' => 'VK'],
                        ['href' => 'https://t.me/demo', 'text' => 'TG'],
                    ],
                    'hash' => 'demo-noindex-social',
                ];
            case 'pages_with_iframe':
                return ['iframe_count' => 1 + ($j % 2)];
            case 'mixed_content':
                $sets = [
                    [
                        'http://cdn.example/logo.svg',
                        'http://stats.example/counter.js',
                    ],
                    [
                        'http://cdn.example/assets/app.css',
                        'http://fonts.example/demo.woff2',
                        'http://img.example/hero.jpg',
                    ],
                    [
                        'http://widget.example/chat.js',
                    ],
                ];
                $pick = $sets[$j % count($sets)];

                return [
                    'count' => count($pick) + ($j % 2),
                    'samples' => $pick,
                ];
            case 'bad_doctype':
                return ($j % 2)
                    ? ['reason' => 'missing']
                    : ['reason' => 'legacy', 'doctype' => 'HTML 4.01 Transitional'];
            case 'serp_title_mismatch':
                return [
                    'engine' => $j % 2 ? 'google' : 'yandex',
                    'page_title' => 'TITLE на сайте ' . ($j + 1),
                    'serp_title' => 'TITLE в выдаче ' . ($j + 1),
                ];
            case 'index_count_mismatch':
                return ['engine' => 'yandex', 'in_index' => false];
            case 'serp_snippets':
                $n = $j + 1;
                $pageTitle = 'Демо TITLE #' . $n . ' — интернет-магазин';
                $yTitle = $n % 3 === 0
                    ? 'Другой заголовок в Яндексе #' . $n
                    : $pageTitle;
                $gTitle = $n % 4 === 0
                    ? $pageTitle . ' | Titlo'
                    : $pageTitle;

                return [
                    'source' => $n % 2 ? 'landing' : 'crawl',
                    'page_title' => $pageTitle,
                    'engines' => [
                        'yandex' => [
                            'indexed' => $n % 11 !== 0,
                            'title' => $n % 11 === 0 ? null : $yTitle,
                            'snippet' => $n % 11 === 0
                                ? null
                                : 'Демо-текст Яндекса под ссылкой для страницы #' . $n . '. Кратко о товаре и доставке.',
                            'title_match' => $yTitle === $pageTitle,
                            'title_mismatch' => $yTitle !== $pageTitle,
                        ],
                        'google' => [
                            'indexed' => $n % 13 !== 0,
                            'title' => $n % 13 === 0 ? null : $gTitle,
                            'snippet' => $n % 13 === 0
                                ? null
                                : 'Google demo snippet for page #' . $n . ' — short description under the blue link.',
                            'title_match' => $gTitle === $pageTitle,
                            'title_mismatch' => $gTitle !== $pageTitle,
                        ],
                    ],
                ];
            case 'serp_snippet_source':
                return [
                    'engine' => $j % 2 ? 'google' : 'yandex',
                    'title_source' => $j % 3 === 0 ? 'h1' : 'title',
                    'snippet_source' => $j % 2 ? 'description' : 'body',
                    'serp_title' => 'Заголовок в выдаче #' . ($j + 1),
                    'snippet' => 'Текст под ссылкой в поиске для демо #' . ($j + 1),
                ];
            case 'psi_mobile':
            case 'psi_desktop':
                $pct = [96, 70, 92, 48, 88, 61][$j % 6];
                $lcp = [320, 2300, 1400, 4100, 900, 2800][$j % 6];
                $cls = [0.05, 0.307, 0.11, 0.28, 0.08, 0.19][$j % 6];
                $tbt = [45, 0, 120, 650, 80, 310][$j % 6];

                return [
                    'strategy' => $code === 'psi_mobile' ? 'mobile' : 'desktop',
                    'score' => $pct / 100,
                    'score_pct' => $pct,
                    'lcp_ms' => $lcp,
                    'cls' => $cls,
                    'tbt_ms' => $tbt,
                    'fcp_ms' => (int) round($lcp * 0.55),
                    'si_ms' => (int) round($lcp * 1.2),
                    'tti_ms' => (int) round($lcp * 1.8),
                    'ttfb_ms' => 120 + ($j % 5) * 40,
                    'accessibility_pct' => 88 + ($j % 10),
                    'best_practices_pct' => 75 + ($j % 20),
                    'seo_pct' => 90 + ($j % 8),
                    'opportunities' => [
                        [
                            'id' => 'unused-javascript',
                            'title' => 'Reduce unused JavaScript',
                            'savings_ms' => 400 + $j * 40,
                            'savings_bytes' => 120000 + $j * 8000,
                            'display' => 'Est savings of ' . (0.4 + $j * 0.05) . ' s',
                        ],
                        [
                            'id' => 'uses-responsive-images',
                            'title' => 'Properly size images',
                            'savings_ms' => 200 + $j * 20,
                            'savings_bytes' => 80000,
                            'display' => null,
                        ],
                    ],
                    'diagnostics' => [
                        ['id' => 'dom-size', 'title' => 'Avoid an excessive DOM size', 'display' => '1,200 elements'],
                        ['id' => 'bootup-time', 'title' => 'Reduce JavaScript execution time', 'display' => '1.8 s'],
                    ],
                    'rich' => true,
                    'psi_version' => '12.x-demo',
                ];
            case 'landing_plagiarism_external':
                $uniq = 42 + ($j % 20);
                $peer = 'https://copy.example/page-' . (($j % 7) + 1) . '/';

                return [
                    'uniqueness_pct' => $uniq,
                    'matched_pct' => max(0, 100 - $uniq),
                    'warn_below' => 70,
                    'sources' => [
                        ['url' => $peer, 'overlap_pct' => max(10, 100 - $uniq)],
                    ],
                    'engine' => 'yandex',
                    'cost' => 1,
                    'provider' => 'titlo_text_uniqueness',
                ];
            case 'word_repeat_in_sentence':
                $words = ['диван', 'доставка', 'скидка', 'москва', 'каталог', 'купить'];

                return [
                    'count' => 3,
                    'samples' => [
                        ['word' => $words[$j % count($words)], 'count' => 4 + ($j % 3)],
                        ['word' => $words[($j + 2) % count($words)], 'count' => 3 + ($j % 2)],
                        ['word' => $words[($j + 4) % count($words)], 'count' => 3],
                    ],
                ];
            case 'text_nausea':
                $tops = [
                    ['купить', 'диван', 'москва'],
                    ['доставка', 'бесплатно', 'сегодня'],
                    ['цена', 'каталог', 'акция'],
                    ['мебель', 'шкаф', 'кухня'],
                    ['заказать', 'онлайн', 'скидка'],
                ];
                $pack = $tops[$j % count($tops)];

                return [
                    'nausea_classic' => 7.5 + ($j % 5),
                    'nausea_academic' => 5.2 + ($j % 4) * 0.3,
                    'top_word' => $pack[0],
                    'top_word_count' => 12 + ($j % 8),
                    'top_words' => [
                        ['word' => $pack[0], 'count' => 12 + ($j % 8)],
                        ['word' => $pack[1], 'count' => 9 + ($j % 5)],
                        ['word' => $pack[2], 'count' => 7 + ($j % 4)],
                    ],
                ];
            case 'text_bigram_spam':
                $bigrams = [
                    ['купить диван', 'диван москва', 'бесплатная доставка'],
                    ['заказать кухню', 'кухня под ключ', 'рассрочка без'],
                    ['шкаф купе', 'недорогая мебель', 'доставка сегодня'],
                    ['офисный стул', 'кресло руководителя', 'купить оптом'],
                    ['матрас ортопедический', 'кровать двуспальная', 'акции недели'],
                ];
                $pack = $bigrams[$j % count($bigrams)];
                $samples = [];
                foreach ($pack as $i => $bg) {
                    $samples[] = [
                        'bigram' => $bg,
                        'count' => 8 + ($j % 6) - $i,
                        'density' => round(1.8 - $i * 0.25 + ($j % 3) * 0.1, 2),
                    ];
                }

                return [
                    'bigram' => $samples[0]['bigram'],
                    'count' => $samples[0]['count'],
                    'density' => $samples[0]['density'],
                    'threshold_count' => 4,
                    'threshold_density' => 1.5,
                    'samples' => $samples,
                ];
            case 'text_trigram_spam':
                $trigrams = [
                    ['купить диван москва', 'диван с доставкой', 'рассрочка без процентов'],
                    ['заказать кухню недорого', 'кухня под ключ', 'бесплатный замер сегодня'],
                    ['шкаф купе на заказ', 'мебель для спальни', 'акция этой недели'],
                    ['офисный стул купить', 'кресло для руководителя', 'доставка по россии'],
                    ['матрас ортопедический купить', 'кровать двуспальная москва', 'скидка на комплект'],
                    ['детская кровать недорого', 'стол письменный купить', 'стул компьютерный москва'],
                ];
                $pack = $trigrams[$j % count($trigrams)];
                $samples = [];
                foreach ($pack as $i => $tg) {
                    $samples[] = [
                        'trigram' => $tg,
                        'count' => max(3, 9 + ($j % 5) - $i * 2),
                        'density' => round(1.6 - $i * 0.2 + ($j % 4) * 0.1, 2),
                    ];
                }

                return [
                    'trigram' => $samples[0]['trigram'],
                    'count' => $samples[0]['count'],
                    'density' => $samples[0]['density'],
                    'threshold_count' => 3,
                    'threshold_density' => 1.0,
                    'samples' => $samples,
                ];
            case 'meta_spam':
                $titleWords = ['купить', 'диван', 'москва', 'недорого', 'акция'];
                $descWords = ['доставка', 'рассрочка', 'каталог', 'скидка', 'мебель'];

                return [
                    'title' => [
                        'word' => $titleWords[$j % count($titleWords)],
                        'count' => 3 + ($j % 3),
                    ],
                    'description' => [
                        'word' => $descWords[$j % count($descWords)],
                        'count' => 3 + ($j % 4),
                    ],
                ];
            case 'h1_spam':
                $words = ['купить', 'диван', 'кухня', 'шкаф', 'матрас', 'кресло'];
                $w = $words[$j % count($words)];

                return [
                    'word' => $w,
                    'count' => 3 + ($j % 2),
                    'h1' => ucfirst($w) . ' ' . $w . ' ' . $w . ' — демо #' . ($j + 1),
                ];
            case 'adult_content':
            case 'negative_content':
                return ['hits' => ['demo', 'fixture'], 'score' => 2 + ($j % 3)];
            case 'heavy_image':
                $count = 1 + ($j % 2);
                $threshold = 500000;
                $samples = [];
                for ($s = 0; $s < $count; $s++) {
                    $id = (($j + $s * 3) % 12) + 10;
                    $samples[] = [
                        'img' => 'https://picsum.photos/id/' . $id . '/1600/1200',
                        'size_bytes' => 1800000 + (($j + $s) % 5) * 250000,
                        'threshold' => $threshold,
                    ];
                }

                return [
                    'count' => $count,
                    'threshold' => $threshold,
                    'samples' => $samples,
                ];
            case 'probable_affiliate':
                $nets = ['admitad', 'cityads', 'amazon', 'awin', 'generic'];
                $samples = [
                    [
                        'url' => 'https://ad.admitad.com/g/demo' . ($j + 1) . '/?ulp=https%3A%2F%2Fshop.example%2Fitem',
                        'network' => 'admitad',
                    ],
                ];
                if ($j % 2 === 1) {
                    $samples[] = [
                        'url' => 'https://www.awin1.com/cread.php?awinmid=1&awinaffid=demo' . $j,
                        'network' => 'awin',
                    ];
                }
                if ($j % 3 === 0) {
                    $samples[] = [
                        'url' => $base . '/go.php?aff_id=42&offer=' . ($j + 1),
                        'network' => 'generic',
                    ];
                }

                return [
                    'count' => count($samples),
                    'samples' => $samples,
                    'network' => $nets[$j % count($nets)],
                ];
            case 'page_too_large':
                return [
                    'size_bytes' => 2500000 + $j * 1000,
                    'threshold' => 1500000,
                ];
            case 'duplicate_url_variants':
                return ['variants' => [$url, rtrim($url, '/') . '?utm=1']];
            case 'risky_query_params':
                return self::riskyQueryDemoMeta($url, $j);
            case 'pagination_param':
                return ['params' => ['page']];
            case 'www_both_available':
                $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: parse_url($base, PHP_URL_HOST) ?: 'demo-audit.titlo.ru'));
                $bare = preg_replace('/^www\./i', '', $host);
                $apex = 'https://' . $bare . '/';
                $www = 'https://www.' . $bare . '/';

                return [
                    'bare_host' => $bare,
                    'apex_url' => $apex,
                    'www_url' => $www,
                    'apex_status' => 200,
                    'www_status' => 200,
                    'apex_final' => $apex,
                    'www_final' => $www,
                    'apex_host' => $bare,
                    'www_host' => 'www.' . $bare,
                    'apex_redirected' => false,
                    'www_redirected' => false,
                    'apex_redirect_statuses' => [],
                    'www_redirect_statuses' => [],
                    'apex_stays_on' => 'apex',
                    'www_stays_on' => 'www',
                    'fix_301_to_apex' => $apex,
                    'fix_301_to_www' => $www,
                ];
            case 'http_https_both_available':
                $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: parse_url($base, PHP_URL_HOST) ?: 'demo-audit.titlo.ru'));
                $bare = preg_replace('/^www\./i', '', $host);
                $http = 'http://' . $bare . '/';
                $https = 'https://' . $bare . '/';
                $httpsWww = 'https://www.' . $bare . '/';

                return [
                    'http_url' => $http,
                    'http_status' => 200,
                    'http_final' => $http,
                    'http_final_scheme' => 'http',
                    'http_redirected' => false,
                    'http_redirect_statuses' => [],
                    'https_apex_url' => $https,
                    'https_www_url' => $httpsWww,
                    'https_apex_status' => 200,
                    'https_www_status' => 200,
                    'https_target' => $https,
                    'fix_301_to' => $https,
                ];
            case 'robots_txt_closed':
                return ['detail' => 'Disallow: /'];
            case 'robots_txt_error':
                return [
                    'reason' => ($j % 3 === 0) ? 'fetch_failed' : (($j % 3 === 1) ? 'empty' : 'http_status'),
                    'status' => 500,
                ];
            case 'sitemap_missing':
                return [
                    'tried' => [
                        $base . '/sitemap.xml',
                        $base . '/sitemap_index.xml',
                        $base . '/robots.txt',
                    ],
                ];
            case 'sitemap_error':
                return [
                    'reason' => ($j % 2) ? 'not_xml' : 'fetch_failed',
                    'sitemap' => $url,
                ];
            case 'site_availability':
                return ['detail' => 'периодические сбои 5xx'];
            case 'error_spike':
                $kinds = [
                    [
                        'kind' => 'status_cluster',
                        'status' => 500,
                        'count' => 42,
                        'error_total' => 60,
                    ],
                    [
                        'kind' => 'path_cluster',
                        'path_prefix' => '/catalog/',
                        'count' => 28,
                        'prefix_total' => 40,
                        'rate' => 0.7,
                    ],
                    [
                        'kind' => 'crawl_delta',
                        'prev_count' => 5,
                        'count' => 48,
                        'ratio' => 9.6,
                    ],
                ];

                return $kinds[$j % count($kinds)];
            case 'keyword_cannibalization':
                return [
                    'query' => 'купить диван демо',
                    'landing_url' => $base . '/catalog/',
                    'hits' => 2,
                    'full_match' => true,
                    'competitor_title' => 'Купить диван демо — ' . ($j === 0 ? 'главная' : 'страница ' . ($j + 1)),
                ];
            case 'ad_cannibalization':
                $hints = ['path_promo', 'path_promo_prefix', 'thin_cta', 'cta_heavy'];
                $queries = [
                    'купить диван демо',
                    'диван москва недорого',
                    'кухня под ключ',
                    'шкаф купе на заказ',
                ];

                return [
                    'query' => $queries[$j % count($queries)],
                    'ad_hint' => $hints[$j % count($hints)],
                    'landing_url' => $base . '/catalog/',
                    'full_match' => ($j % 3) !== 0,
                    'hits' => 2 + ($j % 3),
                    'competitor_title' => 'Купить диван — акция демо #' . ($j + 1),
                    'word_count' => 40 + ($j % 80),
                ];
            case 'serp_snippet_cannibalization':
                return [
                    'query' => 'купить диван демо',
                    'engine' => 'yandex',
                    'position' => 3 + ($j % 5),
                    'own_count' => 2,
                ];
            case 'landing_query_mismatch':
                return [
                    'query' => 'купить диван демо',
                    'match_mode' => 'lemmas',
                    'hits_any' => 1 + ($j % 3),
                    'token_count' => 3,
                    'hits_title' => 1,
                    'hits_h1' => 0,
                    'hits_description' => 0,
                    'missing_tokens' => ['демо'],
                    'in_title' => false,
                    'in_h1' => false,
                ];
            case 'commercial_missing_price':
            case 'commercial_missing_contacts':
            case 'commercial_missing_cta':
            case 'commercial_missing_delivery':
            case 'commercial_missing_payment':
            case 'commercial_missing_stock':
            case 'commercial_missing_reviews':
                return ['missing' => true];
            case 'landing_not_in_sitemap':
            case 'landing_not_crawled':
            case 'landing_url_changed':
                $oldPath = '/catalog/item-' . (($j % 10) + 1) . '/';
                $queries = [
                    'купить диван',
                    'диван угловой',
                    'кровать двуспальная',
                    'шкаф купе',
                    'кресло офисное',
                    'стол письменный',
                    'матрас ортопедический',
                    'тумба под тв',
                ];

                return [
                    'source' => 'monitoring',
                    'query' => $queries[$j % count($queries)] . ' ' . (($j % 5) + 1),
                    'old_url' => $base . $oldPath,
                    'new_url' => $url,
                ];
            case 'landing_no_inbound_internal':
                return ['inbound' => 0];
            case 'no_unique_images':
                $imgCount = 2 + ($j % 3);

                return [
                    'img_count' => $imgCount,
                    'unique_img_src_count' => 0,
                    'reason' => ($j % 3 === 0) ? 'no_img' : 'no_src',
                ];
            case 'deep_pages':
                return ['click_depth' => 6 + ($j % 4), 'threshold' => 5];
            case 'meta_nofollow':
                return ['robots' => 'nofollow'];
            case 'soft_404':
                return [
                    'reason' => ($j % 4 === 0) ? 'title_pattern' : 'thin',
                    'pattern' => ($j % 4 === 0) ? 'не найдена' : null,
                    'matched_in' => ($j % 4 === 0) ? 'title' : null,
                    'word_count' => 12 + ($j % 20),
                    'threshold' => 40,
                    'status' => 200,
                    'title' => ($j % 4 === 0)
                        ? 'Страница не найдена'
                        : ('Короткий TITLE ' . ($j + 1)),
                    'h1' => ($j % 4 === 0) ? 'Ошибка 404' : null,
                ];
            case 'orphan_pages':
                return ['inbound' => 0, 'discovered_via' => 'sitemap'];
            case 'multiple_canonical':
                return ['count' => 2 + ($j % 2)];
            case 'canonical_empty':
                return ['missing' => true];
            case 'html_critical_errors':
                return [
                    'count' => 1 + ($j % 3),
                    'samples' => [
                        ['message' => 'Stray end tag “div”.', 'line' => 40 + $j],
                    ],
                ];
            case 'insecure_form':
                $forms = [
                    [
                        'action' => 'http://demo-audit.titlo.ru/subscribe/',
                        'id' => 'subscribe-form',
                        'name' => 'subscribe',
                        'class' => 'form form--newsletter',
                        'method' => 'post',
                        'snippet' => '<form id="subscribe-form" name="subscribe" class="form form--newsletter" method="post" action="http://demo-audit.titlo.ru/subscribe/">',
                    ],
                    [
                        'action' => 'http://demo-audit.titlo.ru/feedback/',
                        'id' => 'feedback',
                        'name' => null,
                        'class' => 'js-feedback-form',
                        'method' => 'post',
                        'snippet' => '<form id="feedback" class="js-feedback-form" method="post" action="http://demo-audit.titlo.ru/feedback/">',
                    ],
                    [
                        'action' => 'http://legacy.partner.example/lead',
                        'id' => null,
                        'name' => 'lead',
                        'class' => 'lead-form',
                        'method' => 'get',
                        'snippet' => '<form name="lead" class="lead-form" method="get" action="http://legacy.partner.example/lead">',
                    ],
                    [
                        'action' => 'http://example.com/form-action',
                        'id' => null,
                        'name' => null,
                        'class' => null,
                        'method' => 'post',
                        'snippet' => '<form method="post" action="http://example.com/form-action">',
                    ],
                ];
                $pick = $forms[$j % count($forms)];

                return [
                    'count' => 1,
                    'samples' => [$pick],
                ];
            case 'not_in_sitemap':
            case 'no_outbound_internal':
                return ['flag' => true];
            case 'robots_blocked':
                return self::robotsBlockedDemoMeta($url, $j);
            case 'sitemap_not_crawled':
                return ['reason' => 'likely_robots_or_not_queued'];
            case 'missing_hsts':
            case 'missing_csp':
            case 'missing_x_frame_options':
            case 'missing_x_content_type_options':
            case 'missing_referrer_policy':
            case 'missing_permissions_policy':
            case 'missing_coop':
            case 'missing_coep':
            case 'missing_corp':
            case 'missing_charset':
                return ['header' => $code];
            default:
                // Не оставляем голый {"demo":true} — в «Детали» будет пусто.
                return [
                    'note' => 'demo',
                    'code' => $code,
                ];
        }
    }

    /**
     * Демо: разные Disallow, чтобы было видно «какое правило на что».
     *
     * @return array{directive:string,rule:string,agent:string,source:string}
     */
    private static function robotsBlockedDemoMeta(string $url, int $j): array
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '/');
        if (strpos($path, '/catalog') === 0) {
            $rule = '/catalog/';
        } elseif (strpos($path, '/search') === 0) {
            $rule = '/search';
        } elseif (strpos($path, '/cart') === 0 || strpos($path, '/checkout') === 0) {
            $rule = '/cart/';
        } elseif (strpos($path, '/admin') === 0 || strpos($path, '/lk') === 0) {
            $rule = '/admin/';
        } elseif (preg_match('#\.pdf$#i', $path)) {
            $rule = '/*.pdf$';
        } else {
            $pool = ['/tmp/', '/private/', '/api/', '/cgi-bin/', '/filter/', '/print/'];
            $rule = $pool[$j % count($pool)];
        }

        return [
            'directive' => 'Disallow',
            'rule' => $rule,
            'agent' => '*',
            'source' => 'robots.txt',
        ];
    }

    /** URL с query под разные сценарии risky_query_params. */
    private static function riskyQueryDemoUrl(string $cleanUrl, int $j): string
    {
        $base = rtrim($cleanUrl, '?&');
        switch ($j % 4) {
            case 0:
                return $base . (strpos($base, '?') === false ? '?' : '&') . 'session_id=abc' . ($j + 1) . 'def';
            case 1:
                return $base . (strpos($base, '?') === false ? '?' : '&') . 'sort=price&order=desc';
            case 2:
                $parts = [];
                for ($i = 1; $i <= 9; $i++) {
                    $parts[] = 'p' . $i . '=v' . $i;
                }

                return $base . (strpos($base, '?') === false ? '?' : '&') . implode('&', $parts);
            default:
                return $base . (strpos($base, '?') === false ? '?' : '&')
                    . 'q=' . str_repeat('x', 130) . '&ref=demo';
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function riskyQueryDemoMeta(string $url, int $j): array
    {
        $query = parse_url($url, PHP_URL_QUERY);
        $params = [];
        if (is_string($query) && $query !== '') {
            parse_str($query, $params);
        }
        $allKeys = array_map('strtolower', array_keys($params));
        $risky = array_values(array_intersect($allKeys, [
            'phpsessid', 'sid', 'sessionid', 'session_id', 'jsessionid',
            'sort', 'order', 'orderby', 'sortby',
        ]));
        $many = count($allKeys) >= 8;
        $long = is_string($query) && strlen($query) >= 120;

        return [
            'keys' => $risky,
            'key_count' => count($allKeys),
            'query_len' => is_string($query) ? strlen($query) : 0,
            'many_keys' => $many,
            'long_query' => $long,
            'query' => is_string($query) ? $query : '',
            'reason' => $j % 4 === 0 ? 'session' : ($j % 4 === 1 ? 'sort' : ($j % 4 === 2 ? 'many_keys' : 'long_query')),
        ];
    }
}
