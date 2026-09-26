<?php

namespace App\Services\SiteAudit;

use App\Jobs\SiteAudit\AggregateSiteAuditCrawlJob;
use App\Jobs\SiteAudit\ContinueSiteAuditCrawlJob;
use App\SiteAuditCrawl;
use App\SiteAuditFinding;
use App\SiteAuditPage;
use App\SiteAuditProject;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

/**
 * Полный прогон краула: sitemap + дообход по внутренним ссылкам до лимита.
 *
 * Асинхронно — порциями (batch): после лимита страниц/секунд ставится ContinueSiteAuditCrawlJob,
 * чтобы worker timeout (раньше 1ч) не убивал краул на середине.
 */
class SiteAuditCrawlEngine
{
    /** Как часто писать лёгкий прогресс в БД (без очереди URL). */
    private const PROGRESS_DB_EVERY = 5;

    /** Как часто сбрасывать очередь/seen на диск. */
    private const ENGINE_FILE_EVERY = 15;

    public function run(SiteAuditCrawl $crawl, bool $asyncChunks = true): SiteAuditCrawl
    {
        if (! $this->hasEngineState($crawl)) {
            $crawl = $this->discoverAndInitQueue($crawl);
            $crawl->refresh();
            if ($crawl->isFinished()) {
                return $crawl;
            }
            if (! $this->hasEngineState($crawl)) {
                return $crawl;
            }
        }

        if ($asyncChunks) {
            $this->processBatch($crawl);

            return $crawl->fresh() ?: $crawl;
        }

        // sync: крутим батчи в одном процессе до конца
        $guard = 0;
        while ($guard++ < 100000) {
            $crawl->refresh();
            if ($crawl->isFinished()) {
                return $crawl;
            }
            if (! $this->hasEngineState($crawl)) {
                return $crawl;
            }
            $more = $this->processBatch($crawl, false);
            if (! $more) {
                return $crawl->fresh() ?: $crawl;
            }
        }

        $crawl->status = SiteAuditCrawl::STATUS_FAILED;
        $crawl->error = 'Прерван: слишком много батчей (внутренняя защита)';
        $crawl->finished_at = now();
        $crawl->save();
        SiteAuditGlobalCap::promoteWaiting();

        return $crawl->fresh() ?: $crawl;
    }

