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

            case 'heading_hierarchy':
                $bits = [];
                $n = (int) ($meta['issue_count'] ?? 0);
                if ($n > 0) {
                    $bits[] = 'проблем: ' . $n;
                }
                foreach (array_slice($meta['issues'] ?? [], 0, 2) as $issue) {
                    if (! is_array($issue)) {
                        continue;
                    }
                    $type = (string) ($issue['type'] ?? '');
                    if ($type === 'before_h1') {
                        $bits[] = 'до H1: H' . (int) ($issue['level'] ?? 0)
                            . (! empty($issue['text']) ? (' «' . self::clip($issue['text'], 40) . '»') : '');
                    } elseif ($type === 'skip') {
                        $bits[] = 'H' . (int) ($issue['from'] ?? 0) . '→H' . (int) ($issue['to'] ?? 0)
                            . (! empty($issue['text']) ? (' «' . self::clip($issue['text'], 40) . '»') : '');
                    }
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

            case 'text_trigram_spam':
                if (! empty($meta['trigram'])) {
                    return '«' . self::clip($meta['trigram'], 60) . '»×' . (int) ($meta['count'] ?? 0)
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

            case 'page_has_bad_links':
                $n = (int) ($meta['count'] ?? 0);
                $reason = ! empty($meta['samples'][0]['reason'])
                    ? (string) $meta['samples'][0]['reason']
                    : '';
                $href = ! empty($meta['samples'][0]['href'])
                    ? self::clip((string) $meta['samples'][0]['href'], 40)
                    : '';

                return $n
                    ? ('плохих: ' . $n . ($reason !== '' ? ' · ' . $reason : '') . ($href !== '' ? ' · ' . $href : ''))
                    : 'плохие ссылки';

            case 'html_critical_errors':
                $n = (int) ($meta['count'] ?? 0);
                $msg = ! empty($meta['samples'][0]['message'])
                    ? self::clip((string) $meta['samples'][0]['message'], 70)
                    : '';

                return $n ? ('ошибок: ' . $n . ($msg !== '' ? ' · ' . $msg : '')) : 'ошибки HTML';

            case 'lost_file':
                $asset = ! empty($meta['asset']) ? self::clip((string) $meta['asset'], 55) : '';
                $st = isset($meta['status']) ? ('HTTP ' . (int) $meta['status']) : 'unreachable';

                return $asset !== '' ? ($st . ' · ' . $asset) : $st;

            case 'adult_content':
                $hits = isset($meta['hits']) && is_array($meta['hits'])
                    ? implode(', ', array_slice($meta['hits'], 0, 4))
                    : '';

                return $hits !== '' ? ('hits: ' . $hits) : ('score ' . (int) ($meta['score'] ?? 0));

            case 'negative_content':
                $hits = isset($meta['hits']) && is_array($meta['hits'])
                    ? implode(', ', array_slice($meta['hits'], 0, 4))
                    : '';

                return $hits !== '' ? ('hits: ' . $hits) : ('score ' . (int) ($meta['score'] ?? 0));

            case 'word_repeat_in_sentence':
                $w = ! empty($meta['samples'][0]['word'])
                    ? (string) $meta['samples'][0]['word']
                    : '';
                $c = (int) ($meta['samples'][0]['count'] ?? $meta['count'] ?? 0);

                return $w !== '' ? ($w . ' ×' . $c) : ('повторов: ' . (int) ($meta['count'] ?? 0));

            case 'landing_plagiarism_suspect':
                $src = (string) ($meta['source'] ?? 'internal');
                $peer = ! empty($meta['peer_url']) ? self::clip((string) $meta['peer_url'], 45) : '';

                return $peer !== '' ? ($src . ' · ' . $peer) : $src;

            case 'landing_no_inbound_internal':
                return 'входящих внутренних: 0';

            case 'keyword_cannibalization':
                $q = ! empty($meta['query']) ? self::clip((string) $meta['query'], 40) : '';
                $land = ! empty($meta['landing_url']) ? self::clip((string) $meta['landing_url'], 35) : '';

                return ($q !== '' ? ('«' . $q . '»') : 'запрос')
                    . ($land !== '' ? (' · посадочная: ' . $land) : '');

            case 'ad_cannibalization':
                $q = ! empty($meta['query']) ? self::clip((string) $meta['query'], 36) : '';
                $hint = ! empty($meta['ad_hint']) ? (string) $meta['ad_hint'] : '';
                $land = ! empty($meta['landing_url']) ? self::clip((string) $meta['landing_url'], 30) : '';

                return ($q !== '' ? ('«' . $q . '»') : 'запрос')
                    . ($hint !== '' ? (' · ' . $hint) : '')
                    . ($land !== '' ? (' · SEO: ' . $land) : '');

            case 'landing_query_mismatch':
                $q = ! empty($meta['query']) ? self::clip((string) $meta['query'], 40) : '';
                $hits = isset($meta['hits_any'], $meta['token_count'])
                    ? ((int) $meta['hits_any'] . '/' . (int) $meta['token_count'] . ' токенов')
                    : '';

                return ($q !== '' ? ('«' . $q . '»') : 'запрос')
                    . ($hits !== '' ? (' · ' . $hits) : '');

            case 'commercial_missing_contacts':
                $miss = isset($meta['missing']) && is_array($meta['missing'])
                    ? implode(', ', $meta['missing'])
                    : '';

                return $miss !== '' ? ('нет: ' . $miss) : 'нет контактов';

            case 'commercial_missing_price':
                return 'нет цены';

            case 'commercial_missing_cta':
                return 'нет CTA';

            case 'commercial_missing_delivery':
                return 'нет доставки';

            case 'commercial_missing_payment':
                return 'нет оплаты';

            case 'commercial_missing_stock':
                return 'нет наличия';

            case 'commercial_missing_reviews':
                return 'нет отзывов';

            case 'broken_image':
                $n = (int) ($meta['count'] ?? 0);
                $img = ! empty($meta['samples'][0]['img'])
                    ? self::clip((string) $meta['samples'][0]['img'], 50)
                    : '';

                return $n ? ('битых img: ' . $n . ($img !== '' ? ' · ' . $img : '')) : 'битое изображение';

            case 'heavy_image':
                $n = (int) ($meta['count'] ?? 0);
                $sz = ! empty($meta['samples'][0]['size_bytes'])
                    ? round(((int) $meta['samples'][0]['size_bytes']) / 1024) . ' KB'
                    : '';

                return $n ? ('тяжёлых: ' . $n . ($sz !== '' ? ' · ' . $sz : '')) : 'тяжёлое изображение';

            case 'error_spike':
                $kind = (string) ($meta['kind'] ?? '');
                if ($kind === 'status_cluster') {
                    return 'код ' . ($meta['status'] ?? '?')
                        . ': ' . (int) ($meta['count'] ?? 0)
                        . ' из ' . (int) ($meta['error_total'] ?? 0)
                        . ' ошибок';
                }
                if ($kind === 'path_cluster') {
                    return ($meta['path_prefix'] ?? '/')
                        . ' · ' . (int) ($meta['count'] ?? 0)
                        . '/' . (int) ($meta['prefix_total'] ?? 0)
                        . ' ошибок ('
                        . (isset($meta['rate']) ? round(((float) $meta['rate']) * 100) . '%' : '?')
                        . ')';
                }
                if ($kind === 'crawl_delta') {
                    return 'было ' . (int) ($meta['prev_count'] ?? 0)
                        . ' → стало ' . (int) ($meta['count'] ?? 0)
                        . (isset($meta['ratio']) ? (' (×' . $meta['ratio'] . ')') : '');
                }

                return 'выброс ошибок';

            case 'psi_mobile':
            case 'psi_desktop':
                $bits = [];
                if (isset($meta['score_pct'])) {
                    $bits[] = 'Perf ' . (int) $meta['score_pct'];
                } elseif (isset($meta['score'])) {
                    $bits[] = 'Perf ' . (int) round(((float) $meta['score']) * 100);
                }
                if (isset($meta['lcp_ms'])) {
                    $bits[] = 'LCP ' . round(((float) $meta['lcp_ms']) / 1000, 1) . 's';
                }
                if (isset($meta['cls'])) {
                    $bits[] = 'CLS ' . round((float) $meta['cls'], 3);
                }
                if (isset($meta['tbt_ms'])) {
                    $bits[] = 'TBT ' . (int) round((float) $meta['tbt_ms']) . 'ms';
                }

                return $bits ? implode(' · ', $bits) : 'PSI';

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

            case 'serp_snippets':
                $bits = [];
                if (! empty($meta['page_title'])) {
                    $bits[] = 'title: ' . self::clip((string) $meta['page_title'], 40);
                }
                foreach ((array) ($meta['engines'] ?? []) as $eng => $block) {
                    if (! is_array($block)) {
                        continue;
                    }
                    $label = $eng === 'yandex' ? 'Я' : ($eng === 'google' ? 'G' : (string) $eng);
                    if (! empty($block['error'])) {
                        $bits[] = $label . ': ошибка';
                        continue;
                    }
                    if (empty($block['indexed'])) {
                        $bits[] = $label . ': нет в индексе';
                        continue;
                    }
                    $snip = ! empty($block['snippet'])
                        ? self::clip((string) $block['snippet'], 60)
                        : (! empty($block['title']) ? self::clip((string) $block['title'], 40) : 'есть');
                    $bits[] = $label . ': ' . $snip;
                }

                return $bits ? implode(' · ', $bits) : 'сниппет ПС';

            case 'serp_title_mismatch':
                $bits = [];
                if (! empty($meta['engine'])) {
                    $bits[] = (string) $meta['engine'];
                }
                if (! empty($meta['page_title'])) {
                    $bits[] = 'стр: ' . self::clip((string) $meta['page_title'], 35);
                }
                if (! empty($meta['serp_title'])) {
                    $bits[] = 'выдача: ' . self::clip((string) $meta['serp_title'], 35);
                }

                return $bits ? implode(' · ', $bits) : 'title ≠ выдача';

            case 'serp_not_indexed':
                return ! empty($meta['engine'])
                    ? ('нет в индексе: ' . (string) $meta['engine'])
                    : 'нет в индексе ПС';

            case 'serp_snippet_source':
                $bits = [];
                if (! empty($meta['engine'])) {
                    $bits[] = (string) $meta['engine'];
                }
                if (! empty($meta['title_source'])) {
                    $bits[] = 'title←' . (string) $meta['title_source'];
                }
                if (! empty($meta['snippet_source'])) {
                    $bits[] = 'snippet←' . (string) $meta['snippet_source'];
                }

                return $bits ? implode(' · ', $bits) : 'источник сниппета';

            case 'probable_affiliate':
                $n = (int) ($meta['count'] ?? 0);
                $net = ! empty($meta['samples'][0]['network'])
                    ? (string) $meta['samples'][0]['network']
                    : '';

                return $n
                    ? ('affiliate: ' . $n . ($net !== '' ? ' · ' . $net : ''))
                    : 'affiliate-ссылки';

            case 'missing_permissions_policy':
                return 'нет Permissions-Policy';

            case 'missing_coop':
                return 'нет COOP';

            case 'missing_coep':
                return 'нет COEP';

            case 'missing_corp':
                return 'нет CORP';

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

            case 'missing_csp':
                return 'нет Content-Security-Policy';

            case 'missing_referrer_policy':
                return 'нет Referrer-Policy';

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
