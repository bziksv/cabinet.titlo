<?php

namespace App\Services\YandexWebmaster;

use App\Support\HomeUserSites;
use App\YandexWebmasterDomainHost;
use App\YandexWebmasterUserToken;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Throwable;

class YandexWebmasterService
{
    public function isConfigured(): bool
    {
        return (string) config('cabinet-yandex-webmaster.client_id') !== ''
            && (string) config('cabinet-yandex-webmaster.client_secret') !== '';
    }

    public function redirectUri(): string
    {
        $configured = config('cabinet-yandex-webmaster.redirect_uri');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return route('yandex-webmaster.callback');
    }

    public function isConnected(int $userId): bool
    {
        if ($userId < 1 || !YandexWebmasterUserToken::tableReady()) {
            return false;
        }

        $row = YandexWebmasterUserToken::query()->find($userId);

        return $row && (string) $row->access_token !== '';
    }

    /**
     * @param array{domain?:string,return?:string} $payload
     */
    public function buildAuthorizeUrl(int $userId, array $payload = []): string
    {
        $state = $this->encodeState([
            'uid' => $userId,
            'domain' => (string) ($payload['domain'] ?? ''),
            'return' => (string) ($payload['return'] ?? route('home')),
            'ts' => time(),
        ]);

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => config('cabinet-yandex-webmaster.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'scope' => config('cabinet-yandex-webmaster.scope', 'webmaster:hostinfo webmaster:verify'),
            'force_confirm' => 'yes',
            'state' => $state,
        ]);

