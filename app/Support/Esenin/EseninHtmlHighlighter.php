<?php

namespace App\Support\Esenin;

use DOMDocument;
use DOMElement;

final class EseninHtmlHighlighter
{
    /** @var array<int, string> */
    private static $mergeInlineTags = [
        'em', 'i', 'strong', 'b', 'u', 's', 'strike', 'span',
    ];

    public static function isHtml(string $text): bool
    {
        return (bool) preg_match('/<[^>]+>/', $text);
    }

    /**
     * CKEditor/Word часто дают <em>слово</em><em> </em><em>слово</em>.
     * Пробельные inline-теги в contenteditable схлопываются → текст «слипается».
     * Склеиваем соседние одинаковые inline и разворачиваем теги только с пробелом.
     */
    public static function defragmentInline(string $html): string
    {
        $html = trim($html);
        if ($html === '' || ! self::isHtml($html)) {
            return $html;
        }

        $dom = self::loadDom($html);
        $root = $dom->getElementById('esenin-root');
        if (! $root instanceof DOMElement) {
            return $html;
        }

        for ($pass = 0; $pass < 12; $pass++) {
            if (! self::defragmentPass($root)) {
                break;
            }
        }

        return self::innerHtml($root);
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

        $html = self::defragmentInline($html);

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

        $html = self::defragmentInline($html);
        $dom = self::loadDom($html);
        $root = $dom->getElementById('esenin-root');
        if (! $root instanceof DOMElement) {
            return $html;
        }

        return self::innerHtml($root);
    }

    private static function defragmentPass(DOMElement $root): bool
    {
        $changed = false;
        $elements = [];
        foreach ($root->getElementsByTagName('*') as $el) {
            if ($el instanceof DOMElement) {
                $elements[] = $el;
            }
        }

        // Сначала разворачиваем inline, в которых только пробел/nbsp — иначе пробел «живёт» в отдельном теге.
        foreach ($elements as $el) {
            if (! $el->parentNode) {
                continue;
            }
            $tag = strtolower($el->tagName);
            if (! in_array($tag, self::$mergeInlineTags, true)) {
                continue;
            }
            if ($el->hasAttribute('class') || $el->hasAttribute('id') || $el->hasAttribute('style')) {
                continue;
            }
            if ($el->childNodes->length !== 1) {
                continue;
            }
            $only = $el->firstChild;
            if (! $only instanceof \DOMText) {
                continue;
            }
            $value = $only->nodeValue ?? '';
            if ($value === '' || ! preg_match('/^[\s\x{00A0}]+$/u', $value)) {
                continue;
            }
            $el->parentNode->replaceChild($only, $el);
            $changed = true;
        }

        // Потом склеиваем соседние одинаковые inline без атрибутов.
        $parents = [];
        foreach ($root->getElementsByTagName('*') as $el) {
            if ($el instanceof DOMElement && $el->parentNode) {
                $parents[spl_object_hash($el)] = $el;
            }
        }
        $parents[spl_object_hash($root)] = $root;

        foreach ($parents as $parent) {
            $child = $parent->firstChild;
            while ($child) {
                $next = $child->nextSibling;
                if (
                    $child instanceof DOMElement
                    && $next instanceof DOMElement
                    && self::canMergeInline($child, $next)
                ) {
                    while ($next->firstChild) {
                        $child->appendChild($next->firstChild);
                    }
                    $parent->removeChild($next);
                    $changed = true;
                    continue;
                }
                $child = $next;
            }
        }

        return $changed;
    }

    private static function canMergeInline(DOMElement $a, DOMElement $b): bool
    {
        $tag = strtolower($a->tagName);
        if ($tag !== strtolower($b->tagName)) {
            return false;
        }
        if (! in_array($tag, self::$mergeInlineTags, true)) {
            return false;
        }
        if ($a->hasAttributes() || $b->hasAttributes()) {
            // span/em с class/style не трогаем — могут отличаться
            if ($a->attributes->length !== $b->attributes->length) {
                return false;
            }
            foreach ($a->attributes as $attr) {
                if ($b->getAttribute($attr->name) !== $attr->value) {
                    return false;
                }
            }
        }

        return true;
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
