<?php

namespace App\Services\SiteAudit;

/**
 * Лёгкие метрики тошноты / переспама для Site Audit (без полного TextAnalyzer).
 */
class SiteAuditTextMetrics
{
    /** @var array<string,bool>|null */
    private static $stopMap = null;

    /** @var string[] */
    private static $stopList = [
        'и', 'в', 'во', 'не', 'на', 'я', 'с', 'со', 'как', 'а', 'то', 'все', 'она', 'так', 'его',
        'но', 'да', 'ты', 'к', 'у', 'же', 'вы', 'за', 'бы', 'по', 'только', 'её', 'мне', 'было',
        'вот', 'от', 'меня', 'ещё', 'нет', 'о', 'из', 'ему', 'теперь', 'когда', 'даже', 'ну',
        'вдруг', 'ли', 'если', 'уже', 'или', 'ни', 'быть', 'был', 'него', 'до', 'вас', 'нибудь',
        'опять', 'уж', 'вам', 'ведь', 'там', 'потом', 'себя', 'чего', 'эта', 'этот', 'этой',
        'этом', 'эти', 'что', 'это', 'для', 'при', 'без', 'под', 'над', 'про', 'через', 'также',
        'the', 'a', 'an', 'and', 'or', 'of', 'to', 'in', 'on', 'for', 'is', 'are', 'was', 'with',
        'by', 'from', 'as', 'at', 'be', 'this', 'that', 'it', 'we', 'you', 'they', 'not',
        // хвосты HTML-entity после неполного decode (&amp;amp; → токен «amp»)
        'amp', 'nbsp', 'quot', 'apos', 'lt', 'gt', 'ndash', 'mdash', 'hellip', 'laquo', 'raquo',
    ];

    /**
     * Декод HTML-текста для метрик: Bitrix часто пишет &amp;amp; — один проход
     * оставляет &amp;, а токенизатор режет это в слово «amp» → ложный trigram spam.
     */
    public static function decodeHtmlText(string $text): string
    {
        for ($i = 0; $i < 4; $i++) {
            $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded === $text) {
                break;
            }
            $text = $decoded;
        }
        // незакрытые / остаточные entity: &amp &nbsp; &#38;
        $text = preg_replace('/&(?:#\d{1,7}|#x[\da-f]{1,6}|[a-z][\w:-]{0,31});?/iu', ' ', $text) ?? $text;

