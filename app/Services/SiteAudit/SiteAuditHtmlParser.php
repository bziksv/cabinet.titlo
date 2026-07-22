<?php

namespace App\Services\SiteAudit;

/**
 * Лёгкий extract сигналов из HTML без полной DOM-зависимости (regex + DOMDocument если есть).
 */
class SiteAuditHtmlParser
{
    public function parse(string $html, string $finalUrl): array
    {
        $title = $this->firstMatch('/<title[^>]*>(.*?)<\/title>/is', $html);
        $title = $title !== null ? html_entity_decode(strip_tags($title), ENT_QUOTES | ENT_HTML5, 'UTF-8') : null;
        $title = $title !== null ? trim(preg_replace('/\s+/u', ' ', $title)) : null;

        $descriptions = $this->metaContents($html, 'description');
        $description = $descriptions[0] ?? null;

        $robots = $this->metaContents($html, 'robots');
        $robotsMeta = $robots[0] ?? null;

        $canonical = $this->canonical($html);
        $canonicalCount = $this->canonicalCount($html);
        $h1s = $this->allMatches('/<h1\b[^>]*>(.*?)<\/h1>/is', $html);
        $h1s = array_map(function ($h) {
            return trim(html_entity_decode(strip_tags($h), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }, $h1s);

        $h2s = $this->allMatches('/<h2\b[^>]*>(.*?)<\/h2>/is', $html);
        $h2s = array_map(function ($h) {
            return trim(html_entity_decode(strip_tags($h), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }, $h2s);
        $h2s = array_values(array_filter($h2s, function ($h) {
            return $h !== '';
        }));

        $text = $this->visibleText($html);
        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $wordCount = is_array($words) ? count($words) : 0;
        $textMetrics = SiteAuditTextMetrics::analyze($text);
        $noindexText = SiteAuditTextMetrics::noindexText($html);
        $contentRisk = config('site_audit.content_risk_enabled', true)
            ? SiteAuditContentRisk::analyze($text)
            : [
                'adult' => false,
                'adult_score' => 0,
                'adult_hits' => [],
                'negative' => false,
                'negative_score' => 0,
                'negative_hits' => [],
                'word_repeat' => false,
                'word_repeat_samples' => [],
            ];
        $contacts = SiteAuditContacts::detect($text);
        $signals = SiteAuditContacts::detectSignals($text);
        $looksCommercial = SiteAuditContacts::looksCommercial($finalUrl, [
            'title' => $title,
            'h1' => $h1s[0] ?? null,
        ], $text);

        $imgCount = preg_match_all('/<img\b/i', $html) ?: 0;
        $imgWithoutAlt = 0;
        $imgSrcs = [];
        if (preg_match_all('/<img\b([^>]*)>/i', $html, $imgTags)) {
            foreach ($imgTags[1] as $attrs) {
                if (! preg_match('/\balt\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $attrs, $m)) {
                    $imgWithoutAlt++;
                } elseif (($m[2] ?? $m[3] ?? '') === '') {
                    $imgWithoutAlt++;
                }
                if (preg_match('/\bsrc\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $attrs, $sm)) {
                    $src = trim($this->quotedAttr($sm));
                    if ($src !== '') {
                        $imgSrcs[$src] = true;
                    }
                }
            }
        }

        $h2Count = count($h2s);
        $strongCount = (preg_match_all('/<strong\b/i', $html) ?: 0)
            + (preg_match_all('/<b\b/i', $html) ?: 0);
        $emCount = (preg_match_all('/<em\b/i', $html) ?: 0)
            + (preg_match_all('/<i\b(?![a-z])/i', $html) ?: 0);

        $charset = null;
        if (preg_match('/<meta\b[^>]*\bcharset\s*=\s*["\']?\s*([a-z0-9_-]+)/i', $html, $cm)) {
            $charset = strtolower($cm[1]);
        } elseif (preg_match('/<meta\b[^>]*http-equiv\s*=\s*["\']content-type["\'][^>]*content\s*=\s*["\'][^"\']*charset\s*=\s*([a-z0-9_-]+)/i', $html, $cm2)) {
            $charset = strtolower($cm2[1]);
        }

        $noindex = false;
        if ($robotsMeta && preg_match('/\bnoindex\b/i', $robotsMeta)) {
            $noindex = true;
        }

        $titleCount = preg_match_all('/<title\b/i', $html) ?: 0;
        $descCount = count($descriptions);

        $iframeCount = (preg_match_all('/<iframe\b/i', $html) ?: 0)
            + (preg_match_all('/<frame\b/i', $html) ?: 0);

        $doctype = null;
        if (preg_match('/<!DOCTYPE\s+([^>\[]+)/i', $html, $dm)) {
            $doctype = trim(preg_replace('/\s+/', ' ', $dm[1]));
        } elseif (preg_match('/<!DOCTYPE\s*>/i', $html)) {
            $doctype = '';
        }

        $mixedSamples = [];
        $isHttps = stripos($finalUrl, 'https://') === 0;
        if ($isHttps && preg_match_all(
            '/\b(?:src|href|action|data-src)\s*=\s*["\'](http:\/\/[^"\']+)["\']/i',
            $html,
            $mm
        )) {
            foreach ($mm[1] as $httpUrl) {
                // игнор localhost / schema-relative уже отсечены
                if (stripos($httpUrl, 'http://') !== 0) {
                    continue;
                }
                $mixedSamples[] = $httpUrl;
                if (count($mixedSamples) >= 5) {
                    break;
                }
            }
        }

        $insecureForms = $isHttps ? $this->insecureFormActions($html) : [];
        $htmlErrors = $this->collectHtmlErrors($html);
        $headingOutline = $this->headingOutline($html);
        $headingIssues = $this->headingHierarchyIssues($headingOutline);

        return [
            'title' => $title !== '' ? $title : null,
            'title_count' => $titleCount,
            'description' => $description !== null && $description !== '' ? $description : null,
            'description_count' => $descCount,
            'h1' => $h1s[0] ?? null,
            'h1_count' => count($h1s),
            'h2' => $h2s[0] ?? null,
            'h2_count' => $h2Count,
            'h2s' => array_slice($h2s, 0, 20),
            'heading_outline' => $headingOutline,
            'heading_issues' => $headingIssues,
            'canonical' => $canonical,
            'canonical_count' => $canonicalCount,
            'robots_meta' => $robotsMeta,
            'noindex' => $noindex,
            'word_count' => $wordCount,
            'text_len' => mb_strlen($text),
            'content_hash' => hash('sha256', mb_strtolower($text)),
            'nausea_classic' => $textMetrics['nausea_classic'],
            'nausea_academic' => $textMetrics['nausea_academic'],
            'top_word' => $textMetrics['top_word'],
            'top_word_count' => $textMetrics['top_word_count'],
            'top_bigram' => $textMetrics['top_bigram'],
            'top_bigram_count' => $textMetrics['top_bigram_count'],
            'top_trigram' => $textMetrics['top_trigram'],
            'top_trigram_count' => $textMetrics['top_trigram_count'],
            'noindex_text_len' => mb_strlen($noindexText),
            'img_count' => $imgCount,
            'img_without_alt' => $imgWithoutAlt,
            'unique_img_src_count' => count($imgSrcs),
            'strong_count' => $strongCount,
            'em_count' => $emCount,
            'charset' => $charset,
            'iframe_count' => $iframeCount,
            'doctype' => $doctype,
            'has_doctype' => $doctype !== null,
            'mixed_content_count' => count($mixedSamples),
            'mixed_content_samples' => $mixedSamples,
            'insecure_form_count' => count($insecureForms),
            'insecure_form_samples' => $insecureForms,
            'html_error_count' => count($htmlErrors),
            'html_error_samples' => $htmlErrors,
            'content_risk' => $contentRisk,
            'contacts' => $contacts + $signals + ['commercial' => $looksCommercial],
            'simhash' => SiteAuditSimhash::fromText($text),
            'final_url' => $finalUrl,
        ];
    }

    /**
     * Критические ошибки разметки: libxml ERROR/FATAL + грубые эвристики (сэмпл).
     *
     * @return list<array{line:?int, level:string, message:string}>
     */
    private function collectHtmlErrors(string $html): array
    {
        $out = [];
        $push = function (string $level, string $message, ?int $line = null) use (&$out) {
            $message = trim(preg_replace('/\s+/', ' ', $message) ?: $message);
            if ($message === '') {
                return;
            }
            $key = mb_strtolower($message);
            foreach ($out as $row) {
                if (mb_strtolower($row['message']) === $key) {
                    return;
                }
            }
            $out[] = [
                'line' => $line,
                'level' => $level,
                'message' => mb_substr($message, 0, 200),
            ];
        };

        // эвристики без DOM
        if (substr_count(mb_strtolower($html), '</html>') > 1) {
            $push('error', 'Несколько закрывающих тегов </html>');
        }
        if (substr_count(mb_strtolower($html), '</body>') > 1) {
            $push('error', 'Несколько закрывающих тегов </body>');
        }
        $openComments = preg_match_all('/<!--/', $html) ?: 0;
        $closeComments = preg_match_all('/-->/', $html) ?: 0;
        if ($openComments > $closeComments) {
            $push('error', 'Незакрытый HTML-комментарий <!--');
        }

        if (! class_exists(\DOMDocument::class)) {
            return array_slice($out, 0, 10);
        }

        $prev = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $dom = new \DOMDocument();
        $wrapped = '<?xml encoding="UTF-8">' . $html;
        @$dom->loadHTML($wrapped);
        foreach (libxml_get_errors() as $err) {
            if ((int) $err->level < LIBXML_ERR_ERROR) {
                continue;
            }
            $msg = trim($err->message);
            // шум HTML5 / entities
            if (preg_match('/htmlParseEntityRef|htmlParseCharRef|Unexpected end tag : (html|body|head)/i', $msg)) {
                continue;
            }
            if (preg_match('/Tag (nav|section|article|header|footer|main|figure|figcaption|aside|svg|path|source|picture|template) invalid/i', $msg)) {
                continue;
            }
            $level = ((int) $err->level >= LIBXML_ERR_FATAL) ? 'fatal' : 'error';
            $push($level, $msg, $err->line > 0 ? (int) $err->line : null);
            if (count($out) >= 10) {
                break;
            }
        }
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        return array_slice($out, 0, 10);
    }

    private function metaContents(string $html, string $name): array
    {
        $out = [];
        $pattern = '/<meta\b[^>]*\bname\s*=\s*["\']' . preg_quote($name, '/') . '["\'][^>]*>/i';
        if (! preg_match_all($pattern, $html, $tags)) {
            return $out;
        }
        foreach ($tags[0] as $tag) {
            if (preg_match('/\bcontent\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $tag, $m)) {
                $out[] = html_entity_decode(trim($this->quotedAttr($m)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        return $out;
    }

    private function canonical(string $html): ?string
    {
        if (! preg_match('/<link\b[^>]*\brel\s*=\s*["\']canonical["\'][^>]*>/i', $html, $m)) {
            if (! preg_match('/<link\b[^>]*\brel\s*=\s*["\']canonical["\'][^>]*>/i', str_replace("\n", ' ', $html), $m)) {
                // try reverse attr order
                if (! preg_match('/<link\b[^>]*\bhref\s*=\s*("([^"]*)"|\'([^\']*)\')[^>]*\brel\s*=\s*["\']canonical["\']/i', $html, $m2)) {
                    return null;
                }
                return html_entity_decode(trim($this->quotedAttr($m2)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }
        $tag = $m[0];
        if (preg_match('/\bhref\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $tag, $hm)) {
            return html_entity_decode(trim($this->quotedAttr($hm)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return null;
    }

    private function canonicalCount(string $html): int
    {
        if (! preg_match_all('/<link\b[^>]*>/i', $html, $tags)) {
            return 0;
        }
        $n = 0;
        foreach ($tags[0] as $tag) {
            if (preg_match('/\brel\s*=\s*["\'][^"\']*\bcanonical\b[^"\']*["\']/i', $tag)) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * @return list<string>
     */
    private function insecureFormActions(string $html): array
    {
        if (! preg_match_all('/<form\b([^>]*)>/i', $html, $forms)) {
            return [];
        }
        $samples = [];
        foreach ($forms[1] as $attrs) {
            if (! preg_match('/\baction\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $attrs, $m)) {
                continue;
            }
            $action = trim($this->quotedAttr($m));
            if ($action === '' || stripos($action, 'http://') !== 0) {
                continue;
            }
            $samples[] = $action;
            if (count($samples) >= 5) {
                break;
            }
        }

        return $samples;
    }

    /**
     * Порядок заголовков h1–h6 на странице (cap).
     *
     * @return list<array{level:int, text:string}>
     */
    private function headingOutline(string $html): array
    {
        $out = [];
        if (! preg_match_all('/<(h([1-6]))\b[^>]*>(.*?)<\/\1>/is', $html, $matches, PREG_SET_ORDER)) {
            return $out;
        }
        foreach ($matches as $m) {
            $text = trim(html_entity_decode(strip_tags($m[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $text = trim(preg_replace('/\s+/u', ' ', $text) ?: $text);
            if ($text === '') {
                continue;
            }
            $out[] = [
                'level' => (int) $m[2],
                'text' => mb_substr($text, 0, 120),
            ];
            if (count($out) >= 40) {
                break;
            }
        }

        return $out;
    }

    /**
     * Проблемы иерархии: заголовок до первого H1; пропуск уровня (H1→H3).
     *
     * @param  list<array{level:int, text:string}>  $outline
     * @return list<array{type:string, level?:int, from?:int, to?:int, text:string}>
     */
    private function headingHierarchyIssues(array $outline): array
    {
        $issues = [];
        $seenH1 = false;
        $prev = null;
        foreach ($outline as $h) {
            $lvl = (int) ($h['level'] ?? 0);
            $text = (string) ($h['text'] ?? '');
            if ($lvl < 1 || $lvl > 6) {
                continue;
            }
            if (! $seenH1 && $lvl > 1) {
                $issues[] = [
                    'type' => 'before_h1',
                    'level' => $lvl,
                    'text' => $text,
                ];
            }
            if ($lvl === 1) {
                $seenH1 = true;
            }
            if ($prev !== null && $lvl > $prev + 1) {
                $issues[] = [
                    'type' => 'skip',
                    'from' => $prev,
                    'to' => $lvl,
                    'text' => $text,
                ];
            }
            $prev = $lvl;
            if (count($issues) >= 8) {
                break;
            }
        }

        return $issues;
    }

    private function visibleText(string $html): string
    {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $html);
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $html);
        $html = preg_replace('/<noscript\b[^>]*>.*?<\/noscript>/is', ' ', $html);
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * Группа 2 = double-quoted, группа 3 = single-quoted.
     * Нельзя писать `$m[2] !== '' ? $m[2] : $m[3]`: при пустом `src=""` offset 3 не задан.
     */
    private function quotedAttr(array $m): string
    {
        if (isset($m[2]) && $m[2] !== '') {
            return $m[2];
        }
        if (isset($m[3])) {
            return $m[3];
        }
        if (isset($m[2])) {
            return $m[2];
        }

        return '';
    }

    private function firstMatch(string $pattern, string $html): ?string
    {
        if (preg_match($pattern, $html, $m)) {
            return $m[1];
        }

        return null;
    }

    private function allMatches(string $pattern, string $html): array
    {
        if (! preg_match_all($pattern, $html, $m)) {
            return [];
        }

        return $m[1];
    }
}
