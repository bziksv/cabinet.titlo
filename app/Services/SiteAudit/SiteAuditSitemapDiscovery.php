<?php

namespace App\Services\SiteAudit;

use GuzzleHttp\Client;

/**
 * Discover URL из sitemap (+ index) с метаданными для отчётов покрытия/ошибок.
 */
class SiteAuditSitemapDiscovery
{
    /** @var Client */
    private $client;

    public function __construct(?Client $client = null)
    {
        $pool = config('site_audit.user_agents', []);
        $ua = is_array($pool) && $pool
            ? $pool[array_rand($pool)]
            : (string) config('site_audit.user_agent');

        $this->client = $client ?: new Client([
            'timeout' => 20,
            'http_errors' => false,
            'headers' => [
                'User-Agent' => $ua,
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
            ],
        ]);
    }

    /**
     * @return string[] normalized unique URLs (для сида краула)
     */
    public function discover(string $domain, int $limit): array
    {
        $meta = $this->discoverWithMeta($domain, $limit);

        return $meta['seed_urls'];
    }

    /**
     * @return array{
     *   seed_urls: string[],
     *   all_urls: string[],
     *   found: bool,
     *   sources: list<array>,
     *   errors: list<array>
     * }
     */
    public function discoverWithMeta(string $domain, int $seedLimit): array
    {
        $domain = preg_replace('#^https?://#i', '', $domain);
        $domain = rtrim($domain, '/');
        $base = 'https://' . $domain;

        $seedLimit = max(1, $seedLimit);
        $allCap = max($seedLimit, (int) config('site_audit.sitemap_url_cap', 20000));

        $allUrls = [];
        $sources = [];
        $errors = [];
        $visitedSm = [];

        $sitemapUrls = $this->sitemapCandidates($base);

        foreach ($sitemapUrls as $sm) {
            $this->collectFromSitemap(
                $sm,
                $domain,
                $allUrls,
                $allCap,
                $sources,
                $errors,
                $visitedSm,
                0
            );
            if (count($allUrls) >= $allCap) {
                break;
            }
        }

        $found = $allUrls !== [];
        $seed = array_slice(array_keys($allUrls), 0, $seedLimit);

        if (! $found) {
            $home = SiteAuditUrlNormalizer::normalize($base . '/', $domain);
            if ($home) {
                $seed = [$home];
            }
        }

        return [
            'seed_urls' => $seed,
            'all_urls' => array_keys($allUrls),
            'found' => $found,
            'sources' => $sources,
            'errors' => $errors,
        ];
    }

    /**
     * @return string[]
     */
    private function sitemapCandidates(string $base): array
    {
        $list = [
            $base . '/sitemap.xml',
            $base . '/sitemap_index.xml',
            $base . '/sitemap-index.xml',
        ];

        try {
            $res = $this->client->get($base . '/robots.txt');
            if ($res->getStatusCode() >= 200 && $res->getStatusCode() < 400) {
                $body = (string) $res->getBody();
                if (preg_match_all('/^\s*Sitemap:\s*(\S+)/mi', $body, $m)) {
                    foreach ($m[1] as $u) {
                        array_unshift($list, trim($u));
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return array_values(array_unique($list));
    }

    /**
     * @param array<string,bool> $urls
     * @param list<array> $sources
     * @param list<array> $errors
     * @param array<string,bool> $visitedSm
     */
    private function collectFromSitemap(
        string $sitemapUrl,
        string $domain,
        array &$urls,
        int $limit,
        array &$sources,
        array &$errors,
        array &$visitedSm,
        int $depth
    ): void {
        $sitemapUrl = trim($sitemapUrl);
        if ($sitemapUrl === '' || $depth > 4 || count($urls) >= $limit) {
            return;
        }
        if (isset($visitedSm[$sitemapUrl])) {
            return;
        }
        $visitedSm[$sitemapUrl] = true;

        $status = null;
        $xml = '';
        try {
            $res = $this->client->get($sitemapUrl);
            $status = $res->getStatusCode();
            if ($status >= 400) {
                $errors[] = [
                    'sitemap' => $sitemapUrl,
                    'reason' => 'http_' . $status,
                    'status' => $status,
                ];
                $sources[] = [
                    'url' => $sitemapUrl,
                    'ok' => false,
                    'status' => $status,
                    'is_index' => false,
                    'url_count' => 0,
                ];

                return;
            }
            $xml = (string) $res->getBody();
        } catch (\Throwable $e) {
            $errors[] = [
                'sitemap' => $sitemapUrl,
                'reason' => 'fetch_failed',
                'message' => mb_substr($e->getMessage(), 0, 200),
            ];
            $sources[] = [
                'url' => $sitemapUrl,
                'ok' => false,
                'status' => null,
                'is_index' => false,
                'url_count' => 0,
            ];

            return;
        }

        if (trim($xml) === '') {
            $errors[] = [
                'sitemap' => $sitemapUrl,
                'reason' => 'empty',
                'status' => $status,
            ];
            $sources[] = [
                'url' => $sitemapUrl,
                'ok' => false,
                'status' => $status,
                'is_index' => false,
                'url_count' => 0,
            ];

            return;
        }

        $isIndex = false;
        $addedHere = 0;

        if (preg_match_all('#<sitemap>\s*<loc>\s*(.*?)\s*</loc>#is', $xml, $m)) {
            $isIndex = true;
            foreach ($m[1] as $child) {
                $child = html_entity_decode(trim($child), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $this->collectFromSitemap(
                    $child,
                    $domain,
                    $urls,
                    $limit,
                    $sources,
                    $errors,
                    $visitedSm,
                    $depth + 1
                );
                if (count($urls) >= $limit) {
                    break;
                }
            }
        }

        if (preg_match_all('#<url>\s*<loc>\s*(.*?)\s*</loc>#is', $xml, $m2)) {
            foreach ($m2[1] as $loc) {
                $loc = html_entity_decode(trim($loc), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $norm = SiteAuditUrlNormalizer::normalize($loc, $domain);
                if ($norm && ! isset($urls[$norm])) {
                    $urls[$norm] = true;
                    $addedHere++;
                }
                if (count($urls) >= $limit) {
                    break;
                }
            }
        }

        // не считаем ошибкой candidate /sitemap.xml с 200 HTML без loc — просто пустой источник
        $looksXml = stripos($xml, '<urlset') !== false
            || stripos($xml, '<sitemapindex') !== false
            || stripos($xml, '<url>') !== false
            || stripos($xml, '<sitemap>') !== false;

        if (! $isIndex && $addedHere === 0 && $looksXml) {
            // валидный пустой urlset — ok
        } elseif (! $isIndex && $addedHere === 0 && ! $looksXml && $depth === 0) {
            // типичный 404-HTML на /sitemap.xml — не шумим, если есть другие источники;
            // ошибку пишем только если это единственный ответ без loc
            $errors[] = [
                'sitemap' => $sitemapUrl,
                'reason' => 'not_xml',
                'status' => $status,
            ];
        }

        $sources[] = [
            'url' => $sitemapUrl,
            'ok' => $isIndex || $addedHere > 0 || $looksXml,
            'status' => $status,
            'is_index' => $isIndex,
            'url_count' => $addedHere,
        ];
    }
}
