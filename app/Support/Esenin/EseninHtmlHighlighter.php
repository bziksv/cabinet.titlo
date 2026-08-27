<?php

namespace App\Support\Esenin;

use DOMDocument;
use DOMElement;

final class EseninHtmlHighlighter
{
    public static function isHtml(string $text): bool
    {
        return (bool) preg_match('/<[^>]+>/', $text);
    }

    /**
     * @param  array<int, array<string, mixed>>  $marks
     */
    public static function apply(string $html, string $plain, array $marks, string $block): string
    {
        $html = trim($html);
        if ($html === '' || ! self::isHtml($html)) {
            return EseninAnalyzer::renderHighlightedPlainHtml($plain, $marks, $block);
        }

        $accepted = EseninMarkAcceptor::accept($marks, $block);
        if ($accepted === []) {
            return self::sanitizeHtml($html);
        }

        // Слишком много меток на длинном HTML — оставляем самые весомые (карта DOM дорогая).
        $maxMarks = 120;
        if (count($accepted) > $maxMarks) {
            usort($accepted, static function ($a, $b) {
                return ((int) ($b['weight'] ?? 1) <=> (int) ($a['weight'] ?? 1))
                    ?: ((int) ($b['length'] ?? 0) <=> (int) ($a['length'] ?? 0));
            });
            $accepted = array_slice($accepted, 0, $maxMarks);
            usort($accepted, static function ($a, $b) {
                return ((int) ($b['offset'] ?? 0) <=> (int) ($a['offset'] ?? 0));
            });
        }

        $dom = self::loadDom($html);
        $root = $dom->getElementById('esenin-root');
        if (! $root instanceof DOMElement) {
            return EseninAnalyzer::renderHighlightedPlainHtml($plain, $marks, $block);
        }

        $index = EseninPlainIndexMap::fromDom($root);
        $domPlain = (string) ($index['plain'] ?? '');
        if ($domPlain === '') {
            return self::sanitizeHtml($html);
        }
        if ($domPlain !== $plain) {
            return EseninAnalyzer::renderHighlightedPlainHtml($plain, $marks, $block);
        }

        foreach ($accepted as $mark) {
            $index = EseninPlainIndexMap::fromDom($root);
            $domPlain = (string) ($index['plain'] ?? '');
            $map = $index['map'];
            EseninPlainIndexMap::wrapPlainRange(
                $dom,
                $root,
                $domPlain,
                (int) $mark['offset'],
                (int) $mark['length'],
                $mark,
                $map
            );
        }

        return self::innerHtml($root);
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

    private static function sanitizeHtml(string $html): string
    {
        if (! self::isHtml($html)) {
            return htmlspecialchars($html, ENT_QUOTES, 'UTF-8');
        }

        $dom = self::loadDom($html);
        $root = $dom->getElementById('esenin-root');
        if (! $root instanceof DOMElement) {
            return $html;
        }

        return self::innerHtml($root);
    }

    private static function innerHtml(DOMElement $root): string
    {
        $html = '';
        $owner = $root->ownerDocument;
        if (! $owner instanceof DOMDocument) {
            return '';
        }

        foreach ($root->childNodes as $child) {
            $html .= $owner->saveHTML($child);
        }

        return $html;
    }
}
