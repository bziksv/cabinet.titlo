<?php

namespace App\Support;

use App\IndexCheckHistory;
use App\IndexCheckUsage;
use App\Services\IndexCheckService;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class IndexCheckLimits
{
    public static function periodKey(?Carbon $at = null): string
    {
        $at = $at ?? Carbon::now();

        return $at->format('Y-m');
    }

    public static function limitForUser(?User $user = null): ?int
    {
        $user = $user ?? Auth::user();
        if (! $user) {
            return null;
        }

        $tariff = $user->tariff();
        if (! $tariff) {
            return null;
        }

        $settings = $tariff->getAsArray()['settings'] ?? [];
        if (! array_key_exists('IndexCheck', $settings)) {
            return null;
        }

        return (int) $settings['IndexCheck']['value'];
    }

    public static function usedForUser(?User $user = null, ?string $period = null): int
    {
        $user = $user ?? Auth::user();
        if (! $user) {
            return 0;
        }

        return (int) IndexCheckUsage::query()
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
        $row = IndexCheckUsage::query()->firstOrCreate(
            ['user_id' => $user->id, 'period' => $period],
            ['used' => 0]
        );

        $row->used = (int) $row->used + $cost;
        $row->save();
    }

    public static function limitMessage(?User $user = null): ?string
    {
        $user = $user ?? Auth::user();
        if (! $user) {
            return null;
        }

        $tariff = $user->tariff();
        if (! $tariff) {
            return null;
        }

        $settings = $tariff->getAsArray()['settings'] ?? [];

        return $settings['IndexCheck']['message'] ?? null;
    }

    public static function estimateBatchCost(int $urlCount, bool $yandex, bool $google): int
    {
        return $urlCount * IndexCheckService::checkCost($yandex, $google);
    }

    /**
     * Максимум сохранённых проверок со сниппетами (IndexCheckHistory).
     */
    public static function historyLimitForUser(?User $user = null): ?int
    {
        $user = $user ?? Auth::user();
        if (! $user) {
            return null;
        }

        $tariff = $user->tariff();
        if (! $tariff) {
            return null;
        }

        $settings = $tariff->getAsArray()['settings'] ?? [];
        if (! array_key_exists('IndexCheckHistory', $settings)) {
            return null;
        }

        return (int) $settings['IndexCheckHistory']['value'];
    }

    public static function savedCount(?User $user = null): int
    {
        $user = $user ?? Auth::user();
        if (! $user) {
            return 0;
        }

        return (int) IndexCheckHistory::query()->where('user_id', $user->id)->count();
    }

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

        $keepIds = IndexCheckHistory::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->pluck('id');

        if ($keepIds->isEmpty()) {
            return;
        }

        IndexCheckHistory::query()
            ->where('user_id', $user->id)
            ->whereNotIn('id', $keepIds)
            ->delete();
    }
}