        return rtrim((string) config('cabinet-yandex-webmaster.authorize_url'), '?') . '?' . $query;
    }

    /**
     * @return array{uid:int,domain:string,return:string,ts:int}|null
     */
    public function decodeState(string $state): ?array
    {
        try {
            $raw = base64_decode(strtr($state, '-_', '+/'), true);
            if ($raw === false) {
                return null;
            }
            $payload = json_decode($raw, true);
            if (!is_array($payload) || empty($payload['sig']) || empty($payload['data'])) {
                return null;
            }
            $expected = hash_hmac('sha256', $payload['data'], (string) config('app.key'));
            if (!hash_equals($expected, (string) $payload['sig'])) {
                return null;
            }
            $data = json_decode($payload['data'], true);
            if (!is_array($data) || (int) ($data['uid'] ?? 0) < 1) {
                return null;
            }
            if (!empty($data['ts']) && (time() - (int) $data['ts']) > 3600) {
                return null;
            }

            return [
                'uid' => (int) $data['uid'],
                'domain' => (string) ($data['domain'] ?? ''),
                'return' => (string) ($data['return'] ?? route('home')),
                'ts' => (int) ($data['ts'] ?? 0),
            ];
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function encodeState(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        $sig = hash_hmac('sha256', (string) $json, (string) config('app.key'));
        $packed = json_encode(['data' => $json, 'sig' => $sig], JSON_UNESCAPED_UNICODE);

        return rtrim(strtr(base64_encode((string) $packed), '+/', '-_'), '=');
    }

    /**
     * @return array{ok:bool,message?:string}
     */
    public function handleCallback(int $userId, string $code): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'message' => __('Yandex Webmaster is not configured')];
        }

        try {
            $token = $this->requestToken([
                'grant_type' => 'authorization_code',
                'code' => $code,
                'client_id' => config('cabinet-yandex-webmaster.client_id'),
                'client_secret' => config('cabinet-yandex-webmaster.client_secret'),
                'redirect_uri' => $this->redirectUri(),
            ]);
        } catch (Throwable $e) {
            Log::warning('yandex webmaster token exchange failed', ['error' => $e->getMessage()]);

            return ['ok' => false, 'message' => __('Yandex Webmaster authorization failed')];
        }

        $this->storeToken($userId, $token);

        try {
            $this->resolveYandexUserId($userId);
        } catch (Throwable $e) {
            Log::warning('yandex webmaster user id fetch failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }

        return ['ok' => true];
    }

    public function disconnect(int $userId): void
    {
        if (!YandexWebmasterUserToken::tableReady()) {
            return;
        }
        YandexWebmasterUserToken::query()->where('user_id', $userId)->delete();
    }

    /**
     * @return array<int, array{id:string,url:string,unicode_url:string,verified:bool,domain:string}>
     */
    public function listHosts(int $userId): array
    {
        $accessToken = $this->validAccessToken($userId);
        $yandexUserId = $this->resolveYandexUserId($userId);
        if ($accessToken === null || $yandexUserId < 1) {
            return [];
        }

        $client = $this->httpClient();
        $response = $client->get('v4/user/' . $yandexUserId . '/hosts', [
            'headers' => [
                'Authorization' => 'OAuth ' . $accessToken,
                'Accept' => 'application/json',
            ],
            'http_errors' => false,
        ]);

        if ($response->getStatusCode() >= 400) {
            Log::warning('yandex webmaster hosts list http error', [
                'user_id' => $userId,
                'status' => $response->getStatusCode(),
                'body' => mb_substr((string) $response->getBody(), 0, 400),
            ]);

            return [];
        }

        $body = json_decode((string) $response->getBody(), true);
        $rows = is_array($body['hosts'] ?? null) ? $body['hosts'] : [];
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $hostId = trim((string) ($row['host_id'] ?? ''));
            if ($hostId === '') {
                continue;
            }
            $ascii = (string) ($row['ascii_host_url'] ?? '');
            $unicode = (string) ($row['unicode_host_url'] ?? $ascii);
            $display = $unicode !== '' ? $unicode : $ascii;
            $domain = HomeUserSites::normalizeDomain($display !== '' ? $display : $hostId);
            $out[] = [
                'id' => $hostId,
                'url' => $ascii !== '' ? $ascii : $display,
                'unicode_url' => $unicode !== '' ? $unicode : $display,
                'verified' => !empty($row['verified']),
                'domain' => $domain,
            ];
        }

        return $out;
    }

    /**
     * @return array{ok:bool,message?:string,binding?:array<string,mixed>}
     */
    public function bindHost(int $userId, string $domain, string $hostId): array
    {
        if (!$this->isConnected($userId)) {
            return ['ok' => false, 'message' => __('Connect Yandex Webmaster first')];
        }

        $hostId = trim($hostId);
        if ($hostId === '') {
            return ['ok' => false, 'message' => __('Invalid Webmaster host')];
        }

        $hosts = $this->listHosts($userId);
        $found = null;
        foreach ($hosts as $host) {
            if ((string) $host['id'] === $hostId) {
                $found = $host;
                break;
            }
        }
        if ($found === null) {
            $found = [
                'id' => $hostId,
                'url' => $hostId,
                'unicode_url' => $hostId,
                'verified' => false,
                'domain' => HomeUserSites::normalizeDomain($domain),
            ];
        }

        $row = YandexWebmasterDomainHost::bind(
            $userId,
            $domain,
            (string) $found['id'],
            (string) ($found['unicode_url'] ?: $found['url']),
            !empty($found['verified'])
        );
        if ($row === null) {
            return ['ok' => false, 'message' => __('Invalid domain')];
        }

        \App\SeoReports\SeoReportBindings::syncWebmasterHostToProjects(
            $userId,
            (string) $row->domain,
            (string) $row->host_id
        );

        return [
            'ok' => true,
            'binding' => [
                'domain' => $row->domain,
                'host_id' => (string) $row->host_id,
                'host_url' => (string) $row->host_url,
                'verified' => (bool) $row->verified,
            ],
        ];
    }

    public function unbindHost(int $userId, string $domain): bool
    {
        $ok = YandexWebmasterDomainHost::unbind($userId, $domain);
        if ($ok) {
            \App\SeoReports\SeoReportBindings::clearWebmasterHostFromProjects($userId, $domain);
        }

        return $ok;
    }

    private function resolveYandexUserId(int $userId): int
    {
        if (!YandexWebmasterUserToken::tableReady()) {
            return 0;
        }

        /** @var YandexWebmasterUserToken|null $row */
        $row = YandexWebmasterUserToken::query()->find($userId);
        if (!$row) {
            return 0;
        }
        if ((int) $row->yandex_user_id > 0) {
            return (int) $row->yandex_user_id;
        }

        $accessToken = $this->validAccessToken($userId);
        if ($accessToken === null) {
            return 0;
        }

        $client = $this->httpClient();
        $response = $client->get('v4/user', [
            'headers' => [
                'Authorization' => 'OAuth ' . $accessToken,
                'Accept' => 'application/json',
            ],
            'http_errors' => false,
        ]);
        if ($response->getStatusCode() >= 400) {
            return 0;
        }
        $body = json_decode((string) $response->getBody(), true);
        $yandexUserId = (int) ($body['user_id'] ?? 0);
        if ($yandexUserId > 0) {
            $row->yandex_user_id = $yandexUserId;
            $row->save();
        }

        return $yandexUserId;
    }

    private function validAccessToken(int $userId): ?string
    {
        if (!YandexWebmasterUserToken::tableReady()) {
            return null;
        }

        /** @var YandexWebmasterUserToken|null $row */
        $row = YandexWebmasterUserToken::query()->find($userId);
        if (!$row || (string) $row->access_token === '') {
            return null;
        }

        if ($row->isExpired() && (string) $row->refresh_token !== '') {
            try {
                $token = $this->requestToken([
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $row->refresh_token,
                    'client_id' => config('cabinet-yandex-webmaster.client_id'),
                    'client_secret' => config('cabinet-yandex-webmaster.client_secret'),
                ]);
                $this->storeToken($userId, $token);
                $row = YandexWebmasterUserToken::query()->find($userId);
            } catch (Throwable $e) {
                Log::warning('yandex webmaster refresh failed', ['user_id' => $userId, 'error' => $e->getMessage()]);

                return null;
            }
        }

        return $row ? (string) $row->access_token : null;
    }

    /**
     * @param array<string, string> $form
     * @return array<string, mixed>
     */
    private function requestToken(array $form): array
    {
        $client = new Client([
            'timeout' => (int) config('cabinet-yandex-webmaster.timeout', 20),
            'http_errors' => false,
        ]);
        $response = $client->post((string) config('cabinet-yandex-webmaster.token_url'), [
            'form_params' => $form,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);
        $status = $response->getStatusCode();
        $body = json_decode((string) $response->getBody(), true);
        if ($status >= 400 || !is_array($body) || empty($body['access_token'])) {
            throw new \RuntimeException('Token response invalid: HTTP ' . $status);
        }

        return $body;
    }

    /**
     * @param array<string, mixed> $token
     */
    private function storeToken(int $userId, array $token): void
    {
        if (!YandexWebmasterUserToken::tableReady()) {
            throw new \RuntimeException('yandex_webmaster_user_tokens table missing');
        }

        $expiresIn = (int) ($token['expires_in'] ?? 0);
        $payload = [
            'access_token' => (string) $token['access_token'],
            'refresh_token' => isset($token['refresh_token']) ? (string) $token['refresh_token'] : null,
            'expires_at' => $expiresIn > 0 ? Carbon::now()->addSeconds($expiresIn - 60) : null,
            'yandex_login' => isset($token['login']) ? (string) $token['login'] : null,
        ];

        YandexWebmasterUserToken::query()->updateOrCreate(
            ['user_id' => $userId],
            $payload
        );
    }

    /**
     * Диагностика сайта (ошибки FATAL / CRITICAL / POSSIBLE_PROBLEM).
     *
     * @return array{ok:bool,problems?:array<string,array{severity:string,state:string,last_state_update:?string}>,message?:string}
     */
    public function getDiagnostics(int $userId, string $hostId): array
    {
        $payload = $this->apiGet($userId, $hostId, 'diagnostics');
        if (!$payload['ok']) {
            return $payload;
        }
        $body = $payload['body'];
        $raw = is_array($body['problems'] ?? null) ? $body['problems'] : [];
        $problems = [];
        foreach ($raw as $code => $row) {
            if (!is_array($row)) {
                continue;
            }
            $problems[(string) $code] = [
                'severity' => (string) ($row['severity'] ?? ''),
                'state' => (string) ($row['state'] ?? ''),
                'last_state_update' => isset($row['last_state_update']) ? (string) $row['last_state_update'] : null,
            ];
        }

        return ['ok' => true, 'problems' => $problems];
    }

    /**
     * Примеры страниц, появившихся в поиске или исключённых (в т.ч. LOW_QUALITY).
     *
     * @return array{ok:bool,samples?:list<array<string,mixed>>,count?:int,message?:string}
     */
    public function getSearchUrlEventSamples(int $userId, string $hostId, int $limit = 100, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        $payload = $this->apiGet($userId, $hostId, 'search-urls/events/samples', [
            'limit' => $limit,
            'offset' => $offset,
        ]);
        if (!$payload['ok']) {
            return $payload;
        }
        $body = $payload['body'];
        $rows = is_array($body['samples'] ?? null) ? $body['samples'] : [];
        $samples = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $samples[] = [
                'url' => (string) ($row['url'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'event' => (string) ($row['event'] ?? ''),
                'excluded_url_status' => (string) ($row['excluded_url_status'] ?? ''),
                'event_date' => isset($row['event_date']) ? (string) $row['event_date'] : null,
                'last_access' => isset($row['last_access']) ? (string) $row['last_access'] : null,
                'target_url' => isset($row['target_url']) ? (string) $row['target_url'] : null,
                'bad_http_status' => isset($row['bad_http_status']) ? (int) $row['bad_http_status'] : null,
            ];
        }

        return [
            'ok' => true,
            'count' => (int) ($body['count'] ?? count($samples)),
            'samples' => $samples,
        ];
    }

    /**
     * Сводка по всем поисковым запросам за период (клики / показы / позиция по дням).
     *
     * @return array{
     *   ok:bool,
     *   kpis?:array{clicks:?int,impressions:?int,ctr:?float,position:?float},
     *   message?:string
     * }
     */
    public function getSearchQueriesHistoryAll(
        int $userId,
        string $hostId,
        string $dateFrom,
        string $dateTo
    ): array {
        $dateFrom = trim($dateFrom);
        $dateTo = trim($dateTo);
        if ($dateFrom === '' || $dateTo === '') {
            return ['ok' => false, 'message' => __('Yandex Webmaster API error')];
        }

        $payload = $this->apiGet($userId, $hostId, 'search-queries/all/history', [
            'query_indicator' => ['TOTAL_SHOWS', 'TOTAL_CLICKS', 'AVG_SHOW_POSITION'],
            'device_type_indicator' => 'ALL',
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);
        if (!$payload['ok']) {
            return $payload;
        }

        $indicators = is_array($payload['body']['indicators'] ?? null)
            ? $payload['body']['indicators']
            : [];
        $showsSeries = is_array($indicators['TOTAL_SHOWS'] ?? null) ? $indicators['TOTAL_SHOWS'] : [];
        $clicksSeries = is_array($indicators['TOTAL_CLICKS'] ?? null) ? $indicators['TOTAL_CLICKS'] : [];
        $posSeries = is_array($indicators['AVG_SHOW_POSITION'] ?? null) ? $indicators['AVG_SHOW_POSITION'] : [];

        $impressions = 0.0;
        foreach ($showsSeries as $row) {
            if (is_array($row)) {
                $impressions += (float) ($row['value'] ?? 0);
            }
        }
        $clicks = 0.0;
        foreach ($clicksSeries as $row) {
            if (is_array($row)) {
                $clicks += (float) ($row['value'] ?? 0);
            }
        }

        $posWeight = 0.0;
        $posSum = 0.0;
        $showsByDate = [];
        foreach ($showsSeries as $row) {
            if (!is_array($row)) {
                continue;
            }
            $d = substr((string) ($row['date'] ?? ''), 0, 10);
            if ($d !== '') {
                $showsByDate[$d] = (float) ($row['value'] ?? 0);
            }
        }
        foreach ($posSeries as $row) {
            if (!is_array($row)) {
                continue;
            }
            $d = substr((string) ($row['date'] ?? ''), 0, 10);
            $pos = (float) ($row['value'] ?? 0);
            $w = $showsByDate[$d] ?? 1.0;
            if ($w <= 0) {
                $w = 1.0;
            }
            $posSum += $pos * $w;
            $posWeight += $w;
        }

        if ($impressions <= 0 && $clicks <= 0 && $posWeight <= 0) {
            return [
                'ok' => true,
                'kpis' => [
                    'clicks' => 0,
                    'impressions' => 0,
                    'ctr' => 0,
                    'position' => null,
                ],
            ];
        }

        return [
            'ok' => true,
            'kpis' => [
                'clicks' => (int) round($clicks),
                'impressions' => (int) round($impressions),
                'ctr' => $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0,
                'position' => $posWeight > 0 ? round($posSum / $posWeight, 1) : null,
            ],
        ];
    }

    /**
     * Топ популярных поисковых запросов за период.
     *
     * @return array{ok:bool,queries?:list<array{name:string,clicks:int,impressions:int,ctr:?float,position:?float}>,message?:string}
     */
    public function getSearchQueriesPopular(
        int $userId,
        string $hostId,
        string $dateFrom,
        string $dateTo,
        int $limit = 25,
        string $orderBy = 'TOTAL_CLICKS'
    ): array {
        $dateFrom = trim($dateFrom);
        $dateTo = trim($dateTo);
        if ($dateFrom === '' || $dateTo === '') {
            return ['ok' => false, 'message' => __('Yandex Webmaster API error')];
        }

        $orderBy = $orderBy === 'TOTAL_SHOWS' ? 'TOTAL_SHOWS' : 'TOTAL_CLICKS';
        $limit = max(1, min(500, $limit));

        $payload = $this->apiGet($userId, $hostId, 'search-queries/popular', [
            'order_by' => $orderBy,
            'query_indicator' => ['TOTAL_SHOWS', 'TOTAL_CLICKS', 'AVG_SHOW_POSITION'],
            'device_type_indicator' => 'ALL',
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'offset' => 0,
            'limit' => $limit,
        ]);
        if (!$payload['ok']) {
            return $payload;
        }

        $rows = is_array($payload['body']['queries'] ?? null) ? $payload['body']['queries'] : [];
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['query_text'] ?? ''));
            if ($name === '') {
                continue;
            }
            $ind = is_array($row['indicators'] ?? null) ? $row['indicators'] : [];
            $impressions = (float) ($ind['TOTAL_SHOWS'] ?? 0);
            $clicks = (float) ($ind['TOTAL_CLICKS'] ?? 0);
            $position = isset($ind['AVG_SHOW_POSITION']) ? (float) $ind['AVG_SHOW_POSITION'] : null;
            $out[] = [
                'name' => $name,
                'clicks' => (int) round($clicks),
                'impressions' => (int) round($impressions),
                'ctr' => $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : null,
                'position' => $position !== null ? round($position, 1) : null,
            ];
        }

        return ['ok' => true, 'queries' => $out];
    }

    /**
     * Актуальная оценка числа страниц в поиске (search-urls/in-search/history).
     *
     * @return array{ok:bool,count?:?int,date?:?string,message?:string}
     */
    public function getInSearchCount(int $userId, string $hostId): array
    {
        $payload = $this->apiGet($userId, $hostId, 'search-urls/in-search/history');
        if (!$payload['ok']) {
            return $payload;
        }
        $history = is_array($payload['body']['history'] ?? null) ? $payload['body']['history'] : [];
        if ($history === []) {
            return ['ok' => true, 'count' => null, 'date' => null];
        }

        $last = end($history);
        if (! is_array($last)) {
            return ['ok' => true, 'count' => null, 'date' => null];
        }

        return [
            'ok' => true,
            'count' => isset($last['value']) ? (int) $last['value'] : null,
            'date' => isset($last['date']) ? (string) $last['date'] : null,
        ];
    }

    /**
     * Одна страница примеров URL в поиске (limit API: 1–100).
     *
     * @return array{ok:bool,samples?:list<array{url:string,title:string,last_access:?string}>,count?:int,message?:string}
     */
    public function getInSearchSamplesPage(int $userId, string $hostId, int $limit = 100, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        $payload = $this->apiGet($userId, $hostId, 'search-urls/in-search/samples', [
            'limit' => $limit,
            'offset' => $offset,
        ], 45);
        if (!$payload['ok']) {
            return $payload;
        }
        $body = $payload['body'];
        $rows = is_array($body['samples'] ?? null) ? $body['samples'] : [];
        $samples = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $url = trim((string) ($row['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $samples[] = [
                'url' => $url,
                'title' => (string) ($row['title'] ?? ''),
                'last_access' => isset($row['last_access']) ? (string) $row['last_access'] : null,
            ];
        }

        return [
            'ok' => true,
            'count' => (int) ($body['count'] ?? count($samples)),
            'samples' => $samples,
        ];
    }

    /**
     * Пакетный список URL в поиске (до maxUrls, API отдаёт ≤50 000 примеров).
     *
     * @return array{
     *   ok:bool,
     *   urls?:list<string>,
     *   count?:int,
     *   total_available?:?int,
     *   truncated?:bool,
     *   pages_fetched?:int,
     *   message?:string
     * }
     */
    public function collectInSearchUrls(int $userId, string $hostId, int $maxUrls = 50000): array
    {
        $maxUrls = max(100, min(50000, $maxUrls));
        $pageSize = 100;
        $urls = [];
        $seen = [];
        $totalAvailable = null;
        $pagesFetched = 0;
        $offset = 0;

        while (count($urls) < $maxUrls) {
            $page = $this->getInSearchSamplesPage($userId, $hostId, $pageSize, $offset);
            if (!$page['ok']) {
                if ($urls === []) {
                    return $page;
                }
                break;
            }

            $pagesFetched++;
            if ($totalAvailable === null && isset($page['count'])) {
                $totalAvailable = (int) $page['count'];
            }

            $chunk = is_array($page['samples'] ?? null) ? $page['samples'] : [];
            if ($chunk === []) {
                break;
            }

            foreach ($chunk as $row) {
                $url = trim((string) ($row['url'] ?? ''));
                if ($url === '') {
                    continue;
                }
                $key = strtolower($url);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $urls[] = $url;
                if (count($urls) >= $maxUrls) {
                    break 2;
                }
            }

            if (count($chunk) < $pageSize) {
                break;
            }
            if ($totalAvailable !== null && ($offset + count($chunk)) >= $totalAvailable) {
                break;
            }

            $offset += $pageSize;
            usleep(50000);
        }

        $truncated = count($urls) >= $maxUrls
            || ($totalAvailable !== null && $totalAvailable > count($urls));

        return [
            'ok' => true,
            'urls' => $urls,
            'count' => count($urls),
            'total_available' => $totalAvailable,
            'truncated' => $truncated,
            'pages_fetched' => $pagesFetched,
        ];
    }

    /**
     * @param array<string,scalar|list<scalar>> $query
     * @return array{ok:bool,body?:array<string,mixed>,message?:string}
     */
    private function apiGet(int $userId, string $hostId, string $path, array $query = [], ?int $timeout = null): array
    {
        $hostId = trim($hostId);
        if ($hostId === '') {
            return ['ok' => false, 'message' => __('Invalid Webmaster host')];
        }

        $accessToken = $this->validAccessToken($userId);
        $yandexUserId = $this->resolveYandexUserId($userId);
        if ($accessToken === null || $yandexUserId < 1) {
            return ['ok' => false, 'message' => __('Connect Yandex Webmaster first')];
        }

        // Яндекс ждёт повтор query_indicator=…&query_indicator=…, не query_indicator[0]=…
        $queryString = $this->buildQueryString($query);

        try {
            $client = $this->httpClient($timeout);
            $uri = 'v4/user/' . $yandexUserId . '/hosts/' . rawurlencode($hostId) . '/' . ltrim($path, '/');
            if ($queryString !== '') {
                $uri .= '?' . $queryString;
            }
            $response = $client->get($uri, [
                'headers' => [
                    'Authorization' => 'OAuth ' . $accessToken,
                    'Accept' => 'application/json',
                ],
                'http_errors' => false,
            ]);
        } catch (Throwable $e) {
            Log::warning('yandex webmaster api request failed', [
                'user_id' => $userId,
                'host_id' => $hostId,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'message' => $e->getMessage()];
        }

        $status = $response->getStatusCode();
        $rawBody = (string) $response->getBody();
        if ($status >= 400) {
            Log::warning('yandex webmaster api http error', [
                'user_id' => $userId,
                'host_id' => $hostId,
                'path' => $path,
                'status' => $status,
                'body' => mb_substr($rawBody, 0, 400),
            ]);

            return ['ok' => false, 'message' => __('Yandex Webmaster API error') . ' (' . $status . ')'];
        }

        $body = json_decode($rawBody, true);

        return [
            'ok' => true,
            'body' => is_array($body) ? $body : [],
        ];
    }

    /**
     * @param array<string,scalar|list<scalar>> $query
     */
    private function buildQueryString(array $query): string
    {
        $parts = [];
        foreach ($query as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $item) {
                    if ($item === null || $item === '') {
                        continue;
                    }
                    $parts[] = rawurlencode((string) $key) . '=' . rawurlencode((string) $item);
                }
                continue;
            }
            if ($value === null || $value === '') {
                continue;
            }
            $parts[] = rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
        }

        return implode('&', $parts);
    }

    private function httpClient(?int $timeout = null): Client
    {
        return new Client([
            'base_uri' => rtrim((string) config('cabinet-yandex-webmaster.api_base'), '/') . '/',
            'timeout' => $timeout !== null
                ? max(5, $timeout)
                : (int) config('cabinet-yandex-webmaster.timeout', 20),
            'http_errors' => true,
        ]);
    }
}
