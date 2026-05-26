<?php

namespace App\Support;

use App\Services\TelegramConnectivityService;
use App\TelegramProxy;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Список прокси для Telegram API (таблица telegram_proxies).
 */
class TelegramProxyRegistry
{
    /** URL из config (после config:cache), не env() напрямую. */
    public static function configProxyUrl(): string
    {
        $url = config('app.telegram_proxy');

        return is_string($url) ? trim($url) : '';
    }

    /**
     * Если в БД пусто — первая запись из config/app.telegram_proxy.
     */
    public static function seedFromConfigIfNeeded(): void
    {
        if (TelegramProxy::query()->count() > 0) {
            return;
        }

        $url = self::configProxyUrl();
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
        self::seedFromConfigIfNeeded();

        return TelegramProxy::query()
            ->orderByDesc('priority')
            ->get()
            ->map(static function (TelegramProxy $row) {
                return [
                    'id' => $row->id,
                    'label' => $row->label,
                    'url' => $row->url,
                    'enabled' => (bool) $row->enabled,
                    'priority' => (int) $row->priority,
                ];
            })
            ->values()
            ->all();
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
        $url = self::primaryUrl() ?? self::configProxyUrl();
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

        try {
            $keepIds = [];
            foreach ($normalized as $row) {
                TelegramProxy::query()->updateOrCreate(
                    ['id' => $row['id']],
                    [
                        'label' => $row['label'],
                        'url' => $row['url'],
                        'priority' => $row['priority'],
                        'enabled' => $row['enabled'],
                    ]
                );
                $keepIds[] = $row['id'];
            }

            if ($keepIds === []) {
                TelegramProxy::query()->delete();
            } else {
                TelegramProxy::query()->whereNotIn('id', $keepIds)->delete();
            }
        } catch (\Throwable $e) {
            Log::error('TelegramProxyRegistry: cannot save to database', [
                'message' => $e->getMessage(),
            ]);
        }

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
        $url = self::configProxyUrl();
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
