<?php

namespace App\Services\SiteAudit;

use App\SiteAuditPage;

/**
 * Замечания, которые считаются по полям одной страницы (без сквозного сравнения URL).
 * Нужны и в process() (кнопка «Проверить»), и в aggregate emitFromPages.
 */
class SiteAuditPageLocalFindings
{
    /**
     * Коды, которые этот билдер умеет выставлять/снимать при перекачке одной страницы.
     *
     * @return string[]
     */
    public static function codes(): array
    {
        return [
            'thin_content',
            'title_too_short',
            'title_too_long',
            'description_too_short',
            'description_too_long',
            'title_equals_h1',
            'title_equals_description',
            'description_equals_h1',
            'too_many_strong',
            'images_without_alt',
            'no_unique_images',
            'meta_spam',
            'h1_spam',
            'text_nausea',
            'text_bigram_spam',
            'text_trigram_spam',
            'text_in_noindex',
            'soft_404',
            'no_outbound_internal',
            'risky_query_params',
            'pagination_param',
        ];
    }

    /**
     * @param  array<string,mixed>  $page  поля как у site_audit_pages (+ status_code, url)
     * @param  array<string,true>  $chromeBigrams
     * @param  array<string,true>  $chromeTrigrams
     * @return list<array{code:string,meta:array<string,mixed>}>
     */
    public static function build(array $page, array $chromeBigrams = [], array $chromeTrigrams = []): array
    {
        $status = isset($page['status_code']) ? (int) $page['status_code'] : 0;
        if ($status < 200 || $status >= 400) {
            return [];
        }
        $url = (string) ($page['url'] ?? '');
        $contentType = $page['content_type'] ?? null;
        if ($url !== '' && ! SiteAuditUrlNormalizer::isHtmlDocument($contentType, $url)) {
            return [];
        }

        $out = [];
        $thin = (int) config('site_audit.thin_words', 150);
        $titleMin = (int) config('site_audit.title_min', 30);
        $titleMax = (int) config('site_audit.title_max', 70);
        $descMin = (int) config('site_audit.description_min', 70);
        $descMax = (int) config('site_audit.description_max', 160);
        $ngramMinWords = max(1, (int) config('site_audit.ngram_spam_min_words', 40));

        $wordCount = $page['word_count'] ?? null;
        if ($wordCount !== null && (int) $wordCount > 0 && (int) $wordCount < $thin) {
            $out[] = self::item('thin_content', [
                'word_count' => (int) $wordCount,
                'threshold' => $thin,
            ]);
        }

        $title = isset($page['title']) ? (string) $page['title'] : '';
        if ($title !== '') {
            $len = mb_strlen($title);
            if ($len < $titleMin) {
                $out[] = self::item('title_too_short', [
                    'length' => $len,
                    'min' => $titleMin,
                    'title' => $title,
                ]);
            } elseif ($len > $titleMax) {
                $out[] = self::item('title_too_long', [
                    'length' => $len,
                    'max' => $titleMax,
                    'title' => $title,
                ]);
            }
        }

        $description = isset($page['description']) ? (string) $page['description'] : '';
        if ($description !== '') {
            $len = mb_strlen($description);
            if ($len < $descMin) {
                $out[] = self::item('description_too_short', [
                    'length' => $len,
                    'min' => $descMin,
                    'description' => $description,
                ]);
            } elseif ($len > $descMax) {
                $out[] = self::item('description_too_long', [
                    'length' => $len,
                    'max' => $descMax,
                    'description' => $description,
                ]);
            }
        }

        $h1 = isset($page['h1']) ? (string) $page['h1'] : '';
        if ($title !== '' && $h1 !== ''
            && mb_strtolower(trim($title)) === mb_strtolower(trim($h1))) {
            $out[] = self::item('title_equals_h1', [
                'title' => $title,
                'h1' => $h1,
            ]);
        }
        if ($title !== '' && $description !== ''
            && mb_strtolower(trim($title)) === mb_strtolower(trim($description))) {
            $out[] = self::item('title_equals_description', [
                'title' => $title,
                'description' => $description,
            ]);
        }
        if ($description !== '' && $h1 !== ''
            && mb_strtolower(trim($description)) === mb_strtolower(trim($h1))) {
            $out[] = self::item('description_equals_h1', [
                'description' => $description,
                'h1' => $h1,
            ]);
        }

        $strongMax = (int) config('site_audit.strong_max', 20);
        $strongCount = (int) ($page['strong_count'] ?? 0);
        if ($strongCount > $strongMax) {
            $out[] = self::item('too_many_strong', [
                'strong_count' => $strongCount,
                'threshold' => $strongMax,
            ]);
        }

        $imgWithoutAlt = (int) ($page['img_without_alt'] ?? 0);
        if ($imgWithoutAlt > 0) {
            $missingSamples = [];
            foreach (SiteAuditImageItem::normalizeList($page['img_srcs_json'] ?? null) as $imgItem) {
                if (($imgItem['has_alt'] ?? null) !== false) {
                    continue;
                }
                $missingSamples[] = [
                    'src' => $imgItem['src'],
                    'url' => $imgItem['src'],
                    'width' => $imgItem['width'] ?? null,
                    'height' => $imgItem['height'] ?? null,
                    'loading' => $imgItem['loading'] ?? null,
                ];
                if (count($missingSamples) >= 15) {
                    break;
                }
            }
            $out[] = self::item('images_without_alt', [
                'img_without_alt' => $imgWithoutAlt,
                'img_count' => (int) ($page['img_count'] ?? 0),
                'count' => $imgWithoutAlt,
                'samples' => $missingSamples,
            ]);
        }

        $noindex = ! empty($page['noindex']);
        if ((int) ($page['unique_img_src_count'] ?? 0) === 0 && ! $noindex) {
            $imgCount = (int) ($page['img_count'] ?? 0);
            $out[] = self::item('no_unique_images', [
                'img_count' => $imgCount,
                'unique_img_src_count' => 0,
                'reason' => $imgCount > 0 ? 'no_src' : 'no_img',
            ]);
        }

        $titleSpam = SiteAuditTextMetrics::fieldSpam($title !== '' ? $title : null);
        $descSpam = SiteAuditTextMetrics::fieldSpam($description !== '' ? $description : null);
        if ($titleSpam['spam'] || $descSpam['spam']) {
            $out[] = self::item('meta_spam', [
                'title' => $titleSpam['spam'] ? [
                    'word' => $titleSpam['word'],
                    'count' => $titleSpam['count'],
                ] : null,
                'description' => $descSpam['spam'] ? [
                    'word' => $descSpam['word'],
                    'count' => $descSpam['count'],
                ] : null,
            ]);
        }

        $h1Text = trim($h1);
        $h1LooksBroken = $h1Text !== '' && (
            mb_strlen($h1Text) > 300
            || preg_match('/\{"@type"|position\s*:\s*relative|!important/i', $h1Text)
        );
        if (! $h1LooksBroken) {
            $h1Spam = SiteAuditTextMetrics::fieldSpam($h1Text !== '' ? $h1Text : null);
            if ($h1Spam['spam']) {
                $out[] = self::item('h1_spam', [
                    'word' => $h1Spam['word'],
                    'count' => $h1Spam['count'],
                    'h1' => $h1,
                ]);
            }
        }

        $nauseaClassicMax = (float) config('site_audit.nausea_classic_max', 8.0);
        $nauseaAcademicMax = (float) config('site_audit.nausea_academic_max', 25.0);
        $nauseaMinWords = max(1, (int) config('site_audit.nausea_min_words', 50));
        $classic = isset($page['nausea_classic']) && $page['nausea_classic'] !== null
            ? (float) $page['nausea_classic'] : null;
        $academic = isset($page['nausea_academic']) && $page['nausea_academic'] !== null
            ? (float) $page['nausea_academic'] : null;
        $nauseaWords = (int) ($wordCount ?? 0);
        if ($nauseaWords >= $nauseaMinWords
            && (
                ($classic !== null && $classic >= $nauseaClassicMax)
                || ($academic !== null && $academic >= $nauseaAcademicMax)
            )
        ) {
            $out[] = self::item('text_nausea', [
                'nausea_classic' => $classic,
                'nausea_academic' => $academic,
                'top_word' => $page['top_word'] ?? null,
                'top_word_count' => (int) ($page['top_word_count'] ?? 0),
                'word_count' => $nauseaWords,
                'threshold_classic' => $nauseaClassicMax,
                'threshold_academic' => $nauseaAcademicMax,
                'min_words' => $nauseaMinWords,
            ]);
        }

        $words = (int) ($wordCount ?? 0);
        $bigramMin = (int) config('site_audit.bigram_spam_min', 5);
        $bigramDensityMin = (float) config('site_audit.bigram_spam_density_min', 1.5);
        $bgCount = (int) ($page['top_bigram_count'] ?? 0);
        $bgDensity = ($words > 0 && $bgCount > 0)
            ? round(($bgCount / $words) * 100, 2)
            : 0.0;
        $topBigram = isset($page['top_bigram']) ? (string) $page['top_bigram'] : '';
        $bgKey = mb_strtolower(trim($topBigram));
        if ($words >= $ngramMinWords
            && $topBigram !== ''
            && $bgKey !== ''
            && ! SiteAuditTextMetrics::isEntityJunkNgram($bgKey)
            && ! isset($chromeBigrams[$bgKey])
            && $bgCount >= $bigramMin
            && $bgDensity >= $bigramDensityMin
        ) {
            $out[] = self::item('text_bigram_spam', [
                'bigram' => $topBigram,
                'count' => $bgCount,
                'density' => $bgDensity,
                'word_count' => $words,
                'threshold_count' => $bigramMin,
                'threshold_density' => $bigramDensityMin,
                'min_words' => $ngramMinWords,
            ]);
        }

        $trigramMin = (int) config('site_audit.trigram_spam_min', 5);
        $trigramDensityMin = (float) config('site_audit.trigram_spam_density_min', 1.0);
        $tgCount = (int) ($page['top_trigram_count'] ?? 0);
        $tgDensity = ($words > 0 && $tgCount > 0)
            ? round(($tgCount / $words) * 100, 2)
            : 0.0;
        $topTrigram = isset($page['top_trigram']) ? (string) $page['top_trigram'] : '';
        $tgKey = mb_strtolower(trim($topTrigram));
        if ($words >= $ngramMinWords
            && $topTrigram !== ''
            && $tgKey !== ''
            && ! SiteAuditTextMetrics::isEntityJunkNgram($tgKey)
            && ! isset($chromeTrigrams[$tgKey])
            && $tgCount >= $trigramMin
            && $tgDensity >= $trigramDensityMin
        ) {
            $out[] = self::item('text_trigram_spam', [
                'trigram' => $topTrigram,
                'count' => $tgCount,
                'density' => $tgDensity,
                'word_count' => $words,
                'threshold_count' => $trigramMin,
                'threshold_density' => $trigramDensityMin,
                'min_words' => $ngramMinWords,
            ]);
        }

        $noindexLinks = is_array($page['noindex_links_json'] ?? null) ? $page['noindex_links_json'] : [];
        if ((int) ($page['noindex_text_len'] ?? 0) > 0 || $noindexLinks !== []) {
            $sample = trim((string) ($page['noindex_sample'] ?? ''));
            $hash = trim((string) ($page['noindex_hash'] ?? ''));
            if ($hash === '') {
                $hash = md5(mb_strtolower($sample) . '|' . (int) ($page['noindex_text_len'] ?? 0));
            }
            $out[] = self::item('text_in_noindex', [
                'noindex_text_len' => (int) ($page['noindex_text_len'] ?? 0),
                'sample' => $sample !== '' ? $sample : null,
                'links' => $noindexLinks !== [] ? array_values(array_slice($noindexLinks, 0, 8)) : null,
                'hash' => $hash,
            ]);
        }

        $soft = self::soft404Meta($page);
        if ($soft !== null) {
            $out[] = self::item('soft_404', $soft);
        }

        $path = $url !== '' ? parse_url($url, PHP_URL_PATH) : null;
        $outs = is_array($page['out_links_json'] ?? null) ? $page['out_links_json'] : [];
        if ($path !== '/' && $path !== '' && $path !== null && $outs === []) {
            $out[] = self::item('no_outbound_internal', [
                'reason' => 'empty_out_links',
            ]);
        }

        foreach (self::urlParamRisks($url) as $item) {
            $out[] = $item;
        }

        return $out;
    }

