<?php

namespace App\Support;

use App\MetaTag;
use App\MetaTagsHistory;
use App\MetaTagsSettings;
use App\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\Permission\PermissionRegistrar;

class MetaTagsAdminStats
{
    private const TARIFF_ORDER = [
        'Free' => 1,
        'Optimal' => 2,
        'Ultimate' => 3,
        'Maximum' => 4,
    ];

    /**
     * @return array{summary: array<string, int>, rows: array<int, array<string, mixed>>}
     */
    public static function snapshot(): array
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(1);

        $projects = MetaTag::query()
            ->with([
                'user' => static function ($query) {
                    $query->select('id', 'email', 'name', 'telegram_bot_active', 'chat_id', 'last_online_at');
                },
                'user.roles:id,name',
            ])
            ->withCount('histories')
            ->orderBy('name')
            ->get();

        $historyAgg = MetaTagsHistory::query()
            ->selectRaw('meta_tag_id, MAX(created_at) as last_at, MAX(CASE WHEN ideal = 1 THEN 1 ELSE 0 END) as has_ideal')
            ->groupBy('meta_tag_id')
            ->get()
            ->keyBy('meta_tag_id');

        $latestIds = MetaTagsHistory::query()
            ->selectRaw('MAX(id) as id')
            ->groupBy('meta_tag_id')
            ->pluck('id');

        $latestRows = MetaTagsHistory::query()
            ->whereIn('id', $latestIds)
            ->get(['id', 'meta_tag_id', 'created_at', 'quantity', 'errors_count', 'ideal'])
            ->keyBy('meta_tag_id');

        $pagesTotal = 0;
        $rows = [];

        foreach ($projects as $project) {
            $links = array_filter(preg_split("/[\r\n]+/", (string) $project->links) ?: []);
            $linksCount = count($links);
            $pagesTotal += $linksCount;

            $user = $project->user;
            $roleNames = $user ? $user->getRoleNames() : collect();
            $tariffCode = self::tariffLabel($roleNames);
            $lastOnline = $user && $user->last_online_at
                ? Carbon::parse($user->last_online_at)
                : null;

            $latest = $latestRows->get($project->id);
            $agg = $historyAgg->get($project->id);
            $lastSnapshot = null;
            if ($latest) {
                $lastSnapshot = Carbon::parse($latest->created_at);
            } elseif ($agg && $agg->last_at) {
                $lastSnapshot = Carbon::parse($agg->last_at);
            }

            $rows[] = [
                'user_id' => (int) $project->user_id,
                'email' => $user ? $user->email : '—',
                'name' => $user && $user->name ? $user->name : '',
                'last_online_at' => $lastOnline ? $lastOnline->format('d.m.Y H:i') : null,
                'last_online_human' => $lastOnline ? $lastOnline->diffForHumans() : null,
                'last_online_sort' => $lastOnline ? $lastOnline->timestamp : 0,
                'tariff_code' => $tariffCode,
                'tariff_label' => self::tariffDisplayName($tariffCode),
                'tariff_sort' => self::tariffSortKey($tariffCode),
                'telegram' => $user && $user->telegram_bot_active && $user->chat_id,
                'project_id' => (int) $project->id,
                'project_name' => $project->name,
                'links_count' => $linksCount,
                'period' => (int) $project->period,
                'status' => (bool) $project->status,
                'histories_count' => (int) $project->histories_count,
                'last_snapshot_at' => $lastSnapshot ? $lastSnapshot->format('d.m.Y H:i') : null,
                'last_snapshot_human' => $lastSnapshot ? $lastSnapshot->diffForHumans() : null,
                'last_snapshot_sort' => $lastSnapshot ? $lastSnapshot->timestamp : 0,
                'last_pages_in_snapshot' => $latest ? (int) $latest->quantity : null,
                'last_errors_count' => $latest && $latest->errors_count !== null ? (int) $latest->errors_count : null,
                'has_ideal' => (bool) (($agg->has_ideal ?? 0) || ($latest && $latest->ideal)),
            ];
        }

        $settings = new MetaTagsSettings();
        $retention = (int) ($settings->where('code', 'delete_records')->value('value') ?: 0);

        return [
            'summary' => [
                'projects_total' => $projects->count(),
                'projects_active' => $projects->where('status', 1)->count(),
                'users_with_projects' => $projects->pluck('user_id')->unique()->count(),
                'pages_total' => $pagesTotal,
                'snapshots_total' => MetaTagsHistory::query()->count(),
                'snapshots_7d' => MetaTagsHistory::query()
                    ->where('created_at', '>=', Carbon::now()->subDays(7))
                    ->count(),
                'retention_days' => $retention,
                'users_telegram' => User::query()
                    ->where('telegram_bot_active', 1)
                    ->whereNotNull('chat_id')
                    ->count(),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @param  Collection|\Illuminate\Database\Eloquent\Collection  $roleNames
     */
    private static function tariffLabel($roleNames): string
    {
        foreach (['Maximum', 'Ultimate', 'Optimal', 'Free'] as $code) {
            if ($roleNames->contains($code)) {
                return $code;
            }
        }

        $first = $roleNames->first(static function ($name) {
            return $name !== 'user';
        });

        return $first ?: '—';
    }

    private static function tariffDisplayName(string $code): string
    {
        if ($code === '—' || $code === '') {
            return '—';
        }

        return (string) __($code);
    }

    private static function tariffSortKey(string $code): int
    {
        return self::TARIFF_ORDER[$code] ?? 99;
    }
}