    /**
     * Один тик обхода. @return bool true если ещё есть URL и нужен следующий тик
     */
    public function processBatch(SiteAuditCrawl $crawl, bool $dispatchContinue = true): bool
    {
        $lockKey = 'site_audit_crawl_tick_' . $crawl->id;
        $lockTtl = max(120, (int) config('site_audit.batch_max_seconds', 240) + 180);
        // Continue ставим после unlock — иначе второй воркер сразу упирается в lock
        // и копит «промахи» / отложенные тики впустую.
        $continueAfterUnlock = null;
        if (! Cache::add($lockKey, 1, $lockTtl)) {
            // Orphan lock после kill воркера (deploy/supervisor): иначе Continue
            // только откладывается на 45с, а fetched стоит минутами.
            $crawl->refresh();
            $staleLock = $crawl->updated_at
                && $crawl->updated_at->lt(now()->subSeconds(90))
                && ! $crawl->isFinished();
            if ($staleLock) {
                Cache::forget($lockKey);
                if (! Cache::add($lockKey, 1, $lockTtl)) {
                    if ($dispatchContinue) {
                        $this->scheduleContinue((int) $crawl->id, 20);
                    }

                    return true;
                }
            } else {
                if ($dispatchContinue) {
                    $this->scheduleContinue((int) $crawl->id, 45);
                }

                return true;
            }
        }

        try {
            $crawl->refresh();
            if ($crawl->isFinished() || $crawl->status === SiteAuditCrawl::STATUS_CANCELLED) {
                return false;
            }
            if ($crawl->status === SiteAuditCrawl::STATUS_QUEUED_WAIT) {
                return false;
            }
            if (! $this->hasEngineState($crawl)) {
                // rename/tmp race или Continue прилетел раньше записи на диск —
                // не валим краул сразу: подождём и перепоставим тик.
                $path = $this->engineStatePath((int) $crawl->id);
                for ($t = 0; $t < 10; $t++) {
                    usleep(200000);
                    clearstatcache(true, $path);
                    if ($this->hasEngineState($crawl)) {
                        break;
                    }
                }
            }
            if (! $this->hasEngineState($crawl) && (int) $crawl->pages_fetched > 0) {
                // Файл могли снести гонкой — восстановить очередь из sitemap − pages.
                $this->tryRebuildEngineStateForResume($crawl);
                $crawl->refresh();
            }
            if (! $this->hasEngineState($crawl)) {
                if (in_array($crawl->status, [
                    SiteAuditCrawl::STATUS_FETCHING,
                    SiteAuditCrawl::STATUS_DISCOVERING,
                ], true)) {
                    $missKey = 'site_audit_engine_miss_' . $crawl->id;
                    $misses = (int) Cache::get($missKey, 0) + 1;
                    Cache::put($missKey, $misses, 3600);
                    Log::warning('SiteAudit engine state missing', [
                        'crawl_id' => $crawl->id,
                        'status' => $crawl->status,
                        'misses' => $misses,
                        'pages_fetched' => (int) $crawl->pages_fetched,
                        'engine_path' => $this->engineStatePath((int) $crawl->id),
                        'engine_is_file' => is_file($this->engineStatePath((int) $crawl->id)),
                    ]);
                    // Никогда не валим краул из‑за «промаха» файла: либо rebuild уже
                    // отработал выше, либо Continue повторит тик. Hard-fail отсюда
                    // давал ложные «Ошибка» при живом crawl_N.json (#100/#104).
                    if ($dispatchContinue) {
                        $continueAfterUnlock = ['delay' => min(120, 5 + 10 * min(max($misses, 1), 10))];
                    }

                    return true;
                }

                return false;
            }
            Cache::forget('site_audit_engine_miss_' . $crawl->id);

            $maxPages = max(1, (int) config('site_audit.batch_max_pages', 80));
            $maxSeconds = max(30, (int) config('site_audit.batch_max_seconds', 240));
            $startedAt = microtime(true);

            $project = SiteAuditProject::query()->find($crawl->project_id);
            if (! $project) {
                $crawl->status = SiteAuditCrawl::STATUS_FAILED;
                $crawl->error = 'Project not found';
                $crawl->finished_at = now();
                $this->clearEngineState($crawl);
                $crawl->save();
                SiteAuditGlobalCap::promoteWaiting();

                return false;
            }

            $settings = array_merge(
                $project->settings_json ?? [],
                is_array($crawl->progress_json['settings'] ?? null) ? $crawl->progress_json['settings'] : []
            );
            $limit = max(1, (int) $crawl->pages_limit);
            $host = SiteAuditUrlNormalizer::hostOf('https://' . $project->domain) ?: $project->domain;
            $patterns = SiteAuditUrlFilter::parsePatterns(
                $settings['exclude_patterns'] ?? $project->setting('exclude_patterns', [])
            );

            $state = $this->loadEngineState($crawl);
            $queue = $state['queue'];
            $i = $state['index'];
            $fetched = $state['fetched'];
            $seen = $state['seen'];
            $unchanged = $state['unchanged'];
            $expanded = $state['expanded'];
            $origins = $state['origins'];
            $robotsGroups = is_array($crawl->progress_json['robots']['groups'] ?? null)
                ? $crawl->progress_json['robots']['groups']
                : null;

            // сразу снять огромный engine / urls_gz из MySQL (миграция mid-crawl)
            $dirtyProgress = false;
            if (isset($crawl->progress_json['engine'])) {
                $this->persistEngineState($crawl, $queue, $i, $fetched, $seen, $unchanged, $expanded, $origins);
                $dirtyProgress = true;
            }
            if (! empty($crawl->progress_json['sitemap']['urls_gz'])) {
                $this->offloadSitemapUrlsGz($crawl);
                $dirtyProgress = true;
            }
            if ($dirtyProgress) {
                $crawl->save();
            }

            if ($crawl->status !== SiteAuditCrawl::STATUS_FETCHING) {
                $crawl->status = SiteAuditCrawl::STATUS_FETCHING;
                $crawl->save();
            }

            $processor = new SiteAuditPageProcessor();
            $robotsTxt = is_array($robotsGroups) ? new SiteAuditRobotsTxt() : null;
            /** @var array<string, true> $robotsBlockedSeen URL уже отмечены в robots_blocked */
            $robotsBlockedSeen = [];
            $processed = 0;
            $maxConcurrency = max(1, (int) config('site_audit.max_concurrency', 8));
            $concurrency = max(1, min($maxConcurrency, (int) ($settings['concurrency'] ?? 1)));

            while ($i < count($queue)) {
                if ($processed >= $maxPages || (microtime(true) - $startedAt) >= $maxSeconds) {
                    break;
                }

                // cancel/fail — редко; не долбим MySQL на каждом URL
                if ($processed === 0 || $processed % 10 === 0) {
                    if ($this->crawlStatusIsTerminal((int) $crawl->id)) {
                        // Важно: сохранить файл очереди и meta — иначе «Возобновить» пропадает.
                        $this->persistEngineState($crawl, $queue, $i, $fetched, $seen, $unchanged, $expanded, $origins);
                        $crawl->save();

                        return false;
                    }
                }

                $remainingBudget = $maxPages - $processed;
                $waveSize = min($concurrency, $remainingBudget, count($queue) - $i);
                if ($waveSize < 1) {
                    break;
                }

                $wave = [];
                for ($w = 0; $w < $waveSize; $w++) {
                    $wave[] = $queue[$i];
                    $i++;
                }

                $waveOrigins = [];
                foreach ($wave as $wu) {
                    if (isset($origins[$wu]) && is_array($origins[$wu])) {
                        $waveOrigins[$wu] = $origins[$wu];
                    }
                }

                try {
                    $outs = $processor->processMany($crawl->id, $wave, $host, $settings, $waveOrigins);
                } catch (\Throwable $e) {
                    Log::warning('SiteAudit wave process failed', [
                        'crawl_id' => $crawl->id,
                        'urls' => count($wave),
                        'error' => $e->getMessage(),
                    ]);
                    $outs = array_fill(0, count($wave), ['internal_links' => [], 'content_unchanged' => false]);
                }

                foreach ($wave as $wi => $url) {
                    $fetched++;
                    $processed++;
                    $out = $outs[$wi] ?? ['internal_links' => [], 'content_unchanged' => false];

                    if (! empty($out['content_unchanged'])) {
                        $unchanged++;
                    }

                    if (! empty($out['internal_links']) && empty($settings['pages_only'])) {
                        // $i уже сдвинут на всю волну, а в $fetched ещё нет «хвоста» волны —
                        // без inFlight known занижается → при turbo лимит 10000 превращался в 10007.
                        $inFlight = max(0, count($wave) - $wi - 1);
                        $known = $fetched + (count($queue) - $i) + $inFlight;
                        foreach ($out['internal_links'] as $link) {
                            if (isset($seen[$link])) {
                                continue;
                            }
                            if ($known >= $limit) {
                                break;
                            }
                            if (count($seen) >= $limit) {
                                break;
                            }
                            if ($patterns && SiteAuditUrlFilter::isExcluded($link, $patterns)) {
                                continue;
                            }
                            if ($robotsTxt && ! $robotsTxt->isPathAllowed($robotsGroups, $link)) {
                                if (! isset($robotsBlockedSeen[$link])) {
                                    $robotsBlockedSeen[$link] = true;
                                    $this->emitRobotsBlockedFinding(
                                        (int) $crawl->id,
                                        $link,
                                        $robotsTxt,
                                        $robotsGroups,
                                        ['via' => 'link', 'from' => $url]
                                    );
                                }
                                continue;
                            }
                            $seen[$link] = true;
                            $queue[] = $link;
                            if (! isset($origins[$link])) {
                                $origins[$link] = ['via' => 'link', 'from' => $url];
                            }
                            $expanded++;
                            $known++;
                        }
                    }

                    $pagesTotal = min(
                        $limit,
                        max(count($seen), $fetched + (count($queue) - $i) + max(0, count($wave) - $wi - 1))
                    );
                    if ($processed % self::PROGRESS_DB_EVERY === 0 || $processed === 1) {
                        $this->touchProgress($crawl, $fetched, $pagesTotal, $unchanged, $expanded);
                    }
                    if ($processed % self::ENGINE_FILE_EVERY === 0) {
                        $this->persistEngineState($crawl, $queue, $i, $fetched, $seen, $unchanged, $expanded, $origins);
                    }
                }
            }

            // закончили всю очередь
            if ($i >= count($queue)) {
                $this->persistEngineState($crawl, $queue, $i, $fetched, $seen, $unchanged, $expanded, $origins);
                $this->finishFetch($crawl, $dispatchContinue);

                return false;
            }

            // ещё есть URL — следующий тик
            $pagesTotal = min(
                $limit,
                max(count($seen), $fetched + (count($queue) - $i))
            );
            $saved = $this->persistEngineState($crawl, $queue, $i, $fetched, $seen, $unchanged, $expanded, $origins);
            if (! $saved) {
                // одна повторная попытка записать очередь
                $saved = $this->persistEngineState($crawl, $queue, $i, $fetched, $seen, $unchanged, $expanded, $origins);
            }
            // engine_resume в MySQL — иначе UI/canResume смотрят на stale meta с discover.
            if ($saved) {
                $crawl->save();
            }
            $this->touchProgress($crawl, $fetched, $pagesTotal, $unchanged, $expanded);

            if ($dispatchContinue) {
                clearstatcache(true, $this->engineStatePath((int) $crawl->id));
                if ($saved && $this->hasEngineState($crawl)) {
                    // 1с после unlock — второй воркер не стартует внахлёст с finally.
                    $continueAfterUnlock = ['delay' => 1];
                } else {
                    Log::warning('SiteAudit defer continue: state not durable yet', [
                        'crawl_id' => $crawl->id,
                        'fetched' => $fetched,
                    ]);
                    $continueAfterUnlock = ['delay' => 15];
                }
            }

            return true;
        } finally {
            Cache::forget($lockKey);
            if (is_array($continueAfterUnlock) && isset($continueAfterUnlock['delay'])) {
                $delay = (int) $continueAfterUnlock['delay'];
                if ($delay <= 1) {
                    $this->pushContinueJob((int) $crawl->id, max(0, $delay));
                } else {
                    $this->scheduleContinue((int) $crawl->id, $delay);
                }
            }
        }
    }

