<?php

namespace App\Support;

/**
 * Куда безопасно вернуть пользователя после OAuth внешних сервисов.
 * Нельзя отдавать AJAX-фрагменты главной — иначе после Google/Яндекс открывается «голая» HTML-врезка.
 */
class OauthReturnUrl
{
    public static function sanitize($url, ?string $fallback = null): string
    {
        $fallback = $fallback ?: route('home');
        if (!is_string($url) || $url === '') {
            return $fallback;
        }

        if (strpos($url, '/') === 0 && strpos($url, '//') !== 0) {
            $url = url($url);
        } else {
            $app = rtrim((string) config('app.url'), '/');
            if ($app === '' || strpos($url, $app) !== 0) {
                return $fallback;
            }
        }

        $parts = parse_url($url);
        $path = (string) ($parts['path'] ?? '');
        if (preg_match('#^/home/sites/(fragment|visits)$#', $path)
            || preg_match('#^/home/(module-counts|seo-checklist-due)$#', $path)
        ) {
            $query = isset($parts['query']) && $parts['query'] !== ''
                ? ('?' . $parts['query'])
                : '';

            return $fallback . $query;
        }

        return $url;
    }
}
