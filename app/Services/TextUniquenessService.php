<?php

namespace App\Services;

use App\Classes\Xml\SimplifiedXmlFacade;
use App\Support\Esenin\EseninHtmlHighlighter;
use App\TextAnalyzer;

/**
 * Уникальность текста: шинглы + SERP-probe или сравнение с URL.
 */
class TextUniquenessService
{
    /**
     * @param array{mode?: string, text?: string, urls?: array<int, string>|string, engine?: string, yandex_lr?: string} $params
     */
    public static function estimateCost(array $params): int
    {
        $mode = ($params['mode'] ?? 'internet') === 'urls' ? 'urls' : 'internet';
        $text = self::normalizePlain((string) ($params['text'] ?? ''));

        if ($mode === 'urls') {
            $urls = self::normalizeUrlList($params['urls'] ?? []);

            return count($urls);
        }

        return self::probeCountForText($text);
    }

    /**
     * @param array{
     *   mode?: string,
     *   text: string,
     *   urls?: array<int, string>|string,
     *   engine?: string,
     *   yandex_lr?: string,
     *   exclude_hosts?: array<int, string>|string,
     *   force_compare_urls?: array<int, string>|string,
     *   source_html?: string
     * } $params
     * @return array<string, mixed>
     */
    public function analyze(array $params): array
    {
        $mode = ($params['mode'] ?? 'internet') === 'urls' ? 'urls' : 'internet';
        $rawText = (string) ($params['text'] ?? '');
        $sourceHtml = trim((string) ($params['source_html'] ?? ''));
        if ($sourceHtml === '' && EseninHtmlHighlighter::isHtml($rawText)) {
            $sourceHtml = $rawText;
        }
        $text = self::normalizePlain($rawText !== '' ? $rawText : $sourceHtml);
        $minChars = (int) config('cabinet-text-uniqueness.min_chars', 200);
        $maxChars = (int) config('cabinet-text-uniqueness.max_chars', 50000);
        $excludeHosts = self::normalizeExcludeHosts($params['exclude_hosts'] ?? []);
        $forceCompareUrls = self::normalizeUrlList($params['force_compare_urls'] ?? []);

        if (mb_strlen($text) < $minChars) {
            throw new \InvalidArgumentException(__('Text uniqueness text too short', ['min' => $minChars]));
        }
        if (mb_strlen($text) > $maxChars) {
            $text = mb_substr($text, 0, $maxChars);
        }

        $size = max(2, (int) config('cabinet-text-uniqueness.shingle_size', 4));
        $sourceShingles = $this->buildShingles($text, $size);
        if ($sourceShingles === []) {
            throw new \InvalidArgumentException(__('Text uniqueness no shingles'));
        }

        $engine = ($params['engine'] ?? config('cabinet-text-uniqueness.default_engine', 'yandex')) === 'google'
            ? 'google'
            : 'yandex';
        $lr = (string) ($params['yandex_lr'] ?? config('cabinet-text-uniqueness.default_yandex_lr', '213'));

        // Хосты принудительной сверки не берём из SERP как «чужие» источники
        foreach ($forceCompareUrls as $forceUrl) {
            $h = $this->hostFromUrl($forceUrl);
            if ($h !== '' && ! in_array($h, $excludeHosts, true)) {
                $excludeHosts[] = $h;
            }
        }

        if ($mode === 'urls') {
            $urls = array_values(array_filter(
                self::normalizeUrlList($params['urls'] ?? []),
                function ($url) use ($excludeHosts) {
                    return ! $this->hostIsExcluded($this->hostFromUrl($url), $excludeHosts);
                }
            ));
            $urls = array_values(array_unique(array_merge($forceCompareUrls, $urls)));

            return $this->analyzeAgainstUrls($text, $sourceShingles, $urls, $excludeHosts, $forceCompareUrls, $sourceHtml);
        }

        return $this->analyzeAgainstInternet(
            $text,
            $sourceShingles,
            $engine,
            $lr,
            $excludeHosts,
            $forceCompareUrls,
            $sourceHtml
        );
    }