    /**
     * Safety Continue с debounce — не забиваем очередь при живом батче.
     */
    public function scheduleContinue(int $crawlId, int $delaySeconds = 45): void
    {
        $delaySeconds = max(5, $delaySeconds);
        $key = 'site_audit_continue_sched_' . $crawlId;
        if (! Cache::add($key, 1, $delaySeconds + 30)) {
            return;
        }
        $this->pushContinueJob($crawlId, $delaySeconds);
    }

    /**
     * Push Continue в database-queue с retry на deadlock (иначе цепочка батчей рвётся).
     */
    public function pushContinueJob(int $crawlId, int $delaySeconds = 0): void
    {
        $queue = (string) (config('site_audit.queue') ?: 'site_audit');
        for ($attempt = 0; $attempt < 4; $attempt++) {
            try {
                $job = new ContinueSiteAuditCrawlJob($crawlId);
                // Явно pushOn/laterOn: Queue::push() в этом Laravel кладёт в default,
                // и prod-воркеры съедают тик без локального crawl_N.json.
                if ($delaySeconds > 0) {
                    Queue::laterOn($queue, now()->addSeconds($delaySeconds), $job);
                } else {
                    Queue::pushOn($queue, $job);
                }

                return;
            } catch (\Throwable $e) {
                $msg = $e->getMessage();
                $deadlock = stripos($msg, 'Deadlock') !== false || strpos($msg, '1213') !== false;
                if (! $deadlock || $attempt >= 3) {
                    Log::warning('SiteAudit continue dispatch failed', [
                        'crawl_id' => $crawlId,
                        'queue' => $queue,
                        'delay' => $delaySeconds,
                        'attempt' => $attempt + 1,
                        'error' => $msg,
                    ]);

                    return;
                }
                usleep(150000 * ($attempt + 1));
            }
        }
    }

