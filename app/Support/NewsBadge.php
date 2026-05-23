<?php

namespace App\Support;

use App\News;
use App\NewsComments;
use App\NewsNotification;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Счётчики «новые новости» и «новые комментарии» (для админов) в шапке.
 */
class NewsBadge
{
    public static function unreadNewsCount(int $userId): int
    {
        if (cabinet_skip_heavy_web()) {
            return 0;
        }

        $notification = NewsNotification::where('user_id', $userId)->first();
        if ($notification !== null && $notification->last_check) {
            return (int) News::where('created_at', '>=', $notification->last_check)->count();
        }

        return (int) News::count();
    }

    /**
     * @return array{count: int, url: string|null, comment_id: int|null, news_id: int|null}
     */
    public static function unreadCommentsForAdmin(int $userId): array
    {
        $empty = ['count' => 0, 'url' => null, 'comment_id' => null, 'news_id' => null];

        if (! User::isUserAdmin() || (int) auth()->id() !== $userId) {
            return $empty;
        }

        if (cabinet_skip_heavy_web()) {
            return Cache::remember(
                'cabinet.news.unread_comments.' . $userId,
                now()->addMinutes(2),
                static function () use ($userId, $empty) {
                    return self::unreadCommentsForAdminUncached($userId);
                }
            );
        }

        return self::unreadCommentsForAdminUncached($userId);
    }

    /** @return array{count: int, url: string|null, comment_id: int|null, news_id: int|null} */
    protected static function unreadCommentsForAdminUncached(int $userId): array
    {
        $empty = ['count' => 0, 'url' => null, 'comment_id' => null, 'news_id' => null];

        $notification = NewsNotification::where('user_id', $userId)->first();
        $since = $notification && $notification->last_comment_check
            ? Carbon::parse($notification->last_comment_check)
            : null;

        $query = NewsComments::query()->orderBy('id');
        if ($since) {
            $query->where('created_at', '>=', $since);
        }

        $count = (int) $query->count();
        if ($count === 0) {
            return $empty;
        }

        $first = (clone $query)->first(['id', 'news_id']);
        if (! $first) {
            return $empty;
        }

        return [
            'count' => $count,
            'url' => route('news') . '#comment-' . $first->id,
            'comment_id' => (int) $first->id,
            'news_id' => (int) $first->news_id,
        ];
    }

    public static function markNewsSeen(int $userId): void
    {
        $notification = NewsNotification::firstOrNew(['user_id' => $userId]);
        $notification->last_check = Carbon::now();
        $notification->save();
    }

    public static function markCommentsSeenForAdmin(int $userId): void
    {
        if (! User::isUserAdmin()) {
            return;
        }

        $notification = NewsNotification::firstOrNew(['user_id' => $userId]);
        $notification->last_comment_check = Carbon::now();
        $notification->save();

        Cache::forget('cabinet.news.unread_comments.' . $userId);
    }
}
