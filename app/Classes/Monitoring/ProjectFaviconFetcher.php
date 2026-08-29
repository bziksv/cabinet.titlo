<?php

namespace App\Classes\Monitoring;

use Elphin\IcoFileLoader\IcoFileService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Поиск лучшего изображения фавиконки (максимальное разрешение из нескольких источников).
 */
class ProjectFaviconFetcher
{
    public const OUTPUT_SIZE = 128;

    /** Сайты с антиботом чаще отдают HTML/иконки краулеру Яндекса. */
    public const DEFAULT_USER_AGENT = 'Mozilla/5.0 (compatible; YandexBot/3.0; +http://yandex.com/bots)';

    /** gstatic/Google часто не отдают иконку боту — второй проход. */
    public const BROWSER_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

    /** @var Client */
    private $http;

    /** @var Client */
    private $httpBrowser;

    /**
     * Без type-hint Client — иначе Laravel подставляет Guzzle с http_errors=true
     * и 404+PNG (gstatic) падает исключением вместо чтения тела.
     *
     * @param Client|null $http
     * @param Client|null $httpBrowser
     */
    public function __construct($http = null, $httpBrowser = null)
    {
        $userAgent = (string) env('MONITORING_FAVICON_USER_AGENT', self::DEFAULT_USER_AGENT);
        if ($userAgent === '') {
            $userAgent = self::DEFAULT_USER_AGENT;
        }

        $browserAgent = (string) env('MONITORING_FAVICON_BROWSER_USER_AGENT', self::BROWSER_USER_AGENT);
        if ($browserAgent === '') {
            $browserAgent = self::BROWSER_USER_AGENT;
        }

        $this->http = $http instanceof Client ? $http : $this->makeClient($userAgent);
        $this->httpBrowser = $httpBrowser instanceof Client ? $httpBrowser : $this->makeClient($browserAgent);
    }

    private function makeClient(string $userAgent): Client
    {
        return new Client([
            'timeout' => 10,
            'connect_timeout' => 4,
            'http_errors' => false,
            'allow_redirects' => ['max' => 5],
            'headers' => [
                'User-Agent' => $userAgent,
                'Accept' => 'text/html,application/xhtml+xml,image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
                'Accept-Language' => 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
            ],
        ]);
    }

    public function normalizeHost(string $raw): ?string
    {
        $host = strtolower(trim($raw));
        $host = preg_replace('#^https?://#i', '', $host);
        $host = explode('/', $host, 2)[0];
        $host = explode(':', $host, 2)[0];

        if ($host === '' || strlen($host) > 253) {
            return null;
        }

        if (!preg_match('/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]*[a-z0-9])?)+$/', $host)) {
            return null;
        }

        return $host;
    }

    /**
     * @return string|null PNG binary 128×128
     */
    public function fetchBestPng(string $host, bool $fast = false): ?string
    {
        $host = $this->normalizeHost($host);
        if ($host === null) {
            return null;
        }

        // Не возвращаем первый попавшийся: у Kia и др. в HTML сначала 16×16 (~650 B
        // после resize), а сервис отсекает всё < 1100 B — получается «пустой квадрат».
        $best = null;
        $bestLen = 0;
        foreach (($fast ? $this->collectFastCandidateUrls($host) : $this->collectCandidateUrls($host)) as $url) {
            $body = $this->fetchBody($url, $host);
            if ($body === null) {
                continue;
            }
            $png = $this->normalizeToPng($body);
            if ($png === null || !$this->isAcceptableFetchedPng($png)) {
                continue;
            }
            $len = strlen($png);
            if ($len > $bestLen) {
                $best = $png;
                $bestLen = $len;
            }
            // Достаточно плотная иконка — дальше агрегаторы не гоняем.
            if ($bestLen >= 2500) {
                break;
            }
        }

        return $best;
    }

