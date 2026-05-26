<?php

namespace App\Services\Demo;

use App\DomainMonitoring;
use App\Support\TextAnalyzerPdfBranding;

class SiteMonitoringDemoService
{
    public const MODULE = 'monitoring-saytov';

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return config('cabinet-site-monitoring.demo', []);
    }

    /**
     * @param array{url?: string, phrase?: string, waiting_time?: int} $input
     * @return array{ok: true}|array{ok: false, status: int, error: string, message: string}
     */
    public static function validate(array $input): array
    {
        $url = trim((string) ($input['url'] ?? ''));
        if ($url === '') {
            return self::fail(422, 'validation', 'Укажите URL сайта для проверки');
        }
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return self::fail(422, 'validation', 'URL должен быть корректным (http или https)');
        }

        $phrase = trim((string) ($input['phrase'] ?? ''));
        if (mb_strlen($phrase) > 500) {
            return self::fail(422, 'validation', 'Ключевая фраза — до 500 символов');
        }

        $wait = (int) ($input['waiting_time'] ?? self::config()['default_waiting_time'] ?? 15);
        if (!in_array($wait, [10, 15, 20], true)) {
            $wait = 15;
        }

        return ['ok' => true, 'url' => $url, 'phrase' => $phrase, 'waiting_time' => $wait];
    }

    /**
     * @param array{url: string, phrase: string, waiting_time: int} $validated
     * @return array<string, mixed>
     */
    public static function check(array $validated): array
    {
        $phrase = $validated['phrase'] !== '' ? $validated['phrase'] : null;

        return DomainMonitoring::probe(
            $validated['url'],
            $phrase,
            $validated['waiting_time']
        );
    }

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    public static function buildResponse(array $result, int $remaining, string $guestId): array
    {
        $cfg = self::config();
        $maxRuns = (int) ($cfg['max_runs_per_day'] ?? 5);

        return [
            'demo' => true,
            'module' => self::MODULE,
            'remaining' => $remaining,
            'limits' => [
                'max_runs_per_day' => $maxRuns,
            ],
            'result' => $result,
            'upgrade' => [
                'register_url' => url('/register?module=' . self::MODULE . '&from=demo&guest=' . urlencode($guestId)),
                'login_url' => TextAnalyzerPdfBranding::loginUrl(),
            ],
        ];
    }

    /**
     * @return array{ok: false, status: int, error: string, message: string}
     */
    private static function fail(int $status, string $error, string $message): array
    {
        return [
            'ok' => false,
            'status' => $status,
            'error' => $error,
            'message' => $message,
        ];
    }
}
