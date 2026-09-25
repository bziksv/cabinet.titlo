<?php

namespace App\Services\SiteAudit;

/**
 * Режим проверки HTML для краула: libxml (быстрый) или html5 (Nu / vnu).
 */
class SiteAuditHtmlChecker
{
    public const LIBXML = 'libxml';

    public const HTML5 = 'html5';

    public static function vnuEnabled(): bool
    {
        return trim((string) config('site_audit.vnu_url', '')) !== '';
    }

    public static function vnuUrl(): string
    {
        $u = trim((string) config('site_audit.vnu_url', ''));
        if ($u === '') {
            return '';
        }

        return rtrim($u, '/') . '/';
    }

    /**
     * Дефолт для UI / normalize: html5 только если vnu сконфигурирован
     * (или явно задан SITE_AUDIT_HTML_CHECKER=html5 — тогда всё равно html5, parse сделает fallback).
     */
    public static function defaultChecker(): string
    {
        $env = strtolower(trim((string) config('site_audit.html_checker_default', '')));
        if ($env === self::HTML5) {
            return self::HTML5;
        }
        if ($env === self::LIBXML) {
            return self::LIBXML;
        }

        return self::vnuEnabled() ? self::HTML5 : self::LIBXML;
    }

    public static function normalize($raw): string
    {
        $v = strtolower(trim((string) $raw));

        return $v === self::HTML5 ? self::HTML5 : self::LIBXML;
    }

    public static function label(string $checker): string
    {
        return self::normalize($checker) === self::HTML5
            ? 'HTML5 (Nu)'
            : 'Быстрая (libxml)';
    }
}
