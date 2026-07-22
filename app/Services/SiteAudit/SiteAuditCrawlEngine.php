<?php

namespace App\Services\SiteAudit;

use App\Jobs\SiteAudit\AggregateSiteAuditCrawlJob;
use App\SiteAuditCrawl;
use App\SiteAuditProject;

/**
 * Полный прогон краула: sitemap + дообход по внутренним ссылкам до лимита.
 */
class SiteAuditCrawlEngine
{
    public function run(SiteAuditCrawl $crawl): SiteAuditCrawl
    {
        $project = SiteAuditProject::query()->find($crawl->project_id);
        if (! $project) {
            $crawl->status = SiteAuditCrawl::STATUS_FAILED;
            $crawl->error = 'Project not found';
            $crawl->finished_at = now();
            $crawl->save();

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
        $host = SiteAuditUrlNormalizer::hostOf('https://' . $project->domain) ?: $project->domain;

        $patterns = SiteAuditUrlFilter::parsePatterns(
            $settings['exclude_patterns'] ?? $project->setting('exclude_patterns', [])
        );

        $urlOpts = SiteAuditUrlNormalizer::optionsFromSettings($settings, $project->domain);

        $seed = [];
        $manual = $project->setting('seed_urls', []);
        if (is_array($manual)) {
            foreach ($manual as $u) {
                $norm = SiteAuditUrlNormalizer::normalize((string) $u, $project->domain, $urlOpts);
                if ($norm) {
                    $seed[$norm] = true;
                }
            }
        }

        // корень сайта всегда в сидах
        $home = SiteAuditUrlNormalizer::normalize('https://' . $project->domain . '/', $project->domain, $urlOpts);
        if ($home) {
            $seed[$home] = true;
        }

        try {
            $discovered = (new SiteAuditSitemapProbe())->run($crawl, $project->domain, $limit);
            $crawl->refresh();
            foreach ($discovered['seed_urls'] as $u) {
                $norm = SiteAuditUrlNormalizer::normalize($u, $project->domain, $urlOpts) ?: $u;
                $seed[$norm] = true;
            }
        } catch (\Throwable $e) {
            if (! $seed) {
                $crawl->status = SiteAuditCrawl::STATUS_FAILED;
                $crawl->error = 'Discovery failed: ' . $e->getMessage();
                $crawl->finished_at = now();
                $crawl->save();

                return $crawl;
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

        // стартовый набор не больше лимита; дальше добор по ссылкам
        $queue = array_slice($queue, 0, $limit);

        $seen = [];
        foreach ($queue as $u) {
            $seen[$u] = true;
        }

        $crawl->pages_total = count($queue);
        $crawl->pages_fetched = 0;
        $crawl->status = SiteAuditCrawl::STATUS_FETCHING;
        $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
        $progress['fetched'] = 0;
        $progress['total'] = count($queue);
        $progress['links_expanded'] = 0;
        $crawl->progress_json = $progress;
        $crawl->save();

        if (! $queue) {
            $crawl->status = SiteAuditCrawl::STATUS_FAILED;
            $crawl->error = 'No URLs discovered';
            $crawl->finished_at = now();
            $crawl->save();

            return $crawl;
        }

        $processor = new SiteAuditPageProcessor();
        $i = 0;
        $expanded = 0;

        while ($i < count($queue)) {
            $url = $queue[$i];
            $i++;

            try {
                $out = $processor->process($crawl->id, $url, $host, $settings);
            } catch (\Throwable $e) {
                \Log::warning('SiteAudit page process failed', [
                    'crawl_id' => $crawl->id,
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
                $out = ['internal_links' => []];
            }

            $crawl->pages_fetched = $i;
            $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
            $progress['fetched'] = $i;
            $progress['total'] = count($queue);
            $crawl->pages_total = count($queue);
            $crawl->progress_json = $progress;
            $crawl->save();

            if (! empty($out['internal_links'])) {
                foreach ($out['internal_links'] as $link) {
                    if (isset($seen[$link])) {
                        continue;
                    }
                    if (count($queue) >= $limit) {
                        break;
                    }
                    if ($patterns && SiteAuditUrlFilter::isExcluded($link, $patterns)) {
                        continue;
                    }
                    $groups = $crawl->progress_json['robots']['groups'] ?? null;
                    if (is_array($groups) && ! (new SiteAuditRobotsTxt())->isPathAllowed($groups, $link)) {
                        continue;
                    }
                    $seen[$link] = true;
                    $queue[] = $link;
                    $expanded++;
                }
                $progress = is_array($crawl->progress_json) ? $crawl->progress_json : [];
                $progress['links_expanded'] = $expanded;
                $progress['total'] = count($queue);
                $crawl->pages_total = count($queue);
                $crawl->progress_json = $progress;
                $crawl->save();
            }
        }

        SiteAuditUserAgentSession::clear($crawl->id);
        (new AggregateSiteAuditCrawlJob($crawl->id))->handle();

        return $crawl->fresh();
    }
}
