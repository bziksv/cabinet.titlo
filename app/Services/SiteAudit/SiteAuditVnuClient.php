<?php

namespace App\Services\SiteAudit;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Pool;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * HTTP-клиент к локальному Nu Html Checker (vnu servlet).
 *
 * @see https://github.com/validator/validator
 */
class SiteAuditVnuClient
{
    /** @var Client|null */
    private $client;

    public function __construct(?Client $client = null)
    {
        $this->client = $client;
    }

    public function isConfigured(): bool
    {
        return SiteAuditHtmlChecker::vnuEnabled();
    }

    /**
     * @return array{
     *   ok:bool,
     *   errors:list<array{line:?int,level:string,message:string}>,
     *   error:?string
     * }
     */
    public function validate(string $html): array
    {
        $base = SiteAuditHtmlChecker::vnuUrl();
        if ($base === '') {
            return ['ok' => false, 'errors' => [], 'error' => 'vnu_url_empty'];
        }

        $html = $this->truncateHtml($html);
        $url = $this->endpointUrl($base);

        try {
            $response = $this->httpClient()->post($url, [
                'headers' => $this->requestHeaders(),
                'body' => $html,
            ]);
        } catch (GuzzleException $e) {
            return ['ok' => false, 'errors' => [], 'error' => 'vnu_http: ' . $e->getMessage()];
        } catch (Throwable $e) {
            return ['ok' => false, 'errors' => [], 'error' => 'vnu_fail: ' . $e->getMessage()];
        }

        return $this->parseHttpResponse(
            $response->getStatusCode(),
            (string) $response->getBody()
        );
    }

    /**
     * Параллельная проверка нескольких HTML (индекс волны → результат validate).
     *
     * @param array<int|string, string> $htmlByIndex
     * @return array<int|string, array{ok:bool,errors:list,error:?string}>
     */
    public function validateMany(array $htmlByIndex, int $concurrency = 1): array
    {
        if ($htmlByIndex === []) {
            return [];
        }

        $base = SiteAuditHtmlChecker::vnuUrl();
        if ($base === '') {
            $out = [];
            foreach (array_keys($htmlByIndex) as $i) {
                $out[$i] = ['ok' => false, 'errors' => [], 'error' => 'vnu_url_empty'];
            }

            return $out;
        }

        $concurrency = max(1, $concurrency);
        if ($concurrency === 1 || count($htmlByIndex) === 1) {
            $out = [];
            foreach ($htmlByIndex as $i => $html) {
                $out[$i] = $this->validate(is_string($html) ? $html : '');
            }

            return $out;
        }

        $url = $this->endpointUrl($base);
        $client = $this->httpClient();
        /** @var array<int|string, array|null> $results */
        $results = [];
        foreach (array_keys($htmlByIndex) as $i) {
            $results[$i] = null;
        }

        $requests = function () use ($htmlByIndex, $client, $url) {
            foreach ($htmlByIndex as $index => $html) {
                yield $index => function () use ($client, $url, $html) {
                    return $client->postAsync($url, [
                        'headers' => $this->requestHeaders(),
                        'body' => $this->truncateHtml(is_string($html) ? $html : ''),
                    ]);
                };
            }
        };

        $pool = new Pool($client, $requests(), [
            'concurrency' => $concurrency,
            'fulfilled' => function (ResponseInterface $response, $index) use (&$results) {
                $results[$index] = $this->parseHttpResponse(
                    $response->getStatusCode(),
                    (string) $response->getBody()
                );
            },
            'rejected' => function ($reason, $index) use (&$results) {
                $message = is_object($reason) && method_exists($reason, 'getMessage')
                    ? $reason->getMessage()
                    : (string) $reason;
                $prefix = $reason instanceof GuzzleException ? 'vnu_http: ' : 'vnu_fail: ';
                $results[$index] = ['ok' => false, 'errors' => [], 'error' => $prefix . $message];
            },
        ]);
        $pool->promise()->wait();

        foreach ($results as $i => $row) {
            if ($row === null) {
                $results[$i] = ['ok' => false, 'errors' => [], 'error' => 'vnu_missing'];
            }
        }

        return $results;
    }

    private function httpClient(): Client
    {
        if ($this->client instanceof Client) {
            return $this->client;
        }

        $this->client = new Client([
            'timeout' => (float) config('site_audit.vnu_timeout', 8),
            'connect_timeout' => (float) config('site_audit.vnu_connect_timeout', 2),
            'http_errors' => false,
            'verify' => false,
        ]);

        return $this->client;
    }

    private function endpointUrl(string $base): string
    {
        return $base . (strpos($base, '?') === false ? '?' : '&') . 'out=json';
    }

    /**
     * @return array<string, string>
     */
    private function requestHeaders(): array
    {
        return [
            'Content-Type' => 'text/html; charset=utf-8',
            'Accept' => 'application/json',
        ];
    }

    private function truncateHtml(string $html): string
    {
        $maxBytes = max(50_000, (int) config('site_audit.vnu_max_bytes', 1_500_000));
        if (strlen($html) > $maxBytes) {
            return substr($html, 0, $maxBytes);
        }

        return $html;
    }

    /**
     * @return array{
     *   ok:bool,
     *   errors:list<array{line:?int,level:string,message:string}>,
     *   error:?string
     * }
     */
    private function parseHttpResponse(int $status, string $raw): array
    {
        // Nu часто отвечает 200 даже при ошибках в документе; 4xx/5xx — сервис.
        if ($status >= 500 || $status === 0) {
            return ['ok' => false, 'errors' => [], 'error' => 'vnu_status_' . $status];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return ['ok' => false, 'errors' => [], 'error' => 'vnu_bad_json'];
        }

        $messages = isset($decoded['messages']) && is_array($decoded['messages'])
            ? $decoded['messages']
            : [];

        $sampleMax = max(1, (int) config('site_audit.vnu_sample_max', 10));
        $errors = [];
        $seen = [];
        foreach ($messages as $msg) {
            if (! is_array($msg)) {
                continue;
            }
            $type = strtolower((string) ($msg['type'] ?? ''));
            // info + subType warning — шум; только error/non-document-error/fatal
            if ($type !== 'error' && $type !== 'fatal' && $type !== 'non-document-error') {
                continue;
            }
            $text = trim(preg_replace('/\s+/', ' ', (string) ($msg['message'] ?? '')) ?: '');
            if ($text === '') {
                continue;
            }
            $key = mb_strtolower($text);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $line = null;
            if (isset($msg['lastLine']) && (int) $msg['lastLine'] > 0) {
                $line = (int) $msg['lastLine'];
            } elseif (isset($msg['firstLine']) && (int) $msg['firstLine'] > 0) {
                $line = (int) $msg['firstLine'];
            }
            $errors[] = [
                'line' => $line,
                'level' => $type === 'fatal' || $type === 'non-document-error' ? 'fatal' : 'error',
                'message' => mb_substr($text, 0, 200),
            ];
            if (count($errors) >= $sampleMax) {
                break;
            }
        }

        return ['ok' => true, 'errors' => $errors, 'error' => null];
    }
}
