<?php

namespace App\Services\SiteAudit;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
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

        $maxBytes = max(50_000, (int) config('site_audit.vnu_max_bytes', 1_500_000));
        if (strlen($html) > $maxBytes) {
            $html = substr($html, 0, $maxBytes);
        }

        $url = $base . (strpos($base, '?') === false ? '?' : '&') . 'out=json';

        try {
            $client = $this->client ?: new Client([
                'timeout' => (float) config('site_audit.vnu_timeout', 8),
                'connect_timeout' => (float) config('site_audit.vnu_connect_timeout', 2),
                'http_errors' => false,
                'verify' => false,
            ]);
            $response = $client->post($url, [
                'headers' => [
                    'Content-Type' => 'text/html; charset=utf-8',
                    'Accept' => 'application/json',
                ],
                'body' => $html,
            ]);
        } catch (GuzzleException $e) {
            return ['ok' => false, 'errors' => [], 'error' => 'vnu_http: ' . $e->getMessage()];
        } catch (Throwable $e) {
            return ['ok' => false, 'errors' => [], 'error' => 'vnu_fail: ' . $e->getMessage()];
        }

        $status = $response->getStatusCode();
        $raw = (string) $response->getBody();
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
