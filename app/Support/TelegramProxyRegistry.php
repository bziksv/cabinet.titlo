<?php

namespace App\Support;

use App\Services\TelegramConnectivityService;
use Illuminate\Support\Str;

/**
 * Список прокси для Telegram API (файл на сервере, не в git).
 */
class TelegramProxyRegistry
{
    public static function storagePath(): string
    {
        return storage_path((string) config('cabinet-telegram.proxies_file', 'app/telegram-proxies.json'));
    }

    public static function seedFromEnvIfEmpty(): void
    {
        if (is_readable(self::storagePath())) {
            return;
        }

        $url = trim((string) env('TELEGRAM_PROXY', ''));
        if ($url === '' || !self::isValidProxyUrl($url)) {
            return;
        }

        self::save([
            [
                'id' => 'primary',
                'label' => 'Из .env (TELEGRAM_PROXY)',
                'url' => $url,
                'enabled' => true,
                'priority' => 100,
            ],
        ]);
    }

    /**
     * @return array<int, array{id: string, label: string, url: string, enabled: bool, priority: int}>
     */
    public static function all(): array
    {
        self::seedFromEnvIfEmpty();

        $path = self::storagePath();
        if (!is_readable($path)) {
            return [];
        }

        $raw = json_decode((string) file_get_contents($path), true);
        if (!is_array($raw) || !isset($raw['proxies']) || !is_array($raw['proxies'])) {
            return [];
        }

        $out = [];
        foreach ($raw['proxies'] as $row) {
            if (!is_array($row) || empty($row['url'])) {
                continue;
            }
            $out[] = [
                'id' => (string) ($row['id'] ?? Str::uuid()),
                'label' => (string) ($row['label'] ?? 'Proxy'),
                'url' => trim((string) $row['url']),
                'enabled' => !isset($row['enabled']) || (bool) $row['enabled'],
                'priority' => (int) ($row['priority'] ?? 0),
            ];
        }

        usort($out, static function (array $a, array $b) {
            return $b['priority'] <=> $a['priority'];
        });

        return $out;
    }

    /**
     * @return array<int, array{id: string, label: string, url: string, enabled: bool, priority: int}>
     */
    public static function enabled(): array
    {
        return array_values(array_filter(self::all(), static function (array $row) {
            return !empty($row['enabled']);
        }));
    }

    public static function find(string $id): ?array
    {
        foreach (self::all() as $row) {
            if ($row['id'] === $id) {
                return $row;
            }
        }

        return null;
    }

    public static function primaryUrl(): ?string
    {
        $enabled = self::enabled();

        return $enabled[0]['url'] ?? null;
    }

    public static function syncLegacyConfig(): void
    {
        $url = self::primaryUrl() ?? '';
        config(['app.telegram_proxy' => $url !== '' ? $url : null]);
    }

    /**
     * @param array<int, array{id?: string, label: string, url: string, enabled?: bool, priority?: int}> $proxies
     */
    public static function save(array $proxies): void
    {
        $normalized = [];
        foreach ($proxies as $row) {
            $url = trim((string) ($row['url'] ?? ''));
            if ($url === '' || !self::isValidProxyUrl($url)) {
                continue;
            }
            $normalized[] = [
                'id' => (string) ($row['id'] ?? (string) Str::uuid()),
                'label' => trim((string) ($row['label'] ?? 'Proxy')) ?: 'Proxy',
                'url' => $url,
                'enabled' => !isset($row['enabled']) || (bool) $row['enabled'],
                'priority' => (int) ($row['priority'] ?? 0),
            ];
        }

        usort($normalized, static function (array $a, array $b) {
            return $b['priority'] <=> $a['priority'];
        });

        $path = self::storagePath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, json_encode([
            'updated_at' => now()->toIso8601String(),
            'proxies' => $normalized,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        self::syncLegacyConfig();
    }

    public static function add(string $label, string $url, int $priority = 50, bool $enabled = true): void
    {
        $all = self::all();
        $all[] = [
            'id' => (string) Str::uuid(),
            'label' => $label,
            'url' => trim($url),
            'enabled' => $enabled,
            'priority' => $priority,
        ];
        self::save($all);
    }

    public static function update(string $id, string $label, string $url, int $priority, bool $enabled): bool
    {
        $all = self::all();
        $updated = false;
        foreach ($all as $i => $row) {
            if ($row['id'] !== $id) {
                continue;
            }
            $all[$i]['label'] = trim($label) !== '' ? trim($label) : 'Proxy';
            $all[$i]['url'] = trim($url);
            $all[$i]['priority'] = $priority;
            $all[$i]['enabled'] = $enabled;
            $updated = true;
            break;
        }

        if (!$updated) {
            return false;
        }

        self::save($all);

        return true;
    }

    public static function remove(string $id): void
    {
        $all = array_values(array_filter(self::all(), static function (array $row) use ($id) {
            return $row['id'] !== $id;
        }));
        self::save($all);
    }

    public static function importFromEnv(): bool
    {
        $url = trim((string) env('TELEGRAM_PROXY', ''));
        if ($url === '') {
            return false;
        }

        foreach (self::all() as $row) {
            if ($row['url'] === $url) {
                return true;
            }
        }

        self::add('Из .env', $url, 100, true);

        return true;
    }

    public static function isValidProxyUrl(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts)
            && !empty($parts['scheme'])
            && !empty($parts['host'])
            && in_array(strtolower($parts['scheme']), ['http', 'https', 'socks5', 'socks5h'], true);
    }

    /**
     * @return array{id: string, label: string, url_masked: string, enabled: bool, priority: int, probe: array}
     */
    public static function rowWithProbe(array $proxy, TelegramConnectivityService $connectivity): array
    {
        $probe = $connectivity->probe($proxy['url'], 12);

        return [
            'id' => $proxy['id'],
            'label' => $proxy['label'],
            'url_masked' => TelegramConnectivityService::maskProxyUrl($proxy['url']),
            'enabled' => $proxy['enabled'],
            'priority' => $proxy['priority'],
            'probe' => $probe,
        ];
    }
}
