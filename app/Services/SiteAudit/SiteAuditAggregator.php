<?php

namespace App\Services\SiteAudit;

use App\SiteAuditCrawl;
use App\SiteAuditCrawlStat;
use App\SiteAuditFinding;
use App\SiteAuditPage;
use App\SiteAuditProject;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SiteAuditAggregator
{
    /** Порядок этапов агрегации (тяжёлые режутся тиками внутри этапа). */
    private const STAGES = [
        'cleanup',
        'duplicates_title',
        'duplicates_description',
        'duplicates_content',
        'similar_pages',
        'from_pages',
        'redirect_loops',
        'duplicate_url_variants',
        'orphans',
        'no_outbound',
        'url_param_risks',
        'broken_links',
        'broken_external_links',
        'image_assets',
        'lost_files',
        'incremental_head',
        'error_spikes',
        'click_depth',
        'sitemap_coverage',
        'landing_coverage',
        'landing_no_inbound',
        'cannibalization',
        'ad_cannibalization',
        'landing_query_match',
        'availability',
        'serp_index',
        'serp_snippets',
        'serp_cannibalization',
        'psi',
        'finalize',
    ];

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
        'page_has_broken_external_links',
        'broken_external_link',
        'broken_image',
        'heavy_image',
        'lost_file',
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
        'landing_no_inbound_internal',
        'keyword_cannibalization',
        'ad_cannibalization',
        'landing_query_mismatch',
        'site_availability',
        'index_count_mismatch',
        'serp_snippets',
        'serp_title_mismatch',
        'serp_snippet_source',
        'serp_snippet_cannibalization',
        'psi_mobile',
        'psi_desktop',
        'no_outbound_internal',
        'risky_query_params',
        'pagination_param',
        'redirect_loop',
    ];

    /**
     * Полная агрегация синхронно (CLI reaggregate). Крупные краулы тоже идут этапами, но без паузы очереди.
     */
    public function aggregate(SiteAuditCrawl $crawl, bool $notify = true): void
    {
        $this->resetAggregateState($crawl, $notify);
        if ($crawl->status !== SiteAuditCrawl::STATUS_AGGREGATING) {
            $crawl->status = SiteAuditCrawl::STATUS_AGGREGATING;
            $crawl->error = null;
            $crawl->save();
        }
        while ($this->processTick($crawl, $notify, 86400.0)) {
            // следующий этап / кусок
        }
    }

    /**
     * Один тик агрегации. @return bool true — нужно продолжить (ещё этапы/куски)
     */
    public function processTick(SiteAuditCrawl $crawl, bool $notify = true, ?float $budgetSeconds = null): bool
    {
        $budget = $budgetSeconds ?? (float) config('site_audit.aggregate_tick_seconds', 150);
        $budget = max(30.0, $budget);
        $started = microtime(true);
        $deadline = $started + $budget;

        $state = $this->getAggregateState($crawl);
        if (($state['stage'] ?? '') === '' || ($state['stage'] ?? '') === 'done') {
            // уже финализировали — или холодный старт после failed
            if (($state['stage'] ?? '') === 'done' && $crawl->status === SiteAuditCrawl::STATUS_DONE) {
                return false;
            }
            $state = $this->freshAggregateState($notify);
            $this->putAggregateState($crawl, $state);
        } else {
            $state['notify'] = $notify;
        }

        while (($state['stage'] ?? 'done') !== 'done') {
            $moreInStage = $this->runStage($crawl, $state, $deadline);
            $this->putAggregateState($crawl, $state);
            $this->heartbeatAggregate($crawl, $state);

            if ($moreInStage) {
                return true;
            }

            $state['stage'] = $this->nextStage((string) $state['stage']);
            $state['meta'] = [];
            $state['tick'] = (int) ($state['tick'] ?? 0) + 1;
            $this->putAggregateState($crawl, $state);
            $this->heartbeatAggregate($crawl, $state);

            if (($state['stage'] ?? 'done') === 'done') {
                return false;
            }

            if (microtime(true) >= $deadline) {
                return true;
            }
        }

        return false;
    }

    private function freshAggregateState(bool $notify): array
    {
        return [
            'stage' => self::STAGES[0],
            'meta' => [],
            'tick' => 0,
            'notify' => $notify,
            'depth' => [],
            'started_at' => now()->toDateTimeString(),
        ];
    }

    public function resetAggregateState(SiteAuditCrawl $crawl, bool $notify = true): void
    {
        Cache::forget($this->brokenLinksCacheKey((int) $crawl->id));
        Cache::forget($this->brokenLinksCacheKey((int) $crawl->id) . '_head');
        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        $progress['aggregate'] = $this->freshAggregateState($notify);
        $crawl->progress_json = $progress;
        $crawl->save();
    }

    private function getAggregateState(SiteAuditCrawl $crawl): array
    {
        $crawl->refresh();
        $state = $crawl->progress_json['aggregate'] ?? null;

        return is_array($state) ? $state : [];
    }

    private function putAggregateState(SiteAuditCrawl $crawl, array $state): void
    {
        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        $progress['aggregate'] = $state;
        $crawl->progress_json = $progress;
        // updated_at трогаем в heartbeat
        $crawl->save();
    }

    private function heartbeatAggregate(SiteAuditCrawl $crawl, array $state): void
    {
        $crawl->updated_at = now();
        if ($crawl->status !== SiteAuditCrawl::STATUS_AGGREGATING
            && ! $crawl->isFinished()
        ) {
            $crawl->status = SiteAuditCrawl::STATUS_AGGREGATING;
        }
        $crawl->save();
        Log::info('SiteAudit aggregate tick', [
            'crawl_id' => $crawl->id,
            'stage' => $state['stage'] ?? null,
            'tick' => $state['tick'] ?? null,
        ]);
    }

    private function nextStage(string $stage): string
    {
        $i = array_search($stage, self::STAGES, true);
        if ($i === false) {
            return 'done';
        }
        $next = $i + 1;
        if ($next >= count(self::STAGES)) {
            return 'done';
        }

        return self::STAGES[$next];
    }

    /**
     * @return bool true если этап ещё не закончен (нужен ещё тик на том же stage)
     */
    private function runStage(SiteAuditCrawl $crawl, array &$state, float $deadline): bool
    {
        $stage = (string) ($state['stage'] ?? '');
        $meta = is_array($state['meta'] ?? null) ? $state['meta'] : [];

        switch ($stage) {
            case 'cleanup':
                SiteAuditFinding::query()
                    ->where('crawl_id', $crawl->id)
                    ->whereIn('code', self::AGGREGATE_CODES)
                    ->delete();
                Cache::forget($this->brokenLinksCacheKey((int) $crawl->id));
                Cache::forget($this->brokenLinksCacheKey((int) $crawl->id) . '_head');
                break;
            case 'duplicates_title':
                $this->emitDuplicates($crawl->id, 'title_hash', 'duplicate_title');
                break;
            case 'duplicates_description':
                $this->emitDuplicates($crawl->id, 'description_hash', 'duplicate_description');
                break;
            case 'duplicates_content':
                $this->emitDuplicates($crawl->id, 'content_hash', 'duplicate_content');
                break;
            case 'similar_pages':
                $this->emitSimilarPages($crawl->id);
                break;
            case 'from_pages':
                $more = $this->emitFromPages($crawl->id, $meta, $deadline);
                $state['meta'] = $meta;

                return $more;
            case 'redirect_loops':
                $this->emitRedirectLoops($crawl->id);
                break;
            case 'duplicate_url_variants':
                $this->emitDuplicateUrlVariants($crawl->id);
                break;
            case 'orphans':
                $this->emitOrphans($crawl->id);
                break;
            case 'no_outbound':
                $this->emitNoOutboundInternal($crawl->id);
                break;
            case 'url_param_risks':
                $this->emitUrlParamRisks($crawl->id);
                break;
            case 'broken_links':
                $more = $this->emitBrokenLinks($crawl, $meta, $deadline);
                $state['meta'] = $meta;

                return $more;
            case 'broken_external_links':
                $more = $this->emitBrokenExternalLinks($crawl, $meta, $deadline);
                $state['meta'] = $meta;

                return $more;
            case 'image_assets':
                $more = $this->emitImageAssets($crawl, $meta, $deadline);
                $state['meta'] = $meta;

                return $more;
            case 'lost_files':
                $this->emitLostFiles($crawl);
                break;
            case 'incremental_head':
                $this->copyIncrementalHeadFindings($crawl);
                break;
            case 'error_spikes':
                $this->emitErrorSpikes($crawl);
                break;
            case 'click_depth':
                $state['depth'] = $this->emitClickDepth($crawl->id);
                break;
            case 'sitemap_coverage':
                $this->emitSitemapCoverage($crawl);
                break;
            case 'landing_coverage':
                $this->emitLandingCoverage($crawl);
                break;
            case 'landing_no_inbound':
                $this->emitLandingNoInbound($crawl);
                break;
            case 'cannibalization':
                $more = (new SiteAuditCannibalizationProbe())->run($crawl, $deadline, $meta);
                $state['meta'] = $meta;

                return $more;
            case 'ad_cannibalization':
                $more = (new SiteAuditAdCannibalizationProbe())->run($crawl, $deadline, $meta);
                $state['meta'] = $meta;

                return $more;
            case 'landing_query_match':
                (new SiteAuditLandingQueryMatchProbe())->run($crawl);
                break;
            case 'availability':
                (new SiteAuditAvailabilityProbe())->run($crawl);
                break;
            case 'serp_index':
                (new SiteAuditSerpIndexProbe())->run($crawl);
                break;
            case 'serp_snippets':
                (new SiteAuditSerpSnippetsProbe())->run($crawl);
                break;
            case 'serp_cannibalization':
                (new SiteAuditSerpCannibalizationProbe())->run($crawl);
                break;
            case 'psi':
                // Куски по дедлайну тика — иначе 20×2 PSI валят job по timeout.
                return (new SiteAuditPsiProbe())->run($crawl, false, $deadline);
            case 'finalize':
                $this->finalizeAggregate($crawl, $state);
                $state['stage'] = 'done';
                break;
            default:
                Log::warning('SiteAudit unknown aggregate stage', [
                    'crawl_id' => $crawl->id,
                    'stage' => $stage,
                ]);
                break;
        }

        $state['meta'] = [];

        return false;
    }

    private function finalizeAggregate(SiteAuditCrawl $crawl, array $state): void
    {
        $depthMeta = is_array($state['depth'] ?? null) ? $state['depth'] : [];
        $notify = (bool) ($state['notify'] ?? true);

        // www/http зеркала пишутся на discover; если finding потерялся, а host_variants в progress
        // уже есть — восстанавливаем до подсчёта counts_json.
        try {
            $project = SiteAuditProject::query()->find($crawl->project_id);
            if ($project && $project->domain) {
                (new SiteAuditHostVariantProbe())->run($crawl, (string) $project->domain);
                $crawl->refresh();
            }
        } catch (\Throwable $e) {
            // optional
        }

        $buckets = [
            'critical' => 0,
            'other' => 0,
            'important' => 0,
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

        // TITLE ≠ выдаче: в дереве только расхождения, строки «всё ок» не считаем проблемой.
        $byCode['serp_title_mismatch'] = SiteAuditSerpSnippetsProbe::countMismatchFindings((int) $crawl->id);

        $byCode['pages_with_canonical'] = (int) SiteAuditPage::query()
            ->where('crawl_id', $crawl->id)
            ->whereNotNull('canonical')
            ->where('canonical', '!=', '')
            ->count();

        $byCode['click_depth_max'] = (int) ($depthMeta['click_depth_max'] ?? 0);

        $imagesTotal = (int) SiteAuditPage::query()
            ->where('crawl_id', $crawl->id)
            ->sum('img_count');
        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        $progress['images_total'] = $imagesTotal;
        $crawl->progress_json = $progress;

        foreach ($buckets as $bucket => $value) {
            SiteAuditCrawlStat::query()->updateOrCreate(
                ['crawl_id' => $crawl->id, 'bucket' => $bucket],
                ['value' => $value]
            );
        }

        $crawl->buckets_json = $buckets;
        $crawl->counts_json = $byCode;
        $crawl->status = SiteAuditCrawl::STATUS_DONE;
        $crawl->error = null;
        $crawl->finished_at = now();
        $crawl->save();

        Cache::forget($this->brokenLinksCacheKey((int) $crawl->id));

        try {
            (new SiteAuditExternalPlagiarismRunner())->queueAutoSample($crawl);
        } catch (\Throwable $e) {
            Log::warning('SiteAudit plagiarism auto queue failed: ' . $e->getMessage(), [
                'crawl_id' => $crawl->id,
            ]);
        }

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

        SiteAuditGlobalCap::promoteWaiting();
    }

    private function brokenLinksCacheKey(int $crawlId): string
    {
        return 'site_audit_agg_broken_' . $crawlId;
    }

    /**
     * @param array<string,mixed> $meta
     * @return bool true = ещё есть страницы
     */
    private function emitFromPages(int $crawlId, array &$meta = [], ?float $deadline = null): bool
    {
        $thin = (int) config('site_audit.thin_words', 150);
        $titleMin = (int) config('site_audit.title_min', 30);
        $titleMax = (int) config('site_audit.title_max', 70);
        $descMin = (int) config('site_audit.description_min', 70);
        $descMax = (int) config('site_audit.description_max', 160);
        $chunkSize = max(50, (int) config('site_audit.aggregate_from_pages_chunk', 200));
        $afterId = (int) ($meta['after_id'] ?? 0);
        $chromeBigrams = $this->chromeNgramMap($crawlId, 'top_bigram');
        $chromeTrigrams = $this->chromeNgramMap($crawlId, 'top_trigram');
        $ngramMinWords = max(1, (int) config('site_audit.ngram_spam_min_words', 40));

        while (true) {
            $pages = SiteAuditPage::query()
                ->where('crawl_id', $crawlId)
                ->where('id', '>', $afterId)
                ->orderBy('id')
                ->limit($chunkSize)
                ->get();

            if ($pages->isEmpty()) {
                return false;
            }

            foreach ($pages as $page) {
                $afterId = (int) $page->id;
                    // SEO/контент-находки только для успешно открытых HTML-страниц.
                    // На 4xx/5xx/unreachable HTML не парсился → img/title = 0 по умолчанию,
                    // иначе «Нет уникальных изображений» и т.п. заливают весь отчёт битыми URL.
                    // webp/csv/pdf тоже лежат в pages, но это не документы перелинковки/META.
                    $status = $page->status_code;
                    if ($status === null || (int) $status < 200 || (int) $status >= 400) {
                        continue;
                    }
                    if (! SiteAuditUrlNormalizer::isHtmlDocument($page->content_type ?? null, (string) $page->url)) {
                        continue;
                    }
                    // Контент/мета после follow — у финала; редирект уже в «Редиректы».
                    if ($this->pageHadRedirect($page)) {
                        continue;
                    }

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
                                'description' => $page->description,
                            ]);
                        } elseif ($len > $descMax) {
                            $findings[] = $this->row($crawlId, 'description_too_long', $page, [
                                'length' => $len,
                                'max' => $descMax,
                                'description' => $page->description,
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
                                'description' => $page->description,
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
                        $missingSamples = [];
                        foreach (SiteAuditImageItem::normalizeList($page->img_srcs_json) as $imgItem) {
                            if (($imgItem['has_alt'] ?? null) !== false) {
                                continue;
                            }
                            $missingSamples[] = [
                                'src' => $imgItem['src'],
                                'url' => $imgItem['src'],
                                'width' => $imgItem['width'] ?? null,
                                'height' => $imgItem['height'] ?? null,
                                'loading' => $imgItem['loading'] ?? null,
                            ];
                            if (count($missingSamples) >= 15) {
                                break;
                            }
                        }
                        $findings[] = $this->row($crawlId, 'images_without_alt', $page, [
                            'img_without_alt' => (int) $page->img_without_alt,
                            'img_count' => (int) $page->img_count,
                            'count' => (int) $page->img_without_alt,
                            'samples' => $missingSamples,
                        ]);
                    }

                    if ((int) $page->unique_img_src_count === 0 && ! $page->noindex) {
                        $imgCount = (int) $page->img_count;
                        $findings[] = $this->row($crawlId, 'no_unique_images', $page, [
                            'img_count' => $imgCount,
                            'unique_img_src_count' => 0,
                            'reason' => $imgCount > 0 ? 'no_src' : 'no_img',
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

                    $h1Text = is_string($page->h1) ? trim($page->h1) : '';
                    // Мусорный «H1» (JSON-LD/CSS из кривого парсинга) — не считаем переспамом
                    $h1LooksBroken = $h1Text !== '' && (
                        mb_strlen($h1Text) > 300
                        || preg_match('/\{"@type"|position\s*:\s*relative|!important/i', $h1Text)
                    );
                    if (! $h1LooksBroken) {
                        $h1Spam = SiteAuditTextMetrics::fieldSpam($page->h1);
                        if ($h1Spam['spam']) {
                            $findings[] = $this->row($crawlId, 'h1_spam', $page, [
                                'word' => $h1Spam['word'],
                                'count' => $h1Spam['count'],
                                'h1' => $page->h1,
                            ]);
                        }
                    }

                    $nauseaClassicMax = (float) config('site_audit.nausea_classic_max', 8.0);
                    $nauseaAcademicMax = (float) config('site_audit.nausea_academic_max', 25.0);
                    $nauseaMinWords = max(1, (int) config('site_audit.nausea_min_words', 50));
                    $classic = $page->nausea_classic !== null ? (float) $page->nausea_classic : null;
                    $academic = $page->nausea_academic !== null ? (float) $page->nausea_academic : null;
                    $nauseaWords = (int) ($page->word_count ?? 0);
                    if ($nauseaWords >= $nauseaMinWords
                        && (
                            ($classic !== null && $classic >= $nauseaClassicMax)
                            || ($academic !== null && $academic >= $nauseaAcademicMax)
                        )
                    ) {
                        $findings[] = $this->row($crawlId, 'text_nausea', $page, [
                            'nausea_classic' => $classic,
                            'nausea_academic' => $academic,
                            'top_word' => $page->top_word,
                            'top_word_count' => (int) $page->top_word_count,
                            'word_count' => $nauseaWords,
                            'threshold_classic' => $nauseaClassicMax,
                            'threshold_academic' => $nauseaAcademicMax,
                            'min_words' => $nauseaMinWords,
                        ]);
                    }

                    $bigramMin = (int) config('site_audit.bigram_spam_min', 5);
                    $bigramDensityMin = (float) config('site_audit.bigram_spam_density_min', 1.5);
                    $bgCount = (int) $page->top_bigram_count;
                    $words = (int) $page->word_count;
                    $bgDensity = ($words > 0 && $bgCount > 0)
                        ? round(($bgCount / $words) * 100, 2)
                        : 0.0;
                    $bgKey = mb_strtolower(trim((string) ($page->top_bigram ?? '')));
                    if ($words >= $ngramMinWords
                        && $page->top_bigram
                        && $bgKey !== ''
                        && ! isset($chromeBigrams[$bgKey])
                        && $bgCount >= $bigramMin
                        && $bgDensity >= $bigramDensityMin
                    ) {
                        $findings[] = $this->row($crawlId, 'text_bigram_spam', $page, [
                            'bigram' => $page->top_bigram,
                            'count' => $bgCount,
                            'density' => $bgDensity,
                            'word_count' => $words,
                            'threshold_count' => $bigramMin,
                            'threshold_density' => $bigramDensityMin,
                            'min_words' => $ngramMinWords,
                        ]);
                    }

                    $trigramMin = (int) config('site_audit.trigram_spam_min', 5);
                    $trigramDensityMin = (float) config('site_audit.trigram_spam_density_min', 1.0);
                    $tgCount = (int) ($page->top_trigram_count ?? 0);
                    $tgDensity = ($words > 0 && $tgCount > 0)
                        ? round(($tgCount / $words) * 100, 2)
                        : 0.0;
                    $tgKey = mb_strtolower(trim((string) ($page->top_trigram ?? '')));
                    if ($words >= $ngramMinWords
                        && ! empty($page->top_trigram)
                        && $tgKey !== ''
                        && ! isset($chromeTrigrams[$tgKey])
                        && $tgCount >= $trigramMin
                        && $tgDensity >= $trigramDensityMin
                    ) {
                        $findings[] = $this->row($crawlId, 'text_trigram_spam', $page, [
                            'trigram' => $page->top_trigram,
                            'count' => $tgCount,
                            'density' => $tgDensity,
                            'word_count' => $words,
                            'threshold_count' => $trigramMin,
                            'threshold_density' => $trigramDensityMin,
                            'min_words' => $ngramMinWords,
                        ]);
                    }

                    // Любой текст/ссылки внутри Яндекс-блоков noindex (соцкнопки VK/TG тоже).
                    if ((int) $page->noindex_text_len > 0
                        || (is_array($page->noindex_links_json ?? null) && $page->noindex_links_json !== [])
                    ) {
                        $sample = trim((string) ($page->noindex_sample ?? ''));
                        $links = is_array($page->noindex_links_json ?? null) ? $page->noindex_links_json : [];
                        $hash = trim((string) ($page->noindex_hash ?? ''));
                        if ($hash === '') {
                            $hash = md5(mb_strtolower($sample) . '|' . (int) $page->noindex_text_len);
                        }
                        $findings[] = $this->row($crawlId, 'text_in_noindex', $page, [
                            'noindex_text_len' => (int) $page->noindex_text_len,
                            'sample' => $sample !== '' ? $sample : null,
                            'links' => $links !== [] ? array_values(array_slice($links, 0, 8)) : null,
                            'hash' => $hash,
                        ]);
                    }

                    $softMeta = $this->soft404Meta($page);
                    if ($softMeta !== null) {
                        $findings[] = $this->row($crawlId, 'soft_404', $page, $softMeta);
                    }

                    foreach ($findings as $f) {
                        SiteAuditFinding::query()->create($f);
                    }
            }

            $meta['after_id'] = $afterId;
            if ($deadline !== null && microtime(true) >= $deadline) {
                return true;
            }
        }

        return false;
    }

    /**
     * N-граммы, которые «top» на большой доле страниц = сквозной бренд/chrome, не переспам контента.
     *
     * @return array<string,true> ключ — mb_strtolower(фраза)
     */
    private function chromeNgramMap(int $crawlId, string $column): array
    {
        if (! in_array($column, ['top_bigram', 'top_trigram'], true)) {
            return [];
        }
        $share = (float) config('site_audit.ngram_chrome_share', 0.20);
        $minPages = max(2, (int) config('site_audit.ngram_chrome_min_pages', 8));

        $rows = SiteAuditPage::query()
            ->where('crawl_id', $crawlId)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->get([$column]);

        $n = $rows->count();
        if ($n < $minPages) {
            return [];
        }
        $need = max($minPages, (int) ceil($share * $n));
        $df = [];
        foreach ($rows as $row) {
            $key = mb_strtolower(trim((string) ($row->{$column} ?? '')));
            if ($key === '') {
                continue;
            }
            $df[$key] = ($df[$key] ?? 0) + 1;
        }
        $out = [];
        foreach ($df as $key => $c) {
            if ($c >= $need) {
                $out[$key] = true;
            }
        }

        return $out;
    }

    /**
     * Soft 404: 200 + паттерн «не найдено» в TITLE/H1.
     * Крайне мало текста без таких паттернов — это thin_content, не soft 404.
     *
     * @return array<string, mixed>|null
     */
    private function soft404Meta(SiteAuditPage $page): ?array
    {
        if ((int) $page->status_code !== 200) {
            return null;
        }

        $title = trim((string) $page->title);
        $h1 = trim((string) $page->h1);
        $titleL = mb_strtolower($title);
        $h1L = mb_strtolower($h1);
        $patterns = config('site_audit.soft_404_title_patterns', [
            '404',
            'not found',
            'page not found',
            'страница не найдена',
            'не найдена',
            'ошибка 404',
        ]);

        $base = [
            'status' => 200,
            'word_count' => $page->word_count !== null ? (int) $page->word_count : null,
            'title' => $title !== '' ? $title : null,
            'h1' => $h1 !== '' ? $h1 : null,
        ];

        foreach ($patterns as $p) {
            $p = trim((string) $p);
            if ($p === '') {
                continue;
            }
            $pL = mb_strtolower($p);
            if ($titleL !== '' && mb_strpos($titleL, $pL) !== false) {
                return array_merge($base, [
                    'reason' => 'title_pattern',
                    'pattern' => $p,
                    'matched_in' => 'title',
                ]);
            }
            if ($h1L !== '' && mb_strpos($h1L, $pL) !== false) {
                return array_merge($base, [
                    'reason' => 'h1_pattern',
                    'pattern' => $p,
                    'matched_in' => 'h1',
                ]);
            }
        }

        return null;
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
            ->get(['url', 'url_hash', 'status_code', 'content_type']);

        $crawledSet = [];
        $notIn = 0;
        foreach ($crawled as $page) {
            $crawledSet[$page->url] = true;
            $code = (int) ($page->status_code ?? 0);
            if (! isset($sitemapSet[$page->url])) {
                // только успешно отданные HTML-подобные / без content_type
                if ($code < 200 || $code >= 400) {
                    continue;
                }
                if (! SiteAuditUrlNormalizer::isHtmlDocument($page->content_type ?? null, (string) $page->url)) {
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
        }

        $robotsSkipped = (int) ($crawl->progress_json['robots_skipped'] ?? 0);
        $robotsGroups = is_array($crawl->progress_json['robots']['groups'] ?? null)
            ? $crawl->progress_json['robots']['groups']
            : null;
        $robotsTxt = is_array($robotsGroups) && $robotsGroups !== []
            ? new SiteAuditRobotsTxt()
            : null;
        $pagesLimit = (int) $crawl->pages_limit;
        $pagesFetched = (int) $crawl->pages_fetched;
        $pagesStored = count($crawledSet);
        $emitted = 0;
        foreach ($sitemapUrls as $u) {
            // Уже есть в проверке (любой статус) — не считаем «не в проверке»
            if (isset($crawledSet[$u])) {
                continue;
            }
            // Закрытые robots.txt — отдельный отчёт robots_blocked, не дублируем сюда.
            if ($robotsTxt && ! $robotsTxt->isPathAllowed($robotsGroups, $u)) {
                continue;
            }
            if ($emitted >= $sampleNotCrawled) {
                break;
            }
            $hash = SiteAuditUrlNormalizer::hash($u);
            $cfg = config('site_audit.findings.sitemap_not_crawled', []);
            $reason = 'in_sitemap_not_in_crawl';
            if ($pagesStored < max(1, (int) ($pagesFetched * 0.5)) && $pagesFetched > 10) {
                $reason = 'crawl_save_gap';
            } elseif ($robotsSkipped > 0 && $pagesLimit > $pagesFetched) {
                $reason = 'likely_robots_or_not_queued';
            } elseif ($pagesLimit > 0 && $pagesFetched >= $pagesLimit) {
                $reason = 'pages_limit';
            }
            SiteAuditFinding::query()->create([
                'crawl_id' => $crawl->id,
                'code' => 'sitemap_not_crawled',
                'severity' => $cfg['severity'] ?? 'info',
                'url' => $u,
                'url_hash' => $hash,
                'meta_json' => [
                    'reason' => $reason,
                    'pages_limit' => $pagesLimit,
                    'pages_fetched' => $pagesFetched,
                    'pages_stored' => $pagesStored,
                    'robots_skipped' => $robotsSkipped,
                    'sitemap_url_count' => count($sitemapUrls),
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
     * Посадочные без входящих внутренних ссылок из краула (кроме главной).
     */
    private function emitLandingNoInbound(SiteAuditCrawl $crawl): void
    {
        $resolved = (new SiteAuditLandingResolver())->forCrawl($crawl);
        $landings = $resolved['urls'];
        if ($landings === []) {
            return;
        }

        $pages = SiteAuditPage::query()
            ->where('crawl_id', $crawl->id)
            ->get(['url', 'url_hash', 'out_links_json', 'status_code']);
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
                if (isset($byHash[$out])) {
                    if ($out !== $page->url_hash) {
                        $inbound[$out] = ($inbound[$out] ?? 0) + 1;
                    }
                    continue;
                }
                if (isset($byUrl[$out])) {
                    $h = $byUrl[$out]->url_hash;
                    if ($h !== $page->url_hash) {
                        $inbound[$h] = ($inbound[$h] ?? 0) + 1;
                    }
                    continue;
                }
                $h = SiteAuditUrlNormalizer::hash($out);
                if (isset($byHash[$h]) && $h !== $page->url_hash) {
                    $inbound[$h] = ($inbound[$h] ?? 0) + 1;
                }
            }
        }

        $max = (int) config('site_audit.landing_findings_max', 300);
        $cfg = config('site_audit.findings.landing_no_inbound_internal', []);
        $severity = $cfg['severity'] ?? 'warning';
        $emitted = 0;

        foreach ($landings as $url) {
            if ($emitted >= $max) {
                break;
            }
            $hash = SiteAuditUrlNormalizer::hash($url);
            if (! isset($byHash[$hash])) {
                continue;
            }
            $page = $byHash[$hash];
            $path = parse_url($url, PHP_URL_PATH);
            if ($path === '/' || $path === '' || $path === null) {
                continue;
            }
            $code = (int) $page->status_code;
            if ($code && ($code < 200 || $code >= 400)) {
                continue;
            }
            if (! empty($inbound[$hash])) {
                continue;
            }

            SiteAuditFinding::query()->create([
                'crawl_id' => $crawl->id,
                'code' => 'landing_no_inbound_internal',
                'severity' => $severity,
                'url' => $url,
                'url_hash' => $hash,
                'meta_json' => [
                    'source' => 'monitoring',
                    'inbound' => 0,
                    'monitoring_project_ids' => $resolved['project_ids'],
                ],
            ]);
            $emitted++;
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
            ->get(['id', 'url', 'url_hash', 'out_links_json', 'status_code', 'content_type']);

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
            // только успешные HTML-страницы (не webp/csv/pdf/…)
            $code = (int) $page->status_code;
            if ($code && ($code < 200 || $code >= 400)) {
                continue;
            }
            if (! SiteAuditUrlNormalizer::isHtmlDocument($page->content_type ?? null, (string) $page->url)) {
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
     * Успешные HTML-страницы без исходящих внутренних ссылок (тупики).
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
                    if (! SiteAuditUrlNormalizer::isHtmlDocument($page->content_type ?? null, (string) $page->url)) {
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
                    $code = (int) ($page->status_code ?? 0);
                    // 4xx/5xx /missing/page-2/ и т.п. — не «пагинация в индексе».
                    if ($code > 0 && ($code < 200 || $code >= 400)) {
                        continue;
                    }

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
            ->get(['id', 'url', 'url_hash', 'status_code', 'final_url', 'redirect_chain']);

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

            // 301/302 на соседний вариант — нормальная склейка, не дубль контента.
            $live = [];
            foreach ($group as $page) {
                if (! $this->pageRedirectedAway($page)) {
                    $live[] = $page;
                }
            }
            if (count($live) < 2) {
                continue;
            }

            $variants = array_map(static function ($p) {
                return $p->url;
            }, $live);
            foreach ($live as $page) {
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

    /**
     * URL ушёл редиректом на другой адрес (склейка slash/www/http) — не «живой» дубль.
     */
    private function pageRedirectedAway($page): bool
    {
        $url = trim((string) ($page->url ?? ''));
        if ($url === '') {
            return false;
        }

        $chain = $page->redirect_chain ?? null;
        if (is_string($chain)) {
            $decoded = json_decode($chain, true);
            $chain = is_array($decoded) ? $decoded : null;
        }
        if (is_array($chain) && $chain !== []) {
            return true;
        }

        $final = trim((string) ($page->final_url ?? ''));
        if ($final === '' || $final === $url) {
            return false;
        }

        // final после follow redirect отличается от запрошенного URL.
        return true;
    }

    /**
     * Битые ссылки чанками: индекс URL лёгкий, out_links — порциями, uniqueBroken в Cache.
     *
     * @param array<string,mixed> $meta
     * @return bool true = ещё есть страницы
     */
    private function emitBrokenLinks(SiteAuditCrawl $crawl, array &$meta = [], ?float $deadline = null): bool
    {
        $settings = is_array($crawl->progress_json['settings'] ?? null)
            ? $crawl->progress_json['settings']
            : [];
        if (array_key_exists('check_broken_links', $settings) && ! $settings['check_broken_links']) {
            return false;
        }

        $chunkSize = max(50, (int) config('site_audit.aggregate_broken_links_chunk', 250));
        $afterId = (int) ($meta['after_id'] ?? 0);
        $cacheKey = $this->brokenLinksCacheKey((int) $crawl->id);

        // Лёгкий индекс всех URL краула (без out_links) — для сверки целей.
        $indexRows = SiteAuditPage::query()
            ->where('crawl_id', $crawl->id)
            ->get(['url', 'url_hash', 'status_code']);
        if ($indexRows->isEmpty()) {
            return false;
        }
        $byUrl = [];
        $byHash = [];
        foreach ($indexRows as $row) {
            $byUrl[$row->url] = $row;
            $byHash[$row->url_hash] = $row;
        }
        unset($indexRows);

        $maxHead = (int) config('site_audit.broken_link_head_max', 40);
        $checker = new SiteAuditLinkChecker();
        $headCacheKey = $cacheKey . '_head';
        $headCache = Cache::get($headCacheKey);
        if (! is_array($headCache)) {
            $headCache = [];
        }
        $headBudget = array_key_exists('head_budget', $meta)
            ? (int) $meta['head_budget']
            : $maxHead;
        $skipUnchangedHead = config('site_audit.incremental_by_content_hash', true);

        $pageSev = config('site_audit.findings.page_has_broken_links.severity', 'warning');
        $linkSev = config('site_audit.findings.broken_internal_link.severity', 'critical');

        // Циклы редиректов — не «битая ссылка» (отдельный отчёт redirect_loop).
        $redirectLoopHashes = [];
        $redirectLoopUrls = [];
        foreach (
            SiteAuditFinding::query()
                ->where('crawl_id', $crawl->id)
                ->where('code', 'redirect_loop')
                ->get(['url', 'url_hash']) as $loopRow
        ) {
            $redirectLoopHashes[(string) $loopRow->url_hash] = true;
            $redirectLoopUrls[(string) $loopRow->url] = true;
        }

        $uniqueBroken = Cache::get($cacheKey);
        if (! is_array($uniqueBroken)) {
            $uniqueBroken = [];
        }

        $isBrokenCrawlTarget = static function ($target) use ($redirectLoopHashes, $redirectLoopUrls): ?array {
            if (! $target) {
                return null;
            }
            $tu = (string) ($target->url ?? '');
            $th = (string) ($target->url_hash ?? '');
            if (($th !== '' && isset($redirectLoopHashes[$th])) || ($tu !== '' && isset($redirectLoopUrls[$tu]))) {
                return null;
            }
            $code = $target->status_code;
            // null без loop — цель не ответила / оборвался fetch; ≥400 — битая.
            if ($code === null || (int) $code >= 400) {
                return [
                    'url' => $tu !== '' ? $tu : $th,
                    'status' => $code !== null ? (int) $code : null,
                    'source' => 'crawl',
                ];
            }

            return null;
        };

        while (true) {
            $pages = SiteAuditPage::query()
                ->where('crawl_id', $crawl->id)
                ->where('id', '>', $afterId)
                ->orderBy('id')
                ->limit($chunkSize)
                ->get(['id', 'url', 'url_hash', 'out_links_json', 'status_code', 'content_unchanged']);

            if ($pages->isEmpty()) {
                $this->flushBrokenLinkFindings($crawl->id, $uniqueBroken, $linkSev);
                Cache::forget($cacheKey);
                Cache::forget($headCacheKey);
                $meta = [];

                return false;
            }

            foreach ($pages as $page) {
                $afterId = (int) $page->id;
                $outs = is_array($page->out_links_json) ? $page->out_links_json : [];
                if (! $outs) {
                    continue;
                }
                $pageUnchanged = $skipUnchangedHead && ! empty($page->content_unchanged);

                $brokenSamples = [];
                foreach ($outs as $out) {
                    $out = (string) $out;
                    if ($out === '' || (strlen($out) === 64 && ctype_xdigit($out))) {
                        if (isset($byHash[$out])) {
                            $sample = $isBrokenCrawlTarget($byHash[$out]);
                            if ($sample !== null) {
                                $brokenSamples[] = $sample;
                            }
                        }
                        continue;
                    }

                    if (isset($byUrl[$out])) {
                        $sample = $isBrokenCrawlTarget($byUrl[$out]);
                        if ($sample !== null) {
                            $sample['url'] = $out;
                            $brokenSamples[] = $sample;
                        }
                        continue;
                    }

                    $h = SiteAuditUrlNormalizer::hash($out);
                    if (isset($byHash[$h])) {
                        $sample = $isBrokenCrawlTarget($byHash[$h]);
                        if ($sample !== null) {
                            $sample['url'] = $out;
                            $brokenSamples[] = $sample;
                        }
                        continue;
                    }

                    if ($pageUnchanged || $headBudget <= 0) {
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

                foreach ($brokenSamples as $sample) {
                    $bu = (string) ($sample['url'] ?? '');
                    if ($bu === '') {
                        continue;
                    }
                    if (! isset($uniqueBroken[$bu])) {
                        $uniqueBroken[$bu] = [
                            'status' => $sample['status'] ?? null,
                            'source' => $sample['source'] ?? null,
                            'error' => $sample['error'] ?? null,
                            'from' => [],
                        ];
                    }
                    if (count($uniqueBroken[$bu]['from']) < 50
                        && ! in_array($page->url, $uniqueBroken[$bu]['from'], true)) {
                        $uniqueBroken[$bu]['from'][] = $page->url;
                    }
                }
            }

            $meta['after_id'] = $afterId;
            $meta['head_budget'] = $headBudget;
            Cache::put($cacheKey, $uniqueBroken, 7200);
            Cache::put($headCacheKey, $headCache, 7200);

            if ($deadline !== null && microtime(true) >= $deadline) {
                return true;
            }
        }

        return false;
    }

    /**
     * Битые внешние ссылки: HEAD по ext_links_json (или samples из finding external_links).
     *
     * @param array<string,mixed> $meta
     * @return bool true = ещё есть страницы
     */
    private function emitBrokenExternalLinks(SiteAuditCrawl $crawl, array &$meta = [], ?float $deadline = null): bool
    {
        $settings = is_array($crawl->progress_json['settings'] ?? null)
            ? $crawl->progress_json['settings']
            : [];
        if (array_key_exists('check_broken_external_links', $settings) && ! $settings['check_broken_external_links']) {
            return false;
        }
        // По умолчанию включено; явно false в settings — выкл.
        if (array_key_exists('check_broken_links', $settings) && ! $settings['check_broken_links']) {
            return false;
        }

        $chunkSize = max(40, (int) config('site_audit.aggregate_broken_external_chunk', 150));
        $afterId = (int) ($meta['after_id'] ?? 0);
        $cacheKey = 'site_audit_agg_broken_ext_' . (int) $crawl->id;
        $headCacheKey = $cacheKey . '_head';
        $maxHead = (int) config('site_audit.broken_external_head_max', 80);
        $checker = new SiteAuditLinkChecker();
        $headCache = Cache::get($headCacheKey);
        if (! is_array($headCache)) {
            $headCache = [];
        }
        $headBudget = array_key_exists('head_budget', $meta)
            ? (int) $meta['head_budget']
            : $maxHead;

        $linkSev = config('site_audit.findings.broken_external_link.severity', 'warning');

        $uniqueBroken = Cache::get($cacheKey);
        if (! is_array($uniqueBroken)) {
            $uniqueBroken = [];
        }

        $hasExtCol = Schema::hasColumn('site_audit_pages', 'ext_links_json');

        while (true) {
            $cols = ['id', 'url', 'url_hash'];
            if ($hasExtCol) {
                $cols[] = 'ext_links_json';
            }
            $pages = SiteAuditPage::query()
                ->where('crawl_id', $crawl->id)
                ->where('id', '>', $afterId)
                ->orderBy('id')
                ->limit($chunkSize)
                ->get($cols);

            if ($pages->isEmpty()) {
                $this->flushBrokenExternalFindings($crawl->id, $uniqueBroken, $linkSev);
                Cache::forget($cacheKey);
                Cache::forget($headCacheKey);
                $meta = [];

                return false;
            }

            // Fallback: samples из finding external_links, если колонки ещё нет / пусто
            $samplesByPage = [];
            if (! $hasExtCol) {
                $hashes = $pages->pluck('url_hash')->all();
                $rows = SiteAuditFinding::query()
                    ->where('crawl_id', $crawl->id)
                    ->where('code', 'external_links')
                    ->whereIn('url_hash', $hashes)
                    ->get(['url_hash', 'meta_json']);
                foreach ($rows as $row) {
                    $m = is_array($row->meta_json) ? $row->meta_json : [];
                    $samplesByPage[(string) $row->url_hash] = isset($m['samples']) && is_array($m['samples'])
                        ? $m['samples']
                        : [];
                }
            }

            foreach ($pages as $page) {
                $afterId = (int) $page->id;
                $outs = [];
                $textByUrl = [];
                if ($hasExtCol && is_array($page->ext_links_json)) {
                    foreach ($page->ext_links_json as $u) {
                        $u = trim((string) $u);
                        if ($u !== '') {
                            $outs[$u] = true;
                        }
                    }
                }
                if ($outs === []) {
                    $raw = $samplesByPage[(string) $page->url_hash] ?? [];
                    foreach ($raw as $sample) {
                        if (is_string($sample)) {
                            $u = trim($sample);
                            if ($u !== '') {
                                $outs[$u] = true;
                            }
                        } elseif (is_array($sample)) {
                            $u = trim((string) ($sample['url'] ?? $sample['href'] ?? ''));
                            if ($u !== '') {
                                $outs[$u] = true;
                                $t = trim((string) ($sample['text'] ?? ''));
                                if ($t !== '') {
                                    $textByUrl[$u] = $t;
                                }
                            }
                        }
                    }
                }
                if ($outs === []) {
                    continue;
                }

                $brokenSamples = [];
                foreach (array_keys($outs) as $out) {
                    if (isset($headCache[$out])) {
                        $check = $headCache[$out];
                    } elseif ($headBudget > 0) {
                        $check = $checker->check($out);
                        $headCache[$out] = $check;
                        $headBudget--;
                    } else {
                        continue;
                    }
                    if (! empty($check['ok'])) {
                        continue;
                    }
                    $brokenSamples[] = [
                        'url' => $out,
                        'text' => $textByUrl[$out] ?? '',
                        'status' => $check['status'] ?? null,
                        'error' => $check['error'] ?? null,
                        'source' => 'head',
                    ];
                }

                if ($brokenSamples === []) {
                    continue;
                }

                // Страничный код page_has_broken_external_links не пишем —
                // в меню это был дубль «Битые внешние ссылки» (referrers там же).
                $brokenSamples = array_slice($brokenSamples, 0, 15);

                foreach ($brokenSamples as $sample) {
                    $bu = (string) ($sample['url'] ?? '');
                    if ($bu === '') {
                        continue;
                    }
                    if (! isset($uniqueBroken[$bu])) {
                        $uniqueBroken[$bu] = [
                            'status' => $sample['status'] ?? null,
                            'source' => $sample['source'] ?? null,
                            'error' => $sample['error'] ?? null,
                            'text' => $sample['text'] ?? '',
                            'from' => [],
                        ];
                    }
                    if (count($uniqueBroken[$bu]['from']) < 50
                        && ! in_array($page->url, $uniqueBroken[$bu]['from'], true)) {
                        $uniqueBroken[$bu]['from'][] = $page->url;
                    }
                }
            }

            $meta['after_id'] = $afterId;
            $meta['head_budget'] = $headBudget;
            Cache::put($cacheKey, $uniqueBroken, 7200);
            Cache::put($headCacheKey, $headCache, 7200);

            if ($deadline !== null && microtime(true) >= $deadline) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,array<string,mixed>> $uniqueBroken
     */
    private function flushBrokenExternalFindings(int $crawlId, array $uniqueBroken, string $linkSev): void
    {
        $maxBrokenFindings = max(1, (int) config('site_audit.broken_external_max_findings', 200));
        $emitted = 0;
        foreach ($uniqueBroken as $bu => $info) {
            if ($emitted >= $maxBrokenFindings) {
                break;
            }
            $fromList = $info['from'] ?? [];
            SiteAuditFinding::query()->create([
                'crawl_id' => $crawlId,
                'code' => 'broken_external_link',
                'severity' => $linkSev,
                'url' => $bu,
                'url_hash' => SiteAuditUrlNormalizer::hash((string) $bu),
                'meta_json' => [
                    'from' => $fromList[0] ?? null,
                    'referrers' => array_slice($fromList, 0, 12),
                    'referrer_count' => count($fromList),
                    'status' => $info['status'] ?? null,
                    'source' => $info['source'] ?? null,
                    'error' => $info['error'] ?? null,
                    'text' => $info['text'] ?? '',
                ],
            ]);
            $emitted++;
        }
    }

    /**
     * @param array<string,array<string,mixed>> $uniqueBroken
     */
    private function flushBrokenLinkFindings(int $crawlId, array $uniqueBroken, string $linkSev): void
    {
        $maxBrokenFindings = max(1, (int) config('site_audit.broken_link_max_findings', 200));
        $emitted = 0;
        foreach ($uniqueBroken as $bu => $info) {
            if ($emitted >= $maxBrokenFindings) {
                break;
            }
            $fromList = $info['from'] ?? [];
            SiteAuditFinding::query()->create([
                'crawl_id' => $crawlId,
                'code' => 'broken_internal_link',
                'severity' => $linkSev,
                'url' => $bu,
                'url_hash' => SiteAuditUrlNormalizer::hash((string) $bu),
                'meta_json' => [
                    'from' => $fromList[0] ?? null,
                    'referrers' => array_slice($fromList, 0, 12),
                    'referrer_count' => count($fromList),
                    'status' => $info['status'] ?? null,
                    'source' => $info['source'] ?? null,
                    'error' => $info['error'] ?? null,
                ],
            ]);
            $emitted++;
        }
    }

    /**
     * Для страниц с тем же content_hash: переносим HEAD-findings с прошлого краула
     * (бюджет HEAD на них не тратим).
     *
     * lost_file / broken_image не копируем вслепую: URL ассета должен всё ещё
     * быть в текущих asset_srcs / img_srcs страницы. Иначе тянем ложные 404 после
     * фикса нормализации (пример: fonts.googleapis.com → свой /css2).
     */
    private function copyIncrementalHeadFindings(SiteAuditCrawl $crawl): void
    {
        if (! config('site_audit.incremental_by_content_hash', true)) {
            return;
        }
        if (! Schema::hasColumn('site_audit_pages', 'content_unchanged')) {
            return;
        }

        $unchangedPages = SiteAuditPage::query()
            ->where('crawl_id', $crawl->id)
            ->where('content_unchanged', true)
            ->get(['url_hash', 'asset_srcs_json', 'img_srcs_json']);
        if ($unchangedPages->isEmpty()) {
            return;
        }

        $unchangedHashes = [];
        /** @var array<string,array<string,true>> $assetsByHash */
        $assetsByHash = [];
        /** @var array<string,array<string,true>> $imgsByHash */
        $imgsByHash = [];
        foreach ($unchangedPages as $page) {
            $hash = (string) $page->url_hash;
            if ($hash === '') {
                continue;
            }
            $unchangedHashes[] = $hash;
            $assetSet = [];
            foreach (is_array($page->asset_srcs_json) ? $page->asset_srcs_json : [] as $src) {
                $src = (string) $src;
                if ($src !== '') {
                    $assetSet[$src] = true;
                }
            }
            $assetsByHash[$hash] = $assetSet;
            $imgSet = [];
            foreach (SiteAuditImageItem::normalizeList($page->img_srcs_json) as $item) {
                $src = (string) ($item['src'] ?? '');
                if ($src !== '') {
                    $imgSet[$src] = true;
                }
            }
            $imgsByHash[$hash] = $imgSet;
        }
        if ($unchangedHashes === []) {
            return;
        }

        $prevId = SiteAuditCrawl::query()
            ->where('project_id', $crawl->project_id)
            ->where('status', SiteAuditCrawl::STATUS_DONE)
            ->where('id', '<', $crawl->id)
            ->orderByDesc('id')
            ->value('id');
        if (! $prevId) {
            return;
        }

        $codes = [
            'broken_image',
            'heavy_image',
            'lost_file',
        ];

        $rows = SiteAuditFinding::query()
            ->where('crawl_id', (int) $prevId)
            ->whereIn('code', $codes)
            ->whereIn('url_hash', $unchangedHashes)
            ->get(['code', 'severity', 'url', 'url_hash', 'meta_json']);

        foreach ($rows as $row) {
            $hash = (string) $row->url_hash;
            $meta = is_array($row->meta_json) ? $row->meta_json : [];
            if ($row->code === 'lost_file') {
                $asset = (string) ($meta['asset'] ?? '');
                if ($asset === '' || empty($assetsByHash[$hash][$asset])) {
                    continue;
                }
            }
            if ($row->code === 'broken_image' || $row->code === 'heavy_image') {
                $samples = is_array($meta['samples'] ?? null) ? $meta['samples'] : [];
                $stillPresent = false;
                foreach ($samples as $sample) {
                    // emitImageAssets пишет 'img'; старые/ручные meta могли с 'src'
                    $src = (string) ($sample['img'] ?? $sample['src'] ?? '');
                    if ($src !== '' && ! empty($imgsByHash[$hash][$src])) {
                        $stillPresent = true;
                        break;
                    }
                }
                if (! $stillPresent) {
                    continue;
                }
            }

            $dupQ = SiteAuditFinding::query()
                ->where('crawl_id', $crawl->id)
                ->where('code', $row->code)
                ->where('url_hash', $row->url_hash);
            if ($row->code === 'lost_file') {
                $asset = (string) ($meta['asset'] ?? '');
                $already = $dupQ->get(['meta_json'])->contains(static function ($f) use ($asset) {
                    $m = is_array($f->meta_json) ? $f->meta_json : [];

                    return (string) ($m['asset'] ?? '') === $asset;
                });
            } else {
                $already = $dupQ->exists();
            }
            if ($already) {
                continue;
            }

            SiteAuditFinding::query()->create([
                'crawl_id' => $crawl->id,
                'code' => $row->code,
                'severity' => $row->severity,
                'url' => $row->url,
                'url_hash' => $row->url_hash,
                'meta_json' => $row->meta_json,
            ]);
        }
    }

    /**
     * Битые / тяжёлые изображения: проба уникальных img src (бюджет на краул).
     * Результат пишется в img_srcs_json (status, size_bytes, ok) для инвентаря.
     * Этап чанкуется: probe → apply, чтобы не упереться в tick timeout.
     *
     * @param  array<string,mixed>  $meta
     * @return bool true = ещё есть работа
     */
    private function emitImageAssets(SiteAuditCrawl $crawl, array &$meta = [], ?float $deadline = null): bool
    {
        $maxHead = max(0, (int) config('site_audit.broken_image_head_max', 8000));
        $chunk = max(20, (int) config('site_audit.aggregate_image_head_chunk', 80));
        $heavyBytes = (int) config('site_audit.heavy_image_bytes', 500_000);
        $cacheKey = 'sa_img_head:' . (int) $crawl->id;
        $listKey = $cacheKey . ':list';

        $phase = (string) ($meta['phase'] ?? 'collect');
        if ($phase === 'collect') {
            $urls = $this->collectUniqueImageSrcs($crawl);
            Cache::put($listKey, $urls, now()->addHours(6));
            Cache::put($cacheKey, [], now()->addHours(6));
            $meta = [
                'phase' => 'probe',
                'offset' => 0,
                'budget' => $maxHead,
                'total' => count($urls),
            ];
            $phase = 'probe';
        }

        if ($phase === 'probe') {
            $urls = Cache::get($listKey);
            if (! is_array($urls)) {
                $urls = $this->collectUniqueImageSrcs($crawl);
                Cache::put($listKey, $urls, now()->addHours(6));
            }
            $cache = Cache::get($cacheKey);
            if (! is_array($cache)) {
                $cache = [];
            }
            $offset = (int) ($meta['offset'] ?? 0);
            $budget = array_key_exists('budget', $meta) ? (int) $meta['budget'] : $maxHead;
            $checker = new SiteAuditLinkChecker();
            $n = count($urls);
            $done = 0;

            while ($offset < $n && $budget > 0) {
                if ($deadline !== null && microtime(true) >= $deadline) {
                    Cache::put($cacheKey, $cache, now()->addHours(6));
                    $meta['offset'] = $offset;
                    $meta['budget'] = $budget;
                    $meta['phase'] = 'probe';

                    return true;
                }
                if ($done >= $chunk) {
                    Cache::put($cacheKey, $cache, now()->addHours(6));
                    $meta['offset'] = $offset;
                    $meta['budget'] = $budget;
                    $meta['phase'] = 'probe';

                    return true;
                }

                $src = (string) ($urls[$offset] ?? '');
                $offset++;
                if ($src === '' || isset($cache[$src])) {
                    continue;
                }
                $cache[$src] = $checker->check($src);
                $budget--;
                $done++;
            }

            Cache::put($cacheKey, $cache, now()->addHours(6));
            $meta = [
                'phase' => 'apply',
                'after_id' => 0,
                'probed' => count($cache),
            ];

            return true;
        }

        // apply: обогатить img_srcs_json + findings
        $cache = Cache::get($cacheKey);
        if (! is_array($cache) || $cache === []) {
            Cache::forget($cacheKey);
            Cache::forget($listKey);
            $meta = [];

            return false;
        }

        $brokenSev = config('site_audit.findings.broken_image.severity', 'warning');
        $heavySev = config('site_audit.findings.heavy_image.severity', 'info');
        $afterId = (int) ($meta['after_id'] ?? 0);
        $pageChunk = max(40, (int) config('site_audit.aggregate_from_pages_chunk', 200));

        $q = SiteAuditPage::query()
            ->where('crawl_id', $crawl->id)
            ->whereNotNull('img_srcs_json')
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit($pageChunk);
        if (config('site_audit.incremental_by_content_hash', true)
            && Schema::hasColumn('site_audit_pages', 'content_unchanged')) {
            // На apply обогащаем все страницы с картинками — иначе инвентарь «хвоста» пустой.
            // probe уже ограничен бюджетом; content_unchanged режем только на collect/probe.
        }
        $pages = $q->get(['id', 'url', 'url_hash', 'img_srcs_json']);

        if ($pages->isEmpty()) {
            Cache::forget($cacheKey);
            Cache::forget($listKey);
            $meta = [];

            return false;
        }

        foreach ($pages as $page) {
            $afterId = (int) $page->id;
            $items = SiteAuditImageItem::normalizeList($page->img_srcs_json);
            if ($items === []) {
                continue;
            }

            $brokenSamples = [];
            $heavySamples = [];
            $enriched = [];
            $changed = false;

            foreach ($items as $item) {
                $src = (string) ($item['src'] ?? '');
                if ($src !== '' && isset($cache[$src])) {
                    $res = $cache[$src];
                    $item['status'] = $res['status'];
                    $item['size_bytes'] = $res['size_bytes'];
                    $item['ok'] = $res['ok'];
                    if (! empty($res['content_type'])) {
                        $item['content_type'] = $res['content_type'];
                    }
                    $changed = true;

                    if (! $res['ok']) {
                        $brokenSamples[] = [
                            'img' => $src,
                            'status' => $res['status'] ?? null,
                            'error' => $res['error'] ?? null,
                        ];
                    } elseif (! empty($res['size_bytes']) && (int) $res['size_bytes'] >= $heavyBytes) {
                        $heavySamples[] = [
                            'img' => $src,
                            'size_bytes' => (int) $res['size_bytes'],
                            'threshold' => $heavyBytes,
                        ];
                    }
                }
                $enriched[] = $item;
            }

            if ($changed) {
                $page->img_srcs_json = $enriched;
                $page->save();
            }

            if ($brokenSamples) {
                SiteAuditFinding::query()->create([
                    'crawl_id' => $crawl->id,
                    'code' => 'broken_image',
                    'severity' => $brokenSev,
                    'url' => $page->url,
                    'url_hash' => $page->url_hash,
                    'meta_json' => [
                        'count' => count($brokenSamples),
                        'samples' => array_slice($brokenSamples, 0, 8),
                    ],
                ]);
            }
            if ($heavySamples) {
                SiteAuditFinding::query()->create([
                    'crawl_id' => $crawl->id,
                    'code' => 'heavy_image',
                    'severity' => $heavySev,
                    'url' => $page->url,
                    'url_hash' => $page->url_hash,
                    'meta_json' => [
                        'count' => count($heavySamples),
                        'samples' => array_slice($heavySamples, 0, 8),
                        'threshold' => $heavyBytes,
                    ],
                ]);
            }

            if ($deadline !== null && microtime(true) >= $deadline) {
                $meta['after_id'] = $afterId;
                $meta['phase'] = 'apply';

                return true;
            }
        }

        $meta['after_id'] = $afterId;
        $meta['phase'] = 'apply';

        return true;
    }

    /**
     * @return list<string>
     */
    private function collectUniqueImageSrcs(SiteAuditCrawl $crawl): array
    {
        $q = SiteAuditPage::query()
            ->where('crawl_id', $crawl->id)
            ->whereNotNull('img_srcs_json');
        if (config('site_audit.incremental_by_content_hash', true)
            && Schema::hasColumn('site_audit_pages', 'content_unchanged')) {
            $q->where(function ($w) {
                $w->where('content_unchanged', false)->orWhereNull('content_unchanged');
            });
        }

        $seen = [];
        $urls = [];
        $q->orderBy('id')->chunkById(200, function ($pages) use (&$seen, &$urls) {
            foreach ($pages as $page) {
                $items = SiteAuditImageItem::normalizeList($page->img_srcs_json);
                foreach (array_slice($items, 0, 40) as $item) {
                    $src = (string) ($item['src'] ?? '');
                    if ($src === '' || isset($seen[$src])) {
                        continue;
                    }
                    $seen[$src] = true;
                    $urls[] = $src;
                }
            }
        });

        return $urls;
    }

    /**
     * Потерянные файлы: CSS/JS (asset_srcs_json) с 404/ошибкой HEAD + referrer=страница.
     */
    private function emitLostFiles(SiteAuditCrawl $crawl): void
    {
        $q = SiteAuditPage::query()
            ->where('crawl_id', $crawl->id)
            ->whereNotNull('asset_srcs_json');
        if (config('site_audit.incremental_by_content_hash', true)
            && Schema::hasColumn('site_audit_pages', 'content_unchanged')) {
            $q->where('content_unchanged', false);
        }
        $pages = $q->get(['id', 'url', 'url_hash', 'asset_srcs_json']);

        if ($pages->isEmpty()) {
            return;
        }

        $maxHead = (int) config('site_audit.lost_file_head_max', 40);
        $checker = new SiteAuditLinkChecker();
        $cache = [];
        $budget = $maxHead;
        $sev = config('site_audit.findings.lost_file.severity', 'warning');

        // asset URL → list of referrer pages
        $assetPages = [];
        foreach ($pages as $page) {
            $srcs = is_array($page->asset_srcs_json) ? $page->asset_srcs_json : [];
            foreach (array_slice($srcs, 0, 20) as $src) {
                $src = (string) $src;
                if ($src === '') {
                    continue;
                }
                if (! isset($assetPages[$src])) {
                    $assetPages[$src] = [];
                }
                if (count($assetPages[$src]) < 5) {
                    $assetPages[$src][] = [
                        'url' => $page->url,
                        'url_hash' => $page->url_hash,
                    ];
                }
            }
        }

        $emitted = 0;
        $maxFindings = (int) config('site_audit.lost_file_max_findings', 80);
        foreach ($assetPages as $src => $referrers) {
            if ($emitted >= $maxFindings) {
                break;
            }
            if (! isset($cache[$src])) {
                if ($budget <= 0) {
                    break;
                }
                $cache[$src] = $checker->check($src);
                $budget--;
            }
            $res = $cache[$src];
            if ($res['ok']) {
                continue;
            }
            $from = $referrers[0];
            SiteAuditFinding::query()->create([
                'crawl_id' => $crawl->id,
                'code' => 'lost_file',
                'severity' => $sev,
                'url' => $from['url'],
                'url_hash' => $from['url_hash'],
                'meta_json' => [
                    'asset' => $src,
                    'status' => $res['status'] ?? null,
                    'error' => $res['error'] ?? null,
                    'referrers' => array_column($referrers, 'url'),
                    'referrer_count' => count($referrers),
                ],
            ]);
            $emitted++;
        }
    }

    /**
     * Выбросы ошибок: кластеры по коду/префиксу пути и скачок vs прошлый краул.
     */
    private function emitErrorSpikes(SiteAuditCrawl $crawl): void
    {
        $pages = SiteAuditPage::query()
            ->where('crawl_id', $crawl->id)
            ->get(['url', 'url_hash', 'status_code']);

        $total = $pages->count();
        if ($total < 1) {
            return;
        }

        $minCount = max(3, (int) config('site_audit.error_spike_min_count', 5));
        $statusShare = (float) config('site_audit.error_spike_status_share', 0.4);
        $pathMin = max(3, (int) config('site_audit.error_spike_path_min', 5));
        $pathRate = (float) config('site_audit.error_spike_path_rate', 0.5);
        $deltaMin = max(3, (int) config('site_audit.error_spike_delta_min', 5));
        $deltaRatio = (float) config('site_audit.error_spike_delta_ratio', 2.0);

        $errors = [];
        $byStatus = [];
        $byPrefix = [];
        $prefixTotals = [];

        foreach ($pages as $page) {
            $url = (string) $page->url;
            $code = $page->status_code;
            $isErr = $code === null || (int) $code >= 400;
            $prefix = $this->errorSpikePathPrefix($url);
            $prefixTotals[$prefix] = ($prefixTotals[$prefix] ?? 0) + 1;

            if (! $isErr) {
                continue;
            }

            $statusKey = $code === null ? 'unreachable' : (string) (int) $code;
            $errors[] = [
                'url' => $url,
                'url_hash' => $page->url_hash,
                'status' => $code === null ? null : (int) $code,
                'status_key' => $statusKey,
                'prefix' => $prefix,
            ];
            $byStatus[$statusKey] = ($byStatus[$statusKey] ?? 0) + 1;
            $byPrefix[$prefix] = ($byPrefix[$prefix] ?? 0) + 1;
        }

        $errorCount = count($errors);
        if ($errorCount < $minCount) {
            // всё равно проверим delta vs прошлый краул
            $this->emitErrorSpikeDelta($crawl, $errorCount, $deltaMin, $deltaRatio);

            return;
        }

        $sev = config('site_audit.findings.error_spike.severity', 'warning');
        $domain = optional($crawl->project)->domain
            ? preg_replace('#^https?://#i', '', rtrim((string) $crawl->project->domain, '/'))
            : '';
        $rootUrl = $domain !== '' ? ('https://' . $domain . '/') : ($errors[0]['url'] ?? 'https://example.invalid/');

        // 1) доминантный статус среди ошибок
        arsort($byStatus);
        foreach ($byStatus as $statusKey => $cnt) {
            if ($cnt < $minCount) {
                break;
            }
            $share = $errorCount > 0 ? ($cnt / $errorCount) : 0;
            if ($share < $statusShare) {
                break;
            }
            $samples = [];
            foreach ($errors as $e) {
                if ($e['status_key'] !== $statusKey) {
                    continue;
                }
                $samples[] = ['url' => $e['url'], 'status' => $e['status']];
                if (count($samples) >= 8) {
                    break;
                }
            }
            SiteAuditFinding::query()->create([
                'crawl_id' => $crawl->id,
                'code' => 'error_spike',
                'severity' => $sev,
                'url' => $rootUrl,
                'url_hash' => SiteAuditUrlNormalizer::hash($rootUrl),
                'meta_json' => [
                    'kind' => 'status_cluster',
                    'status' => $statusKey,
                    'count' => $cnt,
                    'error_total' => $errorCount,
                    'pages_total' => $total,
                    'share' => round($share, 3),
                    'samples' => $samples,
                ],
            ]);
            break; // один главный статусный выброс
        }

        // 2) префиксы путей с высокой долей ошибок
        $emittedPrefixes = 0;
        arsort($byPrefix);
        foreach ($byPrefix as $prefix => $cnt) {
            if ($emittedPrefixes >= 8) {
                break;
            }
            if ($cnt < $pathMin) {
                continue;
            }
            $inPrefix = (int) ($prefixTotals[$prefix] ?? 0);
            if ($inPrefix < $pathMin) {
                continue;
            }
            $rate = $cnt / $inPrefix;
            if ($rate < $pathRate) {
                continue;
            }
            $prefixUrl = $domain !== ''
                ? ('https://' . $domain . ($prefix === '/' ? '/' : rtrim($prefix, '/') . '/'))
                : $rootUrl;
            $samples = [];
            foreach ($errors as $e) {
                if ($e['prefix'] !== $prefix) {
                    continue;
                }
                $samples[] = ['url' => $e['url'], 'status' => $e['status']];
                if (count($samples) >= 8) {
                    break;
                }
            }
            SiteAuditFinding::query()->create([
                'crawl_id' => $crawl->id,
                'code' => 'error_spike',
                'severity' => $sev,
                'url' => $prefixUrl,
                'url_hash' => SiteAuditUrlNormalizer::hash($prefixUrl),
                'meta_json' => [
                    'kind' => 'path_cluster',
                    'path_prefix' => $prefix,
                    'count' => $cnt,
                    'prefix_total' => $inPrefix,
                    'rate' => round($rate, 3),
                    'error_total' => $errorCount,
                    'pages_total' => $total,
                    'samples' => $samples,
                ],
            ]);
            $emittedPrefixes++;
        }

        $this->emitErrorSpikeDelta($crawl, $errorCount, $deltaMin, $deltaRatio);
    }

    private function emitErrorSpikeDelta(SiteAuditCrawl $crawl, int $errorCount, int $deltaMin, float $deltaRatio): void
    {
        $prev = SiteAuditCrawl::query()
            ->where('project_id', $crawl->project_id)
            ->where('id', '<', $crawl->id)
            ->where('status', SiteAuditCrawl::STATUS_DONE)
            ->orderByDesc('id')
            ->first();

        if (! $prev) {
            return;
        }

        $prevErrors = (int) SiteAuditPage::query()
            ->where('crawl_id', $prev->id)
            ->where(function ($q) {
                $q->whereNull('status_code')->orWhere('status_code', '>=', 400);
            })
            ->count();

        $grew = $errorCount >= $deltaMin
            && (
                ($prevErrors === 0 && $errorCount >= $deltaMin)
                || ($prevErrors > 0 && $errorCount >= (int) ceil($prevErrors * $deltaRatio) && ($errorCount - $prevErrors) >= $deltaMin)
            );

        if (! $grew) {
            return;
        }

        $domain = optional($crawl->project)->domain
            ? preg_replace('#^https?://#i', '', rtrim((string) $crawl->project->domain, '/'))
            : '';
        $rootUrl = $domain !== '' ? ('https://' . $domain . '/') : 'https://example.invalid/';
        $sev = config('site_audit.findings.error_spike.severity', 'warning');

        SiteAuditFinding::query()->create([
            'crawl_id' => $crawl->id,
            'code' => 'error_spike',
            'severity' => $sev,
            'url' => $rootUrl,
            'url_hash' => SiteAuditUrlNormalizer::hash($rootUrl),
            'meta_json' => [
                'kind' => 'crawl_delta',
                'count' => $errorCount,
                'prev_count' => $prevErrors,
                'prev_crawl_id' => $prev->id,
                'delta' => $errorCount - $prevErrors,
                'ratio' => $prevErrors > 0 ? round($errorCount / $prevErrors, 2) : null,
            ],
        ]);
    }

    private function errorSpikePathPrefix(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '' || $path === '/') {
            return '/';
        }
        $parts = array_values(array_filter(explode('/', $path), static function ($p) {
            return $p !== '';
        }));
        if ($parts === []) {
            return '/';
        }
        $take = array_slice($parts, 0, min(2, count($parts)));

        return '/' . implode('/', $take);
    }

    /**
     * BFS по out_links от главной → click_depth + deep_pages.
     *
     * @return array{click_depth_max:int}
     */
    private function emitClickDepth(int $crawlId): array
    {
        $computed = SiteAuditClickDepth::compute($crawlId);
        $depth = $computed['depth_by_id'];
        $pathByUrl = $computed['path_by_url'];
        $maxDepth = (int) $computed['max_depth'];

        if ($depth === []) {
            return ['click_depth_max' => 0];
        }

        $pages = SiteAuditPage::query()
            ->where('crawl_id', $crawlId)
            ->get(['id', 'url', 'url_hash', 'content_type']);

        $warnAt = (int) config('site_audit.click_depth_warn', 4);
        $severity = config('site_audit.findings.deep_pages.severity', 'info');
        $byDepthIds = [];

        foreach ($pages as $page) {
            $id = (int) $page->id;
            $d = array_key_exists($id, $depth) ? $depth[$id] : null;
            $key = $d === null ? 'null' : (string) $d;
            $byDepthIds[$key][] = $id;

            if ($d !== null && $d >= $warnAt
                && SiteAuditUrlNormalizer::isHtmlDocument($page->content_type ?? null, (string) $page->url)
            ) {
                $path = $pathByUrl[(string) $page->url] ?? [];
                SiteAuditFinding::query()->create([
                    'crawl_id' => $crawlId,
                    'code' => 'deep_pages',
                    'severity' => $severity,
                    'url' => $page->url,
                    'url_hash' => $page->url_hash,
                    'meta_json' => [
                        'depth' => $d,
                        'threshold' => $warnAt,
                        'path' => array_values($path),
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
            if (! $user) {
                return;
            }
            if ($user->email) {
                $user->notify(new \App\Notifications\SiteAuditCrawlCompletedNotification($crawl));
            }
            \App\Notifications\SiteAuditCrawlCompletedNotification::sendTelegram($user, $crawl);
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
        $shingleMinOverlap = (float) config('site_audit.simhash_shingle_min_overlap', 0.15);
        $shingleSize = max(2, (int) config('site_audit.simhash_shingle_size', 5));
        $chromeShare = (float) config('site_audit.simhash_chrome_token_share', 0.20);
        $chromeMinPages = (int) config('site_audit.simhash_chrome_min_pages', 8);
        $minUniqueShared = max(1, (int) config('site_audit.simhash_min_unique_shared', 4));
        $thinWords = max(40, (int) config('site_audit.thin_words', 150));
        // На тощих страницах шаблон даёт ложные пары — требуем больше «смысловых» общих слов.
        $minUniqueSharedThin = max($minUniqueShared, (int) config('site_audit.simhash_min_unique_shared_thin', 10));

        $cols = [
            'id', 'url', 'url_hash', 'simhash', 'title', 'h1', 'description',
            'word_count', 'top_word', 'top_bigram', 'top_trigram',
            'redirect_chain', 'final_url',
        ];
        if (Schema::hasColumn('site_audit_pages', 'content_hash')) {
            $cols[] = 'content_hash';
        }
        if (Schema::hasColumn('site_audit_pages', 'token_top_json')) {
            $cols[] = 'token_top_json';
        }
        $hasShinglesCol = Schema::hasColumn('site_audit_pages', 'shingles_json');
        if ($hasShinglesCol) {
            $cols[] = 'shingles_json';
        }
        $pages = SiteAuditPage::query()
            ->where('crawl_id', $crawlId)
            ->whereNotNull('simhash')
            ->where('simhash', '!=', '')
            ->orderBy('id')
            ->get($cols);

        $pages = $pages->filter(function ($page) {
            return ! $this->pageHadRedirect($page);
        })->values();

        $n = $pages->count();
        if ($n < 2) {
            return;
        }

        // ограничиваем pairwise для больших краулов
        if ($n > 800) {
            $pages = $pages->take(800)->values();
            $n = $pages->count();
        }

        $titleSuffixes = SiteAuditTitleChrome::detectCommonSuffixes(
            $pages->pluck('title')->all(),
            $n,
            0.35
        );
        $crawl = SiteAuditCrawl::query()->with('project:id,domain')->find($crawlId);
        $domain = $crawl && $crawl->project ? (string) $crawl->project->domain : '';
        $brandTokens = SiteAuditTitleChrome::domainBrandTokens($domain);

        // DF по токенам страниц + бренд домена + слова из частых хвостов TITLE.
        $df = [];
        $bags = [];
        foreach ($pages as $idx => $page) {
            $bag = self::pageTokenBag($page);
            $bags[$idx] = $bag;
            foreach (array_unique($bag) as $t) {
                $df[$t] = ($df[$t] ?? 0) + 1;
            }
        }
        foreach ($brandTokens as $t) {
            $df[$t] = max($df[$t] ?? 0, $n);
        }
        foreach ($titleSuffixes as $suf) {
            foreach (preg_split('/[^\p{L}\p{N}]+/u', $suf, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $w) {
                $w = mb_strtolower($w);
                if (mb_strlen($w) >= 3) {
                    $df[$w] = max($df[$w] ?? 0, (int) ceil($n * 0.5));
                }
            }
        }

        $chromeNeed = max($chromeMinPages, (int) ceil($n * $chromeShare));
        $chrome = [];
        foreach ($df as $t => $c) {
            if ($c >= $chromeNeed) {
                $chrome[$t] = true;
            }
        }
        // Служебные EN/RU слова — не «смысл статьи», даже если DF низкий на маленьком сэмпле.
        foreach (SiteAuditTitleChrome::fillerTokens() as $fill) {
            $chrome[$fill] = true;
        }

        $severity = config('site_audit.findings.similar_pages.severity', 'important');
        $emitted = 0;
        $seen = [];

        for ($i = 0; $i < $n && $emitted < $maxPairs; $i++) {
            $a = $pages[$i];
            $shinglesA = $hasShinglesCol
                ? SiteAuditSimhash::normalizeShingleList($a->shingles_json ?? null)
                : [];
            for ($j = $i + 1; $j < $n && $emitted < $maxPairs; $j++) {
                $b = $pages[$j];
                $dist = SiteAuditSimhash::hamming($a->simhash, $b->simhash);
                if ($dist > $threshold) {
                    continue;
                }

                // Exact duplicate_content — не дублируем в similar_pages.
                $hashA = trim((string) ($a->content_hash ?? ''));
                $hashB = trim((string) ($b->content_hash ?? ''));
                if ($hashA !== '' && $hashA === $hashB) {
                    continue;
                }

                $shinglesB = $hasShinglesCol
                    ? SiteAuditSimhash::normalizeShingleList($b->shingles_json ?? null)
                    : [];
                $overlap = null;
                // Второй проход: есть шинголовы у обеих — требуем долю общих ≥ порога.
                if (count($shinglesA) >= $shingleSize && count($shinglesB) >= $shingleSize) {
                    $overlap = SiteAuditSimhash::shingleOverlap($shinglesA, $shinglesB, 8);
                    if ($overlap['ratio'] < $shingleMinOverlap) {
                        continue;
                    }
                }

                $tokensA = $bags[$i] ?? self::pageTokenBag($a);
                $tokensB = $bags[$j] ?? self::pageTokenBag($b);
                // Хвосты TITLE этой пары (« — PRIME Blog») — всегда chrome.
                $pairChrome = $chrome;
                foreach ([$a->title ?? '', $b->title ?? ''] as $pairTitle) {
                    foreach (SiteAuditTitleChrome::tokensFromTitle((string) $pairTitle) as $tw) {
                        $pairChrome[$tw] = true;
                    }
                }
                $sharedAll = SiteAuditTextMetrics::sharedTokenList($tokensA, $tokensB, 36);
                $sharedUnique = SiteAuditTitleChrome::withoutChrome($sharedAll, $pairChrome);
                $wordsA = (int) ($a->word_count ?? 0);
                $wordsB = (int) ($b->word_count ?? 0);
                $needUnique = ($wordsA > 0 && $wordsA < $thinWords && $wordsB > 0 && $wordsB < $thinWords)
                    ? $minUniqueSharedThin
                    : $minUniqueShared;
                // Пара только на бренде/меню («blog», «prime», «without») — не «похожий контент».
                if (count($sharedUnique) < $needUnique) {
                    continue;
                }

                // не дублируем exact content_hash пары — они в duplicate_content
                foreach ([$a, $b] as $page) {
                    $key = $page->url_hash;
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $other = $page->id === $a->id ? $b : $a;
                    $sharedSource = (! empty($page->token_top_json) && ! empty($other->token_top_json))
                        ? 'body'
                        : 'meta';
                    $meta = [
                        'similar_url' => $other->url,
                        'hamming' => $dist,
                        'title' => $page->title,
                        'word_count' => (int) ($page->word_count ?? 0),
                        'similar_word_count' => (int) ($other->word_count ?? 0),
                        'shared_words' => array_slice($sharedUnique, 0, 24),
                        'shared_source' => $sharedSource,
                        'chrome_filtered' => max(0, count($sharedAll) - count($sharedUnique)),
                    ];
                    if ($overlap !== null) {
                        $meta['shingle_size'] = $shingleSize;
                        $meta['shingle_overlap'] = $overlap['ratio'];
                        $meta['shingle_shared'] = $overlap['shared'];
                        $meta['shared_shingles'] = $overlap['samples'];
                    }
                    SiteAuditFinding::query()->create([
                        'crawl_id' => $crawlId,
                        'code' => 'similar_pages',
                        'severity' => $severity,
                        'url' => $page->url,
                        'url_hash' => $page->url_hash,
                        'meta_json' => $meta,
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

    /**
     * Токены страницы для пересечения похожих: token_top_json или слабый набор из meta.
     *
     * @return list<string>
     */
    private static function pageTokenBag($page): array
    {
        $raw = $page->token_top_json ?? null;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : null;
        }
        if (is_array($raw) && $raw !== []) {
            $out = [];
            foreach ($raw as $item) {
                if (is_string($item)) {
                    $w = mb_strtolower(trim($item));
                    if ($w !== '') {
                        $out[] = $w;
                    }
                } elseif (is_array($item) && isset($item['word'])) {
                    $w = mb_strtolower(trim((string) $item['word']));
                    if ($w !== '') {
                        $out[] = $w;
                    }
                }
            }
            if ($out !== []) {
                return array_values(array_unique($out));
            }
        }

        return SiteAuditTextMetrics::weakTokenBagFromPage($page);
    }

    private function emitDuplicates(int $crawlId, string $hashColumn, string $code): void
    {
        $severity = config('site_audit.findings.' . $code . '.severity', 'other');

        $cols = ['url', 'url_hash', 'title', 'description', $hashColumn, 'redirect_chain', 'final_url'];
        if ($hashColumn === 'content_hash') {
            $cols[] = 'word_count';
            $cols[] = 'text_len';
        }

        $pages = SiteAuditPage::query()
            ->where('crawl_id', $crawlId)
            ->whereNotNull($hashColumn)
            ->get($cols);

        // SHA-256 пустой строки — оболочка без текста (JS-листинг и т.п.), не «дубль контента».
        $emptyContentHash = hash('sha256', '');

        $byHash = [];
        foreach ($pages as $page) {
            // Follow редиректа → тот же body/meta, что у финала. Не считаем «дублем» с каноном.
            if ($this->pageHadRedirect($page)) {
                continue;
            }
            $hash = (string) $page->{$hashColumn};
            if ($hash === '') {
                continue;
            }
            if ($hashColumn === 'content_hash') {
                if ($hash === $emptyContentHash) {
                    continue;
                }
                $words = (int) ($page->word_count ?? 0);
                $textLen = (int) ($page->text_len ?? 0);
                // Слишком мало текста — не сравниваем как полноценный контент.
                if ($words < 20 && $textLen < 80) {
                    continue;
                }
            }
            if ($hashColumn === 'title_hash' && trim((string) ($page->title ?? '')) === '') {
                continue;
            }
            if ($hashColumn === 'description_hash' && trim((string) ($page->description ?? '')) === '') {
                continue;
            }
            $byHash[$hash][] = $page;
        }

        foreach ($byHash as $hash => $group) {
            $count = count($group);
            if ($count < 2) {
                continue;
            }
            $groupUrls = [];
            foreach ($group as $page) {
                $u = trim((string) $page->url);
                if ($u !== '' && ! in_array($u, $groupUrls, true)) {
                    $groupUrls[] = $u;
                }
            }
            $groupLabel = null;
            if ($code === 'duplicate_content') {
                foreach ($group as $page) {
                    $t = trim((string) ($page->title ?? ''));
                    if ($t !== '') {
                        $groupLabel = $t;
                        break;
                    }
                }
            }
            foreach ($group as $page) {
                $pageUrl = trim((string) $page->url);
                $peers = array_values(array_filter($groupUrls, static function ($u) use ($pageUrl) {
                    return $u !== $pageUrl;
                }));
                $meta = [
                    'group_size' => $count,
                    'hash' => $hash,
                    'title' => $page->title,
                    'description' => $page->description,
                    'peer_urls' => array_slice($peers, 0, 12),
                ];
                if ($groupLabel !== null) {
                    $meta['label'] = $groupLabel;
                }
                SiteAuditFinding::query()->create([
                    'crawl_id' => $crawlId,
                    'code' => $code,
                    'severity' => $severity,
                    'url' => $page->url,
                    'url_hash' => $page->url_hash,
                    'meta_json' => $meta,
                ]);
            }
        }
    }

    /**
     * URL, с которого краулер ушёл по Location (цепь или final ≠ start).
     * У такой страницы title/description/content — уже от финала.
     */
    private function pageHadRedirect($page): bool
    {
        $chain = $page->redirect_chain ?? null;
        if (is_string($chain)) {
            $decoded = json_decode($chain, true);
            $chain = is_array($decoded) ? $decoded : [];
        }
        if (is_array($chain) && $chain !== []) {
            return true;
        }

        $final = trim((string) ($page->final_url ?? ''));
        $url = trim((string) ($page->url ?? ''));
        if ($final === '' || $url === '') {
            return false;
        }

        return SiteAuditRedirectChain::normalize($final) !== SiteAuditRedirectChain::normalize($url);
    }

    /**
     * Циклы редиректов по уже сохранённым pages.redirect_chain (для старых краулов / reaggregate).
     */
    private function emitRedirectLoops(int $crawlId): void
    {
        $severity = config('site_audit.findings.redirect_loop.severity', 'critical');
        $loopHashes = [];
        $rows = [];
        $now = now();

        SiteAuditPage::query()
            ->where('crawl_id', $crawlId)
            ->whereNotNull('redirect_chain')
            ->orderBy('id')
            ->chunkById(200, function ($pages) use ($crawlId, $severity, &$loopHashes, &$rows, $now) {
                foreach ($pages as $page) {
                    $chain = is_array($page->redirect_chain) ? $page->redirect_chain : [];
                    if ($chain === []) {
                        continue;
                    }
                    $info = SiteAuditRedirectChain::analyze(
                        (string) $page->url,
                        $chain,
                        $page->final_url ? (string) $page->final_url : null
                    );
                    if (! $info['loop']) {
                        continue;
                    }
                    $loopHashes[] = (string) $page->url_hash;
                    $rows[] = [
                        'crawl_id' => $crawlId,
                        'code' => 'redirect_loop',
                        'severity' => $severity,
                        'url' => $page->url,
                        'url_hash' => $page->url_hash,
                        'meta_json' => json_encode([
                            'final' => $page->final_url,
                            'chain' => $chain,
                            'path' => $info['path'],
                            'loop_at' => $info['at'],
                            'length' => max(0, count($info['path']) - 1),
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            });

        if ($loopHashes !== []) {
            SiteAuditFinding::query()
                ->where('crawl_id', $crawlId)
                ->whereIn('code', ['redirect', 'redirect_chain_long'])
                ->whereIn('url_hash', array_values(array_unique($loopHashes)))
                ->delete();
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            SiteAuditFinding::query()->insert($chunk);
        }
    }
}