    /**
     * Краулер (YandexBot) → браузер; для URL того же хоста — Referer.
     */
    private function fetchBody(string $url, string $host): ?string
    {
        $body = $this->fetch($url, $this->http, null);
        if ($body !== null) {
            return $body;
        }

        $referer = $this->urlBelongsToHost($url, $host) ? 'https://' . $host . '/' : null;

        return $this->fetch($url, $this->httpBrowser, $referer);
    }

    private function urlBelongsToHost(string $url, string $host): bool
    {
        $parts = parse_url($url);
        if (!isset($parts['host'])) {
            return false;
        }

        return strtolower($parts['host']) === $host
            || strtolower($parts['host']) === 'www.' . $host;
    }

    private function isAggregatorFaviconUrl(string $url): bool
    {
        return (bool) preg_match(
            '#(google\.com/s2/favicons|gstatic\.com/favicon|icon\.horse|duckduckgo\.com/ip3)#i',
            $url
        );
    }

    /** Отсечь буквенную плитку Google (~1.3 KB) и случайные баннеры с главной. */
    private function isAcceptableFetchedPng(string $png): bool
    {
        $bytes = strlen($png);
        if ($bytes < 500 || $bytes > 24_000) {
            return false;
        }

        return !($bytes >= 1290 && $bytes <= 1345);
    }

    /**
     * Быстрый режим для фоновой догрузки списка (без HTML и прямых запросов к сайту).
     *
     * @return string[]
     */
    private function collectFastCandidateUrls(string $host): array
    {
        $enc = rawurlencode($host);
        $pageUrl = 'https://' . $host . '/';

        return array_merge(
            [
                'https://' . $host . '/favicon.ico',
                'http://' . $host . '/favicon.ico',
            ],
            $this->aggregatorFaviconUrls($host, $pageUrl, $enc)
        );
    }

    /**
     * @return string[]
     */
    private function collectCandidateUrls(string $host): array
    {
        $enc = rawurlencode($host);
        $pageUrl = 'https://' . $host . '/';

        $siteUrls = array_merge(
            [
                'https://' . $host . '/favicon.ico',
                'http://' . $host . '/favicon.ico',
            ],
            $this->discoverIconUrlsFromHtml($host),
            [
                'https://' . $host . '/apple-touch-icon.png',
                'https://' . $host . '/apple-touch-icon-precomposed.png',
                'https://' . $host . '/apple-touch-icon-180x180.png',
                'https://' . $host . '/apple-touch-icon-152x152.png',
                'https://' . $host . '/favicon-512x512.png',
                'https://' . $host . '/favicon-192x192.png',
                'https://' . $host . '/favicon-180x180.png',
                'https://' . $host . '/favicon-96x96.png',
                'https://' . $host . '/favicon-32x32.png',
            ]
        );

        // Агрегаторы до icon.horse (часто отдаёт буквенный stub ~1.3 KB после normalize).
        return array_values(array_unique(array_merge(
            $siteUrls,
            $this->aggregatorFaviconUrls($host, $pageUrl, $enc)
        )));
    }

    /**
     * @return string[]
     */
    private function aggregatorFaviconUrls(string $host, string $pageUrl, string $enc): array
    {
        return [
            'https://t1.gstatic.com/faviconV2?client=SOCIAL&type=FAVICON&fallback_opts=TYPE,SIZE,URL&url='
                . rawurlencode($pageUrl) . '&size=128',
            'https://www.google.com/s2/favicons?domain=' . $enc . '&sz=128',
            'https://icons.duckduckgo.com/ip3/' . $host . '.ico',
            // icon.horse — последний: часто буква вместо реальной иконки
            'https://icon.horse/icon/' . $enc,
        ];
    }

