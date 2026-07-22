<?php

namespace App\Services\SiteAudit;

use GuzzleHttp\Client;

/**
 * Fetch + sanity-check robots.txt и проверка Disallow для URL.
 */
class SiteAuditRobotsTxt
{
    /** @var Client */
    private $client;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?: new Client([
            'timeout' => config('site_audit.request_timeout', 15),
            'connect_timeout' => 10,
            'http_errors' => false,
            'allow_redirects' => ['max' => 5],
            'verify' => true,
            'headers' => [
                'User-Agent' => (string) config('site_audit.user_agent', 'TitloSiteAuditBot/1.0'),
                'Accept' => 'text/plain,*/*',
            ],
        ]);
    }

    /**
     * @return array{
     *   url:string,
     *   status_code:?int,
     *   ok:bool,
     *   closed:bool,
     *   sitemaps:array,
     *   groups:array,
     *   findings:array<int,array{code:string,meta:array}>,
     *   error:?string
     * }
     */
    public function analyze(string $domain): array
    {
        $host = preg_replace('#^https?://#i', '', trim($domain));
        $host = rtrim((string) $host, '/');
        $url = 'https://' . $host . '/robots.txt';

        $result = [
            'url' => $url,
            'status_code' => null,
            'ok' => false,
            'closed' => false,
            'sitemaps' => [],
            'groups' => [],
            'findings' => [],
            'error' => null,
        ];

        try {
            $response = $this->client->get($url);
            $code = $response->getStatusCode();
            $result['status_code'] = $code;
            $body = (string) $response->getBody();

            if ($code === 404 || $code === 410) {
                // нет robots.txt — не ошибка
                $result['ok'] = true;

                return $result;
            }

            if ($code < 200 || $code >= 400) {
                $result['findings'][] = [
                    'code' => 'robots_txt_error',
                    'meta' => ['reason' => 'http_status', 'status' => $code],
                ];
                $result['error'] = 'HTTP ' . $code;

                return $result;
            }

            $maxBytes = (int) config('site_audit.robots_max_bytes', 512000);
            if (strlen($body) > $maxBytes) {
                $result['findings'][] = [
                    'code' => 'robots_txt_error',
                    'meta' => ['reason' => 'too_large', 'size' => strlen($body), 'max' => $maxBytes],
                ];
            }

            if ($body === '' || trim($body) === '') {
                $result['findings'][] = [
                    'code' => 'robots_txt_error',
                    'meta' => ['reason' => 'empty'],
                ];
                $result['ok'] = true;

                return $result;
            }

            $parsed = $this->parse($body);
            $result['sitemaps'] = $parsed['sitemaps'];
            $result['groups'] = $parsed['groups'];
            $result['ok'] = true;

            foreach ($parsed['line_errors'] as $err) {
                $result['findings'][] = [
                    'code' => 'robots_txt_error',
                    'meta' => $err,
                ];
            }

            foreach ($parsed['sitemaps'] as $sm) {
                if (! filter_var($sm, FILTER_VALIDATE_URL)) {
                    $result['findings'][] = [
                        'code' => 'robots_txt_error',
                        'meta' => ['reason' => 'bad_sitemap', 'sitemap' => $sm],
                    ];
                }
            }

            if ($this->isSiteClosed($parsed['groups'])) {
                $result['closed'] = true;
                $result['findings'][] = [
                    'code' => 'robots_txt_closed',
                    'meta' => ['reason' => 'disallow_all', 'agent' => '*'],
                ];
            }
        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
            $result['findings'][] = [
                'code' => 'robots_txt_error',
                'meta' => ['reason' => 'fetch_failed', 'error' => $e->getMessage()],
            ];
        }

        return $result;
    }

    /**
     * @param array $groups from analyze()/parse()
     */
    public function isPathAllowed(array $groups, string $urlOrPath, string $ua = '*'): bool
    {
        $path = $urlOrPath;
        if (preg_match('#^https?://#i', $urlOrPath)) {
            $parts = parse_url($urlOrPath);
            $path = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
        }
        if ($path === '') {
            $path = '/';
        }

        $ua = strtolower($ua);
        $group = $this->pickGroup($groups, $ua);
        if (! $group) {
            return true;
        }

        $matched = null;
        $matchedLen = -1;
        $allowed = true;

        foreach ($group['rules'] as $rule) {
            $prefix = $rule['path'];
            if ($prefix === '') {
                continue;
            }
            // простой prefix match (без full wildcards)
            $pattern = str_replace(['*', '$'], ['', ''], $prefix);
            if ($pattern === '/') {
                $ok = true;
                $len = 1;
            } else {
                $ok = strpos($path, $pattern) === 0;
                $len = strlen($pattern);
            }
            if ($ok && $len >= $matchedLen) {
                $matchedLen = $len;
                $allowed = $rule['allow'];
                $matched = $rule;
            }
        }

        return $matched === null ? true : $allowed;
    }

    /**
     * @return array{groups:array,sitemaps:array,line_errors:array}
     */
    public function parse(string $body): array
    {
        $groups = [];
        $sitemaps = [];
        $lineErrors = [];
        $current = null;
        $pendingAgents = [];

        $lines = preg_split("/\r\n|\n|\r/", $body) ?: [];
        foreach ($lines as $idx => $raw) {
            $line = trim($raw);
            if ($line === '' || (isset($line[0]) && $line[0] === '#')) {
                continue;
            }

            if (! preg_match('/^([A-Za-z-]+)\s*:\s*(.*)$/u', $line, $m)) {
                $lineErrors[] = [
                    'reason' => 'bad_line',
                    'line' => $idx + 1,
                    'text' => mb_substr($line, 0, 120),
                ];
                continue;
            }

            $key = strtolower($m[1]);
            $val = trim($m[2]);

            if ($key === 'user-agent') {
                if ($current && $current['rules']) {
                    $groups[] = $current;
                    $current = null;
                    $pendingAgents = [];
                }
                $pendingAgents[] = strtolower($val);
                $current = [
                    'agents' => $pendingAgents,
                    'rules' => $current['rules'] ?? [],
                ];
                continue;
            }

            if ($key === 'sitemap') {
                if ($val !== '') {
                    $sitemaps[] = $val;
                }
                continue;
            }

            if ($key === 'disallow' || $key === 'allow') {
                if (! $current) {
                    $current = ['agents' => ['*'], 'rules' => []];
                }
                $current['rules'][] = [
                    'allow' => $key === 'allow',
                    'path' => $val,
                ];
                continue;
            }
        }

        if ($current) {
            $groups[] = $current;
        }

        return [
            'groups' => $groups,
            'sitemaps' => array_values(array_unique($sitemaps)),
            'line_errors' => $lineErrors,
        ];
    }

    private function isSiteClosed(array $groups): bool
    {
        $group = $this->pickGroup($groups, '*');
        if (! $group) {
            return false;
        }

        foreach ($group['rules'] as $rule) {
            if (! $rule['allow'] && ($rule['path'] === '/' || $rule['path'] === '/*')) {
                // если есть более специфичный Allow — не считаем закрытым
                foreach ($group['rules'] as $other) {
                    if ($other['allow'] && $other['path'] !== '' && $other['path'] !== '/') {
                        return false;
                    }
                }

                return true;
            }
        }

        return false;
    }

    private function pickGroup(array $groups, string $ua): ?array
    {
        $star = null;
        foreach ($groups as $g) {
            $agents = $g['agents'] ?? [];
            if (in_array($ua, $agents, true)) {
                return $g;
            }
            if (in_array('*', $agents, true)) {
                $star = $g;
            }
        }

        return $star;
    }
}
