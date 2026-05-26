<?php

namespace App\Support;

use App\DomainMonitoring;
use App\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\Permission\PermissionRegistrar;

class SiteMonitoringAdminStats
{
    private const TARIFF_ORDER = [
        'Free' => 1,
        'Optimal' => 2,
        'Ultimate' => 3,
        'Maximum' => 4,
    ];

    /**
     * @return array{summary: array<string, int|array<int, int>>, rows: array<int, array<string, mixed>>}
     */
    public static function snapshot(): array
    {
        $projects = DomainMonitoring::query()
            ->with([
                'user' => static function ($query) {
                    $query->select('id', 'email', 'name', 'telegram_bot_active', 'chat_id', 'last_online_at');
                },
                'user.roles:id,name',
            ])
            ->orderBy('project_name')
            ->get();

        app(PermissionRegistrar::class)->setPermissionsTeamId(1);

        $timingBreakdown = [];
        foreach ([5, 10, 15, 20, 30, 60] as $minutes) {
            $timingBreakdown[$minutes] = 0;
        }

        $rows = [];
        foreach ($projects as $project) {
            $user = $project->user;
            $roleNames = $user ? $user->getRoleNames() : collect();
            $tariffCode = self::tariffLabel($roleNames);
            $lastOnline = $user && $user->last_online_at
                ? Carbon::parse($user->last_online_at)
                : null;

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
                'on_free' => $roleNames->contains('Free'),
                'telegram' => $user && $user->telegram_bot_active && $user->chat_id,
                'project_id' => $project->id,
                'project_name' => $project->project_name,
                'link' => $project->link,
                'timing' => (int) $project->timing,
                'waiting_time' => (int) $project->waiting_time,
                'broken' => (bool) $project->broken,
                'send_notification' => (bool) $project->send_notification,
                'status' => $project->status,
                'status_label' => $project->status ? __($project->status) : '',
                'code' => $project->code,
                'uptime_percent' => $project->uptime_percent,
                'last_check' => $project->last_check
                    ? Carbon::parse($project->last_check)->format('d.m.Y H:i')
                    : null,
                'last_check_sort' => $project->last_check
                    ? Carbon::parse($project->last_check)->timestamp
                    : 0,
            ];
        }

        foreach ($projects as $project) {
            $timing = (int) $project->timing;
            if (array_key_exists($timing, $timingBreakdown)) {
                $timingBreakdown[$timing]++;
            }
        }

        $distinctUsers = $projects->pluck('user_id')->unique()->count();

        return [
            'summary' => [
                'projects_total' => $projects->count(),
                'projects_notify_on' => $projects->where('send_notification', 1)->count(),
                'projects_broken' => $projects->where('broken', true)->count(),
                'users_with_projects' => $distinctUsers,
                'users_telegram' => User::query()
                    ->where('telegram_bot_active', 1)
                    ->whereNotNull('chat_id')
                    ->count(),
                'timing_breakdown' => $timingBreakdown,
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
