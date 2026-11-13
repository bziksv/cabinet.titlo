<?php

namespace App\Services;

use App\Classes\Xml\SimplifiedXmlFacade;
use App\Support\CompetitorSearchRegions;
use App\Support\CompetitorSerpDomainFilter;

/**
 * Геозависимость (2 региона), локализация и коммерциализация фраз.
 */
class PhraseCommerceService
{
    public const DEPTH = 20;

    /** Google XML: ≤10 URL на страницу. */
    public const GOOGLE_PAGE_SIZE = 10;

    /**
     * Лимит = число XML-запросов: (страницы на регион) × 2 региона × фразы.
     * Яндекс ТОП-20: 1×2; Google ТОП-20: 2×2.
     *
     * @param array<int, string> $engines
     */
    public static function estimateCost(int $phrasesCount, array $engines, ?int $depth = null): int
    {
        $phrasesCount = max(0, $phrasesCount);
        if ($phrasesCount === 0 || $engines === []) {
            return 0;
        }

        $depth = max(1, $depth ?? (int) config('cabinet-phrase-commerce.depth', self::DEPTH));
        $regions = max(1, (int) config('cabinet-phrase-commerce.regions_per_check', 2));
        $total = 0;

        foreach ($engines as $engine) {
            $pages = $engine === 'google' ? self::googlePagesForDepth($depth) : 1;
            $total += $phrasesCount * $pages * $regions;
        }

        return $total;
    }

    public static function googlePagesForDepth(int $depth): int
    {
        return max(1, (int) ceil(max(1, $depth) / self::GOOGLE_PAGE_SIZE));
    }

    /** Стоимость одной фразы для ПС (с учётом двух регионов). */
    public static function costPerPhraseForEngine(string $engine, ?int $depth = null): int
    {
        $depth = max(1, $depth ?? (int) config('cabinet-phrase-commerce.depth', self::DEPTH));
        $regions = max(1, (int) config('cabinet-phrase-commerce.regions_per_check', 2));
        $pages = $engine === 'google' ? self::googlePagesForDepth($depth) : 1;

        return $pages * $regions;
    }

