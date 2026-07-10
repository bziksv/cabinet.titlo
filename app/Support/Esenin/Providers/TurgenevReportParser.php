<?php

namespace App\Support\Esenin\Providers;

use App\Support\EseninTextCheckSettingsRegistry;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

final class TurgenevReportParser
{
    /** @var array<string, string> */
    private const TOKEN_BLOCK_MAP = [
        's' => 'style',
        'r' => 'readability',
        'd' => 'frequency',
        'f' => 'formality',
        'q' => 'keywords',
        'm' => 'risk',
    ];

    /**
     * @param array<string, mixed> $turgenevData
     * @param array<int, string> $blocks
     * @return array<int, string>
     */
    public static function reportTokensFromData(array $turgenevData, array $blocks = ['style', 'readability']): array
    {
        $tokens = [];

        foreach ($turgenevData['details'] ?? [] as $detail) {
            if (! is_array($detail)) {
                continue;
            }

            $block = (string) ($detail['block'] ?? '');
            if ($blocks !== [] && ! in_array($block, $blocks, true)) {
                continue;
            }

            $token = self::normalizeToken((string) ($detail['link'] ?? ''));
            if ($token !== '') {
                $tokens[] = $token;
            }
        }

        return array_values(array_unique($tokens));
    }

    /**
     * @return array<int, array{phrase: string, hint: string, block: string, rule_id: string, weight: int, severity: string, examples: array<int, string>}>
     */
    public static function parseToken(string $token): array
    {
        $html = self::fetchReportHtml($token);
        if ($html === null || $html === '') {
            return [];
        }

        return self::parseHtml($html, $token);
    }