    private function discoverAndInitQueue(SiteAuditCrawl $crawl): SiteAuditCrawl
    {
        $project = SiteAuditProject::query()->find($crawl->project_id);
        if (! $project) {
            $crawl->status = SiteAuditCrawl::STATUS_FAILED;
            $crawl->error = 'Project not found';
            $crawl->finished_at = now();
            $crawl->save();
            SiteAuditGlobalCap::promoteWaiting();

            return $crawl;
        }

        $crawl->status = SiteAuditCrawl::STATUS_DISCOVERING;
        $crawl->started_at = $crawl->started_at ?: now();
        $crawl->save();

        try {
            (new SiteAuditRobotsProbe())->run($crawl, $project->domain);
            $crawl->refresh();
        } catch (\Throwable $e) {
            // optional
        }

        try {
            (new SiteAuditHostVariantProbe())->run($crawl, $project->domain);
            $crawl->refresh();
        } catch (\Throwable $e) {
            // optional — не валим краул из‑за проверки зеркал
        }

        $settings = array_merge(
            $project->settings_json ?? [],
            is_array($crawl->progress_json['settings'] ?? null) ? $crawl->progress_json['settings'] : []
        );

        $limit = max(1, (int) $crawl->pages_limit);
        $patterns = SiteAuditUrlFilter::parsePatterns(
            $settings['exclude_patterns'] ?? $project->setting('exclude_patterns', [])
        );

        $urlOpts = SiteAuditUrlNormalizer::optionsFromSettings($settings, $project->domain);
        $pagesOnly = ! empty($settings['pages_only']);

        /** @var array<string, true> $seed */
        $seed = [];
        /** @var array<string, array{via:string,from:?string}> $origins */
        $origins = [];
        $markOrigin = static function (string $url, string $via, ?string $from = null) use (&$seed, &$origins): void {
            $seed[$url] = true;
            if (! isset($origins[$url])) {
                $origins[$url] = ['via' => $via, 'from' => $from];
            }
        };

        $manual = $project->setting('seed_urls', []);
        if (is_array($manual)) {
            foreach ($manual as $u) {
                $norm = SiteAuditUrlNormalizer::normalize((string) $u, $project->domain, $urlOpts);
                if ($norm) {
                    $markOrigin($norm, 'seed');
                }
            }
        }

        $home = SiteAuditUrlNormalizer::normalize('https://' . $project->domain . '/', $project->domain, $urlOpts);

        if ($pagesOnly) {
            // только явные URL: без главной «насильно», без sitemap, без дообхода
            if ($seed === []) {
                $crawl->status = SiteAuditCrawl::STATUS_FAILED;
                $crawl->error = 'Режим «только страницы»: укажите хотя бы один URL';
                $crawl->finished_at = now();
                $crawl->save();
                SiteAuditGlobalCap::promoteWaiting();

                return $crawl;
            }
            $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
            $progress['pages_only'] = true;
            $progress['sitemap'] = [
                'found' => false,
                'url_count' => 0,
                'seed_count' => count($seed),
                'sources' => [],
                'errors' => [],
                'skipped' => 'pages_only',
            ];
            $crawl->progress_json = $progress;
            $crawl->save();
        } else {
            if ($home) {
                $markOrigin($home, 'home');
            }

            $extraHosts = SiteAuditUrlNormalizer::parseExtraHosts($settings['extra_hosts'] ?? []);
            foreach ($extraHosts as $extraHost) {
                $extraHome = SiteAuditUrlNormalizer::normalize('https://' . $extraHost . '/', $extraHost, $urlOpts);
                if ($extraHome) {
                    $markOrigin($extraHome, 'home');
                }
            }
            if ($extraHosts !== []) {
                $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
                $progress['extra_hosts'] = $extraHosts;
                $crawl->progress_json = $progress;
                $crawl->save();
            }

            try {
                $discovered = (new SiteAuditSitemapProbe())->run($crawl, $project->domain, $limit);
                $crawl->refresh();
                foreach ($discovered['seed_urls'] as $u) {
                    $norm = SiteAuditUrlNormalizer::normalize($u, $project->domain, $urlOpts) ?: $u;
                    $markOrigin($norm, 'sitemap');
                }
            } catch (\Throwable $e) {
                if (! $seed) {
                    $crawl->status = SiteAuditCrawl::STATUS_FAILED;
                    $crawl->error = 'Discovery failed: ' . $e->getMessage();
                    $crawl->finished_at = now();
                    $crawl->save();
                    SiteAuditGlobalCap::promoteWaiting();

                    return $crawl;
                }
            }
        }

        $queue = array_keys($seed);
        if ($patterns) {
            $before = count($queue);
            $queue = SiteAuditUrlFilter::filterList($queue, $patterns);
            $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
            $progress['excluded'] = max(0, $before - count($queue));
            $crawl->progress_json = $progress;
        }

        $robotsSkipped = 0;
        $groups = $crawl->progress_json['robots']['groups'] ?? null;
        if (is_array($groups) && $groups !== []) {
            $robotsTxt = new SiteAuditRobotsTxt();
            $kept = [];
            $skipped = [];
            foreach ($queue as $u) {
                if ($home && $u === $home) {
                    $kept[] = $u;
                    continue;
                }
                if ($robotsTxt->isPathAllowed($groups, $u)) {
                    $kept[] = $u;
                    continue;
                }
                $skipped[] = $u;
            }
            $robotsSkipped = count($skipped);
            $queue = $kept;
            foreach ($skipped as $u) {
                $this->emitRobotsBlockedFinding(
                    (int) $crawl->id,
                    $u,
                    $robotsTxt,
                    $groups,
                    $origins[$u] ?? ['via' => 'sitemap', 'from' => null]
                );
            }
            $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
            $progress['robots_skipped'] = $robotsSkipped;
            $crawl->progress_json = $progress;
            // Сразу в БД: иначе refresh() ниже затирает несёханный robots_skipped.
            $crawl->save();
        }

        $queue = array_slice($queue, 0, $limit);
        $origins = array_intersect_key($origins, array_flip($queue));

        $seen = [];
        foreach ($queue as $u) {
            $seen[$u] = true;
        }

        // Resume/kick могли параллельно дернуть Discover: не затираем уже скачанный прогресс.
        $crawl->refresh();
        if ((int) $crawl->pages_fetched > 0 || $this->hasEngineState($crawl)) {
            Log::warning('SiteAudit discover skipped: engine/progress already present', [
                'crawl_id' => $crawl->id,
                'pages_fetched' => (int) $crawl->pages_fetched,
            ]);
            if ($crawl->status !== SiteAuditCrawl::STATUS_FETCHING
                && ! $crawl->isFinished()
                && $crawl->status !== SiteAuditCrawl::STATUS_CANCELLED
            ) {
                $crawl->status = SiteAuditCrawl::STATUS_FETCHING;
                $crawl->save();
            }

            return $crawl;
        }

        $crawl->pages_total = count($queue);
        $crawl->pages_fetched = 0;
        $crawl->status = SiteAuditCrawl::STATUS_FETCHING;
        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        $progress['fetched'] = 0;
        $progress['total'] = count($queue);
        $progress['links_expanded'] = 0;
        $progress['robots_skipped'] = $robotsSkipped;
        $crawl->progress_json = $progress;
        $this->persistEngineState($crawl, $queue, 0, 0, $seen, 0, 0, $origins);
        $this->offloadSitemapUrlsGz($crawl);
        $crawl->save();

        if (! $queue) {
            $crawl->status = SiteAuditCrawl::STATUS_FAILED;
            $crawl->error = 'No URLs discovered';
            $crawl->finished_at = now();
            $this->clearEngineState($crawl);
            $crawl->save();
            SiteAuditGlobalCap::promoteWaiting();
        }

        return $crawl;
    }

