<?php

namespace App\Services\Demo;

use App\Services\UniqueWordsAnalysisService;

class UniqueWordsDemoService
{
    public const MODULE = 'vydelenie-unikalnykh-slov-v-tekste';

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return config('cabinet-unique.demo', []);
    }

    /**
     * @return array{ok: true}|array{ok: false, status: int, error: string, message: string}
     */
    public static function validate(string $content): array
    {
        $maxChars = (int) (self::config()['max_chars'] ?? 3000);

        if (trim($content) === '') {
            return self::fail(422, 'validation', 'Вставьте список ключевых фраз');
        }

        if (mb_strlen($content) > $maxChars) {
            return self::fail(
                422,
                'validation',
                sprintf(
                    'В демо до %s символов. В кабинете — без лимита.',
                    number_format($maxChars, 0, ',', ' ')
                )
            );
        }

        return ['ok' => true];
    }

    /**
     * @param array<string, mixed> $analysis
     * @return array<string, mixed>
     */
    public static function buildResponse(array $analysis): array
    {
        return [
            'result' => $analysis,
            'upgrade' => [
                'register_url' => url('/register?module=' . self::MODULE . '&from=demo'),
                'max_chars' => (int) (self::config()['max_chars'] ?? 3000),
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

    /**
     * @return array<string, mixed>
     */
    public static function analyze(string $content): array
    {
        return UniqueWordsAnalysisService::analyze($content);
    }
}
