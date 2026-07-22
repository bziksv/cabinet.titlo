<?php

namespace App\Support;

use App\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Массовое удаление давно не заходивших пользователей.
 * FK CASCADE покрывает основное; таблицы без FK чистим явно.
 */
class InactiveUsersPurge
{
    /** @var list<string> */
    private $excludeRoles;

    /**
     * Таблицы с user_id без FK на users — иначе останутся сироты.
     *
     * @var list<string>
     */
    private const ORPHAN_USER_ID_TABLES = [
        'domain_information_check_logs',
        'domain_monitoring_check_logs',
        'domain_records_histories',
        'domain_records_usages',
        'esenin_text_check_sessions',
        'esenin_text_check_usages',
        'html_editor_presets',
        'html_editor_public_shares',
        'index_check_histories',
        'index_check_usages',
        'monitoring_v2_user_preferences',
        'notification_dispatch_logs',
        'phrase_commerce_histories',
        'phrase_commerce_usages',
        'search_suggestions_histories',
        'search_suggestions_usages',
        'sessions',
        'site_audit_ignores',
        'site_audit_schedules',
        'site_types_histories',
        'site_types_usages',
        'text_uniqueness_histories',
        'text_uniqueness_usages',
        'users_jobs',
    ];

    public function __construct()
    {
        $this->excludeRoles = array_values(array_unique(array_merge(
            (array) config('cabinet-finance-admin.exclude_admin_roles', ['admin', 'Super Admin']),
            ['admin', 'Super Admin']
        )));
    }

    /**
     * Пользователи без входа дольше $years лет (null last_online_at — если аккаунт старше порога).
     */
    public function queryInactiveYears(int $years): Builder
    {
        $years = max(1, min(20, $years));
        $threshold = Carbon::now()->subYears($years);

        app(PermissionRegistrar::class)->setPermissionsTeamId(1);

        $demoEmail = strtolower(trim((string) config('cabinet-demo-cabinet.email', 'demo@cabinet.titlo.ru')));

        return User::query()
            ->where(static function (Builder $q) use ($threshold) {
                $q->where(static function (Builder $inner) use ($threshold) {
                    $inner->whereNotNull('last_online_at')
                        ->where('last_online_at', '<', $threshold);
                })->orWhere(static function (Builder $inner) use ($threshold) {
                    $inner->whereNull('last_online_at')
                        ->where('created_at', '<', $threshold);
                });
            })
            ->when($demoEmail !== '', static function (Builder $q) use ($demoEmail) {
                $q->whereRaw('LOWER(email) != ?', [$demoEmail]);
            })
            ->whereDoesntHave('roles', function (Builder $q) {
                $q->whereIn('name', $this->excludeRoles);
            })
            ->when(Auth::id(), static function (Builder $q, $authId) {
                $q->where('id', '!=', (int) $authId);
            });
    }

    /**
     * @return array{
     *   years: int,
     *   count: int,
     *   sample: list<array{id:int,email:string,last_online_at:?string}>,
     *   storage: array{est_mb: float, est_label: string, rows: int, modules: list<array{label:string,rows:int,est_mb:float}>, note: string}
     * }
     */
    public function preview(int $years, int $sampleLimit = 8): array
    {
        $query = $this->queryInactiveYears($years);
        $count = (clone $query)->count();
        $sample = (clone $query)
            ->orderBy('last_online_at')
            ->orderBy('id')
            ->limit(max(1, min(50, $sampleLimit)))
            ->get(['id', 'email', 'last_online_at'])
            ->map(static function (User $u) {
                return [
                    'id' => (int) $u->id,
                    'email' => (string) $u->email,
                    'last_online_at' => $u->last_online_at
                        ? $u->last_online_at->format('d.m.Y H:i')
                        : null,
                ];
            })
            ->all();

        $ids = (clone $query)->orderBy('id')->pluck('id')->map(static function ($id) {
            return (int) $id;
        })->all();

        return [
            'years' => max(1, $years),
            'count' => $count,
            'sample' => $sample,
            'storage' => $this->estimateStorageForUserIds($ids),
        ];
    }

