<?php

namespace App\SeoReports;

use App\GoogleSearchConsoleDomainProperty;
use App\MonitoringProject;
use App\Support\HomeUserSites;
use App\YandexMetrikaDomainCounter;
use App\YandexWebmasterDomainHost;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class SeoReportBindings
{
    public static function resolveMetrikaCounterId(int $userId, string $domain): ?int
    {
        if ($userId < 1 || !YandexMetrikaDomainCounter::tableReady()) {
            return null;
        }

        $domain = HomeUserSites::normalizeDomain($domain);
        if ($domain === '') {
            return null;
        }

        $counterId = (int) YandexMetrikaDomainCounter::query()
            ->where('user_id', $userId)
            ->where('domain', $domain)
            ->value('counter_id');

        return $counterId > 0 ? $counterId : null;
    }

    /**
     * host_id Яндекс.Вебмастера (например https:kawe.su:443) из привязки на главной.
     */
    public static function resolveWebmasterHost(int $userId, string $domain): ?string
    {
        if ($userId < 1 || !YandexWebmasterDomainHost::tableReady()) {
            return null;
        }

        $domain = HomeUserSites::normalizeDomain($domain);
        if ($domain === '') {
            return null;
        }

        $hostId = trim((string) YandexWebmasterDomainHost::query()
            ->where('user_id', $userId)
            ->where('domain', $domain)
            ->value('host_id'));

        return $hostId !== '' ? $hostId : null;
    }

    /**
     * property_id Google Search Console (sc-domain:… или URL-префикс) из привязки на главной.
     */
    public static function resolveGscProperty(int $userId, string $domain): ?string
    {
        if ($userId < 1 || !GoogleSearchConsoleDomainProperty::tableReady()) {
            return null;
        }

        $domain = HomeUserSites::normalizeDomain($domain);
        if ($domain === '') {
            return null;
        }

        $propertyId = trim((string) GoogleSearchConsoleDomainProperty::query()
            ->where('user_id', $userId)
            ->where('domain', $domain)
            ->value('property_id'));

        return $propertyId !== '' ? $propertyId : null;
    }

    public static function resolveMonitoringProjectId(int $userId, string $domain): ?int
    {
        if ($userId < 1) {
            return null;
        }

        $domain = HomeUserSites::normalizeDomain($domain);
        if ($domain === '') {
            return null;
        }

        foreach (self::monitoringOptionsForUser($userId) as $option) {
            if (($option['domain'] ?? '') === $domain) {
                return (int) $option['id'];
            }
        }

        return null;
    }

    /**
     * @return list<array{id:int,name:string,domain:string,label:string}>
     */
    public static function monitoringOptionsForUser(int $userId): array
    {
        /** @var \App\User|null $user */
        $user = Auth::user();
        $q = ($user && (int) $user->id === $userId)
            ? $user->monitoringProjects()
            : MonitoringProject::query()->whereHas('users', static function ($uq) use ($userId) {
                $uq->where('users.id', $userId);
            });

        $out = [];
        $q->orderByDesc('monitoring_projects.updated_at')
            ->limit(200)
            ->get([
                'monitoring_projects.id',
                'monitoring_projects.url',
                'monitoring_projects.name',
            ])
            ->each(static function ($row) use (&$out) {
                $domain = HomeUserSites::normalizeDomain((string) $row->url);
                $name = trim((string) $row->name);
                // Человекочитаемо: имя/домен первыми, id только в конце.
                if ($name !== '' && $domain !== '') {
                    $label = $name . ' · ' . $domain;
                } elseif ($name !== '') {
                    $label = $name;
                } elseif ($domain !== '') {
                    $label = $domain;
                } else {
                    $label = 'Проект мониторинга';
                }
                $out[] = [
                    'id' => (int) $row->id,
                    'name' => $name,
                    'domain' => $domain,
                    'label' => $label,
                ];
            });

        return $out;
    }

    /**
     * @return Collection<int, YandexMetrikaDomainCounter>
     */
    public static function metrikaBindingsForUser(int $userId): Collection
    {
        return YandexMetrikaDomainCounter::forUser($userId);
    }

    /**
     * @return Collection<int, YandexWebmasterDomainHost>
     */
    public static function webmasterBindingsForUser(int $userId): Collection
    {
        return YandexWebmasterDomainHost::forUser($userId);
    }

    /**
     * @return Collection<int, GoogleSearchConsoleDomainProperty>
     */
    public static function gscBindingsForUser(int $userId): Collection
    {
        return GoogleSearchConsoleDomainProperty::forUser($userId);
    }

    public static function applyAutoBindings(SeoReportProject $project): void
    {
        $userId = (int) $project->user_id;
        $domain = (string) $project->domain;

        if (!$project->metrika_counter_id) {
            $counterId = self::resolveMetrikaCounterId($userId, $domain);
            if ($counterId) {
                $project->metrika_counter_id = $counterId;
            }
        }

        if (!$project->monitoring_project_id) {
            $monitoringId = self::resolveMonitoringProjectId($userId, $domain);
            if ($monitoringId) {
                $project->monitoring_project_id = $monitoringId;
            }
        }

        $settings = is_array($project->settings_json) ? $project->settings_json : [];
        $changed = false;
        $currentHost = trim((string) ($settings['webmaster_host'] ?? ''));
        if ($currentHost === '') {
            $hostId = self::resolveWebmasterHost($userId, $domain);
            if ($hostId) {
                $settings['webmaster_host'] = $hostId;
                $changed = true;
            }
        }
        $currentGsc = trim((string) ($settings['gsc_property'] ?? ''));
        if ($currentGsc === '') {
            $propertyId = self::resolveGscProperty($userId, $domain);
            if ($propertyId) {
                $settings['gsc_property'] = $propertyId;
                $changed = true;
            }
        }
        if ($changed) {
            $project->settings_json = $settings;
        }
    }

    /**
     * После привязки на главной / в настройках — проставить host_id в SEO-проектах с тем же доменом.
     */
    public static function syncWebmasterHostToProjects(int $userId, string $domain, string $hostId): void
    {
        $domain = HomeUserSites::normalizeDomain($domain);
        $hostId = trim($hostId);
        if ($userId < 1 || $domain === '' || $hostId === '') {
            return;
        }

        SeoReportProject::query()
            ->where('user_id', $userId)
            ->where('domain', $domain)
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->each(static function (SeoReportProject $project) use ($hostId) {
                $settings = is_array($project->settings_json) ? $project->settings_json : [];
                if (trim((string) ($settings['webmaster_host'] ?? '')) === $hostId) {
                    return;
                }
                $settings['webmaster_host'] = $hostId;
                $project->settings_json = $settings;
                $project->save();
            });
    }

    /**
     * После отвязки на главной / в настройках — очистить host у SEO-проектов домена.
     */
    public static function clearWebmasterHostFromProjects(int $userId, string $domain): void
    {
        $domain = HomeUserSites::normalizeDomain($domain);
        if ($userId < 1 || $domain === '') {
            return;
        }

        SeoReportProject::query()
            ->where('user_id', $userId)
            ->where('domain', $domain)
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->each(static function (SeoReportProject $project) {
                $settings = is_array($project->settings_json) ? $project->settings_json : [];
                if (trim((string) ($settings['webmaster_host'] ?? '')) === '') {
                    return;
                }
                $settings['webmaster_host'] = null;
                $project->settings_json = $settings;
                $project->save();
            });
    }

    /**
     * После привязки на главной / в настройках — проставить gsc_property в SEO-проектах с тем же доменом.
     */
    public static function syncGscPropertyToProjects(int $userId, string $domain, string $propertyId): void
    {
        $domain = HomeUserSites::normalizeDomain($domain);
        $propertyId = trim($propertyId);
        if ($userId < 1 || $domain === '' || $propertyId === '') {
            return;
        }

        SeoReportProject::query()
            ->where('user_id', $userId)
            ->where('domain', $domain)
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->each(static function (SeoReportProject $project) use ($propertyId) {
                $settings = is_array($project->settings_json) ? $project->settings_json : [];
                if (trim((string) ($settings['gsc_property'] ?? '')) === $propertyId) {
                    return;
                }
                $settings['gsc_property'] = $propertyId;
                $project->settings_json = $settings;
                $project->save();
            });
    }

    /**
     * После отвязки на главной / в настройках — очистить gsc_property у SEO-проектов домена.
     */
    public static function clearGscPropertyFromProjects(int $userId, string $domain): void
    {
        $domain = HomeUserSites::normalizeDomain($domain);
        if ($userId < 1 || $domain === '') {
            return;
        }

        SeoReportProject::query()
            ->where('user_id', $userId)
            ->where('domain', $domain)
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->each(static function (SeoReportProject $project) {
                $settings = is_array($project->settings_json) ? $project->settings_json : [];
                if (trim((string) ($settings['gsc_property'] ?? '')) === '') {
                    return;
                }
                $settings['gsc_property'] = null;
                $project->settings_json = $settings;
                $project->save();
            });
    }
}
