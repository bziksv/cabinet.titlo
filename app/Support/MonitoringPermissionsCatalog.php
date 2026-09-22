<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Справочник ролей и прав внутри проекта мониторинга (/monitoring/permissions).
 */
class MonitoringPermissionsCatalog
{
    /** @var array<string, int> */
    private const ROLE_ORDER = [
        'admin_monitoring' => 10,
        'team_lead_monitoring' => 20,
        'project_manager_monitoring' => 30,
        'seo_monitoring' => 40,
        'viewer_monitoring' => 50,
    ];

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function roleMeta(): array
    {
        return [
            'admin_monitoring' => [
                'badge' => 'danger',
                'title_key' => 'Monitoring perm role admin title',
                'lead_key' => 'Monitoring perm role admin lead',
                'who_key' => 'Monitoring perm role admin who',
            ],
            'team_lead_monitoring' => [
                'badge' => 'primary',
                'title_key' => 'Monitoring perm role team lead title',
                'lead_key' => 'Monitoring perm role team lead lead',
                'who_key' => 'Monitoring perm role team lead who',
            ],
            'project_manager_monitoring' => [
                'badge' => 'info',
                'title_key' => 'Monitoring perm role pm title',
                'lead_key' => 'Monitoring perm role pm lead',
                'who_key' => 'Monitoring perm role pm who',
            ],
            'seo_monitoring' => [
                'badge' => 'success',
                'title_key' => 'Monitoring perm role seo title',
                'lead_key' => 'Monitoring perm role seo lead',
                'who_key' => 'Monitoring perm role seo who',
            ],
            'viewer_monitoring' => [
                'badge' => 'secondary',
                'title_key' => 'Monitoring perm role viewer title',
                'lead_key' => 'Monitoring perm role viewer lead',
                'who_key' => 'Monitoring perm role viewer who',
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function permissionGroups(): array
    {
        return [
            'project' => [
                'icon' => 'bi-building',
                'title_key' => 'Monitoring perm group project',
                'permission_names' => [
                    'edit_project_monitoring',
                    'export_report_monitoring',
                    'update_occurrence_monitoring',
                    'leave_project_monitoring',
                ],
            ],
            'team' => [
                'icon' => 'bi-people',
                'title_key' => 'Monitoring perm group team',
                'permission_names' => [
                    'add_user_to_project_monitoring',
                    'delete_user_from_project_monitoring',
                    'change_user_status_project_monitoring',
                ],
            ],
            'groups' => [
                'icon' => 'bi-folder',
                'title_key' => 'Monitoring perm group groups',
                'permission_names' => [
                    'create_groups_monitoring',
                    'edit_groups_monitoring',
                    'delete_groups_monitoring',
                ],
            ],
            'keywords' => [
                'icon' => 'bi-list-ul',
                'title_key' => 'Monitoring perm group keywords',
                'permission_names' => [
                    'create_query_monitoring',
                    'edit_query_monitoring',
                    'delete_query_monitoring',
                    'form_keyword_monitoring',
                    'form_relative_url_monitoring',
                    'form_target_monitoring',
                    'form_group_monitoring',
                ],
            ],
            'positions' => [
                'icon' => 'bi-arrow-repeat',
                'title_key' => 'Monitoring perm group positions',
                'permission_names' => [
                    'update_position_monitoring',
                    'update_position_all_monitoring',
                ],
            ],
            'budget' => [
                'icon' => 'bi-currency-exchange',
                'title_key' => 'Monitoring perm group budget',
                'permission_names' => [
                    'update_price_monitoring',
                    'update_budget_monitoring',
                ],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function permissionHints(): array
    {
        return [
            'edit_project_monitoring' => 'Monitoring perm hint edit project',
            'export_report_monitoring' => 'Monitoring perm hint export',
            'update_occurrence_monitoring' => 'Monitoring perm hint occurrence',
            'leave_project_monitoring' => 'Monitoring perm hint leave',
            'add_user_to_project_monitoring' => 'Monitoring perm hint add user',
            'delete_user_from_project_monitoring' => 'Monitoring perm hint remove user',
            'change_user_status_project_monitoring' => 'Monitoring perm hint change role',
            'create_groups_monitoring' => 'Monitoring perm hint create group',
            'edit_groups_monitoring' => 'Monitoring perm hint edit group',
            'delete_groups_monitoring' => 'Monitoring perm hint delete group',
            'create_query_monitoring' => 'Monitoring perm hint create query',
            'edit_query_monitoring' => 'Monitoring perm hint edit query',
            'delete_query_monitoring' => 'Monitoring perm hint delete query',
            'form_keyword_monitoring' => 'Monitoring perm hint form keyword',
            'form_relative_url_monitoring' => 'Monitoring perm hint form url',
            'form_target_monitoring' => 'Monitoring perm hint form target',
            'form_group_monitoring' => 'Monitoring perm hint form group',
            'update_position_monitoring' => 'Monitoring perm hint position selected',
            'update_position_all_monitoring' => 'Monitoring perm hint position all',
            'update_price_monitoring' => 'Monitoring perm hint price',
            'update_budget_monitoring' => 'Monitoring perm hint budget',
        ];
    }

    /**
     * @return Collection<int, Role>
     */
    public static function orderedRoles(): Collection
    {
        return Role::query()
            ->where('name', 'like', '%\_monitoring')
            ->get()
            ->sortBy(static function (Role $role) {
                return self::ROLE_ORDER[$role->name] ?? 999;
            })
            ->values();
    }

    /**
     * @return Collection<int, Permission>
     */
    public static function permissions(): Collection
    {
        return Permission::query()
            ->where('name', 'like', '%\_monitoring')
            ->orderBy('title')
            ->get();
    }

    /**
     * @param Collection<int, Permission> $permissions
     *
     * @return array<int, array<string, mixed>>
     */
    public static function groupedPermissions(Collection $permissions): array
    {
        $byName = $permissions->keyBy('name');
        $groups = [];

        foreach (self::permissionGroups() as $key => $group) {
            $items = [];
            foreach ($group['permission_names'] as $name) {
                if ($byName->has($name)) {
                    $items[] = $byName->get($name);
                }
            }
            if ($items !== []) {
                $groups[] = array_merge($group, ['key' => $key, 'permissions' => $items]);
            }
        }

        return $groups;
    }

    public static function roleEnabledCount(Role $role, Collection $permissions): int
    {
        $count = 0;
        foreach ($permissions as $permission) {
            if ($role->hasPermissionTo($permission)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Spatie-роль проекта → код бейджа в monitoring_user_status (список v2 / фильтры).
     */
    public static function statusCodeForRole(?string $roleName): string
    {
        $map = [
            'admin_monitoring' => 'OWNER',
            'team_lead_monitoring' => 'TL',
            'project_manager_monitoring' => 'PM',
            'seo_monitoring' => 'SEO',
            'viewer_monitoring' => 'EMPTY',
        ];

        $roleName = (string) $roleName;

        return $map[$roleName] ?? 'EMPTY';
    }
}
