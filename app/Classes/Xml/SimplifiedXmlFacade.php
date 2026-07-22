<?php

namespace App\Classes\Xml;

use App\Support\ClusterAnalysisDebugLog;
use App\Support\CompetitorAnalysisDebugLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SimplifiedXmlFacade extends XmlFacade
{
    protected int $count;

    protected int $attempt;

    protected string $url;

    /** @var callable|null */
    protected $progressCallback;

    protected ?string $debugPageHash = null;

    protected string $debugLogDriver = 'competitor';

    public function __construct($region, int $count = 100)
    {
        $this->count = $count;
        $this->lr = $region;
        $this->attempt = 0;
    }

    public function setCount($count)
    {
        $this->count = $count;
    }

    public function setAttempt($attempt = 0)
    {
        $this->attempt = $attempt;
    }

    public function setProgressCallback(?callable $callback): void
    {
        $this->progressCallback = $callback;
    }

    public function setDebugPageHash(?string $pageHash): void
    {
        $this->debugPageHash = $pageHash !== '' ? $pageHash : null;
    }

    public function setDebugLogDriver(string $driver): void
    {
        $this->debugLogDriver = $driver === 'cluster' ? 'cluster' : 'competitor';
    }

    public function getXMLResponse(string $searchEngine = 'yandex')
    {
        $docs = $this->getXMLDocuments($searchEngine);

        return array_values(array_map(static function (array $doc) {
            return Str::lower((string) ($doc['url'] ?? ''));
        }, $docs));
    }

    /**
     * Документы выдачи с title/snippet.
     * Google (xmlstock): doc.passages.passage; Яндекс (xmlstock): doc.headline;
     * XmlRiver: doc.passages / doc.extendedpassage.
     *
     * @return list<array{url: string, title: ?string, snippet: ?string}>
     */
    public function getXMLDocuments(string $searchEngine = 'yandex'): array
    {
        $providers = $this->providersForEngine($searchEngine);
        $providerIndex = 0;

        foreach ($providers as $provider) {
            $providerIndex++;
            $this->attempt = $providerIndex;
            $this->notifyProgress($providerIndex, count($providers));

            try {
                $result = $this->fetchProviderResponse($searchEngine, $provider);
                if ($result === null) {
                    $this->logDebug('warn', 'xml.empty_http', [
                        'engine' => $searchEngine,
                        'provider' => $provider,
                        'query' => $this->query,
                    ]);
                    continue;
                }

                if (isset($result['response']['error'])) {
                    $code = $this->extractApiErrorCode($result);
                    $message = $this->extractApiErrorMessage($result);
                    $this->logDebug('warn', 'xml.api_error', [
                        'engine' => $searchEngine,
                        'provider' => $provider,
                        'code' => $code,
                        'message' => $message,
                        'query' => $this->query,
                    ]);
                    Log::debug('XML API error response', [
                        'engine' => $searchEngine,
                        'provider' => $provider,
                        'code' => $code,
                        'message' => $message,
                    ]);

                    // 15 — нет результатов по запросу (валидная пустая выдача)
                    if ($code === 15) {
                        return [];
                    }

                    continue;
                }

                if (isset($result['response']['results']['grouping']['group'])) {
                    $docs = $this->parseDocuments($result['response']['results']['grouping']['group']);
                    $this->logDebug('info', 'xml.success', [
                        'engine' => $searchEngine,
                        'provider' => $provider,
                        'urls' => count($docs),
                        'query' => $this->query,
                    ]);

                    return $docs;
                }
            } catch (Throwable $e) {
                $this->logDebug('error', 'xml.exception', [
                    'engine' => $searchEngine,
                    'provider' => $provider,
                    'message' => $e->getMessage(),
                    'query' => $this->query,
                ]);
                Log::debug('XML Response error', [
                    'engine' => $searchEngine,
                    'provider' => $provider,
                    'message' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                ]);
            }
        }

        return [];
    }

    /**
     * Оценка числа найденных документов (Yandex/XML &lt;found priority="all"&gt;).
     * Достаточно depth=1 — нужны только found, не список docs.
     *
     * @return int|null null при ошибке/недоступности провайдера
     */
    public function getFoundCount(string $searchEngine = 'yandex'): ?int
    {
        $providers = $this->providersForEngine($searchEngine);
        $providerIndex = 0;

        foreach ($providers as $provider) {
            $providerIndex++;
            $this->attempt = $providerIndex;
            $this->notifyProgress($providerIndex, count($providers));

            try {
                $result = $this->fetchProviderResponse($searchEngine, $provider);
                if ($result === null) {
                    continue;
                }

                if (isset($result['response']['error'])) {
                    $code = $this->extractApiErrorCode($result);
                    // 15 — пустая выдача: found = 0
                    if ($code === 15) {
                        return 0;
                    }
                    continue;
                }

                $found = $this->extractFoundCount($result);
                if ($found !== null) {
                    $this->logDebug('info', 'xml.found_count', [
                        'engine' => $searchEngine,
                        'provider' => $provider,
                        'found' => $found,
                        'query' => $this->query,
                    ]);

                    return $found;
                }

                // нет found, но есть docs — fallback на число групп
                if (isset($result['response']['results']['grouping']['group'])) {
                    $docs = $this->parseDocuments($result['response']['results']['grouping']['group']);

                    return count($docs);
                }
            } catch (Throwable $e) {
                $this->logDebug('error', 'xml.found_exception', [
                    'engine' => $searchEngine,
                    'provider' => $provider,
                    'message' => $e->getMessage(),
                    'query' => $this->query,
                ]);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $result
     */
    protected function extractFoundCount(array $result): ?int
    {
        $candidates = [];
        if (isset($result['response']['found'])) {
            $candidates[] = $result['response']['found'];
        }
        if (isset($result['response']['results']['found'])) {
            $candidates[] = $result['response']['results']['found'];
        }

        foreach ($candidates as $node) {
            $parsed = $this->parseFoundNode($node);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        return null;
    }

    /**
     * @param mixed $node
     */
    protected function parseFoundNode($node): ?int
    {
        if ($node === null || $node === '') {
            return null;
        }
        if (is_numeric($node)) {
            return max(0, (int) $node);
        }
        if (is_string($node) && preg_match('/^\d+$/', trim($node))) {
            return (int) trim($node);
        }
        if (! is_array($node)) {
            return null;
        }

        // Один found с @attributes.priority
        if (isset($node['@attributes']) || array_key_exists(0, $node) || array_key_exists('#text', $node)) {
            $priority = isset($node['@attributes']['priority'])
                ? Str::lower((string) $node['@attributes']['priority'])
                : '';
            $value = $this->foundNodeNumericValue($node);
            if ($value !== null && ($priority === 'all' || $priority === '')) {
                return $value;
            }
            if ($value !== null && $priority !== 'all') {
                // запомним phrase как запасной — ниже
            }
        }

        $all = null;
        $any = null;
        $list = isset($node[0]) || (isset($node['@attributes']) === false && $this->isListArray($node))
            ? $node
            : [$node];

        foreach ($list as $item) {
            if (! is_array($item) && is_numeric($item)) {
                $any = max(0, (int) $item);
                continue;
            }
            if (! is_array($item)) {
                $flat = $this->foundNodeNumericValue($item);
                if ($flat !== null) {
                    $any = $flat;
                }
                continue;
            }
            $priority = isset($item['@attributes']['priority'])
                ? Str::lower((string) $item['@attributes']['priority'])
                : '';
            $value = $this->foundNodeNumericValue($item);
            if ($value === null) {
                continue;
            }
            if ($priority === 'all') {
                $all = $value;
            } elseif ($any === null) {
                $any = $value;
            }
        }

        return $all !== null ? $all : $any;
    }

    /**
     * @param mixed $node
     */
    protected function foundNodeNumericValue($node): ?int
    {
        if (is_numeric($node)) {
            return max(0, (int) $node);
        }
        if (is_string($node)) {
            $t = trim($node);
            if ($t !== '' && preg_match('/^\d+$/', $t)) {
                return (int) $t;
            }

            return null;
        }
        if (! is_array($node)) {
            return null;
        }
        foreach (['#text', '0', '_'] as $key) {
            if (isset($node[$key]) && is_numeric($node[$key])) {
                return max(0, (int) $node[$key]);
            }
            if (isset($node[$key]) && is_string($node[$key]) && preg_match('/^\d+$/', trim($node[$key]))) {
                return (int) trim($node[$key]);
            }
        }
        // иногда значение лежит как единственный числовой leaf без ключа
        foreach ($node as $key => $val) {
            if ($key === '@attributes') {
                continue;
            }
            if (is_numeric($val)) {
                return max(0, (int) $val);
            }
            if (is_string($val) && preg_match('/^\d+$/', trim($val))) {
                return (int) trim($val);
            }
        }

        return null;
    }

    /**
     * @param array<mixed> $arr
     */
    protected function isListArray(array $arr): bool
    {
        if ($arr === []) {
            return false;
        }
        $i = 0;
        foreach ($arr as $k => $_) {
            if ($k !== $i) {
                return false;
            }
            $i++;
        }

        return true;
    }

    /**
     * @param mixed $xmlResult
     * @return list<array{url: string, title: ?string, snippet: ?string}>
     */
    protected function parseDocuments($xmlResult): array
    {
        if (! is_array($xmlResult) || $xmlResult === []) {
            return [];
        }

        if (isset($xmlResult['doc'])) {
            $xmlResult = [$xmlResult];
        }

        $docs = [];
        foreach ($xmlResult as $item) {
            if (! is_array($item) || empty($item['doc']) || ! is_array($item['doc'])) {
                continue;
            }
            $doc = $item['doc'];
            $url = isset($doc['url']) ? (string) $doc['url'] : '';
            if ($url === '') {
                continue;
            }

            $title = $this->flattenXmlText($doc['title'] ?? null);
            $snippet = $this->extractSnippet($doc);

            $docs[] = [
                'url' => $url,
                'title' => $title !== '' ? $title : null,
                'snippet' => $snippet !== '' ? $snippet : null,
            ];
        }

        return $docs;
    }

    /**
     * Сниппет из разных форматов XML-провайдеров.
     *
     * @param array<string, mixed> $doc
     */
    protected function extractSnippet(array $doc): string
    {
        $passages = $doc['passages'] ?? null;
        if (is_array($passages) && array_key_exists('passage', $passages)) {
            $fromPassages = $this->flattenXmlText($passages['passage']);
            if ($fromPassages !== '') {
                return $fromPassages;
            }
        }

        // Яндекс через XmlStock: текст сниппета в headline, passages нет
        foreach (['headline', 'extendedpassage'] as $key) {
            if (! array_key_exists($key, $doc)) {
                continue;
            }
            $flat = $this->flattenXmlText($doc[$key]);
            if ($flat !== '') {
                return $flat;
            }
        }

        $extended = $doc['extendedpassages'] ?? null;
        if (is_array($extended) && array_key_exists('passage', $extended)) {
            $items = $extended['passage'];
            if (! is_array($items)) {
                return $this->flattenXmlText($items);
            }
            // Один passage или список: берём текстовые value / плоский текст
            if (isset($items['value']) || isset($items['type'])) {
                $items = [$items];
            }
            $parts = [];
            foreach ($items as $item) {
                if (! is_array($item)) {
                    $flat = $this->flattenXmlText($item);
                    if ($flat !== '') {
                        $parts[] = $flat;
                    }
                    continue;
                }
                $type = isset($item['type']) ? Str::lower((string) $item['type']) : '';
                if ($type !== '' && ! in_array($type, ['text', 'description', 'snippet'], true)) {
                    continue;
                }
                $flat = $this->flattenXmlText($item['value'] ?? $item);
                if ($flat !== '') {
                    $parts[] = $flat;
                }
            }

            return trim(implode(' ', $parts));
        }

        return '';
    }

    /**
     * @param mixed $value
     */
    protected function flattenXmlText($value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_string($value) || is_numeric($value)) {
            return trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        if (! is_array($value)) {
            return '';
        }

        $parts = [];
        foreach ($value as $key => $item) {
            if ($key === '@attributes') {
                continue;
            }
            $flat = $this->flattenXmlText($item);
            if ($flat !== '') {
                $parts[] = $flat;
            }
        }

        return trim(implode(' ', $parts));
    }

    /**
     * @return array<int, string>
     */
    protected function providersForEngine(string $searchEngine): array
    {
        if ($searchEngine === 'google') {
            $providers = ['xmlstock', 'xmlriver'];
        } else {
            $providers = ['xmlstock', 'xmlproxy', 'xmlriver'];
        }

        if (in_array('xmlriver', $providers, true) && $this->getRiverLocation() === null) {
            $providers = array_values(array_diff($providers, ['xmlriver']));
        }

        return $providers;
    }

    protected function notifyProgress(int $providerIndex, int $providerTotal): void
    {
        if ($this->progressCallback !== null) {
            ($this->progressCallback)($providerIndex, $providerTotal);
        }
    }

    protected function fetchProviderResponse(string $searchEngine, string $provider): ?array
    {
        if ($provider === 'xmlstock' && $this->xmlstockHybridRetryEnabled()) {
            return $this->fetchXmlstockHybrid($searchEngine);
        }

        return $this->sendRequest($searchEngine, $provider);
    }

    protected function xmlstockHybridRetryEnabled(): bool
    {
        return (bool) config('cabinet-competitor-analysis.xmlstock_hybrid_retry', true);
    }

    /**
     * XMLStock: гибридный режим — коды 210/202 требуют повтор того же URL через 20–30 с.
     */
    protected function fetchXmlstockHybrid(string $searchEngine): ?array
    {
        $url = $this->buildRequestUrl($searchEngine, 'xmlstock');
        if ($url === null || $url === '') {
            return null;
        }

        $maxAttempts = (int) config('cabinet-competitor-analysis.xmlstock_hybrid_max_attempts', 8);
        $sleepSec = (int) config('cabinet-competitor-analysis.xmlstock_hybrid_sleep_sec', 22);
        $retryCodes = config('cabinet-competitor-analysis.xmlstock_hybrid_retry_codes', [202, 210]);
        if (! is_array($retryCodes)) {
            $retryCodes = [202, 210];
        }
        $retryCodes = array_map('intval', $retryCodes);

        $lastResult = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $this->notifyProgress($attempt, $maxAttempts);
            $lastResult = $this->httpGetParsed($url, true);
            $code = $this->extractApiErrorCode($lastResult);

            if ($lastResult !== null && ! isset($lastResult['response']['error'])) {
                $this->logDebug('info', 'xmlstock.hybrid.ready', [
                    'attempt' => $attempt,
                    'query' => $this->query,
                ]);

                return $lastResult;
            }

            if ($lastResult !== null && isset($lastResult['response']['results']['grouping']['group'])) {
                return $lastResult;
            }

            $message = $this->extractApiErrorMessage($lastResult);
            $this->logDebug('info', 'xmlstock.hybrid.attempt', [
                'attempt' => $attempt,
                'max' => $maxAttempts,
                'code' => $code,
                'message' => $message,
                'query' => $this->query,
            ]);

            if ($code === 15) {
                return $lastResult;
            }

            if ($code !== null && ! in_array($code, $retryCodes, true)) {
                return $lastResult;
            }

            if ($attempt < $maxAttempts && $sleepSec > 0) {
                sleep($sleepSec);
            }
        }

        return $lastResult;
    }

    protected function sendRequest(string $searchEngine, string $provider): ?array
    {
        $this->url = $this->buildRequestUrl($searchEngine, $provider);

        if ($this->url === null || $this->url === '') {
            return null;
        }

        return $this->httpGetParsed($this->url, false);
    }

    protected function httpGetParsed(string $url, bool $xmlstockHybrid): ?array
    {
        $timeout = (int) config('cabinet-competitor-analysis.xml_http_timeout', 12);
        if ($xmlstockHybrid) {
            $timeout = max($timeout, (int) config('cabinet-competitor-analysis.xmlstock_hybrid_http_timeout', 30));
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => $timeout,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false || $response === '') {
            return null;
        }

        $xml = $this->load($response);
        $parsed = json_decode(json_encode($xml), true);
        if (! is_array($parsed)) {
            return null;
        }

        return $this->enrichParsedXml($xml, $parsed);
    }

    /**
     * simplexml → json теряет атрибут code у <error code="…">.
     *
     * @param \SimpleXMLElement $xml
     */
    protected function enrichParsedXml($xml, array $parsed): array
    {
        if (! isset($xml->response->error)) {
            return $parsed;
        }

        $errorNode = $xml->response->error;
        $attrs = $errorNode->attributes();
        if ($attrs === null || ! isset($attrs['code'])) {
            return $parsed;
        }

        $parsed['response']['error'] = [
            '@attributes' => ['code' => (string) $attrs['code']],
            0 => trim((string) $errorNode),
        ];

        return $parsed;
    }

    protected function encodeQueryForUrl(): string
    {
        return rawurlencode($this->query);
    }

    /**
     * XMLStock: простой groupby=10…100 или продвинутый attr=d.mode=deep…
     */
    protected function groupbyForXmlstock(): string
    {
        $allowed = [10, 20, 30, 40, 50, 60, 70, 80, 90, 100];
        if (in_array($this->count, $allowed, true)) {
            return (string) $this->count;
        }

        return 'attr=d.mode=deep.groups-on-page=' . $this->count . '.docs-in-group=1';
    }

    protected function extractApiErrorCode(?array $result): ?int
    {
        if ($result === null || ! isset($result['response']['error'])) {
            return null;
        }

        $error = $result['response']['error'];

        if (is_array($error)) {
            if (isset($error['@attributes']['code'])) {
                return (int) $error['@attributes']['code'];
            }
            if (isset($error['code'])) {
                return (int) $error['code'];
            }
        }

        if (is_numeric($error)) {
            return (int) $error;
        }

        return null;
    }

    protected function extractApiErrorMessage(?array $result): string
    {
        if ($result === null || ! isset($result['response']['error'])) {
            return '';
        }

        $error = $result['response']['error'];

        if (is_array($error)) {
            return (string) ($error[0] ?? $error['@value'] ?? json_encode($error, JSON_UNESCAPED_UNICODE));
        }

        return (string) $error;
    }

    protected function buildRequestUrl(string $searchEngine, string $provider): ?string
    {
        $query = $this->encodeQueryForUrl();

        if ($searchEngine === 'google') {
            if ($provider === 'xmlstock') {
                $this->setPath('https://xmlstock.com/google/xml/');
                $this->setUser(config('xmlstock.user'));
                $this->setKey(config('xmlstock.key'));

                // Google отдаёт ≤10 URL на страницу; глубже — через &page=0,1,2…
                return "$this->path?user=$this->user&key=$this->key&query=$query&groupby=$this->count&lr=$this->lr&sortby=$this->sortby&page=$this->page";
            }

            if ($provider === 'xmlriver') {
                $loc = $this->getRiverLocation();
                if ($loc === null) {
                    return null;
                }
                $this->setPath('https://xmlriver.com/search/xml');
                $this->setUser(config('xmlriver.user'));
                $this->setKey(config('xmlriver.key'));

                return "$this->path?user=$this->user&key=$this->key&query=$query&groupby=$this->count&loc=$loc";
            }

            return null;
        }

        if ($provider === 'xmlstock') {
            $this->setPath('https://xmlstock.com/yandex/xml/');
            $this->setUser(config('xmlstock.user'));
            $this->setKey(config('xmlstock.key'));
            $groupby = rawurlencode($this->groupbyForXmlstock());

            return "$this->path?user=$this->user&key=$this->key&query=$query&groupby=$groupby&lr=$this->lr&sortby=$this->sortby&page=$this->page";
        }

        if ($provider === 'xmlproxy') {
            $this->setPath('https://xmlproxy.ru/search/xml');
            $this->setUser(config('xmlproxy.user'));
            $this->setKey(config('xmlproxy.key'));

            return "$this->path?user=$this->user&key=$this->key&query=$query&groupby=attr=d.mode%3Ddeep.groups-on-page%3D"
                . "$this->count.docs-in-group%3D1&lr=$this->lr&sortby=$this->sortby&page=$this->page";
        }

        if ($provider === 'xmlriver') {
            $loc = $this->getRiverLocation();
            if ($loc === null) {
                return null;
            }
            $this->setPath('https://xmlriver.com/yandex/xml');
            $this->setUser(config('xmlriver.user'));
            $this->setKey(config('xmlriver.key'));

            return "$this->path?user=$this->user&key=$this->key&query=$query&groupby=attr=d.mode%3Ddeep.groups-on-page%3D"
                . "$this->count.docs-in-group%3D1&loc=$loc";
        }

        return null;
    }

    protected function logDebug(string $level, string $message, array $context = []): void
    {
        if ($this->debugPageHash === null) {
            return;
        }

        if ($this->debugLogDriver === 'cluster') {
            ClusterAnalysisDebugLog::append($this->debugPageHash, $level, $message, $context);

            return;
        }

        CompetitorAnalysisDebugLog::append($this->debugPageHash, $level, $message, $context);
    }

    protected function parseResult($xmlResult): array
    {
        $result = [];
        if (isset($xmlResult['doc']['url'])) {
            return [$xmlResult['doc']['url']];
        }

        foreach ($xmlResult as $item) {
            $result[] = Str::lower($item['doc']['url']);
        }

        return $result;
    }

    public static function getPosition($request)
    {
        $xml = new SimplifiedXmlFacade($request['region']);
        $xml->setQuery($request['phrase']);
        $xmlResponse = $xml->getXMLResponse();

        $position = array_search(Str::lower($request['link']), $xmlResponse);
        if ($position === false) {
            $position = array_search(Str::lower($request['link'] . '/'), $xmlResponse);
        }

        return $position;
    }

    protected function getRiverLocation(): ?string
    {
        $array = [
            '213' => '1015930',
            '1' => '20949',
            '20' => '1011859',
            '37' => '1011862',
            '197' => '1011853',
            '4' => '1011868',
            '77' => '1011858',
            '191' => '1011869',
            '24' => '1011977',
            '75' => '1012008',
            '33' => '1012037',
            '192' => '1012073',
            '38' => '1012068',
            '21' => '1012075',
            '193' => '1012077',
            '1106' => '9040919',
            '54' => '1012052',
            '5' => '1011898',
            '63' => '1011896',
            '41' => '1011949',
            '43' => '1012054',
            '22' => '1011914',
            '64' => '1011909',
            '7' => '1011934',
            '35' => '1011905',
            '62' => '1011941',
            '53' => '1011916',
            '8' => '20943',
            '9' => '1011947',
            '28' => '1011892',
            '23' => '1011976',
            '1092' => '9051420',
            '30' => '1011901',
            '47' => '1011981',
            '65' => '1011984',
            '66' => '1011985',
            '10' => '1014494',
            '48' => '1011987',
            '49' => '1011996',
            '50' => '1011993',
            '25' => '1012010',
            '39' => '1012013',
            '11' => '1012017',
            '51' => '1012029',
            '42' => '1011950',
            '2' => '1012040',
            '12' => '1012038',
            '239' => '1011907',
            '36' => '1012043',
            '10649' => '9040930',
            '973' => '1011924',
            '13' => '1011924',
            '14' => '1012061',
            '67' => '1012059',
            '15' => '1012060',
            '195' => '1012067',
            '172' => '1011867',
            '76' => '1011918',
            '45' => '9040951',
            '56' => '1011874',
            '1104' => '1011902',
            '16' => '1012084',
        ];

        return $array[$this->lr] ?? null;
    }
}
