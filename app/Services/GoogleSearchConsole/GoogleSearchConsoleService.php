<?php

namespace App\Services\GoogleSearchConsole;

use App\GoogleSearchConsoleDomainProperty;
use App\GoogleSearchConsoleUserToken;
use App\Support\HomeUserSites;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Throwable;

class GoogleSearchConsoleService
{
    public function isConfigured(): bool
    {
        return (string) config('cabinet-google-search-console.client_id') !== ''
            && (string) config('cabinet-google-search-console.client_secret') !== '';
    }

    public function redirectUri(): string
    {
        $configured = config('cabinet-google-search-console.redirect_uri');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return route('google-search-console.callback');
    }

    public function isConnected(int $userId): bool
    {
        if ($userId < 1 || !GoogleSearchConsoleUserToken::tableReady()) {
            return false;
        }

        $row = GoogleSearchConsoleUserToken::query()->find($userId);

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
            'client_id' => config('cabinet-google-search-console.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'scope' => config('cabinet-google-search-console.scope'),
            'access_type' => 'offline',
            // Всегда запрашиваем полный набор прав (не инкрементальный OAuth).
            'prompt' => 'consent select_account',
            'state' => $state,
        ]);

        return rtrim((string) config('cabinet-google-search-console.authorize_url'), '?') . '?' . $query;
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
            return ['ok' => false, 'message' => __('Google Search Console is not configured')];
        }

        try {
            $token = $this->requestToken([
                'grant_type' => 'authorization_code',
                'code' => $code,
                'client_id' => config('cabinet-google-search-console.client_id'),
                'client_secret' => config('cabinet-google-search-console.client_secret'),
                'redirect_uri' => $this->redirectUri(),
            ]);
        } catch (Throwable $e) {
            Log::warning('gsc token exchange failed', ['error' => $e->getMessage()]);

            return ['ok' => false, 'message' => __('Google Search Console authorization failed')];
        }

        $this->storeToken($userId, $token);

        try {
            $this->resolveGoogleEmail($userId);
        } catch (Throwable $e) {
            Log::warning('gsc user email fetch failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }

        if (!$this->tokenHasWebmasterScope($userId)) {
            $this->disconnect($userId);
            Log::warning('gsc token missing webmasters scope', ['user_id' => $userId]);

            return [
                'ok' => false,
                'message' => __('Google Search Console scope missing reconnect'),
            ];
        }

        return ['ok' => true];
    }

    public function disconnect(int $userId): void
    {
        if (!GoogleSearchConsoleUserToken::tableReady()) {
            return;
        }
        GoogleSearchConsoleUserToken::query()->where('user_id', $userId)->delete();
    }

    /**
     * @return array{ok:bool,properties?:array<int, array{id:string,url:string,unicode_url:string,verified:bool,domain:string}>,need_reauth?:bool,message?:string}
     */
    public function fetchProperties(int $userId): array
    {
        $accessToken = $this->validAccessToken($userId);
        if ($accessToken === null) {
            return [
                'ok' => false,
                'need_reauth' => true,
                'message' => __('Connect Google Search Console first'),
            ];
        }

        if (!$this->tokenHasWebmasterScope($userId, $accessToken)) {
            return [
                'ok' => false,
                'need_reauth' => true,
                'message' => __('Google Search Console scope missing reconnect'),
            ];
        }

        $client = $this->httpClient();
        $response = $client->get('sites', [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept' => 'application/json',
            ],
            'http_errors' => false,
        ]);

        $status = $response->getStatusCode();
        $raw = (string) $response->getBody();
        if ($status >= 400) {
            Log::warning('gsc sites list http error', [
                'user_id' => $userId,
                'status' => $status,
                'body' => mb_substr($raw, 0, 400),
            ]);
            $needReauth = $status === 401 || $status === 403;

            return [
                'ok' => false,
                'need_reauth' => $needReauth,
                'message' => $needReauth
                    ? __('Google Search Console scope missing reconnect')
                    : __('Could not load GSC properties'),
            ];
        }

        $body = json_decode($raw, true);
        $rows = is_array($body['siteEntry'] ?? null) ? $body['siteEntry'] : [];
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $siteUrl = trim((string) ($row['siteUrl'] ?? ''));
            if ($siteUrl === '') {
                continue;
            }
            $permission = (string) ($row['permissionLevel'] ?? '');
            $verified = $permission !== '' && $permission !== 'siteUnverifiedUser';
            $domain = $this->domainFromPropertyId($siteUrl);
            $out[] = [
                'id' => $siteUrl,
                'url' => $siteUrl,
                'unicode_url' => $siteUrl,
                'verified' => $verified,
                'domain' => $domain,
            ];
        }

        return ['ok' => true, 'properties' => $out];
    }

    /**
     * @return array<int, array{id:string,url:string,unicode_url:string,verified:bool,domain:string}>
     */
    public function listProperties(int $userId): array
    {
        $result = $this->fetchProperties($userId);

        return !empty($result['ok']) && is_array($result['properties'] ?? null)
            ? $result['properties']
            : [];
    }

    /**
     * @return array{ok:bool,message?:string,binding?:array<string,mixed>}
     */
    public function bindProperty(int $userId, string $domain, string $propertyId): array
    {
        if (!$this->isConnected($userId)) {
            return ['ok' => false, 'message' => __('Connect Google Search Console first')];
        }

        $propertyId = trim($propertyId);
        if ($propertyId === '') {
            return ['ok' => false, 'message' => __('Invalid GSC property')];
        }

        $properties = $this->listProperties($userId);
        $found = null;
        foreach ($properties as $property) {
            if ((string) $property['id'] === $propertyId) {
                $found = $property;
                break;
            }
        }
        if ($found === null) {
            $found = [
                'id' => $propertyId,
                'url' => $propertyId,
                'unicode_url' => $propertyId,
                'verified' => false,
                'domain' => HomeUserSites::normalizeDomain($domain),
            ];
        }

        $row = GoogleSearchConsoleDomainProperty::bind(
            $userId,
            $domain,
            (string) $found['id'],
            (string) ($found['unicode_url'] ?: $found['url']),
            !empty($found['verified'])
        );
        if ($row === null) {
            return ['ok' => false, 'message' => __('Invalid domain')];
        }

        \App\SeoReports\SeoReportBindings::syncGscPropertyToProjects(
            $userId,
            (string) $row->domain,
            (string) $row->property_id
        );

        return [
            'ok' => true,
            'binding' => [
                'domain' => $row->domain,
                'property_id' => (string) $row->property_id,
                'property_url' => (string) $row->property_url,
                'verified' => (bool) $row->verified,
            ],
        ];
    }

    public function unbindProperty(int $userId, string $domain): bool
    {
        $ok = GoogleSearchConsoleDomainProperty::unbind($userId, $domain);
        if ($ok) {
            \App\SeoReports\SeoReportBindings::clearGscPropertyFromProjects($userId, $domain);
        }

        return $ok;
    }

    /**
     * Search Analytics query (KPI / queries / pages).
     *
     * @param list<string> $dimensions e.g. [] | ['query'] | ['page']
     * @return array{ok:bool,rows?:list<array<string,mixed>>,message?:string,need_reauth?:bool}
     */
    public function fetchSearchAnalytics(
        int $userId,
        string $propertyId,
        string $startDate,
        string $endDate,
        array $dimensions = [],
        int $rowLimit = 25
    ): array {
        $propertyId = trim($propertyId);
        $startDate = trim($startDate);
        $endDate = trim($endDate);
        if ($propertyId === '' || $startDate === '' || $endDate === '') {
            return ['ok' => false, 'message' => __('Invalid GSC property')];
        }

        $accessToken = $this->validAccessToken($userId);
        if ($accessToken === null) {
            return [
                'ok' => false,
                'need_reauth' => true,
                'message' => __('Connect Google Search Console first'),
            ];
        }

        $dims = [];
        foreach ($dimensions as $dim) {
            $dim = trim((string) $dim);
            if ($dim !== '') {
                $dims[] = $dim;
            }
        }

        $payload = [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'rowLimit' => max(1, min(25000, $rowLimit)),
        ];
        if ($dims !== []) {
            $payload['dimensions'] = $dims;
        }

        $path = 'sites/' . rawurlencode($propertyId) . '/searchAnalytics/query';
        $client = $this->httpClient();
        $response = $client->post($path, [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
            'http_errors' => false,
        ]);

        $status = $response->getStatusCode();
        $raw = (string) $response->getBody();
        if ($status >= 400) {
            Log::warning('gsc searchAnalytics http error', [
                'user_id' => $userId,
                'property' => $propertyId,
                'status' => $status,
                'body' => mb_substr($raw, 0, 400),
            ]);
            $needReauth = $status === 401 || $status === 403;

            return [
                'ok' => false,
                'need_reauth' => $needReauth,
                'message' => $needReauth
                    ? __('Google Search Console scope missing reconnect')
                    : __('Could not load GSC search analytics'),
            ];
        }

        $body = json_decode($raw, true);
        $rows = is_array($body['rows'] ?? null) ? $body['rows'] : [];

        return ['ok' => true, 'rows' => $rows];
    }

    public function domainFromPropertyId(string $propertyId): string
    {
        $propertyId = trim($propertyId);
        if ($propertyId === '') {
            return '';
        }
        if (stripos($propertyId, 'sc-domain:') === 0) {
            return HomeUserSites::normalizeDomain(substr($propertyId, strlen('sc-domain:')));
        }

        return HomeUserSites::normalizeDomain($propertyId);
    }

    private function tokenHasWebmasterScope(int $userId, ?string $accessToken = null): bool
    {
        $accessToken = $accessToken !== null && $accessToken !== ''
            ? $accessToken
            : $this->validAccessToken($userId);
        if ($accessToken === null || $accessToken === '') {
            return false;
        }

        try {
            $client = new Client([
                'timeout' => (int) config('cabinet-google-search-console.timeout', 20),
                'http_errors' => false,
            ]);
            $response = $client->get('https://oauth2.googleapis.com/tokeninfo', [
                'query' => ['access_token' => $accessToken],
            ]);
            if ($response->getStatusCode() >= 400) {
                return false;
            }
            $body = json_decode((string) $response->getBody(), true);
            $scope = ' ' . (string) ($body['scope'] ?? '') . ' ';

            return strpos($scope, 'webmasters') !== false;
        } catch (Throwable $e) {
            Log::warning('gsc tokeninfo failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function resolveGoogleEmail(int $userId): ?string
    {
        if (!GoogleSearchConsoleUserToken::tableReady()) {
            return null;
        }

        /** @var GoogleSearchConsoleUserToken|null $row */
        $row = GoogleSearchConsoleUserToken::query()->find($userId);
        if (!$row) {
            return null;
        }
        if (trim((string) $row->google_email) !== '') {
            return (string) $row->google_email;
        }

        $accessToken = $this->validAccessToken($userId);
        if ($accessToken === null) {
            return null;
        }

        $client = new Client([
            'timeout' => (int) config('cabinet-google-search-console.timeout', 20),
            'http_errors' => false,
        ]);
        $response = $client->get((string) config('cabinet-google-search-console.userinfo_url'), [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept' => 'application/json',
            ],
        ]);
        if ($response->getStatusCode() >= 400) {
            return null;
        }
        $body = json_decode((string) $response->getBody(), true);
        $email = trim((string) ($body['email'] ?? ''));
        if ($email !== '') {
            $row->google_email = $email;
            $row->save();
        }

        return $email !== '' ? $email : null;
    }

    private function validAccessToken(int $userId): ?string
    {
        if (!GoogleSearchConsoleUserToken::tableReady()) {
            return null;
        }

        /** @var GoogleSearchConsoleUserToken|null $row */
        $row = GoogleSearchConsoleUserToken::query()->find($userId);
        if (!$row || (string) $row->access_token === '') {
            return null;
        }

        if ($row->isExpired() && (string) $row->refresh_token !== '') {
            try {
                $token = $this->requestToken([
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $row->refresh_token,
                    'client_id' => config('cabinet-google-search-console.client_id'),
                    'client_secret' => config('cabinet-google-search-console.client_secret'),
                ]);
                if (empty($token['refresh_token'])) {
                    $token['refresh_token'] = $row->refresh_token;
                }
                $this->storeToken($userId, $token);
                $row = GoogleSearchConsoleUserToken::query()->find($userId);
            } catch (Throwable $e) {
                Log::warning('gsc refresh failed', ['user_id' => $userId, 'error' => $e->getMessage()]);

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
            'timeout' => (int) config('cabinet-google-search-console.timeout', 20),
            'http_errors' => false,
        ]);
        $response = $client->post((string) config('cabinet-google-search-console.token_url'), [
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
        if (!GoogleSearchConsoleUserToken::tableReady()) {
            throw new \RuntimeException('google_search_console_user_tokens table missing');
        }

        $expiresIn = (int) ($token['expires_in'] ?? 0);
        $payload = [
            'access_token' => (string) $token['access_token'],
            'expires_at' => $expiresIn > 0 ? Carbon::now()->addSeconds($expiresIn - 60) : null,
        ];
        if (isset($token['refresh_token']) && (string) $token['refresh_token'] !== '') {
            $payload['refresh_token'] = (string) $token['refresh_token'];
        }

        GoogleSearchConsoleUserToken::query()->updateOrCreate(
            ['user_id' => $userId],
            $payload
        );
    }

    private function httpClient(): Client
    {
        return new Client([
            'base_uri' => rtrim((string) config('cabinet-google-search-console.api_base'), '/') . '/',
            'timeout' => (int) config('cabinet-google-search-console.timeout', 20),
        ]);
    }
}
