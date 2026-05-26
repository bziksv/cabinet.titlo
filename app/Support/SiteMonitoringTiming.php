<?php

namespace App\Support;

use App\DomainMonitoring;
use App\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SiteMonitoringTiming
{
    public const FREE_MINUTES = 60;

    /** @var array<int, int> */
    public const ALL_INTERVALS = [5, 10, 15, 20, 30, 60];

    /**
     * Опции select «интервал проверки» для UI.
     *
     * @return array<string, string>
     */
    public static function selectOptionsForUser(User $user, bool $listLabels = false): array
    {
        $intervals = $user->onFreeTariff() ? [self::FREE_MINUTES] : self::ALL_INTERVALS;
        $options = [];

        foreach ($intervals as $minutes) {
            $key = (string) $minutes;
            $options[$key] = $listLabels
                ? self::listLabel($minutes)
                : self::createLabel($minutes);
        }

        return $options;
    }

    public static function defaultForUser(User $user): int
    {
        return $user->onFreeTariff() ? self::FREE_MINUTES : 10;
    }

    /**
     * Нормализует интервал с учётом тарифа (store / inline edit).
     */
    public static function resolveForUser($timing, User $user): int
    {
        $timing = (int) $timing;

        if ($user->onFreeTariff()) {
            return self::FREE_MINUTES;
        }

        return in_array($timing, self::ALL_INTERVALS, true) ? $timing : 10;
    }

    /**
     * Синхронизирует проекты одного пользователя Free (при открытии списка).
     */
    public static function enforceForUser(User $user): void
    {
        if (!$user->onFreeTariff()) {
            return;
        }

        DomainMonitoring::query()
            ->where('user_id', $user->id)
            ->where('timing', '!=', self::FREE_MINUTES)
            ->update(['timing' => self::FREE_MINUTES]);
    }

    /**
     * Переводит все проекты пользователей с ролью Free на 60 минут.
     *
     * @return int количество обновлённых строк
     */
    public static function migrateFreeTariffProjectsToAllowedInterval(): int
    {
        $userIds = self::freeTariffUserIds();

        if ($userIds->isEmpty()) {
            return 0;
        }

        return DomainMonitoring::query()
            ->whereIn('user_id', $userIds)
            ->where('timing', '!=', self::FREE_MINUTES)
            ->update(['timing' => self::FREE_MINUTES]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, int>
     */
    public static function freeTariffUserIds()
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(1);

        $roleId = Role::query()
            ->where('name', 'Free')
            ->value('id');

        if (!$roleId) {
            return collect();
        }

        $morphKey = config('permission.column_names.model_morph_key', 'model_id');
        $teamKey = config('permission.column_names.team_foreign_key', 'team_id');

        return DB::table(config('permission.table_names.model_has_roles'))
            ->where('role_id', $roleId)
            ->where('model_type', User::class)
            ->when(
                config('permission.teams'),
                static function ($query) use ($teamKey) {
                    $query->where($teamKey, 1);
                }
            )
            ->pluck($morphKey);
    }

    private static function listLabel(int $minutes): string
    {
        return __('site_monitoring.interval_min', ['n' => $minutes]);
    }

    private static function createLabel(int $minutes): string
    {
        return __('site_monitoring.interval_every', ['n' => $minutes]);
    }
}
