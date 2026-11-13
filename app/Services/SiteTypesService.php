<?php

namespace App\Services;

use App\Classes\Xml\SimplifiedXmlFacade;
use Illuminate\Support\Str;

class SiteTypesService
{
    /** Google XML: не больше 10 URL на страницу. */
    public const GOOGLE_PAGE_SIZE = 10;

    /**
     * Яндекс: 1 фраза = 1 лимит (глубина до 30 в одном запросе).
     * Google: 1 фраза × ceil(depth/10) — ТОП-10=1, ТОП-20=2, ТОП-30=3.
     *
     * @param array<int, string> $engines
     */
    public static function estimateCost(int $phrasesCount, array $engines, int $depth = 10): int
    {
        $phrasesCount = max(0, $phrasesCount);
        if ($phrasesCount === 0 || $engines === []) {
            return 0;
        }

        $depth = max(1, $depth);
        $total = 0;
        foreach ($engines as $engine) {
            if ($engine === 'google') {
                $total += $phrasesCount * self::googlePagesForDepth($depth);
            } else {
                $total += $phrasesCount;
            }
        }

        return $total;
    }

    public static function googlePagesForDepth(int $depth): int
    {
        return max(1, (int) ceil(max(1, $depth) / self::GOOGLE_PAGE_SIZE));
    }

