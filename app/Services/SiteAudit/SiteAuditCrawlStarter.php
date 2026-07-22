<?php

namespace App\Services\SiteAudit;

use App\Jobs\SiteAudit\DiscoverSiteAuditUrlsJob;
use App\SiteAuditCrawl;
use App\SiteAuditProject;
use App\Support\SiteAuditLimits;
use App\User;
use RuntimeException;

class SiteAuditCrawlStarter
{
    public function start(User $user, string $domain, array $settings = [], bool $dispatch = true, bool $force = false): SiteAuditCrawl
    {
        // Пока модуль в локальной отладке — не упираемся в тариф (UI ещё сырой).
        $bypassLimits = $force
            || app()->environment('local')
            || (bool) config('site_audit.bypass_limits', false);

        if (! $bypassLimits && ! SiteAuditLimits::canStartCrawl($user)) {
            throw new RuntimeException('Site audit crawl monthly limit reached');
        }

        if (! $bypassLimits && SiteAuditLimits::hasActiveCrawl($user)) {
            throw new RuntimeException('Another site audit crawl is already running');
        }

        $domain = preg_replace('#^https?://#i', '', trim($domain));
        $domain = rtrim($domain, '/');
        if ($domain === '') {
            throw new RuntimeException('Domain is required');
        }

        $settings = SiteAuditCrawlOptions::normalize($settings);

        $project = SiteAuditProject::query()->firstOrCreate(
            ['user_id' => $user->id, 'domain' => $domain],
            [
                'name' => $settings['name'] ?? $domain,
                'settings_json' => $settings,
            ]
        );

        if ($settings) {
            $project->settings_json = array_merge($project->settings_json ?? [], $settings);
            $project->save();
        }

        $pagesLimit = SiteAuditLimits::pagesPerCrawlLimit($user) ?? 500;
        if ($force || app()->environment('local') || (bool) config('site_audit.bypass_limits', false)) {
            if (! empty($settings['pages_limit'])) {
                $pagesLimit = (int) $settings['pages_limit'];
            }
        }

        $crawl = SiteAuditCrawl::query()->create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'status' => SiteAuditCrawl::STATUS_QUEUED,
            'pages_limit' => $pagesLimit,
            'save_html' => $settings['save_html'] ?? 'off',
            'progress_json' => [
                'settings' => [
                    'crawl_speed' => $settings['crawl_speed'],
                    'rps' => $settings['rps'],
                    'exclude_patterns' => $settings['exclude_patterns'] ?? [],
                    'unify_www' => true,
                    'force_https' => true,
                    'strip_trailing_slash' => true,
                    'check_broken_links' => true,
                ],
            ],
            'started_at' => now(),
        ]);

        if ($dispatch) {
            DiscoverSiteAuditUrlsJob::dispatch($crawl->id);
        }

        return $crawl;
    }
}