    /**
     * Полное удаление одного пользователя + сирот без FK.
     */
    public function deleteUserCompletely(User $user): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(1);

        if ((int) $user->id === (int) Auth::id()) {
            throw new \RuntimeException('Cannot delete self');
        }
        if ($user->hasAnyRole($this->excludeRoles)) {
            throw new \RuntimeException('Protected role');
        }
        $demoEmail = strtolower(trim((string) config('cabinet-demo-cabinet.email', 'demo@cabinet.titlo.ru')));
        if ($demoEmail !== '' && strtolower((string) $user->email) === $demoEmail) {
            throw new \RuntimeException('Protected demo cabinet user');
        }

        $userId = (int) $user->id;

        DB::transaction(function () use ($user, $userId) {
            $this->deleteMonitoringSearchenginesForCreator($userId);
            $this->deleteSiteAuditForUser($userId);
            $this->deleteOrphanUserIdRows($userId);

            $user->roles()->detach();
            if (method_exists($user, 'permissions')) {
                $user->permissions()->detach();
            }
            $user->delete();
        });

        UserStorageFootprintService::forget($userId);
    }

    /**
     * @return array{years: int, deleted: int, failed: int, errors: list<string>}
     */
    public function purge(int $years, int $chunkSize = 25): array
    {
        $years = max(1, min(20, $years));
        $chunkSize = max(1, min(100, $chunkSize));
        $deleted = 0;
        $failed = 0;
        /** @var list<string> $errors */
        $errors = [];
        /** @var list<int> $skipIds */
        $skipIds = [];

        app(PermissionRegistrar::class)->setPermissionsTeamId(1);

        while (true) {
            $ids = $this->queryInactiveYears($years)
                ->when($skipIds !== [], static function (Builder $q) use ($skipIds) {
                    $q->whereNotIn('id', $skipIds);
                })
                ->orderBy('id')
                ->limit($chunkSize)
                ->pluck('id')
                ->all();

            if ($ids === []) {
                break;
            }

            $deletedBefore = $deleted;
            foreach ($ids as $id) {
                try {
                    $user = User::query()->find($id);
                    if (! $user) {
                        continue;
                    }
                    $this->deleteUserCompletely($user);
                    $deleted++;
                } catch (Throwable $e) {
                    $failed++;
                    $skipIds[] = (int) $id;
                    if (count($errors) < 20) {
                        $errors[] = '#' . $id . ': ' . mb_substr($e->getMessage(), 0, 200);
                    }
                }
            }

            if ($deleted === $deletedBefore && $ids !== []) {
                break;
            }
        }

        return [
            'years' => $years,
            'deleted' => $deleted,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * @param int[] $userIds
     * @return array{est_mb: float, est_label: string, rows: int, modules: list<array{label:string,rows:int,est_mb:float}>, note: string}
     */
    public function estimateStorageForUserIds(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if ($userIds === []) {
            return [
                'est_mb' => 0.0,
                'est_label' => '0',
                'rows' => 0,
                'modules' => [],
                'note' => __('Users inactive purge storage empty'),
            ];
        }

        $modules = [];
        $totalRows = 0;
        $totalBytes = 0;

        foreach (UserStorageFootprintService::moduleDefinitions() as $def) {
            $table = (string) ($def['table'] ?? '');
            if ($table === '' || ! Schema::hasTable($table)) {
                continue;
            }
            $count = $this->bulkCountModule($def, $userIds);
            if ($count <= 0) {
                continue;
            }
            $avgBytes = (int) ($def['avg_row_bytes'] ?? 256);
            $bytes = $count * $avgBytes;
            $modules[] = [
                'label' => (string) ($def['label'] ?? $table),
                'rows' => $count,
                'est_mb' => round($bytes / 1024 / 1024, 2),
            ];
            $totalRows += $count;
            $totalBytes += $bytes;
        }

        $estMb = round($totalBytes / 1024 / 1024, 2);

        return [
            'est_mb' => $estMb,
            'est_label' => $this->formatMbLabel($estMb, $totalRows),
            'rows' => $totalRows,
            'modules' => $modules,
            'note' => __('Users inactive purge storage note'),
        ];
    }

    /**
     * @param array<string, mixed> $def
     * @param int[] $userIds
     */
    private function bulkCountModule(array $def, array $userIds): int
    {
        $table = $def['table'];
        $type = $def['type'] ?? 'column';
        $total = 0;

        foreach (array_chunk($userIds, 500) as $chunk) {
            if ($type === 'monitoring_keywords_by_creator') {
                $total += (int) DB::table('monitoring_keywords as k')
                    ->join('monitoring_projects as p', 'p.id', '=', 'k.monitoring_project_id')
                    ->whereIn('p.creator', $chunk)
                    ->count();
                continue;
            }
            if ($type === 'monitoring_positions_by_creator') {
                $total += (int) DB::table('monitoring_positions as pos')
                    ->join('monitoring_keywords as k', 'k.id', '=', 'pos.monitoring_keyword_id')
                    ->join('monitoring_projects as p', 'p.id', '=', 'k.monitoring_project_id')
                    ->whereIn('p.creator', $chunk)
                    ->count();
                continue;
            }

            $column = (string) ($def['column'] ?? 'user_id');
            $total += (int) DB::table($table)->whereIn($column, $chunk)->count();
        }

        return $total;
    }

    private function formatMbLabel(float $estMb, int $rows): string
    {
        if ($rows <= 0) {
            return '0';
        }
        if ($estMb >= 1024) {
            return '~' . number_format($estMb / 1024, 2, ',', ' ') . ' GB · '
                . number_format($rows, 0, ',', ' ') . ' ' . __('rows');
        }
        if ($estMb >= 1) {
            return '~' . number_format($estMb, 1, ',', ' ') . ' MB · '
                . number_format($rows, 0, ',', ' ') . ' ' . __('rows');
        }

        return '~' . number_format($estMb * 1024, 0, ',', ' ') . ' KB · '
            . number_format($rows, 0, ',', ' ') . ' ' . __('rows');
    }

    private function deleteMonitoringSearchenginesForCreator(int $userId): void
    {
        if (! Schema::hasTable('monitoring_searchengines') || ! Schema::hasTable('monitoring_projects')) {
            return;
        }

        $projectIds = DB::table('monitoring_projects')->where('creator', $userId)->pluck('id');
        if ($projectIds->isEmpty()) {
            return;
        }

        // У monitoring_searchengines нет FK на projects — иначе остаются сироты.
        DB::table('monitoring_searchengines')->whereIn('monitoring_project_id', $projectIds)->delete();
    }

    private function deleteSiteAuditForUser(int $userId): void
    {
        if (! Schema::hasTable('site_audit_projects')) {
            return;
        }

        $projectIds = DB::table('site_audit_projects')->where('user_id', $userId)->pluck('id');
        if ($projectIds->isNotEmpty() && Schema::hasTable('site_audit_crawls')) {
            $crawlIds = DB::table('site_audit_crawls')->whereIn('project_id', $projectIds)->pluck('id');
            if ($crawlIds->isNotEmpty()) {
                foreach (['site_audit_findings', 'site_audit_pages', 'site_audit_crawl_stats'] as $child) {
                    if (Schema::hasTable($child)) {
                        DB::table($child)->whereIn('crawl_id', $crawlIds)->delete();
                    }
                }
            }
            DB::table('site_audit_crawls')->whereIn('project_id', $projectIds)->delete();
        }

        if (Schema::hasTable('site_audit_schedules')) {
            DB::table('site_audit_schedules')->where('user_id', $userId)->delete();
        }
        if (Schema::hasTable('site_audit_ignores')) {
            DB::table('site_audit_ignores')->where('user_id', $userId)->delete();
        }
        DB::table('site_audit_projects')->where('user_id', $userId)->delete();
    }

    private function deleteOrphanUserIdRows(int $userId): void
    {
        foreach (self::ORPHAN_USER_ID_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            try {
                DB::table($table)->where('user_id', $userId)->delete();
            } catch (Throwable $e) {
                // колонка может отличаться — не валим всё удаление
            }
        }
    }
}
