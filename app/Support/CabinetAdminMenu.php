<?php

namespace App\Support;

use App\MainProject;
use App\User;
use Illuminate\Support\Facades\Auth;

/**
 * Пункты админ-меню (шестерёнка), не показываются в основном сайдбаре.
 */
class CabinetAdminMenu
{
    /** main_projects.id — пункты шестерёнки (в меню сортируются по алфавиту по title). */
    public const PROJECT_IDS = [16, 26, 29, 17, 27, 33, 31];

    /** Скрыты из шестерёнки, но остаются в PROJECT_IDS (не дублируются в сайдбаре). 17 = /html/ (LTE demo), без badge-версии. 27 = legacy LTE на lk.redbox.su. */
    public const GEAR_HIDDEN_IDS = [17, 27];

    /** @var array<int, array{id:int,title:string,link:string,external:bool}>|null */
    private static $itemsCache;

    public static function isExcludedProjectId($id): bool
    {
        return in_array((int) $id, self::PROJECT_IDS, true);
    }

    /** Роли из main_projects.access для админ-пунктов. */
    public static function canAccess(): bool
    {
        if (! Auth::check()) {
            return false;
        }

        apply_global_team_permissions();
        Auth::user()->loadMissing('roles');

        return Auth::user()->hasRole(['admin', 'Super Admin']) || User::isUserAdmin();
    }

    /**
     * @return array<int, array{id:int,title:string,link:string,external:bool}>
     */
    public static function items(): array
    {
        if (! self::canAccess()) {
            return [];
        }

        if (self::$itemsCache !== null) {
            return self::$itemsCache;
        }

        $gearIds = array_values(array_diff(self::PROJECT_IDS, self::GEAR_HIDDEN_IDS));

        $projects = MainProject::query()
            ->whereIn('id', $gearIds)
            ->get(['id', 'title', 'link']);

        $items = $projects->map(static function (MainProject $project) {
            $link = localize_cabinet_url($project->link);

            return [
                'id' => (int) $project->id,
                'title' => __($project->title),
                'link' => $link,
                'external' => strpos((string) $project->link, 'docs.google.com') !== false
                    || strpos((string) $link, 'docs.google.com') !== false,
            ];
        })->values()->all();

        if (\Illuminate\Support\Facades\Route::has('admin.finance.index')) {
            $items[] = [
                'id' => 0,
                'title' => __('Finance and referral management'),
                'link' => route('admin.finance.index'),
                'external' => false,
            ];
        }

        if (\Illuminate\Support\Facades\Route::has('admin.smtp.index')) {
            $items[] = [
                'id' => 0,
                'title' => __('SMTP management'),
                'link' => route('admin.smtp.index'),
                'external' => false,
            ];
        }

        if (\Illuminate\Support\Facades\Route::has('admin.notifications.index')) {
            $items[] = [
                'id' => 0,
                'title' => __('Notifications management'),
                'link' => route('admin.notifications.index'),
                'external' => false,
            ];
        }

        if (\Illuminate\Support\Facades\Route::has('admin.telegram-proxy.index')) {
            $items[] = [
                'id' => 0,
                'title' => __('Telegram proxy management'),
                'link' => route('admin.telegram-proxy.index'),
                'external' => false,
            ];
        }

        if (\Illuminate\Support\Facades\Route::has('admin.database.index')) {
            $items[] = [
                'id' => 0,
                'title' => __('Database management'),
                'link' => route('admin.database.index'),
                'external' => false,
            ];
        }

        if (\Illuminate\Support\Facades\Route::has('admin.queue.index')) {
            $items[] = [
                'id' => 0,
                'title' => __('Queue management'),
                'link' => route('admin.queue.index'),
                'external' => false,
            ];
        }

        if (\Illuminate\Support\Facades\Route::has('admin.supervisor.index')) {
            $items[] = [
                'id' => 0,
                'title' => __('Supervisor management'),
                'link' => route('admin.supervisor.index'),
                'external' => false,
            ];
        }

        if (\Illuminate\Support\Facades\Route::has('admin.xml-providers.index')) {
            $items[] = [
                'id' => 0,
                'title' => __('XML services management'),
                'link' => route('admin.xml-providers.index'),
                'external' => false,
            ];
        }

        if (\Illuminate\Support\Facades\Route::has('admin.ai-providers.index')) {
            $items[] = [
                'id' => 0,
                'title' => __('AI management'),
                'link' => route('admin.ai-providers.index'),
                'external' => false,
            ];
        }

        self::$itemsCache = self::appendGoogleDocumentation(
            self::pinNotificationsAfterSmtp(self::sortItemsByTitle($items))
        );

        return self::$itemsCache;
    }