    /**
     * @param array<int, string> $lines
     * @return array<int, string>
     */
    public function normalizePhrases(array $lines): array
    {
        $max = max(1, (int) config('cabinet-site-types.max_phrases', 200));
        $out = [];
        $seen = [];

        foreach ($lines as $line) {
            $phrase = trim(preg_replace('/\s+/u', ' ', (string) $line) ?? '');
            if ($phrase === '') {
                continue;
            }
            $key = mb_strtolower($phrase);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $phrase;
            if (count($out) >= $max) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param array{
     *   phrases: array<int, string>,
     *   engines: array<int, string>,
     *   depth: int,
     *   yandex_lr: string,
     *   google_lr: string,
     *   custom_domains?: array<string, array<int, string>>
     * } $params
     * @return array{
     *   cost: int,
     *   requests: int,
     *   errors: int,
     *   depth: int,
     *   summary: array<string, mixed>,
     *   queries: array<int, array<string, mixed>>,
     *   categories: array<string, array<string, mixed>>
     * }
     */
    public function analyze(array $params): array
    {
        $phrases = $params['phrases'] ?? [];
        $engines = $params['engines'] ?? [];
        $depth = $this->normalizeDepth((int) ($params['depth'] ?? 10));
        $yandexLr = (string) ($params['yandex_lr'] ?? config('cabinet-site-types.default_yandex_lr', '213'));
        $googleLr = (string) ($params['google_lr'] ?? config('cabinet-site-types.default_google_lr', '1011969'));
        $catalog = $this->buildCatalog($params['custom_domains'] ?? []);

        $pauseMs = max(0, (int) config('cabinet-site-types.request_pause_ms', 120));
        $probe = new SiteTypesPageClassifier();
        $queries = [];
        $requests = 0;
        $errors = 0;
        $globalCounts = $this->emptyCounts();
        $globalTotal = 0;
        /** @var array<string, array{count: int, type: string, in_catalog: bool}> $hostStats */
        $hostStats = [];

        foreach ($phrases as $phrase) {
            foreach ($engines as $engine) {
                $engine = $engine === 'google' ? 'google' : 'yandex';
                $lr = $engine === 'google' ? $googleLr : $yandexLr;

                $hadError = false;
                $pageRequests = 0;
                $urls = $this->fetchSerpUrls($engine, $lr, $phrase, $depth, $hadError, $pageRequests);
                $requests += max(1, $pageRequests);
                if ($hadError) {
                    $errors++;
                }

                $draft = [];
                $prefetch = [];
                foreach ($urls as $index => $url) {
                    $pos = $index + 1;
                    if ($pos > $depth) {
                        break;
                    }
                    $domain = $this->hostFromUrl($url);
                    $catalogType = $this->classifyDomain($domain, $catalog);
                    $draft[] = [
                        'position' => $pos,
                        'url' => $url,
                        'domain' => $domain,
                        'catalog_type' => $catalogType,
                    ];
                    if ($catalogType === 'unknown' && $domain !== '') {
                        $prefetch[] = ['url' => $url, 'domain' => $domain];
                    }
                }
                $probe->prefetchHtml($prefetch);

                $rows = [];
                $counts = $this->emptyCounts();
                foreach ($draft as $item) {
                    $domain = $item['domain'];
                    $url = $item['url'];
                    $catalogType = $item['catalog_type'];
                    $inCatalog = $catalogType !== 'unknown';
                    $probed = $probe->classify($url, $domain, $catalogType);
                    $type = $probed['type'] ?? 'unknown';

                    $rows[] = [
                        'position' => $item['position'],
                        'url' => $url,
                        'domain' => $domain,
                        'type' => $type,
                        'type_source' => $probed['source'] ?? 'catalog',
                        'in_catalog' => $inCatalog,
                    ];
                    $counts[$type] = ($counts[$type] ?? 0) + 1;
                    $globalCounts[$type] = ($globalCounts[$type] ?? 0) + 1;
                    $globalTotal++;

                    if ($domain !== '') {
                        if (! isset($hostStats[$domain])) {
                            $hostStats[$domain] = [
                                'count' => 0,
                                'type' => $type,
                                'in_catalog' => $inCatalog,
                            ];
                        }
                        $hostStats[$domain]['count']++;
                        $hostStats[$domain]['type'] = $type;
                        $hostStats[$domain]['in_catalog'] = $hostStats[$domain]['in_catalog'] || $inCatalog;
                    }
                }

                $queries[] = [
                    'phrase' => $phrase,
                    'engine' => $engine,
                    'region' => $lr,
                    'rows' => $rows,
                    'counts' => $counts,
                    'total' => count($rows),
                    'mix' => $this->countsToMix($counts, count($rows)),
                    'verdict' => $this->verdictFromCounts($counts, count($rows)),
                    'error' => $hadError && $urls === [],
                    'xml_pages' => $pageRequests,
                ];

                if ($pauseMs > 0) {
                    usleep($pauseMs * 1000);
                }
            }
        }

        $categoriesMeta = $this->categoriesMeta();
        $phraseMatrix = $this->buildPhraseMatrix($queries);
        $frequentHosts = $this->buildFrequentHosts($hostStats);

        return [
            'cost' => self::estimateCost(count($phrases), $engines, $depth),
            'requests' => $requests,
            'errors' => $errors,
            'depth' => $depth,
            'summary' => [
                'total_positions' => $globalTotal,
                'counts' => $globalCounts,
                'mix' => $this->countsToMix($globalCounts, $globalTotal),
                'verdict' => $this->verdictFromCounts($globalCounts, $globalTotal),
                'phrases' => count($phrases),
                'engines' => count($engines),
            ],
            'phrase_matrix' => $phraseMatrix,
            'frequent_hosts' => $frequentHosts,
            'queries' => $queries,
            'categories' => $categoriesMeta,
        ];
    }

    /**
     * Расклад % типов по каждой фразе (как у конкурента).
     *
     * @param array<int, array<string, mixed>> $queries
     * @return array<int, array<string, mixed>>
     */
    private function buildPhraseMatrix(array $queries): array
    {
        $byPhrase = [];
        foreach ($queries as $q) {
            $phrase = (string) ($q['phrase'] ?? '');
            if ($phrase === '') {
                continue;
            }
            if (! isset($byPhrase[$phrase])) {
                $byPhrase[$phrase] = $this->emptyCounts();
                $byPhrase[$phrase]['_total'] = 0;
            }
            foreach (($q['counts'] ?? []) as $type => $n) {
                $byPhrase[$phrase][$type] = ($byPhrase[$phrase][$type] ?? 0) + (int) $n;
                $byPhrase[$phrase]['_total'] += (int) $n;
            }
        }

        $out = [];
        $i = 1;
        foreach ($byPhrase as $phrase => $counts) {
            $total = (int) ($counts['_total'] ?? 0);
            unset($counts['_total']);
            $out[] = [
                'n' => $i++,
                'phrase' => $phrase,
                'total' => $total,
                'counts' => $counts,
                'mix' => $this->countsToMix($counts, $total),
            ];
        }

        return $out;
    }

    /**
     * @param array<string, array{count: int, type: string, in_catalog: bool}> $hostStats
     * @return array<int, array<string, mixed>>
     */
    private function buildFrequentHosts(array $hostStats): array
    {
        uasort($hostStats, static function ($a, $b) {
            return ($b['count'] ?? 0) <=> ($a['count'] ?? 0);
        });

        $out = [];
        foreach ($hostStats as $host => $meta) {
            if ((int) ($meta['count'] ?? 0) < 1) {
                continue;
            }
            $out[] = [
                'host' => $host,
                'count' => (int) $meta['count'],
                'in_catalog' => ! empty($meta['in_catalog']),
                'type' => (string) ($meta['type'] ?? 'unknown'),
            ];
            if (count($out) >= 50) {
                break;
            }
        }

        return $out;
    }

    public function normalizeDepth(int $depth): int
    {
        $allowed = config('cabinet-site-types.depths', [3, 5, 10, 20, 30]);
        if (! is_array($allowed) || $allowed === []) {
            return 10;
        }
        if (in_array($depth, $allowed, true)) {
            return $depth;
        }

        return (int) config('cabinet-site-types.default_depth', 10);
    }

    /**
     * @param array<string, array<int, string>> $custom
     * @return array{priority: array<int, string>, domains: array<string, string>}
     */
    public function buildCatalog(array $custom = []): array
    {
        $priority = config('cabinet-site-types.match_priority', []);
        if (! is_array($priority) || $priority === []) {
            $priority = array_keys(config('cabinet-site-types.categories', []));
        }

        /** @var array<string, string> $domainToType */
        $domainToType = [];
        $categories = config('cabinet-site-types.categories', []);

        // Lower priority first, then higher overwrites — last wins = higher priority.
        $ordered = array_reverse(array_values($priority));
        foreach ($ordered as $type) {
            $base = $categories[$type]['domains'] ?? [];
            if (! is_array($base)) {
                $base = [];
            }
            $extra = $custom[$type] ?? [];
            if (! is_array($extra)) {
                $extra = [];
            }
            foreach (array_merge($base, $extra) as $domain) {
                $host = $this->normalizeDomain((string) $domain);
                if ($host === '') {
                    continue;
                }
                $domainToType[$host] = $type;
            }
        }

        return [
            'priority' => array_values($priority),
            'domains' => $domainToType,
        ];
    }

    /**
     * @param array{priority: array<int, string>, domains: array<string, string>} $catalog
     */
    public function classifyDomain(string $domain, array $catalog): string
    {
        $host = $this->normalizeDomain($domain);
        if ($host === '') {
            return 'unknown';
        }

        $map = $catalog['domains'] ?? [];
        if (isset($map[$host])) {
            return $map[$host];
        }

        // Longest suffix match (sub.domain.ru → domain.ru)
        $best = '';
        $bestType = 'unknown';
        foreach ($map as $known => $type) {
            if ($known === '') {
                continue;
            }
            if ($host === $known || Str::endsWith($host, '.' . $known)) {
                if (mb_strlen($known) > mb_strlen($best)) {
                    $best = $known;
                    $bestType = $type;
                }
            }
        }

        return $bestType;
    }

    public function hostFromUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'http://' . $url;
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return '';
        }

        return $this->normalizeDomain($host);
    }

