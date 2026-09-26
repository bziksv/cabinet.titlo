<?php

namespace App\Services\SiteAudit;

use App\SiteAuditFinding;
use App\SiteAuditPage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

class SiteAuditPageProcessor
{
    /** @var SiteAuditFetcher */
    private $fetcher;

    /** @var SiteAuditHtmlParser */
    private $parser;

    /** @var string|null ключ настроек, для которых уже создан $fetcher (keep-alive между URL) */
    private $fetcherKey;

    /** @var bool|null */
    private static $discoveredColumnsReady;

    public function __construct(?SiteAuditFetcher $fetcher = null, ?SiteAuditHtmlParser $parser = null)
    {
        $this->fetcher = $fetcher ?: new SiteAuditFetcher();
        $this->parser = $parser ?: new SiteAuditHtmlParser();
    }

    /**
     * @return array{internal_links:string[]}
     */
    public function process(int $crawlId, string $url, string $projectHost, array $crawlSettings = []): array
    {
        $this->ensureFetcher($crawlId, $crawlSettings);

        $rps = (float) ($crawlSettings['rps'] ?? config('site_audit.per_host_rps', 1));
        SiteAuditHostThrottle::wait($projectHost, $rps);

        $urlHash = SiteAuditUrlNormalizer::hash($url);
        $result = $this->fetcher->fetch($url);
        $result = $this->recoverTrailingSlash($url, $result);
        $urlHash = SiteAuditUrlNormalizer::hash($url);
        $bodyPath = isset($result['body_path']) ? (string) $result['body_path'] : null;

        try {
            return $this->processFetched($crawlId, $url, $urlHash, $result, $crawlSettings, $projectHost);
        } finally {
            SiteAuditBodyTemp::release($bodyPath);
        }
    }

    /**
     * Обработать уже скачанный ответ (для параллельного fetch).
     *
     * @param array<string, mixed> $result
     * @param array<string, mixed> $crawlSettings
     * @return array{internal_links:string[],content_unchanged?:bool}
     */
    public function processFetchedResult(
        int $crawlId,
        string $url,
        array $result,
        string $projectHost,
        array $crawlSettings = [],
        ?array $discovery = null
    ): array {
        $this->ensureFetcher($crawlId, $crawlSettings);
        $result = $this->recoverTrailingSlash($url, $result);
        $urlHash = SiteAuditUrlNormalizer::hash($url);
        $bodyPath = isset($result['body_path']) ? (string) $result['body_path'] : null;

        try {
            return $this->processFetched(
                $crawlId,
                $url,
                $urlHash,
                $result,
                $crawlSettings,
                $projectHost,
                $discovery
            );
        } finally {
            SiteAuditBodyTemp::release($bodyPath);
        }
    }

    /**
     * Параллельный fetch + (при html5) параллельный vnu, затем parse.
     *
     * @param string[] $urls
     * @param array<string, array{via?:string,from?:?string}> $originsByUrl
     * @return array<int, array{internal_links:string[],content_unchanged?:bool}>
     */
    public function processMany(
        int $crawlId,
        array $urls,
        string $projectHost,
        array $crawlSettings = [],
        array $originsByUrl = []
    ): array {
        $urls = array_values($urls);
        if ($urls === []) {
            return [];
        }

        $this->ensureFetcher($crawlId, $crawlSettings);
        $rps = (float) ($crawlSettings['rps'] ?? config('site_audit.per_host_rps', 1));
        $concurrency = max(1, (int) ($crawlSettings['concurrency'] ?? 1));
        $hostRps = max(0.1, $rps * $concurrency);

        $fetched = $this->fetcher->fetchMany($urls, $concurrency, function () use ($projectHost, $hostRps) {
            SiteAuditHostThrottle::wait($projectHost, $hostRps);
        });

        $vnuByIndex = $this->prevalidateHtml5Wave($fetched, $urls, $crawlSettings, $concurrency);

        $outs = [];
        foreach ($urls as $i => $url) {
            $result = $fetched[$i] ?? [
                'ok' => false,
                'status_code' => null,
                'final_url' => $url,
                'redirect_chain' => [],
                'body' => null,
                'body_path' => null,
                'size_bytes' => 0,
                'content_type' => null,
                'x_robots' => null,
                'sec_headers' => [],
                'error' => 'missing fetch result',
                'user_agent' => null,
                'ua_rotated' => false,
            ];
            try {
                $discovery = isset($originsByUrl[$url]) && is_array($originsByUrl[$url])
                    ? $originsByUrl[$url]
                    : null;
                $settings = $crawlSettings;
                if (isset($vnuByIndex[$i]) && is_array($vnuByIndex[$i])) {
                    $settings['vnu_precomputed'] = $vnuByIndex[$i];
                }
                $outs[] = $this->processFetchedResult(
                    $crawlId,
                    $url,
                    $result,
                    $projectHost,
                    $settings,
                    $discovery
                );
            } catch (\Throwable $e) {
                $outs[] = ['internal_links' => [], 'content_unchanged' => false];
            }
        }

        return $outs;
    }

