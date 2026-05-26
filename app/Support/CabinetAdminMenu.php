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
    /** main_projects.id — порядок в выпадающем списке шестерёнки */
    public const PROJECT_IDS = [16, 26, 29, 17, 27, 33, 31];

    /** Скрыты из шестерёнки, но остаются в PROJECT_IDS (не дублируются в сайдбаре). 17 = /html/ (LTE demo), без badge-версии. */
    public const GEAR_HIDDEN_IDS = [17];

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
        $ids = implode(',', array_map('intval', $gearIds));

        $projects = MainProject::query()
            ->whereIn('id', $gearIds)
            ->orderByRaw("FIELD(id, {$ids})")
            ->get(['id', 'title', 'link']);

        self::$itemsCache = $projects->map(static function (MainProject $project) {
            $link = localize_cabinet_url($project->link);

            return [
                'id' => (int) $project->id,
                'title' => __($project->title),
                'link' => $link,
                'external' => strpos((string) $project->link, 'docs.google.com') !== false
                    || strpos((string) $link, 'docs.google.com') !== false,
            ];
        })->values()->all();

        if (\Illuminate\Support\Facades\Route::has('admin.telegram-proxy.index')) {
            self::$itemsCache[] = [
                'id' => 0,
                'title' => __('Telegram proxy management'),
                'link' => route('admin.telegram-proxy.index'),
                'external' => false,
            ];
        }

        if (\Illuminate\Support\Facades\Route::has('admin.xml-providers.index')) {
            self::$itemsCache[] = [
                'id' => 0,
                'title' => __('XML services management'),
                'link' => route('admin.xml-providers.index'),
                'external' => false,
            ];
        }

        return self::$itemsCache;
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