    public static function fetchReportHtml(string $token): ?string
    {
        $token = self::normalizeToken($token);
        if ($token === '') {
            return null;
        }

        $cfg = EseninTextCheckSettingsRegistry::learningConfig();
        $baseUrl = rtrim((string) ($cfg['report_base_url'] ?? 'https://turgenev.ashmanov.com/'), '/');
        $timeout = max(5, (int) ($cfg['report_timeout'] ?? 25));

        try {
            $response = (new Client([
                'timeout' => (float) $timeout,
                'connect_timeout' => 10,
                'http_errors' => false,
                'headers' => [
                    'User-Agent' => 'TitloEseninReportParser/1.0',
                    'Accept' => 'text/html,application/xhtml+xml',
                ],
            ]))->get($baseUrl . '/?t=' . rawurlencode($token));

            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                return null;
            }

            return (string) $response->getBody();
        } catch (GuzzleException $e) {
            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $turgenevData
     * @param array<int, string> $blocks
     * @return array<int, array<string, mixed>>
     */
    public static function marksFromTurgenevData(string $plain, array $turgenevData, array $blocks = ['style', 'readability']): array
    {
        $plain = trim($plain);
        if ($plain === '') {
            return [];
        }

        $tokens = self::reportTokensFromData($turgenevData, $blocks);
        if ($tokens === []) {
            return [];
        }

        $marks = [];
        foreach ($tokens as $token) {
            $html = self::fetchReportHtml($token);
            if ($html === null || $html === '') {
                continue;
            }

            $marks = array_merge($marks, self::parseHtmlToMarks($html, $token, $plain));
        }

        return self::dedupeMarks($marks);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function parseHtmlToMarks(string $html, string $token, string $plain): array
    {
        $token = self::normalizeToken($token);
        $block = self::blockFromToken($token);
        $xHints = self::extractXHints($html);
        $spans = self::extractSpans($html);
        if ($spans === []) {
            return [];
        }

        $hintsByRule = self::hintsByRuleId($xHints);
        $marks = [];

        foreach ($spans as $span) {
            $text = trim((string) ($span['text'] ?? ''));
            if (! self::isUsefulSpanText($text)) {
                continue;
            }

            $located = self::locateInPlain($plain, $text);
            if ($located === null) {
                continue;
            }

            $ruleId = (string) ($span['rule_id'] ?? '');
            $severity = (string) ($span['severity'] ?? 'slop1');
            $hint = $hintsByRule[$ruleId] ?? 'Стилистическая проблема (Тургенев)';

            $marks[] = [
                'offset' => (int) $located['offset'],
                'length' => (int) $located['length'],
                'block' => $block,
                'variant' => $severity === 'slop2' ? 'slop2' : 'slop1',
                'hint' => $hint,
                'weight' => $severity === 'slop2' ? 2 : 1,
                'source' => 'turgenev',
                'meta' => [
                    'rule_id' => $ruleId,
                    'token' => $token,
                ],
            ];
        }

        return $marks;
    }

    /**
     * @return array<int, array{phrase: string, hint: string, block: string, rule_id: string, weight: int, severity: string, examples: array<int, string>}>
     */
    public static function parseHtml(string $html, string $token): array
    {
        $token = self::normalizeToken($token);
        $block = self::blockFromToken($token);
        $xHints = self::extractXHints($html);
        $spans = self::extractSpans($html);

        if ($xHints === []) {
            return [];
        }

        $severityByRule = [];
        $examplesByRule = [];

        foreach ($spans as $span) {
            $ruleId = (string) ($span['rule_id'] ?? '');
            if ($ruleId === '') {
                continue;
            }

            $text = trim((string) ($span['text'] ?? ''));
            if ($text !== '' && self::isUsefulSpanText($text)) {
                $examplesByRule[$ruleId][] = $text;
            }

            $severity = (string) ($span['severity'] ?? 'slop1');
            if (! isset($severityByRule[$ruleId]) || self::severityRank($severity) > self::severityRank($severityByRule[$ruleId])) {
                $severityByRule[$ruleId] = $severity;
            }
        }

        $candidates = [];
        foreach ($xHints as $ruleId => $rows) {
            if (! is_array($rows) || $rows === []) {
                continue;
            }

            $row = $rows[0];
            if (! is_array($row)) {
                continue;
            }

            $title = self::normalizePhrase((string) ($row['t'] ?? ''));
            if ($title === '' || ! self::isUsefulPhrase($title)) {
                continue;
            }

            $hint = self::cleanComment($row['c'] ?? '');
            if ($hint === '') {
                $hint = 'Стилистическая проблема (Тургенев)';
            }

            $severity = (string) ($severityByRule[$ruleId] ?? 'slop1');
            $weight = $severity === 'slop2' ? 2 : 1;
            $examples = array_values(array_unique($examplesByRule[$ruleId] ?? []));

            $candidates[] = [
                'phrase' => $title,
                'hint' => $hint,
                'block' => $block,
                'rule_id' => (string) $ruleId,
                'weight' => $weight,
                'severity' => $severity,
                'examples' => array_slice($examples, 0, 5),
            ];
        }

        return $candidates;
    }

    public static function normalizeToken(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('#(?:\?t=|^)([a-z][\w]{8,})$#i', $value, $matches)) {
            return (string) $matches[1];
        }

        return preg_match('/^[a-z][\w]{8,}$/i', $value) ? $value : '';
    }

    private static function blockFromToken(string $token): string
    {
        $prefix = strtolower(substr($token, 0, 1));

        return self::TOKEN_BLOCK_MAP[$prefix] ?? 'style';
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private static function extractXHints(string $html): array
    {
        $pos = strpos($html, 'var XHints = ');
        if ($pos === false) {
            return [];
        }

        $start = $pos + strlen('var XHints = ');
        if (! isset($html[$start]) || $html[$start] !== '{') {
            return [];
        }

        $depth = 0;
        $length = strlen($html);
        for ($i = $start; $i < $length; $i++) {
            $char = $html[$i];
            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    $json = substr($html, $start, $i - $start + 1);
                    $decoded = json_decode($json, true);

                    return is_array($decoded) ? $decoded : [];
                }
            }
        }

        return [];
    }

