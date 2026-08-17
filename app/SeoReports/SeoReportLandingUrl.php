<?php

namespace App\SeoReports;

/**
 * Абсолютные URL посадочных для кликабельных ссылок в HTML/PDF-отчёте.
 */
class SeoReportLandingUrl
{
    /**
     * @param  string|null  $raw  Полный URL, путь (/services) или хост
     * @param  string|null  $domain  Домен проекта без схемы
     */
    public static function href(?string $raw, ?string $domain = null): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '' || $raw === '—' || $raw === '-') {
            return null;
        }

        if (preg_match('#^https?://#i', $raw) === 1) {
            return $raw;
        }

        if (isset($raw[0]) && $raw[0] === '/') {
            $host = self::normalizeHost($domain);
            if ($host === '') {
                return null;
            }

            return 'https://' . $host . $raw;
        }

        // Хост без схемы: competitor.example / demo-shop.titlo.ru/path
        if (preg_match('#^[a-z0-9][a-z0-9.\-]*(/[^\s]*)?$#i', $raw) === 1) {
            return 'https://' . ltrim($raw, '/');
        }

        return null;
    }

    public static function normalizeHost(?string $domain): string
    {
        $host = trim((string) $domain);
        $host = preg_replace('#^https?://#i', '', $host) ?? '';
        $host = preg_replace('#/.*$#', '', $host) ?? '';

        return rtrim($host, '/');
    }
}