    /**
     * @param array<string, true> $sourceShingles
     * @param array<int, string> $excludeHosts
     * @return array<string, mixed>
     */
    private function analyzeAgainstInternet(
        string $text,
        array $sourceShingles,
        string $engine,
        string $lr,
        array $excludeHosts = [],
        array $forceCompareUrls = [],
        string $sourceHtml = ''
    ): array {
        $probes = $this->selectProbes($text, self::probeCountForText($text));
        $pauseMs = max(0, (int) config('cabinet-text-uniqueness.request_pause_ms', 80));
        $depth = max(1, (int) config('cabinet-text-uniqueness.serp_depth', 8));
        $fetchMax = max(1, (int) config('cabinet-text-uniqueness.fetch_url_max', 14));
        $rareMax = max(1, (int) config('cabinet-text-uniqueness.rare_probe_urls_max', 3));

        $probeRows = [];
        /** @var array<string, float> $urlScores */
        $urlScores = [];
        $priorityUrls = [];
        $xmlRequests = 0;
        $skippedOwn = 0;

        foreach ($probes as $probe) {
            $urls = $this->searchFragment($engine, $lr, $probe, $depth, $xmlRequests);
            $found = count($urls);
            $probeRows[] = [
                'query' => $probe,
                'urls_found' => $found,
                'urls' => array_slice($urls, 0, 5),
            ];
            // Редкий зонд (1–3 URL) — сильный сигнал копии; частый ТОП — шум
            $rarity = $found > 0 ? (1.0 / $found) : 0.0;
            foreach ($urls as $pos => $url) {
                $host = $this->hostFromUrl($url);
                if ($host === '') {
                    continue;
                }
                if ($this->hostIsExcluded($host, $excludeHosts)) {
                    $skippedOwn++;
                    continue;
                }
                $posBoost = max(0.2, 1.0 - ($pos * 0.08));
                $urlScores[$url] = ($urlScores[$url] ?? 0.0) + ($rarity * 10.0 * $posBoost);
                if ($found > 0 && $found <= $rareMax) {
                    $priorityUrls[$url] = true;
                }
            }
            if ($pauseMs > 0) {
                usleep($pauseMs * 1000);
            }
        }

        arsort($urlScores);
        $candidateUrls = [];
        $seenCand = [];
        foreach (array_keys($priorityUrls) as $url) {
            $candidateUrls[] = $url;
            $seenCand[$url] = true;
            if (count($candidateUrls) >= $fetchMax) {
                break;
            }
        }
        if (count($candidateUrls) < $fetchMax) {
            foreach (array_keys($urlScores) as $url) {
                if (isset($seenCand[$url])) {
                    continue;
                }
                $candidateUrls[] = $url;
                $seenCand[$url] = true;
                if (count($candidateUrls) >= $fetchMax) {
                    break;
                }
            }
        }

        // Сначала своя страница — прямое сравнение шинглов (не зависит от ТОПа выдачи)
        $allUrls = array_values(array_unique(array_merge($forceCompareUrls, $candidateUrls)));

        return $this->finishReport(
            'internet',
            $text,
            $sourceShingles,
            $allUrls,
            [
                'probes' => $probeRows,
                'xml_requests' => $xmlRequests,
                'cost' => max(1, $xmlRequests > 0 ? $xmlRequests : count($probes)),
                'engine' => $engine,
                'region' => $lr,
                'exclude_hosts' => $excludeHosts,
                'skipped_own_hits' => $skippedOwn,
                'force_compare_urls' => $forceCompareUrls,
                'source_html' => $sourceHtml,
            ]
        );
    }

