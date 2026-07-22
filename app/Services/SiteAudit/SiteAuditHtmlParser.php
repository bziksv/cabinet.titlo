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
            'simhash' => SiteAuditSimhash::fromText($text),
            'final_url' => $finalUrl,
        ];
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
