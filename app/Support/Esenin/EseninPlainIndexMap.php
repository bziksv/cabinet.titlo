<?php

namespace App\Support\Esenin;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

final class EseninPlainIndexMap
{
    /** @var array<int, string> */
    private static $blockTags = [
        'p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'tr', 'blockquote',
        'section', 'article', 'header', 'footer', 'main', 'aside', 'figure', 'figcaption',
        'table', 'thead', 'tbody', 'tfoot', 'ul', 'ol', 'dl', 'dt', 'dd',
    ];

    /**
     * @return array{plain: string, map: array<int, array{node: DOMText, offset: int}|null>}
     */
    public static function fromHtml(string $html): array
    {
        $dom = self::loadDom($html);
        $root = $dom->getElementById('esenin-root');
        if (! $root instanceof DOMElement) {
            return ['plain' => '', 'map' => []];
        }

        return self::fromDom($root);
    }

    /**
     * @return array{plain: string, map: array<int, array{node: DOMText, offset: int}|null>}
     */
    public static function fromDom(DOMElement $root): array
    {
        [$raw, $rawMap] = self::walk($root);

        return self::normalizeRaw($raw, $rawMap);
    }

    public static function plainFromDom(DOMElement $root): string
    {
        return self::fromDom($root)['plain'];
    }

    /**
     * @param  array<int, array{node: DOMText, offset: int}|null>  $map
     * @param  array<string, mixed>  $mark
     */
    public static function wrapPlainRange(DOMDocument $dom, DOMElement $root, string $plain, int $start, int $length, array $mark, array &$map): bool
    {
        if ($length <= 0) {
            return false;
        }

        $fragment = mb_substr($plain, $start, $length, 'UTF-8');
        if ($fragment === '') {
            return false;
        }

        $end = $start + $length;
        $segments = [];
        $current = null;

        for ($i = $start; $i < $end; $i++) {
            if (! isset($map[$i]) || ! is_array($map[$i])) {
                continue;
            }

            $entry = $map[$i];
            $node = $entry['node'];
            $offset = (int) $entry['offset'];

            if ($current !== null
                && $current['node'] === $node
                && $current['endOffset'] === $offset
            ) {
                $current['endOffset'] = $offset + 1;
            } else {
                if ($current !== null) {
                    $segments[] = $current;
                }
                $current = [
                    'node' => $node,
                    'startOffset' => $offset,
                    'endOffset' => $offset + 1,
                ];
            }
        }

        if ($current !== null) {
            $segments[] = $current;
        }

        if ($segments === []) {
            return false;
        }

        for ($i = $start; $i < $end; $i++) {
            if (! isset($map[$i]) || ! is_array($map[$i])) {
                return false;
            }
        }

        foreach (array_reverse($segments) as $segment) {
            self::wrapTextNodeSegment($dom, $segment, $mark, $map);
        }

        return true;
    }

    private static function loadDom(string $html): DOMDocument
    {
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $wrapped = '<?xml encoding="UTF-8"><div id="esenin-root">' . $html . '</div>';
        $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $dom;
    }

    /**
     * @return array{0: string, 1: array<int, array{node: DOMText, offset: int}|null>}
     */
    private static function walk(DOMElement $root): array
    {
        $raw = '';
        $rawMap = [];

        self::walkNode($root, $raw, $rawMap);

        return [$raw, $rawMap];
    }

