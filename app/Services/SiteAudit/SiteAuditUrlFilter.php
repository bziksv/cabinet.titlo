<?php

namespace App\Services\SiteAudit;

/**
 * Исключение URL по списку паттернов (подстрока / glob / regex:…).
 */
class SiteAuditUrlFilter
{
    /**
     * @param string|array $raw
     * @return string[]
     */
    public static function parsePatterns($raw): array
    {
        if (is_array($raw)) {
            $lines = $raw;
        } else {
            $lines = preg_split('/\R+/', (string) $raw) ?: [];
        }

        $out = [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || (isset($line[0]) && $line[0] === '#')) {
                continue;
            }
            $out[] = $line;
        }

        return array_values(array_unique($out));
    }

    /**
     * @param string[] $patterns
     */
    public static function isExcluded(string $url, array $patterns): bool
    {
        if (! $patterns) {
            return false;
        }

        $path = $url;
        $parts = parse_url($url);
        if (is_array($parts)) {
            $path = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
        }

        foreach ($patterns as $pattern) {
            if (stripos($pattern, 'regex:') === 0) {
                $re = substr($pattern, 6);
                if ($re !== '' && @preg_match($re, $url)) {
                    return true;
                }
                continue;
            }

            if (strpos($pattern, '*') !== false || strpos($pattern, '?') !== false) {
                $hayUrl = strtolower($url);
                $hayPath = strtolower($path);
                $pat = strtolower($pattern);
                if (fnmatch($pat, $hayUrl) || fnmatch($pat, $hayPath)) {
                    return true;
                }
                continue;
            }

            if (stripos($url, $pattern) !== false || stripos($path, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string[] $urls
     * @param string[] $patterns
     * @return string[]
     */
    public static function filterList(array $urls, array $patterns): array
    {
        if (! $patterns) {
            return array_values($urls);
        }

        $out = [];
        foreach ($urls as $url) {
            if (! self::isExcluded($url, $patterns)) {
                $out[] = $url;
            }
        }

        return $out;
    }
}