    /**
     * N-граммы, которые top на большой доле страниц = сквозной chrome, не переспам.
     *
     * @return array{bigrams:array<string,true>,trigrams:array<string,true>}
     */
    public static function chromeNgramMaps(int $crawlId): array
    {
        return [
            'bigrams' => self::chromeNgramMap($crawlId, 'top_bigram'),
            'trigrams' => self::chromeNgramMap($crawlId, 'top_trigram'),
        ];
    }

    /**
     * @return array<string,true>
     */
    public static function chromeNgramMap(int $crawlId, string $column): array
    {
        if (! in_array($column, ['top_bigram', 'top_trigram'], true)) {
            return [];
        }
        $share = (float) config('site_audit.ngram_chrome_share', 0.20);
        $minPages = max(2, (int) config('site_audit.ngram_chrome_min_pages', 8));

        $rows = SiteAuditPage::query()
            ->where('crawl_id', $crawlId)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->get([$column]);

        $n = $rows->count();
        if ($n < $minPages) {
            return [];
        }
        $need = max($minPages, (int) ceil($share * $n));
        $df = [];
        foreach ($rows as $row) {
            $key = mb_strtolower(trim((string) ($row->{$column} ?? '')));
            if ($key === '') {
                continue;
            }
            $df[$key] = ($df[$key] ?? 0) + 1;
        }
        $out = [];
        foreach ($df as $key => $c) {
            if ($c >= $need) {
                $out[$key] = true;
            }
        }

        return $out;
    }

