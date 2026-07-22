<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddSiteAuditMenuAndPermission extends Migration
{
    /** После «Проверка индексации» */
    private const PARENT_PROJECT_ID = 40;

    public function up(): void
    {
        $this->seedMenuItem();
        $this->seedPermission();
    }

    public function down(): void
    {
        $this->removeMenuItem();
        $this->removePermission();
    }

    private function seedMenuItem(): void
    {
        if (! Schema::hasTable('main_projects')) {
            return;
        }

        $exists = DB::table('main_projects')
            ->where('link', 'like', '%/site-audit%')
            ->exists();

        if ($exists) {
            return;
        }

        $parent = DB::table('main_projects')->where('id', self::PARENT_PROJECT_ID)->first();
        if ($parent === null) {
            $parent = DB::table('main_projects')->where('id', 13)->first();
        }
        if ($parent === null) {
            return;
        }

        $maxPos = (int) DB::table('main_projects')->max('position');
        $position = max($maxPos + 1, ((int) ($parent->position ?? 170)) + 1);

        $newId = DB::table('main_projects')->insertGetId([
            'access' => $parent->access,
            'controller' => "SiteAuditController@index\r\n",
            'color' => '#134e4a',
            'title' => 'Site audit',
            'description' => 'Технический аудит сайта: sitemap, HTTP, мета-теги, canonical, noindex.',
            'link' => 'https://lk.redbox.su/site-audit',
            'icon' => '<i class="fas fa-tasks"></i>',
            'show' => 1,
            'position' => $position,
            'buttons' => $parent->buttons ?? '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->insertAfterProjectIdInUserMenus((int) $parent->id, (int) $newId);
    }

    private function removeMenuItem(): void
    {
        if (! Schema::hasTable('main_projects')) {
            return;
        }

        $ids = DB::table('main_projects')
            ->where('link', 'like', '%/site-audit%')
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        $this->purgeMenuPositions($ids->all());
        DB::table('main_projects')->whereIn('id', $ids)->delete();
    }

    private function seedPermission(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        if (DB::table('permissions')->where('name', 'Site audit')->exists()) {
            return;
        }

        DB::table('permissions')->insert([
            'name' => 'Site audit',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assignPermissionLikeIndexCheck();
    }

    private function removePermission(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $id = DB::table('permissions')->where('name', 'Site audit')->value('id');
        if (! $id) {
            return;
        }

        DB::table('role_has_permissions')->where('permission_id', $id)->delete();
        DB::table('permissions')->where('id', $id)->delete();
    }

    private function assignPermissionLikeIndexCheck(): void
    {
        if (! Schema::hasTable('role_has_permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $newId = DB::table('permissions')->where('name', 'Site audit')->value('id');
        if (! $newId) {
            return;
        }

        $likeId = DB::table('permissions')->where('name', 'Index check')->value('id')
            ?: DB::table('permissions')->where('name', 'Http headers')->value('id');

        $roleIds = collect();
        if ($likeId) {
            $roleIds = $roleIds->merge(
                DB::table('role_has_permissions')
                    ->where('permission_id', $likeId)
                    ->pluck('role_id')
            );
        }

        // тарифные роли из access Index check
        $roleIds = $roleIds->merge(
            DB::table('roles')
                ->whereIn('name', [
                    'admin', 'user', 'Super Admin',
                    'Free', 'Optimal', 'Maximum', 'Ultimate',
                ])
                ->pluck('id')
        )->unique()->filter();

        foreach ($roleIds as $roleId) {
            $exists = DB::table('role_has_permissions')
                ->where('permission_id', $newId)
                ->where('role_id', $roleId)
                ->exists();

            if (! $exists) {
                DB::table('role_has_permissions')->insert([
                    'permission_id' => $newId,
                    'role_id' => $roleId,
                ]);
            }
        }
    }

    private function insertAfterProjectIdInUserMenus(int $afterId, int $newId): void
    {
        if (! Schema::hasTable('menu_items_position')) {
            return;
        }

        DB::table('menu_items_position')->orderBy('id')->chunk(50, function ($rows) use ($afterId, $newId) {
            foreach ($rows as $row) {
                if (empty($row->positions)) {
                    continue;
                }

                $positions = json_decode($row->positions, true);
                if (! is_array($positions)) {
                    continue;
                }

                if ($this->positionsContainId($positions, $newId)) {
                    continue;
                }

                $changed = false;
                $updated = $this->insertAfterIdInPositions($positions, $afterId, $newId, $changed);

                if ($changed) {
                    DB::table('menu_items_position')
                        ->where('id', $row->id)
                        ->update(['positions' => json_encode($updated)]);
                }
            }
        });
    }

    private function positionsContainId(array $positions, int $searchId): bool
    {
        foreach ($positions as $item) {
            if (isset($item[0]) && is_array($item[0]) && ! empty($item[0]['dir'])) {
                foreach ($item as $entry) {
                    if (isset($entry['id']) && (int) $entry['id'] === $searchId) {
                        return true;
                    }
                }
                continue;
            }

            if (isset($item['id']) && (int) $item['id'] === $searchId) {
                return true;
            }
        }

        return false;
    }

    private function insertAfterIdInPositions(array $positions, int $afterId, int $newId, bool &$changed): array
    {
        $result = [];

        foreach ($positions as $item) {
            if (isset($item[0]) && is_array($item[0]) && ! empty($item[0]['dir'])) {
                $group = [];
                $groupChanged = false;

                foreach ($item as $entry) {
                    if (isset($entry['dir'])) {
                        $group[] = $entry;
                        continue;
                    }

                    $group[] = $entry;

                    if (isset($entry['id']) && (int) $entry['id'] === $afterId) {
                        $group[] = ['id' => $newId];
                        $groupChanged = true;
                        $changed = true;
                    }
                }

                if (count($group) > 1 || $groupChanged) {
                    $result[] = $group;
                }

                continue;
            }

            $result[] = $item;

            if (isset($item['id']) && (int) $item['id'] === $afterId) {
                $result[] = ['id' => $newId];
                $changed = true;
            }
        }

        return $result;
    }

    private function purgeMenuPositions(array $removeIds): void
    {
        if (! Schema::hasTable('menu_items_position')) {
            return;
        }

        DB::table('menu_items_position')->orderBy('id')->chunk(50, function ($rows) use ($removeIds) {
            foreach ($rows as $row) {
                if (empty($row->positions)) {
                    continue;
                }

                $positions = json_decode($row->positions, true);
                if (! is_array($positions)) {
                    continue;
                }

                $changed = false;
                $filtered = $this->filterPositions($positions, $removeIds, $changed);

                if ($changed) {
                    DB::table('menu_items_position')
                        ->where('id', $row->id)
                        ->update(['positions' => json_encode($filtered)]);
                }
            }
        });
    }

    private function filterPositions(array $positions, array $removeIds, bool &$changed): array
    {
        $result = [];

        foreach ($positions as $item) {
            if (isset($item[0]) && is_array($item[0]) && ! empty($item[0]['dir'])) {
                $group = [];
                foreach ($item as $entry) {
                    if (isset($entry['dir'])) {
                        $group[] = $entry;
                        continue;
                    }
                    if (isset($entry['id']) && in_array((int) $entry['id'], $removeIds, true)) {
                        $changed = true;
                        continue;
                    }
                    $group[] = $entry;
                }
                if (count($group) > 1) {
                    $result[] = $group;
                } else {
                    $changed = true;
                }
                continue;
            }

            if (isset($item['id']) && in_array((int) $item['id'], $removeIds, true)) {
                $changed = true;
                continue;
            }

            $result[] = $item;
        }

        return $result;
    }
}