    /**
     * @return string[]
     */
    private function discoverIconUrlsFromHtml(string $host): array
    {
        $found = [];
        foreach (['https://' . $host . '/', 'http://' . $host . '/'] as $pageUrl) {
            $html = $this->fetchHtml($pageUrl, $host);
            if ($html === null || strpos($html, '<') === false) {
                continue;
            }

            if (preg_match_all(
                '#<link[^>]+rel\s*=\s*["\']?(?:shortcut\s+)?(?:apple-touch-icon(?:-precomposed)?|icon)\b[^>]*>#i',
                $html,
                $tags
            )) {
                $sized = [];
                foreach ($tags[0] as $tag) {
                    $href = $this->extractLinkHref($tag);
                    if ($href === null) {
                        continue;
                    }
                    $resolved = $this->resolveUrl($pageUrl, $href);
                    if ($resolved === null) {
                        continue;
                    }
                    $px = 0;
                    if (preg_match('#\bsizes\s*=\s*["\']?(\d+)x(\d+)#i', $tag, $sm)) {
                        $px = max((int) $sm[1], (int) $sm[2]);
                    }
                    $sized[] = ['url' => $resolved, 'px' => $px];
                }
                usort($sized, static function ($a, $b) {
                    return $b['px'] <=> $a['px'];
                });
                foreach ($sized as $row) {
                    $found[] = $row['url'];
                }
            }

            foreach ($this->discoverFaviconIcoUrlsInHtml($html, $pageUrl) as $resolved) {
                $found[] = $resolved;
            }

            if (count($found) > 0) {
                break;
            }
        }

        return array_values(array_unique($found));
    }

