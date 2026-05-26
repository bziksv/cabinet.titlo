<?php

namespace App\Services\Demo;

use App\DomainInformation;
use App\Support\TextAnalyzerPdfBranding;

class DomainInformationDemoService
{
    public const MODULE = 'otslezhivanie-sroka-registratsii-domenov';

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return config('cabinet-domain-information.demo', []);
    }

    /**
     * @param array{domain?: string} $input
     * @return array{ok: true, domain: string}|array{ok: false, status: int, error: string, message: string}
     */
    public static function validate(array $input): array
    {
        $domain = DomainInformation::getDomain(trim((string) ($input['domain'] ?? '')));
        if ($domain === '') {
            return self::fail(422, 'validation', 'Укажите домен для проверки, например example.ru');
        }

        if (!DomainInformation::isValidDomain($domain)) {
            return self::fail(422, 'validation', 'Некорректное имя домена — укажите только хост без http:// и пути');
        }

        return ['ok' => true, 'domain' => $domain];
    }

    /**
     * @param array{domain: string} $validated
     * @return array<string, mixed>
     */
    public static function check(array $validated): array
    {
        return DomainInformation::probe($validated['domain']);
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
