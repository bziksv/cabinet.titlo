<?php

namespace App\Services\SiteAudit;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Фильтры отчёта Site Audit: URL всегда + смежные поля (title и т.п.) по типу отчёта.
 * «Умный» = учитывает ввод в другой раскладке (йцукен ↔ qwerty).
 */
class SiteAuditReportFilter
{
    /** @var array<string,string> */
    private static $flipMap;

    /**
     * @return array<int,array{key:string,label:string,param:string}>
     */
    public static function fieldsForCode(string $code): array
    {
        $fields = [
            ['key' => 'url', 'label' => 'URL', 'param' => 'q_url'],
        ];

        $extra = self::extraKeysForCode($code);
        $labels = [
            'title' => 'Title',
            'description' => 'Description',
            'h1' => 'H1',
            'canonical' => 'Canonical',
            'details' => 'Детали',
        ];

        foreach ($extra as $key) {
            $fields[] = [
                'key' => $key,
                'label' => $labels[$key] ?? $key,
                'param' => 'q_' . $key,
            ];
        }

        return $fields;
    }

    /**
     * @return string[]
     */
    public static function extraKeysForCode(string $code): array
    {
        $map = [
            'duplicate_title' => ['title'],
            'empty_title' => ['title'],
            'title_too_short' => ['title'],
            'title_too_long' => ['title'],
            'title_equals_h1' => ['title', 'h1'],
            'title_equals_description' => ['title', 'description'],
            'description_equals_h1' => ['description', 'h1'],
            'h1_equals_h2' => ['h1'],
            'heading_hierarchy' => ['details'],
            'soft_404' => ['title'],
            'duplicate_description' => ['description'],
            'empty_description' => ['description'],
            'description_too_short' => ['description'],
            'description_too_long' => ['description'],
            'missing_h1' => ['h1'],
            'multiple_h1' => ['h1'],
            'duplicate_links' => ['details'],
            'external_links' => ['details'],
            'meta_spam' => ['title', 'description'],
            'h1_spam' => ['h1'],
            'text_nausea' => ['details'],
            'text_bigram_spam' => ['details'],
            'text_trigram_spam' => ['details'],
            'text_in_noindex' => ['details'],
            'canonical_empty' => ['canonical'],
            'canonical_foreign' => ['canonical'],
            'canonical_not_self' => ['canonical'],
            'pages_with_canonical' => ['canonical'],
            'multiple_canonical' => ['canonical'],
            'insecure_form' => ['details'],
            'redirect' => ['details'],
            'redirect_chain_long' => ['details'],
            'broken_internal_link' => ['details'],
            'page_has_broken_links' => ['details'],
            'page_has_bad_links' => ['details'],
            'html_critical_errors' => ['details'],
            'lost_file' => ['details'],
            'adult_content' => ['details'],
            'negative_content' => ['details'],
            'word_repeat_in_sentence' => ['details'],
            'landing_plagiarism_suspect' => ['details'],
            'landing_no_inbound_internal' => ['details'],
            'keyword_cannibalization' => ['details'],
            'ad_cannibalization' => ['details'],
            'landing_query_mismatch' => ['details'],
            'commercial_missing_contacts' => ['details'],
            'commercial_missing_price' => ['details'],
            'commercial_missing_cta' => ['details'],
            'commercial_missing_delivery' => ['details'],
            'commercial_missing_payment' => ['details'],
            'commercial_missing_stock' => ['details'],
            'commercial_missing_reviews' => ['details'],
            'broken_image' => ['details'],
            'heavy_image' => ['details'],
            'error_spike' => ['details'],
            'psi_mobile' => ['details'],
            'psi_desktop' => ['details'],
            'similar_pages' => ['details'],
            'duplicate_url_variants' => ['details'],
            'site_availability' => ['details'],
            'index_count_mismatch' => ['details'],
            'no_outbound_internal' => ['details'],
            'risky_query_params' => ['details'],
            'pagination_param' => ['details'],
            'missing_hsts' => ['details'],
            'missing_x_frame_options' => ['details'],
            'missing_x_content_type_options' => ['details'],
            'serp_not_indexed' => ['details'],
            'serp_snippet_source' => ['details'],
            'probable_affiliate' => ['details'],
            'missing_csp' => ['details'],
            'missing_referrer_policy' => ['details'],
            'missing_permissions_policy' => ['details'],
            'missing_coop' => ['details'],
            'missing_coep' => ['details'],
            'missing_corp' => ['details'],
            'missing_charset' => ['details'],
        ];

        return $map[$code] ?? [];
    }

    /**
     * @return array<string,string> key => value
     */
    public static function valuesFromRequest(Request $request, string $code): array
    {
        $out = [];
        foreach (self::fieldsForCode($code) as $field) {
            $v = trim((string) $request->input($field['param'], ''));
            if ($v !== '') {
                $out[$field['key']] = $v;
            }
        }

        return $out;
    }

