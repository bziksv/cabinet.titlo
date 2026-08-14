<?php

namespace App\Support;

use App\Morphy;
use App\Relevance;

final class RelevancePhraseNgrams
{
    private const MIN_N = 2;

    private const MAX_N = 4;

    private const MIN_TOKEN_LENGTH = 2;

    private const MIN_SITE_COVERAGE = 2;

    /** Ограничение кандидатов TLP — иначе полный анализ «зависает» на 80%. */
    private const MAX_CANDIDATE_PHRASES = 2500;

    /** @var array<string, string> */
    private static $lemmaMap = [];

    /** @var array<string, string> */
    private static $lemmaCache = [];

    /** @var array<string, list<string>> */
    private static $textLemmaTokensCache = [];

    /** @var Morphy|null */
    private static $morphy;

    /** @var array<string, true> */
    private static $unigramRoots = [];

    /** @var array<string, true> */
    private static $noiseLemmas = [];

    /**
     * @param array<string, array<string, mixed>>|null $unigramTable
     */
    public static function configureLemmaContext(?array $unigramTable): void
    {
        self::$lemmaMap = $unigramTable ? self::buildLemmaMapFromUnigram($unigramTable) : [];
        self::$unigramRoots = $unigramTable ? self::buildUnigramRootSet($unigramTable) : [];
        self::$noiseLemmas = self::buildNoiseLemmaSet();
        self::$lemmaCache = [];
        self::$textLemmaTokensCache = [];
    }

    public static function resetLemmaContext(): void
    {
        self::$lemmaMap = [];
        self::$unigramRoots = [];
        self::$noiseLemmas = [];
        self::$lemmaCache = [];
        self::$textLemmaTokensCache = [];
    }

    /**
     * @param array<string, array<string, mixed>> $unigramTable
     * @return array<string, true>
     */
    public static function buildUnigramRootSet(array $unigramTable): array
    {
        $roots = [];

        foreach ($unigramTable as $root => $wordForm) {
            if (!is_array($wordForm)) {
                continue;
            }

            $rootKey = mb_strtolower(trim((string) $root), 'UTF-8');
            if ($rootKey !== '') {
                $roots[$rootKey] = true;
            }
        }

        return $roots;
    }

    /**
     * @param array<string, array<string, mixed>> $unigramTable
     * @return array<string, string>
     */
    public static function buildLemmaMapFromUnigram(array $unigramTable): array
    {
        $map = [];

        foreach ($unigramTable as $root => $wordForm) {
            if (!is_array($wordForm)) {
                continue;
            }

            $rootKey = mb_strtolower(trim((string) $root), 'UTF-8');
            if ($rootKey === '') {
                continue;
            }

            $map[$rootKey] = $rootKey;

            foreach ($wordForm as $word => $unused) {
                if ($word === 'total') {
                    continue;
                }

                $form = mb_strtolower(trim((string) $word), 'UTF-8');
                if ($form !== '') {
                    $map[$form] = $rootKey;
                }
            }
        }

        return $map;
    }

    /**
     * N-граммы (2–4 слова) по каждому сайту конкурентов, с лемматизацией токенов.
     *
     * @param array<string, array<string, mixed>> $sites
     * @return list<string>
     */
    public static function candidatePhrases(array $sites): array
    {
        $aggregated = [];

        foreach ($sites as $page) {
            if (!empty($page['ignored']) || !empty($page['mainPage'])) {
                continue;
            }

            $text = Relevance::concatenation([
                $page['html'] ?? '',
                $page['hiddenText'] ?? '',
            ]);
            $tokens = self::tokenizeLemmas($text);
            if (count($tokens) < self::MIN_N) {
                continue;
            }

            foreach (array_count_values(self::extractNgrams($tokens)) as $phrase => $count) {
                if ($count <= 0) {
                    continue;
                }
                if (!isset($aggregated[$phrase])) {
                    $aggregated[$phrase] = 0;
                }
                $aggregated[$phrase]++;
            }
        }

        $phrases = [];
        foreach ($aggregated as $phrase => $siteCoverage) {
            if ($siteCoverage >= self::MIN_SITE_COVERAGE) {
                $phrases[] = $phrase;
            }
        }

        if (count($phrases) > self::MAX_CANDIDATE_PHRASES) {
            arsort($aggregated);
            $phrases = [];
            foreach ($aggregated as $phrase => $siteCoverage) {
                if ($siteCoverage < self::MIN_SITE_COVERAGE) {
                    continue;
                }
                $phrases[] = $phrase;
                if (count($phrases) >= self::MAX_CANDIDATE_PHRASES) {
                    break;
                }
            }
        }

        return $phrases;
    }

