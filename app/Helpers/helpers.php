<?php
use Illuminate\Support\Facades\Auth;

if (! function_exists('apply_team_permissions')) {
    function apply_team_permissions(int $id): void
    {
        $user = Auth::user();

        if ($user) {
            $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
            $teamChanged = $registrar->getPermissionsTeamId() !== $id;

            if ($teamChanged) {
                setPermissionsTeamId($id);
            }

            // Spatie: роли привязаны к team_id — без сброса relation меню пустое (getRoleNames() = []).
            if ($teamChanged
                || ! $user->relationLoaded('roles')
                || $user->roles->isEmpty()) {
                $user->unsetRelation('roles', 'permissions');
            }
        }
    }
}

if (! function_exists('apply_global_team_permissions')) {
    function apply_global_team_permissions(): void
    {
        $global_team = 1;
        apply_team_permissions($global_team);
    }
}

if (! function_exists('get_team_permission_id')) {
    function get_team_permission_id()
    {
        return app(\Spatie\Permission\PermissionRegistrar::class)->getPermissionsTeamId();
    }
}

if (! function_exists('cabinet_clear_menu_session_cache')) {
    function cabinet_clear_menu_session_cache(): void
    {
        if (class_exists(\App\MenuItemsPosition::class)) {
            \App\MenuItemsPosition::clearSortMenuCache();
        }

        foreach (['cabinet_menu_modules', 'cabinet_menu_modules_v2', 'cabinet_menu_modules_v3', 'cabinet_menu_modules_v4'] as $key) {
            session()->forget($key);
        }
    }
}

if (! function_exists('cabinet_skip_heavy_web')) {
    /** Local dev: remote DB + тяжёлые composers/middleware отключены через .env */
    function cabinet_skip_heavy_web(): bool
    {
        return app()->environment('local') || (bool) env('SKIP_HEAVY_WEB_MIDDLEWARE', false);
    }
}

if (! function_exists('localize_cabinet_url')) {
    /**
     * В local ссылки из БД часто абсолютные на lk.redbox.su — подменяем на APP_URL.
     */
    function localize_cabinet_url(?string $url): ?string
    {
        if ($url === null || $url === '' || ! app()->environment('local')) {
            return $url;
        }

        $local = rtrim(config('app.url'), '/');
        $prefixes = [
            'https://lk.redbox.su',
            'http://lk.redbox.su',
            'https://cabinet.datagon.ru',
            'http://cabinet.datagon.ru',
        ];

        foreach ($prefixes as $prefix) {
            if (strpos($url, $prefix) === 0) {
                return $local . substr($url, strlen($prefix));
            }
        }

        return $url;
    }
}

if (! function_exists('cabinet_storage_url')) {
    /**
     * URL файла из storage/app/public (симлинк public/storage).
     * Если файла нет на диске — CABINET_STORAGE_URL (обычно lk), пока storage не на cabinet.
     */
    function cabinet_storage_url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');

        if (file_exists(public_path('storage/' . $path))) {
            return asset('storage/' . $path);
        }

        $remote = rtrim((string) env('CABINET_STORAGE_URL', ''), '/');
        if ($remote === '' && app()->environment('local')) {
            $remote = 'https://lk.redbox.su';
        }

        if ($remote !== '') {
            return $remote . '/storage/' . $path;
        }

        return null;
    }
}

if (! function_exists('cabinet_brand_name')) {
    function cabinet_brand_name(): string
    {
        return \App\Support\TextAnalyzerPdfBranding::BRAND_NAME;
    }
}

if (! function_exists('cabinet_page_title')) {
    /**
     * Заголовок вкладки: «Раздел — Датагон» (как бренд в сайдбаре).
     */
    function cabinet_page_title(?string $pageTitle = null): string
    {
        $brand = cabinet_brand_name();
        $pageTitle = trim((string) $pageTitle);

        if ($pageTitle === '') {
            return $brand;
        }

        $suffix = ' — ' . $brand;
        if (mb_substr($pageTitle, -mb_strlen($suffix)) === $suffix) {
            return $pageTitle;
        }

        return $pageTitle . $suffix;
    }
}
