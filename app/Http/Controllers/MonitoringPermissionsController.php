<?php

namespace App\Http\Controllers;

use App\Support\MonitoringPermissionsCatalog;
use App\User;
use App\Classes\Monitoring\MonitoringProjectListSerializer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class MonitoringPermissionsController extends Controller
{
    public function __construct()
    {
        $this->middleware(['role:Super Admin|admin']);
    }

    public function index()
    {
        $roles = MonitoringPermissionsCatalog::orderedRoles();
        $permissions = MonitoringPermissionsCatalog::permissions();
        $groupedPermissions = MonitoringPermissionsCatalog::groupedPermissions($permissions);
        $roleMeta = MonitoringPermissionsCatalog::roleMeta();
        $permissionHints = MonitoringPermissionsCatalog::permissionHints();

        $roleStats = $roles->map(static function (Role $role) use ($permissions) {
            $enabled = MonitoringPermissionsCatalog::roleEnabledCount($role, $permissions);

            return [
                'role' => $role,
                'enabled' => $enabled,
                'total' => $permissions->count(),
            ];
        });

        return view('monitoring.permissions', [
            'roles' => $roles,
            'permissions' => $permissions,
            'groupedPermissions' => $groupedPermissions,
            'roleMeta' => $roleMeta,
            'permissionHints' => $permissionHints,
            'roleStats' => $roleStats,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $permissions = $request->input('permissions', []);
        if (!is_array($permissions)) {
            return response()->json([
                'status' => false,
                'message' => __('Monitoring perm save invalid'),
            ], 422);
        }

        foreach ($permissions as $roleName => $permissionMap) {
            if (!is_array($permissionMap)) {
                continue;
            }
            $role = Role::where('name', $roleName)->first();
            if (!$role) {
                continue;
            }
            $role->syncPermissions(array_keys($permissionMap));
        }

        return response()->json([
            'status' => true,
            'message' => __('Monitoring perm saved'),
        ]);
    }

    public function getRoleOptions()
    {
        $roles = MonitoringPermissionsCatalog::orderedRoles();

        $roles->transform(static function ($item) {
            $meta = MonitoringPermissionsCatalog::roleMeta()[$item['name']] ?? null;
            $item['val'] = $item['name'];
            $item['text'] = $meta ? __($meta['title_key']) : ($item['title'] ?: $item['name']);

            return $item;
        });

        return $roles;
    }

    public function syncProjectRoles(Request $request): void
    {
        $id = (int) $request->input('project');
        $role = (string) $request->input('status');

        $user = User::findOrFail($request->input('user'));

        apply_team_permissions($id);

        if ($role !== '') {
            $user->syncRoles([$role]);
            $statusCode = MonitoringPermissionsCatalog::statusCodeForRole($role);
            $statusId = MonitoringProjectUserStatusController::getIdStatusByCode($statusCode);
            $user->monitoringProjects()->updateExistingPivot($id, ['status' => $statusId]);
        }

        if (Auth::id()) {
            MonitoringProjectListSerializer::forgetCacheForUser((int) Auth::id());
        }
    }
}