    /**
     * Ссылка на Google Docs — всегда последним пунктом шестерёнки.
     *
     * @param array<int, array{id:int,title:string,link:string,external:bool}> $items
     * @return array<int, array{id:int,title:string,link:string,external:bool}>
     */
    private static function appendGoogleDocumentation(array $items): array
    {
        $items[] = [
            'id' => 0,
            'title' => __('Google documentation'),
            'link' => 'https://docs.google.com/document/d/1TpjCQjXYV_ZWyxD-c8plld7m2-dce4fSGuMz0fgytK4/edit?tab=t.0',
            'external' => true,
        ];

        return $items;
    }

    /**
     * «Управление рассылками» — сразу после «Управление SMTP».
     *
     * @param array<int, array{id:int,title:string,link:string,external:bool}> $items
     * @return array<int, array{id:int,title:string,link:string,external:bool}>
     */
    private static function pinNotificationsAfterSmtp(array $items): array
    {
        if (! \Illuminate\Support\Facades\Route::has('admin.smtp.index')
            || ! \Illuminate\Support\Facades\Route::has('admin.notifications.index')) {
            return $items;
        }

        $smtpLink = route('admin.smtp.index');
        $notifyLink = route('admin.notifications.index');

        $notifyItem = null;
        $rest = [];

        foreach ($items as $item) {
            if (($item['link'] ?? '') === $notifyLink) {
                $notifyItem = $item;
                continue;
            }
            $rest[] = $item;
        }

        if ($notifyItem === null) {
            return $items;
        }

        $out = [];
        $inserted = false;

        foreach ($rest as $item) {
            $out[] = $item;
            if (!$inserted && ($item['link'] ?? '') === $smtpLink) {
                $out[] = $notifyItem;
                $inserted = true;
            }
        }

        if (!$inserted) {
            $out[] = $notifyItem;
        }

        return $out;
    }

    /**
     * @param array<int, array{id:int,title:string,link:string,external:bool}> $items
     * @return array<int, array{id:int,title:string,link:string,external:bool}>
     */
    private static function sortItemsByTitle(array $items): array
    {
        $collator = class_exists(\Collator::class) ? new \Collator('ru_RU') : null;

        usort($items, static function (array $a, array $b) use ($collator) {
            if ($collator !== null) {
                return $collator->compare($a['title'], $b['title']);
            }

            return strcasecmp($a['title'], $b['title']);
        });

        return $items;
    }

    /**
     * Убрать админ-пункты из дерева сайдбара (в т.ч. из session cache).
     */
    public static function filterModules(array $modules): array
    {
        $out = [];

        foreach ($modules as $key => $module) {
            if (! is_array($module)) {
                continue;
            }

            if (array_key_exists('configurationInfo', $module)) {
                $filtered = ['configurationInfo' => $module['configurationInfo']];

                foreach ($module as $k => $elem) {
                    if ($k === 'configurationInfo' || ! is_array($elem)) {
                        continue;
                    }
                    if (! self::isExcludedProjectId($elem['id'] ?? 0)) {
                        $filtered[$k] = $elem;
                    }
                }

                if (count($filtered) > 1) {
                    $out[$key] = $filtered;
                }

                continue;
            }

            if (isset($module['id']) && self::isExcludedProjectId($module['id'])) {
                continue;
            }

            $out[$key] = $module;
        }

        return $out;
    }
}
