<?php

namespace App\Support;

use App\Services\TelegramConnectivityService;
use App\Support\TelegramProxyRegistry;
use App\User;
use Illuminate\Support\Facades\Auth;

/**
 * Журнал тестов /admin/telegram-proxy (сессия админа, для копирования в поддержку).
 */
class TelegramProxyDebugLog
{
    private const SESSION_KEY = 'telegram_proxy_debug_log';

    public static function enabled(): bool
    {
        return (bool) config('cabinet-telegram.debug_log', true);
    }

    public static function begin(string $action): void
    {
        if (!static::enabled()) {
            return;
        }

        session([
            static::SESSION_KEY => [
                'started_at' => now()->toDateTimeString(),
                'action' => $action,
                'entries' => [],
            ],
        ]);
        static::info('=== ' . $action . ' ===');
        static::info('APP_ENV=' . config('app.env') . ' APP_URL=' . config('app.url'));
    }

    public static function logUserContext(?User $user): void
    {
        if (!$user) {
            static::warn('Пользователь не авторизован');

            return;
        }

        static::info('user', [
            'id' => $user->id,
            'email' => $user->email,
            'chat_id' => $user->chat_id,
            'telegram_bot_active' => (bool) $user->telegram_bot_active,
            'isTelegramConnected' => $user->isTelegramConnected(),
        ]);
    }

    public static function logTelegramConfig(): void
    {
        $token = (string) config('app.telegram_bot_token');
        $proxies = [];
        foreach (TelegramProxyRegistry::all() as $row) {
            $proxies[] = [
                'id' => $row['id'],
                'label' => $row['label'],
                'enabled' => $row['enabled'],
                'priority' => $row['priority'],
                'url_masked' => TelegramConnectivityService::maskProxyUrl($row['url']),
            ];
        }

        static::info('config', [
            'token_set' => $token !== '',
            'token_prefix' => $token !== '' ? (mb_substr($token, 0, 12) . '…') : null,
            'proxies' => $proxies,
            'env_proxy_masked' => TelegramConnectivityService::maskProxyUrl(TelegramProxyRegistry::configProxyUrl()),
        ]);
    }

    public static function logConnectivity(TelegramConnectivityService $connectivity): void
    {
        $status = $connectivity->status();
        static::info('connectivity.direct', $status['direct'] ?? []);
        foreach ($status['proxies'] ?? [] as $proxyRow) {
            static::info('connectivity.proxy.' . ($proxyRow['id'] ?? '?'), [
                'label' => $proxyRow['label'] ?? '',
                'enabled' => $proxyRow['enabled'] ?? false,
                'url_masked' => $proxyRow['url_masked'] ?? '',
                'probe' => $proxyRow['probe'] ?? [],
            ]);
        }
        static::info('sendMessage.attempt_order', [
            'order' => $connectivity->sendAttemptOrder(true),
        ]);
    }

    /**
     * @param array<string, mixed> $diagnostics
     */
    public static function logSendResult(bool $ok, string $lastError, array $diagnostics = []): void
    {
        static::info('sendMessage.result', array_merge([
            'ok' => $ok,
            'last_error' => $lastError,
        ], $diagnostics));
    }

    public static function info(string $message, array $context = []): void
    {
        static::append('info', $message, $context);
    }

    public static function warn(string $message, array $context = []): void
    {
        static::append('warn', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        static::append('error', $message, $context);
    }

    public static function append(string $level, string $message, array $context = []): void
    {
        if (!static::enabled()) {
            return;
        }

        $bag = session(static::SESSION_KEY);
        if (!is_array($bag)) {
            $bag = ['started_at' => now()->toDateTimeString(), 'action' => '—', 'entries' => []];
        }

        $bag['entries'][] = [
            't' => now()->format('H:i:s') . '.' . substr((string) now()->format('v'), 0, 3),
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];

        $max = (int) config('cabinet-telegram.debug_log_max_entries', 80);
        if (count($bag['entries']) > $max) {
            $bag['entries'] = array_slice($bag['entries'], -$max);
        }

        session([static::SESSION_KEY => $bag]);
    }

    public static function clear(): void
    {
        session()->forget(static::SESSION_KEY);
    }

    public static function hasEntries(): bool
    {
        $bag = session(static::SESSION_KEY);

        return is_array($bag) && !empty($bag['entries']);
    }

    public static function formatForCopy(): string
    {
        $bag = session(static::SESSION_KEY);
        if (!is_array($bag) || empty($bag['entries'])) {
            return '';
        }

        $lines = [
            'telegram-proxy debug',
            'user_id=' . (Auth::id() ?? '—'),
            'started=' . ($bag['started_at'] ?? '—'),
            'action=' . ($bag['action'] ?? '—'),
            '',
        ];

        foreach ($bag['entries'] as $entry) {
            $line = '[' . ($entry['t'] ?? '') . '] ' . strtoupper($entry['level'] ?? 'INFO') . ' ' . ($entry['message'] ?? '');
            $ctx = $entry['context'] ?? [];
            if ($ctx !== []) {
                $line .= ' ' . json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }
}