    /**
     * @param array<string, true> $sourceShingles
     * @param array<int, string> $urls
     * @param array<int, string> $excludeHosts
     * @param array<int, string> $forceCompareUrls
     * @return array<string, mixed>
     */
    private function analyzeAgainstUrls(
        string $text,
        array $sourceShingles,
        array $urls,
        array $excludeHosts = [],
        array $forceCompareUrls = [],
        string $sourceHtml = ''
    ): array {
        if ($urls === []) {
            throw new \InvalidArgumentException(__('Text uniqueness urls required'));
        }

        return $this->finishReport(
            'urls',
            $text,
            $sourceShingles,
            $urls,
            [
                'probes' => [],
                'xml_requests' => 0,
                'cost' => max(1, count($urls)),
                'engine' => null,
                'region' => null,
                'exclude_hosts' => $excludeHosts,
                'skipped_own_hits' => 0,
                'force_compare_urls' => $forceCompareUrls,
                'source_html' => $sourceHtml,
            ]
        );
    }

    /**
     * @param array<string, true> $sourceShingles
     * @param array<int, string> $urls
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function finishReport(string $mode, string $text, array $sourceShingles, array $urls, array $meta): array
    {
        $forceSet = [];
        foreach ($meta['force_compare_urls'] ?? [] as $fu) {
            $forceSet[mb_strtolower(rtrim((string) $fu, '/'))] = true;
            $forceSet[mb_strtolower((string) $fu)] = true;
        }

        $unionMatchedWeb = [];
        $unionMatchedOwn = [];
        $sources = [];
        $fetched = 0;
        $ownMatchPct = 0.0;
        $ownUrl = null;
        $minWebPct = max(0.0, (float) config('cabinet-text-uniqueness.min_web_overlap_pct', 3));
        $minWebShingles = max(1, (int) config('cabinet-text-uniqueness.min_web_matched_shingles', 2));
        $noiseDropped = 0;
        $weakSources = [];

        foreach ($urls as $url) {
            $pageText = $this->fetchPageText($url);
            $fetched++;
            $urlKey = mb_strtolower(rtrim($url, '/'));
            $isOwn = isset($forceSet[$urlKey]) || isset($forceSet[mb_strtolower($url)]);

            if ($pageText === '') {
                if ($isOwn) {
                    $sources[] = [
                        'url' => $url,
                        'overlap_pct' => 0,
                        'matched_shingles' => 0,
                        'error' => true,
                        'is_own' => true,
                    ];
                }
                continue;
            }

            $size = max(2, (int) config('cabinet-text-uniqueness.shingle_size', 4));
            $pageShingles = $this->buildShingles($pageText, $size);
            $matched = array_intersect_key($sourceShingles, $pageShingles);
            $matchedCount = count($matched);
            $overlap = count($sourceShingles) > 0
                ? round(($matchedCount / count($sourceShingles)) * 100, 1)
                : 0.0;

            if ($isOwn) {
                foreach ($matched as $k => $_) {
                    $unionMatchedOwn[$k] = true;
                }
                if ($overlap >= $ownMatchPct) {
                    $ownMatchPct = $overlap;
                    $ownUrl = $url;
                }
                $sources[] = [
                    'url' => $url,
                    'overlap_pct' => $overlap,
                    'matched_shingles' => $matchedCount,
                    'error' => false,
                    'is_own' => true,
                    'samples' => array_slice(array_keys($matched), 0, 8),
                ];
                continue;
            }

            $row = [
                'url' => $url,
                'overlap_pct' => $overlap,
                'matched_shingles' => $matchedCount,
                'error' => false,
                'is_own' => false,
                'is_weak' => false,
                'samples' => array_slice(array_keys($matched), 0, 8),
            ];

            // Слабые совпадения — в список «шум», в % уникальности не входят
            if ($overlap < $minWebPct || $matchedCount < $minWebShingles) {
                if ($matchedCount > 0) {
                    $row['is_weak'] = true;
                    $weakSources[] = $row;
                } else {
                    $noiseDropped++;
                }
                continue;
            }

            foreach ($matched as $k => $_) {
                $unionMatchedWeb[$k] = true;
            }
            $sources[] = $row;
        }

        usort($sources, static function ($a, $b) {
            if (! empty($a['is_own']) !== ! empty($b['is_own'])) {
                return ! empty($a['is_own']) ? -1 : 1;
            }

            return ($b['overlap_pct'] ?? 0) <=> ($a['overlap_pct'] ?? 0);
        });

        usort($weakSources, static function ($a, $b) {
            return ($b['overlap_pct'] ?? 0) <=> ($a['overlap_pct'] ?? 0);
        });

        $total = count($sourceShingles);
        $webMatched = count($unionMatchedWeb);
        $matchedPct = $total > 0 ? round(($webMatched / $total) * 100, 1) : 0.0;
        $uniquenessPct = max(0, round(100 - $matchedPct, 1));
        $webSourcesCount = 0;
        foreach ($sources as $s) {
            if (empty($s['is_own']) && empty($s['error'])) {
                $webSourcesCount++;
            }
        }
        $noSignificant = $webMatched === 0 && $ownMatchPct <= 0;
        $xmlRequests = (int) ($meta['xml_requests'] ?? 0);
        $shingleToUrl = $this->mapShingleToSourceUrl($sources);
        $sourceHtml = (string) ($meta['source_html'] ?? '');
        $highlightedHtml = $this->renderHighlightedText(
            $text,
            $unionMatchedWeb,
            (int) config('cabinet-text-uniqueness.shingle_size', 4),
            $shingleToUrl,
            $sourceHtml
        );

        return [
            'mode' => $mode,
            // Уникальность относительно значимых чужих источников
            'uniqueness_pct' => $uniquenessPct,
            'matched_pct' => $matchedPct,
            'own_match_pct' => $ownMatchPct,
            'own_url' => $ownUrl,
            'shingles_total' => $total,
            'shingles_matched' => $webMatched,
            'matched_samples' => array_slice(array_keys($unionMatchedWeb), 0, 40),
            'sources' => $sources,
            'weak_sources' => array_slice($weakSources, 0, 8),
            'probes' => $meta['probes'] ?? [],
            'xml_requests' => $xmlRequests,
            'pages_fetched' => $fetched,
            'cost' => (int) ($meta['cost'] ?? 1),
            'engine' => $meta['engine'] ?? null,
            'region' => $meta['region'] ?? null,
            'chars' => mb_strlen($text),
            'shingle_size' => (int) config('cabinet-text-uniqueness.shingle_size', 4),
            'exclude_hosts' => $meta['exclude_hosts'] ?? [],
            'skipped_own_hits' => (int) ($meta['skipped_own_hits'] ?? 0),
            'force_compare_urls' => $meta['force_compare_urls'] ?? [],
            'noise_dropped' => $noiseDropped,
            'web_sources_count' => $webSourcesCount,
            // true = значимых копий по зондам нет (uniqueness_pct ≈ 100; предупреждение в UI)
            'no_significant_matches' => $noSignificant,
            'verdict' => $noSignificant ? 'no_significant_matches' : ($matchedPct >= 30 ? 'low_unique' : 'ok'),
            'text' => $text,
            'source_html' => $sourceHtml,
            'highlighted_html' => $highlightedHtml,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $sources
     * @return array<string, string>
     */
    private function mapShingleToSourceUrl(array $sources): array
    {
        $map = [];
        foreach ($sources as $src) {
            if (! empty($src['is_own']) || ! empty($src['error']) || empty($src['url'])) {
                continue;
            }
            foreach ($src['samples'] ?? [] as $sample) {
                $key = (string) $sample;
                if ($key === '' || isset($map[$key])) {
                    continue;
                }
                $map[$key] = (string) $src['url'];
            }
        }

        return $map;
    }

