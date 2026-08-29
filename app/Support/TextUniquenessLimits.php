<?php

namespace App\Support;

use App\TextUniquenessHistory;
use App\TextUniquenessUsage;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class TextUniquenessLimits
{
    /** @var array<int, array|null> */
    private static $settingsByUser = [];

    public static function periodKey(?Carbon $at = null): string
    {
        $at = $at ?? Carbon::now();

        return $at->format('Y-m');
    }

    public static function limitForUser(?User $user = null): ?int
    {
        return self::tariffInt('TextUniqueness', $user);
    }

    public static function historyLimitForUser(?User $user = null): ?int
    {
        return self::tariffInt('TextUniquenessHistory', $user);
    }

    public static function canSaveHistory(?User $user = null): bool
    {
        $limit = self::historyLimitForUser($user);

        return $limit !== null && $limit > 0;
    }

    public static function usedForUser(?User $user = null, ?string $period = null): int
    {
        $user = $user ?? Auth::user();
        if (! $user) {
            return 0;
        }

        return (int) TextUniquenessUsage::query()
            ->where('user_id', $user->id)
            ->where('period', $period ?? self::periodKey())
            ->value('used');
    }

    public static function remainingForUser(?User $user = null): ?int
    {
        $limit = self::limitForUser($user);
        if ($limit === null) {
            return null;
        }

        return max(0, $limit - self::usedForUser($user));
    }

    public static function canSpend(int $cost, ?User $user = null): bool
    {
        $limit = self::limitForUser($user);
        if ($limit === null) {
            return true;
        }

        return self::usedForUser($user) + $cost <= $limit;
    }

    public static function spend(int $cost, ?User $user = null): void
    {
        if ($cost <= 0) {
            return;
        }

        $user = $user ?? Auth::user();
        if (! $user) {
            return;
        }

        $period = self::periodKey();
        $row = TextUniquenessUsage::query()->firstOrCreate(
            ['user_id' => $user->id, 'period' => $period],
            ['used' => 0]
        );

        $row->used = (int) $row->used + $cost;
        $row->save();
    }

    public static function limitMessage(?User $user = null): ?string
    {
        return self::tariffMessage('TextUniqueness', $user);
    }

    public static function historyLimitMessage(?User $user = null): ?string
    {
        return self::tariffMessage('TextUniquenessHistory', $user);
    }

    public static function savedCount(?User $user = null): int
    {
        $user = $user ?? Auth::user();
        if (! $user) {
            return 0;
        }

        return (int) TextUniquenessHistory::query()->where('user_id', $user->id)->count();
    }

    /**
     * Оставить только последние N записей (скользящее окно по лимиту тарифа).
     */
    public static function pruneHistory(?User $user = null): void
    {
        $user = $user ?? Auth::user();
        if (! $user) {
            return;
        }

        $limit = self::historyLimitForUser($user);
        if ($limit === null || $limit <= 0) {
            return;
        }

        $keepIds = TextUniquenessHistory::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->pluck('id');

        if ($keepIds->isEmpty()) {
            return;
        }

        TextUniquenessHistory::query()
            ->where('user_id', $user->id)
            ->whereNotIn('id', $keepIds)
            ->delete();
    }

    /**
     * @deprecated Лимит истории — скользящее окно: сохраняем и pruneHistory().
     */
    public static function canSaveAnother(?User $user = null): bool
    {
        return self::canSaveHistory($user);
    }

    private static function tariffInt(string $code, ?User $user = null): ?int
    {
        $settings = self::settings($user);
        if ($settings === null || ! array_key_exists($code, $settings)) {
            return null;
        }

        return (int) $settings[$code]['value'];
    }

    private static function tariffMessage(string $code, ?User $user = null): ?string
    {
        $settings = self::settings($user);
        if ($settings === null) {
            return null;
        }

        return $settings[$code]['message'] ?? null;
    }

    /**
     * @return array|null
     */
    private static function settings(?User $user = null): ?array
    {
        $user = $user ?? Auth::user();
        if (! $user) {
            return null;
        }

        $uid = (int) $user->id;
        if (array_key_exists($uid, self::$settingsByUser)) {
            return self::$settingsByUser[$uid];
        }

        $cacheKey = 'tu_tariff_settings_' . $uid;
        if (Cache::has($cacheKey)) {
            return self::$settingsByUser[$uid] = Cache::get($cacheKey);
        }

        $tariff = $user->tariff();
        $settings = $tariff ? ($tariff->getAsArray()['settings'] ?? []) : null;
        Cache::put($cacheKey, $settings, 120);

        return self::$settingsByUser[$uid] = $settings;
    }
}