    /**
     * @param  array<string,mixed>  $page
     * @return array<string,mixed>|null
     */
    public static function soft404Meta(array $page): ?array
    {
        if ((int) ($page['status_code'] ?? 0) !== 200) {
            return null;
        }

        $title = trim((string) ($page['title'] ?? ''));
        $h1 = trim((string) ($page['h1'] ?? ''));
        $titleL = mb_strtolower($title);
        $h1L = mb_strtolower($h1);
        $patterns = config('site_audit.soft_404_title_patterns', [
            '404',
            'not found',
            'page not found',
            'страница не найдена',
            'не найдена',
            'ошибка 404',
        ]);

        $base = [
            'status' => 200,
            'word_count' => isset($page['word_count']) && $page['word_count'] !== null
                ? (int) $page['word_count'] : null,
            'title' => $title !== '' ? $title : null,
            'h1' => $h1 !== '' ? $h1 : null,
        ];

        foreach ($patterns as $p) {
            $p = trim((string) $p);
            if ($p === '') {
                continue;
            }
            $pL = mb_strtolower($p);
            if ($titleL !== '' && mb_strpos($titleL, $pL) !== false) {
                return array_merge($base, [
                    'reason' => 'title_pattern',
                    'pattern' => $p,
                    'matched_in' => 'title',
                ]);
            }
            if ($h1L !== '' && mb_strpos($h1L, $pL) !== false) {
                return array_merge($base, [
                    'reason' => 'h1_pattern',
                    'pattern' => $p,
                    'matched_in' => 'h1',
                ]);
            }
        }

        return null;
    }

