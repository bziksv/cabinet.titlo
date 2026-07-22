<?php

namespace App\Services\SiteAudit;

class SiteAuditFindingPresenter
{
    public static function severityLabel(string $severity): string
    {
        $map = [
            'critical' => 'Грубые',
            'other' => 'Прочие',
            'warning' => 'Предупреждения',
            'info' => 'Инфо',
        ];

        return $map[$severity] ?? $severity;
    }

    /** Короткая метка для дерева отчётов: (грубое), (замечание)… */
    public static function severityTag(string $severity): string
    {
        $map = [
            'critical' => 'грубое',
            'other' => 'прочие',
            'warning' => 'замечание',
            'info' => 'инфо',
        ];

        return $map[$severity] ?? $severity;
    }

    public static function metaLine(string $code, $meta): string
    {
        if (! is_array($meta) || ! $meta) {
            return '—';
        }

        switch ($code) {
            case 'duplicate_title':
            case 'duplicate_description':
                $parts = [];
                if (! empty($meta['group_size'])) {
                    $parts[] = 'в группе: ' . (int) $meta['group_size'];
                }
                if (! empty($meta['title'])) {
                    $parts[] = 'title: ' . self::clip($meta['title'], 80);
                }
                if (! empty($meta['description'])) {
                    $parts[] = 'desc: ' . self::clip($meta['description'], 80);
                }

                return $parts ? implode(' · ', $parts) : '—';

            case 'thin_content':
                return isset($meta['word_count'])
                    ? ('слов: ' . (int) $meta['word_count'] . ' (порог ' . (int) ($meta['threshold'] ?? 0) . ')')
                    : '—';

            case 'title_too_short':
            case 'title_too_long':
            case 'description_too_short':
            case 'description_too_long':
                $bits = [];
                if (isset($meta['length'])) {
                    $bits[] = 'длина: ' . (int) $meta['length'];
                }
                if (isset($meta['min'])) {
                    $bits[] = 'мин: ' . (int) $meta['min'];
                }
                if (isset($meta['max'])) {
                    $bits[] = 'макс: ' . (int) $meta['max'];
                }

                return $bits ? implode(' · ', $bits) : '—';

            case 'title_equals_h1':
                return ! empty($meta['h1']) ? ('H1: ' . self::clip($meta['h1'], 80)) : '—';

            case 'description_equals_h1':
                return ! empty($meta['h1']) ? ('H1: ' . self::clip($meta['h1'], 80)) : '—';

            case 'h1_equals_h2':
                $bits = [];
                if (! empty($meta['h1'])) {
                    $bits[] = 'H1: ' . self::clip($meta['h1'], 60);
                }
                if (! empty($meta['h2'])) {
                    $bits[] = 'H2: ' . self::clip($meta['h2'], 60);
                }

                return $bits ? implode(' · ', $bits) : '—';

            case 'too_many_strong':
                if (isset($meta['strong_count'])) {
                    $thr = isset($meta['threshold']) ? (' / порог ' . (int) $meta['threshold']) : '';

                    return 'strong/b: ' . (int) $meta['strong_count'] . $thr;
                }

                return '—';

            case 'duplicate_links':
                if (isset($meta['count'])) {
                    $sample = '';
                    if (! empty($meta['samples']) && is_array($meta['samples'])) {
                        $first = reset($meta['samples']);
                        if (is_string($first)) {
                            $sample = ' · ' . self::clip($first, 70);
                        } elseif (is_array($first) && ! empty($first['url'])) {
                            $sample = ' · ' . self::clip($first['url'], 70);
                        }
                    }

                    return 'дублей URL: ' . (int) $meta['count'] . $sample;
                }

                return '—';

            case 'external_links':
                if (isset($meta['count'])) {
                    return 'внешних: ' . (int) $meta['count'];
                }

                return '—';

            case 'meta_spam':
                $bits = [];
                if (! empty($meta['title']['word'])) {
                    $bits[] = 'title «' . self::clip($meta['title']['word'], 40) . '»×' . (int) ($meta['title']['count'] ?? 0);
                }
                if (! empty($meta['description']['word'])) {
                    $bits[] = 'desc «' . self::clip($meta['description']['word'], 40) . '»×' . (int) ($meta['description']['count'] ?? 0);
                }

                return $bits ? implode(' · ', $bits) : '—';

            case 'h1_spam':
                if (! empty($meta['word'])) {
                    return '«' . self::clip($meta['word'], 40) . '»×' . (int) ($meta['count'] ?? 0);
                }

                return '—';

            case 'text_nausea':
                $bits = [];
                if (isset($meta['nausea_classic'])) {
                    $bits[] = 'класс. ' . $meta['nausea_classic'] . '%';
                }
                if (isset($meta['nausea_academic'])) {
                    $bits[] = 'акад. ' . $meta['nausea_academic'] . '%';
                }
                if (! empty($meta['top_word'])) {
                    $bits[] = 'топ: «' . self::clip($meta['top_word'], 30) . '»×' . (int) ($meta['top_word_count'] ?? 0);
                }

                return $bits ? implode(' · ', $bits) : '—';

            case 'text_bigram_spam':
                if (! empty($meta['bigram'])) {
                    return '«' . self::clip($meta['bigram'], 50) . '»×' . (int) ($meta['count'] ?? 0)
                        . (isset($meta['density']) ? (' · ' . $meta['density'] . '%') : '');
                }

                return '—';

            case 'no_unique_images':
                return 'img: ' . (int) ($meta['img_count'] ?? 0) . ' · unique src: 0';

            case 'text_in_noindex':
                return isset($meta['noindex_text_len'])
                    ? ('символов в noindex: ' . (int) $meta['noindex_text_len'])
                    : '—';

            case 'images_without_alt':
                return isset($meta['img_without_alt'])
                    ? ('без alt: ' . (int) $meta['img_without_alt'] . ' / ' . (int) ($meta['img_count'] ?? 0))
                    : '—';

            case 'redirect':
            case 'redirect_chain_long':
                $parts = [];
                if (! empty($meta['final'])) {
                    $parts[] = '→ ' . self::clip($meta['final'], 90);
                }
                if (! empty($meta['length'])) {
                    $parts[] = 'длина: ' . (int) $meta['length'];
                } elseif (! empty($meta['chain']) && is_array($meta['chain'])) {
                    $parts[] = 'шагов: ' . count($meta['chain']);
                }

                return $parts ? implode(' · ', $parts) : '—';

            case 'http_4xx':
            case 'http_5xx':
                return isset($meta['status']) ? ('код ' . (int) $meta['status']) : '—';

            case 'page_too_large':
                $size = isset($meta['size_bytes']) ? self::formatBytes((int) $meta['size_bytes']) : null;
                $thr = isset($meta['threshold']) ? self::formatBytes((int) $meta['threshold']) : null;
                if ($size && $thr) {
                    return $size . ' (порог ' . $thr . ')';
                }

                return $size ?: '—';

            case 'canonical_foreign':
                return ! empty($meta['canonical']) ? self::clip($meta['canonical'], 100) : '—';

            case 'canonical_not_self':
                return ! empty($meta['canonical'])
                    ? ('→ ' . self::clip((string) $meta['canonical'], 100))
                    : 'canonical ≠ URL';

            case 'noindex':
                $bits = [];
                if (! empty($meta['robots'])) {
                    $bits[] = 'robots: ' . $meta['robots'];
                }
                if (! empty($meta['x_robots'])) {
                    $bits[] = 'X-Robots: ' . $meta['x_robots'];
                }

                return $bits ? implode(' · ', $bits) : '—';

            case 'robots_txt_error':
                $reason = $meta['reason'] ?? '';
                $map = [
                    'http_status' => 'HTTP ' . ($meta['status'] ?? ''),
                    'too_large' => 'файл слишком большой',
                    'empty' => 'пустой файл',
                    'bad_line' => 'битая строка' . (! empty($meta['line']) ? ' #' . $meta['line'] : ''),
                    'bad_sitemap' => 'битый Sitemap: ' . self::clip((string) ($meta['sitemap'] ?? ''), 60),
                    'fetch_failed' => 'не удалось скачать',
                ];

                return $map[$reason] ?? ($reason !== '' ? $reason : '—');

            case 'robots_txt_closed':
                return 'Disallow: / для User-agent: *';

            case 'robots_blocked':
                return 'закрыт правилом robots.txt';

            case 'sitemap_missing':
                return 'sitemap не найден';

            case 'sitemap_error':
                $reason = (string) ($meta['reason'] ?? '');
                $map = [
                    'empty' => 'пустой ответ',
                    'not_xml' => 'не XML',
                    'fetch_failed' => 'не удалось скачать',
                ];
                if (strpos($reason, 'http_') === 0) {
                    return 'HTTP ' . substr($reason, 5);
                }

                return $map[$reason] ?? ($reason !== '' ? $reason : '—');

            case 'not_in_sitemap':
                return 'нет в sitemap';

            case 'sitemap_not_crawled':
                return isset($meta['pages_limit'])
                    ? ('лимит краула: ' . (int) $meta['pages_limit'])
                    : 'не в крауле';

            case 'landing_not_in_sitemap':
                return 'посадочная · нет в sitemap';

            case 'landing_not_crawled':
                return isset($meta['pages_limit'])
                    ? ('посадочная · не в крауле · лимит ' . (int) $meta['pages_limit'])
                    : 'посадочная · не в крауле';

            case 'landing_url_changed':
                $q = trim((string) ($meta['query'] ?? ''));
                $old = self::clip((string) ($meta['old_url'] ?? ''), 60);
                $prefix = $q !== '' ? ('«' . self::clip($q, 40) . '» · ') : '';

                return $prefix . ($old !== '' ? ($old . ' → новый') : 'URL посадочной изменился');

            case 'pages_with_iframe':
                return isset($meta['iframe_count'])
                    ? ('frame/iframe: ' . (int) $meta['iframe_count'])
                    : '—';

            case 'mixed_content':
                $n = (int) ($meta['count'] ?? 0);
                $sample = '';
                if (! empty($meta['samples'][0])) {
                    $sample = ' · ' . self::clip((string) $meta['samples'][0], 70);
                }

                return $n ? ('http-ресурсов: ' . $n . $sample) : '—';

            case 'insecure_form':
                $n = (int) ($meta['count'] ?? 0);
                $sample = '';
                if (! empty($meta['samples'][0])) {
                    $sample = ' · ' . self::clip((string) $meta['samples'][0], 70);
                }

                return $n ? ('форм: ' . $n . $sample) : 'form action=http';

            case 'bad_doctype':
                if (($meta['reason'] ?? '') === 'missing') {
                    return 'DOCTYPE отсутствует';
                }

                return ! empty($meta['doctype'])
                    ? ('DOCTYPE: ' . self::clip((string) $meta['doctype'], 80))
                    : '—';

            case 'pages_with_canonical':
                return ! empty($meta['canonical']) ? self::clip((string) $meta['canonical'], 100) : '—';

            case 'similar_pages':
                $parts = [];
                if (! empty($meta['similar_url'])) {
                    $parts[] = '≈ ' . self::clip((string) $meta['similar_url'], 80);
                }
                if (isset($meta['hamming'])) {
                    $parts[] = 'hamming: ' . (int) $meta['hamming'];
                }

                return $parts ? implode(' · ', $parts) : '—';

            case 'duplicate_content':
                return isset($meta['group_size'])
                    ? ('в группе: ' . (int) $meta['group_size'])
                    : '—';

            case 'meta_nofollow':
                $bits = [];
                if (! empty($meta['robots'])) {
                    $bits[] = 'robots: ' . $meta['robots'];
                }
                if (! empty($meta['x_robots'])) {
                    $bits[] = 'X-Robots: ' . $meta['x_robots'];
                }

                return $bits ? implode(' · ', $bits) : 'nofollow';

            case 'links_nofollow':
                return isset($meta['count']) ? ('nofollow-ссылок: ' . (int) $meta['count']) : '—';

            case 'external_assets':
                $n = (int) ($meta['count'] ?? 0);
                $sample = ! empty($meta['samples'][0]) ? ' · ' . self::clip((string) $meta['samples'][0], 70) : '';

                return $n ? ('внешних: ' . $n . $sample) : '—';

            case 'soft_404':
                $bits = [];
                if (isset($meta['word_count'])) {
                    $bits[] = 'слов: ' . (int) $meta['word_count'];
                }
                if (! empty($meta['title'])) {
                    $bits[] = 'title: ' . self::clip((string) $meta['title'], 60);
                }

                return $bits ? implode(' · ', $bits) : 'soft 404';

            case 'orphan_pages':
                return 'нет входящих ссылок в крауле';

            case 'duplicate_url_variants':
                $n = (int) ($meta['count'] ?? 0);
                $sample = ! empty($meta['variants'][0]) ? ' · ' . self::clip((string) $meta['variants'][0], 50) : '';

                return $n ? ('вариантов: ' . $n . $sample) : 'дубль URL';

            case 'www_both_available':
                $bits = [];
                if (! empty($meta['apex_final'])) {
                    $bits[] = self::clip((string) $meta['apex_final'], 40);
                }
                if (! empty($meta['www_final'])) {
                    $bits[] = self::clip((string) $meta['www_final'], 40);
                }

                return $bits ? implode(' ↔ ', $bits) : 'оба зеркала доступны';

            case 'http_https_both_available':
                return ! empty($meta['http_final'])
                    ? ('http без редиректа: ' . self::clip((string) $meta['http_final'], 60))
                    : 'http открыт параллельно с https';

            case 'page_has_broken_links':
                $n = (int) ($meta['count'] ?? 0);
                $sample = ! empty($meta['samples'][0]['url'])
                    ? ' · ' . self::clip((string) $meta['samples'][0]['url'], 60)
                    : '';

                return $n ? ('битых: ' . $n . $sample) : '—';

            case 'broken_internal_link':
                $bits = [];
                if (! empty($meta['from'])) {
                    $bits[] = 'с: ' . self::clip((string) $meta['from'], 50);
                }
                if (isset($meta['status'])) {
                    $bits[] = 'HTTP ' . (int) $meta['status'];
                }

                return $bits ? implode(' · ', $bits) : 'битая ссылка';

            case 'deep_pages':
                return isset($meta['depth'])
                    ? ('глубина: ' . (int) $meta['depth'] . ' (порог ' . (int) ($meta['threshold'] ?? 0) . ')')
                    : 'глубокая страница';

            case 'site_availability':
                $bits = [];
                if (! empty($meta['root_bad'])) {
                    $bits[] = 'корень: HTTP ' . (isset($meta['root_status']) ? (int) $meta['root_status'] : '—');
                }
                if (isset($meta['fail_rate_pct'])) {
                    $bits[] = 'ошибок: ' . $meta['fail_rate_pct'] . '%';
                }
                if (isset($meta['unreachable']) || isset($meta['http_5xx'])) {
                    $bits[] = 'unreachable ' . (int) ($meta['unreachable'] ?? 0)
                        . ' / 5xx ' . (int) ($meta['http_5xx'] ?? 0);
                }

                return $bits ? implode(' · ', $bits) : 'проблемы доступности';

            case 'index_count_mismatch':
                $engine = (string) ($meta['engine'] ?? '');
                $indexed = isset($meta['indexed']) ? (int) $meta['indexed'] : null;
                $pages = isset($meta['pages_total']) ? (int) $meta['pages_total'] : null;
                $ratio = isset($meta['ratio']) ? round((float) $meta['ratio'], 2) : null;
                $bits = [];
                if ($engine !== '') {
                    $bits[] = $engine;
                }
                if ($indexed !== null && $pages !== null) {
                    $bits[] = 'индекс ' . $indexed . ' vs краул ' . $pages;
                }
                if ($ratio !== null) {
                    $bits[] = '×' . $ratio;
                }

                return $bits ? implode(' · ', $bits) : 'расхождение индекса и краула';

            case 'multiple_canonical':
                return isset($meta['count'])
                    ? ('canonical: ' . (int) $meta['count'])
                    : 'несколько canonical';

            case 'no_outbound_internal':
                return 'нет исходящих внутренних ссылок';

            case 'risky_query_params':
                $bits = [];
                if (! empty($meta['keys']) && is_array($meta['keys'])) {
                    $bits[] = 'keys: ' . implode(', ', array_slice($meta['keys'], 0, 5));
                }
                if (! empty($meta['many_keys'])) {
                    $bits[] = 'параметров: ' . (int) ($meta['key_count'] ?? 0);
                }
                if (! empty($meta['long_query'])) {
                    $bits[] = 'query ' . (int) ($meta['query_len'] ?? 0) . ' симв.';
                }

                return $bits ? implode(' · ', $bits) : 'рисковые параметры';

            case 'pagination_param':
                $bits = [];
                if (! empty($meta['pagination_keys']) && is_array($meta['pagination_keys'])) {
                    $bits[] = implode(', ', $meta['pagination_keys']);
                }
                if (! empty($meta['facet_keys']) && is_array($meta['facet_keys'])) {
                    $bits[] = 'facet: ' . implode(', ', $meta['facet_keys']);
                }
                if (! empty($meta['path_pagination'])) {
                    $bits[] = 'path pagination';
                }

                return $bits ? implode(' · ', $bits) : 'пагинация/фильтр';

            case 'missing_hsts':
                return 'нет Strict-Transport-Security';

            case 'missing_x_frame_options':
                return 'нет X-Frame-Options';

            case 'missing_x_content_type_options':
                return 'нет X-Content-Type-Options';

            case 'missing_charset':
                return 'charset не объявлен';

            case 'multiple_h1':
                return isset($meta['count']) ? ('H1: ' . (int) $meta['count']) : '—';

            case 'unreachable':
                return ! empty($meta['error']) ? self::clip($meta['error'], 120) : '—';

            default:
                return self::clip(json_encode($meta, JSON_UNESCAPED_UNICODE), 120);
        }
    }

    private static function clip(string $text, int $len): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if (mb_strlen($text) <= $len) {
            return $text;
        }

        return mb_substr($text, 0, $len - 1) . '…';
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return round($bytes / 1048576, 2) . ' MB';
    }
}
