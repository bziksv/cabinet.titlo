<?php

namespace App\Support;

use App\ClusterResults;
use App\DomainInformation;
use App\DomainMonitoring;
use App\DomainRecordsHistory;
use App\EseninTextCheckSession;
use App\HomeUserArchivedSite;
use App\IndexCheckHistory;
use App\HomeUserSitesPreference;
use App\GoogleSearchConsoleDomainProperty;
use App\YandexMetrikaDomainCounter;
use App\YandexWebmasterDomainHost;
use App\MonitoringProject;
use App\ProjectRelevanceHistory;
use App\SiteAuditProject;
use App\SeoChecklist\SeoChecklistProject;
use App\SeoReports\SeoReportProject;
use App\Services\YandexMetrika\YandexMetrikaService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Сводка сайтов пользователя для главной (cards v2): домен → участие во всех модулях.
 */
class HomeUserSites
{
    /** Лимит выборки из каждого модуля (защита от тяжёлых запросов). */
    private const PER_SOURCE_LIMIT = 250;

    /** Максимум доменов в матрице на главной; дальше — усечение с подсказкой. */
    private const RESULT_LIMIT = 500;

    /**
     * Каталог колонок матрицы сайтов.
     * kind=module — участие в модуле; kind=integration — внешняя связь (Метрика и т.п.).
     * supports_sync — модуль/интеграция участвует в будущей синхронизации с Яндекс.Метрикой.
     *
     * @return array<int, array{key: string, title: string, short: string, create_url: string, kind: string, supports_sync: bool}>
     */
    public static function moduleCatalog(): array
    {
        return [
            [
                'key' => 'analyze-relevance',
                'title' => __('Page Relevance Analyzer'),
                'short' => __('Relevance short'),
                'create_url' => url('/analyze-relevance'),
                'kind' => 'module',
                'supports_sync' => false,
            ],
            [
                'key' => 'monitoring',
                'title' => __('Position monitoring'),
                'short' => __('Monitoring short'),
                'create_url' => url('/monitoring-v2'),
                'kind' => 'module',
                // Закладываем синхронизацию с Яндекс.Метрикой (позиции ↔ счётчик).
                'supports_sync' => true,
            ],
            [
                'key' => 'yandex-metrika',
                'title' => __('Yandex Metrika'),
                'short' => __('Metrika short'),
                'create_url' => '#metrika',
                'kind' => 'integration',
                'supports_sync' => true,
            ],
            [
                'key' => 'yandex-webmaster',
                'title' => __('Yandex Webmaster'),
                'short' => __('Webmaster short'),
                'create_url' => '#webmaster',
                'kind' => 'integration',
                'supports_sync' => true,
            ],
            [
                'key' => 'google-search-console',
                'title' => __('Google Search Console'),
                'short' => __('GSC short'),
                'create_url' => '#gsc',
                'kind' => 'integration',
                'supports_sync' => true,
            ],
            [
                'key' => 'site-audit',
                'title' => __('Site audit'),
                'short' => __('Audit short'),
                'create_url' => url('/site-audit'),
                'kind' => 'module',
                // Закладываем синхронизацию с Яндекс.Метрикой (аудит ↔ счётчик).
                'supports_sync' => true,
            ],
            [
                'key' => 'checklist',
                'title' => \App\SeoChecklist\SeoChecklistUserPreference::moduleTitleFor(
                    Auth::check() ? (int) Auth::id() : null
                ),
                'short' => __('Checklist short'),
                'create_url' => url('/checklist'),
                'kind' => 'module',
                'supports_sync' => false,
            ],
            [
                'key' => 'reports',
                'title' => __('Reports'),
                'short' => __('SEO reports short'),
                'create_url' => url('/reports'),
                'kind' => 'module',
                'supports_sync' => false,
            ],
            [
                'key' => 'site-monitoring',
                'title' => __('Site monitoring'),
                'short' => __('Uptime short'),
                'create_url' => url('/site-monitoring'),
                'kind' => 'module',
                'supports_sync' => false,
            ],
            [
                'key' => 'domain-information',
                'title' => __('Tracking the domain registration period'),
                'short' => __('Domain expiry short'),
                'create_url' => url('/domain-information'),
                'kind' => 'module',
                'supports_sync' => false,
            ],
            [
                'key' => 'domain-records',
                'title' => __('Domain records'),
                'short' => __('DNS short'),
                'create_url' => url('/domain-records'),
                'kind' => 'module',
                'supports_sync' => false,
            ],
            [
                'key' => 'cluster',
                'title' => __('Cluster'),
                'short' => __('Cluster short'),
                'create_url' => url('/cluster'),
                'kind' => 'module',
                'supports_sync' => false,
            ],
            [
                'key' => 'index-check',
                'title' => __('Index check'),
                'short' => __('Index short'),
                'create_url' => url('/index-check'),
                'kind' => 'module',
                'supports_sync' => false,
            ],
            [
                'key' => 'esenin-text-check',
                'title' => __('Esenin text check'),
                'short' => __('Esenin short'),
                'create_url' => url('/esenin-text-check'),
                'kind' => 'module',
                'supports_sync' => false,
            ],
        ];
    }

