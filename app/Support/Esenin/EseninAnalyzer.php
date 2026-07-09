<?php

namespace App\Support\Esenin;

use App\Classes\SimpleHtmlDom\HtmlDocument;
use App\Support\TextAnalyzerStopWords;
use App\TextAnalyzer;

final class EseninAnalyzer
{
    /** @var array<string, string> */
    public const BLOCK_LABELS = [
        'frequency' => 'Повторы',
        'style' => 'Стилистика',
        'keywords' => 'Запросы',
        'formality' => 'Водность',
        'readability' => 'Удобочитаемость',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function analyze(string $text, string $mode = 'risk'): array
    {
        $plain = self::normalizePlainText($text);
        if ($plain === '') {
            throw new \InvalidArgumentException('Текст для проверки не указан');
        }

        $words = EseninTextParser::tokenize($plain);
        EseninMorphology::resetCache();
        $lemmaTokens = EseninMorphology::lemmatizeTokens($words);
        $wordCounts = EseninTextParser::wordCounts($plain);
        $lemmaCounts = EseninMorphology::lemmaCounts($words);
        $lemmaGroups = EseninMorphology::groupByLemma($words);
        $totalWords = count($words);
        $sentences = EseninTextParser::sentences($plain);
        $charCount = mb_strlen(preg_replace('/\s+/u', '', $plain) ?? '', 'UTF-8');

        $metrics = self::buildMetrics($plain, $words, $lemmaTokens, $wordCounts, $lemmaCounts, $lemmaGroups, $totalWords, $sentences, $charCount);
        $blocks = self::scoreBlocks($metrics, $totalWords);
        $marks = self::buildMarks($plain, $words, $metrics, $blocks);

        $details = [];
        $totalRisk = 0;
        foreach (self::BLOCK_LABELS as $block => $label) {
            $sum = (int) ($blocks[$block]['score'] ?? 0);
            $details[] = [
                'block' => $block,
                'label' => $label,
                'sum' => $sum,
            ];
            if ($mode === 'risk' || $mode === $block) {
                $totalRisk += $sum;
            }
        }

        if ($mode !== 'risk' && isset($blocks[$mode])) {
            $totalRisk = (int) $blocks[$mode]['score'];
        }

        $activeBlock = $mode === 'risk' ? 'risk' : $mode;
        $params = $blocks[$activeBlock === 'risk' ? 'frequency' : $activeBlock]['params'] ?? [];
        if ($activeBlock === 'risk') {
            $params = self::flattenScoredParams($blocks);
        }

        $highlights = [];
        foreach (array_merge(['risk'], array_keys(self::BLOCK_LABELS)) as $blockKey) {
            $highlights[$blockKey] = self::renderHighlightedHtml($plain, $marks, $blockKey);
        }

        return [
            'risk' => $totalRisk,
            'level' => self::levelFromScore($totalRisk),
            'details' => $details,
            'params' => $params,
            'metrics' => $metrics,
            'blocks' => $blocks,
            'frequency_lists' => $metrics['frequency_lists'] ?? ['words' => [], 'phrases' => []],
            'marks' => $marks,
            'highlighted_html' => $highlights[$activeBlock],
            'highlights' => $highlights,
            'stats' => [
                'chars' => mb_strlen($plain, 'UTF-8'),
                'chars_no_spaces' => $charCount,
                'words' => $totalWords,
            ],
        ];
    }

    public static function extractTextFromUrl(string $url, string $selector = ''): string
    {
        $html = TextAnalyzer::curlInitV2($url);
        if (! is_string($html) || trim($html) === '') {
            throw new \RuntimeException('Не удалось загрузить страницу');
        }

        if ($selector !== '') {
            $document = new HtmlDocument();
            $document->load($html);
            $nodes = $document->find($selector);
            $chunks = [];
            foreach ($nodes as $node) {
                $chunks[] = html_entity_decode(strip_tags($node->innertext()), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            $text = trim(implode("\n\n", array_filter($chunks)));
            if ($text !== '') {
                return $text;
            }
        }

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';

        return trim($text);
    }

    private static function normalizePlainText(string $text): string
    {
        if (preg_match('/<[^>]+>/', $text)) {
            $text = preg_replace('/<\s*br\s*\/?>/i', "\n", $text) ?? $text;
            $text = preg_replace('/<\/\s*(p|div|h[1-6]|li|tr|blockquote|section|article|header|footer|main|aside|figure|figcaption|table|thead|tbody|tfoot|ul|ol|dl|dt|dd)\s*>/i', "\n", $text) ?? $text;
            $text = preg_replace('/<\s*(p|div|h[1-6]|li|tr|blockquote|section|article|header|footer|main|aside|figure|figcaption|table|thead|tbody|tfoot|ul|ol|dl|dt|dd)[^>]*>/i', '', $text) ?? $text;
        }

        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\r\n?/", "\n", $text) ?? $text;
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    public static function plainTextLength(string $text): int
    {
        return mb_strlen(self::normalizePlainText($text), 'UTF-8');
    }

    /**
     * @param array<int, string> $words
     * @param array<string, int> $wordCounts
     * @param array<int, string> $sentences
     * @return array<string, mixed>
     */
    private static function buildMetrics(
        string $plain,
        array $words,
        array $lemmaTokens,
        array $wordCounts,
        array $lemmaCounts,
        array $lemmaGroups,
        int $totalWords,
        array $sentences,
        int $charCount
    ): array {
        $bigrams = EseninTextParser::ngramCounts($lemmaTokens, 2);
        $styleMatches = self::matchStyleDictionary($plain);
        $styleQuanta = array_sum(array_column($styleMatches, 'weight'));
        $styleDensity = $totalWords > 0 ? round($styleQuanta / $totalWords, 2) : 0.0;

        $keywordPhrases = self::detectKeywordPhrases($words);
        $keywordCoverage = self::keywordCoverage($plain, $keywordPhrases);

        $andCount = $lemmaCounts['и'] ?? ($wordCounts['и'] ?? 0);
        $andPerThousand = $totalWords > 0 ? round(($andCount / $totalWords) * 1000, 1) : 0.0;

        return [
            'academic_nausea' => EseninTextParser::academicNausea($lemmaCounts, $totalWords),
            'phrase_nausea' => EseninTextParser::phraseNausea($bigrams, $totalWords),
            'classic_nausea' => EseninTextParser::classicNausea($lemmaCounts),
            'super_frequent_words' => EseninTextParser::superFrequentWords($lemmaCounts, $totalWords),
            'and_concentration' => $andPerThousand,
            'style_problems' => $styleQuanta,
            'style_density' => $styleDensity,
            'style_matches' => $styleMatches,
            'keyword_phrases' => $keywordPhrases,
            'keyword_coverage' => $keywordCoverage,
            'wateriness' => EseninTextParser::wateriness($lemmaCounts, $totalWords),
            'informative_share' => EseninTextParser::informativeShare($lemmaCounts, $totalWords),
            'readability_index' => EseninTextParser::readabilityIndex($charCount, $totalWords, max(1, count($sentences))),
            'top_words' => self::topWords($lemmaGroups, $totalWords, 20),
            'top_bigrams' => self::topNgrams($bigrams, $totalWords, 15),
            'lemma_groups' => $lemmaGroups,
            'frequency_lists' => [
                'words' => self::topWords($lemmaGroups, $totalWords, 20),
                'phrases' => self::topNgrams($bigrams, $totalWords, 15),
            ],
            'long_sentences' => self::longSentences($sentences),
            'long_words' => self::longWords($wordCounts),
        ];
    }

    /**
     * @param array<string, mixed> $metrics
     * @return array<string, array{score: int, params: array<int, array{name: string, value: mixed, score: int}>}>
     */
    private static function scoreBlocks(array $metrics, int $totalWords): array
    {
        $frequencyScore = 0;
        $frequencyParams = [];

        $academic = (float) $metrics['academic_nausea'];
        $academicPenalty = self::tierScore($academic, [
            [10.5, 0],
            [12.0, 1],
            [14.0, 2],
            [16.0, 3],
        ]);
        $frequencyScore += $academicPenalty;
        $frequencyParams[] = ['name' => 'Академическая тошнота', 'value' => $academic, 'score' => $academicPenalty];

        $phrase = (float) $metrics['phrase_nausea'];
        $phrasePenalty = self::tierScore($phrase, [
            [8.0, 0],
            [10.0, 1],
            [12.0, 2],
        ]);
        $frequencyScore += $phrasePenalty;
        $frequencyParams[] = ['name' => 'Тошнота словосочетаний', 'value' => $phrase, 'score' => $phrasePenalty];

        $frequencyParams[] = [
            'name' => 'Классическая тошнота',
            'value' => $metrics['classic_nausea'],
            'score' => 0,
        ];

        $superFreq = $metrics['super_frequent_words'];
        $superPenalty = count($superFreq) >= 3 ? 2 : (count($superFreq) >= 1 ? 1 : 0);
        $frequencyScore += $superPenalty;
        $frequencyParams[] = [
            'name' => 'Сверхчастые слова',
            'value' => count($superFreq) > 0 ? count($superFreq) : 'Нет',
            'score' => $superPenalty,
        ];

        $andConc = (float) $metrics['and_concentration'];
        $andPenalty = self::tierScore($andConc, [
            [25, 0],
            [35, 1],
            [45, 2],
            [55, 3],
        ]);
        $frequencyScore += $andPenalty;
        $frequencyParams[] = ['name' => 'Концентрация «и» (на 1000 слов)', 'value' => $andConc, 'score' => $andPenalty];

        $styleQuanta = (int) $metrics['style_problems'];
        $styleDensity = (float) $metrics['style_density'];
        $styleScore = 0;
        if ($styleQuanta > 100 && $styleDensity > 0.1) {
            $styleScore += 1;
        }
        $styleScore += self::tierScore($styleDensity, [
            [0.1, 0],
            [0.15, 1],
            [0.2, 2],
            [0.25, 8],
        ]);
        $styleParams = [
            ['name' => 'Плотность стилистических проблем', 'value' => $styleDensity, 'score' => self::tierScore($styleDensity, [[0.1, 0], [0.15, 1], [0.2, 2], [0.25, 8]])],
            ['name' => 'Количество стилистических проблем', 'value' => $styleQuanta, 'score' => ($styleQuanta > 100 && $styleDensity > 0.1) ? 1 : 0],
        ];

        $coverage = (float) $metrics['keyword_coverage'];
        $keywordScore = self::tierScore($coverage, [
            [0.10, 0],
            [0.15, 2],
            [0.20, 4],
            [0.30, 8],
        ]);
        $keywordParams = [
            ['name' => 'Покрытие ключевыми фразами', 'value' => round($coverage * 100, 1) . '%', 'score' => $keywordScore],
            ['name' => 'Найдено фраз (3+ слова)', 'value' => count($metrics['keyword_phrases']), 'score' => 0],
        ];

        $informative = (float) $metrics['informative_share'];
        $formalityScore = self::tierScore($informative, [
            [0.23, 0],
            [0.20, 1],
            [0.18, 2],
            [0.15, 4],
        ], true);
        $formalityParams = [
            ['name' => 'Доля содержательного текста', 'value' => $informative, 'score' => $formalityScore],
            ['name' => 'Водность (стоп-слова)', 'value' => $metrics['wateriness'], 'score' => 0],
        ];

        $readability = (float) $metrics['readability_index'];
        $readabilityScore = self::tierScore($readability, [
            [15, 0],
            [17, 1],
            [20, 2],
        ]);
        $readabilityParams = [
            ['name' => 'Индекс удобочитаемости', 'value' => $readability, 'score' => $readabilityScore],
        ];

        if ($totalWords < 50) {
            $frequencyScore = 0;
            $styleScore = 0;
            $keywordScore = 0;
            $formalityScore = 0;
            $readabilityScore = 0;
        }

        return [
            'frequency' => ['score' => $frequencyScore, 'params' => $frequencyParams],
            'style' => ['score' => $styleScore, 'params' => $styleParams],
            'keywords' => ['score' => $keywordScore, 'params' => $keywordParams],
            'formality' => ['score' => $formalityScore, 'params' => $formalityParams],
            'readability' => ['score' => $readabilityScore, 'params' => $readabilityParams],
        ];
    }

    /**
     * @param array<int, array{threshold: float, score: int}> $tiers
     */
    private static function tierScore(float $value, array $tiers, bool $reverse = false): int
    {
        if ($reverse) {
            $tiers = array_reverse($tiers);
            foreach ($tiers as [$threshold, $score]) {
                if ($value <= $threshold) {
                    return (int) $score;
                }
            }

            return 0;
        }

        $result = 0;
        foreach ($tiers as [$threshold, $score]) {
            if ($value >= $threshold) {
                $result = (int) $score;
            }
        }

        return $result;
    }

    /**
     * @return array<int, array{phrase: string, weight: int, hint: string, offset: int}>
     */
    private static function matchStyleDictionary(string $plain): array
    {
        $entries = config('esenin-style-dictionary.entries', []);
        usort($entries, static function ($a, $b) {
            return mb_strlen($b['phrase']) <=> mb_strlen($a['phrase']);
        });

        $lower = mb_strtolower($plain, 'UTF-8');
        $used = [];
        $matches = [];

        foreach ($entries as $entry) {
            $phrase = mb_strtolower(trim((string) ($entry['phrase'] ?? '')), 'UTF-8');
            if ($phrase === '') {
                continue;
            }

            $offset = 0;
            while (($pos = mb_stripos($lower, $phrase, $offset, 'UTF-8')) !== false) {
                $end = $pos + mb_strlen($phrase, 'UTF-8');
                $overlap = false;
                for ($i = $pos; $i < $end; $i++) {
                    if (! empty($used[$i])) {
                        $overlap = true;
                        break;
                    }
                }
                if (! $overlap) {
                    for ($i = $pos; $i < $end; $i++) {
                        $used[$i] = true;
                    }
                    $matches[] = [
                        'phrase' => $phrase,
                        'weight' => (int) ($entry['weight'] ?? 1),
                        'hint' => (string) ($entry['hint'] ?? ''),
                        'offset' => $pos,
                        'length' => mb_strlen($phrase, 'UTF-8'),
                        'block' => 'style',
                    ];
                }
                $offset = $pos + 1;
            }
        }

        return $matches;
    }

    /**
     * @param array<int, string> $words
     * @return array<int, array{phrase: string, count: int}>
     */
    private static function detectKeywordPhrases(array $words): array
    {
        $result = [];
        foreach ([3, 4, 5] as $size) {
            $counts = EseninTextParser::ngramCounts($words, $size);
            foreach ($counts as $phrase => $count) {
                if ($count < 2) {
                    continue;
                }
                $tokens = explode(' ', $phrase);
                if (self::isStopEdge($tokens[0]) || self::isStopEdge(end($tokens))) {
                    continue;
                }
                $result[] = ['phrase' => $phrase, 'count' => $count];
            }
        }

        usort($result, static function ($a, $b) {
            return ($b['count'] <=> $a['count']) ?: (mb_strlen($b['phrase']) <=> mb_strlen($a['phrase']));
        });

        return array_slice($result, 0, 30);
    }

    private static function isStopEdge(string $word): bool
    {
        return TextAnalyzerStopWords::isPhraseStopWord($word);
    }

    /**
     * @param array<int, array{phrase: string, count: int}> $phrases
     */
    private static function keywordCoverage(string $plain, array $phrases): float
    {
        if ($plain === '' || $phrases === []) {
            return 0.0;
        }

        $lower = mb_strtolower($plain, 'UTF-8');
        $covered = [];
        foreach ($phrases as $row) {
            $phrase = $row['phrase'];
            $offset = 0;
            while (($pos = mb_stripos($lower, $phrase, $offset, 'UTF-8')) !== false) {
                $len = mb_strlen($phrase, 'UTF-8');
                for ($i = $pos; $i < $pos + $len; $i++) {
                    $covered[$i] = true;
                }
                $offset = $pos + 1;
            }
        }

        return count($covered) / max(1, mb_strlen($plain, 'UTF-8'));
    }

    /**
     * @param array<string, int> $wordCounts
     * @return array<int, array{word: string, count: int}>
     */
    private static function topWords(array $lemmaGroups, int $totalWords, int $limit): array
    {
        uasort($lemmaGroups, static function ($a, $b) {
            return ($b['count'] <=> $a['count']) ?: strcmp($a['lemma'], $b['lemma']);
        });

        $rows = [];
        foreach (array_slice($lemmaGroups, 0, $limit, true) as $group) {
            $lemma = (string) $group['lemma'];
            $count = (int) $group['count'];
            $isStop = TextAnalyzerStopWords::isPhraseStopWord($lemma);
            $percent = $totalWords > 0 ? round($count / $totalWords * 100, 1) : 0.0;
            $flagged = false;
            if ($lemma === 'и' && $totalWords > 0 && ($count / $totalWords) * 1000 >= 35) {
                $flagged = true;
            }
            if (! $isStop && $count >= 3 && $percent >= 1.5) {
                $flagged = true;
            }
            $forms = array_keys($group['forms'] ?? []);
            $rows[] = [
                'word' => $lemma,
                'count' => $count,
                'percent' => $percent,
                'stop' => $isStop,
                'flagged' => $flagged,
                'forms' => $forms,
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, int> $counts
     * @return array<int, array{phrase: string, count: int, percent: float}>
     */
    private static function topNgrams(array $counts, int $totalWords, int $limit): array
    {
        arsort($counts);
        $rows = [];
        foreach (array_slice($counts, 0, $limit, true) as $phrase => $count) {
            $rows[] = [
                'phrase' => $phrase,
                'count' => $count,
                'percent' => $totalWords > 0 ? round(($count * 2) / $totalWords * 100, 1) : 0.0,
                'flagged' => $count >= 2,
            ];
        }

        return $rows;
    }

    /**
     * @param array<int, string> $sentences
     * @return array<int, array{sentence: string, words: int}>
     */
    private static function longSentences(array $sentences): array
    {
        $rows = [];
        foreach ($sentences as $sentence) {
            $words = count(EseninTextParser::tokenize($sentence));
            if ($words >= 25) {
                $rows[] = ['sentence' => $sentence, 'words' => $words];
            }
        }

        usort($rows, static function ($a, $b) {
            return $b['words'] <=> $a['words'];
        });

        return array_slice($rows, 0, 5);
    }

    /**
     * @param array<string, int> $wordCounts
     * @return array<int, array{word: string, count: int, length: int}>
     */
    private static function longWords(array $wordCounts): array
    {
        $rows = [];
        foreach ($wordCounts as $word => $count) {
            $len = mb_strlen($word, 'UTF-8');
            if ($len >= 12) {
                $rows[] = ['word' => $word, 'count' => $count, 'length' => $len];
            }
        }

        usort($rows, static function ($a, $b) {
            return $b['length'] <=> $a['length'];
        });

        return array_slice($rows, 0, 10);
    }

    /**
     * @param array<string, array{score: int, params: array}> $blocks
     * @return array<int, array{name: string, value: mixed, score: int}>
     */
    private static function flattenScoredParams(array $blocks): array
    {
        $params = [];
        foreach ($blocks as $block) {
            foreach ($block['params'] as $row) {
                if ((int) ($row['score'] ?? 0) > 0 || in_array($row['name'], [
                    'Академическая тошнота',
                    'Плотность стилистических проблем',
                    'Покрытие ключевыми фразами',
                    'Доля содержательного текста',
                    'Индекс удобочитаемости',
                ], true)) {
                    $params[] = $row;
                }
            }
        }

        return $params;
    }

    /**
     * @param array<string, mixed> $metrics
     * @param array<string, array{score: int}> $blocks
     * @return array<int, array<string, mixed>>
     */
    private static function buildMarks(string $plain, array $words, array $metrics, array $blocks): array
    {
        $marks = $metrics['style_matches'];
        $totalWords = count($words);

        foreach ($metrics['super_frequent_words'] as $row) {
            $marks = array_merge($marks, self::markLemmaForms(
                $plain,
                (string) $row['word'],
                $metrics['lemma_groups'][$row['word']]['forms'] ?? [$row['word'] => (int) $row['count']],
                'frequency',
                self::frequencyWordHint((string) $row['word'], (int) $row['count'], $totalWords, 'Сверхчастое слово (лемма)')
            ));
        }

        foreach ($metrics['top_words'] as $row) {
            if (empty($row['flagged'])) {
                continue;
            }
            $lemma = (string) $row['word'];
            $forms = $metrics['lemma_groups'][$lemma]['forms'] ?? [$lemma => (int) $row['count']];
            $formsHint = count($row['forms'] ?? []) > 1
                ? ' Формы: ' . implode(', ', array_slice($row['forms'], 0, 5)) . '.'
                : '';
            $marks = array_merge($marks, self::markLemmaForms(
                $plain,
                $lemma,
                $forms,
                'frequency',
                self::frequencyWordHint($lemma, (int) $row['count'], $totalWords, 'Частая лемма') . $formsHint
            ));
        }

        foreach ($metrics['top_bigrams'] as $row) {
            if (empty($row['flagged'])) {
                continue;
            }
            $marks = array_merge($marks, self::markPhraseOccurrences(
                $plain,
                (string) $row['phrase'],
                'frequency',
                self::frequencyPhraseHint((string) $row['phrase'], (int) $row['count'], $totalWords)
            ));
        }

        foreach ($metrics['keyword_phrases'] as $row) {
            $marks = array_merge($marks, self::markPhraseOccurrences(
                $plain,
                $row['phrase'],
                'keywords',
                'Повторяющаяся SEO-фраза (' . $row['count'] . '×) — разбавьте синонимами или перефразируйте'
            ));
        }

        foreach ($metrics['long_words'] as $row) {
            $marks = array_merge($marks, self::markWordOccurrences(
                $plain,
                $row['word'],
                'readability',
                'Длинное слово (' . $row['length'] . ' букв) — упростите формулировку',
                'word'
            ));
        }

        foreach ($metrics['long_sentences'] as $row) {
            $marks = array_merge($marks, self::markPhraseOccurrences(
                $plain,
                (string) $row['sentence'],
                'readability',
                'Длинное предложение (' . (int) $row['words'] . ' слов) — разбейте на 2–3 коротких',
                'sentence'
            ));
        }

        $genericLookup = array_flip(array_map(static function ($word) {
            return mb_strtolower($word, 'UTF-8');
        }, config('esenin-generic-words', [])));

        foreach ($wordCounts as $word => $count) {
            if ($count <= 0) {
                continue;
            }

            if (TextAnalyzerStopWords::isPhraseStopWord($word)) {
                $marks = array_merge($marks, self::markWordOccurrences(
                    $plain,
                    $word,
                    'formality',
                    'Стоп-слово — разбавьте текст конкретикой',
                    'stop'
                ));
                continue;
            }

            if (isset($genericLookup[$word])) {
                $marks = array_merge($marks, self::markWordOccurrences(
                    $plain,
                    $word,
                    'formality',
                    'Общее («пустое») слово — замените на конкретику',
                    'generic'
                ));
            }
        }

        return $marks;
    }

    private static function frequencyWordHint(string $word, int $count, int $totalWords, string $reason): string
    {
        $percent = $totalWords > 0 ? round($count / $totalWords * 100, 1) : 0.0;

        return $reason . ': «' . $word . '» — ' . $count . ' раз (' . $percent . '%). Замените часть повторов синонимами.';
    }

    private static function frequencyPhraseHint(string $phrase, int $count, int $totalWords): string
    {
        $percent = $totalWords > 0 ? round(($count * 2) / $totalWords * 100, 1) : 0.0;

        return 'Повтор словосочетания: «' . $phrase . '» — ' . $count . ' раз (~' . $percent . '% текста). Перефразируйте или удалите лишние вхождения.';
    }

    /**
     * @param array<string, int> $forms
     * @return array<int, array<string, mixed>>
     */
    private static function markLemmaForms(string $plain, string $lemma, array $forms, string $block, string $hint): array
    {
        $marks = [];
        foreach (array_keys($forms) as $form) {
            $marks = array_merge($marks, self::markWordOccurrences($plain, (string) $form, $block, $hint));
        }

        return $marks;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function markWordOccurrences(string $plain, string $word, string $block, string $hint, string $variant = ''): array
    {
        $marks = [];
        $pattern = '/(?<![\p{L}\p{N}])' . preg_quote($word, '/') . '(?![\p{L}\p{N}])/ui';
        if (! preg_match_all($pattern, $plain, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        foreach ($matches[0] as $match) {
            $byteOffset = $match[1];
            $marks[] = [
                'offset' => self::byteOffsetToChar($plain, $byteOffset),
                'length' => mb_strlen($match[0], 'UTF-8'),
                'block' => $block,
                'hint' => $hint,
                'variant' => $variant,
                'weight' => 1,
            ];
        }

        return $marks;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function markPhraseOccurrences(string $plain, string $phrase, string $block, string $hint, string $variant = ''): array
    {
        $marks = [];
        $lower = mb_strtolower($plain, 'UTF-8');
        $needle = mb_strtolower($phrase, 'UTF-8');
        $offset = 0;
        while (($pos = mb_stripos($lower, $needle, $offset, 'UTF-8')) !== false) {
            $marks[] = [
                'offset' => $pos,
                'length' => mb_strlen($needle, 'UTF-8'),
                'block' => $block,
                'hint' => $hint,
                'variant' => $variant,
                'weight' => 1,
            ];
            $offset = $pos + 1;
        }

        return $marks;
    }

    private static function byteOffsetToChar(string $text, int $byteOffset): int
    {
        return mb_strlen(substr($text, 0, $byteOffset), 'UTF-8');
    }

    /**
     * @param array<int, array<string, mixed>> $marks
     */
    private static function renderHighlightedHtml(string $plain, array $marks, string $block): string
    {
        if ($marks === []) {
            return nl2br(htmlspecialchars($plain, ENT_QUOTES, 'UTF-8'));
        }

        $filtered = array_values(array_filter($marks, static function ($mark) use ($block) {
            if ($block === 'risk') {
                return true;
            }

            return ($mark['block'] ?? '') === $block;
        }));

        usort($filtered, static function ($a, $b) {
            return ($a['offset'] <=> $b['offset']) ?: ($b['length'] <=> $a['length']);
        });

        $occupied = [];
        $accepted = [];
        foreach ($filtered as $mark) {
            $start = (int) $mark['offset'];
            $end = $start + (int) $mark['length'];
            $overlap = false;
            for ($i = $start; $i < $end; $i++) {
                if (! empty($occupied[$i])) {
                    $overlap = true;
                    break;
                }
            }
            if ($overlap) {
                continue;
            }
            for ($i = $start; $i < $end; $i++) {
                $occupied[$i] = true;
            }
            $accepted[] = $mark;
        }

        usort($accepted, static function ($a, $b) {
            return $b['offset'] <=> $a['offset'];
        });

        $html = $plain;
        foreach ($accepted as $mark) {
            $start = (int) $mark['offset'];
            $length = (int) $mark['length'];
            $fragment = mb_substr($html, $start, $length, 'UTF-8');
            $blockName = (string) ($mark['block'] ?? 'style');
            $variant = (string) ($mark['variant'] ?? '');
            $class = 'esenin-mark esenin-mark--' . htmlspecialchars($blockName, ENT_QUOTES, 'UTF-8');
            if ($variant !== '') {
                $class .= ' esenin-mark--' . htmlspecialchars($blockName . '-' . $variant, ENT_QUOTES, 'UTF-8');
            }
            $title = htmlspecialchars((string) ($mark['hint'] ?? ''), ENT_QUOTES, 'UTF-8');
            $replacement = '<mark class="' . $class . '" data-esenin-tip="' . $title . '" data-esenin-mark="' . htmlspecialchars((string) ($mark['block'] ?? ''), ENT_QUOTES, 'UTF-8') . '">' .
                htmlspecialchars($fragment, ENT_QUOTES, 'UTF-8') .
                '<span class="esenin-mark__icon" aria-hidden="true">!</span></mark>';
            $html = mb_substr($html, 0, $start, 'UTF-8') . $replacement . mb_substr($html, $start + $length, null, 'UTF-8');
        }

        return nl2br($html);
    }

    public static function levelFromScore(int $score): string
    {
        if ($score >= 13) {
            return 'критический';
        }
        if ($score >= 8) {
            return 'высокий';
        }
        if ($score >= 5) {
            return 'средний';
        }

        return 'незначительный';
    }
}
