<?php

namespace App\Support;

use App\ClusterQueue;
use App\ClusterResults;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ClusterProgress
{
    private const FAILED_CACHE_PREFIX = 'cluster_progress_failed:';
    private const EXPECTED_CACHE_PREFIX = 'cluster_progress_expected:';
    private const ACTIVE_CACHE_PREFIX = 'cluster_progress_active_user:';

    public static function rememberExpectedTotal(string $progressId, int $total): void
    {
        if ($progressId === '' || $total < 1) {
            return;
        }

        Cache::put(self::EXPECTED_CACHE_PREFIX . md5($progressId), $total, now()->addHours(12));
    }

    public static function expectedTotal(string $progressId): int
    {
        if ($progressId === '') {
            return 0;
        }

        return (int) Cache::get(self::EXPECTED_CACHE_PREFIX . md5($progressId), 0);
    }

    /**
     * Новый запуск отменяет незавершённый предыдущий у того же пользователя,
     * иначе UI показывает 0/N, пока воркеры дожимают старый progress_id.
     */
    public static function claimActiveForUser(int $userId, string $progressId): ?string
    {
        if ($userId < 1 || $progressId === '') {
            return null;
        }

        $key = self::ACTIVE_CACHE_PREFIX . $userId;
        $previous = (string) Cache::get($key, '');
        Cache::put($key, $progressId, now()->addHours(12));

        if ($previous === '' || $previous === $progressId) {
            return null;
        }

        if (self::isComplete($previous) || self::getFailed($previous)) {
            return null;
        }

        self::cancelProgress($previous, 'Заменён новым запуском анализа');
        self::removeJobsForProgress(ClusterQueues::name('main'), $previous);

        return $previous;
    }

    public static function activeForUser(int $userId): ?string
    {
        if ($userId < 1) {
            return null;
        }

        $progressId = (string) Cache::get(self::ACTIVE_CACHE_PREFIX . $userId, '');

        return $progressId !== '' ? $progressId : null;
    }

    public static function clearActiveForUser(int $userId): void
    {
        if ($userId < 1) {
            return;
        }

        Cache::forget(self::ACTIVE_CACHE_PREFIX . $userId);
    }

    public static function clearActiveIfMatches(int $userId, string $progressId): void
    {
        if ($userId < 1 || $progressId === '') {
            return;
        }

        if (self::activeForUser($userId) === $progressId) {
            self::clearActiveForUser($userId);
        }
    }

    /**
     * @return array{queue_count:int,phrases_done:int,phrases_pending:int,phrases_total:int,waiting_in_queue:bool,starting:bool}
     */
    public static function snapshot(string $progressId): array
    {
        $done = (int) ClusterQueue::where('progress_id', $progressId)->count();
        $pending = (int) DB::table('jobs')
            ->where('queue', ClusterQueues::name('child'))
            ->where('payload', 'like', '%' . $progressId . '%')
            ->count();
        $expected = self::expectedTotal($progressId);
        $total = max($done + $pending, $done, $expected);
        $starting = $done === 0 && $pending === 0 && $expected > 0;

        return [
            'queue_count' => $done,
            'phrases_done' => $done,
            'phrases_pending' => $pending,
            'phrases_total' => $total,
            'waiting_in_queue' => $done === 0 && $pending > 0,
            'starting' => $starting,
        ];
    }

    /**
     * @return array{started_at:?string,last_row_at:?string,wait_job_at:?string,wait_jobs:int,child_jobs:int}|null
     */
    public static function timing(string $progressId): ?array
    {
        if ($progressId === '') {
            return null;
        }

        $rowStats = ClusterQueue::query()
            ->where('progress_id', $progressId)
            ->selectRaw('MIN(created_at) as started_at, MAX(created_at) as last_row_at')
            ->first();

        $waitStats = DB::table('jobs')
            ->where('queue', ClusterQueues::name('wait'))
            ->where('payload', 'like', '%' . $progressId . '%')
            ->selectRaw('MIN(FROM_UNIXTIME(created_at)) as wait_job_at, COUNT(*) as wait_jobs')
            ->first();

        $childJobs = (int) DB::table('jobs')
            ->where('queue', ClusterQueues::name('child'))
            ->where('payload', 'like', '%' . $progressId . '%')
            ->count();

        return [
            'started_at' => $rowStats && $rowStats->started_at ? (string) $rowStats->started_at : null,
            'last_row_at' => $rowStats && $rowStats->last_row_at ? (string) $rowStats->last_row_at : null,
            'wait_job_at' => $waitStats && $waitStats->wait_job_at ? (string) $waitStats->wait_job_at : null,
            'wait_jobs' => (int) ($waitStats->wait_jobs ?? 0),
            'child_jobs' => $childJobs,
        ];
    }

    public static function isComplete(string $progressId): bool
    {
        return ClusterResults::where('progress_id', $progressId)->exists();
    }

    /**
     * @return array{message:string,at:string}|null
     */
    public static function getFailed(string $progressId): ?array
    {
        if ($progressId === '') {
            return null;
        }

        $data = Cache::get(self::FAILED_CACHE_PREFIX . md5($progressId));

        return is_array($data) ? $data : null;
    }

    public static function markFailed(string $progressId, string $message): void
    {
        if ($progressId === '') {
            return;
        }

        $ttlHours = max(1, (int) config('cabinet-cluster.wait_failed_cache_hours', 48));

        Cache::put(self::FAILED_CACHE_PREFIX . md5($progressId), [
            'message' => $message,
            'at' => now()->toDateTimeString(),
        ], now()->addHours($ttlHours));
    }

    public static function clearFailed(string $progressId): void
    {
        if ($progressId === '') {
            return;
        }

        Cache::forget(self::FAILED_CACHE_PREFIX . md5($progressId));
    }

    public static function isStale(string $progressId): bool
    {
        if ($progressId === '' || self::isComplete($progressId) || self::getFailed($progressId)) {
            return false;
        }

        $snapshot = self::snapshot($progressId);
        $total = (int) ($snapshot['phrases_total'] ?? 0);
        $done = (int) ($snapshot['phrases_done'] ?? 0);
        $staleMinutes = max(5, (int) config('cabinet-cluster.wait_stale_minutes', 30));
        $maxWaitHours = max(1, (int) config('cabinet-cluster.wait_max_hours', 6));
        $now = now();

        if ($total > 0 && $done >= $total) {
            if (self::isComplete($progressId)) {
                return false;
            }

            $timing = self::timing($progressId) ?? [];
            if (($timing['wait_jobs'] ?? 0) === 0 && ($timing['child_jobs'] ?? 0) === 0) {
                if (! empty($timing['last_row_at'])) {
                    return Carbon::parse($timing['last_row_at'])->diffInMinutes($now) >= $staleMinutes;
                }

                return true;
            }

            return false;
        }

        $timing = self::timing($progressId) ?? [];

        if (! empty($timing['last_row_at'])) {
            $lastRow = Carbon::parse($timing['last_row_at']);
            if ($lastRow->diffInMinutes($now) >= $staleMinutes) {
                return true;
            }
        }

        $startedAt = $timing['started_at'] ?? $timing['wait_job_at'] ?? null;
        if ($startedAt !== null && Carbon::parse($startedAt)->diffInHours($now) >= $maxWaitHours) {
            return true;
        }

        if ($done === 0 && ! empty($timing['wait_job_at'])) {
            $waitAt = Carbon::parse($timing['wait_job_at']);
            if ($waitAt->diffInMinutes($now) >= $staleMinutes) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{progress_id:string,removed_queue_rows:int,removed_wait_jobs:int,removed_child_jobs:int}
     */
    public static function cancelProgress(string $progressId, ?string $reason = null): array
    {
        $reason = $reason ?: __('Cluster analysis stalled and was cancelled by administrator.');

        $snapshot = self::snapshot($progressId);
        ClusterAnalysisDebugLog::error($progressId, 'cluster.wait.aborted', array_merge($snapshot, self::timing($progressId) ?? []));
        self::markFailed($progressId, $reason);

        $removedWait = self::removeJobsForProgress(ClusterQueues::name('wait'), $progressId);
        $removedChild = self::removeJobsForProgress(ClusterQueues::name('child'), $progressId);
        $removedRows = (int) ClusterQueue::where('progress_id', $progressId)->delete();

        return [
            'progress_id' => $progressId,
            'removed_queue_rows' => $removedRows,
            'removed_wait_jobs' => $removedWait,
            'removed_child_jobs' => $removedChild,
        ];
    }

    public static function abortIfStale(string $progressId): bool
    {
        if (! self::isStale($progressId)) {
            return false;
        }

        self::cancelProgress($progressId, __('Cluster analysis stalled: no progress for too long.'));

        return true;
    }

    public static function removeJobsForProgress(string $queue, string $progressId): int
    {
        if ($progressId === '') {
            return 0;
        }

        return (int) DB::table('jobs')
            ->where('queue', $queue)
            ->where('payload', 'like', '%' . $progressId . '%')
            ->delete();
    }

    public static function hasActiveJobs(string $progressId): bool
    {
        $timing = self::timing($progressId) ?? [];

        return ($timing['wait_jobs'] ?? 0) > 0 || ($timing['child_jobs'] ?? 0) > 0;
    }

    public static function isAbandoned(string $progressId): bool
    {
        if ($progressId === '' || self::isComplete($progressId) || self::getFailed($progressId)) {
            return false;
        }

        if (self::hasActiveJobs($progressId)) {
            return false;
        }

        $snapshot = self::snapshot($progressId);
        $done = (int) ($snapshot['phrases_done'] ?? 0);

        return $done === 0;
    }

    public static function isDeadSession(string $progressId): bool
    {
        if ($progressId === '' || self::isComplete($progressId)) {
            return false;
        }

        if (self::hasActiveJobs($progressId)) {
            return false;
        }

        return self::isOrphan($progressId) || self::isAbandoned($progressId) || self::isStale($progressId) || self::getFailed($progressId);
    }

    public static function isOrphan(string $progressId): bool
    {
        if ($progressId === '' || self::isComplete($progressId) || self::getFailed($progressId)) {
            return false;
        }

        $snapshot = self::snapshot($progressId);
        $timing = self::timing($progressId) ?? [];
        $done = (int) ($snapshot['phrases_done'] ?? 0);
        $total = (int) ($snapshot['phrases_total'] ?? 0);

        if (($timing['wait_jobs'] ?? 0) > 0 || ($timing['child_jobs'] ?? 0) > 0) {
            return false;
        }

        return $total > 0 && $done >= $total;
    }

    public static function resolveStatus(string $progressId): string
    {
        if (self::isComplete($progressId)) {
            return 'complete';
        }

        if (self::getFailed($progressId)) {
            return 'failed';
        }

        if (self::isOrphan($progressId)) {
            return 'orphan';
        }

        if (self::isAbandoned($progressId)) {
            return 'abandoned';
        }

        if (self::isStale($progressId)) {
            return 'stuck';
        }

        if (self::hasActiveJobs($progressId)) {
            return 'running';
        }

        return 'orphan';
    }

    /**
     * @return array{deleted_progress:int,deleted_rows:int}
     */
    public static function purgeOrphans(int $olderThanDays = 0): array
    {
        $cutoff = $olderThanDays > 0 ? now()->subDays($olderThanDays) : null;
        $deletedProgress = 0;
        $deletedRows = 0;

        foreach (self::listActiveProgressIds(200) as $item) {
            $status = (string) ($item['status'] ?? '');
            if (! in_array($status, ['orphan', 'abandoned', 'stuck'], true)) {
                continue;
            }

            if ($cutoff !== null && ! empty($item['started_at'])) {
                if (Carbon::parse($item['started_at'])->greaterThan($cutoff)) {
                    continue;
                }
            }

            $result = self::cancelProgress(
                (string) $item['progress_id'],
                __('Cluster dead session cleaned by administrator.')
            );
            $deletedProgress++;
            $deletedRows += (int) ($result['removed_queue_rows'] ?? 0);
        }

        return [
            'deleted_progress' => $deletedProgress,
            'deleted_rows' => $deletedRows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function orphanSummary(): array
    {
        $orphanProgress = 0;
        $orphanRows = 0;

        foreach (self::listActiveProgressIds(200) as $item) {
            if (! in_array($item['status'] ?? '', ['orphan', 'abandoned', 'stuck'], true)) {
                continue;
            }

            $orphanProgress++;
            $orphanRows += (int) ($item['phrases_done'] ?? 0);
        }

        return [
            'orphan_progress' => $orphanProgress,
            'orphan_rows' => $orphanRows,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function listActiveProgressIds(int $limit = 50): array
    {
        $fromRows = ClusterQueue::query()
            ->select('progress_id')
            ->selectRaw('COUNT(*) as phrases_done')
            ->selectRaw('MIN(created_at) as started_at')
            ->selectRaw('MAX(created_at) as last_row_at')
            ->groupBy('progress_id')
            ->orderByDesc('last_row_at')
            ->limit($limit)
            ->get();

        $waitIds = DB::table('jobs')
            ->where('queue', ClusterQueues::name('wait'))
            ->orderByDesc('id')
            ->limit($limit)
            ->pluck('payload')
            ->map(static function ($payload) {
                if (preg_match('/progressId";s:32:"([a-f0-9]{32})"/', (string) $payload, $m)) {
                    return $m[1];
                }

                return null;
            })
            ->filter()
            ->unique()
            ->values();

        $ids = $fromRows->pluck('progress_id')
            ->merge($waitIds)
            ->unique()
            ->take($limit);

        $out = [];

        foreach ($ids as $progressId) {
            $row = $fromRows->firstWhere('progress_id', $progressId);
            $snapshot = self::snapshot($progressId);
            $timing = self::timing($progressId) ?? [];
            $failed = self::getFailed($progressId);
            $status = self::resolveStatus($progressId);

            $meta = self::parseProgressMeta($progressId);

            $out[] = [
                'progress_id' => $progressId,
                'phrases_done' => (int) ($snapshot['phrases_done'] ?? 0),
                'phrases_pending' => (int) ($snapshot['phrases_pending'] ?? 0),
                'phrases_total' => (int) ($snapshot['phrases_total'] ?? 0),
                'started_at' => $row->started_at ?? $timing['started_at'] ?? $timing['wait_job_at'] ?? null,
                'last_row_at' => $row->last_row_at ?? $timing['last_row_at'] ?? null,
                'wait_jobs' => (int) ($timing['wait_jobs'] ?? 0),
                'child_jobs' => (int) ($timing['child_jobs'] ?? 0),
                'host' => $meta['host'] ?? null,
                'user_email' => $meta['user_email'] ?? null,
                'status' => $status,
                'failed_message' => $failed['message'] ?? null,
                'note' => $status === 'orphan'
                    ? __('Cluster orphan note')
                    : ($status === 'abandoned'
                        ? __('Cluster abandoned note')
                        : ($meta['host'] === null && $meta['user_email'] === null ? __('Cluster meta unknown note') : null)),
            ];
        }

        $out = array_values(array_filter($out, static function (array $item) {
            return ($item['status'] ?? '') !== 'complete';
        }));

        usort($out, static function (array $a, array $b) {
            $order = ['stuck' => 0, 'abandoned' => 1, 'orphan' => 2, 'running' => 3, 'failed' => 4];

            return ($order[$a['status']] ?? 9) <=> ($order[$b['status']] ?? 9);
        });

        return $out;
    }

    /**
     * @return array{host:?string,user_email:?string}
     */
    protected static function parseProgressMeta(string $progressId): array
    {
        $payload = DB::table('jobs')
            ->where(function ($query) use ($progressId) {
                $query->where('queue', ClusterQueues::name('wait'))
                    ->orWhere('queue', ClusterQueues::name('child'))
                    ->orWhere('queue', ClusterQueues::name('main'));
            })
            ->where('payload', 'like', '%' . $progressId . '%')
            ->orderByDesc('id')
            ->value('payload');

        if (! $payload) {
            $result = ClusterResults::where('progress_id', $progressId)->first(['request']);
            if ($result && $result->request) {
                $request = json_decode($result->request, true) ?: [];

                return [
                    'host' => $request['host'] ?? $request['domain'] ?? null,
                    'user_email' => null,
                ];
            }

            return ['host' => null, 'user_email' => null];
        }

        $host = null;
        $email = null;

        if (preg_match('/"host";s:\d+:"([^"]+)"/', (string) $payload, $m)) {
            $host = $m[1];
        } elseif (preg_match('/"domain";s:\d+:"([^"]+)"/', (string) $payload, $m)) {
            $host = $m[1];
        }

        if (preg_match('/"email";s:\d+:"([^"]+)"/', (string) $payload, $m)) {
            $email = $m[1];
        }

        return ['host' => $host, 'user_email' => $email];
    }
}