    private function extractLinkHref(string $tag): ?string
    {
        if (preg_match('#href\s*=\s*["\']([^"\']+)#i', $tag, $m)) {
            return trim($m[1]);
        }
        if (preg_match('#href\s*=\s*([^"\'\s>]+)#i', $tag, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    /**
     * WordPress и др.: href=https://site/.../favicon.ico без кавычек.
     *
     * @return string[]
     */
    private function discoverFaviconIcoUrlsInHtml(string $html, string $pageUrl): array
    {
        $found = [];
        if (!preg_match_all(
            '#(?:href|src)\s*=\s*(["\']?)(https?://[^\s"\'>]+\.ico|/[^\s"\'>]+\.ico)\1#i',
            $html,
            $matches
        )) {
            return $found;
        }

        foreach ($matches[2] as $href) {
            $href = trim($href);
            if ($href === '' || stripos($href, 'favicon') === false) {
                continue;
            }
            $resolved = $this->resolveUrl($pageUrl, $href);
            if ($resolved !== null) {
                $found[] = $resolved;
            }
        }

        return $found;
    }

    private function resolveUrl(string $base, string $href): ?string
    {
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        if (strpos($href, '//') === 0) {
            return 'https:' . $href;
        }

        $parts = parse_url($base);
        if (!isset($parts['scheme'], $parts['host'])) {
            return null;
        }
        $origin = $parts['scheme'] . '://' . $parts['host'];

        if (strpos($href, '/') === 0) {
            return $origin . $href;
        }

        $path = isset($parts['path']) ? $parts['path'] : '/';
        $dir = rtrim(dirname($path), '/');

        return $origin . ($dir === '' || $dir === '.' ? '' : $dir) . '/' . $href;
    }

    private function fetchHtml(string $url, string $host): ?string
    {
        $referer = 'https://' . $host . '/';

        // Медленные WP-хосты: YandexBot часто таймаутит, браузер — нет.
        foreach ([[$this->httpBrowser, $referer], [$this->http, null]] as $pair) {
            $html = $this->fetchHtmlWithClient($url, $pair[0], $pair[1]);
            if ($html !== null) {
                return $html;
            }
        }

        return null;
    }

    private function fetchHtmlWithClient(string $url, Client $client, ?string $referer): ?string
    {
        try {
            $options = [];
            if ($referer !== null) {
                $options['headers'] = ['Referer' => $referer];
            }
            $response = $client->get($url, $options);
            if ($response->getStatusCode() !== 200) {
                return null;
            }
            $body = (string) $response->getBody();
            if ($body === '' || strlen($body) > 2_000_000 || stripos($body, '<html') === false) {
                return null;
            }

            return $body;
        } catch (GuzzleException $e) {
            return null;
        }
    }

    private function fetch(string $url, Client $client, ?string $referer): ?string
    {
        try {
            $options = [];
            if ($referer !== null) {
                $options['headers'] = ['Referer' => $referer];
            }
            $response = $client->get($url, $options);
            $status = $response->getStatusCode();
            if ($status >= 500) {
                return null;
            }
            $contentType = strtolower((string) $response->getHeaderLine('Content-Type'));
            $body = (string) $response->getBody();
            if ($body === '' || strlen($body) > 3_000_000) {
                return null;
            }
            if (strpos($contentType, 'text/html') !== false || (strpos(ltrim($body), '<') === 0 && stripos($body, '<html') !== false)) {
                return null;
            }
            if ($status !== 200 && !$this->looksLikeImageBinary($body)) {
                return null;
            }

            return $body;
        } catch (GuzzleException $e) {
            return null;
        }
    }

    private function looksLikeImageBinary(string $body): bool
    {
        if (strpos($body, "\x89PNG\r\n\x1a\n") === 0) {
            return true;
        }
        if (strncmp($body, "\x00\x00\x01\x00", 4) === 0) {
            return true;
        }

        return function_exists('imagecreatefromstring') && @imagecreatefromstring($body) !== false;
    }

    /**
     * PNG внутри .ico или конвертация через Imagick.
     */
    private function decodeImageBinary(string $binary): ?string
    {
        if (function_exists('imagecreatefromstring')) {
            $probe = @imagecreatefromstring($binary);
            if ($probe !== false) {
                imagedestroy($probe);

                return $binary;
            }
        }

        $pngPos = strpos($binary, "\x89PNG\r\n\x1a\n");
        if ($pngPos !== false) {
            return substr($binary, $pngPos);
        }

        if (strlen($binary) >= 6 && unpack('v', substr($binary, 2, 2))[1] === 1) {
            try {
                $loader = new IcoFileService();
                // extractIcon(128) часто false для 16×16 .ico (himopttorg и др.) — пробуем нативный размер.
                $icoSizes = [self::OUTPUT_SIZE, 48, 32, 24, 16];
                $icoSizes = array_values(array_unique($icoSizes));
                foreach ($icoSizes as $icoSize) {
                    try {
                        $im = $loader->extractIcon($binary, $icoSize, $icoSize);
                        if (!is_resource($im)) {
                            continue;
                        }
                        ob_start();
                        imagepng($im);
                        $png = ob_get_clean();
                        imagedestroy($im);
                        if (is_string($png) && $png !== '') {
                            return $png;
                        }
                    } catch (\Throwable $e) {
                        continue;
                    }
                }
            } catch (\Throwable $e) {
                // fall through
            }
        }

        if (class_exists(\Imagick::class)) {
            try {
                $im = new \Imagick();
                $im->readImageBlob($binary);
                $im->setImageFormat('png');

                return $im->getImageBlob();
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }

    private function normalizeToPng(string $binary): ?string
    {
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }

        $decoded = $this->decodeImageBinary($binary);
        if ($decoded === null) {
            return null;
        }

        $src = @imagecreatefromstring($decoded);
        if ($src === false) {
            return null;
        }

        $w = imagesx($src);
        $h = imagesy($src);
        if ($w < 1 || $h < 1) {
            imagedestroy($src);

            return null;
        }

        $size = self::OUTPUT_SIZE;
        $dest = imagecreatetruecolor($size, $size);
        if ($dest === false) {
            imagedestroy($src);

            return null;
        }

        imagealphablending($dest, false);
        imagesavealpha($dest, true);
        $transparent = imagecolorallocatealpha($dest, 255, 255, 255, 127);
        imagefilledrectangle($dest, 0, 0, $size, $size, $transparent);

        $scale = min($size / $w, $size / $h);
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $dx = (int) floor(($size - $nw) / 2);
        $dy = (int) floor(($size - $nh) / 2);

        imagealphablending($dest, true);
        imagecopyresampled($dest, $src, $dx, $dy, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);

        ob_start();
        imagepng($dest, null, 4);
        $png = ob_get_clean();
        imagedestroy($dest);

        return is_string($png) && strlen($png) > 200 ? $png : null;
    }
}
