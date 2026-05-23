<?php

namespace App\Support;

/**
 * Подсветка текущего модуля в боковом меню.
 */
class CabinetSidebarActive
{
    /** @var array<string, bool> */
    private static $linkCache = [];

    public static function isLinkActive(?string $link): bool
    {
        if ($link === null || $link === '') {
            return false;
        }

        if (array_key_exists($link, self::$linkCache)) {
            return self::$linkCache[$link];
        }

        $current = self::normalizedPath((string) request()->path());
        $target = self::normalizedPathFromUrl($link);

        $active = self::pathsMatch($current, $target);

        return self::$linkCache[$link] = $active;
    }

    /**
     * @param  array<string, mixed>  $module
     */
    public static function isFolderActive(array $module): bool
    {
        foreach ($module as $key => $elem) {
            if ($key === 'configurationInfo' || ! is_array($elem)) {
                continue;
            }
            if (self::isLinkActive($elem['link'] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $module
     */
    public static function folderShouldOpen(array $module): bool
    {
        $configOpen = ($module['configurationInfo']['show'] ?? '') === 'true';

        return $configOpen || self::isFolderActive($module);
    }

    public static function normalizedPathFromUrl(string $url): string
    {
        $url = localize_cabinet_url($url) ?? $url;

        if (preg_match('#^https?://#i', $url)) {
            $path = parse_url($url, PHP_URL_PATH) ?? '';
        } else {
            $path = $url;
        }

        return self::normalizedPath($path);
    }

    public static function normalizedPath(string $path): string
    {
        $path = trim($path, '/');

        if ($path === 'index.php') {
            return '';
        }

        return $path;
    }

    protected static function pathsMatch(string $current, string $target): bool
    {
        if ($target === '') {
            return $current === '' || $current === 'home';
        }

        if ($current === $target) {
            return true;
        }

        return strpos($current, $target . '/') === 0;
    }
}