    /**
     * @return array{sites: array<int, array<string, mixed>>, total: int, shown: int, catalog: array<int, array<string, mixed>>}
     */
    public static function forCurrentUser(bool $withVisits = true): array
    {
        if (!Auth::check()) {
            return self::emptyPayload();
        }

        return self::forUser((int) Auth::id(), $withVisits);
    }

    /**
     * Посещаемость Метрики для доменов (для фоновой догрузки на главной).
     *
     * @param list<string>|null $domains null — все домены с привязкой счётчика
     * @return array{by_domain: array<string, array<string, mixed>>, meta: ?array<string, mixed>}
     */
    public static function visitsForUser(int $userId, ?array $domains = null): array
    {
        if ($userId < 1) {
            return ['by_domain' => [], 'meta' => null];
        }

        if ($domains === null) {
            $domains = [];
            YandexMetrikaDomainCounter::forUser($userId)->each(static function ($row) use (&$domains): void {
                $domain = self::normalizeDomain((string) ($row->domain ?? ''));
                if ($domain !== '') {
                    $domains[] = $domain;
                }
            });
            $domains = array_values(array_unique($domains));
        } else {
            $normalized = [];
            foreach ($domains as $raw) {
                $domain = self::normalizeDomain((string) $raw);
                if ($domain !== '') {
                    $normalized[] = $domain;
                }
            }
            $domains = array_values(array_unique($normalized));
        }

        $sites = [];
        foreach ($domains as $domain) {
            $sites[] = ['domain' => $domain];
        }

        return self::loadPilotVisits($userId, $sites);
    }

    /**
     * @return array{sites: array<int, array<string, mixed>>, archived: array<int, array<string, mixed>>, total: int, archived_total: int, shown: int, catalog: array<int, array<string, mixed>>, modules_total: int}
     */
    public static function forUser(int $userId, bool $withVisits = true): array
    {
        if ($userId < 1) {
            return self::emptyPayload();
        }

        // Без визитов — кэш на пару минут (главная бьёт сюда фоном; remote DB дорогая).
        if (! $withVisits) {
            $cached = Cache::remember(self::sitesCacheKey($userId), 180, static function () use ($userId) {
                return self::buildForUser($userId, false);
            });

            return is_array($cached) ? $cached : self::buildForUser($userId, false);
        }

        return self::buildForUser($userId, true);
    }

    public static function forgetSitesCache(int $userId): void
    {
        if ($userId > 0) {
            Cache::forget(self::sitesCacheKey($userId));
        }
    }

    private static function sitesCacheKey(int $userId): string
    {
        return 'home.user_sites.v2.' . $userId;
    }

