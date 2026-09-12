<?php

namespace App\Services\Demo;

use App\Support\TextAnalyzerPdfBranding;
use App\TextAnalyzer;

class TextAnalyzerDemoService
{
    public const MODULE = 'analiz-teksta';

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return config('cabinet-text-analyzer.demo', []);
    }

    /**
     * @param array{
     *   mode?: string,
     *   text?: string,
     *   url?: string,
     *   exclude_stop_words?: bool,
     *   no_index?: bool,
     *   hidden_text?: bool,
     *   compare_competitor?: bool,
     *   competitor_url?: string
     * } $input
     * @return array{ok: true, payload: array<string, mixed>}|array{ok: false, status: int, error: string, message: string}
     */
    public static function validate(array $input): array
    {
        $cfg = self::config();
        $maxChars = (int) ($cfg['max_chars'] ?? 3000);
        $minChars = (int) ($cfg['min_chars'] ?? 100);
        $mode = ($input['mode'] ?? 'text') === 'url' ? 'url' : 'text';

        if ($mode === 'url') {
            $url = trim((string) ($input['url'] ?? ''));
            if ($url === '') {
                return self::fail(422, 'validation', 'Укажите URL страницы для анализа');
            }
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                return self::fail(422, 'validation', 'URL страницы должен быть корректным');
            }
        } else {
            $text = isset($input['text']) ? (string) $input['text'] : '';
            if ($text === '' || trim($text) === '') {
                return self::fail(422, 'validation', 'Вставьте текст для анализа');
            }
            if (mb_strlen($text) < $minChars) {
                return self::fail(
                    422,
                    'validation',
                    sprintf('В демо минимум %d символов (в кабинете — от 200 для текста).', $minChars)
                );
            }
            if (mb_strlen($text) > $maxChars) {
                $full = (int) ($cfg['full_max_chars'] ?? 38600);

                return self::fail(
                    422,
                    'validation',
                    sprintf(
                        'В демо до %s символов. Полный лимит %s — в кабинете.',
                        number_format($maxChars, 0, ',', ' '),
                        number_format($full, 0, ',', ' ')
                    )
                );
            }
        }

        $compareCompetitor = !in_array($input['compare_competitor'] ?? false, [false, 0, '0', 'false', 'off', ''], true);
        $competitorUrl = trim((string) ($input['competitor_url'] ?? ''));
        if ($compareCompetitor) {
            if ($competitorUrl === '') {
                return self::fail(422, 'validation', 'Укажите URL страницы конкурента');
            }
            if (!filter_var($competitorUrl, FILTER_VALIDATE_URL)) {
                return self::fail(422, 'validation', 'URL конкурента должен быть корректным');
            }
            if ($mode === 'url') {
                $pageUrl = trim((string) ($input['url'] ?? ''));
                if ($pageUrl !== '' && strcasecmp(rtrim($pageUrl, '/'), rtrim($competitorUrl, '/')) === 0) {
                    return self::fail(422, 'validation', 'URL конкурента должен отличаться от URL вашей страницы');
                }
            }
        }

        return ['ok' => true, 'payload' => $input];
    }

    /**
     * @param array{
     *   mode?: string,
     *   text?: string,
     *   url?: string,
     *   exclude_stop_words?: bool,
     *   no_index?: bool,
     *   hidden_text?: bool,
     *   compare_competitor?: bool,
     *   competitor_url?: string
     * } $input
     * @return array<string, mixed>|array{ok: false, status: int, error: string, message: string}
     */
    public static function analyze(array $input): array
    {
        $cfg = self::config();
        $wordsLimit = (int) ($cfg['words_rows'] ?? 10);
        $zipfRowsLimit = (int) ($cfg['zipf_rows'] ?? 10);
        $zipfChartLimit = (int) ($cfg['zipf_chart_points'] ?? 12);
        $phrasesLimit = (int) ($cfg['phrases_rows'] ?? 10);
        $cloudLimit = (int) ($cfg['cloud_text_words'] ?? 35);
        $compareWordsLimit = (int) ($cfg['compare_words_rows'] ?? 10);

        $mode = ($input['mode'] ?? 'text') === 'url' ? 'url' : 'text';
        $excludeStopWords = !in_array($input['exclude_stop_words'] ?? true, [false, 0, '0', 'false', 'off'], true);
        $noIndex = !in_array($input['no_index'] ?? false, [false, 0, '0', 'false', 'off', ''], true);
        $hiddenText = !in_array($input['hidden_text'] ?? false, [false, 0, '0', 'false', 'off', ''], true);
        $competitorUrl = !in_array($input['compare_competitor'] ?? false, [false, 0, '0', 'false', 'off', ''], true)
            ? trim((string) ($input['competitor_url'] ?? ''))
            : '';

        $request = [
            'type' => $mode,
            'demo' => true,
            'conjunctionsPrepositionsPronouns' => $excludeStopWords ? '1' : '0',
            'removeWords' => false,
            'hiddenText' => $hiddenText,
            'noIndex' => $noIndex,
        ];

        if ($mode === 'url') {
            $pageUrl = trim((string) ($input['url'] ?? ''));
            $html = TextAnalyzer::curlInit($pageUrl);
            if ($html === false || $html === '') {
                return self::fail(422, 'url_fetch', 'Не удалось загрузить страницу. Проверьте URL.');
            }
            $html = TextAnalyzer::removeStylesAndScripts($html);
            $response = TextAnalyzer::analyze($html, $request);
        } else {
            $response = TextAnalyzer::analyze((string) ($input['text'] ?? ''), $request);
        }

        $comparisonPayload = null;
        if ($competitorUrl !== '') {
            $competitorHtml = TextAnalyzer::curlInit($competitorUrl);
            if ($competitorHtml === false || $competitorHtml === '') {
                return self::fail(422, 'competitor_fetch', 'Не удалось загрузить страницу конкурента. Проверьте URL.');
            }
            $competitorHtml = TextAnalyzer::removeStylesAndScripts($competitorHtml);
            $competitorResponse = TextAnalyzer::analyze($competitorHtml, $request);
            TextAnalyzer::attachCompetitorComparison($response, $competitorResponse, $competitorUrl);

            $compareRows = $response['comparison']['totalWords'] ?? [];
            $competitorGeneral = $competitorResponse['general'] ?? [];
            $comparisonPayload = [
                'competitor_url' => $competitorUrl,
                'competitor_host' => TextAnalyzer::urlHost($competitorUrl),
                'general_competitor' => $competitorGeneral,
                'words' => [
                    'rows' => self::mapCompareWords(array_slice($compareRows, 0, $compareWordsLimit)),
                    'total' => count($compareRows),
                    'shown' => min($compareWordsLimit, count($compareRows)),
                ],
            ];
        }
        $totalWords = $response['totalWords'] ?? [];
        $graph = $response['graph'] ?? [];
        $zipfAllRows = TextAnalyzerPdfBranding::zipfTableRows($graph);
        $phrasesAll = $response['phrases'] ?? [];
        $cloudTextAll = $response['clouds']['text'] ?? [];

        return [
            'general' => $response['general'] ?? [],
            'words' => [
                'rows' => self::mapTopWords(array_slice($totalWords, 0, $wordsLimit)),
                'total' => count($totalWords),
                'shown' => min($wordsLimit, count($totalWords)),
            ],
            'zipf' => [
                'graph' => self::mapZipfGraph(array_slice($graph, 0, $zipfChartLimit)),
                'rows' => array_slice($zipfAllRows, 0, $zipfRowsLimit),
                'total' => count($zipfAllRows),
                'shown' => min($zipfRowsLimit, count($zipfAllRows)),
            ],
            'phrases' => [
                'rows' => self::mapPhrases(array_slice($phrasesAll, 0, $phrasesLimit)),
                'total' => count($phrasesAll),
                'shown' => min($phrasesLimit, count($phrasesAll)),
            ],
            'cloud' => [
                'text' => self::mapCloud(array_slice($cloudTextAll, 0, $cloudLimit)),
                'text_total' => count($cloudTextAll),
                'text_shown' => min($cloudLimit, count($cloudTextAll)),
            ],
            'comparison' => $comparisonPayload,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildResponse(array $result, int $remaining, string $guestId): array
    {
        $cfg = self::config();
        $moduleSlug = (string) ($cfg['module_slug'] ?? self::MODULE);
        $registerBase = rtrim((string) config('app.url', 'https://cabinet.titlo.ru'), '/');

        $registerUrl = $registerBase . '/register?' . http_build_query([
            'module' => $moduleSlug,
            'from' => 'demo',
            'guest' => $guestId,
        ]);

        return [
            'demo' => true,
            'module' => self::MODULE,
            'remaining' => $remaining,
            'limits' => [
                'max_chars' => (int) ($cfg['max_chars'] ?? 3000),
                'min_chars' => (int) ($cfg['min_chars'] ?? 100),
                'max_runs_per_day' => (int) ($cfg['max_runs_per_day'] ?? 5),
                'full_max_chars' => (int) ($cfg['full_max_chars'] ?? 38600),
                'words_rows' => (int) ($cfg['words_rows'] ?? 10),
                'zipf_rows' => (int) ($cfg['zipf_rows'] ?? 10),
                'zipf_chart_points' => (int) ($cfg['zipf_chart_points'] ?? 12),
                'phrases_rows' => (int) ($cfg['phrases_rows'] ?? 10),
                'cloud_text_words' => (int) ($cfg['cloud_text_words'] ?? 35),
                'compare_words_rows' => (int) ($cfg['compare_words_rows'] ?? 10),
            ],
            'result' => [
                'general' => $result['general'],
                'words' => $result['words'],
                'zipf' => $result['zipf'],
                'phrases' => $result['phrases'],
                'cloud' => $result['cloud'],
                'comparison' => $result['comparison'] ?? null,
                'locked' => self::lockedFeatures($result),
            ],
            'upgrade' => [
                'register_url' => $registerUrl,
                'login_url' => $registerBase . '/login',
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $words
     * @return array<int, array<string, mixed>>
     */
    private static function mapTopWords(array $words): array
    {
        $mapped = [];
        foreach ($words as $word) {
            $mapped[] = [
                'text' => (string) ($word['text'] ?? ''),
                'density' => $word['density'] ?? 0,
                'total' => $word['total'] ?? 0,
                'inText' => $word['inText'] ?? 0,
                'inLink' => $word['inLink'] ?? 0,
            ];
        }

        return $mapped;
    }

    /**
     * @param array<int, array<string, mixed>> $graph
     * @return array<int, array<string, mixed>>
     */
    private static function mapZipfGraph(array $graph): array
    {
        $mapped = [];
        foreach ($graph as $point) {
            $mapped[] = [
                'x' => (int) ($point['x'] ?? $point['rank'] ?? 0),
                'y' => (int) ($point['y'] ?? 0),
                'label' => (string) ($point['label'] ?? ''),
            ];
        }

        return $mapped;
    }

    /**
     * @param array<int, array<string, mixed>> $phrases
     * @return array<int, array<string, mixed>>
     */
    private static function mapPhrases(array $phrases): array
    {
        $mapped = [];
        foreach ($phrases as $row) {
            $mapped[] = [
                'phrase' => (string) ($row['phrase'] ?? ''),
                'count' => (int) ($row['count'] ?? 0),
                'density' => $row['density'] ?? 0,
            ];
        }

        return $mapped;
    }

    /**
     * @param array<int, array<string, mixed>> $cloud
     * @return array<int, array<string, mixed>>
     */
    private static function mapCloud(array $cloud): array
    {
        $mapped = [];
        foreach ($cloud as $item) {
            $mapped[] = [
                'text' => (string) ($item['text'] ?? ''),
                'weight' => (int) ($item['weight'] ?? 1),
            ];
        }

        return $mapped;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private static function mapCompareWords(array $rows): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            $main = is_array($row['main'] ?? null) ? $row['main'] : null;
            $competitor = is_array($row['competitor'] ?? null) ? $row['competitor'] : null;
            $mapped[] = [
                'text' => (string) ($row['text'] ?? ''),
                'main_total' => (int) ($main['total'] ?? 0),
                'main_density' => $main['density'] ?? 0,
                'competitor_total' => (int) ($competitor['total'] ?? 0),
                'competitor_density' => $competitor['density'] ?? 0,
                'delta_total' => (int) ($row['delta_total'] ?? 0),
            ];
        }

        return $mapped;
    }

    /**
     * @param array<string, mixed> $result
     * @return string[]
     */
    private static function lockedFeatures(array $result): array
    {
        $locked = ['cloud_links', 'cloud_both', 'export', 'word_forms', 'compare_zipf', 'compare_cloud'];
        if (empty($result['comparison'])) {
            $locked[] = 'compare';
        }

        return $locked;
    }

    /**
     * @return array{ok: false, status: int, error: string, message: string}
     */
    private static function fail(int $status, string $error, string $message): array
    {
        return ['ok' => false, 'status' => $status, 'error' => $error, 'message' => $message];
    }
}