    /**
     * Параллельный vnu для HTML-кандидатов волны (только html5 + сконфигурированный vnu).
     *
     * @param array<int, array> $fetched
     * @param string[] $urls
     * @return array<int, array{ok:bool,errors:list,error:?string}>
     */
    private function prevalidateHtml5Wave(
        array $fetched,
        array $urls,
        array $crawlSettings,
        int $concurrency
    ): array {
        $checker = SiteAuditHtmlChecker::normalize(
            $crawlSettings['html_checker'] ?? SiteAuditHtmlChecker::defaultChecker()
        );
        if ($checker !== SiteAuditHtmlChecker::HTML5 || ! SiteAuditHtmlChecker::vnuEnabled()) {
            return [];
        }

        $htmlByIndex = [];
        foreach ($urls as $i => $url) {
            $result = $fetched[$i] ?? null;
            if (! is_array($result) || empty($result['ok'])) {
                continue;
            }
            $code = (int) ($result['status_code'] ?? 0);
            if ($code < 200 || $code >= 400) {
                continue;
            }
            if (! SiteAuditUrlNormalizer::isHtmlDocument($result['content_type'] ?? null, $url)) {
                continue;
            }
            $body = SiteAuditBodyTemp::takeBody($result);
            if ($body === null || $body === '') {
                continue;
            }
            $htmlByIndex[$i] = $body;
        }

        if ($htmlByIndex === []) {
            return [];
        }

        $vnuConc = (int) config('site_audit.vnu_concurrency', 0);
        if ($vnuConc <= 0) {
            $vnuConc = $concurrency;
        }
        $vnuConc = max(1, min($concurrency, $vnuConc));

        return (new SiteAuditVnuClient())->validateMany($htmlByIndex, $vnuConc);
    }