    /**
     * @param array<int, string> $lines
     * @return array<int, string>
     */
    public function normalizePhrases(array $lines): array
    {
        $max = max(1, (int) config('cabinet-phrase-commerce.max_phrases', 200));
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
     *   yandex_lr: string,
     *   google_lr: string,
     *   yandex_lr2?: string,
     *   google_lr2?: string
     * } $params
     * @return array<string, mixed>
     */
    public function analyze(array $params): array
    {
        $phrases = $params['phrases'] ?? [];
        $engines = $params['engines'] ?? [];
        $depth = (int) config('cabinet-phrase-commerce.depth', self::DEPTH);
        $pauseMs = max(0, (int) config('cabinet-phrase-commerce.request_pause_ms', 120));

        $siteTypes = new SiteTypesService();
        $catalog = $siteTypes->buildCatalog([]);
        $probe = new SiteTypesPageClassifier();

        $rows = [];
        $requests = 0;
        $errors = 0;
        /** Один контрольный город на ПС за весь прогон — стабильнее сравнение по фразам. */
        $contrastByEngine = [];

        foreach ($phrases as $phrase) {
            foreach ($engines as $engine) {
                $engine = $engine === 'google' ? 'google' : 'yandex';
                $primaryLr = $engine === 'google'
                    ? (string) ($params['google_lr'] ?? config('cabinet-phrase-commerce.default_google_lr'))
                    : (string) ($params['yandex_lr'] ?? config('cabinet-phrase-commerce.default_yandex_lr'));
                if (! isset($contrastByEngine[$engine])) {
                    $contrastByEngine[$engine] = $this->resolveContrastLr($engine, $primaryLr, $params);
                }
                $contrastLr = $contrastByEngine[$engine];

                $err1 = false;
                $err2 = false;
                $n1 = 0;
                $n2 = 0;
                $urlsPrimary = $this->fetchSerp($engine, $primaryLr, $phrase, $depth, $err1, $n1);
                $requests += max(1, $n1);
                if ($pauseMs > 0) {
                    usleep($pauseMs * 1000);
                }
                $urlsContrast = $this->fetchSerp($engine, $contrastLr, $phrase, $depth, $err2, $n2);
                $requests += max(1, $n2);
                if ($err1 || $err2) {
                    $errors++;
                }

                // Коммерция/локализация — по основной выдаче (с HTML).
                // Контрольный регион — только для гео; типы без HTTP (иначе ×2 по времени).
                $classifiedPrimary = $this->classifyUrls($urlsPrimary, $siteTypes, $catalog, $probe, true);
                $classifiedContrast = $this->classifyUrls($urlsContrast, $siteTypes, $catalog, $probe, false);
                $commerce = $this->scoreCommerce($classifiedPrimary);
                $localization = $this->scoreLocalization($classifiedPrimary, $engine, $primaryLr);
                $geo = $this->scoreGeo($urlsPrimary, $urlsContrast);
                $sharedHosts = $geo['shared_hosts'] ?? [];

                $rows[] = [
                    'phrase' => $phrase,
                    'engine' => $engine,
                    'region' => $primaryLr,
                    'region_contrast' => $contrastLr,
                    'region_name' => $this->regionName($engine, $primaryLr),
                    'region_contrast_name' => $this->regionName($engine, $contrastLr),
                    'geo' => $geo,
                    'localization' => $localization,
                    'commerce' => $commerce,
                    'serp_count' => count($urlsPrimary),
                    'serp_contrast_count' => count($urlsContrast),
                    'serp_primary' => $this->serpRows($classifiedPrimary, $sharedHosts),
                    'serp_contrast' => $this->serpRows($classifiedContrast, $sharedHosts),
                    'types' => $this->typeCounts($classifiedPrimary),
                    'error' => ($err1 && $urlsPrimary === []) || ($err2 && $urlsContrast === []),
                ];

                if ($pauseMs > 0) {
                    usleep($pauseMs * 1000);
                }
            }
        }

        return [
            'cost' => self::estimateCost(count($phrases), $engines),
            'requests' => $requests,
            'errors' => $errors,
            'depth' => $depth,
            'rows' => $rows,
            'summary' => $this->buildSummary($rows),
            'contrast_regions' => $contrastByEngine,
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    private function resolveContrastLr(string $engine, string $primaryLr, array $params): string
    {
        $customKey = $engine === 'google' ? 'google_lr2' : 'yandex_lr2';
        $custom = trim((string) ($params[$customKey] ?? ''));
        if ($custom !== '' && $custom !== $primaryLr) {
            return $custom;
        }

        $pool = $this->contrastCityPool($engine, $primaryLr);
        if ($pool === []) {
            // На крайний случай — любой другой из топ-10
            $pool = $this->contrastCityPool($engine, '');
        }
        if ($pool === []) {
            return $engine === 'google'
                ? (string) config('cabinet-phrase-commerce.default_google_lr', '1011969')
                : (string) config('cabinet-phrase-commerce.default_yandex_lr', '213');
        }

        $pick = $pool[array_rand($pool)];

        return (string) $pick['lr'];
    }

    /**
     * Кандидаты контрольного региона из топ-10 РФ (без выбранного города).
     *
     * @return array<int, array{lr: string, name: string, slug: string}>
     */
    private function contrastCityPool(string $engine, string $primaryLr): array
    {
        $cities = config('cabinet-phrase-commerce.top_rf_cities', []);
        if (! is_array($cities) || $cities === []) {
            return [];
        }

        $primarySlug = $this->citySlugForLr($engine, $primaryLr);
        $pool = [];

        foreach ($cities as $city) {
            if (! is_array($city)) {
                continue;
            }
            $slug = (string) ($city['slug'] ?? '');
            if ($primarySlug !== '' && $slug === $primarySlug) {
                continue;
            }
            $lr = $this->cityLr($engine, $city);
            if ($lr === '' || $lr === $primaryLr) {
                continue;
            }
            $pool[] = [
                'lr' => $lr,
                'name' => (string) ($city['name'] ?? $lr),
                'slug' => $slug,
            ];
        }

        return $pool;
    }

    /**
     * @param array<string, mixed> $city
     */
    private function cityLr(string $engine, array $city): string
    {
        if ($engine === 'google') {
            $ids = $city['google'] ?? [];
            if (is_string($ids) || is_int($ids)) {
                return (string) $ids;
            }
            if (is_array($ids) && $ids !== []) {
                return (string) $ids[0];
            }

            return '';
        }

        return (string) ($city['yandex'] ?? '');
    }

    private function citySlugForLr(string $engine, string $lr): string
    {
        $lr = (string) $lr;
        if ($lr === '') {
            return '';
        }
        $cities = config('cabinet-phrase-commerce.top_rf_cities', []);
        if (! is_array($cities)) {
            return '';
        }

        foreach ($cities as $city) {
            if (! is_array($city)) {
                continue;
            }
            if ($engine === 'google') {
                $ids = $city['google'] ?? [];
                if (! is_array($ids)) {
                    $ids = [(string) $ids];
                }
                foreach ($ids as $id) {
                    if ((string) $id === $lr) {
                        return (string) ($city['slug'] ?? '');
                    }
                }
            } elseif ((string) ($city['yandex'] ?? '') === $lr) {
                return (string) ($city['slug'] ?? '');
            }
        }

        return '';
    }

    /**
     * @param array<int, array{url: string, domain: string, type: string}> $classified
     * @param array<int, string> $sharedHosts
     * @return array<int, array{pos: int, url: string, domain: string, type: string, shared: bool}>
     */
    private function serpRows(array $classified, array $sharedHosts): array
    {
        $shared = array_fill_keys($sharedHosts, true);
        $out = [];
        $pos = 1;
        foreach ($classified as $row) {
            $domain = (string) ($row['domain'] ?? '');
            $out[] = [
                'pos' => $pos++,
                'url' => (string) ($row['url'] ?? ''),
                'domain' => $domain,
                'type' => (string) ($row['type'] ?? 'unknown'),
                'shared' => $domain !== '' && isset($shared[$domain]),
            ];
        }

        return $out;
    }

    private function regionName(string $engine, string $lr): string
    {
        $found = CompetitorSearchRegions::find($engine, $lr);
        if ($found && ! empty($found['name'])) {
            return (string) $found['name'];
        }

        return $lr;
    }

    /**
     * @return array<int, string>
     */
    private function fetchSerp(
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
            return $this->fetchGoogleSerp($lr, $query, $depth, $hadError, $pageRequests);
        }

        try {
            $pageRequests = 1;
            $xml = new SimplifiedXmlFacade($lr, $depth);
            $xml->setQuery($query);
            $chunk = $xml->getXMLResponse('yandex');
            $urls = is_array($chunk) ? array_values($chunk) : [];

            return array_slice($urls, 0, $depth);
        } catch (\Throwable $e) {
            $hadError = true;
            report($e);

            return [];
        }
    }

    /**
     * Google: ≤10 URL на страницу, page=0,1,…
     *
     * @return array<int, string>
     */
    private function fetchGoogleSerp(
        string $lr,
        string $query,
        int $depth,
        bool &$hadError,
        int &$pageRequests
    ): array {
        $pages = self::googlePagesForDepth($depth);
        $urls = [];
        $pauseMs = max(0, (int) config('cabinet-phrase-commerce.request_pause_ms', 120));

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

            if ($pauseMs > 0 && $page + 1 < $pages) {
                usleep($pauseMs * 1000);
            }
        }

        return array_slice($urls, 0, $depth);
    }