    /**
     * @param array<string, true> $matchedShingles
     * @param array<string, string> $shingleToUrl
     */
    private function renderHighlightedText(
        string $text,
        array $matchedShingles,
        int $size,
        array $shingleToUrl = [],
        string $sourceHtml = ''
    ): string {
        if ($text === '') {
            return $sourceHtml !== '' ? $sourceHtml : '';
        }
        if ($matchedShingles === [] || $size < 2) {
            if ($sourceHtml !== '' && EseninHtmlHighlighter::isHtml($sourceHtml)) {
                return $sourceHtml;
            }

            return self::plainToParagraphHtml($text);
        }

        $tokens = $this->tokenizeWithOffsets($text);
        if (count($tokens) < $size) {
            if ($sourceHtml !== '' && EseninHtmlHighlighter::isHtml($sourceHtml)) {
                return $sourceHtml;
            }

            return self::plainToParagraphHtml($text);
        }

        $ranges = [];
        $last = count($tokens) - $size;
        for ($i = 0; $i <= $last; $i++) {
            $chunk = [];
            for ($j = 0; $j < $size; $j++) {
                $chunk[] = $tokens[$i + $j]['word'];
            }
            $shingle = implode(' ', $chunk);
            if (! isset($matchedShingles[$shingle])) {
                continue;
            }
            $start = $tokens[$i]['start'];
            $end = $tokens[$i + $size - 1]['end'];
            $url = $shingleToUrl[$shingle] ?? '';
            $ranges[] = ['start' => $start, 'end' => $end, 'url' => $url];
        }

        if ($ranges === []) {
            if ($sourceHtml !== '' && EseninHtmlHighlighter::isHtml($sourceHtml)) {
                return $sourceHtml;
            }

            return self::plainToParagraphHtml($text);
        }

        usort($ranges, static function ($a, $b) {
            if ($a['start'] === $b['start']) {
                return $b['end'] <=> $a['end'];
            }

            return $a['start'] <=> $b['start'];
        });

        $merged = [];
        foreach ($ranges as $range) {
            if ($merged === []) {
                $merged[] = $range;
                continue;
            }
            $prev = &$merged[count($merged) - 1];
            if ($range['start'] <= $prev['end'] + 1) {
                $prev['end'] = max($prev['end'], $range['end']);
                if ($prev['url'] === '' && $range['url'] !== '') {
                    $prev['url'] = $range['url'];
                }
            } else {
                $merged[] = $range;
            }
            unset($prev);
        }

        if ($sourceHtml !== '' && EseninHtmlHighlighter::isHtml($sourceHtml)) {
            $marks = [];
            foreach ($merged as $range) {
                $start = (int) $range['start'];
                $end = (int) $range['end'];
                $url = (string) ($range['url'] ?? '');
                $hint = $url !== ''
                    ? (string) __('Text analyzer uniqueness mark tip', ['url' => $url])
                    : (string) __('Text analyzer uniqueness mark tip plain');
                $marks[] = [
                    'offset' => $start,
                    'length' => max(0, $end - $start),
                    'block' => 'uniqueness',
                    'hint' => $hint,
                    'url' => $url,
                ];
            }

            return EseninHtmlHighlighter::apply($sourceHtml, $text, $marks, 'uniqueness');
        }

        $html = '';
        $cursor = 0;
        $textLen = mb_strlen($text, 'UTF-8');
        foreach ($merged as $range) {
            $start = max(0, min($textLen, (int) $range['start']));
            $end = max($start, min($textLen, (int) $range['end']));
            if ($start > $cursor) {
                $html .= htmlspecialchars(mb_substr($text, $cursor, $start - $cursor, 'UTF-8'), ENT_QUOTES, 'UTF-8');
            }
            $fragment = htmlspecialchars(mb_substr($text, $start, $end - $start, 'UTF-8'), ENT_QUOTES, 'UTF-8');
            $tip = $range['url'] !== ''
                ? __('Text analyzer uniqueness mark tip', ['url' => $range['url']])
                : __('Text analyzer uniqueness mark tip plain');
            $tipAttr = htmlspecialchars((string) $tip, ENT_QUOTES, 'UTF-8');
            $urlAttr = $range['url'] !== ''
                ? ' data-uniq-url="' . htmlspecialchars($range['url'], ENT_QUOTES, 'UTF-8') . '"'
                : '';
            $html .= '<mark class="cabinet-ta-uniq-mark esenin-mark esenin-mark--uniqueness" data-esenin-tip="'
                . $tipAttr . '"' . $urlAttr . '>'
                . $fragment
                . '<span class="esenin-mark__icon" aria-hidden="true">!</span></mark>';
            $cursor = $end;
        }
        if ($cursor < $textLen) {
            $html .= htmlspecialchars(mb_substr($text, $cursor, null, 'UTF-8'), ENT_QUOTES, 'UTF-8');
        }

        return self::markedPlainToParagraphHtml($html);
    }