    public function normalizeDomain(string $domain): string
    {
        $domain = trim(mb_strtolower($domain));
        $domain = preg_replace('#^https?://#i', '', $domain) ?? $domain;
        $domain = preg_replace('#/.*$#', '', $domain) ?? $domain;
        $domain = preg_replace('#:\d+$#', '', $domain) ?? $domain;
        $domain = preg_replace('/^www\./i', '', $domain) ?? $domain;

        return trim($domain, ". \t\n\r\0\x0B");
    }

    /**
     * @param array<int, string>|string $raw
     * @return array<int, string>
     */
    public function parseDomainList($raw): array
    {
        if (is_array($raw)) {
            $lines = $raw;
        } else {
            $lines = preg_split('/\r\n|\r|\n|,|;/u', (string) $raw) ?: [];
        }

        $out = [];
        $seen = [];
        foreach ($lines as $line) {
            $host = $this->normalizeDomain((string) $line);
            if ($host === '' || isset($seen[$host])) {
                continue;
            }
            $seen[$host] = true;
            $out[] = $host;
        }

        return $out;
    }

    /**
     * @return array<string, int>
     */
    private function emptyCounts(): array
    {
        $counts = ['unknown' => 0];
        foreach (array_keys(config('cabinet-site-types.categories', [])) as $type) {
            $counts[$type] = 0;
        }

        return $counts;
    }

    /**
     * @param array<string, int> $counts
     * @return array<string, float>
     */
    private function countsToMix(array $counts, int $total): array
    {
        $mix = [];
        foreach ($counts as $type => $n) {
            $mix[$type] = $total > 0 ? round(($n / $total) * 100, 1) : 0.0;
        }

        return $mix;
    }