    /**
     * @param array<int, string> $urls
     * @return array<int, array{url: string, domain: string, type: string}>
     */
    private function classifyUrls(
        array $urls,
        SiteTypesService $siteTypes,
        array $catalog,
        SiteTypesPageClassifier $probe,
        bool $withHtml = true
    ): array {
        $draft = [];
        $prefetch = [];
        foreach ($urls as $url) {
            $domain = $siteTypes->hostFromUrl($url);
            $catalogType = $siteTypes->classifyDomain($domain, $catalog);
            $draft[] = ['url' => $url, 'domain' => $domain, 'catalog_type' => $catalogType];
            if ($withHtml && $catalogType === 'unknown' && $domain !== '') {
                $prefetch[] = ['url' => $url, 'domain' => $domain];
            }
        }
        if ($withHtml) {
            $probe->prefetchHtml($prefetch);
        }

        $out = [];
        foreach ($draft as $item) {
            $probed = $withHtml
                ? $probe->classify($item['url'], $item['domain'], $item['catalog_type'])
                : $probe->classifyWithoutHtml($item['url'], $item['domain'], $item['catalog_type']);
            $out[] = [
                'url' => $item['url'],
                'domain' => $item['domain'],
                'type' => $probed['type'] ?? 'unknown',
            ];
        }

        return $out;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    public function summaryFromRows(array $rows): array
    {
        return $this->buildSummary($rows);
    }

    /**
     * @param array<int, array{url: string, domain: string, type: string}> $classified
     * @return array{pct: float, label: string, code: string, commercial: int, total: int}
     */
    private function scoreCommerce(array $classified): array
    {
        $total = count($classified);
        $commercial = 0;
        foreach ($classified as $row) {
            if (in_array($row['type'], ['aggregators', 'ecommerce', 'organizations'], true)) {
                $commercial++;
            }
        }
        $pct = $total > 0 ? round(($commercial / $total) * 100, 1) : 0.0;
        $high = (float) config('cabinet-phrase-commerce.commerce_high', 60);
        $low = (float) config('cabinet-phrase-commerce.commerce_low', 35);

        if ($pct >= $high) {
            $code = 'commercial';
            $label = 'Коммерческий';
        } elseif ($pct <= $low) {
            $code = 'informational';
            $label = 'Информационный';
        } else {
            $code = 'mixed';
            $label = 'Смешанный';
        }

        return [
            'pct' => $pct,
            'label' => $label,
            'code' => $code,
            'commercial' => $commercial,
            'total' => $total,
        ];
    }

    /**
     * @param array<int, array{url: string, domain: string, type: string}> $classified
     * @return array{pct: float, label: string, code: string, local: int, total: int}
     */
    private function scoreLocalization(array $classified, string $engine, string $lr): array
    {
        $markers = $this->localizationMarkers($engine, $lr);
        $total = count($classified);
        $local = 0;
        foreach ($classified as $row) {
            $hay = mb_strtolower($row['domain'] . ' ' . $row['url']);
            foreach ($markers as $m) {
                if ($m !== '' && mb_strpos($hay, $m) !== false) {
                    $local++;
                    break;
                }
            }
        }
        $pct = $total > 0 ? round(($local / $total) * 100, 1) : 0.0;
        $high = (float) config('cabinet-phrase-commerce.localization_high', 50);
        $low = (float) config('cabinet-phrase-commerce.localization_low', 15);

        if ($pct >= $high) {
            $code = 'high';
            $label = 'Высокая';
        } elseif ($pct <= $low) {
            $code = 'low';
            $label = 'Низкая';
        } else {
            $code = 'medium';
            $label = 'Средняя';
        }

        return [
            'pct' => $pct,
            'label' => $label,
            'code' => $code,
            'local' => $local,
            'total' => $total,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function localizationMarkers(string $engine, string $lr): array
    {
        $name = $this->regionName($engine, $lr);
        $markers = [];
        $nameLower = mb_strtolower($name);
        // Убрать хвост «[213]» / область
        $nameLower = trim(preg_replace('/\[[^\]]*\]/u', '', $nameLower) ?? '');
        $nameLower = trim(preg_replace('/,.*/u', '', $nameLower) ?? '');
        if ($nameLower !== '') {
            $markers[] = $nameLower;
            $markers[] = str_replace([' ', '-'], '', $nameLower);
        }

        $translit = [
            'москва' => ['moscow', 'msk', 'moskva'],
            'санкт-петербург' => ['spb', 'petersburg', 'peterburg', 'leningrad'],
            'санкт петербург' => ['spb', 'petersburg'],
            'новосибирск' => ['novosibirsk', 'nsk'],
            'екатеринбург' => ['ekaterinburg', 'yekaterinburg', 'eburg'],
            'казань' => ['kazan'],
            'нижний новгород' => ['nnovgorod', 'nizhniy', 'nn'],
            'челябинск' => ['chelyabinsk'],
            'самара' => ['samara'],
            'омск' => ['omsk'],
            'ростов' => ['rostov'],
            'уфа' => ['ufa'],
            'краснодар' => ['krasnodar'],
            'воронеж' => ['voronezh'],
            'пермь' => ['perm'],
            'волгоград' => ['volgograd'],
        ];
        foreach ($translit as $ru => $eng) {
            if (mb_strpos($nameLower, $ru) !== false) {
                foreach ($eng as $e) {
                    $markers[] = $e;
                }
            }
        }

        // Частые региональные зоны
        if (mb_strpos($nameLower, 'москва') !== false) {
            $markers[] = '.msk.';
            $markers[] = 'moscow.';
        }
        if (mb_strpos($nameLower, 'петербург') !== false || mb_strpos($nameLower, 'санкт') !== false) {
            $markers[] = '.spb.';
            $markers[] = 'spb.';
        }

        return array_values(array_unique(array_filter($markers)));
    }

    /**
     * Сходство выдач: |A∩B| / min(|A|,|B|).
     * Так нерезанный ТОП (16 vs 20) не раздувает «геозависимость», как Jaccard по объединению.
     *
     * @param array<int, string> $urlsA
     * @param array<int, string> $urlsB
     * @return array{
     *   code: string,
     *   label: string,
     *   overlap: float,
     *   overlap_pct: int,
     *   shared: int,
     *   base_count: int,
     *   hosts_primary: int,
     *   hosts_contrast: int,
     *   incomplete: bool,
     *   shared_hosts: array<int, string>,
     *   only_primary: array<int, string>,
     *   only_contrast: array<int, string>
     * }
     */
    private function scoreGeo(array $urlsA, array $urlsB): array
    {
        $hostsA = $this->hostSet($urlsA);
        $hostsB = $this->hostSet($urlsB);
        $shared = array_values(array_intersect($hostsA, $hostsB));
        $onlyA = array_values(array_diff($hostsA, $hostsB));
        $onlyB = array_values(array_diff($hostsB, $hostsA));
        $nA = count($hostsA);
        $nB = count($hostsB);
        $baseCount = min($nA, $nB);
        $overlap = $baseCount > 0 ? count($shared) / $baseCount : 0.0;
        $minIndependent = (float) config('cabinet-phrase-commerce.geo_independent_min_overlap', 0.4);
        $depth = (int) config('cabinet-phrase-commerce.depth', self::DEPTH);
        $incomplete = $nA < $depth || $nB < $depth;

        $base = [
            'shared_hosts' => $shared,
            'only_primary' => $onlyA,
            'only_contrast' => $onlyB,
            'base_count' => $baseCount,
            'hosts_primary' => $nA,
            'hosts_contrast' => $nB,
            'incomplete' => $incomplete,
        ];

        if ($nA === 0 || $nB === 0) {
            return array_merge($base, [
                'code' => 'unknown',
                'label' => 'Нет данных',
                'overlap' => 0.0,
                'overlap_pct' => 0,
                'shared' => 0,
            ]);
        }

        if ($overlap >= $minIndependent) {
            return array_merge($base, [
                'code' => 'geo_independent',
                'label' => 'Геонезависимый',
                'overlap' => round($overlap, 3),
                'overlap_pct' => (int) round($overlap * 100),
                'shared' => count($shared),
            ]);
        }

        return array_merge($base, [
            'code' => 'geo_dependent',
            'label' => 'Геозависимый',
            'overlap' => round($overlap, 3),
            'overlap_pct' => (int) round($overlap * 100),
            'shared' => count($shared),
        ]);
    }

    /**
     * @param array<int, string> $urls
     * @return array<int, string>
     */
    private function hostSet(array $urls): array
    {
        $siteTypes = new SiteTypesService();
        $hosts = [];
        foreach ($urls as $url) {
            $h = $siteTypes->hostFromUrl($url);
            if ($h === '' || CompetitorSerpDomainFilter::hostMatchesExcludedList($h, CompetitorSerpDomainFilter::excludedDomains())) {
                continue;
            }
            $hosts[] = $h;
        }

        return array_values(array_unique($hosts));
    }

    /**
     * @param array<int, array{type: string}> $classified
     * @return array<string, int>
     */
    private function typeCounts(array $classified): array
    {
        $counts = [];
        foreach ($classified as $row) {
            $t = $row['type'] ?? 'unknown';
            $counts[$t] = ($counts[$t] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function buildSummary(array $rows): array
    {
        $geoDep = 0;
        $geoInd = 0;
        $comm = 0;
        $info = 0;
        $n = count($rows);
        $locSum = 0;
        $comSum = 0;

        foreach ($rows as $r) {
            $g = $r['geo']['code'] ?? '';
            if ($g === 'geo_dependent') {
                $geoDep++;
            } elseif ($g === 'geo_independent') {
                $geoInd++;
            }
            $c = $r['commerce']['code'] ?? '';
            if ($c === 'commercial') {
                $comm++;
            } elseif ($c === 'informational') {
                $info++;
            }
            $locSum += (float) ($r['localization']['pct'] ?? 0);
            $comSum += (float) ($r['commerce']['pct'] ?? 0);
        }

        return [
            'total' => $n,
            'geo_dependent' => $geoDep,
            'geo_independent' => $geoInd,
            'commercial' => $comm,
            'informational' => $info,
            'avg_localization' => $n > 0 ? round($locSum / $n, 1) : 0,
            'avg_commerce' => $n > 0 ? round($comSum / $n, 1) : 0,
        ];
    }
}