    /**
     * URL выкинут из очереди из‑за Disallow — фиксируем robots_blocked (иначе отчёт пустой:
     * processFetched на такие URL не вызывается).
     *
     * @param  array  $groups
     * @param  array{via?:string,from?:?string}|null  $discovery
     */
    private function emitRobotsBlockedFinding(
        int $crawlId,
        string $url,
        SiteAuditRobotsTxt $robotsTxt,
        array $groups,
        ?array $discovery = null
    ): void {
        $url = trim($url);
        if ($url === '') {
            return;
        }
        $hash = SiteAuditUrlNormalizer::hash($url);
        $exists = SiteAuditFinding::query()
            ->where('crawl_id', $crawlId)
            ->where('code', 'robots_blocked')
            ->where('url_hash', $hash)
            ->exists();
        if ($exists) {
            return;
        }

        $match = $robotsTxt->matchingRule($groups, $url);
        if ($match === null || ! empty($match['allow'])) {
            return;
        }

        $cfg = config('site_audit.findings.robots_blocked', []);
        $meta = [
            'source' => 'robots.txt',
            'directive' => (string) ($match['directive'] ?? 'Disallow'),
            'rule' => (string) ($match['path'] ?? ''),
            'agent' => (string) ($match['agent'] ?? '*'),
        ];
        $via = is_array($discovery) ? trim((string) ($discovery['via'] ?? '')) : '';
        if ($via !== '' && in_array($via, ['sitemap', 'seed', 'home', 'link'], true)) {
            $meta['discovered_via'] = $via;
            if ($via === 'link') {
                $from = trim((string) ($discovery['from'] ?? ''));
                if ($from !== '') {
                    $meta['discovered_from'] = $from;
                }
            }
        }

        try {
            SiteAuditFinding::query()->create([
                'crawl_id' => $crawlId,
                'code' => 'robots_blocked',
                'severity' => $cfg['severity'] ?? 'warning',
                'url' => $url,
                'url_hash' => $hash,
                'meta_json' => $meta,
            ]);
        } catch (\Throwable $e) {
            Log::warning('SiteAudit robots_blocked emit failed', [
                'crawl_id' => $crawlId,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function finishFetch(SiteAuditCrawl $crawl, bool $queueAggregate = true): void
    {
        SiteAuditUserAgentSession::clear($crawl->id);

        $crawl->refresh();
        // Остановлен пользователем — очередь на диске нужна для «Возобновить».
        // Раньше clearEngineState() здесь сносил файл → play пропадал при 20/240.
        if ($crawl->status === SiteAuditCrawl::STATUS_CANCELLED) {
            $crawl->finished_at = $crawl->finished_at ?: now();
            $crawl->save();
            SiteAuditGlobalCap::promoteWaiting();

            return;
        }

        $this->clearEngineState($crawl);
        $crawl->save();

        if ($crawl->isFinished()) {
            SiteAuditGlobalCap::promoteWaiting();

            return;
        }

        if ($queueAggregate) {
            AggregateSiteAuditCrawlJob::dispatch($crawl->id);
        } else {
            (new AggregateSiteAuditCrawlJob($crawl->id))->handle();
        }
    }

    private function crawlStatusIsTerminal(int $crawlId): bool
    {
        $status = \Illuminate\Support\Facades\DB::table('site_audit_crawls')
            ->where('id', $crawlId)
            ->value('status');

        return in_array($status, [
            SiteAuditCrawl::STATUS_DONE,
            SiteAuditCrawl::STATUS_FAILED,
            SiteAuditCrawl::STATUS_CANCELLED,
        ], true);
    }

    /**
     * Лёгкий апдейт счётчиков в БД — без перезаписи всего progress_json (urls_gz и т.п.).
     */
    private function touchProgress(
        SiteAuditCrawl $crawl,
        int $fetched,
        int $pagesTotal,
        int $unchanged,
        int $expanded
    ): void {
        $crawl->pages_fetched = $fetched;
        $crawl->pages_total = $pagesTotal;

        \Illuminate\Support\Facades\DB::table('site_audit_crawls')
            ->where('id', $crawl->id)
            ->update([
                'pages_fetched' => $fetched,
                'pages_total' => $pagesTotal,
                'updated_at' => now(),
                'progress_json' => \Illuminate\Support\Facades\DB::raw(
                    "JSON_SET(COALESCE(progress_json, '{}'),"
                    . " '$.engine_storage', 'file',"
                    . " '$.fetched', " . (int) $fetched . ","
                    . " '$.total', " . (int) $pagesTotal . ","
                    . " '$.pages_unchanged', " . (int) $unchanged . ","
                    . " '$.links_expanded', " . (int) $expanded . ","
                    . " '$.engine_resume.fetched', " . (int) $fetched . ","
                    . " '$.engine_resume.remaining', GREATEST(0, " . (int) $pagesTotal . " - " . (int) $fetched . ")"
                    . ")"
                ),
            ]);
    }

    private function engineStatePath(int $crawlId): string
    {
        $dir = storage_path('app/site-audit-engine');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir . '/crawl_' . $crawlId . '.json';
    }

    /**
     * urls_gz (~сотня KB–MB) не должен жить в progress_json во время fetch —
     * иначе каждый save/refresh таскает его из MySQL.
     */
    private function offloadSitemapUrlsGz(SiteAuditCrawl $crawl): void
    {
        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        $gz = $progress['sitemap']['urls_gz'] ?? null;
        if (! is_string($gz) || $gz === '') {
            return;
        }

        $dir = storage_path('app/site-audit-engine');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $path = $dir . '/crawl_' . (int) $crawl->id . '_sitemap_urls.b64';
        if (@file_put_contents($path, $gz) === false) {
            return;
        }
        unset($progress['sitemap']['urls_gz']);
        $progress['sitemap']['urls_gz_file'] = true;
        $crawl->progress_json = $progress;
    }

    public function hasEngineState(SiteAuditCrawl $crawl): bool
    {
        $path = $this->engineStatePath((int) $crawl->id);
        clearstatcache(true, $path);
        if (is_file($path)) {
            $size = @filesize($path);
            // filesize() может вернуть false при гонке — не считаем это «нет файла».
            if ($size === false || $size > 2) {
                return true;
            }
        }

        $engine = $crawl->progress_json['engine'] ?? null;

        return is_array($engine)
            && isset($engine['queue'], $engine['index'])
            && is_array($engine['queue']);
    }

    /**
     * Можно ли продолжить после failed/cancelled с сохранённой очередью.
     */
    public function canResume(SiteAuditCrawl $crawl): bool
    {
        if (! in_array($crawl->status, [
            SiteAuditCrawl::STATUS_FAILED,
            SiteAuditCrawl::STATUS_CANCELLED,
        ], true)) {
            return false;
        }

        if (! $this->hasEngineState($crawl)) {
            // Не вызываем tryRebuild здесь: rebuild писал pages_total = размер всего sitemap
            // при каждом открытии истории (canResume на строке). Rebuild — только в resume()/processBatch.
            if ((int) $crawl->pages_fetched < 1) {
                return false;
            }
            $fetched = (int) $crawl->pages_fetched;
            $planCap = $this->resumePlanCap($crawl, $fetched);
            if ($fetched >= $planCap) {
                return false;
            }
            // Не разжимаем sitemap в истории: достаточно url_count / seed из JSON_EXTRACT.
            $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
            $urlCount = (int) ($progress['sitemap']['url_count'] ?? 0);
            if ($urlCount <= 0 && isset($crawl->sitemap_url_count_raw)) {
                $urlCount = (int) $crawl->sitemap_url_count_raw;
            }
            if ($urlCount > 0) {
                return $urlCount > $fetched;
            }

            return $fetched < max(1, (int) $crawl->pages_total, $planCap);
        }

        $meta = $this->engineResumeMeta($crawl);
        if ($meta !== null) {
            // Не читаем crawl_N.json (может быть МБ) только ради кнопки «Возобновить».
            return (int) ($meta['remaining'] ?? 0) > 0;
        }

        $state = $this->loadEngineState($crawl);

        return count($state['queue']) > $state['index'];
    }

    /**
     * Если файл очереди потерян, а страницы уже скачаны — собрать remaining из sitemap − pages.
     */
    public function tryRebuildEngineStateForResume(SiteAuditCrawl $crawl): bool
    {
        if ($this->hasEngineState($crawl)) {
            return true;
        }
        if ((int) $crawl->pages_fetched < 1) {
            return false;
        }

        $sitemapUrls = SiteAuditSitemapProbe::urlsFromProgress($crawl);
        if ($sitemapUrls === []) {
            // urls_gz / файл карты могли пропасть вместе с engine — пересоберём sitemap.
            $project = SiteAuditProject::query()->find($crawl->project_id);
            if (! $project) {
                return false;
            }
            $seedLimit = max((int) $crawl->pages_limit, (int) ($crawl->progress_json['sitemap']['url_count'] ?? 0), 100);
            try {
                $meta = (new SiteAuditSitemapDiscovery())->discoverWithMeta($project->domain, $seedLimit);
                $sitemapUrls = is_array($meta['all_urls'] ?? null) ? $meta['all_urls'] : [];
            } catch (\Throwable $e) {
                Log::warning('SiteAudit resume rebuild: sitemap rediscover failed', [
                    'crawl_id' => $crawl->id,
                    'error' => $e->getMessage(),
                ]);

                return false;
            }
        }
        if ($sitemapUrls === []) {
            return false;
        }

        $done = SiteAuditPage::query()
            ->where('crawl_id', $crawl->id)
            ->pluck('url')
            ->all();
        $doneSet = array_fill_keys($done, true);

        $remaining = [];
        foreach ($sitemapUrls as $u) {
            $u = (string) $u;
            if ($u === '' || isset($doneSet[$u])) {
                continue;
            }
            $remaining[] = $u;
        }
        if ($remaining === []) {
            return false;
        }

        $fetched = max((int) $crawl->pages_fetched, count($done));
        $planCap = $this->resumePlanCap($crawl, $fetched);
        $remaining = array_slice($remaining, 0, max(0, $planCap - count($done)));
        if ($remaining === []) {
            return false;
        }

        $seen = [];
        foreach ($done as $u) {
            $seen[(string) $u] = true;
        }
        foreach ($remaining as $u) {
            $seen[$u] = true;
        }

        $origins = [];
        foreach ($remaining as $u) {
            $origins[$u] = ['via' => 'sitemap', 'from' => null];
        }

        $ok = $this->persistEngineState($crawl, $remaining, 0, $fetched, $seen, 0, 0, $origins);
        if (! $ok) {
            return false;
        }
        $crawl->save();

        Log::warning('SiteAudit engine state rebuilt for resume', [
            'crawl_id' => $crawl->id,
            'fetched' => $fetched,
            'remaining' => count($remaining),
            'plan_cap' => $planCap,
        ]);

        return $this->hasEngineState($crawl);
    }

    /**
     * Потолок плана при rebuild: не раздувать до всего sitemap, если изначально был seed/лимит прогресса.
     */
    private function resumePlanCap(SiteAuditCrawl $crawl, int $fetched): int
    {
        $limit = max(1, (int) $crawl->pages_limit);
        $existingTotal = max(0, (int) $crawl->pages_total);
        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        $seedCount = (int) ($progress['sitemap']['seed_count'] ?? 0);
        $urlCount = (int) ($progress['sitemap']['url_count'] ?? 0);
        if ($seedCount <= 0 && isset($crawl->sitemap_seed_count_raw)) {
            $seedCount = (int) $crawl->sitemap_seed_count_raw;
        }
        if ($urlCount <= 0 && isset($crawl->sitemap_url_count_raw)) {
            $urlCount = (int) $crawl->sitemap_url_count_raw;
        }

        $plan = $limit;
        if ($existingTotal > 0) {
            $plan = min($plan, max($existingTotal, $fetched));
        }
        // Уже раздутый total (= весь sitemap) — откатываем к seed.
        if ($seedCount > 0 && $urlCount > 0 && $existingTotal >= $urlCount) {
            $plan = min($limit, max($seedCount, $fetched));
        } elseif ($seedCount > 0 && $existingTotal <= 0) {
            $plan = min($plan, max($seedCount, $fetched));
        }

        return max($fetched, $plan);
    }

    /**
     * @return array{remaining?:int,fetched?:int}|null
     */
    private function engineResumeMeta(SiteAuditCrawl $crawl): ?array
    {
        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : null;
        if ($progress === null && isset($crawl->engine_resume_raw)) {
            $raw = $crawl->engine_resume_raw;
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);

                return is_array($decoded) ? $decoded : null;
            }
            if (is_array($raw)) {
                return $raw;
            }
        }
        if (! is_array($progress)) {
            return null;
        }
        $meta = $progress['engine_resume'] ?? null;

        return is_array($meta) ? $meta : null;
    }

    /**
     * Снять finished и снова поставить в очередь (тот же crawl_id, тот же прогресс).
     * Важно: не через Discover — иначе при гонке hasEngineState=false discover затрёт очередь.
     */
    public function resume(SiteAuditCrawl $crawl): SiteAuditCrawl
    {
        if (! $this->canResume($crawl)) {
            throw new \RuntimeException('Нет сохранённого прогресса для продолжения — только полный повтор');
        }

        if (! $this->hasEngineState($crawl)) {
            if (! $this->tryRebuildEngineStateForResume($crawl)) {
                throw new \RuntimeException('Нет сохранённого прогресса для продолжения — только полный повтор');
            }
        }

        Cache::forget('site_audit_engine_miss_' . $crawl->id);
        Cache::forget('site_audit_crawl_tick_' . $crawl->id);
        Cache::forget('site_audit_continue_sched_' . $crawl->id);

        $crawl->status = SiteAuditCrawl::STATUS_FETCHING;
        $crawl->error = null;
        $crawl->finished_at = null;
        $crawl->save();

        $this->pushContinueJob((int) $crawl->id, 1);

        return $crawl->fresh() ?: $crawl;
    }

    public static function clearStoredState(int $crawlId): void
    {
        $dir = storage_path('app/site-audit-engine');
        foreach ([
            $dir . '/crawl_' . $crawlId . '.json',
            $dir . '/crawl_' . $crawlId . '.json.tmp',
            $dir . '/crawl_' . $crawlId . '_sitemap_urls.b64',
        ] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * @return array{queue: string[], index: int, fetched: int, seen: array<string,bool>, unchanged: int, expanded: int, origins: array<string, array{via:string,from:?string}>}
     */
    private function loadEngineState(SiteAuditCrawl $crawl): array
    {
        $path = $this->engineStatePath((int) $crawl->id);
        $engine = null;
        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded)) {
                $engine = $decoded;
            }
        }

        // миграция со старого огромного progress_json.engine
        if ($engine === null) {
            $engine = is_array($crawl->progress_json['engine'] ?? null) ? $crawl->progress_json['engine'] : [];
        }

        $queue = array_values(array_map('strval', is_array($engine['queue'] ?? null) ? $engine['queue'] : []));
        $index = max(0, (int) ($engine['index'] ?? 0));
        $fetched = (int) ($engine['fetched'] ?? $index);
        if ($index > 0 && $index <= count($queue)) {
            // компактим «уже обойдённые» URL из старого формата
            $queue = array_values(array_slice($queue, $index));
            $index = 0;
        } elseif ($index > count($queue)) {
            $index = 0;
        }

        $seenList = is_array($engine['seen'] ?? null) ? $engine['seen'] : [];
        $seen = [];
        foreach ($seenList as $u) {
            $seen[(string) $u] = true;
        }
        foreach ($queue as $u) {
            $seen[$u] = true;
        }

        $origins = [];
        $rawOrigins = is_array($engine['origins'] ?? null) ? $engine['origins'] : [];
        foreach ($rawOrigins as $u => $row) {
            if (! is_array($row)) {
                continue;
            }
            $via = (string) ($row['via'] ?? '');
            if ($via === '') {
                continue;
            }
            $from = isset($row['from']) && is_string($row['from']) && $row['from'] !== ''
                ? $row['from']
                : null;
            $origins[(string) $u] = ['via' => $via, 'from' => $from];
        }

        return [
            'queue' => $queue,
            'index' => $index,
            'fetched' => max(0, $fetched),
            'seen' => $seen,
            'unchanged' => (int) ($engine['unchanged'] ?? 0),
            'expanded' => (int) ($engine['expanded'] ?? 0),
            'origins' => $origins,
        ];
    }

    /**
     * @param string[] $queue
     * @param array<string,bool> $seen
     * @param array<string, array{via:string,from:?string}> $origins
     * @return bool false — файл очереди не записался
     */
    private function persistEngineState(
        SiteAuditCrawl $crawl,
        array $queue,
        int $index,
        int $fetched,
        array $seen,
        int $unchanged,
        int $expanded,
        array $origins = []
    ): bool {
        $remaining = array_values(array_slice($queue, max(0, $index)));
        $originsPersist = [];
        foreach ($remaining as $u) {
            if (isset($origins[$u]) && is_array($origins[$u])) {
                $originsPersist[$u] = [
                    'via' => (string) ($origins[$u]['via'] ?? ''),
                    'from' => isset($origins[$u]['from']) && is_string($origins[$u]['from']) && $origins[$u]['from'] !== ''
                        ? $origins[$u]['from']
                        : null,
                ];
            }
        }
        $payload = [
            'queue' => $remaining,
            'index' => 0,
            'fetched' => $fetched,
            'seen' => array_keys($seen),
            'unchanged' => $unchanged,
            'expanded' => $expanded,
            'origins' => $originsPersist,
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || $json === '') {
            Log::warning('SiteAudit engine state encode failed', [
                'crawl_id' => $crawl->id,
                'remaining' => count($remaining),
            ]);

            return false;
        }

        $path = $this->engineStatePath((int) $crawl->id);
        // Уникальный tmp: два воркера иначе перетирают один crawl_N.json.tmp.
        $tmp = $path . '.tmp.' . getmypid() . '.' . str_replace('.', '', uniqid('', true));
        $written = @file_put_contents($tmp, $json);
        if ($written === false || $written < 3) {
            @unlink($tmp);
            Log::warning('SiteAudit engine state write failed', [
                'crawl_id' => $crawl->id,
                'bytes' => $written,
            ]);

            return false;
        }
        if (! @rename($tmp, $path)) {
            @unlink($tmp);
            Log::warning('SiteAudit engine state rename failed', [
                'crawl_id' => $crawl->id,
            ]);

            return false;
        }
        clearstatcache(true, $path);
        if (! is_file($path) || filesize($path) < 3) {
            Log::warning('SiteAudit engine state missing after rename', [
                'crawl_id' => $crawl->id,
            ]);

            return false;
        }

        // убираем blob очереди из MySQL — иначе 6MB UPDATE на каждый тик
        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        unset($progress['engine']);
        $progress['engine_storage'] = 'file';
        $progress['engine_resume'] = [
            'remaining' => count($remaining),
            'fetched' => $fetched,
        ];
        $progress['fetched'] = $fetched;
        $progress['total'] = $this->cappedPagesTotal($crawl, $fetched, count($remaining), count($seen));
        $progress['pages_unchanged'] = $unchanged;
        $progress['links_expanded'] = $expanded;
        $crawl->progress_json = $progress;
        $crawl->pages_fetched = $fetched;
        $crawl->pages_total = (int) $progress['total'];

        return true;
    }

    /**
     * Знаменатель прогресса: не больше pages_limit и не прыгает к размеру всего sitemap.
     */
    private function cappedPagesTotal(SiteAuditCrawl $crawl, int $fetched, int $remainingCount, int $seenCount): int
    {
        $limit = max(1, (int) $crawl->pages_limit);
        $total = min($limit, max($seenCount, $fetched + $remainingCount, $fetched));

        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        $seedCount = (int) ($progress['sitemap']['seed_count'] ?? 0);
        $urlCount = (int) ($progress['sitemap']['url_count'] ?? 0);
        $prev = max(0, (int) $crawl->pages_total);

        // Артефакт resume-rebuild / uncapped seen: total ≈ весь sitemap.
        if ($urlCount > 0 && $seedCount > 0 && $total >= $urlCount) {
            $safe = max($fetched, $seedCount);
            if ($prev > 0 && $prev < $urlCount) {
                $safe = max($safe, $prev);
            }
            $queuePlan = $fetched + $remainingCount;
            if ($queuePlan > $safe && $queuePlan < $urlCount) {
                $safe = $queuePlan;
            }
            $total = min($total, $safe);
        }

        return max($fetched, $total);
    }

    private function clearEngineState(SiteAuditCrawl $crawl): void
    {
        $path = $this->engineStatePath((int) $crawl->id);
        if (is_file($path)) {
            @unlink($path);
        }
        $tmp = $path . '.tmp';
        if (is_file($tmp)) {
            @unlink($tmp);
        }

        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        unset($progress['engine'], $progress['engine_storage'], $progress['engine_resume']);
        $crawl->progress_json = $progress;
    }
}