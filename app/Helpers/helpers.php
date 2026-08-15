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

        foreach ([
            'cabinet_menu_modules',
            'cabinet_menu_modules_v2',
            'cabinet_menu_modules_v3',
            'cabinet_menu_modules_v4',
            'cabinet_menu_modules_v4_stamp',
        ] as $key) {
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

if (! function_exists('cabinet_menu_item_is_admin_only')) {
    /**
     * Пункт меню доступен только ролям admin / Super Admin (без пользовательских тарифов).
     *
     * @param  array<int, string>|string|null  $access
     */
    function cabinet_menu_item_is_admin_only($access): bool
    {
        if (is_string($access)) {
            $decoded = json_decode($access, true);
            $access = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($access) || $access === []) {
            return false;
        }

        $adminRoles = ['admin', 'Super Admin'];

        foreach ($access as $role) {
            if (! in_array((string) $role, $adminRoles, true)) {
                return false;
            }
        }

        return true;
    }
}

if (! function_exists('localize_cabinet_url')) {
    /**
     * Ссылки main_projects в БД часто абсолютные на lk.redbox.su (общая БД с legacy).
     * На cabinet.titlo.ru и local подменяем на config('app.url'); на lk — не трогаем.
     */
    function localize_cabinet_url(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return $url;
        }

        $local = rtrim((string) config('app.url'), '/');
        if ($local === '') {
            return $url;
        }

        if (preg_match('#^https?://(lk\.redbox\.su|www\.lk\.redbox\.su)(/|$)#i', $local)) {
            return $url;
        }

        $legacyPrefixes = [
            'https://lk.redbox.su',
            'http://lk.redbox.su',
            'https://cabinet.datagon.ru',
            'http://cabinet.datagon.ru',
        ];

        foreach ($legacyPrefixes as $prefix) {
            if (strpos($url, $prefix) === 0) {
                return $local . substr($url, strlen($prefix));
            }
        }

        if (app()->environment('local')) {
            foreach (['https://cabinet.titlo.ru', 'http://cabinet.titlo.ru'] as $prefix) {
                if (strpos($url, $prefix) === 0) {
                    return $local . substr($url, strlen($prefix));
                }
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
     * Заголовок вкладки: «Раздел — Титло» (как бренд в сайдбаре).
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

if (! function_exists('cabinet_sc_document_title')) {
    /**
     * Title SEO-чеклиста: «Хроника — Чек лист» (+ бренд добавит cabinet_page_title).
     *
     * @param  string|null  $section  Подраздел (Хроника, Проекты, домен проекта…)
     * @param  int|null  $userId
     */
    function cabinet_sc_document_title(?string $section = null, ?int $userId = null): string
    {
        $module = \App\SeoChecklist\SeoChecklistUserPreference::moduleTitleFor(
            $userId ?? (auth()->check() ? (int) auth()->id() : null)
        );
        $section = trim((string) $section);
        if ($section === '') {
            return $module;
        }

        return $section . ' — ' . $module;
    }
}

if (! function_exists('cabinet_sc_module_title_html')) {
    /**
     * Заголовок карточки модуля с бейджем версии (как у аудита сайта).
     */
    function cabinet_sc_module_title_html(?int $userId = null): string
    {
        $title = e(\App\SeoChecklist\SeoChecklistUserPreference::moduleTitleFor(
            $userId ?? (auth()->check() ? (int) auth()->id() : null)
        ));

        return $title . view('partials.cabinet-module-version-badge', [
            'configKey' => 'cabinet-seo-checklist',
        ])->render();
    }
}

if (! function_exists('db_admin_date_column_label')) {
    /**
     * Человекочитаемая подпись колонки даты на /admin/database.
     */
    function db_admin_date_column_label(?string $column): string
    {
        if ($column === null || $column === '') {
            return '—';
        }

        $labels = config('cabinet-database-admin.date_column_labels', []);

        return $labels[$column] ?? __('Database date column generic', ['column' => $column]);
    }
}

if (! function_exists('db_admin_format_datetime')) {
    /**
     * Формат даты для админки БД: 27.05.2026 16:21.
     */
    function db_admin_format_datetime($value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_numeric($value)) {
            try {
                return \Carbon\Carbon::createFromTimestamp((int) $value)->format('d.m.Y H:i');
            } catch (\Exception $e) {
                return (string) $value;
            }
        }

        try {
            return \Carbon\Carbon::parse((string) $value)->format('d.m.Y H:i');
        } catch (\Exception $e) {
            return (string) $value;
        }
    }
}