    /**
     * @param  array<int, array{node: DOMText, offset: int}|null>  $rawMap
     */
    private static function walkNode(DOMNode $node, string &$raw, array &$rawMap): void
    {
        if ($node instanceof DOMText) {
            $text = html_entity_decode($node->nodeValue ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $length = mb_strlen($text, 'UTF-8');
            for ($i = 0; $i < $length; $i++) {
                $rawMap[mb_strlen($raw, 'UTF-8')] = ['node' => $node, 'offset' => $i];
                $raw .= mb_substr($text, $i, 1, 'UTF-8');
            }

            return;
        }

        if (! $node instanceof DOMElement) {
            return;
        }

        $tag = strtolower($node->tagName);
        if ($tag === 'mark' && $node->hasAttribute('data-esenin-mark')) {
            foreach ($node->childNodes as $child) {
                if ($child instanceof DOMElement && strpos($child->getAttribute('class'), 'esenin-mark__icon') !== false) {
                    continue;
                }
                self::walkNode($child, $raw, $rawMap);
            }

            return;
        }

        if ($tag === 'br') {
            $rawMap[mb_strlen($raw, 'UTF-8')] = null;
            $raw .= "\n";

            return;
        }

        foreach ($node->childNodes as $child) {
            self::walkNode($child, $raw, $rawMap);
        }

        if (in_array($tag, self::$blockTags, true)) {
            if ($raw !== '' && mb_substr($raw, -1, 1, 'UTF-8') !== "\n") {
                $rawMap[mb_strlen($raw, 'UTF-8')] = null;
                $raw .= "\n";
            } elseif ($raw === '') {
                // Block without text still contributes nothing before normalization.
            }
        }
    }

    /**
     * @param  array<int, array{node: DOMText, offset: int}|null>  $rawMap
     * @return array{plain: string, map: array<int, array{node: DOMText, offset: int}|null>}
     */
    private static function normalizeRaw(string $raw, array $rawMap): array
    {
        $text = preg_replace("/\r\n?/", "\n", $raw) ?? $raw;
        $textLength = mb_strlen($text, 'UTF-8');

        $plain = '';
        $map = [];
        $i = 0;

        while ($i < $textLength) {
            $char = mb_substr($text, $i, 1, 'UTF-8');

            if ($char === ' ' || $char === "\t") {
                $lastPlain = $plain === '' ? '' : mb_substr($plain, -1, 1, 'UTF-8');
                if ($plain !== '' && $lastPlain !== "\n" && $lastPlain !== ' ') {
                    $plain .= ' ';
                    $map[mb_strlen($plain, 'UTF-8') - 1] = $rawMap[$i] ?? null;
                }

                $i++;
                while ($i < $textLength) {
                    $next = mb_substr($text, $i, 1, 'UTF-8');
                    if ($next === ' ' || $next === "\t" || $next === "\r") {
                        $i++;
                        continue;
                    }
                    break;
                }

                continue;
            }

            if ($char === "\n") {
                $plain .= "\n";
                $map[mb_strlen($plain, 'UTF-8') - 1] = $rawMap[$i] ?? null;
                $i++;

                while ($i < $textLength && mb_substr($text, $i, 1, 'UTF-8') === "\n") {
                    $i++;
                }

                continue;
            }

            $plain .= $char;
            $map[mb_strlen($plain, 'UTF-8') - 1] = $rawMap[$i] ?? null;
            $i++;
        }

        $trimLeft = mb_strlen($plain, 'UTF-8') - mb_strlen(ltrim($plain), 'UTF-8');
        $plain = trim($plain);
        if ($plain === '') {
            return ['plain' => '', 'map' => []];
        }

        if ($trimLeft > 0) {
            $shifted = [];
            $plainLength = mb_strlen($plain, 'UTF-8');
            for ($j = 0; $j < $plainLength; $j++) {
                $shifted[$j] = $map[$j + $trimLeft] ?? null;
            }
            $map = $shifted;
        }

        return ['plain' => $plain, 'map' => $map];
    }

    /**
     * @param  array{node: DOMText, startOffset: int, endOffset: int}  $segment
     * @param  array<string, mixed>  $mark
     * @param  array<int, array{node: DOMText, offset: int}|null>  $map
     */
    private static function wrapTextNodeSegment(DOMDocument $dom, array $segment, array $mark, array &$map): void
    {
        $node = $segment['node'];
        if (! $node->parentNode) {
            return;
        }

        $text = html_entity_decode($node->nodeValue ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $start = max(0, (int) $segment['startOffset']);
        $end = min(mb_strlen($text, 'UTF-8'), (int) $segment['endOffset']);
        if ($end <= $start) {
            return;
        }

        $before = mb_substr($text, 0, $start, 'UTF-8');
        $middle = mb_substr($text, $start, $end - $start, 'UTF-8');
        $after = mb_substr($text, $end, null, 'UTF-8');

        $markEl = self::createMarkElement($dom, $mark);
        $middleNode = $dom->createTextNode($middle);
        $markEl->appendChild($middleNode);

        $parent = $node->parentNode;
        $next = $node->nextSibling;
        $beforeNode = null;
        $afterNode = null;

        if ($before !== '') {
            $beforeNode = $dom->createTextNode($before);
            $parent->insertBefore($beforeNode, $node);
        }

        $parent->insertBefore($markEl, $node);
        $parent->removeChild($node);

        if ($after !== '') {
            $afterNode = $dom->createTextNode($after);
            if ($next) {
                $parent->insertBefore($afterNode, $next);
            } else {
                $parent->appendChild($afterNode);
            }
        }

        // После split старые указатели на $node нужно перепривязать — иначе fromDom на каждую метку.
        foreach ($map as $plainOffset => $entry) {
            if (! is_array($entry) || ($entry['node'] ?? null) !== $node) {
                continue;
            }

            $nodeOffset = (int) ($entry['offset'] ?? -1);
            if ($nodeOffset < $start) {
                if ($beforeNode instanceof DOMText) {
                    $map[$plainOffset] = ['node' => $beforeNode, 'offset' => $nodeOffset];
                } else {
                    $map[$plainOffset] = null;
                }
            } elseif ($nodeOffset >= $end) {
                if ($afterNode instanceof DOMText) {
                    $map[$plainOffset] = ['node' => $afterNode, 'offset' => $nodeOffset - $end];
                } else {
                    $map[$plainOffset] = null;
                }
            } else {
                $map[$plainOffset] = ['node' => $middleNode, 'offset' => $nodeOffset - $start];
            }
        }
    }

    /**
     * @param  array<string, mixed>  $mark
     */
    private static function createMarkElement(DOMDocument $dom, array $mark): DOMElement
    {
        $blockName = (string) ($mark['block'] ?? 'style');
        $variant = (string) ($mark['variant'] ?? '');
        $class = 'esenin-mark esenin-mark--' . $blockName;
        if ($variant !== '') {
            $class .= ' esenin-mark--' . $blockName . '-' . $variant;
        }

        $markEl = $dom->createElement('mark');
        $markEl->setAttribute('class', $class);
        $markEl->setAttribute('data-esenin-mark', $blockName);
        $hint = (string) ($mark['hint'] ?? '');
        if ($hint !== '') {
            $markEl->setAttribute('data-esenin-tip', $hint);
        }
        $url = (string) ($mark['url'] ?? '');
        if ($url !== '') {
            $markEl->setAttribute('data-uniq-url', $url);
        }

        return $markEl;
    }
}
