<?php

namespace App\Services\SiteAudit;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RedirectMiddleware;

class SiteAuditFetcher
{
    /** @var Client */
    private $client;

    /** @var int|null */
    private $crawlId;

    public function __construct(?Client $client = null, ?int $crawlId = null)
    {
        $this->crawlId = $crawlId;
        $this->client = $client ?: new Client([
            'timeout' => config('site_audit.request_timeout', 15),
            'connect_timeout' => 10,
            'http_errors' => false,
            'allow_redirects' => [
                'max' => config('site_audit.max_redirects', 10),
                'track_redirects' => true,
            ],
            'verify' => true,
            'headers' => [],
        ]);
    }

    public static function fromCrawlSettings(array $settings, ?int $crawlId = null): self
    {
        return new self(null, $crawlId);
    }

    /**
     * @return array{ok:bool,status_code:?int,final_url:string,redirect_chain:array,body:?string,size_bytes:int,content_type:?string,x_robots:?string,sec_headers:array{hsts:bool,x_frame:bool,x_content_type:bool},error:?string,user_agent:?string,ua_rotated:bool}
     */
    public function fetch(string $url): array
    {
        $ua = $this->resolveUa();
        $result = $this->doRequest($url, $ua);

        $bad = SiteAuditUserAgentSession::shouldRotate(
            $result['status_code'],
            ! $result['ok'] && $result['body'] === null
        );

        // sticky до ошибки; при 403/429/503/transport — новый UA + один retry
        if ($this->crawlId && $bad) {
            $ua = SiteAuditUserAgentSession::rotate($this->crawlId, $ua);
            $retry = $this->doRequest($url, $ua);
            $retry['ua_rotated'] = true;
            $retry['user_agent'] = $ua;

            return $retry;
        }

        $result['ua_rotated'] = false;
        $result['user_agent'] = $ua;

        return $result;
    }

    private function resolveUa(): string
    {
        if ($this->crawlId) {
            return SiteAuditUserAgentSession::current($this->crawlId, true);
        }

        $pool = config('site_audit.user_agents', []);
        if (is_array($pool) && $pool) {
            return $pool[array_rand($pool)];
        }

        return (string) config('site_audit.user_agent', 'Mozilla/5.0');
    }

    private function doRequest(string $url, string $ua): array
    {
        $headers = $this->browserHeaders($ua);

        try {
            $response = $this->client->get($url, ['headers' => $headers]);
            $status = $response->getStatusCode();
            $body = (string) $response->getBody();
            $history = $response->getHeader(RedirectMiddleware::HISTORY_HEADER);
            $chain = [];
            if (is_array($history) && $history) {
                foreach ($history as $h) {
                    $chain[] = $h;
                }
            }
            $final = $url;
            if ($chain) {
                $final = end($chain) ?: $url;
                reset($chain);
            }
            $hist = $response->getHeader('X-Guzzle-Redirect-History');
            if ($hist) {
                $final = end($hist) ?: $final;
            }

            return [
                'ok' => true,
                'status_code' => $status,
                'final_url' => $final,
                'redirect_chain' => $chain,
                'body' => $body,
                'size_bytes' => strlen($body),
                'content_type' => $response->getHeaderLine('Content-Type') ?: null,
                'x_robots' => $response->getHeaderLine('X-Robots-Tag') ?: null,
                'sec_headers' => [
                    'hsts' => $response->getHeaderLine('Strict-Transport-Security') !== '',
                    'x_frame' => $response->getHeaderLine('X-Frame-Options') !== '',
                    'x_content_type' => $response->getHeaderLine('X-Content-Type-Options') !== '',
                ],
                'error' => null,
                'user_agent' => $ua,
                'ua_rotated' => false,
            ];
        } catch (RequestException $e) {
            return $this->fail($url, $ua, $e->hasResponse() && $e->getResponse()
                ? $e->getResponse()->getStatusCode()
                : null, $e->getMessage());
        } catch (\Throwable $e) {
            return $this->fail($url, $ua, null, $e->getMessage());
        }
    }

    private function fail(string $url, string $ua, ?int $status, string $error): array
    {
        return [
            'ok' => false,
            'status_code' => $status,
            'final_url' => $url,
            'redirect_chain' => [],
            'body' => null,
            'size_bytes' => 0,
            'content_type' => null,
            'x_robots' => null,
            'sec_headers' => [
                'hsts' => false,
                'x_frame' => false,
                'x_content_type' => false,
            ],
            'error' => $error,
            'user_agent' => $ua,
            'ua_rotated' => false,
        ];
    }

    private function browserHeaders(string $ua): array
    {
        $isChrome = stripos($ua, 'Chrome') !== false && stripos($ua, 'Edg') === false;
        $isFirefox = stripos($ua, 'Firefox') !== false;

        $headers = [
            'User-Agent' => $ua,
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language' => 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
            'Accept-Encoding' => 'gzip, deflate',
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache',
            'Upgrade-Insecure-Requests' => '1',
            'Connection' => 'keep-alive',
        ];

        if ($isChrome || $isFirefox) {
            $headers['Sec-Fetch-Dest'] = 'document';
            $headers['Sec-Fetch-Mode'] = 'navigate';
            $headers['Sec-Fetch-Site'] = 'none';
            $headers['Sec-Fetch-User'] = '?1';
        }

        return $headers;
    }
}
