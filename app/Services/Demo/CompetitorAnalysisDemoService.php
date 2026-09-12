<?php

namespace App\Services\Demo;

use App\Classes\Xml\SimplifiedXmlFacade;
use App\Services\Competitor\CompetitorGeoDependency;
use App\Support\CompetitorSearchRegions;
use App\Support\CompetitorSerpDomainFilter;
use App\Support\GoogleGeoRegions;
use App\Support\YandexLrRegions;

class CompetitorAnalysisDemoService
{
    public const MODULE = 'analiz-konkurentov';

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return config('cabinet-competitor-analysis.demo', []);
    }

    /**
     * @return array<int, array{id: string, label: string}>
     */
    public static function regionsForUi(string $engine = 'yandex'): array
    {
        $cfg = self::config();
        $engine = CompetitorSearchRegions::normalizeEngine($engine);

        if ($engine === 'google') {
            $ids = $cfg['allowed_google_region_ids'] ?? ['1011969', '1012040', '1012077', '1011984', '1012052'];
            $out = [];
            foreach ($ids as $id) {
                $id = (string) $id;
                $item = GoogleGeoRegions::find($id);
                $out[] = [
                    'id' => $id,
                    'label' => $item['name'] ?? $id,
                ];
            }

            return $out;
        }

        $ids = $cfg['allowed_yandex_region_ids'] ?? $cfg['allowed_region_ids'] ?? ['213', '2', '193', '65', '54'];
        $out = [];
        foreach ($ids as $id) {
            $id = (string) $id;
            $item = YandexLrRegions::find($id);
            $out[] = [
                'id' => $id,
                'label' => $item['name'] ?? $id,
            ];
        }

        return $out;
    }

    /**
     * @param array{
     *   phrase?: string,
     *   region_id?: string,
     *   compare_region_id?: string,
     *   search_engine?: string
     * } $input
     * @return array{ok: true, payload: array<string, mixed>}|array{ok: false, status: int, error: string, message: string}
     */
    public static function validate(array $input): array
    {
        $cfg = self::config();
        $phrase = trim((string) ($input['phrase'] ?? ''));
        $minLen = (int) ($cfg['min_phrase_length'] ?? 2);
        $maxLen = (int) ($cfg['max_phrase_length'] ?? 120);
        $engine = CompetitorSearchRegions::normalizeEngine((string) ($input['search_engine'] ?? 'yandex'));

        if ($phrase === '') {
            return self::fail(422, 'validation', 'Введите ключевую фразу');
        }
        if (mb_strlen($phrase) < $minLen) {
            return self::fail(422, 'validation', sprintf('Минимум %d символа в фразе', $minLen));
        }
        if (mb_strlen($phrase) > $maxLen) {
            return self::fail(
                422,
                'validation',
                sprintf('В демо до %d символов в одной фразе. В кабинете — до 40 фраз за запуск.', $maxLen)
            );
        }

        $allowedEngines = $cfg['search_engines'] ?? ['yandex', 'google'];
        if (!in_array($engine, $allowedEngines, true)) {
            return self::fail(422, 'validation', 'Выберите поисковую систему');
        }

        $regionId = trim((string) ($input['region_id'] ?? ''));
        $allowedRegions = array_column(self::regionsForUi($engine), 'id');
        if ($regionId === '' || !in_array($regionId, $allowedRegions, true)) {
            return self::fail(422, 'validation', 'Выберите город из списка');
        }

        $compareId = trim((string) ($input['compare_region_id'] ?? ''));
        if ($compareId !== '' && !in_array($compareId, $allowedRegions, true)) {
            return self::fail(422, 'validation', 'Выберите второй город для сравнения');
        }
        if ($compareId !== '' && $compareId === $regionId) {
            return self::fail(422, 'validation', 'Для сравнения выберите другой город');
        }

        return [
            'ok' => true,
            'payload' => [
                'phrase' => $phrase,
                'region_id' => $regionId,
                'compare_region_id' => $compareId,
                'search_engine' => $engine,
            ],
        ];
    }

    /**
     * @param array{
     *   phrase: string,
     *   region_id: string,
     *   compare_region_id: string,
     *   search_engine: string
     * } $input
     * @return array<string, mixed>|array{ok: false, status: int, error: string, message: string}
     */
    public static function analyze(array $input): array
    {
        $cfg = self::config();
        $topCount = (int) ($cfg['top_count'] ?? 10);
        $serpLimit = (int) ($cfg['serp_rows'] ?? 10);
        $phrase = $input['phrase'];
        $engine = $input['search_engine'];
        $regionId = $input['region_id'];
        $compareId = $input['compare_region_id'];

        $urlsPrimary = self::fetchSerpUrls($engine, $regionId, $phrase, $topCount);
        if ($urlsPrimary === null) {
            return self::fail(
                422,
                'empty_serp',
                'Не удалось получить выдачу: сервис XMLStock занят или временно недоступен. Подождите 20–30 секунд и нажмите «Показать выдачу» ещё раз.'
            );
        }

        $rows = self::mapSerpPayload($urlsPrimary, $serpLimit);
        if ($rows['shown'] === 0 && $rows['excluded_count'] === 0) {
            return self::fail(422, 'empty_serp', 'Выдача пуста. Попробуйте другую фразу.');
        }

        $regionPrimary = self::regionPayload($engine, $regionId);
        $geo = null;
        $compareRegion = null;

        if ($compareId !== '') {
            $urlsCompare = self::fetchSerpUrls($engine, $compareId, $phrase, $topCount);
            if ($urlsCompare === null) {
                return self::fail(
                    422,
                    'empty_serp',
                    'Не удалось получить выдачу для второго города. Попробуйте позже.'
                );
            }
            $compareRegion = self::regionPayload($engine, $compareId);
            $geo = self::buildGeoPreview($engine, $phrase, $regionPrimary, $compareRegion, $urlsPrimary, $urlsCompare);
        }

        return [
            'phrase' => $phrase,
            'engine' => $engine,
            'engine_label' => CompetitorSearchRegions::engineLabel($engine),
            'region' => $regionPrimary,
            'compare_region' => $compareRegion,
            'top_count' => $topCount,
            'serp' => $rows,
            'geo' => $geo,
        ];
    }

    /**
     * @return array<int, string>|null
     */
    protected static function fetchSerpUrls(string $engine, string $regionId, string $phrase, int $topCount): ?array
    {
        $demoCfg = self::config();
        $configKeys = [
            'xmlstock_hybrid_retry',
            'xmlstock_hybrid_max_attempts',
            'xmlstock_hybrid_sleep_sec',
        ];
        $prev = [];
        foreach ($configKeys as $key) {
            $prev[$key] = config('cabinet-competitor-analysis.' . $key);
        }

        config([
            'cabinet-competitor-analysis.xmlstock_hybrid_retry' => (bool) (
                $demoCfg['xmlstock_hybrid_retry'] ?? true
            ),
            'cabinet-competitor-analysis.xmlstock_hybrid_max_attempts' => (int) (
                $demoCfg['xmlstock_hybrid_max_attempts'] ?? 4
            ),
            'cabinet-competitor-analysis.xmlstock_hybrid_sleep_sec' => (int) (
                $demoCfg['xmlstock_hybrid_sleep_sec'] ?? 18
            ),
        ]);

        try {
            $xml = new SimplifiedXmlFacade($regionId, $topCount);
            $xml->setQuery($phrase);
            $urls = $xml->getXMLResponse($engine);
        } finally {
            foreach ($configKeys as $key) {
                config(['cabinet-competitor-analysis.' . $key => $prev[$key]]);
            }
        }

        if (!is_array($urls) || count($urls) === 0) {
            return null;
        }

        return $urls;
    }

    /**
     * @param array<int, string> $urls
     * @return array{
     *   rows: array<int, array{position: int, serp_position: int, url: string, host: string, path: string, excluded: false}>,
     *   excluded_rows: array<int, array{serp_position: int, url: string, host: string, path: string, excluded: true}>,
     *   total: int,
     *   shown: int,
     *   excluded_count: int,
     *   excludes_aggregators: true,
     *   excluded_domains_sample: array<int, string>,
     *   excluded_domains_total: int
     * }
     */
    protected static function mapSerpPayload(array $urls, int $limit): array
    {
        $excludedBreakdown = CompetitorSerpDomainFilter::excludedDomainsBreakdown();
        $included = [];
        $excluded = [];
        $serpPosition = 0;

        foreach (array_slice($urls, 0, $limit) as $url) {
            if (!is_string($url) || $url === '') {
                continue;
            }
            $serpPosition++;
            $normalized = self::normalizeUrl($url);
            $host = parse_url($normalized, PHP_URL_HOST) ?: $normalized;
            $path = parse_url($normalized, PHP_URL_PATH) ?: '/';
            $path = $path === '' ? '/' : $path;
            $isExcluded = CompetitorSerpDomainFilter::isExcludedUrl($normalized);

            if ($isExcluded) {
                $excluded[] = [
                    'serp_position' => $serpPosition,
                    'url' => $normalized,
                    'host' => $host,
                    'path' => $path,
                    'excluded' => true,
                ];
                continue;
            }

            $included[] = [
                'position' => count($included) + 1,
                'serp_position' => $serpPosition,
                'url' => $normalized,
                'host' => $host,
                'path' => $path,
                'excluded' => false,
            ];
        }

        return [
            'rows' => $included,
            'excluded_rows' => $excluded,
            'total' => $serpPosition,
            'shown' => count($included),
            'excluded_count' => count($excluded),
            'excludes_aggregators' => true,
            'excluded_domains_sample' => array_slice($excludedBreakdown['from_defaults'], 0, 8),
            'excluded_domains_total' => count($excludedBreakdown['all']),
        ];
    }

    /**
     * @deprecated use mapSerpPayload
     * @param array<int, string> $urls
     * @return array<int, array{position: int, url: string, host: string, path: string}>
     */
    protected static function mapSerpRows(array $urls, int $limit): array
    {
        $rows = [];
        $position = 1;
        foreach (array_slice($urls, 0, $limit) as $url) {
            if (!is_string($url) || $url === '') {
                continue;
            }
            $normalized = self::normalizeUrl($url);
            $host = parse_url($normalized, PHP_URL_HOST) ?: $normalized;
            $path = parse_url($normalized, PHP_URL_PATH) ?: '/';
            $rows[] = [
                'position' => $position,
                'url' => $normalized,
                'host' => $host,
                'path' => $path === '' ? '/' : $path,
            ];
            $position++;
        }

        return $rows;
    }

    /**
     * @return array{id: string, label: string, tab_label: string}
     */
    protected static function regionPayload(string $engine, string $regionId): array
    {
        $label = $regionId;
        if ($engine === 'google') {
            $item = GoogleGeoRegions::find($regionId);
            $label = $item['name'] ?? $regionId;
        } else {
            $item = YandexLrRegions::find($regionId);
            $label = $item['name'] ?? $regionId;
        }

        return [
            'id' => $regionId,
            'label' => $label,
            'tab_label' => CompetitorSearchRegions::engineLabel($engine) . ' · ' . $label,
        ];
    }

    /**
     * @param array{id: string, label: string, tab_label: string} $regionA
     * @param array{id: string, label: string, tab_label: string} $regionB
     * @param array<int, string> $urlsA
     * @param array<int, string> $urlsB
     * @return array<string, mixed>|null
     */
    protected static function buildGeoPreview(
        string $engine,
        string $phrase,
        array $regionA,
        array $regionB,
        array $urlsA,
        array $urlsB
    ): ?array {
        $keyA = CompetitorSearchRegions::regionKey($engine, $regionA['id']);
        $keyB = CompetitorSearchRegions::regionKey($engine, $regionB['id']);

        $byRegion = [
            $keyA => ['analysedSites' => self::phraseSitesFromUrls($phrase, $urlsA)],
            $keyB => ['analysedSites' => self::phraseSitesFromUrls($phrase, $urlsB)],
        ];
        $regionsMeta = [
            ['key' => $keyA, 'engine' => $engine, 'id' => $regionA['id'], 'tabLabel' => $regionA['tab_label']],
            ['key' => $keyB, 'engine' => $engine, 'id' => $regionB['id'], 'tabLabel' => $regionB['tab_label']],
        ];

        $payload = (new CompetitorGeoDependency())->analyze($byRegion, $regionsMeta);
        if ($payload === null || empty($payload['phrases'])) {
            return null;
        }

        $row = $payload['phrases'][0];
        $pair = null;
        if (!empty($row['pairs'][0])) {
            $pair = $row['pairs'][0];
        }

        $sharedUrls = [];
        if (is_array($pair['shared_urls'] ?? null)) {
            $sharedUrls = array_slice($pair['shared_urls'], 0, 5);
        }

        $excluded = CompetitorSerpDomainFilter::excludedDomainsBreakdown();

        return [
            'overlap_pct' => $row['overlap_pct'] ?? null,
            'status' => $row['status'] ?? 'partial',
            'status_label' => self::geoStatusLabel((string) ($row['status'] ?? 'partial')),
            'region_a' => $regionA,
            'region_b' => $regionB,
            'shared_count' => $pair['shared_count'] ?? 0,
            'shared_urls' => $sharedUrls,
            'count_a' => $pair['count_a'] ?? count($urlsA),
            'count_b' => $pair['count_b'] ?? count($urlsB),
            'overlap_pct_a' => $pair['overlap_pct_a'] ?? null,
            'overlap_pct_b' => $pair['overlap_pct_b'] ?? null,
            'excludes_aggregators' => true,
            'excluded_domains_sample' => array_slice($excluded['from_defaults'], 0, 8),
            'excluded_domains_total' => count($excluded['all']),
        ];
    }

    /**
     * @param array<int, string> $urls
     * @return array<string, array{mainPage: bool}>
     */
    protected static function phraseSitesFromUrls(string $phrase, array $urls): array
    {
        $sites = [];
        foreach ($urls as $url) {
            if (!is_string($url) || $url === '') {
                continue;
            }
            $fullUrl = self::normalizeUrl($url);
            $path = parse_url($fullUrl, PHP_URL_PATH) ?: '/';
            $sites[$fullUrl] = ['mainPage' => $path === '/' || $path === ''];
        }

        return [$phrase => $sites];
    }

    protected static function geoStatusLabel(string $status): string
    {
        $map = [
            'geo_independent' => 'Геонезависимый',
            'geo_dependent' => 'Геозависимый',
            'partial' => 'Частично геозависимый',
            'skipped' => 'Не оценён',
            'mixed' => 'Смешанный',
        ];

        return $map[$status] ?? $status;
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildResponse(array $result, int $remaining, string $guestId): array
    {
        $cfg = self::config();
        $moduleSlug = (string) ($cfg['module_slug'] ?? self::MODULE);
        $registerBase = rtrim((string) config('app.url', 'https://cabinet.titlo.ru'), '/');

        return [
            'demo' => true,
            'module' => self::MODULE,
            'remaining' => $remaining,
            'limits' => [
                'max_phrase_length' => (int) ($cfg['max_phrase_length'] ?? 120),
                'max_runs_per_day' => (int) ($cfg['max_runs_per_day'] ?? 3),
                'top_count' => (int) ($cfg['top_count'] ?? 10),
                'serp_rows' => (int) ($cfg['serp_rows'] ?? 10),
                'top_depths' => $cfg['top_depths'] ?? [
                    ['value' => 30, 'label' => '30 (рекомендуемый)', 'demo' => false],
                    ['value' => 20, 'label' => '20', 'demo' => false],
                    ['value' => 10, 'label' => '10', 'demo' => true],
                ],
                'search_engines' => $cfg['search_engines'] ?? ['yandex', 'google'],
                'yandex_regions' => self::regionsForUi('yandex'),
                'google_regions' => self::regionsForUi('google'),
            ],
            'result' => [
                'phrase' => $result['phrase'],
                'engine' => $result['engine'],
                'engine_label' => $result['engine_label'],
                'region' => $result['region'],
                'compare_region' => $result['compare_region'],
                'top_count' => $result['top_count'],
                'serp' => $result['serp'],
                'geo' => $result['geo'],
                'locked' => self::lockedFeatures($result),
            ],
            'upgrade' => [
                'register_url' => $registerBase . '/register?' . http_build_query([
                    'module' => $moduleSlug,
                    'from' => 'demo',
                    'guest' => $guestId,
                ]),
                'login_url' => $registerBase . '/login',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $result
     * @return string[]
     */
    private static function lockedFeatures(array $result): array
    {
        $locked = [
            'meta_tags',
            'recommendations',
            'multi_phrase',
            'site_parsing',
            'export_xls',
            'top_20_30',
            'relevance_module',
            'serp_city_compare_ui',
        ];

        if (empty($result['geo'])) {
            $locked[] = 'geo_dependency_full';
        }

        return $locked;
    }

    private static function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        return $url;
    }

    /**
     * @return array{ok: false, status: int, error: string, message: string}
     */
    private static function fail(int $status, string $error, string $message): array
    {
        return ['ok' => false, 'status' => $status, 'error' => $error, 'message' => $message];
    }
}
