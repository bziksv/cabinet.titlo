<?php

namespace App\Support;

/**
 * Сравнение и отображение DNS: порядок NS не считается изменением.
 */
class DomainInformationDns
{
    /**
     * @param  array<int, string>|null  $nameServers
     */
    public static function formatFromNameServers(?array $nameServers): string
    {
        $lines = self::normalizeList($nameServers);

        if ($lines === []) {
            return '';
        }

        return "DNS:\n" . implode("\n", $lines);
    }

    public static function hasChanged(?string $previous, ?string $current): bool
    {
        if ($previous === null || trim($previous) === '') {
            return false;
        }

        return self::fingerprints($previous) !== self::fingerprints($current);
    }

    /**
     * @return array<int, string>
     */
    public static function normalizeList($nameServers): array
    {
        if (!is_array($nameServers)) {
            return [];
        }

        $out = [];
        foreach ($nameServers as $server) {
            $normalized = self::normalizeHost((string) $server);
            if ($normalized !== '') {
                $out[$normalized] = $normalized;
            }
        }

        $lines = array_values($out);
        sort($lines, SORT_STRING);

        return $lines;
    }

    /**
     * @return array<int, string>
     */
    public static function parseStoredBlock(?string $block): array
    {
        if ($block === null || trim($block) === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $block) ?: [];
        $hosts = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || stripos($line, 'DNS:') === 0) {
                continue;
            }
            $normalized = self::normalizeHost($line);
            if ($normalized !== '') {
                $hosts[$normalized] = $normalized;
            }
        }

        $list = array_values($hosts);
        sort($list, SORT_STRING);

        return $list;
    }

  /**
     * Стабильный ключ для сравнения (отсортированный набор NS).
     */
    public static function fingerprints(?string $block): string
    {
        $list = self::parseStoredBlock($block);

        return $list === [] ? '' : implode("\0", $list);
    }

    public static function normalizeHost(string $host): string
    {
        $host = trim($host);
        $host = rtrim($host, '.');
        $host = mb_strtolower($host);

        return $host;
    }
}