    /**
     * @param array<string, int> $counts
     * @return array{code: string, label: string, hint: string}
     */
    private function verdictFromCounts(array $counts, int $total): array
    {
        if ($total <= 0) {
            return [
                'code' => 'empty',
                'label' => 'Нет данных',
                'hint' => 'Выдача пуста или XML не ответил.',
            ];
        }

        $commercial = ($counts['aggregators'] ?? 0) + ($counts['ecommerce'] ?? 0) + ($counts['organizations'] ?? 0);
        $info = ($counts['content'] ?? 0) + ($counts['news'] ?? 0);
        $social = ($counts['social'] ?? 0) + ($counts['reviews'] ?? 0);
        $unknown = $counts['unknown'] ?? 0;

        $cShare = $commercial / $total;
        $iShare = $info / $total;
        $sShare = $social / $total;
        $uShare = $unknown / $total;

        if ($uShare >= 0.7) {
            return [
                'code' => 'unknown_heavy',
                'label' => 'Мало типизированных',
                'hint' => 'Много доменов без явных признаков типа. Можно дополнить свои списки или перепроверить.',
            ];
        }
        if ($cShare >= 0.55 && $cShare - $iShare >= 0.15) {
            return [
                'code' => 'commercial',
                'label' => 'Коммерческая выдача',
                'hint' => 'Доминируют агрегаторы, магазины и сайты организаций.',
            ];
        }
        if ($iShare >= 0.55 && $iShare - $cShare >= 0.15) {
            return [
                'code' => 'informational',
                'label' => 'Информационная выдача',
                'hint' => 'В топе в основном контент и новости.',
            ];
        }
        if ($sShare >= 0.4) {
            return [
                'code' => 'social_reviews',
                'label' => 'Соцсети и отзывы',
                'hint' => 'Сильная доля соцсетей и отзовиков — проверяйте бренд-SERP.',
            ];
        }

        return [
            'code' => 'mixed',
            'label' => 'Смешанная выдача',
            'hint' => 'Нет явного доминирования одного типа — смотрите доли по категориям.',
        ];
    }

    /**
     * @return array<string, array{label: string, short: string, color: string, hint: string}>
     */
    private function categoriesMeta(): array
    {
        $meta = [];
        foreach (config('cabinet-site-types.categories', []) as $key => $cat) {
            $meta[$key] = [
                'label' => (string) ($cat['label'] ?? $key),
                'short' => (string) ($cat['short'] ?? $key),
                'color' => (string) ($cat['color'] ?? '#64748b'),
                'hint' => (string) ($cat['hint'] ?? ''),
            ];
        }
        $meta['unknown'] = [
            'label' => 'Не определён',
            'short' => '?',
            'color' => '#94a3b8',
            'hint' => 'Не нашли в каталоге и не распознали по URL/странице.',
        ];

        return $meta;
    }

    /**
     * @return array<int, string>
     */
    private function fetchSerpUrls(
        string $engine,
        string $lr,
        string $query,
        int $depth,
        bool &$hadError,
        int &$pageRequests
    ): array {
        $hadError = false;
        $pageRequests = 0;

        if ($engine === 'google') {
            return $this->fetchGoogleSerpUrls($lr, $query, $depth, $hadError, $pageRequests);
        }

        try {
            $pageRequests = 1;
            $xml = new SimplifiedXmlFacade($lr, $depth);
            $xml->setQuery($query);
            $chunk = $xml->getXMLResponse('yandex');

            return is_array($chunk) ? array_values($chunk) : [];
        } catch (\Throwable $e) {
            $hadError = true;
            report($e);

            return [];
        }
    }

    /**
     * Google: страница = ≤10 результатов, page=0,1,2…
     *
     * @return array<int, string>
     */
    private function fetchGoogleSerpUrls(
        string $lr,
        string $query,
        int $depth,
        bool &$hadError,
        int &$pageRequests
    ): array {
        $pages = self::googlePagesForDepth($depth);
        $urls = [];

        for ($page = 0; $page < $pages; $page++) {
            try {
                $pageRequests++;
                $xml = new SimplifiedXmlFacade($lr, self::GOOGLE_PAGE_SIZE);
                $xml->setQuery($query);
                $xml->setPage((string) $page);
                $chunk = $xml->getXMLResponse('google');
                $chunk = is_array($chunk) ? array_values($chunk) : [];
                foreach ($chunk as $url) {
                    $urls[] = $url;
                    if (count($urls) >= $depth) {
                        break 2;
                    }
                }
                // Google часто отдаёт <10 organic на странице — не стопаем пагинацию раньше depth.
                if (count($chunk) === 0) {
                    break;
                }
            } catch (\Throwable $e) {
                $hadError = true;
                report($e);
                break;
            }

            $pauseMs = max(0, (int) config('cabinet-site-types.request_pause_ms', 120));
            if ($pauseMs > 0 && $page + 1 < $pages) {
                usleep($pauseMs * 1000);
            }
        }

        if ($urls === [] && $pageRequests > 0) {
            // пустая выдача не всегда ошибка; hadError уже выставлен при exception
        }

        return $urls;
    }
}