    /**
     * @return array{sites: array<int, array<string, mixed>>, archived: array<int, array<string, mixed>>, total: int, archived_total: int, shown: int, catalog: array<int, array<string, mixed>>, modules_total: int}
     */
    private static function buildForUser(int $userId, bool $withVisits = true): array
    {
        $catalog = self::moduleCatalog();
        if ($userId < 1) {
            return self::emptyPayload($catalog);
        }

        $catalogByKey = [];
        foreach ($catalog as $item) {
            $catalogByKey[$item['key']] = $item;
        }

        /** @var array<string, array{domain: string, present: array<string, array<string, mixed>>, last_at: ?Carbon}> $byDomain */
        $byDomain = [];

        $add = static function (
            string $rawHost,
            string $moduleKey,
            string $openUrl,
            $at,
            string $label = '',
            array $extra = []
        ) use (&$byDomain, $catalogByKey): void {
            if (!isset($catalogByKey[$moduleKey])) {
                return;
            }

            $domain = self::normalizeDomain($rawHost);
            if ($domain === '') {
                return;
            }

            $ts = self::toCarbon($at);
            if (!isset($byDomain[$domain])) {
                $byDomain[$domain] = [
                    'domain' => $domain,
                    'present' => [],
                    'last_at' => null,
                ];
            }

            $prev = $byDomain[$domain]['present'][$moduleKey] ?? null;
            if ($prev === null || ($ts && (!$prev['at'] || $ts->gt($prev['at'])))) {
                $byDomain[$domain]['present'][$moduleKey] = array_merge([
                    'url' => $openUrl,
                    'label' => $label,
                    'at' => $ts,
                ], $extra);
            }

            if ($ts && (!$byDomain[$domain]['last_at'] || $ts->gt($byDomain[$domain]['last_at']))) {
                $byDomain[$domain]['last_at'] = $ts;
            }
        };

        try {
            self::collectRelevance($userId, $add);
            self::collectMonitoring($userId, $add);
            self::collectSiteAudit($userId, $add);
            self::collectSeoChecklist($userId, $add);
            self::collectSeoReports($userId, $add);
            self::collectDomainInformation($userId, $add);
            self::collectSiteMonitoring($userId, $add);
            self::collectCluster($userId, $add);
            self::collectDomainRecords($userId, $add);
            self::collectIndexCheck($userId, $add);
            self::collectEsenin($userId, $add);
            self::collectYandexMetrika($userId, $add);
            self::collectYandexWebmaster($userId, $add);
            self::collectGoogleSearchConsole($userId, $add);
        } catch (Throwable $e) {
            report($e);
        }

        // Если к домену привязан счётчик — помечаем sync-модули как синхронизированные.
        foreach ($byDomain as $domain => &$domainRow) {
            if (!isset($domainRow['present']['yandex-metrika'])) {
                continue;
            }
            foreach (['monitoring', 'site-audit'] as $syncKey) {
                if (!isset($domainRow['present'][$syncKey])) {
                    continue;
                }
                $domainRow['present'][$syncKey]['synced'] = true;
            }
        }
        unset($domainRow);

        $sites = array_values($byDomain);
        usort($sites, static function ($a, $b) {
            $ta = $a['last_at'] ? $a['last_at']->getTimestamp() : 0;
            $tb = $b['last_at'] ? $b['last_at']->getTimestamp() : 0;
            if ($ta === $tb) {
                return strcmp($a['domain'], $b['domain']);
            }

            return $tb <=> $ta;
        });

        $archivedMap = HomeUserArchivedSite::domainMapForUser($userId, HomeUserArchivedSite::KIND_ARCHIVED);
        $hiddenMap = HomeUserArchivedSite::domainMapForUser($userId, HomeUserArchivedSite::KIND_HIDDEN);
        $activeRaw = [];
        $archivedRaw = [];
        $hiddenRaw = [];
        foreach ($sites as $site) {
            $domain = $site['domain'];
            if (isset($archivedMap[$domain])) {
                $archivedRaw[] = $site;
            } elseif (isset($hiddenMap[$domain])) {
                $hiddenRaw[] = $site;
            } else {
                $activeRaw[] = $site;
            }
        }

        $total = count($activeRaw);
        $archivedTotal = count($archivedRaw);
        $hiddenTotal = count($hiddenRaw);
        $activeRaw = array_slice($activeRaw, 0, self::RESULT_LIMIT);
        $archivedRaw = array_slice($archivedRaw, 0, self::RESULT_LIMIT);
        $hiddenRaw = array_slice($hiddenRaw, 0, self::RESULT_LIMIT);
        $modulesTotal = self::countModuleColumns($catalog);

        $active = self::hydrateSitesMatrix($activeRaw, $catalog, $modulesTotal);
        $archived = self::hydrateSitesMatrix($archivedRaw, $catalog, $modulesTotal);
        $hidden = self::hydrateSitesMatrix($hiddenRaw, $catalog, $modulesTotal);

        $visitsMeta = null;
        if ($withVisits) {
            $visitsLoaded = self::loadPilotVisits($userId, array_merge($active, $archived, $hidden));
            $active = self::attachVisits($active, $visitsLoaded['by_domain']);
            $archived = self::attachVisits($archived, $visitsLoaded['by_domain']);
            $hidden = self::attachVisits($hidden, $visitsLoaded['by_domain']);
            $visitsMeta = $visitsLoaded['meta'];
        }

        return [
            'sites' => $active,
            'archived' => $archived,
            'hidden' => $hidden,
            'total' => $total,
            'archived_total' => $archivedTotal,
            'hidden_total' => $hiddenTotal,
            'shown' => count($active),
            'catalog' => $catalog,
            'modules_total' => $modulesTotal,
            'visits_meta' => $visitsMeta,
            'visits_deferred' => ! $withVisits,
            'columns' => HomeUserSitesPreference::columnsForUser($userId),
        ];
    }