    /**
     * @return array<int, array{rule_id: string, text: string, severity: string}>
     */
    private static function extractSpans(string $html): array
    {
        $patterns = [
            "/<span class='([^']*)'>(.*?)<\\/span>/su",
            '/<span class="([^"]*)">(.*?)<\\/span>/su',
        ];

        $spans = [];
        foreach ($patterns as $pattern) {
            if (! preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($matches as $match) {
                $classes = preg_split('/\s+/', trim((string) ($match[1] ?? ''))) ?: [];
                if (! in_array('xhl', $classes, true)) {
                    continue;
                }

                $ruleId = '';
                foreach ($classes as $className) {
                    if (preg_match('/^xhint-(\d+-\d+)$/', $className, $ruleMatch)) {
                        $ruleId = (string) $ruleMatch[1];
                        break;
                    }
                }

                if ($ruleId === '') {
                    continue;
                }

                $severity = in_array('slop2', $classes, true) ? 'slop2' : 'slop1';
                $text = html_entity_decode(strip_tags((string) ($match[2] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $text = preg_replace('/\s+/u', ' ', trim((string) $text));

                $spans[] = [
                    'rule_id' => $ruleId,
                    'text' => $text,
                    'severity' => $severity,
                ];
            }
        }

        return $spans;
    }

    /**
     * @param mixed $comment
     */
    private static function cleanComment($comment): string
    {
        if (is_array($comment)) {
            $comment = (string) ($comment[0] ?? '');
        }

        $comment = html_entity_decode(strip_tags((string) $comment), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $comment = preg_replace('/_([^_]+)_/u', '$1', (string) $comment);
        $comment = preg_replace('/\s*&#[\w]+(\[[^\]]+\])?\s*$/u', '', (string) $comment);
        $comment = preg_replace('/\s+/u', ' ', trim((string) $comment));

        return mb_substr((string) $comment, 0, 500, 'UTF-8');
    }

    private static function normalizePhrase(string $phrase): string
    {
        $phrase = html_entity_decode(strip_tags($phrase), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $phrase = preg_replace('/\s+/u', ' ', trim($phrase));

        return mb_strtolower((string) $phrase, 'UTF-8');
    }

    private static function isUsefulPhrase(string $phrase): bool
    {
        if (mb_strlen($phrase, 'UTF-8') < 3) {
            return false;
        }

        if (! preg_match('/[\p{L}]{2,}/u', $phrase)) {
            return false;
        }

        if (preg_match('/^[\p{P}\p{S}\d\s]+$/u', $phrase)) {
            return false;
        }

        return true;
    }

    private static function isUsefulSpanText(string $text): bool
    {
        if (mb_strlen($text, 'UTF-8') < 2) {
            return false;
        }

        return (bool) preg_match('/[\p{L}]{2,}/u', $text);
    }

    private static function severityRank(string $severity): int
    {
        return $severity === 'slop2' ? 2 : 1;
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $xHints
     * @return array<string, string>
     */
    private static function hintsByRuleId(array $xHints): array
    {
        $hints = [];
        foreach ($xHints as $ruleId => $rows) {
            if (! is_array($rows) || $rows === []) {
                continue;
            }

            $row = $rows[0];
            if (! is_array($row)) {
                continue;
            }

            $hint = self::cleanComment($row['c'] ?? '');
            if ($hint === '') {
                $hint = self::cleanComment($row['t'] ?? '');
            }
            if ($hint === '') {
                $hint = 'Стилистическая проблема (Тургенев)';
            }

            $hints[(string) $ruleId] = $hint;
        }

        return $hints;
    }

    /**
     * @return array{offset: int, length: int}|null
     */
    private static function locateInPlain(string $plain, string $spanText): ?array
    {
        $spanText = preg_replace('/\s+/u', ' ', trim($spanText));
        if ($spanText === '') {
            return null;
        }

        $pos = mb_stripos($plain, $spanText, 0, 'UTF-8');
        if ($pos !== false) {
            return [
                'offset' => $pos,
                'length' => mb_strlen($spanText, 'UTF-8'),
            ];
        }

        $pattern = '/\b' . preg_replace('/\s+/u', '\\s+', preg_quote($spanText, '/')) . '\b/iu';
        if (preg_match($pattern, $plain, $matches, PREG_OFFSET_CAPTURE)) {
            $byteOffset = (int) ($matches[0][1] ?? -1);
            if ($byteOffset >= 0) {
                $offset = mb_strlen(substr($plain, 0, $byteOffset), 'UTF-8');
                $matched = (string) ($matches[0][0] ?? '');

                return [
                    'offset' => $offset,
                    'length' => mb_strlen($matched, 'UTF-8'),
                ];
            }
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $marks
     * @return array<int, array<string, mixed>>
     */
    private static function dedupeMarks(array $marks): array
    {
        if ($marks === []) {
            return [];
        }

        usort($marks, static function ($a, $b) {
            $severityA = (($a['variant'] ?? '') === 'slop2') ? 2 : 1;
            $severityB = (($b['variant'] ?? '') === 'slop2') ? 2 : 1;

            return ($severityB <=> $severityA)
                ?: ((int) ($b['length'] ?? 0) <=> (int) ($a['length'] ?? 0))
                ?: ((int) ($a['offset'] ?? 0) <=> (int) ($b['offset'] ?? 0));
        });

        $occupied = [];
        $accepted = [];
        foreach ($marks as $mark) {
            $start = (int) ($mark['offset'] ?? 0);
            $end = $start + (int) ($mark['length'] ?? 0);
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

        return $accepted;
    }
}
