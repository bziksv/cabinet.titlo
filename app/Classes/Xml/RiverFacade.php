<?php

namespace App\Classes\Xml;

use App\Support\ClusterAnalysisDebugLog;
use Illuminate\Support\Facades\Log;

class RiverFacade
{
    protected $user;

    protected $key;

    protected $region;

    protected $query;

    protected $countAttempts;

    /** @var string|null progressId для ClusterAnalysisDebugLog */
    protected static $debugProgressId = null;

    public function __construct($region)
    {
        $this->user = config('xmlriver.user');
        $this->key = config('xmlriver.key');
        $this->region = $region;
        $this->countAttempts = 4;
    }

    public static function setClusterDebugProgressId(?string $progressId): void
    {
        self::$debugProgressId = $progressId !== '' ? $progressId : null;
    }

    public function setQuery($query)
    {
        $this->query = $query;
    }

    public function getQuery()
    {
        return $this->query;
    }

    public function setRegions($region)
    {
        $this->region = $region;
    }

    public function setUser($user)
    {
        $this->user = $user;
    }

    public function setKey($key)
    {
        $this->key = $key;
    }

    /**
     * Wordstat New — pagetype=history, totalValue (см. xmlriver.com/apiwordstatnew).
     *
     * @param bool $searchInItems Для базовой частоты — нормализованная фраза из popular.
     * @return array{number: int, phrase: string}
     */
    public function riverRequest(bool $searchInItems = true): array
    {
        $query = (string) $this->getQuery();
        $empty = [
            'number' => 0,
            'phrase' => $query,
        ];

        try {
            $riverResponse = null;
            $lastError = null;

            for ($attempt = 1; $attempt <= $this->countAttempts; $attempt++) {
                $riverResponse = $this->fetchNewWordstatResponse($query);

                if ($this->hasFatalWordstatError($riverResponse)) {
                    $lastError = $riverResponse;
                    $this->debugLog('warn', 'river.wordstat.error', [
                        'query' => $query,
                        'attempt' => $attempt,
                        'response' => $riverResponse,
                    ]);

                    return $empty;
                }

                if ($this->isRetryableWordstatError($riverResponse)) {
                    $lastError = $riverResponse;
                    $this->debugLog('warn', 'river.wordstat.retry', [
                        'query' => $query,
                        'attempt' => $attempt,
                        'code' => $riverResponse['code'] ?? null,
                        'error' => $riverResponse['error'] ?? null,
                    ]);
                    usleep(600000 * $attempt);
                    continue;
                }

                if (isset($riverResponse['totalValue'])) {
                    $phrase = $query;
                    if ($searchInItems) {
                        $phrase = $this->resolvePopularPhrase($riverResponse, $query) ?? $query;
                    }

                    return [
                        'number' => (int) $riverResponse['totalValue'],
                        'phrase' => $phrase,
                    ];
                }

                $lastError = $riverResponse;
                usleep(400000 * $attempt);
            }

            Log::debug('river request missing totalValue', [
                'query' => $query,
                'response' => $lastError,
            ]);
            $this->debugLog('warn', 'river.wordstat.empty', [
                'query' => $query,
                'response' => $lastError,
            ]);

            return $empty;
        } catch (\Throwable $e) {
            Log::debug('river request error', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'query' => $query,
            ]);
            $this->debugLog('error', 'river.wordstat.exception', [
                'query' => $query,
                'message' => $e->getMessage(),
            ]);

            return $empty;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function fetchNewWordstatResponse(string $query): ?array
    {
        $url = $this->buildNewWordstatUrl($query);
        $context = stream_context_create([
            'http' => [
                'timeout' => 45,
                'ignore_errors' => true,
            ],
        ]);
        $raw = @file_get_contents($url, false, $context);

        if ($raw === false || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    protected function buildNewWordstatUrl(string $query): string
    {
        $base = rtrim((string) config('xmlriver.url', 'https://xmlriver.com/wordstat/new/json'), '/');

        if (strpos($base, '/wordstat/new/json') === false) {
            $base = 'https://xmlriver.com/wordstat/new/json';
        }

        $params = http_build_query([
            'user' => $this->user,
            'key' => $this->key,
            'regions' => $this->region,
            'pagetype' => 'history',
        ], '', '&', PHP_QUERY_RFC3986);

        return $base . '?' . $params . '&query=' . rawurlencode($query);
    }

    /**
     * @param array<string, mixed>|null $response
     */
    protected function hasFatalWordstatError(?array $response): bool
    {
        if ($response === null) {
            return false;
        }

        if (!isset($response['code'])) {
            return !empty($response['error']) && !isset($response['totalValue']);
        }

        $code = (int) $response['code'];

        return in_array($code, [2, 31, 42, 45, 121, 200, 400], true);
    }

    /**
     * @param array<string, mixed>|null $response
     */
    protected function isRetryableWordstatError(?array $response): bool
    {
        if ($response === null) {
            return true;
        }

        if (!isset($response['code'])) {
            return !empty($response['error']) && !isset($response['totalValue']);
        }

        $code = (int) $response['code'];

        return in_array($code, [101, 110, 115, 500], true);
    }

    /**
     * @param array<string, mixed> $response
     */
    protected function resolvePopularPhrase(array $response, string $query): ?string
    {
        $popular = $response['table']['tableData']['popular'] ?? null;
        if (!is_array($popular)) {
            return null;
        }

        $queryLower = mb_strtolower($query);
        foreach ($popular as $item) {
            if (!is_array($item) || empty($item['text'])) {
                continue;
            }
            if (mb_strtolower((string) $item['text']) === $queryLower) {
                return (string) $item['text'];
            }
        }

        return null;
    }

    protected function debugLog(string $level, string $message, array $context = []): void
    {
        if (self::$debugProgressId === null) {
            return;
        }

        if ($level === 'error') {
            ClusterAnalysisDebugLog::error(self::$debugProgressId, $message, $context);
        } elseif ($level === 'warn') {
            ClusterAnalysisDebugLog::warn(self::$debugProgressId, $message, $context);
        } else {
            ClusterAnalysisDebugLog::info(self::$debugProgressId, $message, $context);
        }
    }
}