    public static function countLemmaPhraseOccurrences(string $lemmaPhrase, string $text): int
    {
        $phraseTokens = preg_split('/\s+/u', trim($lemmaPhrase), -1, PREG_SPLIT_NO_EMPTY);
        $n = is_array($phraseTokens) ? count($phraseTokens) : 0;
        if ($n < self::MIN_N) {
            return 0;
        }

        $lemmas = self::lemmaTokensForText($text);
        $tokenCount = count($lemmas);
        if ($tokenCount < $n) {
            return 0;
        }

        $count = 0;
        for ($i = 0; $i <= $tokenCount - $n; $i++) {
            $slice = array_slice($lemmas, $i, $n);
            if ($slice === $phraseTokens) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return list<string>
     */
    private static function lemmaTokensForText(string $text): array
    {
        if ($text === '') {
            return [];
        }

        $cacheKey = md5($text);
        if (isset(self::$textLemmaTokensCache[$cacheKey])) {
            return self::$textLemmaTokensCache[$cacheKey];
        }

        self::$textLemmaTokensCache[$cacheKey] = self::tokenizeLemmas($text);

        return self::$textLemmaTokensCache[$cacheKey];
    }

    /**
     * @return list<string>
     */
    private static function tokenizeLemmas(string $text): array
    {
        $tokens = self::tokenize($text);
        $lemmas = [];

        foreach ($tokens as $token) {
            $token = mb_strtolower(trim($token), 'UTF-8');
            if ($token === '') {
                continue;
            }

            $isStop = TextAnalyzerStopWords::isPhraseStopWord($token);

            if (self::$unigramRoots !== []) {
                if (isset(self::$lemmaMap[$token])) {
                    $lemma = self::$lemmaMap[$token];
                } elseif ($isStop) {
                    // Служебные слова оставляем в TLPs, даже если их вырезали из униграмм.
                    $lemma = $token;
                } else {
                    continue;
                }
            } else {
                $lemma = $isStop ? $token : self::lemma($token);
            }

            $minLen = $isStop ? 1 : self::MIN_TOKEN_LENGTH;
            if (mb_strlen($lemma) >= $minLen) {
                $lemmas[] = $lemma;
            }
        }

        return $lemmas;
    }

    private static function lemma(string $token): string
    {
        $token = mb_strtolower(trim($token), 'UTF-8');
        if ($token === '') {
            return '';
        }

        if (isset(self::$lemmaMap[$token])) {
            return self::$lemmaMap[$token];
        }

        if (isset(self::$lemmaCache[$token])) {
            return self::$lemmaCache[$token];
        }

        $base = self::morphy()->base($token);
        $lemma = $base !== null && $base !== '' ? mb_strtolower($base, 'UTF-8') : $token;
        self::$lemmaCache[$token] = $lemma;

        return $lemma;
    }

    private static function morphy(): Morphy
    {
        if (self::$morphy === null) {
            self::$morphy = new Morphy();
        }

        return self::$morphy;
    }

    /**
     * @return list<string>
     */
    private static function tokenize(string $text): array
    {
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? '';

        if ($text === '') {
            return [];
        }

        return explode(' ', $text);
    }

    /**
     * @param list<string> $tokens
     * @return list<string>
     */
    private static function extractNgrams(array $tokens): array
    {
        $ngrams = [];
        $tokenCount = count($tokens);

        for ($n = self::MIN_N; $n <= self::MAX_N; $n++) {
            if ($tokenCount < $n) {
                continue;
            }

            for ($i = 0; $i <= $tokenCount - $n; $i++) {
                $slice = array_slice($tokens, $i, $n);
                $valid = true;

                foreach ($slice as $token) {
                    $isStop = TextAnalyzerStopWords::isPhraseStopWord($token);
                    $minLen = $isStop ? 1 : self::MIN_TOKEN_LENGTH;
                    if (mb_strlen($token) < $minLen) {
                        $valid = false;
                        break;
                    }
                }

                if ($valid) {
                    $ngrams[] = implode(' ', $slice);
                }
            }
        }

        return $ngrams;
    }

    public static function isValidUnigramPhrase(string $phrase): bool
    {
        if (self::$unigramRoots === []) {
            return true;
        }

        $tokens = preg_split('/\s+/u', trim($phrase), -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($tokens) || count($tokens) < self::MIN_N) {
            return false;
        }

        $contentHits = 0;
        foreach ($tokens as $token) {
            $key = mb_strtolower(trim((string) $token), 'UTF-8');
            if ($key === '') {
                return false;
            }

            if (TextAnalyzerStopWords::isPhraseStopWord($key)) {
                continue;
            }

            if (!isset(self::$unigramRoots[$key])) {
                return false;
            }
            $contentHits++;
        }

        // Фраза только из служебных слов — мусор; нужен хотя бы один значимый токен из униграмм.
        return $contentHits > 0;
    }

    /**
     * @return array<string, true>
     */
    public static function buildNoiseLemmaSet(): array
    {
        $noise = [
            'данные', 'данный', 'данных', 'персональный', 'персональных', 'обработка', 'обработку', 'согласие',
            'санкт', 'петербург', 'москва', 'новгород', 'нижний', 'ростов', 'дону', 'йошкар', 'ола', 'краснодар',
            'казань', 'калуга', 'брянск', 'иркутск', 'иваново', 'саранск', 'тюмень', 'архангельск', 'чебоксары',
            'тула', 'омск', 'калининград', 'белгород', 'хабаровск', 'симферополь', 'ставрополь', 'тверь', 'владимир',
            'интернет', 'магазин', 'каталог', 'товар', 'товары', 'товаров', 'корзина', 'корзину', 'добавить',
            'заказать', 'звонок', 'клик', 'получить', 'регистрационный', 'удостоверение', 'удостоверением',
            'политика', 'конфиденциальность', 'cookie', 'cookies', 'расходные', 'материалы', 'компания', 'компании',
            'доставка', 'оплата', 'наличие', 'наличии', 'контакт', 'контактной', 'заказ', 'запрос', 'запросу',
            'цена', 'скидка', 'акция', 'промокод', 'подписка', 'рассылка', 'copyright', 'все', 'права', 'защищены',
        ];

        $set = [];
        foreach ($noise as $lemma) {
            $set[mb_strtolower($lemma, 'UTF-8')] = true;
        }

        return $set;
    }

    /**
     * @param array<string, array<string, mixed>>|null $unigramTable
     * @return array<string, true>
     */
    public static function buildAnchorLemmaSet(?array $unigramTable, int $minSites = 3, float $minTfidf = 0.035): array
    {
        if (!is_array($unigramTable) || $unigramTable === []) {
            return [];
        }

        $candidates = [];
        foreach ($unigramTable as $root => $wordForm) {
            if (!is_array($wordForm) || !isset($wordForm['total']) || !is_array($wordForm['total'])) {
                continue;
            }

            $rootKey = mb_strtolower(trim((string) $root), 'UTF-8');
            if ($rootKey === '' || isset(self::$noiseLemmas[$rootKey]) || mb_strlen($rootKey) < self::MIN_TOKEN_LENGTH) {
                continue;
            }

            $total = $wordForm['total'];
            $tfidf = (float) ($total['tfidfTop'] ?? $total['score'] ?? 0);
            $sites = (int) ($total['numberOccurrences'] ?? 0);
            if ($sites < $minSites || $tfidf < $minTfidf) {
                continue;
            }

            $candidates[$rootKey] = $tfidf;
        }

        if ($candidates === []) {
            return [];
        }

        arsort($candidates);

        $anchors = [];
        $limit = 0;
        foreach ($candidates as $lemma => $tfidf) {
            $anchors[$lemma] = true;
            $limit++;
            if ($limit >= 120) {
                break;
            }
        }

        return $anchors;
    }

    /**
     * @param list<string> $tokens
     */
    public static function phraseTokens(string $phrase): array
    {
        $tokens = preg_split('/\s+/u', trim($phrase), -1, PREG_SPLIT_NO_EMPTY);

        return is_array($tokens) ? $tokens : [];
    }

    /**
     * @param list<string> $tokens
     */
    public static function hasRepeatedLemmaToken(array $tokens): bool
    {
        $seen = [];
        foreach ($tokens as $token) {
            $key = mb_strtolower(trim((string) $token), 'UTF-8');
            if ($key === '') {
                continue;
            }
            if (isset($seen[$key])) {
                return true;
            }
            $seen[$key] = true;
        }

        return false;
    }

    /**
     * @param list<string> $tokens
     * @param array<string, true> $anchorLemmas
     */
    public static function isLowQualityPhrase(string $phrase, array $tokens, array $anchorLemmas): bool
    {
        if ($tokens === [] || count($tokens) < self::MIN_N || count($tokens) > self::MAX_N) {
            return true;
        }

        if (self::hasRepeatedLemmaToken($tokens)) {
            return true;
        }

        $normalized = mb_strtolower(trim($phrase), 'UTF-8');
        if ($normalized === '') {
            return true;
        }

        foreach (self::boilerplatePhrasePatterns() as $pattern) {
            if (preg_match($pattern, $normalized)) {
                return true;
            }
        }

        $noiseCount = 0;
        $anchorCount = 0;
        foreach ($tokens as $token) {
            $key = mb_strtolower(trim((string) $token), 'UTF-8');
            if ($key === '') {
                continue;
            }
            if (isset(self::$noiseLemmas[$key])) {
                $noiseCount++;
            }
            if ($anchorLemmas !== [] && isset($anchorLemmas[$key])) {
                $anchorCount++;
            }
        }

        if ($noiseCount === count($tokens)) {
            return true;
        }

        if ($anchorLemmas !== [] && $anchorCount === 0) {
            return true;
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function boilerplatePhrasePatterns(): array
    {
        return [
            '/персональн/u',
            '/\bобработк/u',
            '/\bсогласи/u',
            '/интернет\s+магазин/u',
            '/москва\s+санкт/u',
            '/санкт\s+петербург/u',
            '/заказать\s+звонок/u',
            '/добавить\s+корзин/u',
            '/купить\s+клик/u',
            '/цена\s+запрос/u',
            '/регистрационн/u',
            '/корзин.*купить/u',
            '/купить.*корзин/u',
            '/время\s+работ/u',
            '/всей\s+росси/u',
            '/cookies/u',
            '/файлы\s+cookie/u',
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $phrasesTable
     * @param array<string, array<string, mixed>>|null $unigramTable
     * @return array<string, array<string, mixed>>
     */
    public static function filterQualityPhrases(array $phrasesTable, ?array $unigramTable = null): array
    {
        if ($phrasesTable === []) {
            return [];
        }

        if (self::$noiseLemmas === []) {
            self::$noiseLemmas = self::buildNoiseLemmaSet();
        }

        $anchorLemmas = self::buildAnchorLemmaSet($unigramTable);
        $filtered = [];

        foreach ($phrasesTable as $phrase => $row) {
            if (!is_string($phrase) || $phrase === '' || !is_array($row)) {
                continue;
            }

            $tokens = self::phraseTokens($phrase);
            if (self::isLowQualityPhrase($phrase, $tokens, $anchorLemmas)) {
                continue;
            }

            $filtered[$phrase] = $row;
        }

        return $filtered;
    }

    /**
     * «дерматоскоп оптический» и «оптический дерматоскоп» — одна фраза, оставляем лучшую по TF-IDF.
     *
     * @param array<string, array<string, mixed>> $phrasesTable
     * @return array<string, array<string, mixed>>
     */
    public static function deduplicatePermutedPhrases(array $phrasesTable): array
    {
        if ($phrasesTable === []) {
            return [];
        }

        $groups = [];
        foreach ($phrasesTable as $phrase => $row) {
            if (!is_string($phrase) || $phrase === '' || !is_array($row)) {
                continue;
            }

            $tokens = self::phraseTokens($phrase);
            if ($tokens === []) {
                continue;
            }

            $sorted = $tokens;
            sort($sorted, SORT_STRING);
            $signature = implode("\x1f", $sorted);
            if (!isset($groups[$signature])) {
                $groups[$signature] = [];
            }

            $groups[$signature][] = [
                'phrase' => $phrase,
                'row' => $row,
                'tokens' => $tokens,
            ];
        }

        $result = [];
        foreach ($groups as $entries) {
            usort($entries, static function (array $a, array $b): int {
                $scoreA = (float) ($a['row']['tfidfTop'] ?? $a['row']['score'] ?? 0);
                $scoreB = (float) ($b['row']['tfidfTop'] ?? $b['row']['score'] ?? 0);
                if ($scoreA !== $scoreB) {
                    return $scoreB <=> $scoreA;
                }

                $bm25A = (float) ($a['row']['bm25Top'] ?? 0);
                $bm25B = (float) ($b['row']['bm25Top'] ?? 0);
                if ($bm25A !== $bm25B) {
                    return $bm25B <=> $bm25A;
                }

                $lenCompare = count($a['tokens']) <=> count($b['tokens']);
                if ($lenCompare !== 0) {
                    return $lenCompare;
                }

                return strcmp($a['phrase'], $b['phrase']);
            });

            $best = $entries[0];
            $result[$best['phrase']] = $best['row'];
        }

        return $result;
    }

    /**
     * @param list<string> $tokens
     */
    public static function phraseLengthScore(array $tokens): int
    {
        $count = count($tokens);
        if ($count <= 2) {
            return 2;
        }
        if ($count === 3) {
            return 1;
        }

        return 0;
    }

    /**
     * Убирает короткие n-граммы, которые являются подстрокой более длинной фразы
     * с теми же метриками вхождений (типичный случай: «обработку персональных»
     * и «обработку персональных данных» с одинаковыми TF-IDF/BM25 и охватом).
     *
     * @param array<string, array<string, mixed>> $phrasesTable
     * @return array<string, array<string, mixed>>
     */
    public static function deduplicateOverlappingPhrases(array $phrasesTable): array
    {
        if ($phrasesTable === []) {
            return [];
        }

        $entries = [];
        foreach ($phrasesTable as $phrase => $row) {
            if (!is_string($phrase) || $phrase === '' || !is_array($row)) {
                continue;
            }

            $tokens = preg_split('/\s+/u', trim($phrase), -1, PREG_SPLIT_NO_EMPTY);
            if (!is_array($tokens) || count($tokens) < self::MIN_N) {
                continue;
            }

            $entries[] = [
                'phrase' => $phrase,
                'row' => $row,
                'tokens' => $tokens,
            ];
        }

        if ($entries === []) {
            return [];
        }

        usort($entries, static function (array $a, array $b): int {
            $lenCompare = count($b['tokens']) <=> count($a['tokens']);
            if ($lenCompare !== 0) {
                return $lenCompare;
            }

            $scoreA = (float) ($a['row']['tfidfTop'] ?? $a['row']['score'] ?? 0);
            $scoreB = (float) ($b['row']['tfidfTop'] ?? $b['row']['score'] ?? 0);
            if ($scoreA !== $scoreB) {
                return $scoreB <=> $scoreA;
            }

            $bm25A = (float) ($a['row']['bm25Top'] ?? 0);
            $bm25B = (float) ($b['row']['bm25Top'] ?? 0);

            return $bm25B <=> $bm25A;
        });

        $kept = [];
        foreach ($entries as $entry) {
            $isNestedDuplicate = false;

            foreach ($kept as $keptEntry) {
                if (!self::isContiguousSubphrase($entry['tokens'], $keptEntry['tokens'])) {
                    continue;
                }

                if (self::phraseOccurrenceStatsEquivalent($entry['row'], $keptEntry['row'])) {
                    $isNestedDuplicate = true;
                    break;
                }
            }

            if (!$isNestedDuplicate) {
                $kept[] = $entry;
            }
        }

        $result = [];
        foreach ($kept as $entry) {
            $result[$entry['phrase']] = $entry['row'];
        }

        return $result;
    }

    /**
     * @param list<string> $short
     * @param list<string> $long
     */
    public static function isContiguousSubphrase(array $short, array $long): bool
    {
        $shortLen = count($short);
        $longLen = count($long);

        if ($shortLen === 0 || $shortLen >= $longLen) {
            return false;
        }

        for ($i = 0; $i <= $longLen - $shortLen; $i++) {
            if (array_slice($long, $i, $shortLen) === $short) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     */
    public static function phraseOccurrenceStatsEquivalent(array $a, array $b): bool
    {
        $fields = [
            'numberOccurrences',
            'medianInCompetitors',
            'avgInTotalCompetitors',
            'totalRepeatMainPage',
        ];

        foreach ($fields as $field) {
            if ((int) ($a[$field] ?? 0) !== (int) ($b[$field] ?? 0)) {
                return false;
            }
        }

        $occA = $a['occurrences'] ?? null;
        $occB = $b['occurrences'] ?? null;
        if (is_array($occA) && is_array($occB)) {
            return $occA == $occB;
        }

        return (float) ($a['tfidfTop'] ?? $a['score'] ?? 0) === (float) ($b['tfidfTop'] ?? $b['score'] ?? 0)
            && (float) ($a['bm25Top'] ?? 0) === (float) ($b['bm25Top'] ?? 0);
    }
}
