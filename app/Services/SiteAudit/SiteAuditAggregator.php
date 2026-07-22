<?php

namespace App\Services\SiteAudit;

use App\SiteAuditCrawl;
use App\SiteAuditCrawlStat;
use App\SiteAuditFinding;
use App\SiteAuditPage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SiteAuditAggregator
{
    /** Коды, которые считаются только на этапе aggregate (можно пересчитать) */
    private const AGGREGATE_CODES = [
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
    ];

    public function aggregate(SiteAuditCrawl $crawl, bool $notify = true): void
    {
        SiteAuditFinding::query()
            ->where('crawl_id', $crawl->id)
            ->whereIn('code', self::AGGREGATE_CODES)
            ->delete();

        $this->emitDuplicates($crawl->id, 'title_hash', 'duplicate_title');
        $this->emitDuplicates($crawl->id, 'description_hash', 'duplicate_description');
        $this->emitDuplicates($crawl->id, 'content_hash', 'duplicate_content');
        $this->emitSimilarPages($crawl->id);
        $this->emitFromPages($crawl->id);
        $this->emitDuplicateUrlVariants($crawl->id);
        $this->emitOrphans($crawl->id);
        $this->emitNoOutboundInternal($crawl->id);
        $this->emitUrlParamRisks($crawl->id);
        $this->emitBrokenLinks($crawl);
        $depthMeta = $this->emitClickDepth($crawl->id);
        $this->emitSitemapCoverage($crawl);
        $this->emitLandingCoverage($crawl);
        (new SiteAuditAvailabilityProbe())->run($crawl);
        (new SiteAuditSerpIndexProbe())->run($crawl);

        $buckets = [
            'critical' => 0,
            'other' => 0,
            'warning' => 0,
            'info' => 0,
        ];

        $counts = SiteAuditFinding::query()
            ->where('crawl_id', $crawl->id)
            ->select('severity', DB::raw('count(*) as c'))
            ->groupBy('severity')
            ->pluck('c', 'severity')
            ->all();

        foreach ($buckets as $k => $_) {
            $buckets[$k] = (int) ($counts[$k] ?? 0);
        }

        $byCode = SiteAuditFinding::query()
            ->where('crawl_id', $crawl->id)
            ->select('code', DB::raw('count(*) as c'))
            ->groupBy('code')
            ->pluck('c', 'code')
            ->all();

        $byCode['pages_with_canonical'] = (int) SiteAuditPage::query()
            ->where('crawl_id', $crawl->id)
            ->whereNotNull('canonical')
            ->where('canonical', '!=', '')
            ->count();

        $byCode['click_depth_max'] = (int) ($depthMeta['click_depth_max'] ?? 0);

        foreach ($buckets as $bucket => $value) {
            SiteAuditCrawlStat::query()->updateOrCreate(
                ['crawl_id' => $crawl->id, 'bucket' => $bucket],
                ['value' => $value]
            );
        }

        $crawl->buckets_json = $buckets;
        $crawl->counts_json = $byCode;
        $crawl->status = SiteAuditCrawl::STATUS_DONE;
        $crawl->finished_at = now();
        $crawl->save();

        try {
            (new SiteAuditPruner())->pruneProject((int) $crawl->project_id);
        } catch (\Throwable $e) {
            Log::warning('SiteAudit prune failed: ' . $e->getMessage(), [
                'project_id' => $crawl->project_id,
            ]);
        }

        if ($notify) {
            $this->notifyOwner($crawl);
        }
    }

    private function emitFromPages(int $crawlId): void
    {
        $thin = (int) config('site_audit.thin_words', 150);
        $titleMin = (int) config('site_audit.title_min', 30);
        $titleMax = (int) config('site_audit.title_max', 70);
        $descMin = (int) config('site_audit.description_min', 70);
        $descMax = (int) config('site_audit.description_max', 160);

        SiteAuditPage::query()
            ->where('crawl_id', $crawlId)
            ->orderBy('id')
            ->chunkById(200, function ($pages) use ($crawlId, $thin, $titleMin, $titleMax, $descMin, $descMax) {
                foreach ($pages as $page) {
                    $findings = [];

                    if ($page->word_count !== null && (int) $page->word_count > 0 && (int) $page->word_count < $thin) {
                        $findings[] = $this->row($crawlId, 'thin_content', $page, [
                            'word_count' => (int) $page->word_count,
                            'threshold' => $thin,
                        ]);
                    }

                    if ($page->title) {
                        $len = mb_strlen($page->title);
                        if ($len < $titleMin) {
                            $findings[] = $this->row($crawlId, 'title_too_short', $page, [
                                'length' => $len,
                                'min' => $titleMin,
                                'title' => $page->title,
                            ]);
                        } elseif ($len > $titleMax) {
                            $findings[] = $this->row($crawlId, 'title_too_long', $page, [
                                'length' => $len,
                                'max' => $titleMax,
                                'title' => $page->title,
                            ]);
                        }
                    }

                    if ($page->description) {
                        $len = mb_strlen($page->description);
                        if ($len < $descMin) {
                            $findings[] = $this->row($crawlId, 'description_too_short', $page, [
                                'length' => $len,
                                'min' => $descMin,
                            ]);
                        } elseif ($len > $descMax) {
                            $findings[] = $this->row($crawlId, 'description_too_long', $page, [
                                'length' => $len,
                                'max' => $descMax,
                            ]);
                        }
                    }

                    if ($page->title && $page->h1) {
                        if (mb_strtolower(trim($page->title)) === mb_strtolower(trim($page->h1))) {
                            $findings[] = $this->row($crawlId, 'title_equals_h1', $page, [
                                'title' => $page->title,
                                'h1' => $page->h1,
                            ]);
                        }
                    }

                    if ($page->title && $page->description) {
                        if (mb_strtolower(trim($page->title)) === mb_strtolower(trim($page->description))) {
                            $findings[] = $this->row($crawlId, 'title_equals_description', $page, [
                                'title' => $page->title,
                            ]);
                        }
                    }

                    if ($page->description && $page->h1) {
                        if (mb_strtolower(trim($page->description)) === mb_strtolower(trim($page->h1))) {
                            $findings[] = $this->row($crawlId, 'description_equals_h1', $page, [
                                'description' => $page->description,
                                'h1' => $page->h1,
                            ]);
                        }
                    }

                    $strongMax = (int) config('site_audit.strong_max', 20);
                    if ((int) $page->strong_count > $strongMax) {
                        $findings[] = $this->row($crawlId, 'too_many_strong', $page, [
                            'strong_count' => (int) $page->strong_count,
                            'threshold' => $strongMax,
                        ]);
                    }

                    if ((int) $page->img_without_alt > 0) {
                        $findings[] = $this->row($crawlId, 'images_without_alt', $page, [
                            'img_without_alt' => (int) $page->img_without_alt,
                            'img_count' => (int) $page->img_count,
                        ]);
                    }

                    if ((int) $page->unique_img_src_count === 0) {
                        $findings[] = $this->row($crawlId, 'no_unique_images', $page, [
                            'img_count' => (int) $page->img_count,
                            'unique_img_src_count' => 0,
                        ]);
                    }

                    $titleSpam = SiteAuditTextMetrics::fieldSpam($page->title);
                    $descSpam = SiteAuditTextMetrics::fieldSpam($page->description);
                    if ($titleSpam['spam'] || $descSpam['spam']) {
                        $findings[] = $this->row($crawlId, 'meta_spam', $page, [
                            'title' => $titleSpam['spam'] ? [
                                'word' => $titleSpam['word'],
                                'count' => $titleSpam['count'],
                            ] : null,
                            'description' => $descSpam['spam'] ? [
                                'word' => $descSpam['word'],
                                'count' => $descSpam['count'],
                            ] : null,
                        ]);
                    }

                    $h1Spam = SiteAuditTextMetrics::fieldSpam($page->h1);
                    if ($h1Spam['spam']) {
                        $findings[] = $this->row($crawlId, 'h1_spam', $page, [
                            'word' => $h1Spam['word'],
                            'count' => $h1Spam['count'],
                            'h1' => $page->h1,
                        ]);
                    }

                    $nauseaClassicMax = (float) config('site_audit.nausea_classic_max', 8.0);
                    $nauseaAcademicMax = (float) config('site_audit.nausea_academic_max', 10.0);
                    $classic = $page->nausea_classic !== null ? (float) $page->nausea_classic : null;
                    $academic = $page->nausea_academic !== null ? (float) $page->nausea_academic : null;
                    if (($classic !== null && $classic >= $nauseaClassicMax)
                        || ($academic !== null && $academic >= $nauseaAcademicMax)
                    ) {
                        $findings[] = $this->row($crawlId, 'text_nausea', $page, [
                            'nausea_classic' => $classic,
                            'nausea_academic' => $academic,
                            'top_word' => $page->top_word,
                            'top_word_count' => (int) $page->top_word_count,
                            'threshold_classic' => $nauseaClassicMax,
                            'threshold_academic' => $nauseaAcademicMax,
                        ]);
                    }

                    $bigramMin = (int) config('site_audit.bigram_spam_min', 4);
                    $bigramDensityMin = (float) config('site_audit.bigram_spam_density_min', 1.5);
                    $bgCount = (int) $page->top_bigram_count;
                    $words = (int) $page->word_count;
                    $bgDensity = ($words > 0 && $bgCount > 0)
                        ? round(($bgCount / $words) * 100, 2)
                        : 0.0;
                    if ($page->top_bigram && $bgCount >= $bigramMin && $bgDensity >= $bigramDensityMin) {
                        $findings[] = $this->row($crawlId, 'text_bigram_spam', $page, [
                            'bigram' => $page->top_bigram,
                            'count' => $bgCount,
                            'density' => $bgDensity,
                            'threshold_count' => $bigramMin,
                            'threshold_density' => $bigramDensityMin,
                        ]);
                    }

                    $noindexMin = (int) config('site_audit.noindex_text_min', 40);
                    if ((int) $page->noindex_text_len >= $noindexMin) {
                        $findings[] = $this->row($crawlId, 'text_in_noindex', $page, [
                            'noindex_text_len' => (int) $page->noindex_text_len,
                            'threshold' => $noindexMin,
                        ]);
                    }

                    if ($this->looksLikeSoft404($page, $thin)) {
                        $findings[] = $this->row($crawlId, 'soft_404', $page, [
                            'status' => (int) $page->status_code,
                            'word_count' => (int) $page->word_count,
                            'title' => $page->title,
                        ]);
                    }

                    foreach ($findings as $f) {
                        SiteAuditFinding::query()->create($f);
                    }
                }
            });
    }

    private function looksLikeSoft404(SiteAuditPage $page, int $thin): bool
    {
        if ((int) $page->status_code !== 200) {
            return false;
        }

        $title = mb_strtolower((string) $page->title);
        $h1 = mb_strtolower((string) $page->h1);
        $patterns = config('site_audit.soft_404_title_patterns', [
            '404',
            'not found',
            'page not found',
            'страница не найдена',
            'не найдена',
            'ошибка 404',
        ]);
        foreach ($patterns as $p) {
            $p = mb_strtolower((string) $p);
            if ($p !== '' && (mb_strpos($title, $p) !== false || mb_strpos($h1, $p) !== false)) {
                return true;
            }
        }

        // очень тощий ответ при 200 — кандидат soft-404 (жёстче обычного thin)
        $softThin = max(20, (int) floor($thin / 3));
        if ($page->word_count !== null && (int) $page->word_count > 0 && (int) $page->word_count < $softThin) {
            return true;
        }

        return false;
    }

    private function emitSitemapCoverage(SiteAuditCrawl $crawl): void
    {
        $sm = is_array($crawl->progress_json['sitemap'] ?? null) ? $crawl->progress_json['sitemap'] : null;
        if (! $sm || empty($sm['found'])) {
            return;
        }

        $sitemapUrls = SiteAuditSitemapProbe::urlsFromProgress($crawl);
        if ($sitemapUrls === []) {
            return;
        }

        $sitemapSet = array_fill_keys($sitemapUrls, true);
        $sampleNotCrawled = (int) config('site_audit.sitemap_not_crawled_sample', 80);
        $maxNotIn = (int) config('site_audit.not_in_sitemap_max', 500);

        $crawled = SiteAuditPage::query()
            ->where('crawl_id', $crawl->id)
            ->where('status_code', '>=', 200)
            ->where('status_code', '<', 400)
            ->get(['url', 'url_hash', 'status_code', 'content_type']);

        $crawledSet = [];
        $notIn = 0;
        foreach ($crawled as $page) {
            $crawledSet[$page->url] = true;
            if (isset($sitemapSet[$page->url])) {
                continue;
            }
            // только HTML-подобные / без content_type
            $ct = (string) ($page->content_type ?? '');
            if ($ct !== '' && stripos($ct, 'html') === false && stripos($ct, 'text') === false) {
                continue;
            }
            if ($notIn >= $maxNotIn) {
                continue;
            }
            SiteAuditFinding::query()->create($this->row($crawl->id, 'not_in_sitemap', $page, [
                'sitemap_url_count' => count($sitemapUrls),
            ]));
            $notIn++;
        }

        $emitted = 0;
        foreach ($sitemapUrls as $u) {
            if (isset($crawledSet[$u])) {
                continue;
            }
            if ($emitted >= $sampleNotCrawled) {
                break;
            }
            $hash = SiteAuditUrlNormalizer::hash($u);
            $cfg = config('site_audit.findings.sitemap_not_crawled', []);
            SiteAuditFinding::query()->create([
                'crawl_id' => $crawl->id,
                'code' => 'sitemap_not_crawled',
                'severity' => $cfg['severity'] ?? 'info',
                'url' => $u,
                'url_hash' => $hash,
                'meta_json' => [
                    'reason' => 'in_sitemap_not_in_crawl',
                    'pages_limit' => (int) $crawl->pages_limit,
                ],
            ]);
            $emitted++;
        }
    }

    private function emitLandingCoverage(SiteAuditCrawl $crawl): void
    {
        $resolved = (new SiteAuditLandingResolver())->forCrawl($crawl);
        $landings = $resolved['urls'];
        $byKeyword = is_array($resolved['by_keyword'] ?? null) ? $resolved['by_keyword'] : [];

        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        $progress['landings'] = [
            'count' => count($landings),
            'monitoring_project_ids' => $resolved['project_ids'],
            'raw_count' => $resolved['raw_count'],
            'by_keyword' => $byKeyword,
        ];
        $crawl->progress_json = $progress;
        $crawl->save();

        if ($landings === []) {
            return;
        }

        $this->emitLandingUrlChanges($crawl, $byKeyword, $resolved['project_ids']);

        $sitemapUrls = SiteAuditSitemapProbe::urlsFromProgress($crawl);
        $sitemapSet = $sitemapUrls !== [] ? array_fill_keys($sitemapUrls, true) : null;
        $sitemapFound = ! empty($crawl->progress_json['sitemap']['found']);

        $crawledUrls = SiteAuditPage::query()
            ->where('crawl_id', $crawl->id)
            ->pluck('url')
            ->all();
        $crawledSet = array_fill_keys($crawledUrls, true);

        $max = (int) config('site_audit.landing_findings_max', 300);
        $notInSm = 0;
        $notCrawled = 0;

        foreach ($landings as $url) {
            if ($sitemapFound && is_array($sitemapSet) && ! isset($sitemapSet[$url])) {
                if ($notInSm < $max) {
                    $cfg = config('site_audit.findings.landing_not_in_sitemap', []);
                    SiteAuditFinding::query()->create([
                        'crawl_id' => $crawl->id,
                        'code' => 'landing_not_in_sitemap',
                        'severity' => $cfg['severity'] ?? 'warning',
                        'url' => $url,
                        'url_hash' => SiteAuditUrlNormalizer::hash($url),
                        'meta_json' => [
                            'source' => 'monitoring',
                            'monitoring_project_ids' => $resolved['project_ids'],
                        ],
                    ]);
                    $notInSm++;
                }
            }

            if (! isset($crawledSet[$url])) {
                if ($notCrawled < $max) {
                    $cfg = config('site_audit.findings.landing_not_crawled', []);
                    SiteAuditFinding::query()->create([
                        'crawl_id' => $crawl->id,
                        'code' => 'landing_not_crawled',
                        'severity' => $cfg['severity'] ?? 'warning',
                        'url' => $url,
                        'url_hash' => SiteAuditUrlNormalizer::hash($url),
                        'meta_json' => [
                            'source' => 'monitoring',
                            'pages_limit' => (int) $crawl->pages_limit,
                            'monitoring_project_ids' => $resolved['project_ids'],
                        ],
                    ]);
                    $notCrawled++;
                }
            }
        }
    }

    /**
     * Сравнение снимка посадочных (monitoring.page) с предыдущим done-краулом проекта.
     *
     * @param array<string, array{url: string, query: string, project_id?: int}> $byKeyword
     * @param int[] $projectIds
     */
    private function emitLandingUrlChanges(SiteAuditCrawl $crawl, array $byKeyword, array $projectIds): void
    {
        if ($byKeyword === [] || ! $crawl->project_id) {
            return;
        }

        $prev = SiteAuditCrawl::query()
            ->where('project_id', $crawl->project_id)
            ->where('id', '<', $crawl->id)
            ->where('status', SiteAuditCrawl::STATUS_DONE)
            ->orderByDesc('id')
            ->first(['id', 'progress_json']);

        if (! $prev) {
            return;
        }

        $prevMap = $prev->progress_json['landings']['by_keyword'] ?? null;
        if (! is_array($prevMap) || $prevMap === []) {
            return;
        }

        $max = (int) config('site_audit.landing_findings_max', 300);
        $emitted = 0;
        $cfg = config('site_audit.findings.landing_url_changed', []);

        foreach ($byKeyword as $kid => $cur) {
            if ($emitted >= $max) {
                break;
            }
            $old = $prevMap[(string) $kid] ?? null;
            if (! is_array($old) || empty($old['url']) || empty($cur['url'])) {
                continue;
            }
            if ((string) $old['url'] === (string) $cur['url']) {
                continue;
            }

            SiteAuditFinding::query()->create([
                'crawl_id' => $crawl->id,
                'code' => 'landing_url_changed',
                'severity' => $cfg['severity'] ?? 'warning',
                'url' => (string) $cur['url'],
                'url_hash' => SiteAuditUrlNormalizer::hash((string) $cur['url']),
                'meta_json' => [
                    'source' => 'monitoring',
                    'monitoring_keyword_id' => (int) $kid,
                    'query' => (string) ($cur['query'] ?? ''),
                    'old_url' => (string) $old['url'],
                    'new_url' => (string) $cur['url'],
                    'prev_crawl_id' => (int) $prev->id,
                    'monitoring_project_ids' => $projectIds,
                ],
            ]);
            $emitted++;
        }
    }

    private function emitOrphans(int $crawlId): void
    {
        $pages = SiteAuditPage::query()
            ->where('crawl_id', $crawlId)
            ->get(['id', 'url', 'url_hash', 'out_links_json', 'status_code']);

        if ($pages->count() < 2) {
            return;
        }

        $byHash = [];
        $byUrl = [];
        foreach ($pages as $page) {
            $byHash[$page->url_hash] = $page;
            $byUrl[$page->url] = $page;
        }

        $inbound = [];
        foreach ($pages as $page) {
            $outs = is_array($page->out_links_json) ? $page->out_links_json : [];
            foreach ($outs as $out) {
                $out = (string) $out;
                if ($out === '') {
                    continue;
                }
                // совместимость: раньше писали hash
                if (isset($byHash[$out])) {
                    $inbound[$out] = ($inbound[$out] ?? 0) + 1;
                    continue;
                }
                if (isset($byUrl[$out])) {
                    $h = $byUrl[$out]->url_hash;
                    $inbound[$h] = ($inbound[$h] ?? 0) + 1;
                    continue;
                }
                $h = SiteAuditUrlNormalizer::hash($out);
                if (isset($byHash[$h])) {
                    $inbound[$h] = ($inbound[$h] ?? 0) + 1;
                }
            }
        }

        $severity = config('site_audit.findings.orphan_pages.severity', 'warning');
        foreach ($pages as $page) {
            $path = parse_url($page->url, PHP_URL_PATH);
            if ($path === '/' || $path === '' || $path === null) {
                continue;
            }
            if (! empty($inbound[$page->url_hash])) {
                continue;
            }
            // только успешные HTML-страницы
            $code = (int) $page->status_code;
            if ($code && ($code < 200 || $code >= 400)) {
                continue;
            }

            SiteAuditFinding::query()->create([
                'crawl_id' => $crawlId,
                'code' => 'orphan_pages',
                'severity' => $severity,
                'url' => $page->url,
                'url_hash' => $page->url_hash,
                'meta_json' => ['reason' => 'no_inbound_links'],
            ]);
        }
    }

    /**
     * Успешные страницы без исходящих внутренних ссылок (тупики).
     */
    private function emitNoOutboundInternal(int $crawlId): void
    {
        $severity = config('site_audit.findings.no_outbound_internal.severity', 'info');
        $max = (int) config('site_audit.no_outbound_internal_max', 500);

        $emitted = 0;
        SiteAuditPage::query()
            ->where('crawl_id', $crawlId)
            ->orderBy('id')
            ->chunkById(200, function ($pages) use ($crawlId, $severity, $max, &$emitted) {
                foreach ($pages as $page) {
                    if ($emitted >= $max) {
                        return false;
                    }
                    $code = (int) $page->status_code;
                    if ($code < 200 || $code >= 400) {
                        continue;
                    }
                    $path = parse_url($page->url, PHP_URL_PATH);
                    if ($path === '/' || $path === '' || $path === null) {
                        continue;
                    }
                    $outs = is_array($page->out_links_json) ? $page->out_links_json : [];
                    if ($outs !== []) {
                        continue;
                    }
                    SiteAuditFinding::query()->create([
                        'crawl_id' => $crawlId,
                        'code' => 'no_outbound_internal',
                        'severity' => $severity,
                        'url' => $page->url,
                        'url_hash' => $page->url_hash,
                        'meta_json' => ['reason' => 'empty_out_links'],
                    ]);
                    $emitted++;
                }
            });
    }

    /**
     * Рисковые session/sort params и пагинация/facets в URL.
     */
    private function emitUrlParamRisks(int $crawlId): void
    {
        $riskyKeys = config('site_audit.risky_query_keys', [
            'phpsessid', 'sid', 'sessionid', 'session_id', 'jsessionid',
            'sort', 'order', 'orderby', 'sortby',
        ]);
        if (! is_array($riskyKeys)) {
            $riskyKeys = [];
        }
        $riskyKeys = array_map('strtolower', $riskyKeys);

        $paginationKeys = config('site_audit.pagination_query_keys', [
            'page', 'p', 'pagen_1', 'paged', 'offset', 'start',
        ]);
        if (! is_array($paginationKeys)) {
            $paginationKeys = [];
        }
        $paginationKeys = array_map('strtolower', $paginationKeys);

        $facetKeys = config('site_audit.facet_query_keys', [
            'filter', 'filters', 'facet', 'facets',
        ]);
        if (! is_array($facetKeys)) {
            $facetKeys = [];
        }
        $facetKeys = array_map('strtolower', $facetKeys);

        $maxRisky = (int) config('site_audit.risky_query_max', 300);
        $maxPag = (int) config('site_audit.pagination_param_max', 300);
        $emittedRisky = 0;
        $emittedPag = 0;

        SiteAuditPage::query()
            ->where('crawl_id', $crawlId)
            ->orderBy('id')
            ->chunkById(200, function ($pages) use (
                $crawlId,
                $riskyKeys,
                $paginationKeys,
                $facetKeys,
                $maxRisky,
                $maxPag,
                &$emittedRisky,
                &$emittedPag
            ) {
                foreach ($pages as $page) {
                    $query = parse_url($page->url, PHP_URL_QUERY);
                    $path = (string) (parse_url($page->url, PHP_URL_PATH) ?: '');
                    $params = [];
                    if (is_string($query) && $query !== '') {
                        parse_str($query, $params);
                    }
                    $keys = array_map('strtolower', array_keys($params));

                    if ($emittedRisky < $maxRisky) {
                        $hit = array_values(array_intersect($keys, $riskyKeys));
                        $manyKeys = count($keys) >= (int) config('site_audit.risky_query_key_count', 8);
                        $longQuery = is_string($query) && strlen($query) >= (int) config('site_audit.risky_query_len', 120);
                        if ($hit !== [] || $manyKeys || $longQuery) {
                            $cfg = config('site_audit.findings.risky_query_params', []);
                            SiteAuditFinding::query()->create([
                                'crawl_id' => $crawlId,
                                'code' => 'risky_query_params',
                                'severity' => $cfg['severity'] ?? 'warning',
                                'url' => $page->url,
                                'url_hash' => $page->url_hash,
                                'meta_json' => [
                                    'keys' => $hit,
                                    'key_count' => count($keys),
                                    'query_len' => is_string($query) ? strlen($query) : 0,
                                    'many_keys' => $manyKeys,
                                    'long_query' => $longQuery,
                                ],
                            ]);
                            $emittedRisky++;
                        }
                    }

                    if ($emittedPag < $maxPag) {
                        $pagHit = array_values(array_intersect($keys, $paginationKeys));
                        $facetHit = array_values(array_intersect($keys, $facetKeys));
                        $pathPag = (bool) preg_match('#/(?:page|pagen)/\d+(?:/|$|\?)#i', $path)
                            || (bool) preg_match('#/page-\d+(?:/|$|\?)#i', $path);
                        if ($pagHit !== [] || $facetHit !== [] || $pathPag) {
                            $cfg = config('site_audit.findings.pagination_param', []);
                            SiteAuditFinding::query()->create([
                                'crawl_id' => $crawlId,
                                'code' => 'pagination_param',
                                'severity' => $cfg['severity'] ?? 'info',
                                'url' => $page->url,
                                'url_hash' => $page->url_hash,
                                'meta_json' => [
                                    'pagination_keys' => $pagHit,
                                    'facet_keys' => $facetHit,
                                    'path_pagination' => $pathPag,
                                ],
                            ]);
                            $emittedPag++;
                        }
                    }

                    if ($emittedRisky >= $maxRisky && $emittedPag >= $maxPag) {
                        return false;
                    }
                }
            });
    }

    private function emitDuplicateUrlVariants(int $crawlId): void
    {
        $pages = SiteAuditPage::query()
            ->where('crawl_id', $crawlId)
            ->get(['id', 'url', 'url_hash']);

        $groups = [];
        foreach ($pages as $page) {
            $key = SiteAuditUrlNormalizer::canonicalKey($page->url);
            if (! $key) {
                continue;
            }
            $groups[$key][] = $page;
        }

        $severity = config('site_audit.findings.duplicate_url_variants.severity', 'other');
        foreach ($groups as $key => $group) {
            if (count($group) < 2) {
                continue;
            }
            $variants = array_map(static function ($p) {
                return $p->url;
            }, $group);
            foreach ($group as $page) {
                SiteAuditFinding::query()->create([
                    'crawl_id' => $crawlId,
                    'code' => 'duplicate_url_variants',
                    'severity' => $severity,
                    'url' => $page->url,
                    'url_hash' => $page->url_hash,
                    'meta_json' => [
                        'canonical_key' => $key,
                        'variants' => $variants,
                        'count' => count($variants),
                    ],
                ]);
            }
        }
    }

    private function emitBrokenLinks(SiteAuditCrawl $crawl): void
    {
        $settings = is_array($crawl->progress_json['settings'] ?? null)
            ? $crawl->progress_json['settings']
            : [];
        if (array_key_exists('check_broken_links', $settings) && ! $settings['check_broken_links']) {
            return;
        }

        $pages = SiteAuditPage::query()
            ->where('crawl_id', $crawl->id)
            ->get(['id', 'url', 'url_hash', 'out_links_json', 'status_code']);

        if ($pages->isEmpty()) {
            return;
        }

        $byUrl = [];
        $byHash = [];
        foreach ($pages as $page) {
            $byUrl[$page->url] = $page;
            $byHash[$page->url_hash] = $page;
        }

        $maxHead = (int) config('site_audit.broken_link_head_max', 40);
        $checker = new SiteAuditLinkChecker();
        $headCache = [];
        $headBudget = $maxHead;

        $pageSev = config('site_audit.findings.page_has_broken_links.severity', 'warning');
        $linkSev = config('site_audit.findings.broken_internal_link.severity', 'critical');

        foreach ($pages as $page) {
            $outs = is_array($page->out_links_json) ? $page->out_links_json : [];
            if (! $outs) {
                continue;
            }

            $brokenSamples = [];
            foreach ($outs as $out) {
                $out = (string) $out;
                if ($out === '' || (strlen($out) === 64 && ctype_xdigit($out))) {
                    // старый формат hash — только сверка с краулом
                    if (isset($byHash[$out])) {
                        $target = $byHash[$out];
                        $code = $target->status_code;
                        if ($code === null || (int) $code >= 400) {
                            $brokenSamples[] = [
                                'url' => $target->url,
                                'status' => $code !== null ? (int) $code : null,
                                'source' => 'crawl',
                            ];
                        }
                    }
                    continue;
                }

                if (isset($byUrl[$out])) {
                    $target = $byUrl[$out];
                    $code = $target->status_code;
                    if ($code === null || (int) $code >= 400) {
                        $brokenSamples[] = [
                            'url' => $out,
                            'status' => $code !== null ? (int) $code : null,
                            'source' => 'crawl',
                        ];
                    }
                    continue;
                }

                $h = SiteAuditUrlNormalizer::hash($out);
                if (isset($byHash[$h])) {
                    $target = $byHash[$h];
                    $code = $target->status_code;
                    if ($code === null || (int) $code >= 400) {
                        $brokenSamples[] = [
                            'url' => $out,
                            'status' => $code !== null ? (int) $code : null,
                            'source' => 'crawl',
                        ];
                    }
                    continue;
                }

                // не в крауле — выборочный HEAD
                if ($headBudget <= 0) {
                    continue;
                }
                if (! array_key_exists($out, $headCache)) {
                    $headCache[$out] = $checker->check($out);
                    $headBudget--;
                }
                $res = $headCache[$out];
                if (! $res['ok']) {
                    $brokenSamples[] = [
                        'url' => $out,
                        'status' => $res['status'],
                        'error' => $res['error'],
                        'source' => 'head',
                    ];
                }
            }

            if (! $brokenSamples) {
                continue;
            }

            $brokenSamples = array_slice($brokenSamples, 0, 10);
            SiteAuditFinding::query()->create([
                'crawl_id' => $crawl->id,
                'code' => 'page_has_broken_links',
                'severity' => $pageSev,
                'url' => $page->url,
                'url_hash' => $page->url_hash,
                'meta_json' => [
                    'count' => count($brokenSamples),
                    'samples' => $brokenSamples,
                ],
            ]);

            // отдельные finding по уникальным битым URL (cap)
            $seenBroken = [];
                foreach (array_slice($brokenSamples, 0, 5) as $sample) {
                $bu = (string) ($sample['url'] ?? '');
                if ($bu === '' || isset($seenBroken[$bu])) {
                    continue;
                }
                $seenBroken[$bu] = true;
                SiteAuditFinding::query()->create([
                    'crawl_id' => $crawl->id,
                    'code' => 'broken_internal_link',
                    'severity' => $linkSev,
                    'url' => $bu,
                    'url_hash' => SiteAuditUrlNormalizer::hash($bu),
                    'meta_json' => [
                        'from' => $page->url,
                        'status' => $sample['status'] ?? null,
                        'source' => $sample['source'] ?? null,
                    ],
                ]);
            }
        }
    }

    /**
     * BFS по out_links от главной → click_depth + deep_pages.
     *
     * @return array{click_depth_max:int}
     */
    private function emitClickDepth(int $crawlId): array
    {
        $pages = SiteAuditPage::query()
            ->where('crawl_id', $crawlId)
            ->get(['id', 'url', 'url_hash', 'out_links_json']);

        if ($pages->isEmpty()) {
            return ['click_depth_max' => 0];
        }

        $byUrl = [];
        $byHash = [];
        foreach ($pages as $page) {
            $byUrl[$page->url] = $page;
            $byHash[$page->url_hash] = $page;
        }

        $depth = [];
        $queue = [];
        foreach ($pages as $page) {
            $path = parse_url($page->url, PHP_URL_PATH);
            if ($path === '/' || $path === '' || $path === null) {
                $depth[$page->id] = 0;
                $queue[] = $page;
            }
        }

        if (! $queue) {
            // нет «корня» — берём страницу с самым коротким path
            $best = null;
            $bestLen = PHP_INT_MAX;
            foreach ($pages as $page) {
                $path = (string) (parse_url($page->url, PHP_URL_PATH) ?: '/');
                $len = strlen($path);
                if ($len < $bestLen) {
                    $bestLen = $len;
                    $best = $page;
                }
            }
            if ($best) {
                $depth[$best->id] = 0;
                $queue[] = $best;
            }
        }

        $qi = 0;
        while ($qi < count($queue)) {
            $cur = $queue[$qi++];
            $curDepth = $depth[$cur->id];
            $outs = is_array($cur->out_links_json) ? $cur->out_links_json : [];
            foreach ($outs as $out) {
                $out = (string) $out;
                $target = null;
                if (isset($byUrl[$out])) {
                    $target = $byUrl[$out];
                } elseif (isset($byHash[$out])) {
                    $target = $byHash[$out];
                } else {
                    $h = SiteAuditUrlNormalizer::hash($out);
                    if (isset($byHash[$h])) {
                        $target = $byHash[$h];
                    }
                }
                if (! $target || isset($depth[$target->id])) {
                    continue;
                }
                $depth[$target->id] = $curDepth + 1;
                $queue[] = $target;
            }
        }

        $warnAt = (int) config('site_audit.click_depth_warn', 4);
        $severity = config('site_audit.findings.deep_pages.severity', 'info');
        $maxDepth = 0;
        $byDepthIds = [];

        foreach ($pages as $page) {
            $d = array_key_exists($page->id, $depth) ? $depth[$page->id] : null;
            if ($d !== null && $d > $maxDepth) {
                $maxDepth = $d;
            }
            $key = $d === null ? 'null' : (string) $d;
            $byDepthIds[$key][] = $page->id;

            if ($d !== null && $d >= $warnAt) {
                SiteAuditFinding::query()->create([
                    'crawl_id' => $crawlId,
                    'code' => 'deep_pages',
                    'severity' => $severity,
                    'url' => $page->url,
                    'url_hash' => $page->url_hash,
                    'meta_json' => [
                        'depth' => $d,
                        'threshold' => $warnAt,
                    ],
                ]);
            }
        }

        foreach ($byDepthIds as $key => $ids) {
            $val = $key === 'null' ? null : (int) $key;
            foreach (array_chunk($ids, 200) as $chunk) {
                SiteAuditPage::query()->whereIn('id', $chunk)->update(['click_depth' => $val]);
            }
        }

        return ['click_depth_max' => $maxDepth];
    }

    private function row(int $crawlId, string $code, SiteAuditPage $page, array $meta): array
    {
        $cfg = config('site_audit.findings.' . $code, []);

        return [
            'crawl_id' => $crawlId,
            'code' => $code,
            'severity' => $cfg['severity'] ?? 'warning',
            'url' => $page->url,
            'url_hash' => $page->url_hash,
            'meta_json' => $meta,
        ];
    }

    private function notifyOwner(SiteAuditCrawl $crawl): void
    {
        try {
            $user = \App\User::query()->find($crawl->user_id);
            if (! $user || ! $user->email) {
                return;
            }
            $user->notify(new \App\Notifications\SiteAuditCrawlCompletedNotification($crawl));
        } catch (\Throwable $e) {
            Log::warning('SiteAudit notify failed: ' . $e->getMessage(), [
                'crawl_id' => $crawl->id,
            ]);
        }
    }

    private function emitSimilarPages(int $crawlId): void
    {
        $threshold = (int) config('site_audit.simhash_hamming_max', 6);
        $maxPairs = (int) config('site_audit.simhash_max_pairs', 200);

        $pages = SiteAuditPage::query()
            ->where('crawl_id', $crawlId)
            ->whereNotNull('simhash')
            ->where('simhash', '!=', '')
            ->orderBy('id')
            ->get(['id', 'url', 'url_hash', 'simhash', 'title']);

        $n = $pages->count();
        if ($n < 2) {
            return;
        }

        // ограничиваем pairwise для больших краулов
        if ($n > 800) {
            $pages = $pages->take(800)->values();
            $n = $pages->count();
        }

        $severity = config('site_audit.findings.similar_pages.severity', 'warning');
        $emitted = 0;
        $seen = [];

        for ($i = 0; $i < $n && $emitted < $maxPairs; $i++) {
            $a = $pages[$i];
            for ($j = $i + 1; $j < $n && $emitted < $maxPairs; $j++) {
                $b = $pages[$j];
                $dist = SiteAuditSimhash::hamming($a->simhash, $b->simhash);
                if ($dist > $threshold) {
                    continue;
                }
                // не дублируем exact content_hash пары — они в duplicate_content
                foreach ([$a, $b] as $page) {
                    $key = $page->url_hash;
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $other = $page->id === $a->id ? $b : $a;
                    SiteAuditFinding::query()->create([
                        'crawl_id' => $crawlId,
                        'code' => 'similar_pages',
                        'severity' => $severity,
                        'url' => $page->url,
                        'url_hash' => $page->url_hash,
                        'meta_json' => [
                            'similar_url' => $other->url,
                            'hamming' => $dist,
                            'title' => $page->title,
                        ],
                    ]);
                    $seen[$key] = true;
                    $emitted++;
                    if ($emitted >= $maxPairs) {
                        break 2;
                    }
                }
            }
        }
    }

    private function emitDuplicates(int $crawlId, string $hashColumn, string $code): void
    {
        $severity = config('site_audit.findings.' . $code . '.severity', 'other');

        $groups = SiteAuditPage::query()
            ->where('crawl_id', $crawlId)
            ->whereNotNull($hashColumn)
            ->select($hashColumn, DB::raw('count(*) as c'))
            ->groupBy($hashColumn)
            ->having('c', '>', 1)
            ->pluck('c', $hashColumn);

        foreach ($groups as $hash => $count) {
            $pages = SiteAuditPage::query()
                ->where('crawl_id', $crawlId)
                ->where($hashColumn, $hash)
                ->get(['url', 'url_hash', 'title', 'description']);

            foreach ($pages as $page) {
                SiteAuditFinding::query()->create([
                    'crawl_id' => $crawlId,
                    'code' => $code,
                    'severity' => $severity,
                    'url' => $page->url,
                    'url_hash' => $page->url_hash,
                    'meta_json' => [
                        'group_size' => (int) $count,
                        'hash' => $hash,
                        'title' => $page->title,
                        'description' => $page->description,
                    ],
                ]);
            }
        }
    }
}