    /**
     * @return list<array{code:string,meta:array<string,mixed>}>
     */
    private static function urlParamRisks(string $url): array
    {
        if ($url === '') {
            return [];
        }
        $riskyKeys = config('site_audit.risky_query_keys', [
            'phpsessid', 'sid', 'sessionid', 'session_id', 'jsessionid',
            'sort', 'order', 'orderby', 'sortby',
        ]);
        if (! is_array($riskyKeys)) {
            $riskyKeys = [];
        }
        $riskyKeys = array_map('strtolower', $riskyKeys);

        $paginationKeys = config('site_audit.pagination_query_keys', [
            'page', 'p', 'pagen_1', 'paged', 'offset', 'start',
        ]);
        if (! is_array($paginationKeys)) {
            $paginationKeys = [];
        }
        $paginationKeys = array_map('strtolower', $paginationKeys);

        $facetKeys = config('site_audit.facet_query_keys', [
            'filter', 'filters', 'facet', 'facets',
        ]);
        if (! is_array($facetKeys)) {
            $facetKeys = [];
        }
        $facetKeys = array_map('strtolower', $facetKeys);

        $query = parse_url($url, PHP_URL_QUERY);
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');
        $params = [];
        if (is_string($query) && $query !== '') {
            parse_str($query, $params);
        }
        $keys = array_map('strtolower', array_keys($params));
        $out = [];

        $hit = array_values(array_intersect($keys, $riskyKeys));
        $manyKeys = count($keys) >= (int) config('site_audit.risky_query_key_count', 8);
        $longQuery = is_string($query) && strlen($query) >= (int) config('site_audit.risky_query_len', 120);
        if ($hit !== [] || $manyKeys || $longQuery) {
            $out[] = self::item('risky_query_params', [
                'keys' => $hit,
                'key_count' => count($keys),
                'query_len' => is_string($query) ? strlen($query) : 0,
                'many_keys' => $manyKeys,
                'long_query' => $longQuery,
            ]);
        }

        $pagHit = array_values(array_intersect($keys, $paginationKeys));
        $facetHit = array_values(array_intersect($keys, $facetKeys));
        $pathPag = (bool) preg_match('#/(?:page|pagen)/\d+(?:/|$|\?)#i', $path)
            || (bool) preg_match('#/page-\d+(?:/|$|\?)#i', $path);
        if ($pagHit !== [] || $facetHit !== [] || $pathPag) {
            $out[] = self::item('pagination_param', [
                'pagination_keys' => $pagHit,
                'facet_keys' => $facetHit,
                'path_pagination' => $pathPag,
            ]);
        }

        return $out;
    }

    /**
     * @param  array<string,mixed>  $meta
     * @return array{code:string,meta:array<string,mixed>}
     */
    private static function item(string $code, array $meta): array
    {
        return ['code' => $code, 'meta' => $meta];
    }
}
