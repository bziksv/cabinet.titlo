<?php

namespace App\Services\SiteAudit;

use App\SiteAuditCrawl;
use App\SiteAuditProject;
use App\Support\SiteAuditLimits;
use App\User;
use RuntimeException;

class SiteAuditCrawlStarter
{
    /**
     * @param  bool  $skipActiveCheck  устарело: оставлен для совместимости вызовов, не используется
     */
    public function start(
        User $user,
        string $domain,
        array $settings = [],
        bool $dispatch = true,
        bool $force = false,
        bool $skipActiveCheck = false
    ): SiteAuditCrawl {
        $bypassLimits = $force
            || (bool) config('site_audit.bypass_limits', false);

        if (! $bypassLimits && ! SiteAuditLimits::canStartCrawl($user)) {
            throw new RuntimeException('Исчерпан месячный лимит проверок аудита сайта');
        }

        // Активный проверка у пользователя больше не блокирует запуск:
        // новый уходит в queued_wait и стартует, когда слот/предыдущий освободятся.
        // (Раньше одиночный домен падал с ошибкой, пакет из 2+ — нет. Путаница.)

        $domain = preg_replace('#^https?://#i', '', trim($domain));
        $domain = rtrim($domain, '/');
        if ($domain === '') {
            throw new RuntimeException('Укажите домен');
        }

        $settings = SiteAuditCrawlOptions::normalize($settings);
        if ($bypassLimits) {
            $settings['concurrency'] = max(1, min(
                (int) config('site_audit.max_concurrency', 8),
                max(1, (int) ($settings['concurrency'] ?? 1))
            ));
        } else {
            $settings['concurrency'] = SiteAuditLimits::resolveConcurrency($user, $settings['concurrency'] ?? 1);
        }

        if (! $bypassLimits && ! SiteAuditLimits::canCreateProject($user, $domain)) {
            $lim = SiteAuditLimits::projectsLimit($user);
            throw new RuntimeException(
                "Лимит проектов аудита сайта исчерпан ({$lim}). Удалите старый проект или увеличьте тариф."
            );
        }

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

        $tariffMax = SiteAuditLimits::pagesPerCrawlLimit($user);
        if ($bypassLimits && ! empty($settings['pages_limit'])) {
            // local / force: можно задать свой лимит (в т.ч. выше тарифа для теста)
            $pagesLimit = max(1, (int) $settings['pages_limit']);
        } else {
            $pagesLimit = SiteAuditLimits::resolvePagesLimit(
                $user,
                $settings['pages_limit'] ?? null
            );
        }
        // страховка: на проде не выше тарифа
        if (! $bypassLimits) {
            $pagesLimit = min($pagesLimit, $tariffMax);
        }

        // Сначала в глобальную очередь ожидания; tryDispatch поднимет, если слот свободен.
        $crawl = SiteAuditCrawl::query()->create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'status' => SiteAuditCrawl::STATUS_QUEUED_WAIT,
            'pages_limit' => $pagesLimit,
            'save_html' => $settings['save_html'] ?? 'off',
            'progress_json' => [
                'settings' => [
                    'crawl_speed' => $settings['crawl_speed'],
                    'rps' => $settings['rps'],
                    'concurrency' => (int) ($settings['concurrency'] ?? 1),
                    'exclude_patterns' => $settings['exclude_patterns'] ?? [],
                    'virtual_robots' => $settings['virtual_robots'] ?? '',
                    'html_checker' => $settings['html_checker'] ?? SiteAuditHtmlChecker::defaultChecker(),
                    'unify_www' => true,
                    'force_https' => true,
                    'strip_trailing_slash' => false,
                    'check_broken_links' => true,
                    'pages_only' => ! empty($settings['pages_only']),
                    'extra_hosts' => $settings['extra_hosts'] ?? [],
                ],
            ],
            'started_at' => null,
        ]);

        if ($dispatch) {
            SiteAuditGlobalCap::tryDispatch($crawl);
            $crawl->refresh();
        }

        return $crawl;
    }
}
