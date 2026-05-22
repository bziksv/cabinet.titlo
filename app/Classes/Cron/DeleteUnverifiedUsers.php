<?php

namespace App\Classes\Cron;

use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Удаление пользователей без подтверждения email старше N дней.
 * Запуск: Laravel scheduler dailyAt 02:15 (cron * * * * php artisan schedule:run).
 * Ручная проверка: php artisan users:prune-unverified --dry-run
 */
class DeleteUnverifiedUsers
{
    public function __invoke(): void
    {
        $result = static::run(false);
        if ($result['enabled'] && ($result['deleted'] > 0 || $result['candidates'] > 0)) {
            Log::info($result['message']);
        }
    }

    /**
     * @return array{enabled: bool, days: int, candidates: int, deleted: int, message: string}
     */
    public static function run(bool $dryRun = false): array
    {
        $enabled = filter_var(env('DELETE_UNVERIFIED_USERS', true), FILTER_VALIDATE_BOOLEAN);
        $days = max(1, (int) env('DELETE_UNVERIFIED_USERS_DAYS', 30));

        if (!$enabled) {
            return [
                'enabled' => false,
                'days' => $days,
                'candidates' => 0,
                'deleted' => 0,
                'message' => 'DeleteUnverifiedUsers: disabled (DELETE_UNVERIFIED_USERS=false)',
            ];
        }

        $candidates = static::countCandidates($days);
        $deleted = $dryRun ? 0 : User::deleteUnverifiedOlderThan($days);

        return [
            'enabled' => true,
            'days' => $days,
            'candidates' => $candidates,
            'deleted' => $deleted,
            'message' => $dryRun
                ? 'DeleteUnverifiedUsers (dry-run): ' . $candidates . ' candidate(s), older than ' . $days . ' days without email verification.'
                : 'DeleteUnverifiedUsers: removed ' . $deleted . ' of ' . $candidates . ' candidate(s), older than ' . $days . ' days without email verification.',
        ];
    }

    public static function countCandidates(int $days): int
    {
        return User::query()
            ->whereNull('email_verified_at')
            ->where('created_at', '<=', Carbon::now()->subDays($days))
            ->count();
    }
}