    public static function hasActive(array $values): bool
    {
        return $values !== [];
    }

    /**
     * Фильтр findings (+ join pages при title/description/h1/canonical).
     */
    public static function applyToFindings(Builder $query, int $crawlId, array $values): Builder
    {
        if (isset($values['url'])) {
            self::applySmartLike($query, 'site_audit_findings.url', $values['url']);
        }

        $pageCols = ['title', 'description', 'h1', 'canonical'];
        foreach ($pageCols as $col) {
            if (! isset($values[$col])) {
                continue;
            }
            $term = $values[$col];
            $query->whereExists(function ($sub) use ($crawlId, $col, $term) {
                $sub->selectRaw('1')
                    ->from('site_audit_pages')
                    ->whereColumn('site_audit_pages.url_hash', 'site_audit_findings.url_hash')
                    ->where('site_audit_pages.crawl_id', $crawlId);
                self::applySmartLike($sub, 'site_audit_pages.' . $col, $term);
            });
        }

        if (isset($values['details'])) {
            // meta_json + куски meta для удобства
            self::applySmartLike($query, 'site_audit_findings.meta_json', $values['details']);
        }

        return $query;
    }

    /**
     * Фильтр pages (canonical report и т.п.).
     */
    public static function applyToPages(Builder $query, array $values): Builder
    {
        if (isset($values['url'])) {
            self::applySmartLike($query, 'site_audit_pages.url', $values['url']);
        }
        foreach (['title', 'description', 'h1', 'canonical'] as $col) {
            if (isset($values[$col])) {
                self::applySmartLike($query, 'site_audit_pages.' . $col, $values[$col]);
            }
        }

        return $query;
    }

    /**
     * Query-string для ссылок CSV/пагинации.
     *
     * @return array<string,string>
     */
    public static function queryParams(array $values): array
    {
        $params = [];
        foreach ($values as $key => $val) {
            $params['q_' . $key] = $val;
        }

        return $params;
    }

    public static function applySmartLike($query, string $column, string $term): void
    {
        $needles = self::needles($term);
        if ($needles === []) {
            return;
        }

        $query->where(function ($q) use ($column, $needles) {
            foreach ($needles as $needle) {
                $q->orWhere($column, 'like', '%' . self::escapeLike($needle) . '%');
            }
        });
    }

    /**
     * @return string[]
     */
    public static function needles(string $term): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        $flipped = self::flipLayout($term);
        $out = [$term];
        if ($flipped !== '' && mb_strtolower($flipped) !== mb_strtolower($term)) {
            $out[] = $flipped;
        }

        return array_values(array_unique($out));
    }

    public static function flipLayout(string $text): string
    {
        $map = self::flipMap();
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $out = '';
        foreach ($chars as $ch) {
            $out .= $map[$ch] ?? $ch;
        }

        return $out;
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * @return array<string,string>
     */
    private static function flipMap(): array
    {
        if (self::$flipMap !== null) {
            return self::$flipMap;
        }

        $pairs = [
            ['`', 'ё'], ['q', 'й'], ['w', 'ц'], ['e', 'у'], ['r', 'к'], ['t', 'е'],
            ['y', 'н'], ['u', 'г'], ['i', 'ш'], ['o', 'щ'], ['p', 'з'], ['[', 'х'],
            [']', 'ъ'], ['a', 'ф'], ['s', 'ы'], ['d', 'в'], ['f', 'а'], ['g', 'п'],
            ['h', 'р'], ['j', 'о'], ['k', 'л'], ['l', 'д'], [';', 'ж'], ["'", 'э'],
            ['z', 'я'], ['x', 'ч'], ['c', 'с'], ['v', 'м'], ['b', 'и'], ['n', 'т'],
            ['m', 'ь'], [',', 'б'], ['.', 'ю'], ['/', '.'],
            ['~', 'Ё'], ['Q', 'Й'], ['W', 'Ц'], ['E', 'У'], ['R', 'К'], ['T', 'Е'],
            ['Y', 'Н'], ['U', 'Г'], ['I', 'Ш'], ['O', 'Щ'], ['P', 'З'], ['{', 'Х'],
            ['}', 'Ъ'], ['A', 'Ф'], ['S', 'Ы'], ['D', 'В'], ['F', 'А'], ['G', 'П'],
            ['H', 'Р'], ['J', 'О'], ['K', 'Л'], ['L', 'Д'], [':', 'Ж'], ['"', 'Э'],
            ['Z', 'Я'], ['X', 'Ч'], ['C', 'С'], ['V', 'М'], ['B', 'И'], ['N', 'Т'],
            ['M', 'Ь'], ['<', 'Б'], ['>', 'Ю'], ['?', ','],
        ];

        $map = [];
        foreach ($pairs as $pair) {
            $map[$pair[0]] = $pair[1];
            $map[$pair[1]] = $pair[0];
        }
        self::$flipMap = $map;

        return self::$flipMap;
    }
}
