<?php

namespace App\Support;

class PhpCliBinary
{
    /**
     * Путь к php CLI (не php-fpm). Под FPM PHP_BINARY часто указывает на php-fpm.
     */
    public static function resolve(): string
    {
        $configured = config('cabinet-competitor-analysis.php_cli');
        if (is_string($configured) && $configured !== '' && is_executable($configured)) {
            return $configured;
        }

        foreach (self::defaultCandidates() as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        $binary = PHP_BINARY;
        if (self::looksLikeCli($binary)) {
            return $binary;
        }

        return $binary;
    }

    /**
     * @return array<int, string>
     */
    public static function defaultCandidates(): array
    {
        return [
            '/opt/php85/bin/php',
            '/opt/homebrew/opt/php@8.5/bin/php',
            '/opt/homebrew/bin/php',
            '/usr/local/bin/php',
            '/usr/bin/php',
            // legacy fallbacks
            '/opt/php74/bin/php',
            '/opt/homebrew/opt/php@7.4/bin/php',
        ];
    }

    public static function looksLikeCli(string $binary): bool
    {
        return strpos($binary, 'php-fpm') === false
            && strpos($binary, 'php-cgi') === false;
    }
}