        return $text;
    }

    /**
     * @return array{
     *   tokens: int,
     *   nausea_classic: float,
     *   nausea_academic: float,
     *   top_word: ?string,
     *   top_word_count: int,
     *   top_bigram: ?string,
     *   top_bigram_count: int,
     *   top_trigram: ?string,
     *   top_trigram_count: int,
     *   top_tokens: list<string>,
     *   spam: bool,
     *   spam_word: ?string,
     *   spam_count: int
     * }
     */
    public static function analyze(string $text, int $minLen = 3): array
    {
        $tokens = self::tokens($text, $minLen);
        $n = count($tokens);
        $empty = [
            'tokens' => 0,
            'nausea_classic' => 0.0,
            'nausea_academic' => 0.0,
            'top_word' => null,
            'top_word_count' => 0,
            'top_bigram' => null,
            'top_bigram_count' => 0,
            'top_trigram' => null,
            'top_trigram_count' => 0,
            'top_tokens' => [],
            'spam' => false,
            'spam_word' => null,
            'spam_count' => 0,
        ];
        if ($n < 1) {
            return $empty;
        }

        $counts = array_count_values($tokens);
        arsort($counts);
        $topWord = (string) array_key_first($counts);
        $topCount = (int) $counts[$topWord];
        $topTokens = array_slice(array_keys($counts), 0, 40);

        $sumSq = 0;
        foreach ($counts as $c) {
            $sumSq += $c * $c;
        }

        $classic = round(($topCount / $n) * 100, 2);
        $academic = round((sqrt($sumSq) / $n) * 100, 2);

        $bigramCount = 0;
        $topBigram = null;
        if ($n >= 2) {
            $bigrams = [];
            for ($i = 1; $i < $n; $i++) {
                $bg = $tokens[$i - 1] . ' ' . $tokens[$i];
                $bigrams[$bg] = ($bigrams[$bg] ?? 0) + 1;
            }
            arsort($bigrams);
            $topBigram = (string) array_key_first($bigrams);
            $bigramCount = (int) $bigrams[$topBigram];
        }

        $trigramCount = 0;
        $topTrigram = null;
        if ($n >= 3) {
            $trigrams = [];
            for ($i = 2; $i < $n; $i++) {
                $tg = $tokens[$i - 2] . ' ' . $tokens[$i - 1] . ' ' . $tokens[$i];
                $trigrams[$tg] = ($trigrams[$tg] ?? 0) + 1;
            }
            arsort($trigrams);
            $topTrigram = (string) array_key_first($trigrams);
            $trigramCount = (int) $trigrams[$topTrigram];
        }

        $spam = false;
        $spamWord = null;
        $spamCount = 0;
        if ($topCount >= 3) {
            $spam = true;
            $spamWord = $topWord;
            $spamCount = $topCount;
        } elseif ($topCount >= 2 && $n <= 5) {
            $spam = true;
            $spamWord = $topWord;
            $spamCount = $topCount;
        }

        return [
            'tokens' => $n,
            'nausea_classic' => $classic,
            'nausea_academic' => $academic,
            'top_word' => $topWord,
            'top_word_count' => $topCount,
            'top_bigram' => $topBigram,
            'top_bigram_count' => $bigramCount,
            'top_trigram' => $topTrigram,
            'top_trigram_count' => $trigramCount,
            'top_tokens' => $topTokens,
            'spam' => $spam,
            'spam_word' => $spamWord,
            'spam_count' => $spamCount,
        ];
    }

    /**
     * Топ значимых слов текста (без стоп-слов), по убыванию частоты.
     *
     * @return list<string>
     */
    public static function topTokenList(string $text, int $limit = 40, int $minLen = 3): array
    {
        $m = self::analyze($text, $minLen);
        $list = isset($m['top_tokens']) && is_array($m['top_tokens']) ? $m['top_tokens'] : [];

        return array_values(array_slice($list, 0, max(1, $limit)));
    }

    /**
     * Пересечение токенов: порядок как у первой страницы (частые раньше).
     *
     * @param  list<string>  $a
     * @param  list<string>  $b
     * @return list<string>
     */
    public static function sharedTokenList(array $a, array $b, int $limit = 24): array
    {
        if ($a === [] || $b === []) {
            return [];
        }
        $setB = [];
        foreach ($b as $w) {
            $w = mb_strtolower(trim((string) $w));
            if ($w !== '') {
                $setB[$w] = true;
            }
        }
        $out = [];
        $seen = [];
        foreach ($a as $w) {
            $w = mb_strtolower(trim((string) $w));
            if ($w === '' || isset($seen[$w]) || ! isset($setB[$w])) {
                continue;
            }
            $seen[$w] = true;
            $out[] = $w;
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * Слабый набор токенов из title/h1/description/top_* (когда нет token_top_json).
     *
     * @param  object|array  $page
     * @return list<string>
     */
    public static function weakTokenBagFromPage($page): array
    {
        $get = static function ($page, string $key) {
            if (is_array($page)) {
                return $page[$key] ?? null;
            }

            return $page->{$key} ?? null;
        };
        $chunks = [
            (string) ($get($page, 'title') ?? ''),
            (string) ($get($page, 'h1') ?? ''),
            (string) ($get($page, 'description') ?? ''),
            (string) ($get($page, 'top_word') ?? ''),
            (string) ($get($page, 'top_bigram') ?? ''),
            (string) ($get($page, 'top_trigram') ?? ''),
        ];

        return self::topTokenList(implode(' ', $chunks), 30, 2);
    }

    /**
     * Переспам короткого поля (title / description / h1).
     *
     * @return array{spam: bool, word: ?string, count: int, tokens: int}
     */
    public static function fieldSpam(?string $field): array
    {
        $field = trim((string) $field);
        if ($field === '') {
            return ['spam' => false, 'word' => null, 'count' => 0, 'tokens' => 0];
        }
        $m = self::analyze($field, 2);

        return [
            'spam' => $m['spam'],
            'word' => $m['spam_word'],
            'count' => $m['spam_count'],
            'tokens' => $m['tokens'],
        ];
    }

    /**
     * @return string[]
     */
    public static function tokens(string $text, int $minLen = 3): array
    {
        $text = self::decodeHtmlText($text);
        $text = mb_strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text);
        $parts = preg_split('/\s+/u', trim((string) $text), -1, PREG_SPLIT_NO_EMPTY);
        if (! is_array($parts)) {
            return [];
        }

        $out = [];
        foreach ($parts as $w) {
            if (mb_strlen($w) < $minLen) {
                continue;
            }
            if (self::isStop($w)) {
                continue;
            }
            $out[] = $w;
        }

        return $out;
    }

    private static function isStop(string $w): bool
    {
        if (self::$stopMap === null) {
            self::$stopMap = array_fill_keys(self::$stopList, true);
        }

        return isset(self::$stopMap[$w]);
    }

    /**
     * N-грамма только из хвостов HTML-entity (amp amp amp) — не контентный спам.
     */
    public static function isEntityJunkNgram(?string $ngram): bool
    {
        $ngram = mb_strtolower(trim((string) $ngram));
        if ($ngram === '') {
            return true;
        }
        $junk = [
            'amp' => true, 'nbsp' => true, 'quot' => true, 'apos' => true,
            'lt' => true, 'gt' => true, 'ndash' => true, 'mdash' => true,
            'hellip' => true, 'laquo' => true, 'raquo' => true,
        ];
        $parts = preg_split('/\s+/u', $ngram, -1, PREG_SPLIT_NO_EMPTY);
        if (! is_array($parts) || $parts === []) {
            return true;
        }
        foreach ($parts as $p) {
            if (! isset($junk[$p])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Текст внутри Яндекс-&lt;noindex&gt; / HTML-комментариев noindex.
     */
    public static function noindexText(string $html): string
    {
        return (string) (self::noindexInfo($html)['text'] ?? '');
    }

    /**
     * Разбор блоков noindex: текст, ссылки, отпечаток для группировки.
     *
     * @return array{text:string,len:int,sample:string,links:array<int,array{href:string,text:string}>,hash:string}
     */
    public static function noindexInfo(string $html): array
    {
        $chunks = [];
        if (preg_match_all('/<noindex\b[^>]*>(.*?)<\/noindex>/is', $html, $m)) {
            foreach ($m[1] as $chunk) {
                $chunks[] = $chunk;
            }
        }
        if (preg_match_all('/<!--\s*noindex\s*-->(.*?)<!--\s*\/noindex\s*-->/is', $html, $m2)) {
            foreach ($m2[1] as $chunk) {
                $chunks[] = $chunk;
            }
        }

        if ($chunks === []) {
            return [
                'text' => '',
                'len' => 0,
                'sample' => '',
                'links' => [],
                'hash' => '',
            ];
        }

        $joined = implode(' ', $chunks);
        $links = [];
        $seenHref = [];
        if (preg_match_all('/<a\b[^>]*\bhref\s*=\s*["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $joined, $am)) {
            foreach ($am[1] as $i => $href) {
                $href = trim(html_entity_decode((string) $href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($href === '' || isset($seenHref[$href])) {
                    continue;
                }
                $seenHref[$href] = true;
                $label = trim(preg_replace(
                    '/\s+/u',
                    ' ',
                    html_entity_decode(strip_tags((string) ($am[2][$i] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8')
                ));
                $links[] = [
                    'href' => $href,
                    'text' => $label !== '' ? $label : $href,
                ];
                if (count($links) >= 12) {
                    break;
                }
            }
        }

        $text = strip_tags($joined);
        $text = self::decodeHtmlText($text);
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?: '');
        $sample = $text;
        if ($sample === '' && $links !== []) {
            $bits = [];
            foreach (array_slice($links, 0, 4) as $l) {
                $bits[] = (string) $l['text'];
            }
            $sample = implode(' · ', $bits);
        }
        if (mb_strlen($sample) > 120) {
            $sample = rtrim(mb_substr($sample, 0, 119)) . '…';
        }

        $linkKey = [];
        foreach ($links as $l) {
            $linkKey[] = mb_strtolower((string) $l['href']);
        }
        sort($linkKey);
        $hash = md5(mb_strtolower($text) . '|' . implode('|', $linkKey));

        return [
            'text' => $text,
            'len' => mb_strlen($text),
            'sample' => $sample,
            'links' => $links,
            'hash' => $hash,
        ];
    }
}
