<?php

namespace App\Services;

use App\Support\TelegramProxyRegistry;
use Illuminate\Support\Facades\Cache;

class TelegramConnectivityService
{
    public const SEND_ORDER_CACHE_KEY = 'telegram_send_attempt_order_v3';

    private const SEND_ORDER_CACHE_MINUTES = 5;

    /**
     * Порядок для sendMessage: direct, затем proxy:{id} по приоритету.
     *
     * @return array<int, string> direct|proxy:{id}
     */
    public function sendAttemptOrder(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::SEND_ORDER_CACHE_KEY);
        }

        return Cache::remember(
            self::SEND_ORDER_CACHE_KEY,
            now()->addMinutes(self::SEND_ORDER_CACHE_MINUTES),
            function () {
                return $this->computeSendAttemptOrder();
            }
        );
    }

    public static function forgetSendAttemptOrderCache(): void
    {
        Cache::forget(self::SEND_ORDER_CACHE_KEY);
    }

    /**
     * @return array<int, string>
     */
    private function computeSendAttemptOrder(): array
    {
        $direct = $this->probe(null, 6);
        $directOk = !empty($direct['ok']);

        $workingProxyIds = [];
        foreach (TelegramProxyRegistry::enabled() as $proxy) {
            $probe = $this->probe($proxy['url'], 10);
            if (!empty($probe['ok'])) {
                $workingProxyIds[] = $proxy['id'];
            }
        }

        if ($workingProxyIds === []) {
            return $directOk ? ['direct'] : ['direct'];
        }

        if (!$directOk) {
            return array_map(static function (string $id) {
                return 'proxy:' . $id;
            }, $workingProxyIds);
        }

        $order = ['direct'];
        foreach ($workingProxyIds as $id) {
            $order[] = 'proxy:' . $id;
        }

        return $order;
    }

    /**
     * @return array{ok:bool,http_code:int,curl_error:string,elapsed_ms:int}
     */
    public function probe(?string $proxy = null, int $timeout = 12): array
    {
        $url = 'https://api.telegram.org/';
        $started = microtime(true);

        if (!function_exists('curl_init')) {
            return [
                'ok' => false,
                'http_code' => 0,
                'curl_error' => 'curl extension missing',
                'elapsed_ms' => 0,
            ];
        }

        $ch = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY => false,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_NOSIGNAL => true,
        ];

        if ($proxy !== null && $proxy !== '') {
            $options[CURLOPT_PROXY] = $proxy;
            $options[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
        }

        curl_setopt_array($ch, $options);
        curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $elapsed = (int) round((microtime(true) - $started) * 1000);

        return [
            'ok' => $httpCode >= 200 && $httpCode < 400 && $curlError === '',
            'http_code' => $httpCode,
            'curl_error' => $curlError,
            'elapsed_ms' => $elapsed,
        ];
    }

    /**
     * @return array{
     *   direct: array,
     *   proxies: array<int, array>,
     *   proxy_count: int,
     *   proxy_configured: bool,
     *   proxy_masked: string,
     *   env_proxy_masked: string,
     *   token_configured: bool,
     *   send_order: array<int, string>
     * }
     */
    public function status(): array
    {
        TelegramProxyRegistry::syncLegacyConfig();

        $proxies = [];
        foreach (TelegramProxyRegistry::all() as $row) {
            $proxies[] = TelegramProxyRegistry::rowWithProbe($row, $this);
        }

        $enabled = TelegramProxyRegistry::enabled();
        $primaryUrl = $enabled[0]['url'] ?? null;
        $envUrl = TelegramProxyRegistry::configProxyUrl();

        return [
            'direct' => $this->probe(null, 8),
            'proxies' => $proxies,
            'proxy_count' => count($proxies),
            'proxy_configured' => count($enabled) > 0 || $envUrl !== '',
            'proxy_masked' => self::maskProxyUrl((string) ($primaryUrl ?? '')),
            'env_proxy_masked' => self::maskProxyUrl($envUrl),
            'token_configured' => config('app.telegram_bot_token') !== null
                && config('app.telegram_bot_token') !== '',
            'send_order' => $this->sendAttemptOrder(false),
        ];
    }

    public static function maskProxyUrl(string $proxy): string
    {
        if ($proxy === '') {
            return '';
        }

        $parts = parse_url($proxy);
        if ($parts === false || empty($parts['host'])) {
            return '***';
        }

        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $host = $parts['host'];
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $user = isset($parts['user']) ? rawurlencode($parts['user']) : '';
        $pass = isset($parts['pass']) ? ':***' : '';

        if ($user !== '') {
            return $scheme . $user . $pass . '@' . $host . $port;
        }

        return $scheme . $host . $port;
    }
}
