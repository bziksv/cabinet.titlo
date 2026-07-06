<?php

namespace App\Services;

use App\Support\NotificationDispatchLogger;
use App\Support\TelegramProxyRegistry;
use App\User;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TelegramBotService
{
    protected $url = 'https://api.telegram.org/bot';
    protected $token;
    protected $chat_id;

    /** Последняя ошибка API (для flash в админке, без токена). */
    public static $lastError = '';

    /** Диагностика последнего sendMessage (для /admin/telegram-proxy). */
    public static $lastSendDiagnostics = [];

    public function __construct(int $chat_id)
    {
        $this->token = config('app.telegram_bot_token');
        $this->setChatId($chat_id);
    }

    public function updateUserChatID(string $email)
    {
        return User::where('email', $email)->update(['chat_id' => $this->getChatId(), 'telegram_bot_active' => 1]);
    }

    /**
     * @param  array{event_id?: string, user_id?: int|null, source?: string}  $dispatch
     */
    public function sendMsg(string $text, ?array $replyMarkup = null, array $dispatch = []): bool
    {
        self::$lastError = '';
        self::$lastSendDiagnostics = [];

        if ($this->token === null || $this->token === '') {
            self::$lastError = 'TELEGRAM_BOT_TOKEN не задан в .env на сервере';
            Log::warning('Telegram sendMessage: empty bot token');

            return false;
        }

        if (!class_exists(Client::class)) {
            self::$lastError = 'Guzzle HTTP client недоступен (composer install)';

            return false;
        }

        $payload = [
            'text' => $text,
            'chat_id' => $this->getChatId(),
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];

        if ($replyMarkup !== null) {
            $payload['reply_markup'] = json_encode(
                $replyMarkup,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        }

        $lastResult = null;
        foreach ($this->buildSendAttempts() as $index => $attempt) {
            $lastResult = $this->executeSendMessage(
                $payload,
                $attempt['proxy'],
                $attempt['send_via'],
                $index > 0
            );

            if ($lastResult['success']) {
                if (!empty($dispatch['event_id'])) {
                    NotificationDispatchLogger::log(
                        (string) $dispatch['event_id'],
                        NotificationDispatchLogger::CHANNEL_TELEGRAM,
                        isset($dispatch['user_id']) ? (int) $dispatch['user_id'] : null,
                        (string) ($dispatch['source'] ?? 'system')
                    );
                }

                return true;
            }

            if (!self::shouldRetryWithAlternateTransport($lastResult)) {
                break;
            }
        }

        if (is_array($lastResult)) {
            self::$lastError = $lastResult['error'];
            self::$lastSendDiagnostics = $lastResult['diagnostics'];
        }

        return false;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{success: bool, error: string, diagnostics: array<string, mixed>}
     */
    private function executeSendMessage(array $payload, ?string $proxy, string $sendVia, bool $isRetry): array
    {
        $started = microtime(true);
        $options = [
            'timeout' => 15,
            'connect_timeout' => 8,
            'form_params' => $payload,
            'http_errors' => false,
        ];

        if ($proxy !== null && $proxy !== '') {
            $options['proxy'] = $proxy;
        }

        $body = '';
        $httpCode = 0;
        $transportError = '';

        try {
            $response = (new Client())->post($this->api() . '/sendMessage', $options);
            $httpCode = $response->getStatusCode();
            $body = (string) $response->getBody();
        } catch (GuzzleException $e) {
            $transportError = $e->getMessage();
        }

        $diagnostics = [
            'endpoint' => 'POST https://api.telegram.org/bot***/sendMessage',
            'transport' => 'guzzle',
            'chat_id' => $this->getChatId(),
            'http_code' => $httpCode,
            'transport_error' => $transportError,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'proxy_masked' => TelegramConnectivityService::maskProxyUrl((string) ($proxy ?? '')),
            'proxy_used' => $proxy !== null && $proxy !== '',
            'send_via' => $sendVia,
            'is_retry' => $isRetry,
            'response_preview' => mb_substr($body, 0, 600),
        ];

        if ($transportError !== '') {
            Log::warning('Telegram sendMessage guzzle failed', [
                'error' => $transportError,
                'chat_id' => $this->getChatId(),
                'send_via' => $sendVia,
            ]);

            return ['success' => false, 'error' => $transportError, 'diagnostics' => $diagnostics];
        }

        $decoded = json_decode($body, true);
        if ($httpCode !== 200 || !is_array($decoded) || empty($decoded['ok'])) {
            $description = is_array($decoded) ? ($decoded['description'] ?? '') : '';
            $error = $description !== '' ? $description : ('HTTP ' . $httpCode);
            Log::warning('Telegram sendMessage API error', [
                'http_code' => $httpCode,
                'description' => $description,
                'response' => mb_substr($body, 0, 500),
                'chat_id' => $this->getChatId(),
                'send_via' => $sendVia,
            ]);

            return ['success' => false, 'error' => $error, 'diagnostics' => $diagnostics];
        }

        self::$lastSendDiagnostics = $diagnostics;

        return ['success' => true, 'error' => '', 'diagnostics' => $diagnostics];
    }

    /**
     * @param array{success: bool, error: string, diagnostics: array} $result
     */
    private static function shouldRetryWithAlternateTransport(array $result): bool
    {
        if ($result['success']) {
            return false;
        }

        $err = strtolower($result['error'] ?? '');
        if ($err === '') {
            return true;
        }

        return strpos($err, 'timed out') !== false
            || strpos($err, 'timeout') !== false
            || strpos($err, 'tls') !== false
            || strpos($err, 'ssl') !== false
            || strpos($err, 'connection') !== false
            || strpos($err, 'could not connect') !== false
            || strpos($err, 'curl') !== false
            || strpos($err, 'нет ответа') !== false;
    }

    /**
     * @return array<int, mixed>
     */
    public static function curlProxyOptions(): array
    {
        $proxy = TelegramProxyRegistry::primaryUrl();
        if ($proxy === null || $proxy === '') {
            return [];
        }

        return [
            CURLOPT_PROXY => $proxy,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        ];
    }

    public static function supportsInlineUrlButton(string $url): bool
    {
        $parts = parse_url($url);
        if (!isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower($parts['host']);

        return !in_array($host, ['localhost', '127.0.0.1', '0.0.0.0', '::1'], true);
    }

    public function getChatId(): int
    {
        return $this->chat_id;
    }

    public function setChatId($chat_id): void
    {
        $this->chat_id = $chat_id;
    }

    private function api(): string
    {
        return $this->url . $this->token;
    }

    /**
     * @return array<int, array{send_via: string, proxy: string|null}>
     */
    private function buildSendAttempts(): array
    {
        TelegramProxyRegistry::syncLegacyConfig();

        $attempts = [];
        foreach ($this->resolveSendAttemptOrder() as $mode) {
            if ($mode === 'direct') {
                $attempts[] = ['send_via' => 'direct', 'proxy' => null];

                continue;
            }

            if (strpos($mode, 'proxy:') === 0) {
                $id = substr($mode, 6);
                $row = TelegramProxyRegistry::find($id);
                if ($row !== null && !empty($row['enabled'])) {
                    $attempts[] = [
                        'send_via' => $mode,
                        'proxy' => $row['url'],
                    ];
                }
            }
        }

        if ($attempts === []) {
            $attempts[] = ['send_via' => 'direct', 'proxy' => null];
        }

        if ($this->shouldSkipDirectOutbound()) {
            $attempts = array_values(array_filter($attempts, static function (array $attempt) {
                return $attempt['send_via'] !== 'direct';
            }));
        }

        if ($attempts === []) {
            $attempts[] = ['send_via' => 'direct', 'proxy' => null];
        }

        return $attempts;
    }

    private function shouldSkipDirectOutbound(): bool
    {
        $configured = config('cabinet-telegram.skip_direct_outbound');
        if ($configured !== null) {
            return (bool) $configured;
        }

        if (TelegramProxyRegistry::enabled() !== []) {
            return true;
        }

        $envProxy = config('app.telegram_proxy');

        return $envProxy !== null && $envProxy !== '';
    }

    /**
     * @return array<int, string>
     */
    private function resolveSendAttemptOrder(): array
    {
        $enabled = TelegramProxyRegistry::enabled();
        if ($enabled === []) {
            return ['direct'];
        }

        $cached = Cache::get(TelegramConnectivityService::SEND_ORDER_CACHE_KEY);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $order = ['direct'];
        foreach ($enabled as $row) {
            $order[] = 'proxy:' . $row['id'];
        }

        return $order;
    }

    /**
     * @return array{ok: bool, updates: array<int, array>, error: string}
     */
    public static function fetchUpdates(int $offset = 0): array
    {
        $token = config('app.telegram_bot_token');
        if ($token === null || $token === '') {
            return ['ok' => false, 'updates' => [], 'error' => 'empty token'];
        }

        TelegramProxyRegistry::syncLegacyConfig();

        $query = http_build_query([
            'offset' => max(0, $offset),
            'timeout' => 0,
            'limit' => 50,
            'allowed_updates' => json_encode(['message']),
        ]);

        $url = 'https://api.telegram.org/bot' . $token . '/getUpdates?' . $query;
        $attempts = self::buildStaticSendAttempts();
        $lastError = 'no attempts';

        foreach ($attempts as $attempt) {
            $result = self::curlGetJson($url, $attempt['proxy'], 20);
            if (!$result['ok']) {
                $lastError = $result['error'];
                continue;
            }

            $decoded = $result['json'];
            if (!is_array($decoded) || empty($decoded['ok'])) {
                $lastError = is_array($decoded) ? ($decoded['description'] ?? 'API error') : 'invalid JSON';

                continue;
            }

            $updates = $decoded['result'] ?? [];

            return [
                'ok' => true,
                'updates' => is_array($updates) ? $updates : [],
                'error' => '',
            ];
        }

        return ['ok' => false, 'updates' => [], 'error' => $lastError];
    }

    /**
     * @return array<int, array{send_via: string, proxy: string|null}>
     */
    private static function buildStaticSendAttempts(): array
    {
        $service = new self(0);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('buildSendAttempts');
        $method->setAccessible(true);

        return $method->invoke($service);
    }

    /**
     * @return array{ok: bool, json: ?array, error: string}
     */
    private static function curlGetJson(string $url, ?string $proxy, int $timeout): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'json' => null, 'error' => 'curl missing'];
        }

        $ch = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
            CURLOPT_HTTPGET => true,
            CURLOPT_NOSIGNAL => true,
        ];

        if ($proxy !== null && $proxy !== '') {
            $options[CURLOPT_PROXY] = $proxy;
            $options[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
        }

        curl_setopt_array($ch, $options);
        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError !== '') {
            return ['ok' => false, 'json' => null, 'error' => $curlError];
        }

        if ($httpCode !== 200 || !is_string($body)) {
            return ['ok' => false, 'json' => null, 'error' => 'HTTP ' . $httpCode];
        }

        $json = json_decode($body, true);

        return ['ok' => true, 'json' => is_array($json) ? $json : null, 'error' => ''];
    }
}