    /**
     * @param array<int, array<string, mixed>>|null $catalog
     * @return array{sites: array<int, array<string, mixed>>, archived: array<int, array<string, mixed>>, hidden: array<int, array<string, mixed>>, total: int, archived_total: int, hidden_total: int, shown: int, catalog: array<int, array<string, mixed>>, modules_total: int}
     */
    private static function emptyPayload(?array $catalog = null): array
    {
        $catalog = $catalog ?? self::moduleCatalog();

        return [
            'sites' => [],
            'archived' => [],
            'hidden' => [],
            'total' => 0,
            'archived_total' => 0,
            'hidden_total' => 0,
            'shown' => 0,
            'catalog' => $catalog,
            'modules_total' => self::countModuleColumns($catalog),
            'visits_meta' => null,
            'visits_deferred' => false,
            'columns' => HomeUserSitesPreference::columnsForUser(Auth::check() ? (int) Auth::id() : 0),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $sites
     * @param array<int, array<string, mixed>> $catalog
     * @return array<int, array<string, mixed>>
     */
    private static function hydrateSitesMatrix(array $sites, array $catalog, int $modulesTotal): array
    {
        $moduleKeys = [];
        foreach ($catalog as $catalogItem) {
            if (($catalogItem['kind'] ?? 'module') === 'module') {
                $moduleKeys[$catalogItem['key']] = true;
            }
        }

        foreach ($sites as &$site) {
            $matrix = [];
            $presentCount = 0;
            foreach ($catalog as $catalogItem) {
                $key = $catalogItem['key'];
                $kind = (string) ($catalogItem['kind'] ?? 'module');
                $supportsSync = (bool) ($catalogItem['supports_sync'] ?? false);
                $info = $site['present'][$key] ?? null;
                $present = $info !== null;
                $synced = $kind === 'integration'
                    ? $present
                    : ($supportsSync ? (bool) ($info['synced'] ?? false) : null);

                if ($present && isset($moduleKeys[$key])) {
                    $presentCount++;
                }

                $matrix[] = [
                    'key' => $key,
                    'title' => $catalogItem['title'],
                    'short' => $catalogItem['short'],
                    'kind' => $kind,
                    'supports_sync' => $supportsSync,
                    'present' => $present,
                    'synced' => $synced,
                    'url' => $present ? $info['url'] : $catalogItem['create_url'],
                    'label' => $present ? (string) ($info['label'] ?? '') : '',
                    'counter_id' => $present && isset($info['counter_id'])
                        ? (int) $info['counter_id']
                        : null,
                    'host_id' => $present && isset($info['host_id'])
                        ? (string) $info['host_id']
                        : null,
                    'property_id' => $present && isset($info['property_id'])
                        ? (string) $info['property_id']
                        : null,
                ];
            }
            $site['matrix'] = $matrix;
            $site['modules_count'] = $presentCount;
            $site['modules_total'] = $modulesTotal;
            $site['visits'] = null;
            unset($site['present'], $site['last_at']);
        }
        unset($site);

        return $sites;
    }

    /**
     * Посещаемость для всех доменов таблицы, у которых есть привязка Метрики (пакетно + кэш).
     *
     * @param array<int, array<string, mixed>> $sites
     * @return array{by_domain: array<string, array<string, mixed>>, meta: ?array<string, mixed>}
     */
    private static function loadPilotVisits(int $userId, array $sites): array
    {
        $domains = [];
        foreach ($sites as $site) {
            $domain = (string) ($site['domain'] ?? '');
            if ($domain !== '') {
                $domains[] = $domain;
            }
        }
        if ($domains === [] || $userId < 1) {
            return ['by_domain' => [], 'meta' => null];
        }

        $out = [];
        $meta = null;
        try {
            /** @var YandexMetrikaService $service */
            $service = app(YandexMetrikaService::class);
            $summaries = $service->visitorsSummariesForDomains($userId, $domains);
            foreach ($summaries as $domain => $summary) {
                if (!is_array($summary)) {
                    continue;
                }
                $rowMeta = $summary['meta'] ?? null;
                unset($summary['meta']);
                $out[$domain] = $summary;
                if (is_array($rowMeta) && !empty($rowMeta['as_of'])) {
                    if ($meta === null || strcmp((string) $rowMeta['as_of'], (string) ($meta['as_of'] ?? '')) > 0) {
                        $meta = $rowMeta;
                    }
                }
            }
        } catch (Throwable $e) {
            report($e);
        }

        return ['by_domain' => $out, 'meta' => $meta];
    }

    /**
     * @param array<int, array<string, mixed>> $sites
     * @param array<string, array<string, mixed>> $visitsByDomain
     * @return array<int, array<string, mixed>>
     */
    private static function attachVisits(array $sites, array $visitsByDomain): array
    {
        foreach ($sites as &$site) {
            $domain = (string) ($site['domain'] ?? '');
            $site['visits'] = $visitsByDomain[$domain] ?? null;
        }
        unset($site);

        return $sites;
    }

    /**
     * @param array<int, array<string, mixed>> $catalog
     */
    private static function countModuleColumns(array $catalog): int
    {
        $n = 0;
        foreach ($catalog as $item) {
            if (($item['kind'] ?? 'module') === 'module') {
                $n++;
            }
        }

        return $n;
    }

    /**
     * Привязки доменов к счётчикам Яндекс.Метрики.
     *
     * @param callable(string,string,string,mixed,string):void $add
     */
    private static function collectYandexMetrika(int $userId, callable $add): void
    {
        if ($userId < 1 || !YandexMetrikaDomainCounter::tableReady()) {
            return;
        }

        YandexMetrikaDomainCounter::forUser($userId)->each(static function ($row) use ($add) {
            $label = trim((string) $row->counter_name);
            if ($label === '') {
                $label = '#' . (int) $row->counter_id;
            }
            $add(
                (string) $row->domain,
                'yandex-metrika',
                '#metrika',
                $row->updated_at ?: $row->created_at,
                $label,
                ['counter_id' => (int) $row->counter_id]
            );
        });
    }

    /**
     * Привязки доменов к хостам Яндекс.Вебмастера.
     *
     * @param callable(string,string,string,mixed,string):void $add
     */
    private static function collectYandexWebmaster(int $userId, callable $add): void
    {
        if ($userId < 1 || !YandexWebmasterDomainHost::tableReady()) {
            return;
        }

        YandexWebmasterDomainHost::forUser($userId)->each(static function ($row) use ($add) {
            $label = trim((string) $row->host_url);
            if ($label === '') {
                $label = (string) $row->host_id;
            }
            $add(
                (string) $row->domain,
                'yandex-webmaster',
                '#webmaster',
                $row->updated_at ?: $row->created_at,
                $label,
                ['host_id' => (string) $row->host_id]
            );
        });
    }

    /**
     * Привязки доменов к property Google Search Console.
     *
     * @param callable(string,string,string,mixed,string):void $add
     */
    private static function collectGoogleSearchConsole(int $userId, callable $add): void
    {
        if ($userId < 1 || !GoogleSearchConsoleDomainProperty::tableReady()) {
            return;
        }

        GoogleSearchConsoleDomainProperty::forUser($userId)->each(static function ($row) use ($add) {
            $label = trim((string) $row->property_url);
            if ($label === '') {
                $label = (string) $row->property_id;
            }
            $add(
                (string) $row->domain,
                'google-search-console',
                '#gsc',
                $row->updated_at ?: $row->created_at,
                $label,
                ['property_id' => (string) $row->property_id]
            );
        });
    }

    /**
     * @param callable(string,string,string,mixed,string):void $add
     */
    private static function collectSeoChecklist(int $userId, callable $add): void
    {
        if ($userId < 1 || !SeoChecklistProject::tableReady()) {
            return;
        }

        $teamIds = [];
        if (\App\SeoChecklist\SeoChecklistTeam::tableReady()) {
            $teamIds = \App\SeoChecklist\SeoChecklistTeamMember::query()
                ->where('user_id', $userId)
                ->pluck('team_id')
                ->all();
        }

        SeoChecklistProject::query()
            ->where(function ($q) use ($userId, $teamIds) {
                $q->where('user_id', $userId)
                    ->orWhere('owner_user_id', $userId)
                    ->orWhere('pm_user_id', $userId);
                if (!empty($teamIds)) {
                    $q->orWhereIn('team_id', $teamIds);
                }
            })
            ->where('status', 'active')
            ->orderByDesc('id')
            ->limit(self::PER_SOURCE_LIMIT)
            ->get(['id', 'domain', 'progress_done', 'progress_total', 'updated_at', 'last_activity_at'])
            ->each(static function ($row) use ($add) {
                $label = ((int) $row->progress_done) . '/' . ((int) $row->progress_total);
                $add(
                    (string) $row->domain,
                    "checklist",
                    url('/checklist/' . (int) $row->id),
                    $row->last_activity_at ?: $row->updated_at,
                    $label
                );
            });
    }

    /**
     * @param callable(string,string,string,mixed,string):void $add
     */
    private static function collectSeoReports(int $userId, callable $add): void
    {
        if ($userId < 1 || !SchemaMemo::hasTable('seo_report_projects')) {
            return;
        }

        $teamIds = SeoReportProject::teamIdsForMember($userId);

        SeoReportProject::query()
            ->where('status', 'active')
            ->where(static function ($q) use ($userId, $teamIds) {
                $q->where('user_id', $userId);
                if ($teamIds !== [] && SeoReportProject::teamColumnReady()) {
                    $q->orWhereIn('team_id', $teamIds);
                }
                if (SchemaMemo::hasTable('seo_report_project_user')) {
                    $q->orWhereIn('id', function ($sub) use ($userId) {
                        $sub->select('seo_report_project_id')
                            ->from('seo_report_project_user')
                            ->where('user_id', $userId);
                    });
                }
            })
            ->withCount('reports')
            ->orderByDesc('updated_at')
            ->limit(self::PER_SOURCE_LIMIT)
            ->get()
            ->each(static function ($row) use ($add) {
                $label = (string) ((int) $row->reports_count);
                $add(
                    (string) $row->domain,
                    'reports',
                    url('/reports/' . (int) $row->id),
                    $row->updated_at,
                    $label
                );
            });
    }

    /**
     * @param callable(string,string,string,mixed,string):void $add
     */
    private static function collectRelevance(int $userId, callable $add): void
    {
        ProjectRelevanceHistory::query()
            ->where('user_id', $userId)
            ->orderByDesc('updated_at')
            ->limit(self::PER_SOURCE_LIMIT)
            ->get(['id', 'name', 'updated_at', 'created_at'])
            ->each(static function ($row) use ($add) {
                $add(
                    (string) $row->name,
                    'analyze-relevance',
                    url('/history'),
                    $row->updated_at ?: $row->created_at
                );
            });
    }

    /**
     * @param callable(string,string,string,mixed,string):void $add
     */
    private static function collectMonitoring(int $userId, callable $add): void
    {
        /** @var \App\User|null $user */
        $user = Auth::user();
        $q = ($user && (int) $user->id === $userId)
            ? $user->monitoringProjects()
            : MonitoringProject::query()->whereHas('users', static function ($uq) use ($userId) {
                $uq->where('users.id', $userId);
            });

        $q->orderByDesc('monitoring_projects.updated_at')
            ->limit(self::PER_SOURCE_LIMIT)
            ->get([
                'monitoring_projects.id',
                'monitoring_projects.url',
                'monitoring_projects.name',
                'monitoring_projects.updated_at',
                'monitoring_projects.created_at',
            ])
            ->each(static function ($row) use ($add) {
                $host = self::normalizeDomain((string) $row->url) ?: (string) $row->url;
                $add(
                    $host !== '' ? $host : (string) $row->name,
                    'monitoring',
                    url('/monitoring/' . (int) $row->id),
                    $row->updated_at ?: $row->created_at,
                    (string) $row->name
                );
            });
    }

    /**
     * @param callable(string,string,string,mixed,string):void $add
     */
    private static function collectSiteAudit(int $userId, callable $add): void
    {
        SiteAuditProject::query()
            ->where('user_id', $userId)
            ->orderByDesc('updated_at')
            ->limit(self::PER_SOURCE_LIMIT)
            ->get(['id', 'domain', 'name', 'updated_at', 'created_at'])
            ->each(static function ($row) use ($add) {
                $add(
                    (string) $row->domain,
                    'site-audit',
                    url('/site-audit'),
                    $row->updated_at ?: $row->created_at,
                    (string) ($row->name ?: '')
                );
            });
    }

    /**
     * @param callable(string,string,string,mixed,string):void $add
     */
    private static function collectDomainInformation(int $userId, callable $add): void
    {
        DomainInformation::query()
            ->where('user_id', $userId)
            ->orderByDesc('updated_at')
            ->limit(self::PER_SOURCE_LIMIT)
            ->get(['id', 'domain', 'updated_at', 'created_at'])
            ->each(static function ($row) use ($add) {
                $add(
                    (string) $row->domain,
                    'domain-information',
                    url('/domain-information'),
                    $row->updated_at ?: $row->created_at
                );
            });
    }

    /**
     * @param callable(string,string,string,mixed,string):void $add
     */
    private static function collectSiteMonitoring(int $userId, callable $add): void
    {
        DomainMonitoring::query()
            ->where('user_id', $userId)
            ->orderByDesc('updated_at')
            ->limit(self::PER_SOURCE_LIMIT)
            ->get(['id', 'link', 'project_name', 'updated_at', 'created_at'])
            ->each(static function ($row) use ($add) {
                $add(
                    (string) $row->link,
                    'site-monitoring',
                    url('/site-monitoring'),
                    $row->updated_at ?: $row->created_at,
                    (string) ($row->project_name ?: '')
                );
            });
    }

    /**
     * @param callable(string,string,string,mixed,string):void $add
     */
    private static function collectCluster(int $userId, callable $add): void
    {
        ClusterResults::query()
            ->where('user_id', $userId)
            ->where('show', 1)
            ->orderByDesc('updated_at')
            ->limit(self::PER_SOURCE_LIMIT)
            ->get(['id', 'domain', 'comment', 'updated_at', 'created_at'])
            ->each(static function ($row) use ($add) {
                $host = (string) ($row->domain ?: '');
                if ($host === '') {
                    return;
                }
                $add(
                    $host,
                    'cluster',
                    url('/show-cluster-result/' . (int) $row->id),
                    $row->updated_at ?: $row->created_at,
                    (string) ($row->comment ?: '')
                );
            });
    }

    /**
     * @param callable(string,string,string,mixed,string):void $add
     */
    private static function collectDomainRecords(int $userId, callable $add): void
    {
        DomainRecordsHistory::query()
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->limit(self::PER_SOURCE_LIMIT)
            ->get(['id', 'domain', 'updated_at', 'created_at'])
            ->each(static function ($row) use ($add) {
                $add(
                    (string) $row->domain,
                    'domain-records',
                    url('/domain-records'),
                    $row->updated_at ?: $row->created_at
                );
            });
    }

    /**
     * @param callable(string,string,string,mixed,string):void $add
     */
    private static function collectIndexCheck(int $userId, callable $add): void
    {
        IndexCheckHistory::query()
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->limit(self::PER_SOURCE_LIMIT)
            ->get(['id', 'url', 'updated_at', 'created_at'])
            ->each(static function ($row) use ($add) {
                $add(
                    (string) $row->url,
                    'index-check',
                    url('/index-check'),
                    $row->updated_at ?: $row->created_at
                );
            });
    }

    /**
     * @param callable(string,string,string,mixed,string):void $add
     */
    private static function collectEsenin(int $userId, callable $add): void
    {
        if (!SchemaMemo::hasTable('esenin_text_check_sessions')) {
            return;
        }

        EseninTextCheckSession::query()
            ->where('user_id', $userId)
            ->whereNotNull('source_url')
            ->where('source_url', '!=', '')
            ->orderByDesc('updated_at')
            ->limit(self::PER_SOURCE_LIMIT)
            ->get(['id', 'source_url', 'name', 'updated_at', 'created_at'])
            ->each(static function ($row) use ($add) {
                $add(
                    (string) $row->source_url,
                    'esenin-text-check',
                    url('/esenin-text-check/sessions/' . (int) $row->id),
                    $row->updated_at ?: $row->created_at,
                    (string) ($row->name ?: '')
                );
            });
    }

    public static function normalizeDomain(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (!preg_match('#^https?://#i', $value)) {
            $value = 'https://' . ltrim($value, '/');
        }

        $host = parse_url($value, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            $host = preg_replace('#^www\.#i', '', preg_replace('#/.*$#', '', trim($value)));
        }

        $host = strtolower((string) $host);
        $host = preg_replace('#^www\.#i', '', $host);

        if ($host === '' || !preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $host)) {
            return '';
        }

        return $host;
    }

    /**
     * @param mixed $value
     */
    private static function toCarbon($value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return Carbon::parse($value);
        } catch (Throwable $e) {
            return null;
        }
    }
}
