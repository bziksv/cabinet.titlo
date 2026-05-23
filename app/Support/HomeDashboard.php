<?php

namespace App\Support;

use App\MenuItemsPosition;
use App\News;
use App\Support\NewsBadge;
use App\SupportTicket;
use App\User;
use Illuminate\Support\Facades\Auth;

class HomeDashboard
{
    public static function summary(): array
    {
        /** @var User $user */
        $user = Auth::user();
        $tariff = $user->tariff();
        $tariffName = $tariff ? $tariff->name() : null;

        $supportCount = 0;
        $supportFilter = null;
        if (SupportAccess::isStaff()) {
            $supportCount = (int) SupportTicket::where('status', SupportTicket::STATUS_OPEN)->count();
            $supportFilter = SupportTicket::STATUS_OPEN;
        } else {
            $supportCount = (int) SupportTicket::where('user_id', $user->id)
                ->where('status', SupportTicket::STATUS_ANSWERED)
                ->count();
            $supportFilter = SupportTicket::STATUS_ANSWERED;
        }

        return [
            'displayName' => $user->fullName ?: $user->email,
            'balanceFormatted' => number_format((float) $user->balance, 0, '.', ' '),
            'tariffName' => $tariffName,
            'supportCount' => $supportCount,
            'supportFilter' => $supportFilter,
            'telegramConnected' => $user->isTelegramConnected(),
            'newsCount' => self::unreadNewsCount(),
            'modulesCount' => count(self::modules()),
        ];
    }

    /**
     * Плоский список модулей с учётом ролей (как в сайдбаре).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function modules(): array
    {
        if (!Auth::check()) {
            return [];
        }

        if (cabinet_skip_heavy_web()) {
            $cached = session('cabinet_home_modules_flat');
            if (is_array($cached)) {
                return $cached;
            }
        }

        apply_global_team_permissions();
        Auth::user()->loadMissing('roles');

        $user = Auth::user();
        $flat = [];

        foreach (MenuItemsPosition::sortMenu() as $item) {
            if (array_key_exists('configurationInfo', $item)) {
                foreach ($item as $key => $elem) {
                    if ($key === 'configurationInfo' || !is_array($elem)) {
                        continue;
                    }
                    if (self::userCanAccessModule($user, $elem)) {
                        $flat[] = self::normalizeModule($elem);
                    }
                }
                continue;
            }

            if (is_array($item) && self::userCanAccessModule($user, $item)) {
                $flat[] = self::normalizeModule($item);
            }
        }

        $flat = array_values(array_filter($flat, static function (array $module) {
            return !CabinetAdminMenu::isExcludedProjectId($module['id'] ?? 0);
        }));

        if (cabinet_skip_heavy_web()) {
            session(['cabinet_home_modules_flat' => $flat]);
        }

        return $flat;
    }

    protected static function userCanAccessModule(User $user, array $elem): bool
    {
        if (CabinetAdminMenu::isExcludedProjectId($elem['id'] ?? 0)) {
            return false;
        }

        $access = $elem['access'] ?? null;
        $roles = is_null($access) ? [] : (is_array($access) ? $access : [$access]);

        return $user->hasRole($roles);
    }

    protected static function normalizeModule(array $elem): array
    {
        $link = localize_cabinet_url($elem['link'] ?? '#');
        $external = self::isExternalLink($elem['link'] ?? '', $link);

        return [
            'id' => (int) ($elem['id'] ?? 0),
            'title' => __($elem['title'] ?? ''),
            'description' => isset($elem['description']) ? __($elem['description']) : '',
            'link' => $link,
            'icon' => $elem['icon'] ?? '<i class="bi bi-grid-3x3-gap"></i>',
            'color' => self::normalizeColor($elem['color'] ?? null),
            'external' => $external,
        ];
    }

    protected static function isExternalLink(string $raw, string $localized): bool
    {
        return strpos($raw, 'http') === 0
            || strpos($localized, 'http') === 0
            || strpos($raw, 'docs.google.com') !== false;
    }

    protected static function normalizeColor(?string $color): string
    {
        if (!$color || !preg_match('/^#([A-Fa-f0-9]{6})$/', $color)) {
            return '#0d6efd';
        }

        return $color;
    }

    protected static function unreadNewsCount(): int
    {
        if (!Auth::check() || cabinet_skip_heavy_web()) {
            return 0;
        }

        return NewsBadge::unreadNewsCount((int) Auth::id());
    }
}
