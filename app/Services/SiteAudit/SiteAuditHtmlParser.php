<?php

namespace App\Services\SiteAudit;

/**
 * Лёгкий extract сигналов из HTML без полной DOM-зависимости (regex + DOMDocument если есть).
 */
class SiteAuditHtmlParser
{
    public function parse(string $html, string $finalUrl, array $options = []): array
    {
        $htmlChecker = SiteAuditHtmlChecker::normalize(
            $options['html_checker'] ?? SiteAuditHtmlChecker::defaultChecker()
        );
        $titleRaws = $this->allMatches('/<title[^>]*>(.*?)<\/title>/is', $html);
        $titles = [];
        foreach ($titleRaws as $rawTitle) {
            $t = html_entity_decode(strip_tags((string) $rawTitle), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $t = trim(preg_replace('/\s+/u', ' ', $t) ?: '');
            if ($t === '') {
                continue;
            }
            $titles[] = mb_substr($t, 0, 300);
            if (count($titles) >= 8) {
                break;
            }
        }
        $title = $titles[0] ?? null;

        $descriptions = $this->metaContents($html, 'description');
        $description = $descriptions[0] ?? null;
        $descriptionSamples = [];
        foreach (array_slice($descriptions, 0, 8) as $d) {
            $d = trim(preg_replace('/\s+/u', ' ', (string) $d) ?: '');
            if ($d === '') {
                continue;
            }
            $descriptionSamples[] = mb_substr($d, 0, 400);
        }

        $robots = $this->metaContents($html, 'robots');
        $robotsMeta = $robots[0] ?? null;

        $keywordsList = $this->metaContents($html, 'keywords');
        $keywordsMeta = $keywordsList[0] ?? null;
        if ($keywordsMeta !== null) {
            $keywordsMeta = trim(preg_replace('/\s+/u', ' ', $keywordsMeta) ?: $keywordsMeta);
            if ($keywordsMeta === '') {
                $keywordsMeta = null;
            } else {
                $keywordsMeta = mb_substr($keywordsMeta, 0, 500);
            }
        }

        $canonicals = $this->canonicals($html);
        $canonical = $canonicals[0] ?? $this->canonical($html);
        $canonicalCount = max(count($canonicals), $this->canonicalCount($html));

        // Заголовки — без <script>/<style>: в статьях про SEO часто сырой пример `<h1>`
        // внутри script/JSON → regex тянет мусор до настоящего </h1>.
        $markup = $this->stripNonContentBlocks($html);
        $h1s = $this->allMatches('/<h1\b[^>]*>(.*?)<\/h1>/is', $markup);
        $h1s = array_values(array_filter(array_map(function ($h) {
            return $this->normalizeHeadingText($h);
        }, $h1s)));

        $h2s = $this->allMatches('/<h2\b[^>]*>(.*?)<\/h2>/is', $markup);
        $h2s = array_values(array_filter(array_map(function ($h) {
            return $this->normalizeHeadingText($h);
        }, $h2s)));

        $text = $this->visibleText($html);
        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $wordCount = is_array($words) ? count($words) : 0;
        $textMetrics = SiteAuditTextMetrics::analyze($text);
        $noindexInfo = SiteAuditTextMetrics::noindexInfo($html);
        $noindexText = (string) ($noindexInfo['text'] ?? '');
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
        $contacts = SiteAuditContacts::detect($text, $html);
        $signals = SiteAuditContacts::detectSignals($text);
        $pageMeta = [
            'title' => $title,
            'h1' => $h1s[0] ?? null,
        ];
        $looksCommercial = SiteAuditContacts::looksCommercial($finalUrl, $pageMeta, $text);
        $looksProduct = SiteAuditContacts::looksProductOffer($finalUrl, $pageMeta, $text);

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

        $titleCount = max(count($titles), preg_match_all('/<title\b/i', $html) ?: 0);
        $descCount = count($descriptions);

        $iframeCount = (preg_match_all('/<iframe\b/i', $html) ?: 0)
            + (preg_match_all('/<frame\b/i', $html) ?: 0);

        $doctype = null;
        if (preg_match('/<!DOCTYPE\s+([^>\[]+)/i', $html, $dm)) {
            $doctype = trim(preg_replace('/\s+/', ' ', $dm[1]));
        } elseif (preg_match('/<!DOCTYPE\s*>/i', $html)) {
            $doctype = '';
        }

        $isHttps = stripos($finalUrl, 'https://') === 0;
        $mixedSamples = $isHttps ? $this->collectMixedContentSamples($html) : [];

        $insecureForms = $isHttps ? $this->insecureFormActions($html) : [];
        $htmlErrorsBag = $this->collectHtmlErrors($html, $htmlChecker, $options);
        $htmlErrors = $htmlErrorsBag['errors'];

        $htmlCheckerEffective = $htmlErrorsBag['checker'];
        $htmlCheckerFallback = ! empty($htmlErrorsBag['fallback']);
        $headingOutline = $this->headingOutline($markup);
        $headingIssues = $this->headingHierarchyIssues($headingOutline);
        $headingsByLevel = $this->headingsByLevel($headingOutline, $h1s, $h2s);

        return [
            'title' => $title !== '' && $title !== null ? $title : null,
            'title_count' => $titleCount,
            'titles' => $titles,
            'description' => $description !== null && $description !== '' ? $description : null,
            'description_count' => $descCount,
            'descriptions' => $descriptionSamples,
            'h1' => $h1s[0] ?? null,
            'h1_count' => count($h1s),
            'h1s' => array_values(array_slice($h1s, 0, 8)),
            'h2' => $h2s[0] ?? null,
            'h2_count' => $h2Count,
            'h2s' => array_slice($h2s, 0, 20),
            'headings' => $headingsByLevel,
            'heading_outline' => $headingOutline,
            'heading_issues' => $headingIssues,
            'canonical' => $canonical,
            'canonical_count' => $canonicalCount,
            'canonicals' => $canonicals,
            'robots_meta' => $robotsMeta,
            'keywords_meta' => $keywordsMeta,
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
            'token_top' => $textMetrics['top_tokens'] ?? [],
            'noindex_text_len' => (int) ($noindexInfo['len'] ?? mb_strlen($noindexText)),
            'noindex_sample' => (string) ($noindexInfo['sample'] ?? ''),
            'noindex_links' => isset($noindexInfo['links']) && is_array($noindexInfo['links'])
                ? $noindexInfo['links']
                : [],
            'noindex_hash' => (string) ($noindexInfo['hash'] ?? ''),
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
            'html_checker' => $htmlCheckerEffective,
            'html_checker_fallback' => $htmlCheckerFallback,
            'content_risk' => $contentRisk,
            'contacts' => $contacts + $signals + [
                'commercial' => $looksCommercial,
                'product_offer' => $looksProduct,
            ],
            'simhash' => SiteAuditSimhash::fromText($text),
            'shingles' => SiteAuditSimhash::shinglesFromText($text),
            'final_url' => $finalUrl,
        ];
    }

    /**
     * Mixed content = HTTP-подресурсы на HTTPS-странице (img/script/css/iframe/…).
     * Не считаем: обычные &lt;a href&gt;, rel=canonical, form action (это insecure_form).
     *
     * @return list<string>
     */
    private function collectMixedContentSamples(string $html): array
    {
        $samples = [];
        $add = static function (string $url) use (&$samples): void {
            $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($url === '' || stripos($url, 'http://') !== 0) {
                return;
            }
            if (in_array($url, $samples, true)) {
                return;
            }
            $samples[] = $url;
        };

        // Passiveктивные/пассивные media-теги с src / data-src / poster.
        if (preg_match_all(
            '/<(?:img|script|iframe|embed|video|audio|source|track|object)\b([^>]*)>/i',
            $html,
            $tags,
            PREG_SET_ORDER
        )) {
            foreach ($tags as $tag) {
                $attrs = $tag[1] ?? '';
                foreach (['src', 'data-src', 'poster', 'data'] as $attr) {
                    if (preg_match('/\b' . $attr . '\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $attrs, $am)) {
                        $add($this->quotedAttr($am));
                    }
                }
                if (count($samples) >= 5) {
                    return $samples;
                }
            }
        }

        // <link rel="stylesheet|icon|preload|prefetch|…"> с http href — не canonical/alternate.
        if (preg_match_all('/<link\b([^>]*)>/i', $html, $links, PREG_SET_ORDER)) {
            foreach ($links as $link) {
                $attrs = $link[1] ?? '';
                $rel = '';
                if (preg_match('/\brel\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $attrs, $rm)) {
                    $rel = mb_strtolower($this->quotedAttr($rm));
                }
                if ($rel === '' || ! preg_match('/\b(stylesheet|icon|shortcut\s+icon|apple-touch-icon|preload|prefetch|modulepreload|manifest)\b/i', $rel)) {
                    continue;
                }
                if (preg_match('/\bhref\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $attrs, $hm)) {
                    $add($this->quotedAttr($hm));
                }
                if (count($samples) >= 5) {
                    return $samples;
                }
            }
        }

        return array_slice($samples, 0, 5);
    }

    /**
     * Критические ошибки разметки: эвристики + libxml или Nu (vnu).
     *
     * @param array{vnu_precomputed?:array{ok?:bool,errors?:list,error?:?string}} $options
     * @return array{
     *   errors:list<array{line:?int,level:string,message:string}>,
     *   checker:string,
     *   fallback:bool
     * }
     */
    private function collectHtmlErrors(string $html, string $checker, array $options = []): array
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

        // эвристики без DOM — для обоих режимов
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

        $fallback = false;
        $effective = SiteAuditHtmlChecker::LIBXML;

        if ($checker === SiteAuditHtmlChecker::HTML5) {
            if (isset($options['vnu_precomputed']) && is_array($options['vnu_precomputed'])) {
                $result = $options['vnu_precomputed'];
            } else {
                $vnu = new SiteAuditVnuClient();
                $result = $vnu->validate($html);
            }
            if (! empty($result['ok'])) {
                $effective = SiteAuditHtmlChecker::HTML5;
                $vnuErrors = isset($result['errors']) && is_array($result['errors'])
                    ? $result['errors']
                    : [];
                foreach ($vnuErrors as $err) {
                    if (! is_array($err)) {
                        continue;
                    }
                    $push(
                        (string) ($err['level'] ?? 'error'),
                        (string) ($err['message'] ?? ''),
                        isset($err['line']) ? (int) $err['line'] : null
                    );
                    if (count($out) >= 10) {
                        break;
                    }
                }

                return [
                    'errors' => array_slice($out, 0, 10),
                    'checker' => $effective,
                    'fallback' => false,
                ];
            }
            $fallback = true;
        }

        $this->collectLibxmlHtmlErrors($html, $push, $out);

        return [
            'errors' => array_slice($out, 0, 10),
            'checker' => $effective,
            'fallback' => $fallback,
        ];
    }

    /**
     * @param callable(string,string,?int):void $push
     * @param list<array{line:?int,level:string,message:string}> $out
     */
    private function collectLibxmlHtmlErrors(string $html, callable $push, array &$out): void
    {
        if (! class_exists(\DOMDocument::class)) {
            return;
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
            // шум HTML5 / entities: libxml HTML-парсер по таблице HTML4 → «Tag X invalid» на нормальных тегах.
            if (preg_match('/htmlParseEntityRef|htmlParseCharRef|Unexpected end tag : (html|body|head)/i', $msg)) {
                continue;
            }
            if (preg_match(
                '/Tag ('
                // semantic HTML5
                . 'nav|section|article|header|footer|main|figure|figcaption|aside|mark|time|dialog'
                . '|details|summary|picture|source|template|video|audio|canvas|track|embed|wbr|slot'
                // SVG root + common children (circle/rect часто на иконках/рейтингах)
                . '|svg|path|circle|rect|ellipse|line|polyline|polygon|g|defs|use|symbol|clippath|mask'
                . '|lineargradient|radialgradient|stop|text|tspan|title|desc|foreignobject|view|image|pattern|filter'
                . ') invalid/i',
                $msg
            )) {
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
        return count($this->canonicals($html));
    }

    /**
     * Все href из link rel=canonical (порядок как в HTML).
     *
     * @return list<string>
     */
    private function canonicals(string $html): array
    {
        if (! preg_match_all('/<link\b[^>]*>/i', $html, $tags)) {
            return [];
        }
        $out = [];
        foreach ($tags[0] as $tag) {
            if (! preg_match('/\brel\s*=\s*["\'][^"\']*\bcanonical\b[^"\']*["\']/i', $tag)) {
                continue;
            }
            if (! preg_match('/\bhref\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $tag, $hm)) {
                continue;
            }
            $href = html_entity_decode(trim($this->quotedAttr($hm)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($href !== '') {
                $out[] = $href;
            }
        }

        return $out;
    }

    /**
     * Формы на HTTPS с action=http:// — с атрибутами для поиска в HTML.
     *
     * @return list<array{action:string, id:?string, name:?string, class:?string, method:?string, snippet:string}>
     */
    private function insecureFormActions(string $html): array
    {
        if (! preg_match_all('/<form\b([^>]*)>/i', $html, $forms, PREG_SET_ORDER)) {
            return [];
        }
        $samples = [];
        foreach ($forms as $form) {
            $attrs = (string) ($form[1] ?? '');
            $openTag = trim(preg_replace('/\s+/u', ' ', (string) ($form[0] ?? '')) ?: '');
            if (! preg_match('/\baction\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $attrs, $m)) {
                continue;
            }
            $action = trim($this->quotedAttr($m));
            if ($action === '' && isset($m[4])) {
                $action = trim((string) $m[4]);
            }
            if ($action === '' || stripos($action, 'http://') !== 0) {
                continue;
            }
            $action = html_entity_decode($action, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $samples[] = [
                'action' => mb_substr($action, 0, 500),
                'id' => $this->htmlAttrValue($attrs, 'id'),
                'name' => $this->htmlAttrValue($attrs, 'name'),
                'class' => $this->htmlAttrValue($attrs, 'class'),
                'method' => $this->htmlAttrValue($attrs, 'method'),
                'snippet' => mb_substr($openTag !== '' ? $openTag : ('<form ' . trim($attrs) . '>'), 0, 220),
            ];
            if (count($samples) >= 5) {
                break;
            }
        }

        return $samples;
    }

    /**
     * Значение HTML-атрибута из строки attrs открывающего тега.
     */
    private function htmlAttrValue(string $attrs, string $name): ?string
    {
        $name = preg_quote($name, '/');
        if (! preg_match('/\b' . $name . '\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $attrs, $m)) {
            return null;
        }
        $val = '';
        if (isset($m[2]) && $m[2] !== '') {
            $val = $m[2];
        } elseif (isset($m[3]) && $m[3] !== '') {
            $val = $m[3];
        } elseif (isset($m[4])) {
            $val = $m[4];
        } elseif (isset($m[2])) {
            $val = $m[2];
        }
        $val = trim(html_entity_decode($val, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $val = trim(preg_replace('/\s+/u', ' ', $val) ?: $val);
        if ($val === '') {
            return null;
        }

        return mb_substr($val, 0, 160);
    }

    /**
     * Первые тексты h1–h6 для инвентаря страниц.
     *
     * @param  list<array{level:int, text:string}>  $outline
     * @param  list<string>  $h1s
     * @param  list<string>  $h2s
     * @return array<string, list<string>>
     */
    private function headingsByLevel(array $outline, array $h1s, array $h2s): array
    {
        $by = [
            'h1' => array_values(array_filter(array_map(function ($t) {
                return mb_substr(trim((string) $t), 0, 160);
            }, array_slice($h1s, 0, 5)))),
            'h2' => array_values(array_filter(array_map(function ($t) {
                return mb_substr(trim((string) $t), 0, 160);
            }, array_slice($h2s, 0, 8)))),
            'h3' => [],
            'h4' => [],
            'h5' => [],
            'h6' => [],
        ];
        foreach ($outline as $h) {
            $lvl = (int) ($h['level'] ?? 0);
            if ($lvl < 3 || $lvl > 6) {
                continue;
            }
            $key = 'h' . $lvl;
            if (count($by[$key]) >= 5) {
                continue;
            }
            $text = mb_substr(trim((string) ($h['text'] ?? '')), 0, 160);
            if ($text === '') {
                continue;
            }
            $by[$key][] = $text;
        }

        return $by;
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
            $text = $this->normalizeHeadingText($m[3]);
            if ($text === null || $text === '') {
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

    /** Видимый текст страницы (без script/style) — для антиплагиата и метрик. */
    public function extractVisibleText(string $html): string
    {
        return $this->visibleText($html);
    }

    /**
     * Текст для SimHash / шинголов / тошноты: без <head>, с упором на <main>/<article>,
     * иначе вырезаем header/nav/footer/aside — иначе бренд из TITLE и меню тянут «похожие».
     */
    private function visibleText(string $html): string
    {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $html) ?? $html;
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $html) ?? $html;
        $html = preg_replace('/<noscript\b[^>]*>.*?<\/noscript>/is', ' ', $html) ?? $html;
        // TITLE/meta в head — не контент: «| Blog | Prime» иначе попадает в simhash через strip_tags.
        $html = preg_replace('/<head\b[^>]*>.*?<\/head>/is', ' ', $html) ?? $html;
        $html = preg_replace('/<title\b[^>]*>.*?<\/title>/is', ' ', $html) ?? $html;

        $root = $this->contentRootHtml($html);
        if ($root !== null) {
            $html = $root;
        } else {
            for ($pass = 0; $pass < 3; $pass++) {
                $next = preg_replace('/<(header|nav|footer|aside)\b[^>]*>.*?<\/\1>/is', ' ', $html);
                if ($next === null || $next === $html) {
                    break;
                }
                $html = $next;
            }
        }

        // Иначе <a>Главная</a><a>Каталог</a> → «ГлавнаяКаталог» и ломает токенизацию.
        $html = preg_replace('/(?<=>)(?=\S)/u', ' ', $html) ?? $html;
        $html = preg_replace('/(?<=\S)(?=<)/u', ' ', $html) ?? $html;

        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * Основной контент: <main>, [role=main] или самый длинный <article>.
     */
    private function contentRootHtml(string $html): ?string
    {
        $minLen = 120;

        if (preg_match('/<main\b[^>]*>(.*?)<\/main>/is', $html, $m)) {
            $inner = $m[1];
            if (mb_strlen(strip_tags($inner)) >= $minLen) {
                return $inner;
            }
        }

        if (preg_match(
            '/<([a-z][a-z0-9]*)\b[^>]*\brole\s*=\s*["\']main["\'][^>]*>(.*?)<\/\1>/is',
            $html,
            $m
        )) {
            $inner = $m[2];
            if (mb_strlen(strip_tags($inner)) >= $minLen) {
                return $inner;
            }
        }

        if (! preg_match_all('/<article\b[^>]*>(.*?)<\/article>/is', $html, $mm) || empty($mm[1])) {
            return null;
        }

        $best = '';
        $bestLen = 0;
        foreach ($mm[1] as $chunk) {
            $len = mb_strlen(strip_tags($chunk));
            if ($len > $bestLen) {
                $best = $chunk;
                $bestLen = $len;
            }
        }

        return $bestLen >= $minLen ? $best : null;
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

    /** Убрать script/style/noscript — там не заголовки страницы. */
    private function stripNonContentBlocks(string $html): string
    {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $html) ?? $html;
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $html) ?? $html;
        $html = preg_replace('/<noscript\b[^>]*>.*?<\/noscript>/is', ' ', $html) ?? $html;

        return $html;
    }

    /** Текст заголовка: без тегов, без гигантского мусора. */
    private function normalizeHeadingText(string $raw): ?string
    {
        $text = trim(html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?: $text);
        if ($text === '') {
            return null;
        }
        // Явный мусор из JSON-LD / CSS, если что-то всё же просочилось
        if (preg_match('/\{"@type"|position\s*:\s*relative|!important/i', $text)) {
            return null;
        }
        if (mb_strlen($text) > 300) {
            $text = mb_substr($text, 0, 300);
        }

        return $text;
    }
}