    private static function discoveredColumnsReady(): bool
    {
        // Кэшируем только успех: иначе воркер, стартовавший до migrate, навсегда
        // пишет страницы без discovered_* до queue:restart.
        if (self::$discoveredColumnsReady === true) {
            return true;
        }
        try {
            $ready = Schema::hasColumn('site_audit_pages', 'discovered_via')
                && Schema::hasColumn('site_audit_pages', 'discovered_from');
            if ($ready) {
                self::$discoveredColumnsReady = true;
            }

            return $ready;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function ensureFetcher(int $crawlId, array $crawlSettings): void
    {
        if (! $crawlSettings) {
            return;
        }
        $key = $crawlId . ':' . md5(json_encode([
            $crawlSettings['rps'] ?? null,
            $crawlSettings['crawl_speed'] ?? null,
            $crawlSettings['concurrency'] ?? null,
        ]));
        if ($this->fetcherKey !== $key) {
            $this->fetcher = SiteAuditFetcher::fromCrawlSettings($crawlSettings, $crawlId);
            $this->fetcherKey = $key;
        }
    }

    /**
     * Bitrix и часть CMS отдают 200 только со слэшем, а без — 404.
     * Если запрос без «/» дал 4xx — один повтор со слэшем; при успехе сохраняем URL как в sitemap.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function recoverTrailingSlash(string &$url, array $result): array
    {
        $code = (int) ($result['status_code'] ?? 0);
        if ($code < 400 || $code >= 500) {
            return $result;
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return $result;
        }
        $path = (string) ($parts['path'] ?? '');
        if ($path === '' || $path === '/' || substr($path, -1) === '/') {
            return $result;
        }
        // файлы с расширением не трогаем
        if (preg_match('/\.[a-z0-9]{2,5}$/i', $path)) {
            return $result;
        }

        $slashUrl = $parts['scheme'] . '://' . $parts['host']
            . (isset($parts['port']) ? ':' . $parts['port'] : '')
            . $path . '/'
            . (isset($parts['query']) ? '?' . $parts['query'] : '');

        $retry = $this->fetcher->fetch($slashUrl);
        $retryCode = (int) ($retry['status_code'] ?? 0);
        if (! empty($retry['ok']) && $retryCode >= 200 && $retryCode < 400) {
            SiteAuditBodyTemp::release($result['body_path'] ?? null);
            $url = $slashUrl;
            $retry['recovered_trailing_slash'] = true;
            $retry['requested_without_slash'] = true;

            return $retry;
        }

        SiteAuditBodyTemp::release($retry['body_path'] ?? null);

        return $result;
    }

    /**
     * @param array<string, mixed> $result
     * @param array<string, mixed> $crawlSettings
     * @return array{internal_links:string[]}
     */
    private function processFetched(
        int $crawlId,
        string $url,
        string $urlHash,
        array $result,
        array $crawlSettings,
        string $projectHost,
        ?array $discovery = null
    ): array {
        $findings = [];
        $internalLinks = [];

        $robotsGroups = $this->robotsGroupsForCrawl($crawlId);
        if ($robotsGroups !== null) {
            $robots = new SiteAuditRobotsTxt();
            $match = $robots->matchingRule($robotsGroups, $url);
            if ($match !== null && empty($match['allow'])) {
                $findings[] = $this->finding('robots_blocked', $url, $urlHash, [
                    'source' => 'robots.txt',
                    'directive' => (string) ($match['directive'] ?? 'Disallow'),
                    'rule' => (string) ($match['path'] ?? ''),
                    'agent' => (string) ($match['agent'] ?? '*'),
                ]);
            }
        }

        $via = is_array($discovery) ? trim((string) ($discovery['via'] ?? '')) : '';
        $from = is_array($discovery) ? trim((string) ($discovery['from'] ?? '')) : '';
        if ($via !== '' && ! in_array($via, ['sitemap', 'seed', 'home', 'link'], true)) {
            $via = '';
        }
        if ($via !== 'link') {
            $from = '';
        }

        $pageData = [
            'crawl_id' => $crawlId,
            'url' => $url,
            'url_hash' => $urlHash,
            'final_url' => $result['final_url'],
            'status_code' => $result['status_code'],
            'redirect_chain' => $result['redirect_chain'] ?: null,
            'size_bytes' => $result['size_bytes'],
            'content_type' => $result['content_type'],
            'title' => null,
            'title_hash' => null,
            'description' => null,
            'description_hash' => null,
            'h1' => null,
            'h1_count' => 0,
            'h2_count' => 0,
            'headings_json' => null,
            'canonical' => null,
            'robots_meta' => null,
            'keywords_meta' => null,
            'noindex' => false,
            'word_count' => null,
            'content_hash' => null,
            'content_unchanged' => false,
            'simhash' => null,
            'out_links_json' => null,
            'ext_links_json' => null,
            'img_count' => 0,
            'img_without_alt' => 0,
        ];
        if ($via !== '' && self::discoveredColumnsReady()) {
            $pageData['discovered_via'] = $via;
            $pageData['discovered_from'] = $from !== '' ? $from : null;
        }

        $body = SiteAuditBodyTemp::takeBody($result);

        $chain = is_array($result['redirect_chain'] ?? null) ? $result['redirect_chain'] : [];
        $redirectFindings = $this->redirectFindings(
            $url,
            $urlHash,
            $chain,
            (string) ($result['final_url'] ?? ''),
            ! empty($result['redirect_loop'])
        );
        $hasRedirectLoop = false;
        foreach ($redirectFindings as $rf) {
            $findings[] = $rf;
            if (($rf['code'] ?? '') === 'redirect_loop') {
                $hasRedirectLoop = true;
            }
        }

        if (! $result['ok'] || $body === null) {
            if (! $hasRedirectLoop) {
                $findings[] = $this->finding('unreachable', $url, $urlHash, [
                    'error' => $result['error'],
                    'chain' => $chain ?: null,
                    'attempt' => 1,
                    'attempt_at' => now()->toIso8601String(),
                ]);
            }
        } else {
            $code = (int) $result['status_code'];
            // Мусорный URL из примера в JSON-LD / кривого href — не «ошибка 4xx сайта».
            $urlLooksGarbage = SiteAuditFindingPresenter::urlLooksBrokenHref($url);
            if ($code >= 400 && $code < 500 && ! $urlLooksGarbage) {
                $meta4 = ['status' => $code];
                $p = parse_url($url);
                $path = is_array($p) ? (string) ($p['path'] ?? '') : '';
                if ($path !== ''
                    && $path !== '/'
                    && substr($path, -1) !== '/'
                    && is_array($p)
                    && ! empty($p['host'])) {
                    $meta4['slash_hint'] = true;
                    $meta4['slash_url'] = ($p['scheme'] ?? 'https') . '://' . $p['host']
                        . (isset($p['port']) ? ':' . $p['port'] : '')
                        . $path . '/'
                        . (isset($p['query']) ? '?' . $p['query'] : '');
                }
                $findings[] = $this->finding('http_4xx', $url, $urlHash, $meta4);
            } elseif ($code >= 500 && ! $urlLooksGarbage) {
                $findings[] = $this->finding('http_5xx', $url, $urlHash, ['status' => $code]);
            }

            $large = (int) config('site_audit.large_page_bytes', 1_500_000);
            if ($result['size_bytes'] >= $large) {
                $findings[] = $this->finding('page_too_large', $url, $urlHash, [
                    'size_bytes' => $result['size_bytes'],
                    'threshold' => $large,
                ]);
            }

            $isHtml = SiteAuditUrlNormalizer::isHtmlDocument($result['content_type'] ?? null, $url);
            if ($isHtml && $code >= 200 && $code < 400) {
                $parseOpts = [
                    'html_checker' => $crawlSettings['html_checker']
                        ?? SiteAuditHtmlChecker::defaultChecker(),
                ];
                if (isset($crawlSettings['vnu_precomputed']) && is_array($crawlSettings['vnu_precomputed'])) {
                    $parseOpts['vnu_precomputed'] = $crawlSettings['vnu_precomputed'];
                }
                $parsed = $this->parser->parse($body, $result['final_url'] ?: $url, $parseOpts);
                $pageData['title'] = $parsed['title'];
                $pageData['title_hash'] = $parsed['title'] ? hash('sha256', mb_strtolower($parsed['title'])) : null;
                $pageData['description'] = $parsed['description'];
                $pageData['description_hash'] = $parsed['description']
                    ? hash('sha256', mb_strtolower($parsed['description']))
                    : null;
                $pageData['h1'] = $parsed['h1'];
                $pageData['h1_count'] = $parsed['h1_count'];
                $pageData['h2_count'] = $parsed['h2_count'] ?? 0;
                $pageData['headings_json'] = ! empty($parsed['headings']) && is_array($parsed['headings'])
                    ? $parsed['headings']
                    : null;
                $pageData['canonical'] = $parsed['canonical'];
                $pageData['robots_meta'] = $parsed['robots_meta'];
                $pageData['keywords_meta'] = $parsed['keywords_meta'] ?? null;
                $pageData['noindex'] = $parsed['noindex'];
                $pageData['word_count'] = $parsed['word_count'];
                $pageData['text_len'] = $parsed['text_len'] ?? null;
                $pageData['content_hash'] = $parsed['content_hash'];
                $pageData['content_unchanged'] = $this->isContentUnchanged(
                    $crawlId,
                    $urlHash,
                    (string) $parsed['content_hash'],
                    (int) $code
                );
                $pageData['simhash'] = $parsed['simhash'] ?? null;
                $pageData['img_count'] = $parsed['img_count'];
                $pageData['img_without_alt'] = $parsed['img_without_alt'];
                $pageData['unique_img_src_count'] = $parsed['unique_img_src_count'] ?? 0;
                $pageData['strong_count'] = $parsed['strong_count'] ?? 0;
                $pageData['em_count'] = $parsed['em_count'] ?? 0;
                $pageData['nausea_classic'] = $parsed['nausea_classic'] ?? null;
                $pageData['nausea_academic'] = $parsed['nausea_academic'] ?? null;
                $pageData['top_word'] = $parsed['top_word'] ?? null;
                $pageData['top_word_count'] = $parsed['top_word_count'] ?? 0;
                $pageData['top_bigram'] = $parsed['top_bigram'] ?? null;
                $pageData['top_bigram_count'] = $parsed['top_bigram_count'] ?? 0;
                $pageData['top_trigram'] = $parsed['top_trigram'] ?? null;
                $pageData['top_trigram_count'] = $parsed['top_trigram_count'] ?? 0;
                if (Schema::hasColumn('site_audit_pages', 'token_top_json')) {
                    $tok = isset($parsed['token_top']) && is_array($parsed['token_top'])
                        ? array_values(array_slice($parsed['token_top'], 0, 40))
                        : [];
                    $pageData['token_top_json'] = $tok !== [] ? $tok : null;
                }
                if (Schema::hasColumn('site_audit_pages', 'shingles_json')) {
                    $sh = isset($parsed['shingles']) && is_array($parsed['shingles'])
                        ? array_values($parsed['shingles'])
                        : [];
                    $pageData['shingles_json'] = $sh !== [] ? $sh : null;
                }
                $pageData['noindex_text_len'] = $parsed['noindex_text_len'] ?? 0;
                if (Schema::hasColumn('site_audit_pages', 'noindex_sample')) {
                    $sample = trim((string) ($parsed['noindex_sample'] ?? ''));
                    $pageData['noindex_sample'] = $sample !== '' ? mb_substr($sample, 0, 191) : null;
                }
                if (Schema::hasColumn('site_audit_pages', 'noindex_links_json')) {
                    $links = isset($parsed['noindex_links']) && is_array($parsed['noindex_links'])
                        ? array_values(array_slice($parsed['noindex_links'], 0, 12))
                        : [];
                    $pageData['noindex_links_json'] = $links !== [] ? $links : null;
                }
                if (Schema::hasColumn('site_audit_pages', 'noindex_hash')) {
                    $hash = trim((string) ($parsed['noindex_hash'] ?? ''));
                    $pageData['noindex_hash'] = $hash !== '' ? $hash : null;
                }
                $pageData['charset'] = $parsed['charset'] ?? null;

                if ($result['x_robots'] && preg_match('/\bnoindex\b/i', $result['x_robots'])) {
                    $pageData['noindex'] = true;
                }

                $urlOpts = SiteAuditUrlNormalizer::optionsFromSettings($crawlSettings, $projectHost);
                $links = (new SiteAuditLinkExtractor())->extract(
                    $body,
                    $result['final_url'] ?: $url,
                    $projectHost,
                    $urlOpts
                );
                $internalLinks = $links['internal'];
                // URL-строки (не только hash) — для orphan + broken links
                // Больше ссылок — точнее колонка «Откуда» на крупных сайтах с жирным меню.
                $pageData['out_links_json'] = array_slice($internalLinks, 0, 400) ?: null;
                $pageData['img_srcs_json'] = ! empty($links['img_srcs'])
                    ? array_slice($links['img_srcs'], 0, 40)
                    : null;
                $pageData['asset_srcs_json'] = ! empty($links['asset_srcs'])
                    ? array_slice($links['asset_srcs'], 0, 40)
                    : null;

                if (! empty($links['bad_links'])) {
                    $findings[] = $this->finding('page_has_bad_links', $url, $urlHash, [
                        'count' => count($links['bad_links']),
                        'samples' => array_slice($links['bad_links'], 0, 10),
                    ]);
                }

                $htmlErrMin = max(1, (int) config('site_audit.html_critical_min', 1));
                if (! empty($parsed['html_error_count']) && (int) $parsed['html_error_count'] >= $htmlErrMin) {
                    $findings[] = $this->finding('html_critical_errors', $url, $urlHash, [
                        'count' => (int) $parsed['html_error_count'],
                        'samples' => array_slice($parsed['html_error_samples'] ?? [], 0, 8),
                        'checker' => (string) ($parsed['html_checker'] ?? SiteAuditHtmlChecker::LIBXML),
                        'checker_fallback' => ! empty($parsed['html_checker_fallback']),
                    ]);
                }

                $risk = is_array($parsed['content_risk'] ?? null) ? $parsed['content_risk'] : [];
                if (! empty($risk['adult'])) {
                    $findings[] = $this->finding('adult_content', $url, $urlHash, [
                        'score' => (int) ($risk['adult_score'] ?? 0),
                        'hits' => $risk['adult_hits'] ?? [],
                    ]);
                }
                if (! empty($risk['negative'])) {
                    $findings[] = $this->finding('negative_content', $url, $urlHash, [
                        'score' => (int) ($risk['negative_score'] ?? 0),
                        'hits' => $risk['negative_hits'] ?? [],
                    ]);
                }
                if (! empty($risk['word_repeat'])) {
                    $findings[] = $this->finding('word_repeat_in_sentence', $url, $urlHash, [
                        'count' => count($risk['word_repeat_samples'] ?? []),
                        'samples' => $risk['word_repeat_samples'] ?? [],
                    ]);
                }

                $contacts = is_array($parsed['contacts'] ?? null) ? $parsed['contacts'] : [];
                $isCommercial = ! empty($contacts['commercial']);
                $isProduct = ! empty($contacts['product_offer']);
                if ($isCommercial && (int) ($pageData['word_count'] ?? 0) >= 40) {
                    // Телефон обязателен. Адрес сам по себе не валим, если телефон уже есть
                    // (часто в шапке; адрес — в JSON-LD / на /kontakty/).
                    $missing = [];
                    if (empty($contacts['has_phone'])) {
                        $missing[] = 'phone';
                        if (empty($contacts['has_address'])) {
                            $missing[] = 'address';
                        }
                    }
                    if ($missing !== []) {
                        $meta = ['missing' => $missing];
                        if (! empty($contacts['phone_sample'])) {
                            $meta['phone_sample'] = $contacts['phone_sample'];
                        }
                        if (! empty($contacts['address_sample'])) {
                            $meta['address_sample'] = $contacts['address_sample'];
                        }
                        if (! empty($contacts['phone_source'])) {
                            $meta['phone_source'] = $contacts['phone_source'];
                        }
                        if (! empty($contacts['address_source'])) {
                            $meta['address_source'] = $contacts['address_source'];
                        }
                        $findings[] = $this->finding('commercial_missing_contacts', $url, $urlHash, $meta);
                    }
                    if (empty($contacts['has_cta'])) {
                        $findings[] = $this->finding('commercial_missing_cta', $url, $urlHash);
                    }
                }
                // Цена / наличие / оплата / наличие / отзывы — только товарная витрина, не блог и не услуги.
                if ($isProduct && (int) ($pageData['word_count'] ?? 0) >= 40) {
                    if (empty($contacts['has_price'])) {
                        $findings[] = $this->finding('commercial_missing_price', $url, $urlHash);
                    }
                    if (empty($contacts['has_delivery'])) {
                        $findings[] = $this->finding('commercial_missing_delivery', $url, $urlHash);
                    }
                    if (empty($contacts['has_payment'])) {
                        $findings[] = $this->finding('commercial_missing_payment', $url, $urlHash);
                    }
                    if (empty($contacts['has_stock'])) {
                        $findings[] = $this->finding('commercial_missing_stock', $url, $urlHash);
                    }
                    if (empty($contacts['has_reviews'])) {
                        $findings[] = $this->finding('commercial_missing_reviews', $url, $urlHash);
                    }
                }

                if ($links['meta_nofollow'] || ($result['x_robots'] && preg_match('/\bnofollow\b/i', $result['x_robots']))) {
                    $findings[] = $this->finding('meta_nofollow', $url, $urlHash, [
                        'robots' => $pageData['robots_meta'],
                        'x_robots' => $result['x_robots'],
                    ]);
                }
                if ($links['nofollow_links'] > 0) {
                    $findings[] = $this->finding('links_nofollow', $url, $urlHash, [
                        'count' => $links['nofollow_links'],
                        'samples' => array_slice($links['nofollow_samples'] ?? [], 0, 20),
                    ]);
                }
                if ($links['external_assets'] || ! empty($links['external_asset_items'])) {
                    $items = is_array($links['external_asset_items'] ?? null)
                        ? $links['external_asset_items']
                        : [];
                    if ($items === [] && ! empty($links['external_assets'])) {
                        foreach (array_slice($links['external_assets'], 0, 15) as $assetUrl) {
                            $items[] = ['url' => (string) $assetUrl, 'kind' => 'file'];
                        }
                    }
                    $findings[] = $this->finding('external_assets', $url, $urlHash, [
                        'count' => (int) ($links['external_assets']
                            ? count($links['external_assets'])
                            : count($items)),
                        'samples' => array_slice($items, 0, 15),
                    ]);
                }
                if (! empty($links['external'])) {
                    $extSamples = [];
                    if (! empty($links['external_items']) && is_array($links['external_items'])) {
                        foreach (array_slice($links['external_items'], 0, 40) as $item) {
                            if (! is_array($item)) {
                                continue;
                            }
                            $eu = trim((string) ($item['url'] ?? ''));
                            if ($eu === '') {
                                continue;
                            }
                            $extSamples[] = [
                                'url' => $eu,
                                'text' => trim((string) ($item['text'] ?? '')),
                            ];
                        }
                    }
                    if ($extSamples === []) {
                        foreach (array_slice($links['external'], 0, 40) as $eu) {
                            $extSamples[] = ['url' => (string) $eu, 'text' => ''];
                        }
                    }
                    $findings[] = $this->finding('external_links', $url, $urlHash, [
                        'count' => count($links['external']),
                        'samples' => $extSamples,
                    ]);
                    $pageData['ext_links_json'] = array_values(array_unique(array_map(function ($item) {
                        return is_array($item) ? (string) ($item['url'] ?? '') : (string) $item;
                    }, $extSamples)));
                    $pageData['ext_links_json'] = array_values(array_filter($pageData['ext_links_json']));
                    if ($pageData['ext_links_json'] === []) {
                        $pageData['ext_links_json'] = array_slice($links['external'], 0, 80);
                    }
                    $aff = SiteAuditAffiliateDetector::fromExternalUrls($links['external']);
                    if ($aff) {
                        $findings[] = $this->finding('probable_affiliate', $url, $urlHash, $aff);
                    }
                }
                if (! empty($links['duplicate_links_count'])) {
                    $findings[] = $this->finding('duplicate_links', $url, $urlHash, [
                        'count' => (int) $links['duplicate_links_count'],
                        'samples' => $links['duplicate_links'] ?? [],
                    ]);
                }

                $h1Norm = $pageData['h1'] ? mb_strtolower(trim($pageData['h1'])) : '';
                if ($h1Norm !== '') {
                    foreach ($parsed['h2s'] ?? [] as $h2) {
                        if ($h1Norm === mb_strtolower(trim((string) $h2))) {
                            $findings[] = $this->finding('h1_equals_h2', $url, $urlHash, [
                                'h1' => $pageData['h1'],
                                'h2' => $h2,
                            ]);
                            break;
                        }
                    }
                }

                $headingIssues = $parsed['heading_issues'] ?? [];
                if (is_array($headingIssues) && $headingIssues !== []) {
                    $findings[] = $this->finding('heading_hierarchy', $url, $urlHash, [
                        'issue_count' => count($headingIssues),
                        'issues' => array_slice($headingIssues, 0, 5),
                        'outline_sample' => array_slice($parsed['heading_outline'] ?? [], 0, 12),
                    ]);
                }

                if (! $pageData['title']) {
                    $findings[] = $this->finding('empty_title', $url, $urlHash);
                }
                if (! $pageData['description']) {
                    $findings[] = $this->finding('empty_description', $url, $urlHash);
                }
                if (($parsed['title_count'] ?? 0) > 1 || ($parsed['description_count'] ?? 0) > 1) {
                    $titleSamples = [];
                    if (! empty($parsed['titles']) && is_array($parsed['titles'])) {
                        foreach (array_slice($parsed['titles'], 0, 8) as $t) {
                            $t = trim((string) $t);
                            if ($t !== '') {
                                $titleSamples[] = mb_substr($t, 0, 300);
                            }
                        }
                    } elseif (! empty($parsed['title'])) {
                        $titleSamples[] = mb_substr(trim((string) $parsed['title']), 0, 300);
                    }
                    $descSamples = [];
                    if (! empty($parsed['descriptions']) && is_array($parsed['descriptions'])) {
                        foreach (array_slice($parsed['descriptions'], 0, 8) as $d) {
                            $d = trim((string) $d);
                            if ($d !== '') {
                                $descSamples[] = mb_substr($d, 0, 400);
                            }
                        }
                    } elseif (! empty($parsed['description'])) {
                        $descSamples[] = mb_substr(trim((string) $parsed['description']), 0, 400);
                    }
                    $findings[] = $this->finding('multiple_title_or_description', $url, $urlHash, [
                        'title_count' => (int) ($parsed['title_count'] ?? 0),
                        'description_count' => (int) ($parsed['description_count'] ?? 0),
                        'titles' => $titleSamples,
                        'descriptions' => $descSamples,
                    ]);
                }
                if ($pageData['noindex']) {
                    $findings[] = $this->finding('noindex', $url, $urlHash, [
                        'robots' => $pageData['robots_meta'],
                        'x_robots' => $result['x_robots'],
                    ]);
                }
                if ($pageData['h1_count'] === 0) {
                    $findings[] = $this->finding('missing_h1', $url, $urlHash);
                } elseif ($pageData['h1_count'] > 1) {
                    $h1Samples = [];
                    if (! empty($parsed['h1s']) && is_array($parsed['h1s'])) {
                        foreach (array_slice($parsed['h1s'], 0, 8) as $t) {
                            $t = trim((string) $t);
                            if ($t !== '') {
                                $h1Samples[] = mb_substr($t, 0, 160);
                            }
                        }
                    } elseif (! empty($parsed['headings']['h1']) && is_array($parsed['headings']['h1'])) {
                        foreach (array_slice($parsed['headings']['h1'], 0, 8) as $t) {
                            $t = trim((string) $t);
                            if ($t !== '') {
                                $h1Samples[] = mb_substr($t, 0, 160);
                            }
                        }
                    }
                    $findings[] = $this->finding('multiple_h1', $url, $urlHash, [
                        'count' => (int) $pageData['h1_count'],
                        'samples' => $h1Samples,
                    ]);
                }

                if ($pageData['canonical']) {
                    $pageUrl = (string) ($result['final_url'] ?: $url);
                    $canonRaw = (string) $pageData['canonical'];
                    $canonAbs = SiteAuditUrlNormalizer::resolve($canonRaw, $pageUrl, null, [])
                        ?: (preg_match('#^https?://#i', $canonRaw) ? $canonRaw : null);

                    $canonHost = $canonAbs ? SiteAuditUrlNormalizer::hostOf($canonAbs) : SiteAuditUrlNormalizer::hostOf($canonRaw);
                    $baseBare = preg_replace('/^www\./', '', strtolower($projectHost));
                    $canonBare = $canonHost ? preg_replace('/^www\./', '', $canonHost) : null;
                    $isForeign = $canonBare && $baseBare && $canonBare !== $baseBare;

                    if ($isForeign) {
                        $findings[] = $this->finding('canonical_foreign', $url, $urlHash, [
                            'canonical' => $pageData['canonical'],
                        ]);
                    } elseif ($canonAbs) {
                        $pageKey = SiteAuditUrlNormalizer::canonicalKey($pageUrl);
                        $canonKey = SiteAuditUrlNormalizer::canonicalKey($canonAbs);
                        if ($pageKey && $canonKey && $pageKey !== $canonKey) {
                            $findings[] = $this->finding('canonical_not_self', $url, $urlHash, [
                                'canonical' => $canonAbs,
                                'page_key' => $pageKey,
                                'canon_key' => $canonKey,
                            ]);
                        }
                    }
                    if (($parsed['canonical_count'] ?? 0) > 1) {
                        $canonicals = [];
                        foreach (($parsed['canonicals'] ?? []) as $c) {
                            $c = trim((string) $c);
                            if ($c !== '') {
                                $canonicals[] = mb_substr($c, 0, 500);
                            }
                        }
                        $findings[] = $this->finding('multiple_canonical', $url, $urlHash, [
                            'count' => (int) $parsed['canonical_count'],
                            'canonical' => $pageData['canonical'],
                            'canonicals' => $canonicals,
                        ]);
                    }
                } else {
                    $findings[] = $this->finding('canonical_empty', $url, $urlHash);
                }

                if (($parsed['iframe_count'] ?? 0) > 0) {
                    $findings[] = $this->finding('pages_with_iframe', $url, $urlHash, [
                        'iframe_count' => (int) $parsed['iframe_count'],
                    ]);
                }

                if (($parsed['mixed_content_count'] ?? 0) > 0) {
                    $findings[] = $this->finding('mixed_content', $url, $urlHash, [
                        'count' => (int) $parsed['mixed_content_count'],
                        'samples' => $parsed['mixed_content_samples'] ?? [],
                    ]);
                }

                if (($parsed['insecure_form_count'] ?? 0) > 0) {
                    $findings[] = $this->finding('insecure_form', $url, $urlHash, [
                        'count' => (int) $parsed['insecure_form_count'],
                        'samples' => $parsed['insecure_form_samples'] ?? [],
                    ]);
                }

                if (empty($parsed['has_doctype'])) {
                    $findings[] = $this->finding('bad_doctype', $url, $urlHash, [
                        'reason' => 'missing',
                    ]);
                } elseif (isset($parsed['doctype']) && $parsed['doctype'] !== ''
                    && ! preg_match('/^html(\s|$)/i', $parsed['doctype'])) {
                    $findings[] = $this->finding('bad_doctype', $url, $urlHash, [
                        'reason' => 'unusual',
                        'doctype' => $parsed['doctype'],
                    ]);
                }

                if (empty($parsed['charset'])) {
                    $findings[] = $this->finding('missing_charset', $url, $urlHash);
                }

                // Security headers lite: только HTTPS + 200 HTML
                if ($code === 200 && stripos((string) ($result['final_url'] ?: $url), 'https://') === 0) {
                    $sec = is_array($result['sec_headers'] ?? null) ? $result['sec_headers'] : [];
                    if (empty($sec['hsts'])) {
                        $findings[] = $this->finding('missing_hsts', $url, $urlHash);
                    }
                    if (empty($sec['x_frame'])) {
                        $findings[] = $this->finding('missing_x_frame_options', $url, $urlHash);
                    }
                    if (empty($sec['x_content_type'])) {
                        $findings[] = $this->finding('missing_x_content_type_options', $url, $urlHash);
                    }
                    if (empty($sec['csp'])) {
                        $findings[] = $this->finding('missing_csp', $url, $urlHash);
                    }
                    if (empty($sec['referrer_policy'])) {
                        $findings[] = $this->finding('missing_referrer_policy', $url, $urlHash);
                    }
                    if (empty($sec['permissions_policy'])) {
                        $findings[] = $this->finding('missing_permissions_policy', $url, $urlHash);
                    }
                    if (empty($sec['coop'])) {
                        $findings[] = $this->finding('missing_coop', $url, $urlHash);
                    }
                    if (empty($sec['coep'])) {
                        $findings[] = $this->finding('missing_coep', $url, $urlHash);
                    }
                    if (empty($sec['corp'])) {
                        $findings[] = $this->finding('missing_corp', $url, $urlHash);
                    }
                }
            }
        }

        unset($result['body'], $body);

        if ($via !== '') {
            foreach ($findings as $fi => $findingRow) {
                $meta = is_array($findingRow['meta_json'] ?? null) ? $findingRow['meta_json'] : [];
                $meta['discovered_via'] = $via;
                if ($from !== '') {
                    $meta['discovered_from'] = $from;
                }
                $findings[$fi]['meta_json'] = $meta;
            }
        }

        SiteAuditPage::query()->updateOrCreate(
            ['crawl_id' => $crawlId, 'url_hash' => $urlHash],
            $pageData
        );

        SiteAuditFinding::query()
            ->where('crawl_id', $crawlId)
            ->where('url_hash', $urlHash)
            ->whereNotIn('code', [
                'duplicate_title',
                'duplicate_description',
                'duplicate_content',
                'similar_pages',
                'soft_404',
                'orphan_pages',
                'duplicate_url_variants',
                'page_has_broken_links',
                'broken_internal_link',
                'page_has_broken_external_links',
                'broken_external_link',
                'lost_file',
                'broken_image',
                'heavy_image',
                'error_spike',
                'deep_pages',
                'thin_content',
                'title_too_short',
                'title_too_long',
                'description_too_short',
                'description_too_long',
                'title_equals_h1',
                'title_equals_description',
                'description_equals_h1',
                'too_many_strong',
                'images_without_alt',
                'meta_spam',
                'h1_spam',
                'text_nausea',
                'text_bigram_spam',
                'text_trigram_spam',
                'no_unique_images',
                'text_in_noindex',
                'not_in_sitemap',
                'sitemap_not_crawled',
                'landing_not_in_sitemap',
                'landing_not_crawled',
                'landing_url_changed',
                'site_availability',
                'index_count_mismatch',
                'serp_snippets',
                'serp_title_mismatch',
                'serp_snippet_source',
                'serp_snippet_cannibalization',
                'psi_mobile',
                'psi_desktop',
                'landing_no_inbound_internal',
                'keyword_cannibalization',
                'ad_cannibalization',
                'landing_query_mismatch',
                'no_outbound_internal',
                'risky_query_params',
                'pagination_param',
            ])
            ->delete();

        if ($findings !== []) {
            $now = now();
            $rows = [];
            foreach ($findings as $f) {
                $meta = $f['meta_json'] ?? null;
                $rows[] = [
                    'crawl_id' => $crawlId,
                    'code' => $f['code'],
                    'severity' => $f['severity'],
                    'url' => $f['url'],
                    'url_hash' => $f['url_hash'],
                    'meta_json' => $meta === null ? null : json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            foreach (array_chunk($rows, 100) as $chunk) {
                SiteAuditFinding::query()->insert($chunk);
            }
        }

        return [
            'internal_links' => $internalLinks,
            'content_unchanged' => ! empty($pageData['content_unchanged']),
        ];
    }

    private function finding(string $code, string $url, string $urlHash, array $meta = []): array
    {
        $cfg = Config::get('site_audit.findings.' . $code, []);

        return [
            'code' => $code,
            'severity' => $cfg['severity'] ?? 'warning',
            'url' => $url,
            'url_hash' => $urlHash,
            'meta_json' => $meta ?: null,
        ];
    }

    /**
     * @param string[] $chain
     * @return array<int, array>
     */
    private function redirectFindings(
        string $url,
        string $urlHash,
        array $chain,
        string $finalUrl,
        bool $forceLoop = false
    ): array {
        $info = SiteAuditRedirectChain::analyze($url, $chain, $finalUrl !== '' ? $finalUrl : null);
        $out = [];

        $finalForKind = $finalUrl !== '' ? $finalUrl : (string) (end($info['path']) ?: '');
        $slashOnly = $finalForKind !== ''
            && SiteAuditRedirectChain::isSlashOnlyRedirect($url, $finalForKind);

        if ($forceLoop || $info['loop']) {
            $out[] = $this->finding('redirect_loop', $url, $urlHash, [
                'final' => $finalUrl !== '' ? $finalUrl : null,
                'chain' => $chain,
                'path' => $info['path'],
                'loop_at' => $info['at'],
                'length' => max(0, count($info['path']) - 1),
                'slash_only' => false,
                'redirect_kind' => 'loop',
            ]);

            return $out;
        }

        $isRedirect = count($chain) >= 1
            || ($finalUrl !== '' && SiteAuditRedirectChain::normalize($finalUrl) !== SiteAuditRedirectChain::normalize($url));
        if (! $isRedirect) {
            return $out;
        }

        $out[] = $this->finding('redirect', $url, $urlHash, [
            'final' => $finalUrl !== '' ? $finalUrl : null,
            'chain' => $chain,
            'path' => $info['path'],
            'length' => max(0, count($info['path']) - 1),
            'slash_only' => $slashOnly,
            'redirect_kind' => $slashOnly ? 'slash_only' : 'other_page',
        ]);

        $maxRedirects = (int) config('site_audit.max_redirects', 10);
        $hops = max(0, count($info['path']) - 1);
        if ($hops >= max(3, (int) floor($maxRedirects / 2))) {
            $out[] = $this->finding('redirect_chain_long', $url, $urlHash, [
                'final' => $finalUrl !== '' ? $finalUrl : null,
                'length' => $hops,
                'chain' => $chain,
                'path' => $info['path'],
                'slash_only' => $slashOnly,
                'redirect_kind' => $slashOnly ? 'slash_only' : 'other_page',
            ]);
        }

        return $out;
    }

    /**
     * Совпадение content_hash + status с прошлым done-краулом проекта.
     */
    private function isContentUnchanged(int $crawlId, string $urlHash, string $contentHash, int $statusCode): bool
    {
        if ($contentHash === '' || ! config('site_audit.incremental_by_content_hash', true)) {
            return false;
        }

        $prevCrawlId = $this->previousDoneCrawlId($crawlId);
        if (! $prevCrawlId) {
            return false;
        }

        $prev = SiteAuditPage::query()
            ->where('crawl_id', $prevCrawlId)
            ->where('url_hash', $urlHash)
            ->first(['content_hash', 'status_code']);

        if (! $prev || ! $prev->content_hash) {
            return false;
        }

        return hash_equals((string) $prev->content_hash, $contentHash)
            && (int) $prev->status_code === $statusCode;
    }

    private function previousDoneCrawlId(int $crawlId): ?int
    {
        static $cache = [];
        if (array_key_exists($crawlId, $cache)) {
            return $cache[$crawlId];
        }

        $projectId = \App\SiteAuditCrawl::query()->where('id', $crawlId)->value('project_id');
        if (! $projectId) {
            return $cache[$crawlId] = null;
        }

        $prevId = \App\SiteAuditCrawl::query()
            ->where('project_id', $projectId)
            ->where('status', \App\SiteAuditCrawl::STATUS_DONE)
            ->where('id', '<', $crawlId)
            ->orderByDesc('id')
            ->value('id');

        return $cache[$crawlId] = $prevId ? (int) $prevId : null;
    }

    /**
     * @return array|null
     */
    private function robotsGroupsForCrawl(int $crawlId): ?array
    {
        static $cache = [];
        if (array_key_exists($crawlId, $cache)) {
            return $cache[$crawlId];
        }

        // не тянем весь progress_json (там urls_gz ~сотни KB)
        $raw = \Illuminate\Support\Facades\DB::table('site_audit_crawls')
            ->where('id', $crawlId)
            ->value(\Illuminate\Support\Facades\DB::raw("JSON_EXTRACT(progress_json, '$.robots.groups')"));
        if ($raw === null || $raw === '') {
            return $cache[$crawlId] = null;
        }
        $groups = is_string($raw) ? json_decode($raw, true) : $raw;

        return $cache[$crawlId] = is_array($groups) ? $groups : null;
    }
}
