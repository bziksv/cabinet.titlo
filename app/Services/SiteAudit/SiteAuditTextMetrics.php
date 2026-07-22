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
    ];

    /**
     * @return array{
     *   tokens: int,
     *   nausea_classic: float,
     *   nausea_academic: float,
     *   top_word: ?string,
     *   top_word_count: int,
     *   top_bigram: ?string,
     *   top_bigram_count: int,
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
            'spam' => $spam,
            'spam_word' => $spamWord,
            'spam_count' => $spamCount,
        ];
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
     * Текст внутри Яндекс-&lt;noindex&gt; / HTML-комментариев noindex.
     */
    public static function noindexText(string $html): string
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
            return '';
        }
        $text = strip_tags(implode(' ', $chunks));
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text));
    }
}