    /**
     * Текст без разметки → абзацы.
     */
    public static function plainToParagraphHtml(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        $parts = preg_split("/\n+/u", $text) ?: [];
        $html = '';
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $html .= '<p>' . htmlspecialchars($part, ENT_QUOTES, 'UTF-8') . '</p>';
        }

        return $html !== '' ? $html : '<p>' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</p>';
    }

    /**
     * Уже экранированный текст с <mark> и переводами строк → <p>.
     */
    private static function markedPlainToParagraphHtml(string $html): string
    {
        $parts = preg_split("/\n+/u", $html) ?: [];
        $out = '';
        foreach ($parts as $part) {
            if (trim(strip_tags($part)) === '') {
                continue;
            }
            $out .= '<p>' . $part . '</p>';
        }

        return $out !== '' ? $out : '<p>' . $html . '</p>';
    }

    /**
     * @return array<int, array{word: string, start: int, end: int}>
     */
    private function tokenizeWithOffsets(string $text): array
    {
        $words = [];
        $len = mb_strlen($text, 'UTF-8');
        $i = 0;
        while ($i < $len) {
            $ch = mb_substr($text, $i, 1, 'UTF-8');
            if (preg_match('/[a-zа-яё0-9]/ui', $ch)) {
                $start = $i;
                $buf = '';
                while ($i < $len) {
                    $ch = mb_substr($text, $i, 1, 'UTF-8');
                    if (! preg_match('/[a-zа-яё0-9]/ui', $ch)) {
                        break;
                    }
                    $buf .= $ch;
                    $i++;
                }
                $w = mb_strtolower($buf, 'UTF-8');
                if (mb_strlen($w, 'UTF-8') >= 2) {
                    $words[] = [
                        'word' => $w,
                        'start' => $start,
                        'end' => $i,
                    ];
                }
            } else {
                $i++;
            }
        }

        return $words;
    }

    /**
     * @param array<int, string>|string $raw
     * @return array<int, string>
     */
    public static function normalizeExcludeHosts($raw): array
    {
        if (is_string($raw)) {
            $raw = preg_split('/[\s,;]+/u', $raw) ?: [];
        }
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        $seen = [];
        foreach ($raw as $item) {
            $host = self::normalizeHost((string) $item);
            if ($host === '' || isset($seen[$host])) {
                continue;
            }
            $seen[$host] = true;
            $out[] = $host;
        }

        return $out;
    }

    public static function normalizeHost(string $value): string
    {
        $value = trim(mb_strtolower($value));
        if ($value === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $value) || strpos($value, '/') !== false) {
            $host = parse_url(
                preg_match('#^https?://#i', $value) ? $value : ('https://' . ltrim($value, '/')),
                PHP_URL_HOST
            );
            $value = is_string($host) ? $host : $value;
        }
        $value = preg_replace('/^www\./i', '', $value) ?? $value;

        return trim($value, ". \t\n\r");
    }

    /**
     * @param array<int, string> $excludeHosts
     */
    private function hostIsExcluded(string $host, array $excludeHosts): bool
    {
        if ($host === '' || $excludeHosts === []) {
            return false;
        }
        foreach ($excludeHosts as $ex) {
            if ($ex === '') {
                continue;
            }
            if ($host === $ex || substr($host, -strlen('.' . $ex)) === '.' . $ex) {
                return true;
            }
        }

        return false;
    }

    public static function normalizePlain(string $text): string
    {
        return TextAnalyzer::normalizePlainForUniqueness($text);
    }

    /**
     * @param array<int, string>|string $raw
     * @return array<int, string>
     */
    public static function normalizeUrlList($raw): array
    {
        if (is_string($raw)) {
            $raw = preg_split('/\r\n|\r|\n/u', $raw) ?: [];
        }
        if (! is_array($raw)) {
            return [];
        }

        $max = max(1, (int) config('cabinet-text-uniqueness.compare_url_max', 10));
        $out = [];
        $seen = [];
        foreach ($raw as $line) {
            $url = trim((string) $line);
            if ($url === '') {
                continue;
            }
            if (! preg_match('#^https?://#i', $url)) {
                $url = 'https://' . $url;
            }
            $key = mb_strtolower($url);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $url;
            if (count($out) >= $max) {
                break;
            }
        }

        return $out;
    }

    public static function probeCountForText(string $text): int
    {
        $min = max(1, (int) config('cabinet-text-uniqueness.probe_min', 5));
        $max = max($min, (int) config('cabinet-text-uniqueness.probe_max', 12));
        $per = max(200, (int) config('cabinet-text-uniqueness.chars_per_probe', 700));
        $len = mb_strlen($text);
        $n = (int) ceil($len / $per);

        return max($min, min($max, $n));
    }

    /**
     * @return array<string, true> keyed by shingle string
     */
    private function buildShingles(string $text, int $size): array
    {
        $words = $this->tokenize($text);
        if (count($words) < $size) {
            return [];
        }

        $out = [];
        $last = count($words) - $size;
        for ($i = 0; $i <= $last; $i++) {
            $chunk = array_slice($words, $i, $size);
            $shingle = implode(' ', $chunk);
            $out[$shingle] = true;
        }

        return $out;
    }

    /**
     * @return array<int, string>
     */
    private function tokenize(string $text): array
    {
        $text = mb_strtolower($text, 'UTF-8');
        $parts = preg_split('/[^a-zа-яё0-9]+/ui', $text) ?: [];
        $words = [];
        foreach ($parts as $w) {
            $w = trim((string) $w);
            if ($w === '' || mb_strlen($w) < 2) {
                continue;
            }
            $words[] = $w;
        }

        return $words;
    }

    /**
     * @return array<int, string>
     */
    private function selectProbes(string $text, int $count): array
    {
        $size = max(4, (int) config('cabinet-text-uniqueness.probe_words', 6));
        $words = $this->tokenize($text);
        if (count($words) < $size) {
            return [mb_substr($text, 0, 120)];
        }

        $stop = [
            'и' => true, 'в' => true, 'во' => true, 'не' => true, 'на' => true, 'с' => true,
            'со' => true, 'а' => true, 'но' => true, 'как' => true, 'к' => true, 'по' => true,
            'из' => true, 'у' => true, 'за' => true, 'от' => true, 'для' => true, 'о' => true,
            'об' => true, 'это' => true, 'то' => true, 'что' => true, 'или' => true, 'же' => true,
            'бы' => true, 'ли' => true, 'при' => true, 'до' => true, 'ни' => true, 'его' => true,
            'ее' => true, 'их' => true, 'мы' => true, 'вы' => true, 'он' => true, 'она' => true,
            'они' => true, 'ты' => true, 'я' => true, 'быть' => true, 'есть' => true, 'уже' => true,
            'еще' => true, 'ещё' => true, 'все' => true, 'всё' => true, 'так' => true, 'там' => true,
            'тут' => true, 'если' => true, 'также' => true, 'только' => true, 'можно' => true,
            'нужно' => true, 'будет' => true, 'были' => true, 'был' => true, 'была' => true,
        ];

        $last = count($words) - $size;
        $segments = max(1, $count);
        $segWidth = max(1, (int) ceil(($last + 1) / $segments));
        $picked = [];
        $seen = [];

        for ($seg = 0; $seg < $segments; $seg++) {
            $from = $seg * $segWidth;
            $to = min($last, $from + $segWidth - 1);
            if ($from > $last) {
                break;
            }
            $best = null;
            $bestScore = -1;
            for ($i = $from; $i <= $to; $i++) {
                $chunk = array_slice($words, $i, $size);
                $phrase = implode(' ', $chunk);
                if (isset($seen[$phrase])) {
                    continue;
                }
                $score = 0;
                $contentWords = 0;
                foreach ($chunk as $w) {
                    $len = mb_strlen($w);
                    if (isset($stop[$w]) || $len < 3) {
                        $score -= 2;
                        continue;
                    }
                    $contentWords++;
                    $score += min(12, $len);
                    if ($len >= 8) {
                        $score += 4;
                    }
                    if (preg_match('/\d/u', $w)) {
                        $score += 3;
                    }
                }
                if ($contentWords < 3) {
                    continue;
                }
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $phrase;
                }
            }
            if ($best !== null) {
                $seen[$best] = true;
                $picked[] = $best;
            }
        }

        // Добираем глобально лучшие, если сегменты дали мало
        if (count($picked) < $count) {
            $candidates = [];
            for ($i = 0; $i <= $last; $i++) {
                $chunk = array_slice($words, $i, $size);
                $phrase = implode(' ', $chunk);
                if (isset($seen[$phrase])) {
                    continue;
                }
                $score = 0;
                foreach ($chunk as $w) {
                    if (isset($stop[$w])) {
                        continue;
                    }
                    $score += mb_strlen($w);
                }
                $candidates[] = ['phrase' => $phrase, 'score' => $score];
            }
            usort($candidates, static function ($a, $b) {
                return $b['score'] <=> $a['score'];
            });
            foreach ($candidates as $cand) {
                $seen[$cand['phrase']] = true;
                $picked[] = $cand['phrase'];
                if (count($picked) >= $count) {
                    break;
                }
            }
        }

        if ($picked === []) {
            $picked[] = implode(' ', array_slice($words, 0, $size));
        }

        return array_slice($picked, 0, $count);
    }

    /**
     * @return array<int, string>
     */
    private function searchFragment(string $engine, string $lr, string $query, int $depth, int &$xmlRequests): array
    {
        $quoted = '"' . str_replace('"', '', $query) . '"';
        try {
            $xmlRequests++;
            $xml = new SimplifiedXmlFacade($lr, $depth);
            $xml->setQuery($quoted);
            if ($engine === 'google') {
                $xml->setPage('0');
                $chunk = $xml->getXMLResponse('google');
            } else {
                $chunk = $xml->getXMLResponse('yandex');
            }

            $urls = is_array($chunk) ? array_values($chunk) : [];

            return array_slice($urls, 0, $depth);
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    private function fetchPageText(string $url): string
    {
        try {
            $html = TextAnalyzer::curlInit($url);
            if (! $html) {
                $html = TextAnalyzer::curlInitV2($url);
            }
            if (! $html) {
                return '';
            }
            $html = TextAnalyzer::removeStylesAndScripts($html);

            return self::normalizePlain(TextAnalyzer::deleteEverythingExceptCharacters($html));
        } catch (\Throwable $e) {
            report($e);

            return '';
        }
    }

    private function hostFromUrl(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return '';
        }

        return mb_strtolower(preg_replace('/^www\./i', '', $host) ?? $host);
    }
}
