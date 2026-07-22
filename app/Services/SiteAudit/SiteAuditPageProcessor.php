<?php

namespace App\Services\SiteAudit;

use App\SiteAuditFinding;
use App\SiteAuditPage;
use Illuminate\Support\Facades\Config;

class SiteAuditPageProcessor
{
    /** @var SiteAuditFetcher */
    private $fetcher;

    /** @var SiteAuditHtmlParser */
    private $parser;

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
        if ($crawlSettings) {
            $this->fetcher = SiteAuditFetcher::fromCrawlSettings($crawlSettings, $crawlId);
        }

        $rps = (float) ($crawlSettings['rps'] ?? config('site_audit.per_host_rps', 1));
        SiteAuditHostThrottle::wait($projectHost, $rps);

        $urlHash = SiteAuditUrlNormalizer::hash($url);
        $result = $this->fetcher->fetch($url);

        $findings = [];
        $internalLinks = [];

        $robotsGroups = $this->robotsGroupsForCrawl($crawlId);
        if ($robotsGroups !== null) {
            $robots = new SiteAuditRobotsTxt();
            if (! $robots->isPathAllowed($robotsGroups, $url)) {
                $findings[] = $this->finding('robots_blocked', $url, $urlHash, [
                    'source' => 'robots.txt',
                ]);
            }
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
            'canonical' => null,
            'robots_meta' => null,
            'noindex' => false,
            'word_count' => null,
            'content_hash' => null,
            'simhash' => null,
            'out_links_json' => null,
            'img_count' => 0,
            'img_without_alt' => 0,
        ];

        $body = $result['body'] ?? null;

        if (! $result['ok'] || $body === null) {
            $findings[] = $this->finding('unreachable', $url, $urlHash, ['error' => $result['error']]);
        } else {
            $code = (int) $result['status_code'];
            if ($code >= 400 && $code < 500) {
                $findings[] = $this->finding('http_4xx', $url, $urlHash, ['status' => $code]);
            } elseif ($code >= 500) {
                $findings[] = $this->finding('http_5xx', $url, $urlHash, ['status' => $code]);
            }

            $chain = $result['redirect_chain'] ?: [];
            if (count($chain) >= 1 && $result['final_url'] !== $url) {
                $findings[] = $this->finding('redirect', $url, $urlHash, [
                    'final' => $result['final_url'],
                    'chain' => $chain,
                ]);
            }
            $maxRedirects = (int) config('site_audit.max_redirects', 10);
            if (count($chain) >= max(3, (int) floor($maxRedirects / 2))) {
                $findings[] = $this->finding('redirect_chain_long', $url, $urlHash, [
                    'length' => count($chain),
                    'chain' => $chain,
                ]);
            }

            $large = (int) config('site_audit.large_page_bytes', 1_500_000);
            if ($result['size_bytes'] >= $large) {
                $findings[] = $this->finding('page_too_large', $url, $urlHash, [
                    'size_bytes' => $result['size_bytes'],
                    'threshold' => $large,
                ]);
            }

            $isHtml = ! $result['content_type'] || stripos($result['content_type'], 'html') !== false;
            if ($isHtml && $code >= 200 && $code < 400) {
                $parsed = $this->parser->parse($body, $result['final_url'] ?: $url);
                $pageData['title'] = $parsed['title'];
                $pageData['title_hash'] = $parsed['title'] ? hash('sha256', mb_strtolower($parsed['title'])) : null;
                $pageData['description'] = $parsed['description'];
                $pageData['description_hash'] = $parsed['description']
                    ? hash('sha256', mb_strtolower($parsed['description']))
                    : null;
                $pageData['h1'] = $parsed['h1'];
                $pageData['h1_count'] = $parsed['h1_count'];
                $pageData['h2_count'] = $parsed['h2_count'] ?? 0;
                $pageData['canonical'] = $parsed['canonical'];
                $pageData['robots_meta'] = $parsed['robots_meta'];
                $pageData['noindex'] = $parsed['noindex'];
                $pageData['word_count'] = $parsed['word_count'];
                $pageData['text_len'] = $parsed['text_len'] ?? null;
                $pageData['content_hash'] = $parsed['content_hash'];
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
                $pageData['noindex_text_len'] = $parsed['noindex_text_len'] ?? 0;
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
                $pageData['out_links_json'] = array_slice($internalLinks, 0, 150) ?: null;

                if ($links['meta_nofollow'] || ($result['x_robots'] && preg_match('/\bnofollow\b/i', $result['x_robots']))) {
                    $findings[] = $this->finding('meta_nofollow', $url, $urlHash, [
                        'robots' => $pageData['robots_meta'],
                        'x_robots' => $result['x_robots'],
                    ]);
                }
                if ($links['nofollow_links'] > 0) {
                    $findings[] = $this->finding('links_nofollow', $url, $urlHash, [
                        'count' => $links['nofollow_links'],
                    ]);
                }
                if ($links['external_assets']) {
                    $findings[] = $this->finding('external_assets', $url, $urlHash, [
                        'count' => count($links['external_assets']),
                        'samples' => array_slice($links['external_assets'], 0, 5),
                    ]);
                }
                if (! empty($links['external'])) {
                    $findings[] = $this->finding('external_links', $url, $urlHash, [
                        'count' => count($links['external']),
                        'samples' => array_slice($links['external'], 0, 5),
                    ]);
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

                if (! $pageData['title']) {
                    $findings[] = $this->finding('empty_title', $url, $urlHash);
                }
                if (! $pageData['description']) {
                    $findings[] = $this->finding('empty_description', $url, $urlHash);
                }
                if (($parsed['title_count'] ?? 0) > 1 || ($parsed['description_count'] ?? 0) > 1) {
                    $findings[] = $this->finding('multiple_title_or_description', $url, $urlHash, [
                        'title_count' => $parsed['title_count'],
                        'description_count' => $parsed['description_count'],
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
                    $findings[] = $this->finding('multiple_h1', $url, $urlHash, ['count' => $pageData['h1_count']]);
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
                        $findings[] = $this->finding('multiple_canonical', $url, $urlHash, [
                            'count' => (int) $parsed['canonical_count'],
                            'canonical' => $pageData['canonical'],
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
                }
            }
        }

        unset($result['body'], $body);

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
                'no_unique_images',
                'text_in_noindex',
                'not_in_sitemap',
                'sitemap_not_crawled',
                'landing_not_in_sitemap',
                'landing_not_crawled',
                'landing_url_changed',
                'site_availability',
                'index_count_mismatch',
                'no_outbound_internal',
                'risky_query_params',
                'pagination_param',
            ])
            ->delete();

        foreach ($findings as $f) {
            SiteAuditFinding::query()->create($f + ['crawl_id' => $crawlId]);
        }

        return ['internal_links' => $internalLinks];
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
     * @return array|null
     */
    private function robotsGroupsForCrawl(int $crawlId): ?array
    {
        $crawl = \App\SiteAuditCrawl::query()->find($crawlId);
        if (! $crawl) {
            return null;
        }
        $groups = $crawl->progress_json['robots']['groups'] ?? null;

        return is_array($groups) ? $groups : null;
    }
}
