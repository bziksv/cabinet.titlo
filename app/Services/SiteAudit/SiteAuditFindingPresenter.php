<?php

namespace App\Services\SiteAudit;

class SiteAuditFindingPresenter
{
    public static function severityLabel(string $severity): string
    {
        $map = [
            'critical' => 'Грубые',
            'other' => 'Прочие',
            'important' => 'Важные замечания',
            'warning' => 'Предупреждения',
            'info' => 'Инфо',
        ];

        return $map[$severity] ?? $severity;
    }

    /** Короткая метка для дерева отчётов: (грубое), (важное), (замечание)… */
    public static function severityTag(string $severity): string
    {
        $map = [
            'critical' => 'грубое',
            'other' => 'прочие',
            'important' => 'важное',
            'warning' => 'замечание',
            'info' => 'инфо',
        ];

        return $map[$severity] ?? $severity;
    }

    /** Подпись типа рядом с названием отчёта: «грубое», «важное замечание», «инфо»… */
    public static function severityTypeLabel(string $severity): string
    {
        $map = [
            'critical' => 'грубое',
            'other' => 'прочие',
            'important' => 'важное замечание',
            'warning' => 'предупреждение',
            'info' => 'инфо',
        ];

        return $map[$severity] ?? $severity;
    }

    public static function severityBadgeHtml(string $severity): string
    {
        $sev = preg_replace('/[^a-z]/', '', strtolower($severity)) ?: 'info';
        $label = e(self::severityTypeLabel($sev));

        return '<span class="cabinet-sa-sev-badge cabinet-sa-sev-badge--' . e($sev) . '">' . $label . '</span>';
    }

    /**
     * Богатая ячейка «Детали» (HTML). null → рисовать обычный metaLine.
     */
    public static function metaDetailsHtml(string $code, $meta, ?string $url = null): ?string
    {
        if (! is_array($meta)) {
            $meta = [];
        }

        // Пустая meta ок для кодов, которые достают детали из URL.
        if ($meta === [] && ! in_array($code, ['risky_query_params'], true)) {
            return null;
        }

        if ($code === 'commercial_missing_contacts') {
            return self::commercialMissingContactsHtml($meta);
        }

        if ($code === 'probable_affiliate') {
            return self::probableAffiliateDetailsHtml($meta);
        }

        if ($code === 'heavy_image') {
            return self::heavyImageDetailsHtml($meta);
        }

        if ($code === 'broken_image') {
            return self::brokenImageDetailsHtml($meta);
        }

        if ($code === 'images_without_alt') {
            return self::imagesWithoutAltDetailsHtml($meta);
        }

        if ($code === 'no_unique_images') {
            return self::noUniqueImagesDetailsHtml($meta);
        }

        if ($code === 'risky_query_params') {
            return self::riskyQueryParamsDetailsHtml($meta, $url);
        }

        if ($code === 'robots_blocked') {
            return self::robotsBlockedDetailsHtml($meta);
        }

        if ($code === 'heading_hierarchy') {
            return self::headingHierarchyDetailsHtml($meta);
        }

        if ($code === 'duplicate_url_variants') {
            return self::duplicateUrlVariantsHtml($meta, $url);
        }

        if (in_array($code, ['duplicate_content', 'duplicate_title', 'duplicate_description'], true)) {
            return self::duplicatePeersHtml($code, $meta, $url);
        }

        if ($code === 'similar_pages') {
            return self::similarPagesDetailsHtml($meta, $url);
        }

        if ($code === 'noindex') {
            return self::noindexDetailsHtml($meta);
        }

        if ($code === 'text_in_noindex') {
            return self::textInNoindexDetailsHtml($meta);
        }

        if ($code === 'page_has_bad_links') {
            return self::badLinksDetailsHtml($meta);
        }

        if ($code === 'links_nofollow') {
            return self::nofollowLinksDetailsHtml($meta);
        }

        if ($code === 'page_has_broken_external_links') {
            return self::brokenExternalPageDetailsHtml($meta);
        }

        if ($code === 'broken_external_link') {
            return self::brokenExternalLinkDetailsHtml($meta);
        }

        if ($code === 'serp_title_mismatch') {
            return self::serpTitleMismatchHtml($meta);
        }

        if ($code === 'serp_snippets') {
            return self::serpSnippetsDetailsHtml($meta);
        }

        if ($code === 'serp_snippet_source') {
            return self::serpSnippetSourceHtml($meta);
        }

        if ($code === 'lost_file') {
            return self::lostFileDetailsHtml($meta);
        }

        if ($code === 'broken_internal_link') {
            return self::brokenInternalLinkDetailsHtml($meta);
        }

        if (in_array($code, ['http_4xx', 'http_5xx', 'unreachable'], true)) {
            return self::httpStatusDetailsHtml($code, $meta, $url);
        }

        if ($code === 'external_assets') {
            return self::externalAssetsDetailsHtml($meta);
        }

        if ($code === 'external_links') {
            return self::externalLinksDetailsHtml($meta);
        }

        if ($code === 'deep_pages') {
            return self::deepPagesDetailsHtml($meta);
        }

        if (in_array($code, [
            'h1_equals_h2',
            'title_equals_h1',
            'title_equals_description',
            'description_equals_h1',
        ], true)) {
            return self::headingPairDetailsHtml($code, $meta);
        }

        if (in_array($code, [
            'title_too_short',
            'title_too_long',
            'description_too_short',
            'description_too_long',
        ], true)) {
            return self::metaLengthDetailsHtml($code, $meta);
        }

        if (in_array($code, [
            'text_trigram_spam',
            'text_bigram_spam',
            'h1_spam',
            'meta_spam',
            'text_nausea',
            'word_repeat_in_sentence',
        ], true)) {
            return self::spamPhraseDetailsHtml($code, $meta);
        }

        if ($code === 'landing_plagiarism_external') {
            return self::plagiarismExternalDetailsHtml($meta);
        }

        if (in_array($code, ['redirect', 'redirect_chain_long', 'redirect_loop'], true)) {
            return self::redirectDetailsHtml($meta, $code, $url);
        }

        if ($code === 'keyword_cannibalization') {
            return null;
        }

        if ($code === 'mixed_content') {
            return self::mixedContentDetailsHtml($meta);
        }

        if ($code === 'soft_404') {
            return self::soft404DetailsHtml($meta);
        }

        if ($code === 'multiple_h1') {
            return self::multipleH1DetailsHtml($meta);
        }

        if ($code === 'multiple_title_or_description') {
            return self::multipleTitleDescriptionDetailsHtml($meta);
        }

        if ($code === 'multiple_canonical') {
            return self::multipleCanonicalDetailsHtml($meta);
        }

        if ($code === 'www_both_available') {
            return self::wwwBothAvailableDetailsHtml($meta);
        }

        if ($code === 'http_https_both_available') {
            return self::httpHttpsBothAvailableDetailsHtml($meta);
        }

        if ($code === 'insecure_form') {
            return self::insecureFormDetailsHtml($meta);
        }

        if ($code === 'page_has_broken_links') {
            return self::pageHasBrokenLinksDetailsHtml($meta);
        }

        if ($code === 'duplicate_links') {
            return self::duplicateLinksDetailsHtml($meta);
        }

        if (in_array($code, [
            'canonical_foreign',
            'canonical_not_self',
            'pages_with_canonical',
            'crawl_pages',
            'crawl_images',
            'landing_url_changed',
            'ad_cannibalization',
            'robots_txt_error',
        ], true)) {
            // URL в деталях — кликабельно и без обрезки (metaLine оставляем для экспорта/CSV).
            $plain = self::metaLine($code, $meta, $url);
            if ($plain === '' || $plain === '—') {
                return null;
            }

            return self::linkifyUrlsInText($plain);
        }

        if ($code !== 'html_critical_errors') {
            return null;
        }

        $n = (int) ($meta['count'] ?? 0);
        $samples = isset($meta['samples']) && is_array($meta['samples']) ? $meta['samples'] : [];
        if ($samples === []) {
            return null;
        }

        $parts = [];
        $hints = [];
        $checker = ! empty($meta['checker'])
            ? SiteAuditHtmlChecker::label((string) $meta['checker'])
            : '';
        if ($checker !== '') {
            $badge = e($checker);
            if (! empty($meta['checker_fallback'])) {
                $badge .= ' → libxml';
            }
            $parts[] = '<span class="cabinet-sa-html-err__checker">' . $badge . '</span>';
        }
        if ($n > 0) {
            $parts[] = '<span class="cabinet-sa-html-err__count">ошибок: ' . $n . '</span>';
        }

        foreach (array_slice($samples, 0, 5) as $sample) {
            if (! is_array($sample)) {
                continue;
            }
            $msg = trim((string) ($sample['message'] ?? ''));
            if ($msg === '') {
                continue;
            }
            $line = isset($sample['line']) && (int) $sample['line'] > 0
                ? (int) $sample['line']
                : null;
            $label = e(self::clip($msg, 90));
            $q = 'html validation "' . $msg . '"';
            $href = e(self::googleSearchUrl($q));
            $lineBit = $line !== null
                ? ' <span class="text-muted">стр. ' . $line . '</span>'
                : '';
            $hint = SiteAuditFindingHelp::htmlErrorHint($msg);
            $titleAttr = $hint !== null
                ? e($hint)
                : 'Искать в Google';
            $parts[] = '<a class="cabinet-sa-html-err__msg" href="' . $href
                . '" target="_blank" rel="noopener noreferrer" title="' . $titleAttr . '">'
                . $label . '</a>' . $lineBit;
            if ($hint !== null && ! in_array($hint, $hints, true)) {
                $hints[] = $hint;
            }
        }

        if (count($parts) <= ($n > 0 ? 1 : 0)) {
            return null;
        }

        $html = '<div class="cabinet-sa-html-err">'
            . '<div class="cabinet-sa-html-err__line">' . implode(' · ', $parts) . '</div>';
        foreach (array_slice($hints, 0, 2) as $hint) {
            $html .= '<div class="cabinet-sa-html-err__tip">' . e($hint) . '</div>';
        }
        $html .= '</div>';

        return $html;
    }

    private static function commercialMissingContactsHtml(array $meta): ?string
    {
        $plain = self::commercialMissingContactsPlain($meta);
        if ($plain === '—' || $plain === '') {
            return null;
        }

        return '<div class="cabinet-sa-contacts-miss">' . e($plain) . '</div>';
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function commercialMissingContactsPlain(array $meta): string
    {
        $miss = isset($meta['missing']) && is_array($meta['missing']) ? $meta['missing'] : [];
        $labels = [];
        foreach ($miss as $m) {
            $m = (string) $m;
            if ($m === 'phone') {
                $labels[] = 'телефон';
            } elseif ($m === 'address') {
                $labels[] = 'адрес';
            } elseif ($m !== '') {
                $labels[] = $m;
            }
        }
        if ($labels === []) {
            return 'нет контактов';
        }

        $parts = ['нет: ' . implode(' и ', $labels)];
        if (! empty($meta['phone_sample'])) {
            $parts[] = 'найден телефон: ' . self::clip((string) $meta['phone_sample'], 40);
        }
        if (! empty($meta['address_sample'])) {
            $parts[] = 'найден адрес: ' . self::clip((string) $meta['address_sample'], 60);
        }

        return implode(' · ', $parts);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function probableAffiliateDetailsHtml(array $meta): ?string
    {
        $samples = self::probableAffiliateSamples($meta);
        if ($samples === []) {
            return null;
        }

        $n = max((int) ($meta['count'] ?? 0), count($samples));
        $html = '<div class="cabinet-sa-aff">';
        $html .= '<div class="cabinet-sa-aff__head">партнёрских: '
            . number_format($n, 0, '', ' ') . '</div>';
        foreach (array_slice($samples, 0, 8) as $sample) {
            $netLabel = self::affiliateNetworkLabel($sample['network']);
            $html .= '<div class="cabinet-sa-aff__card">';
            $html .= '<div class="cabinet-sa-aff__net">' . e($netLabel) . '</div>';
            $host = (string) (parse_url($sample['url'], PHP_URL_HOST) ?? '');
            if ($host !== '') {
                $html .= '<div class="cabinet-sa-aff__host">' . e($host) . '</div>';
            }
            $html .= '<a class="cabinet-sa-aff__url cabinet-sa-url-break" href="'
                . e($sample['url']) . '" target="_blank" rel="noopener noreferrer">'
                . e($sample['url']) . '</a>';
            $html .= '</div>';
        }
        $more = $n - min(8, count($samples));
        if ($more > 0) {
            $html .= '<div class="cabinet-sa-aff__more">и ещё '
                . number_format($more, 0, '', ' ') . '</div>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function probableAffiliatePlain(array $meta): string
    {
        $samples = self::probableAffiliateSamples($meta);
        if ($samples === []) {
            return 'партнёрские ссылки';
        }
        $n = max((int) ($meta['count'] ?? 0), count($samples));
        $nets = [];
        foreach ($samples as $s) {
            $label = self::affiliateNetworkLabel($s['network']);
            if ($label !== '' && ! isset($nets[$label])) {
                $nets[$label] = true;
            }
        }
        $parts = ['партнёрских: ' . $n];
        if ($nets !== []) {
            $parts[] = implode(', ', array_slice(array_keys($nets), 0, 4));
        }
        $parts[] = $samples[0]['url'];

        return implode(' · ', $parts);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return list<array{url:string,network:string}>
     */
    private static function probableAffiliateSamples(array $meta): array
    {
        $raw = isset($meta['samples']) && is_array($meta['samples']) ? $meta['samples'] : [];
        $out = [];
        foreach ($raw as $sample) {
            if (is_string($sample)) {
                $url = trim($sample);
                if ($url === '') {
                    continue;
                }
                $out[] = ['url' => $url, 'network' => 'generic'];
                continue;
            }
            if (! is_array($sample)) {
                continue;
            }
            $url = trim((string) ($sample['url'] ?? $sample['href'] ?? ''));
            if ($url === '') {
                continue;
            }
            $out[] = [
                'url' => $url,
                'network' => trim((string) ($sample['network'] ?? 'generic')) ?: 'generic',
            ];
        }

        return $out;
    }

    private static function affiliateNetworkLabel(string $network): string
    {
        $map = [
            'admitad' => 'Admitad',
            'actionpay' => 'ActionPay',
            'gdeslon' => 'GdeSlon',
            'cityads' => 'CityAds',
            'advertise' => 'Advertise.ru',
            'tradetracker' => 'TradeTracker',
            'awin' => 'Awin',
            'cj' => 'CJ / Commission Junction',
            'amazon' => 'Amazon',
            'generic' => 'партнёрский URL',
        ];
        $key = strtolower(trim($network));

        return $map[$key] ?? ($network !== '' ? $network : 'партнёрский URL');
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function heavyImageDetailsHtml(array $meta): ?string
    {
        $samples = self::heavyImageSamples($meta);
        if ($samples === []) {
            return null;
        }

        $threshold = (int) ($meta['threshold'] ?? 0);
        if ($threshold <= 0) {
            $threshold = (int) ($samples[0]['threshold'] ?? 0);
        }

        $html = '<div class="cabinet-sa-heavy">';
        foreach (array_slice($samples, 0, 5) as $sample) {
            $img = (string) $sample['img'];
            $bytes = (int) $sample['bytes'];
            $name = self::heavyImageBasename($img);
            $html .= '<div class="cabinet-sa-heavy__card">';
            $html .= '<a class="cabinet-sa-heavy__thumb" href="' . e($img) . '" target="_blank" rel="noopener noreferrer" title="Открыть изображение">';
            $html .= '<img src="' . e($img) . '" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer"'
                . ' onerror="this.parentElement.classList.add(\'is-broken\');this.remove();">';
            $html .= '<span class="cabinet-sa-heavy__thumb-fallback" aria-hidden="true">IMG</span>';
            $html .= '</a>';
            $html .= '<div class="cabinet-sa-heavy__body">';
            if ($bytes > 0) {
                $html .= '<div class="cabinet-sa-heavy__size">' . e(self::formatBytes($bytes)) . '</div>';
                if ($threshold > 0 && $bytes > $threshold) {
                    $over = $bytes / max(1, $threshold);
                    $html .= '<div class="cabinet-sa-heavy__meta">порог '
                        . e(self::formatBytes($threshold))
                        . ' · тяжелее в '
                        . e(self::formatHeavyRatio($over))
                        . '</div>';
                }
            }
            if ($name !== '') {
                $html .= '<div class="cabinet-sa-heavy__name" title="' . e($name) . '">' . e($name) . '</div>';
            }
            $html .= '<a class="cabinet-sa-heavy__url cabinet-sa-url-break" href="' . e($img)
                . '" target="_blank" rel="noopener noreferrer">' . e($img) . '</a>';
            $html .= '</div></div>';
        }
        $more = count($samples) - min(5, count($samples));
        if ($more > 0) {
            $html .= '<div class="cabinet-sa-heavy__more">и ещё '
                . number_format($more, 0, '', ' ') . ' на этой странице</div>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * Img без alt: карточки как у heavy_image (превью + статус + src).
     *
     * @param  array<string, mixed>  $meta
     */
    private static function imagesWithoutAltDetailsHtml(array $meta): ?string
    {
        $samples = self::imagesWithoutAltSamples($meta);
        $without = (int) ($meta['img_without_alt'] ?? $meta['count'] ?? count($samples));
        $totalImg = (int) ($meta['img_count'] ?? 0);

        if ($samples === []) {
            if ($without <= 0) {
                return null;
            }
            $line = 'без alt: ' . number_format($without, 0, '', ' ');
            if ($totalImg > 0) {
                $line .= ' / ' . number_format($totalImg, 0, '', ' ');
            }
            $line .= ' <span class="text-muted">(список src — после нового обхода)</span>';

            return '<div class="cabinet-sa-heavy cabinet-sa-heavy--summary">' . $line . '</div>';
        }

        $html = '<div class="cabinet-sa-heavy">';
        foreach (array_slice($samples, 0, 8) as $sample) {
            $img = (string) $sample['img'];
            $name = self::heavyImageBasename($img);
            $w = $sample['width'];
            $h = $sample['height'];
            $dims = '';
            if ($w || $h) {
                $dims = ($w ? number_format((int) $w, 0, '', ' ') : '?')
                    . '×'
                    . ($h ? number_format((int) $h, 0, '', ' ') : '?')
                    . ' px';
            }

            $html .= '<div class="cabinet-sa-heavy__card">';
            $html .= '<a class="cabinet-sa-heavy__thumb" href="' . e($img) . '" target="_blank" rel="noopener noreferrer">';
            $html .= '<img src="' . e($img) . '" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer"'
                . ' onerror="this.parentElement.classList.add(\'is-broken\');this.remove();">';
            $html .= '<span class="cabinet-sa-heavy__thumb-fallback" aria-hidden="true">IMG</span>';
            $html .= '</a>';
            $html .= '<div class="cabinet-sa-heavy__body">';
            $html .= '<div class="cabinet-sa-heavy__size">нет alt</div>';
            if ($dims !== '') {
                $html .= '<div class="cabinet-sa-heavy__meta">' . e($dims) . '</div>';
            } else {
                $html .= '<div class="cabinet-sa-heavy__meta">атрибут alt пустой или отсутствует</div>';
            }
            if ($name !== '') {
                $html .= '<div class="cabinet-sa-heavy__name" title="' . e($name) . '">' . e($name) . '</div>';
            }
            $html .= '<a class="cabinet-sa-heavy__url cabinet-sa-url-break" href="' . e($img)
                . '" target="_blank" rel="noopener noreferrer">' . e($img) . '</a>';
            $html .= '</div></div>';
        }

        $shown = min(8, count($samples));
        $extra = max(count($samples) - $shown, $without - $shown);
        if ($extra > 0) {
            $html .= '<div class="cabinet-sa-heavy__more">и ещё '
                . number_format($extra, 0, '', ' ') . ' на этой странице</div>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return list<array{img:string,width:?int,height:?int}>
     */
    private static function imagesWithoutAltSamples(array $meta): array
    {
        $raw = isset($meta['samples']) && is_array($meta['samples']) ? $meta['samples'] : [];
        $out = [];
        foreach ($raw as $sample) {
            $img = '';
            $w = null;
            $h = null;
            if (is_string($sample)) {
                $img = trim($sample);
            } elseif (is_array($sample)) {
                $img = trim((string) ($sample['src'] ?? $sample['url'] ?? $sample['img'] ?? ''));
                if (isset($sample['width']) && $sample['width'] !== null && $sample['width'] !== '') {
                    $w = (int) $sample['width'];
                }
                if (isset($sample['height']) && $sample['height'] !== null && $sample['height'] !== '') {
                    $h = (int) $sample['height'];
                }
            }
            if ($img === '') {
                continue;
            }
            $out[] = ['img' => $img, 'width' => $w, 'height' => $h];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function imagesWithoutAltPlain(array $meta): string
    {
        $without = (int) ($meta['img_without_alt'] ?? $meta['count'] ?? 0);
        $totalImg = (int) ($meta['img_count'] ?? 0);
        $samples = self::imagesWithoutAltSamples($meta);
        $bits = [];
        if ($without > 0) {
            $bits[] = 'без alt: ' . $without . ($totalImg > 0 ? (' / ' . $totalImg) : '');
        }
        if ($samples !== []) {
            $names = [];
            foreach (array_slice($samples, 0, 3) as $s) {
                $n = self::heavyImageBasename((string) $s['img']);
                $names[] = $n !== '' ? $n : self::clip((string) $s['img'], 40);
            }
            $bits[] = implode(', ', $names);
            if (count($samples) > 3) {
                $bits[] = '…+' . (count($samples) - 3);
            }
        }

        return $bits !== [] ? implode(' · ', $bits) : 'есть img без alt';
    }

    /**
     * Нет картинок с нормальным src на странице (не «уже были на других URL»).
     *
     * @param  array<string, mixed>  $meta
     */
    private static function noUniqueImagesDetailsHtml(array $meta): ?string
    {
        $imgCount = (int) ($meta['img_count'] ?? 0);
        $unique = (int) ($meta['unique_img_src_count'] ?? 0);
        $reason = (string) ($meta['reason'] ?? '');
        if ($reason === '') {
            $reason = $imgCount > 0 ? 'no_src' : 'no_img';
        }

        $html = '<div class="cabinet-sa-no-uniq-img">';
        if ($reason === 'no_src') {
            $html .= '<div class="cabinet-sa-no-uniq-img__flag">нет картинок с адресом</div>';
            $html .= '<div class="cabinet-sa-no-uniq-img__title">Теги &lt;img&gt; есть, но без нормального src</div>';
            $html .= '<div class="cabinet-sa-no-uniq-img__meta">'
                . 'тегов img: <strong>' . number_format($imgCount, 0, '', ' ') . '</strong>'
                . ' · с рабочим src: <strong>' . number_format($unique, 0, '', ' ') . '</strong>'
                . '</div>';
            $html .= '<div class="cabinet-sa-no-uniq-img__hint">'
                . 'Это не «картинки уже где-то на сайте». На этой странице у img пустой src, data-URI '
                . 'или адрес не распознан — для индекса и сниппета таких файлов нет.'
                . '</div>';
        } else {
            $html .= '<div class="cabinet-sa-no-uniq-img__flag">нет изображений</div>';
            $html .= '<div class="cabinet-sa-no-uniq-img__title">На странице нет ни одного &lt;img&gt;</div>';
            $html .= '<div class="cabinet-sa-no-uniq-img__hint">'
                . 'Для контентной/карточной страницы обычно нужны свои фото или иллюстрации '
                . 'с нормальным src и осмысленным alt — не только иконки из CSS.'
                . '</div>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function noUniqueImagesPlain(array $meta): string
    {
        $imgCount = (int) ($meta['img_count'] ?? 0);
        $unique = (int) ($meta['unique_img_src_count'] ?? 0);
        $reason = (string) ($meta['reason'] ?? '');
        if ($reason === '') {
            $reason = $imgCount > 0 ? 'no_src' : 'no_img';
        }
        if ($reason === 'no_src') {
            return 'тегов img: ' . number_format($imgCount, 0, '', ' ')
                . ' · с src: ' . number_format($unique, 0, '', ' ')
                . ' — нет картинок с нормальным адресом';
        }

        return 'на странице нет изображений (img: 0)';
    }

    /**
     * Битые img: URL файла, имя, HTTP-статус — чтобы найти в коде страницы.
     *
     * @param  array<string, mixed>  $meta
     */
    private static function brokenImageDetailsHtml(array $meta): ?string
    {
        $samples = self::brokenImageSamples($meta);
        if ($samples === []) {
            return null;
        }

        $html = '<div class="cabinet-sa-broken-img">';
        foreach (array_slice($samples, 0, 5) as $sample) {
            $img = (string) $sample['img'];
            $status = $sample['status'];
            $error = (string) $sample['error'];
            $name = self::heavyImageBasename($img);

            $html .= '<div class="cabinet-sa-broken-img__card">';
            $html .= '<div class="cabinet-sa-broken-img__badge" aria-hidden="true"><i class="fa fa-picture-o"></i></div>';
            $html .= '<div class="cabinet-sa-broken-img__body">';

            $statusBits = [];
            if ($status !== null && $status > 0) {
                $statusBits[] = 'HTTP ' . $status;
            } elseif ($error !== '') {
                $statusBits[] = self::clip($error, 60);
            } else {
                $statusBits[] = 'не отдаётся';
            }
            $html .= '<div class="cabinet-sa-broken-img__status">' . e(implode(' · ', $statusBits)) . '</div>';

            if ($name !== '') {
                $html .= '<div class="cabinet-sa-broken-img__name" title="' . e($name) . '">'
                    . 'файл: <code>' . e($name) . '</code></div>';
            }

            $html .= '<div class="cabinet-sa-broken-img__label">src</div>';
            $html .= '<a class="cabinet-sa-broken-img__url cabinet-sa-url-break" href="' . e($img)
                . '" target="_blank" rel="noopener noreferrer">' . e($img) . '</a>';
            $html .= '</div></div>';
        }

        $shown = min(5, count($samples));
        $extra = max(count($samples) - $shown, (int) ($meta['count'] ?? 0) - $shown);
        if ($extra > 0) {
            $html .= '<div class="cabinet-sa-broken-img__more">и ещё '
                . number_format($extra, 0, '', ' ') . ' на этой странице</div>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return list<array{img:string,status:?int,error:string}>
     */
    private static function brokenImageSamples(array $meta): array
    {
        $out = [];
        $raw = isset($meta['samples']) && is_array($meta['samples']) ? $meta['samples'] : [];
        foreach ($raw as $sample) {
            if (! is_array($sample)) {
                continue;
            }
            $img = trim((string) ($sample['img'] ?? $sample['src'] ?? $sample['url'] ?? ''));
            if ($img === '') {
                continue;
            }
            $status = isset($sample['status']) && $sample['status'] !== null && $sample['status'] !== ''
                ? (int) $sample['status']
                : null;
            $out[] = [
                'img' => $img,
                'status' => $status,
                'error' => trim((string) ($sample['error'] ?? '')),
            ];
        }
        if ($out === []) {
            $img = trim((string) ($meta['src'] ?? $meta['img'] ?? ''));
            if ($img !== '') {
                $status = isset($meta['status']) && $meta['status'] !== null && $meta['status'] !== ''
                    ? (int) $meta['status']
                    : null;
                $out[] = [
                    'img' => $img,
                    'status' => $status,
                    'error' => trim((string) ($meta['error'] ?? '')),
                ];
            }
        }

        return $out;
    }

    /**
     * Нормализация meta risky_query_params (+ разбор query из URL, если meta пустая).
     *
     * @param  array<string, mixed>  $meta
     * @return array{keys:list<string>,key_count:int,query_len:int,many_keys:bool,long_query:bool,query:string,reasons:list<string>}
     */
    private static function riskyQueryParamsNormalize(array $meta, ?string $url): array
    {
        $keys = [];
        if (! empty($meta['keys']) && is_array($meta['keys'])) {
            $keys = array_values(array_filter(array_map('strval', $meta['keys'])));
        } elseif (! empty($meta['params']) && is_array($meta['params'])) {
            $keys = array_values(array_filter(array_map('strval', $meta['params'])));
        }

        $query = trim((string) ($meta['query'] ?? ''));
        if ($query === '' && is_string($url) && $url !== '') {
            $q = parse_url($url, PHP_URL_QUERY);
            if (is_string($q) && $q !== '') {
                $query = $q;
            }
        }

        $allKeys = [];
        if ($query !== '') {
            $params = [];
            parse_str($query, $params);
            $allKeys = array_map('strtolower', array_keys($params));
        }

        $riskyCfg = config('site_audit.risky_query_keys', [
            'phpsessid', 'sid', 'sessionid', 'session_id', 'jsessionid',
            'sort', 'order', 'orderby', 'sortby',
        ]);
        $riskyCfg = is_array($riskyCfg) ? array_map('strtolower', $riskyCfg) : [];

        if ($keys === [] && $allKeys !== []) {
            $keys = array_values(array_intersect($allKeys, $riskyCfg));
        }

        $keyCount = isset($meta['key_count'])
            ? (int) $meta['key_count']
            : ($allKeys !== [] ? count($allKeys) : count($keys));
        $queryLen = isset($meta['query_len'])
            ? (int) $meta['query_len']
            : strlen($query);
        $manyKeys = ! empty($meta['many_keys'])
            || $keyCount >= (int) config('site_audit.risky_query_key_count', 8);
        $longQuery = ! empty($meta['long_query'])
            || $queryLen >= (int) config('site_audit.risky_query_len', 120);

        $sessionKeys = ['phpsessid', 'sid', 'sessionid', 'session_id', 'jsessionid'];
        $sortKeys = ['sort', 'order', 'orderby', 'sortby'];
        $keysLower = array_map('strtolower', $keys);
        $reasons = [];
        if (array_intersect($keysLower, $sessionKeys) !== []) {
            $reasons[] = 'session';
        }
        if (array_intersect($keysLower, $sortKeys) !== []) {
            $reasons[] = 'sort';
        }
        if ($manyKeys) {
            $reasons[] = 'many_keys';
        }
        if ($longQuery) {
            $reasons[] = 'long_query';
        }
        if ($reasons === [] && $keys !== []) {
            $reasons[] = 'keys';
        }

        return [
            'keys' => array_values(array_unique($keys)),
            'key_count' => $keyCount,
            'query_len' => $queryLen,
            'many_keys' => $manyKeys,
            'long_query' => $longQuery,
            'query' => $query,
            'reasons' => $reasons,
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function riskyQueryParamsPlain(array $meta, ?string $url = null): string
    {
        $n = self::riskyQueryParamsNormalize(is_array($meta) ? $meta : [], $url);
        $bits = [];
        $reasonLabels = [
            'session' => 'session в URL',
            'sort' => 'сортировка в URL',
            'many_keys' => 'много параметров (' . $n['key_count'] . ')',
            'long_query' => 'длинный query (' . $n['query_len'] . ' симв.)',
            'keys' => 'рисковые параметры',
        ];
        foreach ($n['reasons'] as $r) {
            if (isset($reasonLabels[$r])) {
                $bits[] = $reasonLabels[$r];
            }
        }
        if ($n['keys'] !== []) {
            $bits[] = implode(', ', array_slice($n['keys'], 0, 6));
        }
        if ($n['query'] !== '' && $bits === []) {
            $bits[] = '?' . self::clip($n['query'], 60);
        }

        return $bits !== [] ? implode(' · ', $bits) : 'рисковые параметры в query';
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function riskyQueryParamsDetailsHtml(array $meta, ?string $url = null): ?string
    {
        $n = self::riskyQueryParamsNormalize($meta, $url);
        if ($n['keys'] === [] && $n['query'] === '' && $n['reasons'] === []) {
            return null;
        }

        $reasonLabels = [
            'session' => 'Идентификатор сессии в адресе',
            'sort' => 'Сортировка каталога в URL',
            'many_keys' => 'Слишком много параметров',
            'long_query' => 'Слишком длинный query',
            'keys' => 'Рисковые параметры',
        ];

        $html = '<div class="cabinet-sa-risky-q">';
        if ($n['reasons'] !== []) {
            $html .= '<div class="cabinet-sa-risky-q__reasons">';
            foreach ($n['reasons'] as $r) {
                $label = $reasonLabels[$r] ?? $r;
                $mod = preg_replace('/[^a-z0-9_]+/i', '', $r) ?: 'keys';
                $html .= '<span class="cabinet-sa-risky-q__chip cabinet-sa-risky-q__chip--' . e($mod) . '">'
                    . e($label) . '</span>';
            }
            $html .= '</div>';
        }

        if ($n['keys'] !== []) {
            $html .= '<div class="cabinet-sa-risky-q__row">'
                . '<span class="cabinet-sa-risky-q__label">Параметры</span>'
                . '<span class="cabinet-sa-risky-q__vals">';
            foreach (array_slice($n['keys'], 0, 8) as $key) {
                $html .= '<code class="cabinet-sa-risky-q__key">' . e((string) $key) . '</code>';
            }
            $html .= '</span></div>';
        }

        if ($n['many_keys'] || $n['long_query']) {
            $metaBits = [];
            if ($n['many_keys']) {
                $metaBits[] = 'параметров: ' . $n['key_count'];
            }
            if ($n['long_query']) {
                $metaBits[] = 'длина query: ' . $n['query_len'] . ' симв.';
            }
            $html .= '<div class="cabinet-sa-risky-q__meta">' . e(implode(' · ', $metaBits)) . '</div>';
        }

        if ($n['query'] !== '') {
            $html .= '<div class="cabinet-sa-risky-q__row">'
                . '<span class="cabinet-sa-risky-q__label">Query</span>'
                . '<code class="cabinet-sa-risky-q__query" title="' . e($n['query']) . '">?'
                . e(self::clip($n['query'], 160)) . '</code>'
                . '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function keywordCannibalizationPlain(array $meta): string
    {
        $q = ! empty($meta['query']) ? self::clip((string) $meta['query'], 40) : '';
        $land = ! empty($meta['landing_url']) ? self::clip((string) $meta['landing_url'], 40) : '';
        $bits = [];
        if ($q !== '') {
            $bits[] = 'запрос «' . $q . '»';
        }
        if ($land !== '') {
            $bits[] = 'посадочная: ' . $land;
        }
        if (! empty($meta['full_match'])) {
            $bits[] = 'полное совпадение в title/h1';
        } elseif (isset($meta['hits'])) {
            $bits[] = 'слов из запроса: ' . (int) $meta['hits'];
        }

        return $bits !== [] ? implode(' · ', $bits) : 'каннибализация запроса';
    }

    /**
     * Код эвристики probe → короткая подпись в таблице (не путь URL).
     */
    private static function adCannibalizationHintLabel(string $hint): string
    {
        $hint = trim($hint);
        if ($hint === '') {
            return '';
        }

        $map = [
            'path_promo' => 'промо-путь',
            'path_promo_prefix' => 'промо-путь',
            'thin_cta' => 'тонкая CTA-страница',
            'cta_heavy' => 'много CTA, мало текста',
        ];
        if (isset($map[$hint])) {
            return $map[$hint];
        }
        // Старое демо писало в ad_hint буквальный «/promo/» — не путать с URL слева.
        if ($hint === '/promo/' || $hint[0] === '/') {
            return 'промо-путь';
        }

        return $hint;
    }

    /**
     * Mixed content: спокойный список http:// ресурсов на HTTPS-странице.
     *
     * @param  array<string, mixed>  $meta
     */
    private static function mixedContentDetailsHtml(array $meta): ?string
    {
        $n = (int) ($meta['count'] ?? 0);
        $samples = isset($meta['samples']) && is_array($meta['samples']) ? $meta['samples'] : [];
        $urls = [];
        foreach ($samples as $sample) {
            if (is_string($sample) && trim($sample) !== '') {
                $urls[] = trim($sample);
            } elseif (is_array($sample) && ! empty($sample['url'])) {
                $urls[] = trim((string) $sample['url']);
            }
        }
        $urls = array_values(array_unique($urls));
        if ($n < 1 && $urls === []) {
            return null;
        }
        if ($n < 1) {
            $n = count($urls);
        }

        $noun = 'ресурсов';
        if ($n % 10 === 1 && $n % 100 !== 11) {
            $noun = 'ресурс';
        } elseif (in_array($n % 10, [2, 3, 4], true) && ! in_array($n % 100, [12, 13, 14], true)) {
            $noun = 'ресурса';
        }

        $html = '<div class="cabinet-sa-mixed">';
        $html .= '<div class="cabinet-sa-mixed__head">'
            . '<span class="cabinet-sa-mixed__count">' . $n . ' http-' . $noun . '</span>'
            . '<span class="cabinet-sa-mixed__hint">на HTTPS-странице</span>'
            . '</div>';

        if ($urls !== []) {
            $html .= '<ul class="cabinet-sa-mixed__list">';
            foreach (array_slice($urls, 0, 5) as $httpUrl) {
                $kind = self::mixedContentKindLabel($httpUrl);
                $name = self::mixedContentFileName($httpUrl);
                $html .= '<li class="cabinet-sa-mixed__item">';
                if ($kind !== '') {
                    $html .= '<span class="cabinet-sa-mixed__kind">' . e($kind) . '</span>';
                }
                $html .= '<div class="cabinet-sa-mixed__body">';
                if ($name !== '') {
                    $html .= '<code class="cabinet-sa-mixed__file">' . e($name) . '</code>';
                }
                $html .= '<a class="cabinet-sa-mixed__url" href="' . e($httpUrl) . '" target="_blank" rel="noopener noreferrer">'
                    . e($httpUrl) . '</a>';
                $html .= '</div></li>';
            }
            $html .= '</ul>';
            if ($n > count($urls)) {
                $more = $n - count($urls);
                $html .= '<div class="cabinet-sa-mixed__more">ещё '
                    . $more . ' в HTML страницы</div>';
            }
        }

        $html .= '</div>';

        return $html;
    }

    private static function mixedContentKindLabel(string $url): string
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $map = [
            'js' => 'скрипт',
            'mjs' => 'скрипт',
            'css' => 'стиль',
            'png' => 'картинка',
            'jpg' => 'картинка',
            'jpeg' => 'картинка',
            'gif' => 'картинка',
            'svg' => 'картинка',
            'webp' => 'картинка',
            'ico' => 'иконка',
            'woff' => 'шрифт',
            'woff2' => 'шрифт',
            'ttf' => 'шрифт',
            'otf' => 'шрифт',
        ];

        return $map[$ext] ?? 'ресурс';
    }

    private static function mixedContentFileName(string $url): string
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');
        $base = basename($path);

        return $base !== '' && $base !== '/' ? $base : '';
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function keywordCannibalizationDetailsHtml(array $meta): ?string
    {
        $query = trim((string) ($meta['query'] ?? ''));
        $landing = trim((string) ($meta['landing_url'] ?? ''));
        $title = trim((string) ($meta['competitor_title'] ?? ''));
        if ($query === '' && $landing === '') {
            return null;
        }

        $html = '<div class="cabinet-sa-cannibal">';
        $html .= '<div class="cabinet-sa-cannibal__chip">Лишняя страница под чужой запрос</div>';

        if ($query !== '') {
            $html .= '<div class="cabinet-sa-cannibal__row">'
                . '<span class="cabinet-sa-cannibal__label">Запрос из мониторинга</span>'
                . '<span class="cabinet-sa-cannibal__query">«' . e($query) . '»</span>'
                . '</div>';
        }

        if ($landing !== '') {
            $html .= '<div class="cabinet-sa-cannibal__row">'
                . '<span class="cabinet-sa-cannibal__label">Посадочная из мониторинга позиций</span>'
                . self::urlLinkHtml($landing)
                . '</div>';
        }

        $html .= '<div class="cabinet-sa-cannibal__row">'
            . '<span class="cabinet-sa-cannibal__label">Эта строка (URL слева)</span>'
            . '<span class="cabinet-sa-cannibal__note">конкурирует: в её TITLE/H1 нашёлся этот запрос</span>'
            . '</div>';

        if ($title !== '') {
            $html .= '<div class="cabinet-sa-cannibal__row">'
                . '<span class="cabinet-sa-cannibal__label">TITLE этой страницы</span>'
                . '<span class="cabinet-sa-cannibal__title" title="' . e($title) . '">'
                . e(self::clip($title, 120)) . '</span>'
                . '</div>';
        }

        $how = [];
        if (! empty($meta['full_match'])) {
            $how[] = 'запрос целиком есть в TITLE или H1';
        } elseif (isset($meta['hits'])) {
            $how[] = 'совпало слов из запроса: ' . (int) $meta['hits'];
        }
        if ($how !== []) {
            $html .= '<div class="cabinet-sa-cannibal__meta">' . e(implode(' · ', $how)) . '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function brokenImagePlain(array $meta): string
    {
        $samples = self::brokenImageSamples($meta);
        if ($samples === []) {
            return 'битое изображение';
        }
        $n = max((int) ($meta['count'] ?? 0), count($samples));
        $first = $samples[0];
        $bits = ['битых img: ' . $n];
        if ($first['status'] !== null && $first['status'] > 0) {
            $bits[] = 'HTTP ' . $first['status'];
        }
        $bits[] = self::clip($first['img'], 80);

        return implode(' · ', $bits);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return list<array{img:string,bytes:int,threshold:int}>
     */
    private static function heavyImageSamples(array $meta): array
    {
        $out = [];
        $raw = isset($meta['samples']) && is_array($meta['samples']) ? $meta['samples'] : [];
        foreach ($raw as $sample) {
            if (! is_array($sample)) {
                continue;
            }
            $img = trim((string) ($sample['img'] ?? $sample['src'] ?? $sample['url'] ?? ''));
            if ($img === '') {
                continue;
            }
            $out[] = [
                'img' => $img,
                'bytes' => (int) ($sample['size_bytes'] ?? $sample['bytes'] ?? 0),
                'threshold' => (int) ($sample['threshold'] ?? $meta['threshold'] ?? 0),
            ];
        }
        if ($out === []) {
            $img = trim((string) ($meta['src'] ?? $meta['img'] ?? $meta['url'] ?? ''));
            if ($img !== '') {
                $out[] = [
                    'img' => $img,
                    'bytes' => (int) ($meta['bytes'] ?? $meta['size_bytes'] ?? 0),
                    'threshold' => (int) ($meta['threshold'] ?? 0),
                ];
            }
        }

        return $out;
    }

    private static function heavyImageBasename(string $url): string
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        $base = rawurldecode(basename($path));
        if ($base !== '' && $base !== '/' && $base !== '.' && preg_match('/\.(jpe?g|png|gif|webp|avif|svg|bmp|ico)(\?|$)/i', $base)) {
            return $base;
        }
        $host = (string) (parse_url($url, PHP_URL_HOST) ?? '');
        $path = trim($path, '/');
        if ($host !== '' && $path !== '') {
            return $host . '/' . self::clip($path, 48);
        }
        if ($host !== '') {
            return $host;
        }

        return $base !== '' && $base !== '/' ? $base : '';
    }

    private static function formatHeavyRatio(float $ratio): string
    {
        if ($ratio >= 10) {
            return (string) (int) round($ratio) . '×';
        }
        if ($ratio >= 2) {
            return str_replace('.', ',', (string) round($ratio, 1)) . '×';
        }

        return str_replace('.', ',', (string) round($ratio, 2)) . '×';
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function heavyImagePlain(array $meta): string
    {
        $samples = self::heavyImageSamples($meta);
        if ($samples === []) {
            return 'тяжёлое изображение';
        }
        $parts = [];
        $n = max((int) ($meta['count'] ?? 0), count($samples));
        if ($n > 1) {
            $parts[] = 'тяжёлых: ' . number_format($n, 0, '', ' ');
        }
        $first = $samples[0];
        if ($first['bytes'] > 0) {
            $parts[] = self::formatBytes($first['bytes']);
        }
        $parts[] = $first['img'];

        return implode(' · ', $parts);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function duplicateUrlVariantsHtml(array $meta, ?string $rowUrl = null): ?string
    {
        $variants = [];
        if (isset($meta['variants']) && is_array($meta['variants'])) {
            foreach ($meta['variants'] as $v) {
                $v = trim((string) $v);
                if ($v !== '' && ! in_array($v, $variants, true)) {
                    $variants[] = $v;
                }
            }
        }
        // В «Деталях» только другие адреса — текущий уже в колонке URL.
        $others = $variants;
        if ($rowUrl !== null && $rowUrl !== '') {
            $others = array_values(array_filter($variants, static function ($v) use ($rowUrl) {
                return $v !== $rowUrl;
            }));
        }
        if ($others === []) {
            return null;
        }

        $diff = self::describeUrlVariantDiff($variants);
        $html = '<div class="cabinet-sa-url-variants">';
        $html .= '<div class="cabinet-sa-url-variants__head">';
        $html .= count($others) === 1 ? 'также:' : ('также (' . count($others) . '):');
        if ($diff !== '') {
            $html .= ' <span class="cabinet-sa-url-variants__diff">' . e($diff) . '</span>';
        }
        $html .= '</div><ul class="cabinet-sa-url-variants__list">';
        foreach ($others as $v) {
            $html .= '<li><a href="' . e($v) . '" target="_blank" rel="noopener noreferrer">'
                . e($v) . '</a></li>';
        }
        $html .= '</ul></div>';

        return $html;
    }

    /**
     * Дубли title/description/content: что совпало + другие URL в группе.
     *
     * @param  array<string, mixed>  $meta
     */
    private static function duplicatePeersHtml(string $code, array $meta, ?string $rowUrl = null): ?string
    {
        $size = (int) ($meta['group_size'] ?? 0);
        $peers = self::peerUrlsFromMeta($meta, $rowUrl);
        $match = self::duplicateMatchText($code, $meta);
        $kind = self::duplicateKindLabel($code);

        if ($match === '' && $peers === [] && $size < 2) {
            return null;
        }

        $baseHost = '';
        if ($rowUrl) {
            $baseHost = (string) (parse_url($rowUrl, PHP_URL_HOST) ?: '');
        }

        $html = '<div class="cabinet-sa-dup">';
        $html .= '<div class="cabinet-sa-dup__chips">';
        $html .= '<span class="cabinet-sa-dup__chip cabinet-sa-dup__chip--kind">Дубль · ' . e($kind) . '</span>';
        if ($size > 1) {
            $html .= '<span class="cabinet-sa-dup__chip">'
                . number_format($size, 0, '', ' ') . ' '
                . self::ruWordForm($size, 'страница', 'страницы', 'страниц')
                . '</span>';
        }
        $html .= '</div>';

        if ($match !== '') {
            $html .= '<div class="cabinet-sa-dup__quote">«' . e(self::clip($match, 160)) . '»</div>';
        }

        if ($peers !== []) {
            $shown = array_slice($peers, 0, 6);
            $more = count($peers) - count($shown);
            $html .= '<div class="cabinet-sa-dup__peers-label">Также с этим текстом</div>';
            $html .= '<ul class="cabinet-sa-dup__peers">';
            foreach ($shown as $peer) {
                $label = self::redirectStepLabel($peer, $baseHost);
                $html .= '<li><a href="' . e($peer) . '" target="_blank" rel="noopener noreferrer">'
                    . e($label !== '' ? $label : $peer) . '</a></li>';
            }
            $html .= '</ul>';
            if ($more > 0) {
                $html .= '<div class="cabinet-sa-dup__more">и ещё '
                    . number_format($more, 0, '', ' ')
                    . ' — удобнее во вкладке «Группы»</div>';
            }
        } elseif ($size > 1) {
            $html .= '<div class="cabinet-sa-dup__more">'
                . 'Откройте вид «Группы», чтобы увидеть все URL с этим текстом.'
                . '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function duplicatePeersPlain(string $code, array $meta, ?string $rowUrl = null): string
    {
        $parts = [];
        $parts[] = 'Дубль ' . self::duplicateKindLabel($code);
        $size = (int) ($meta['group_size'] ?? 0);
        if ($size > 0) {
            $parts[] = number_format($size, 0, '', ' ') . ' стр.';
        }
        $match = self::duplicateMatchText($code, $meta);
        if ($match !== '') {
            $parts[] = '«' . self::clip($match, 80) . '»';
        }
        $peers = self::peerUrlsFromMeta($meta, $rowUrl);
        if ($peers !== []) {
            $parts[] = 'также: ' . implode(', ', array_map(static function ($u) {
                return self::clip($u, 60);
            }, array_slice($peers, 0, 4)));
            if (count($peers) > 4) {
                $parts[] = '…+' . (count($peers) - 4);
            }
        }

        return implode(' · ', $parts);
    }

    /**
     * Чистый текст совпадения (без префикса «Одинаковое description:» из старых демо).
     *
     * @param  array<string, mixed>  $meta
     */
    private static function duplicateMatchText(string $code, array $meta): string
    {
        $raw = self::duplicateMatchLabel($code, $meta);
        if ($raw === '') {
            return '';
        }
        $raw = preg_replace(
            '/^Одинаков(ое|ый|ая)\s+(description|title|текст(?:ом)?|контент)\s*[:：]\s*/iu',
            '',
            $raw
        ) ?: $raw;
        $raw = preg_replace('/^Дубль\s+(TITLE|Description|title|description)\s*[:：]?\s*/iu', '', $raw) ?: $raw;
        $raw = preg_replace('/^«(.+)»$/u', '$1', trim($raw)) ?: $raw;
        $raw = preg_replace('/[,·•—–-]\s*группа\s+\d+\s*$/iu', '', $raw) ?: $raw;
        $raw = preg_replace('/\s+группа\s+\d+\s*$/iu', '', $raw) ?: $raw;

        return trim($raw);
    }

    private static function duplicateKindLabel(string $code): string
    {
        if ($code === 'duplicate_title') {
            return 'TITLE';
        }
        if ($code === 'duplicate_description') {
            return 'Description';
        }

        return 'контент';
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return list<string>
     */
    private static function peerUrlsFromMeta(array $meta, ?string $rowUrl = null): array
    {
        $raw = [];
        if (isset($meta['peer_urls']) && is_array($meta['peer_urls'])) {
            $raw = $meta['peer_urls'];
        } elseif (! empty($meta['peer_url'])) {
            $raw = [(string) $meta['peer_url']];
        } elseif (isset($meta['urls']) && is_array($meta['urls'])) {
            $raw = $meta['urls'];
        }

        $out = [];
        foreach ($raw as $u) {
            $u = trim((string) $u);
            if ($u === '' || ($rowUrl !== null && $u === $rowUrl)) {
                continue;
            }
            if (! in_array($u, $out, true)) {
                $out[] = $u;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function duplicateMatchLabel(string $code, array $meta): string
    {
        if ($code === 'duplicate_description') {
            return trim((string) ($meta['description'] ?? $meta['label'] ?? ''));
        }
        if ($code === 'duplicate_title') {
            return trim((string) ($meta['title'] ?? $meta['label'] ?? ''));
        }
        // content
        $label = trim((string) ($meta['label'] ?? ''));
        if ($label !== '') {
            return $label;
        }
        $title = trim((string) ($meta['title'] ?? ''));
        if ($title !== '') {
            return 'текст ≈ title «' . $title . '»';
        }

        return 'одинаковый текст страниц';
    }

    private static function duplicateKindWord(string $code): string
    {
        return self::duplicateKindLabel($code);
    }

    /**
     * Похожие страницы (SimHash): явная пара URL + сколько слов + насколько близки.
     *
     * @param  array<string, mixed>  $meta
     */
    private static function similarPagesDetailsHtml(array $meta, ?string $rowUrl = null): ?string
    {
        $thisUrl = trim((string) ($rowUrl ?? ''));
        $similar = trim((string) ($meta['similar_url'] ?? ''));
        $hamming = array_key_exists('hamming', $meta) ? (int) $meta['hamming'] : null;
        $words = (int) ($meta['word_count'] ?? 0);
        $simWords = (int) ($meta['similar_word_count'] ?? 0);
        $threshold = (int) config('site_audit.simhash_hamming_max', 6);
        $shared = [];
        if (! empty($meta['shared_words']) && is_array($meta['shared_words'])) {
            foreach ($meta['shared_words'] as $w) {
                $w = trim((string) $w);
                if ($w !== '') {
                    $shared[] = $w;
                }
            }
        }
        // Страховка UI: старые findings могли сохранить chrome (blog/prime/without).
        if ($shared !== []) {
            $chrome = [];
            foreach (SiteAuditTitleChrome::fillerTokens() as $t) {
                $chrome[$t] = true;
            }
            foreach (SiteAuditTitleChrome::tokensFromTitle((string) ($meta['title'] ?? '')) as $tw) {
                $chrome[$tw] = true;
            }
            $shared = SiteAuditTitleChrome::withoutChrome($shared, $chrome);
        }
        $sharedSource = (string) ($meta['shared_source'] ?? '');

        if ($thisUrl === '' && $similar === '' && $hamming === null && $words <= 0 && $simWords <= 0 && $shared === []) {
            return null;
        }

        $html = '<div class="cabinet-sa-similar">';
        $html .= '<div class="cabinet-sa-similar__head">Похожая пара страниц</div>';
        $html .= '<div class="cabinet-sa-similar__pair">';

        $html .= self::similarPagesSideHtml('A', 'Эта страница', $thisUrl, $words);
        $html .= '<div class="cabinet-sa-similar__vs" aria-hidden="true">≈</div>';
        $html .= self::similarPagesSideHtml('B', 'Похожая на неё', $similar, $simWords);

        $html .= '</div>';

        if ($hamming !== null) {
            $closeness = self::similarPagesClosenessLabel($hamming, $threshold);
            $html .= '<div class="cabinet-sa-similar__metric">'
                . '<span class="cabinet-sa-similar__pill">' . e($closeness) . '</span>'
                . '<span class="cabinet-sa-similar__metric-text">'
                . 'отпечатки текста отличаются на <strong>' . $hamming . '</strong> бит из 64'
                . ' · порог отчёта ≤ ' . $threshold
                . ' (0 — почти копия, чем больше число — тем слабее сходство)'
                . '</span></div>';
        }

        $shingleOverlap = isset($meta['shingle_overlap']) ? (float) $meta['shingle_overlap'] : null;
        $shingleSize = (int) ($meta['shingle_size'] ?? config('site_audit.simhash_shingle_size', 5));
        $sharedShingles = [];
        if (! empty($meta['shared_shingles']) && is_array($meta['shared_shingles'])) {
            foreach ($meta['shared_shingles'] as $s) {
                $s = trim((string) $s);
                if ($s !== '') {
                    $sharedShingles[] = $s;
                }
            }
        }
        if ($shingleOverlap !== null) {
            $pct = rtrim(rtrim(number_format($shingleOverlap * 100, 1, ',', ''), '0'), ',');
            $html .= '<div class="cabinet-sa-similar__metric">'
                . '<span class="cabinet-sa-similar__pill cabinet-sa-similar__pill--shingle">'
                . e($shingleSize) . '-граммы: ' . e($pct) . '%'
                . '</span>'
                . '<span class="cabinet-sa-similar__metric-text">'
                . 'общих фраз из ' . e((string) $shingleSize) . ' слов подряд: <strong>'
                . number_format((int) ($meta['shingle_shared'] ?? count($sharedShingles)), 0, '', ' ')
                . '</strong>'
                . ' · второй фильтр отсекает пары, где совпал в основном шаблон сайта'
                . '</span></div>';
            if ($sharedShingles !== []) {
                $html .= '<div class="cabinet-sa-similar__shared">'
                    . '<div class="cabinet-sa-similar__shared-label">Общие ' . e((string) $shingleSize)
                    . '-граммы <span class="cabinet-sa-similar__shared-note">(фразы подряд)</span></div>'
                    . '<div class="cabinet-sa-similar__chips">';
                foreach (array_slice($sharedShingles, 0, 8) as $s) {
                    $html .= '<span class="cabinet-sa-similar__chip cabinet-sa-similar__chip--shingle">'
                        . e($s) . '</span>';
                }
                $html .= '</div></div>';
            }
        }

        if ($shared !== []) {
            $html .= '<div class="cabinet-sa-similar__shared">';
            $html .= '<div class="cabinet-sa-similar__shared-label">Общие слова'
                . ($sharedSource === 'meta'
                    ? ' <span class="cabinet-sa-similar__shared-note">(по title/H1/описанию — полный список из тела после нового обхода)</span>'
                    : ' <span class="cabinet-sa-similar__shared-note">(частые в тексте обеих страниц)</span>')
                . '</div>';
            $html .= '<div class="cabinet-sa-similar__chips">';
            foreach (array_slice($shared, 0, 24) as $w) {
                $html .= '<span class="cabinet-sa-similar__chip">' . e($w) . '</span>';
            }
            $html .= '</div>';
            $html .= '</div>';
        } else {
            $html .= '<div class="cabinet-sa-similar__shared cabinet-sa-similar__shared--empty">'
                . 'Общие слова не сохранены — сделайте новый обход, чтобы увидеть пересечение лексики.'
                . '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * @param  ?int  $wordCount
     */
    private static function similarPagesSideHtml(string $mark, string $label, string $url, int $wordCount): string
    {
        $html = '<div class="cabinet-sa-similar__side">';
        $html .= '<div class="cabinet-sa-similar__side-top">'
            . '<span class="cabinet-sa-similar__mark">' . e($mark) . '</span>'
            . '<span class="cabinet-sa-similar__side-label">' . e($label) . '</span>';
        if ($wordCount > 0) {
            $html .= '<span class="cabinet-sa-similar__wc">'
                . number_format($wordCount, 0, '', ' ') . ' слов</span>';
        }
        $html .= '</div>';
        if ($url !== '') {
            $html .= '<a class="cabinet-sa-similar__link cabinet-sa-url-break" href="'
                . e($url) . '" target="_blank" rel="noopener noreferrer">'
                . e($url) . '</a>';
        } else {
            $html .= '<span class="cabinet-sa-similar__missing">URL не сохранён</span>';
        }
        $html .= '</div>';

        return $html;
    }

    private static function similarPagesClosenessLabel(int $hamming, int $threshold): string
    {
        if ($hamming <= 0) {
            return 'почти идентичны';
        }
        if ($hamming <= max(1, (int) floor($threshold / 3))) {
            return 'очень похожи';
        }
        if ($hamming <= $threshold) {
            return 'похожи';
        }

        return 'слабое сходство';
    }

    /**
     * @param  string[]  $variants
     */
    private static function describeUrlVariantDiff(array $variants): string
    {
        if (count($variants) < 2) {
            return '';
        }
        $bits = [];
        $schemes = [];
        $hosts = [];
        $slash = [];
        foreach ($variants as $v) {
            $p = parse_url($v);
            if (! is_array($p)) {
                continue;
            }
            $schemes[strtolower((string) ($p['scheme'] ?? ''))] = true;
            $hosts[strtolower((string) ($p['host'] ?? ''))] = true;
            $path = (string) ($p['path'] ?? '/');
            $slash[$path !== '/' && substr($path, -1) === '/' ? 'со /' : 'без /'] = true;
        }
        if (count($schemes) > 1) {
            $bits[] = 'http ↔ https';
        }
        if (count($hosts) > 1) {
            $www = false;
            $apex = false;
            foreach (array_keys($hosts) as $h) {
                if (strpos($h, 'www.') === 0) {
                    $www = true;
                } else {
                    $apex = true;
                }
            }
            $bits[] = ($www && $apex) ? 'www ↔ без www' : 'разный хост';
        }
        if (count($slash) > 1) {
            $bits[] = 'со слэшем ↔ без';
        }
        if ($bits === []) {
            $bits[] = 'разный путь/query';
        }

        return implode(', ', $bits);
    }

    public static function googleSearchUrl(string $query): string
    {
        return 'https://www.google.com/search?q=' . rawurlencode($query);
    }

    public static function metaLine(string $code, $meta, ?string $url = null): string
    {
        if (! is_array($meta) || ! $meta) {
            return '—';
        }

        switch ($code) {
            case 'duplicate_title':
            case 'duplicate_description':
            case 'duplicate_content':
                return self::duplicatePeersPlain($code, $meta, $url);

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
                $field = strpos($code, 'description_') === 0 ? 'description' : 'title';
                $text = trim((string) ($meta[$field] ?? ''));
                if ($text !== '') {
                    $bits[] = self::clip($text, 120);
                }

                return $bits ? implode(' · ', $bits) : '—';

            case 'title_equals_h1':
                return ! empty($meta['h1']) ? ('H1: ' . self::clip($meta['h1'], 80)) : '—';

            case 'title_equals_description':
                $t = ! empty($meta['title']) ? self::clip((string) $meta['title'], 70) : '';
                $d = ! empty($meta['description']) ? self::clip((string) $meta['description'], 70) : $t;
                if ($t === '' && $d === '') {
                    return 'TITLE = Description';
                }
                if ($t !== '' && $d !== '' && mb_strtolower($t) === mb_strtolower($d)) {
                    return 'TITLE = DESC · «' . $t . '»';
                }

                return 'TITLE: «' . $t . '» · DESC: «' . $d . '»';

            case 'description_equals_h1':
                return ! empty($meta['h1']) ? ('H1: ' . self::clip($meta['h1'], 80)) : '—';

            case 'multiple_title_or_description':
                return self::multipleTitleDescriptionPlain($meta);

            case 'missing_h1':
                return 'нет H1';

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
                $n = (int) ($meta['issue_count'] ?? count($meta['issues'] ?? []));
                if ($n > 0) {
                    $bits[] = $n === 1 ? '1 нарушение' : ($n . ' нарушений');
                }
                foreach (array_slice($meta['issues'] ?? [], 0, 2) as $issue) {
                    if (! is_array($issue)) {
                        continue;
                    }
                    $type = (string) ($issue['type'] ?? '');
                    if ($type === 'before_h1') {
                        $bits[] = 'H' . (int) ($issue['level'] ?? 0) . ' до первого H1'
                            . (! empty($issue['text']) ? (' «' . self::clip($issue['text'], 40) . '»') : '');
                    } elseif ($type === 'skip') {
                        $bits[] = 'пропуск H' . (int) ($issue['from'] ?? 0)
                            . '→H' . (int) ($issue['to'] ?? 0)
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
                return self::duplicateLinksPlain($meta);

            case 'external_links':
                return self::externalLinksPlain($meta);

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
                $bits = [];
                if (! empty($meta['word'])) {
                    $bits[] = '«' . (string) $meta['word'] . '»×' . (int) ($meta['count'] ?? 0);
                }
                if (! empty($meta['h1'])) {
                    $bits[] = self::clip((string) $meta['h1'], 200);
                }

                return $bits !== [] ? implode(' · ', $bits) : '—';

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
                return self::noUniqueImagesPlain($meta);

            case 'text_in_noindex':
                return self::textInNoindexPlain($meta);

            case 'images_without_alt':
                return self::imagesWithoutAltPlain($meta);

            case 'redirect':
            case 'redirect_chain_long':
            case 'redirect_loop':
                $chain = ! empty($meta['chain']) && is_array($meta['chain']) ? $meta['chain'] : [];
                $final = ! empty($meta['final']) ? (string) $meta['final'] : null;
                $start = $url ?: (! empty($meta['path'][0]) ? (string) $meta['path'][0] : '');
                if ($start === '' && ! empty($meta['path']) && is_array($meta['path'])) {
                    return SiteAuditRedirectChain::formatDetails(
                        (string) $meta['path'][0],
                        array_slice($meta['path'], 1),
                        $final,
                        64,
                        $code === 'redirect_loop'
                    );
                }
                if ($start !== '') {
                    return SiteAuditRedirectChain::formatDetails(
                        $start,
                        $chain,
                        $final,
                        64,
                        $code === 'redirect_loop'
                    );
                }
                // Старые finding без start URL в meta
                if ($chain) {
                    $line = implode(' → ', array_map(function ($u) {
                        return self::clip((string) $u, 64);
                    }, $chain));
                    if ($code === 'redirect_loop') {
                        $line .= ' · цикл';
                    }
                    $line .= ' · шагов: ' . count($chain);

                    return $line;
                }
                if ($final) {
                    return '→ ' . self::clip($final, 90);
                }

                return '—';

            case 'http_4xx':
            case 'http_5xx':
                $bits = [];
                if (isset($meta['status'])) {
                    $bits[] = 'код ' . (int) $meta['status'];
                }
                $refN = (int) ($meta['referrer_count'] ?? 0);
                if ($refN > 0) {
                    // URL страниц со ссылкой — в колонке «Страница со ссылкой», не дублируем здесь
                    // (длинный URL + nowrap ломал таблицу).
                    $bits[] = 'ссылаются: ' . $refN;
                } elseif (array_key_exists('referrer_count', $meta)) {
                    $bits[] = 'ссылок с страниц проверки нет';
                }
                if (! empty($meta['slash_hint']) || ! empty($meta['false_404_slash'])) {
                    // Не тащим в CSV кривой slash_url от мусорного href
                    if (! self::urlLooksBrokenHref((string) ($url ?? ''))
                        && ! self::urlLooksBrokenHref(trim((string) ($meta['slash_url'] ?? '')))) {
                        $slashUrl = trim((string) ($meta['slash_url'] ?? ''));
                        $bits[] = $slashUrl !== ''
                            ? ('в sitemap часто со слэшем: ' . self::clip($slashUrl, 60))
                            : 'возможен ложный 404: страница жива со слэшем в конце';
                    }
                }

                return $bits ? implode(' · ', $bits) : '—';

            case 'unreachable':
                $bits = [];
                if (! empty($meta['retried']) || (isset($meta['attempts']) && is_array($meta['attempts']) && count($meta['attempts']) >= 2)) {
                    $bits[] = '2 обхода';
                    $a = is_array($meta['attempts'] ?? null) ? $meta['attempts'] : [];
                    $l1 = trim((string) ($a[0]['label'] ?? ''));
                    $l2 = trim((string) ($a[1]['label'] ?? ''));
                    if ($l1 === '') {
                        $l1 = self::connectionErrorLabel((string) ($meta['error_first'] ?? $meta['error'] ?? ''));
                    }
                    if ($l2 === '') {
                        $l2 = self::connectionErrorLabel((string) ($meta['error'] ?? ''));
                    }
                    $bits[] = '1: ' . $l1 . ' → 2: ' . $l2;
                } elseif (! empty($meta['error'])) {
                    $bits[] = self::clip((string) $meta['error'], 40);
                }
                $refN = (int) ($meta['referrer_count'] ?? 0);
                if ($refN > 0) {
                    $bits[] = 'ссылаются: ' . $refN;
                } elseif (array_key_exists('referrer_count', $meta)) {
                    $bits[] = 'ссылок с страниц проверки нет';
                }

                return $bits ? implode(' · ', $bits) : '—';

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
                    $bits[] = 'meta robots: ' . $meta['robots'];
                }
                if (! empty($meta['x_robots'])) {
                    $bits[] = 'X-Robots-Tag: ' . $meta['x_robots'];
                }

                return $bits ? implode(' · ', $bits) : 'noindex';

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
                $directive = trim((string) ($meta['directive'] ?? 'Disallow'));
                if ($directive === '') {
                    $directive = 'Disallow';
                }
                $rule = (string) ($meta['rule'] ?? $meta['path'] ?? '');
                $agent = trim((string) ($meta['agent'] ?? '*'));
                if ($agent === '') {
                    $agent = '*';
                }
                if ($rule !== '') {
                    return $directive . ': ' . $rule
                        . ($agent !== '*' ? (' · User-agent: ' . $agent) : '');
                }

                return 'закрыт правилом robots.txt';

            case 'sitemap_missing':
                $tried = is_array($meta['tried'] ?? null) ? array_values(array_filter($meta['tried'])) : [];
                if ($tried !== []) {
                    $short = [];
                    foreach (array_slice($tried, 0, 3) as $u) {
                        $short[] = self::clip((string) $u, 52);
                    }
                    $extra = count($tried) > 3
                        ? (' · ещё ' . number_format(count($tried) - 3, 0, '', ' '))
                        : '';

                    return 'на сайте нет рабочей карты · проверено: ' . implode(', ', $short) . $extra;
                }

                return 'на сайте нет рабочей карты (sitemap.xml / Sitemap: в robots.txt)';

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
                return 'страница отсутствует в карте сайта';

            case 'sitemap_not_crawled':
                $reason = (string) ($meta['reason'] ?? '');
                if ($reason === 'crawl_save_gap') {
                    return 'сбой сохранения страниц в проверке';
                }
                if ($reason === 'likely_robots_or_not_queued') {
                    return 'не в очереди (robots / фильтр)';
                }
                if ($reason === 'pages_limit' || isset($meta['pages_limit'])) {
                    return 'лимит проверки: ' . number_format((int) ($meta['pages_limit'] ?? 0), 0, '', ' ');
                }

                return 'не в проверке';

            case 'landing_not_in_sitemap':
                return 'посадочная отсутствует в карте сайта';

            case 'landing_not_crawled':
                $reason = (string) ($meta['reason'] ?? '');
                $fetched = (int) ($meta['pages_fetched'] ?? 0);
                $planned = (int) ($meta['pages_total'] ?? $meta['seed_count'] ?? 0);
                $limit = (int) ($meta['pages_limit'] ?? 0);
                // «лимит 100 000» — потолок настройки, не размер сайта; показываем только если упёрлись в лимит.
                if ($reason === 'pages_limit' || ($limit > 0 && $fetched > 0 && $fetched >= $limit)) {
                    return 'посадочная · не в проверке · упёрлись в лимит '
                        . number_format($limit, 0, '', ' ');
                }
                if ($fetched > 0) {
                    return 'посадочная · не в обходе · проверено '
                        . number_format($fetched, 0, '', ' ') . ' стр.';
                }
                if ($planned > 0) {
                    return 'посадочная · не в обходе · в проверке '
                        . number_format($planned, 0, '', ' ') . ' стр.';
                }
                // Старые findings: в meta только pages_limit (тарифный потолок) — не путаем с «было 100к».
                return 'посадочная · не в обходе';

            case 'landing_url_changed':
                $q = trim((string) ($meta['query'] ?? ''));
                // old_url/new_url — прод; expected/landing_url — старые ключи демо
                $old = trim((string) ($meta['old_url'] ?? $meta['expected'] ?? ''));
                $new = trim((string) ($meta['new_url'] ?? $meta['landing_url'] ?? ''));
                $prefix = $q !== '' ? ('«' . self::clip($q, 40) . '» · ') : '';
                if ($old !== '' && $new !== '' && $old !== $new) {
                    return $prefix . 'было ' . self::clip($old, 80) . ' → стало ' . self::clip($new, 80);
                }
                if ($old !== '') {
                    return $prefix . 'было ' . self::clip($old, 100);
                }

                return $prefix !== '' ? ($prefix . 'URL посадочной изменился') : 'URL посадочной изменился';

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
                return self::insecureFormPlain($meta);

            case 'bad_doctype':
                $reason = (string) ($meta['reason'] ?? '');
                if ($reason === 'missing') {
                    return 'DOCTYPE отсутствует';
                }
                if ($reason === 'legacy') {
                    return 'устаревший DOCTYPE'
                        . (! empty($meta['doctype']) ? (': ' . self::clip((string) $meta['doctype'], 60)) : '');
                }

                return ! empty($meta['doctype'])
                    ? ('DOCTYPE: ' . self::clip((string) $meta['doctype'], 80))
                    : '—';

            case 'pages_with_canonical':
                return ! empty($meta['canonical']) ? self::clip((string) $meta['canonical'], 100) : '—';

            case 'crawl_pages':
                $bits = [];
                if (isset($meta['status_code']) && $meta['status_code'] !== null && $meta['status_code'] !== '') {
                    $bits[] = (string) (int) $meta['status_code'];
                }
                if (! empty($meta['title'])) {
                    $bits[] = self::clip((string) $meta['title'], 48);
                }
                $img = (int) ($meta['img_count'] ?? 0);
                if ($img > 0) {
                    $bits[] = 'img: ' . number_format($img, 0, '', ' ');
                }
                $words = (int) ($meta['word_count'] ?? 0);
                if ($words > 0) {
                    $bits[] = 'слов: ' . number_format($words, 0, '', ' ');
                }

                return $bits !== [] ? implode(' · ', $bits) : 'страница проверки';

            case 'crawl_images':
                $page = ! empty($meta['page_url']) ? (string) $meta['page_url'] : '';

                return $page !== '' ? ('на странице: ' . self::clip($page, 70)) : 'изображение';

            case 'similar_pages':
                $parts = [];
                $sim = trim((string) ($meta['similar_url'] ?? ''));
                if ($sim !== '') {
                    $parts[] = 'пара с ' . self::clip($sim, 80);
                }
                if (isset($meta['hamming'])) {
                    $h = (int) $meta['hamming'];
                    $parts[] = 'отличаются на ' . $h . ' бит из 64';
                }
                $words = (int) ($meta['word_count'] ?? 0);
                $simWords = (int) ($meta['similar_word_count'] ?? 0);
                if ($words > 0 || $simWords > 0) {
                    $parts[] = 'слов: '
                        . number_format($words, 0, '', ' ')
                        . ' / '
                        . number_format($simWords, 0, '', ' ')
                        . ' (эта / похожая)';
                }
                if (! empty($meta['shared_words']) && is_array($meta['shared_words'])) {
                    $sw = [];
                    foreach (array_slice($meta['shared_words'], 0, 8) as $w) {
                        $w = trim((string) $w);
                        if ($w !== '') {
                            $sw[] = $w;
                        }
                    }
                    if ($sw !== []) {
                        $parts[] = 'общие: ' . implode(', ', $sw);
                    }
                }
                if (isset($meta['shingle_overlap'])) {
                    $parts[] = '5-граммы: '
                        . rtrim(rtrim(number_format((float) $meta['shingle_overlap'] * 100, 1, ',', ''), '0'), ',')
                        . '%';
                }

                return $parts ? implode(' · ', $parts) : '—';

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
                return self::nofollowLinksPlain($meta);

            case 'page_has_broken_external_links':
                return self::brokenExternalPagePlain($meta);

            case 'broken_external_link':
                return self::brokenExternalLinkPlain($meta);

            case 'external_assets':
                return self::externalAssetsPlain($meta);

            case 'soft_404':
                return self::soft404Plain($meta);

            case 'orphan_pages':
                return 'нет входящих ссылок в проверке';

            case 'duplicate_url_variants':
                $n = (int) ($meta['count'] ?? count($meta['variants'] ?? []));
                $variants = isset($meta['variants']) && is_array($meta['variants']) ? $meta['variants'] : [];
                if ($variants) {
                    return 'вариантов: ' . $n . ' · ' . implode(' | ', array_map(function ($v) {
                        return (string) $v;
                    }, $variants));
                }

                return $n ? ('вариантов: ' . $n) : 'дубль URL';

            case 'www_both_available':
                return self::wwwBothAvailablePlain($meta);

            case 'http_https_both_available':
                return self::httpHttpsBothAvailablePlain($meta);

            case 'page_has_broken_links':
                $n = (int) ($meta['count'] ?? 0);
                $sample = ! empty($meta['samples'][0]['url'])
                    ? ' · ' . self::clip((string) $meta['samples'][0]['url'], 60)
                    : '';

                return $n ? ('битых: ' . $n . $sample) : '—';

            case 'broken_internal_link':
                // URL источника — в колонке «Откуда», здесь только статус ответа.
                if (isset($meta['status'])) {
                    $bits = ['HTTP ' . (int) $meta['status']];
                    $refN = (int) ($meta['referrer_count'] ?? 0);
                    if ($refN > 1) {
                        $bits[] = 'ссылок: ' . $refN;
                    }

                    return implode(' · ', $bits);
                }

                return 'битая ссылка';

            case 'page_has_bad_links':
                $n = (int) ($meta['count'] ?? 0);
                $sample = is_array($meta['samples'][0] ?? null) ? $meta['samples'][0] : [];
                $bits = [];
                if ($n > 0) {
                    $bits[] = 'плохих: ' . $n;
                }
                $reason = self::badLinkReasonLabel((string) ($sample['reason'] ?? ''));
                if ($reason !== '') {
                    $bits[] = $reason;
                }
                $href = trim((string) ($sample['href'] ?? ''));
                if ($href !== '') {
                    $bits[] = self::clip($href, 50);
                }
                $text = trim((string) ($sample['text'] ?? ''));
                if ($text !== '') {
                    $bits[] = '«' . self::clip($text, 40) . '»';
                } elseif ($href === '' && ($sample['reason'] ?? '') === 'missing_href') {
                    $snip = trim((string) ($sample['snippet'] ?? ''));
                    if ($snip !== '') {
                        $bits[] = self::clip($snip, 55);
                    }
                }

                return $bits ? implode(' · ', $bits) : 'плохие ссылки';

            case 'html_critical_errors':
                $n = (int) ($meta['count'] ?? 0);
                $msg = ! empty($meta['samples'][0]['message'])
                    ? self::clip((string) $meta['samples'][0]['message'], 70)
                    : '';
                $checker = ! empty($meta['checker'])
                    ? SiteAuditHtmlChecker::label((string) $meta['checker'])
                    : '';
                $bits = [];
                if ($n) {
                    $bits[] = 'ошибок: ' . $n;
                }
                if ($checker !== '') {
                    $bits[] = $checker;
                }
                if (! empty($meta['checker_fallback'])) {
                    $bits[] = 'fallback libxml';
                }
                if ($msg !== '') {
                    $bits[] = $msg;
                }

                return $bits ? implode(' · ', $bits) : 'ошибки HTML';

            case 'lost_file':
                $asset = ! empty($meta['asset']) ? self::clip((string) $meta['asset'], 55) : '';
                $st = isset($meta['status']) ? ('HTTP ' . (int) $meta['status']) : 'недоступен';

                return $asset !== ''
                    ? ('CSS/JS не найден · ' . $st . ' · ' . $asset)
                    : ('CSS/JS не найден · ' . $st);

            case 'adult_content':
                $hits = isset($meta['hits']) && is_array($meta['hits'])
                    ? implode(', ', array_slice($meta['hits'], 0, 4))
                    : '';

                return $hits !== '' ? ('слова: ' . $hits) : ('совпадений: ' . (int) ($meta['score'] ?? 0));

            case 'negative_content':
                $hits = isset($meta['hits']) && is_array($meta['hits'])
                    ? implode(', ', array_slice($meta['hits'], 0, 4))
                    : '';

                return $hits !== '' ? ('слова: ' . $hits) : ('совпадений: ' . (int) ($meta['score'] ?? 0));

            case 'word_repeat_in_sentence':
                $w = ! empty($meta['samples'][0]['word'])
                    ? (string) $meta['samples'][0]['word']
                    : '';
                $c = (int) ($meta['samples'][0]['count'] ?? $meta['count'] ?? 0);

                return $w !== '' ? ($w . ' ×' . $c) : ('повторов: ' . (int) ($meta['count'] ?? 0));

            case 'landing_plagiarism_external':
                $pct = self::plagiarismExternalUniquenessPct($meta);
                $u = $pct !== null ? ($pct . '%') : '';
                $top = self::plagiarismExternalTopSource($meta);
                $top = $top !== '' ? self::clip($top, 40) : '';

                return trim($u . ($top !== '' ? (' · ' . $top) : ''));

            case 'landing_no_inbound_internal':
                return 'входящих внутренних: 0';

            case 'keyword_cannibalization':
                return self::keywordCannibalizationPlain($meta);

            case 'ad_cannibalization':
                $q = ! empty($meta['query']) ? self::clip((string) $meta['query'], 36) : '';
                $hint = self::adCannibalizationHintLabel((string) ($meta['ad_hint'] ?? ''));
                $land = ! empty($meta['landing_url']) ? self::clip((string) $meta['landing_url'], 30) : '';

                return ($q !== '' ? ('«' . $q . '»') : 'запрос')
                    . ($hint !== '' ? (' · ' . $hint) : '')
                    . ($land !== '' ? (' · SEO: ' . $land) : '');

            case 'serp_snippet_cannibalization':
                $q = ! empty($meta['query']) ? self::clip((string) $meta['query'], 36) : '';
                $eng = ! empty($meta['engine']) ? (string) $meta['engine'] : '';
                $pos = isset($meta['position']) ? ('#' . (int) $meta['position']) : '';
                $n = (int) ($meta['own_count'] ?? 0);

                return trim(($q !== '' ? ('«' . $q . '»') : 'запрос')
                    . ($eng !== '' ? (' · ' . $eng) : '')
                    . ($pos !== '' ? (' · ' . $pos) : '')
                    . ($n > 0 ? (' · своих в ТОП: ' . $n) : ''));

            case 'landing_query_mismatch':
                $q = ! empty($meta['query']) ? self::clip((string) $meta['query'], 40) : '';
                $hits = isset($meta['hits_any'], $meta['token_count'])
                    ? ((int) $meta['hits_any'] . '/' . (int) $meta['token_count'] . ' токенов')
                    : '';

                return ($q !== '' ? ('«' . $q . '»') : 'запрос')
                    . ($hits !== '' ? (' · ' . $hits) : '');

            case 'commercial_missing_contacts':
                return self::commercialMissingContactsPlain($meta);

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
                return self::brokenImagePlain($meta);

            case 'heavy_image':
                return self::heavyImagePlain($meta);

            case 'error_spike':
                $kind = (string) ($meta['kind'] ?? '');
                if ($kind === 'status_cluster') {
                    return 'один код HTTP ' . ($meta['status'] ?? '?')
                        . ': ' . number_format((int) ($meta['count'] ?? 0), 0, '', ' ')
                        . ' из ' . number_format((int) ($meta['error_total'] ?? 0), 0, '', ' ')
                        . ' ошибок';
                }
                if ($kind === 'path_cluster') {
                    $pct = isset($meta['rate']) ? (int) round(((float) $meta['rate']) * 100) : null;

                    return 'раздел ' . ($meta['path_prefix'] ?? '/')
                        . ' · ' . number_format((int) ($meta['count'] ?? 0), 0, '', ' ')
                        . ' из ' . number_format((int) ($meta['prefix_total'] ?? 0), 0, '', ' ')
                        . ' URL с ошибкой'
                        . ($pct !== null ? (' (' . $pct . '%)') : '');
                }
                if ($kind === 'crawl_delta') {
                    return 'рост к прошлой проверке: было '
                        . number_format((int) ($meta['prev_count'] ?? 0), 0, '', ' ')
                        . ' → стало ' . number_format((int) ($meta['count'] ?? 0), 0, '', ' ')
                        . (isset($meta['ratio']) ? (' (×' . $meta['ratio'] . ')') : '');
                }

                return 'выброс ошибок';

            case 'psi_mobile':
            case 'psi_desktop':
                return \App\Services\SiteAudit\SiteAuditPsiMetrics::compactLine(is_array($meta) ? $meta : []);

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
                if (($meta['kind'] ?? '') === 'missing_url') {
                    $bits = [];
                    $engine = (string) ($meta['engine'] ?? 'yandex');
                    $bits[] = $engine === 'yandex' ? 'Яндекс' : ($engine === 'google' ? 'Google' : $engine);
                    if (($meta['source'] ?? '') === 'webmaster') {
                        $bits[] = 'Вебмастер';
                    } elseif (($meta['source'] ?? '') === 'gsc') {
                        $bits[] = 'GSC';
                    }
                    $bits[] = 'нет в индексе';
                    // Предупреждение про неполный список — один раз в зелёном блоке сверху, не в каждой строке.

                    return implode(' · ', $bits);
                }
                $engine = (string) ($meta['engine'] ?? '');
                $bits = [];
                if ($engine === 'yandex') {
                    $bits[] = 'Яндекс';
                } elseif ($engine === 'google') {
                    $bits[] = 'Google';
                } elseif ($engine !== '') {
                    $bits[] = $engine;
                }
                $source = (string) ($meta['source'] ?? '');
                if ($source === 'webmaster') {
                    $bits[] = 'Вебмастер';
                } elseif ($source === 'xml_site') {
                    $bits[] = 'устаревший источник';
                }
                if (! empty($meta['needs_webmaster']) && empty($meta['deep'])) {
                    $bits[] = 'нужен Вебмастер';
                }
                if (! empty($meta['deep']) && (($meta['mode'] ?? '') === 'site_list'
                    || ($meta['mode'] ?? '') === 'webmaster_list'
                    || isset($meta['serp_count']))) {
                    $matched = (int) ($meta['matched'] ?? 0);
                    $crawlN = (int) ($meta['crawl_count'] ?? $meta['pages_total'] ?? 0);
                    $serpN = (int) ($meta['serp_count'] ?? 0);
                    $bits[] = 'список ' . $serpN . ' URL';
                    $bits[] = 'совпало ' . $matched . '/' . max(1, $crawlN);
                    $miss = (int) ($meta['missing_in_index'] ?? 0);
                    $extra = (int) ($meta['extra_in_index'] ?? 0);
                    if ($miss > 0) {
                        $bits[] = 'нет в индексе: ' . $miss;
                    }
                    if ($extra > 0) {
                        $bits[] = 'лишние в индексе: ' . $extra;
                    }
                    if (! empty($meta['truncated'])) {
                        $bits[] = 'список обрезан';
                    }

                    return $bits ? implode(' · ', $bits) : 'сверка индекса';
                }
                if (! empty($meta['deep'])) {
                    $si = (int) ($meta['sample_indexed'] ?? 0);
                    $sm = (int) ($meta['sample_missing'] ?? 0);
                    $sample = (int) ($meta['sample'] ?? ($si + $sm));
                    $bits[] = 'выборка ' . $si . '/' . max(1, $sample) . ' в индексе';
                    if (isset($meta['estimate'])) {
                        $bits[] = 'оценка ~' . (int) $meta['estimate']
                            . (isset($meta['pages_total']) ? (' из ' . (int) $meta['pages_total']) : '');
                    }

                    return $bits ? implode(' · ', $bits) : 'выборка индекса';
                }
                $indexed = isset($meta['indexed']) ? (int) $meta['indexed'] : null;
                $pages = isset($meta['pages_total']) ? (int) $meta['pages_total'] : null;
                $ratio = isset($meta['ratio']) ? round((float) $meta['ratio'], 2) : null;
                if (! empty($meta['ok_count']) && $indexed !== null && $pages !== null) {
                    $bits[] = 'в поиске ' . $indexed . ' · проверка ' . $pages;

                    return $bits ? implode(' · ', $bits) : 'индекс ≈ проверка';
                }
                if (! empty($meta['capped'])) {
                    $bits[] = 'потолок site: ~' . ($indexed ?? 100);
                    if ($pages !== null) {
                        $bits[] = 'проверка ' . $pages;
                    }
                    $bits[] = 'нужна сверка списка';

                    return $bits ? implode(' · ', $bits) : 'оценка site: ограничена';
                }
                if ($indexed !== null && $pages !== null) {
                    $bits[] = 'индекс ' . $indexed . ' vs проверка ' . $pages;
                }
                if ($ratio !== null) {
                    $bits[] = '×' . $ratio;
                }

                return $bits ? implode(' · ', $bits) : 'расхождение индекса и проверки';

            case 'serp_snippets':
                $bits = [];
                if (! empty($meta['page_title'])) {
                    $bits[] = 'на сайте: ' . self::clip((string) $meta['page_title'], 40);
                }
                $engines = isset($meta['engines']) && is_array($meta['engines']) ? $meta['engines'] : null;
                // Старое демо: {engine, snippet} без engines.
                if (($engines === null || $engines === []) && (! empty($meta['engine']) || ! empty($meta['snippet']))) {
                    $eng = (string) ($meta['engine'] ?? 'yandex');
                    $engines = [
                        $eng => [
                            'indexed' => true,
                            'title' => $meta['serp_title'] ?? $meta['title'] ?? null,
                            'snippet' => $meta['snippet'] ?? null,
                        ],
                    ];
                }
                foreach ((array) $engines as $eng => $block) {
                    if (! is_array($block)) {
                        continue;
                    }
                    $label = $eng === 'yandex' ? 'Яндекс' : ($eng === 'google' ? 'Google' : (string) $eng);
                    if (! empty($block['error'])) {
                        $bits[] = $label . ': ошибка';
                        continue;
                    }
                    if (array_key_exists('indexed', $block) && empty($block['indexed'])) {
                        $bits[] = $label . ': нет в поиске';
                        continue;
                    }
                    $title = trim((string) ($block['title'] ?? ''));
                    $snip = trim((string) ($block['snippet'] ?? ''));
                    if ($title !== '') {
                        $bits[] = $label . ': ' . self::clip($title, 50);
                    } elseif ($snip !== '') {
                        $bits[] = $label . ': ' . self::clip($snip, 60);
                    } else {
                        $bits[] = $label . ': есть в поиске';
                    }
                }

                return $bits ? implode(' · ', $bits) : 'как в поиске';

            case 'serp_title_mismatch':
                $bits = [];
                if (! empty($meta['engine'])) {
                    $eng = (string) $meta['engine'];
                    $bits[] = $eng === 'yandex' ? 'Яндекс' : ($eng === 'google' ? 'Google' : $eng);
                }
                if (! empty($meta['page_title'])) {
                    $bits[] = 'на сайте: ' . self::clip((string) $meta['page_title'], 90);
                }
                if (! empty($meta['serp_title'])) {
                    $bits[] = 'в выдаче: ' . self::clip((string) $meta['serp_title'], 90);
                }

                return $bits ? implode(' · ', $bits) : 'title ≠ выдача';

            case 'serp_snippet_source':
                return self::serpSnippetSourcePlain($meta);

            case 'probable_affiliate':
                return self::probableAffiliatePlain($meta);

            case 'missing_permissions_policy':
                return 'нет Permissions-Policy';

            case 'missing_coop':
                return 'нет COOP';

            case 'missing_coep':
                return 'нет COEP';

            case 'missing_corp':
                return 'нет CORP';

            case 'multiple_canonical':
                return self::multipleCanonicalPlain($meta);

            case 'no_outbound_internal':
                return 'нет исходящих внутренних ссылок';

            case 'risky_query_params':
                return self::riskyQueryParamsPlain($meta, $url);

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

            case 'empty_title':
                return 'title пустой';

            case 'empty_description':
                return 'description пустой';

            case 'canonical_empty':
                return 'canonical отсутствует';

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
                return self::multipleH1Plain($meta);

            default:
                // discovered_* пишется в meta для колонки «Откуда», в «Детали» не показываем сырой JSON.
                $clean = $meta;
                foreach ([
                    'discovered_via',
                    'discovered_from',
                    'origin_label',
                    'origin_hint',
                    'from_sitemap',
                    'sitemap_href',
                    'referrers',
                    'referrer_count',
                    'slash_hint',
                    'slash_url',
                    'false_404_slash',
                ] as $drop) {
                    unset($clean[$drop]);
                }
                if ($clean === []) {
                    return '—';
                }

                return self::clip(json_encode($clean, JSON_UNESCAPED_UNICODE), 120);
        }
    }

    /**
     * Список nofollow: анкор → URL.
     *
     * @param  array<string, mixed>  $meta
     */
    private static function nofollowLinksDetailsHtml(array $meta): ?string
    {
        $samples = isset($meta['samples']) && is_array($meta['samples']) ? $meta['samples'] : [];
        $n = (int) ($meta['count'] ?? count($samples));
        if ($samples === [] && $n <= 0) {
            return $n > 0 ? ('nofollow-ссылок: ' . $n) : null;
        }

        $parts = [];
        if ($n > 0) {
            $parts[] = '<div class="mb-1"><strong>nofollow: ' . $n . '</strong></div>';
        }
        $parts[] = '<ul class="mb-0 ps-3 cabinet-sa-nofollow-list">';
        foreach (array_slice($samples, 0, 15) as $sample) {
            if (! is_array($sample)) {
                continue;
            }
            $href = trim((string) ($sample['href'] ?? $sample['url'] ?? ''));
            $text = trim((string) ($sample['text'] ?? ''));
            $scope = (string) ($sample['scope'] ?? '');
            if ($scope === 'internal') {
                $scopeBit = '<span class="cabinet-sa-link-scope cabinet-sa-link-scope--internal">внутр.</span> ';
            } elseif ($scope === 'external') {
                $scopeBit = '<span class="cabinet-sa-link-scope cabinet-sa-link-scope--external">внешн.</span> ';
            } else {
                $scopeBit = '';
            }
            $anchorBit = $text !== ''
                ? '<span class="cabinet-sa-nofollow-list__anchor">«' . e(self::clip($text, 70)) . '»</span>'
                : '<span class="text-muted">без анкора</span>';
            $urlBit = $href !== ''
                ? '<div class="cabinet-sa-details-stack__url">' . self::urlLinkHtml($href) . '</div>'
                : '';
            $parts[] = '<li class="cabinet-sa-nofollow-list__item">'
                . $scopeBit . $anchorBit
                . ($urlBit !== '' ? ' → ' . $urlBit : '')
                . '</li>';
        }
        $parts[] = '</ul>';
        if ($n > count($samples) && $samples !== []) {
            $parts[] = '<div class="text-secondary small mt-1">показаны '
                . count($samples) . ' из ' . $n . '</div>';
        }

        return implode('', $parts);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function nofollowLinksPlain(array $meta): string
    {
        $samples = isset($meta['samples']) && is_array($meta['samples']) ? $meta['samples'] : [];
        $n = (int) ($meta['count'] ?? count($samples));
        if ($n <= 0 && $samples === []) {
            return '—';
        }
        $bits = ['nofollow: ' . max($n, count($samples))];
        foreach (array_slice($samples, 0, 2) as $sample) {
            if (! is_array($sample)) {
                continue;
            }
            $text = trim((string) ($sample['text'] ?? ''));
            $href = trim((string) ($sample['href'] ?? $sample['url'] ?? ''));
            if ($text !== '' && $href !== '') {
                $bits[] = '«' . self::clip($text, 40) . '» → ' . self::clip($href, 50);
            } elseif ($href !== '') {
                $bits[] = self::clip($href, 60);
            }
        }

        return implode(' · ', $bits);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function brokenExternalPageDetailsHtml(array $meta): ?string
    {
        $samples = isset($meta['samples']) && is_array($meta['samples']) ? $meta['samples'] : [];
        $n = (int) ($meta['count'] ?? count($samples));
        if ($samples === [] && $n <= 0) {
            return null;
        }
        $parts = [];
        if ($n > 0) {
            $parts[] = '<div class="mb-1"><strong>битых внешних: ' . $n . '</strong></div>';
        }
        $parts[] = '<ul class="mb-0 ps-3">';
        foreach (array_slice($samples, 0, 12) as $sample) {
            if (! is_array($sample)) {
                continue;
            }
            $href = trim((string) ($sample['url'] ?? $sample['href'] ?? ''));
            $text = trim((string) ($sample['text'] ?? ''));
            $status = $sample['status'] ?? null;
            $statusBit = $status !== null && $status !== ''
                ? '<span class="badge badge-danger">' . e((string) $status) . '</span> '
                : '<span class="badge badge-secondary">ошибка</span> ';
            $anchorBit = $text !== ''
                ? '«' . e(self::clip($text, 50)) . '» → '
                : '';
            $parts[] = '<li>' . $statusBit . $anchorBit
                . ($href !== '' ? self::urlLinkHtml($href) : '—')
                . '</li>';
        }
        $parts[] = '</ul>';

        return implode('', $parts);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return list<array{url:string,count:int}>
     */
    private static function duplicateLinkSamples(array $meta): array
    {
        $raw = isset($meta['samples']) && is_array($meta['samples']) ? $meta['samples'] : [];
        $out = [];
        foreach ($raw as $item) {
            if (is_string($item)) {
                $u = trim($item);
                if ($u !== '') {
                    $out[] = ['url' => $u, 'count' => 2];
                }
                continue;
            }
            if (! is_array($item)) {
                continue;
            }
            $u = trim((string) ($item['url'] ?? ''));
            if ($u === '') {
                continue;
            }
            $out[] = [
                'url' => $u,
                'count' => max(2, (int) ($item['count'] ?? 2)),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function duplicateLinksPlain(array $meta): string
    {
        $samples = self::duplicateLinkSamples($meta);
        $n = (int) ($meta['count'] ?? 0);
        if ($n < 1) {
            $n = count($samples);
        }
        if ($n < 1 && $samples === []) {
            return '—';
        }

        $bits = [number_format(max($n, 1), 0, '', ' ') . ' URL с повторами на странице'];
        foreach (array_slice($samples, 0, 4) as $s) {
            $bits[] = self::clip($s['url'], 48) . ' ×' . number_format($s['count'], 0, '', ' ');
        }
        if (count($samples) > 4) {
            $bits[] = '… ещё ' . number_format(count($samples) - 4, 0, '', ' ');
        }

        return implode(' · ', $bits);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function duplicateLinksDetailsHtml(array $meta): ?string
    {
        $samples = self::duplicateLinkSamples($meta);
        $n = (int) ($meta['count'] ?? 0);
        if ($n < 1) {
            $n = count($samples);
        }
        if ($n < 1 && $samples === []) {
            return null;
        }

        $html = '<div class="cabinet-sa-duplinks">';
        $html .= '<div class="cabinet-sa-duplinks__head">'
            . '<span class="cabinet-sa-duplinks__count">' . number_format(max($n, 1), 0, '', ' ') . '</span>'
            . '<span class="cabinet-sa-duplinks__label"> URL с повторами на этой странице</span>'
            . '</div>';

        if ($samples !== []) {
            $html .= '<ul class="cabinet-sa-duplinks__list">';
            foreach ($samples as $s) {
                $html .= '<li class="cabinet-sa-duplinks__item">'
                    . '<span class="cabinet-sa-duplinks__times">×'
                    . number_format($s['count'], 0, '', ' ')
                    . '</span> '
                    . self::urlLinkHtml($s['url'])
                    . '</li>';
            }
            $html .= '</ul>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function brokenExternalPagePlain(array $meta): string
    {
        $n = (int) ($meta['count'] ?? 0);
        $samples = isset($meta['samples']) && is_array($meta['samples']) ? $meta['samples'] : [];
        if ($n <= 0 && $samples === []) {
            return '—';
        }
        $bits = ['битых внешних: ' . max($n, count($samples))];
        if ($samples) {
            $first = $samples[0];
            if (is_array($first)) {
                $u = (string) ($first['url'] ?? '');
                if ($u !== '') {
                    $bits[] = self::clip($u, 70);
                }
            }
        }

        return implode(' · ', $bits);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function brokenExternalLinkDetailsHtml(array $meta): ?string
    {
        return self::brokenInternalLinkDetailsHtml($meta);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function brokenExternalLinkPlain(array $meta): string
    {
        $status = $meta['status'] ?? null;
        $refs = (int) ($meta['referrer_count'] ?? (is_array($meta['referrers'] ?? null) ? count($meta['referrers']) : 0));
        $bits = [];
        if ($status !== null && $status !== '') {
            $bits[] = 'HTTP ' . $status;
        } elseif (! empty($meta['error'])) {
            $bits[] = 'недоступен';
        }
        if ($refs > 0) {
            $bits[] = 'на ' . $refs . ' стр.';
        }

        return $bits !== [] ? implode(' · ', $bits) : 'битая внешняя ссылка';
    }

    /**
     * Список плохих ссылок на странице: проблема / текст / фрагмент тега для поиска в коде.
     *
     * @param array<string,mixed> $meta
     */
    private static function badLinksDetailsHtml(array $meta): ?string
    {
        $samples = isset($meta['samples']) && is_array($meta['samples']) ? $meta['samples'] : [];
        if ($samples === []) {
            return null;
        }

        $n = max((int) ($meta['count'] ?? 0), count($samples));
        $html = '<div class="cabinet-sa-bad-links">';
        if ($n > 1) {
            $html .= '<div class="cabinet-sa-bad-links__count">на странице: '
                . number_format($n, 0, '', ' ') . '</div>';
        }

        foreach (array_slice($samples, 0, 8) as $sample) {
            if (! is_array($sample)) {
                continue;
            }
            $reasonCode = (string) ($sample['reason'] ?? '');
            $reason = self::badLinkReasonLabel($reasonCode);
            $href = trim((string) ($sample['href'] ?? ''));
            // null из парсера / пустая строка из демо
            if ($href === '' && array_key_exists('href', $sample) && $sample['href'] === null) {
                $href = '';
            }
            $text = trim((string) ($sample['text'] ?? ''));
            $snippet = trim((string) ($sample['snippet'] ?? ''));

            $html .= '<div class="cabinet-sa-bad-links__card">';
            if ($reason !== '') {
                $mod = preg_replace('/[^a-z0-9_]+/i', '', $reasonCode) ?: 'other';
                $html .= '<div class="cabinet-sa-bad-links__chip cabinet-sa-bad-links__chip--'
                    . e($mod) . '">' . e($reason) . '</div>';
            }

            if ($text !== '') {
                $html .= '<div class="cabinet-sa-bad-links__row">'
                    . '<span class="cabinet-sa-bad-links__label">Текст ссылки</span>'
                    . '<span class="cabinet-sa-bad-links__text" title="' . e($text) . '">«'
                    . e(self::clip($text, 80)) . '»</span>'
                    . '<span class="cabinet-sa-bad-links__hint">найдите этот текст в HTML страницы</span>'
                    . '</div>';
            }

            if ($href !== '') {
                $html .= '<div class="cabinet-sa-bad-links__row">'
                    . '<span class="cabinet-sa-bad-links__label">href</span>'
                    . '<code class="cabinet-sa-bad-links__href" title="' . e($href) . '">'
                    . e(self::clip($href, 120)) . '</code>'
                    . '</div>';
            } elseif ($reasonCode === 'missing_href') {
                $html .= '<div class="cabinet-sa-bad-links__row">'
                    . '<span class="cabinet-sa-bad-links__label">href</span>'
                    . '<span class="cabinet-sa-bad-links__missing">атрибута нет</span>'
                    . '</div>';
            }

            if ($snippet !== '') {
                $html .= '<div class="cabinet-sa-bad-links__row">'
                    . '<span class="cabinet-sa-bad-links__label">В коде</span>'
                    . '<code class="cabinet-sa-bad-links__snippet" title="' . e($snippet) . '">'
                    . e(self::clip($snippet, 180)) . '</code>'
                    . '</div>';
            } elseif ($text !== '') {
                $html .= '<div class="cabinet-sa-bad-links__hint-block">'
                    . 'В исходнике страницы ищите: <code>&lt;a</code> рядом с текстом «'
                    . e(self::clip($text, 40)) . '»'
                    . '</div>';
            }

            $html .= '</div>';
        }

        $shown = min(8, count($samples));
        $extra = $n - $shown;
        if ($extra > 0) {
            $html .= '<div class="cabinet-sa-bad-links__more">и ещё '
                . number_format($extra, 0, '', ' ') . ' на этой странице</div>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * Откуда ПС, похоже, взяла заголовок/текст в выдаче (эвристика).
     *
     * @param  array<string, mixed>  $meta
     */
    private static function serpSnippetSourcePlain(array $meta): string
    {
        $engine = self::serpEngineLabelRu((string) ($meta['engine'] ?? ''));
        $titleSrc = self::serpFieldSourceLabelRu((string) ($meta['title_source'] ?? ''));
        $snipSrc = self::serpFieldSourceLabelRu((string) ($meta['snippet_source'] ?? ''));
        $parts = [];
        if ($engine !== '') {
            $parts[] = $engine;
        }
        if ($titleSrc !== '') {
            $parts[] = 'заголовок в выдаче ≈ ' . $titleSrc;
        }
        if ($snipSrc !== '') {
            $parts[] = 'текст сниппета ≈ ' . $snipSrc;
        }

        return $parts !== [] ? implode('; ', $parts) : 'источник сниппета';
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function serpSnippetSourceHtml(array $meta): ?string
    {
        $engine = self::serpEngineLabelRu((string) ($meta['engine'] ?? ''));
        $titleKey = (string) ($meta['title_source'] ?? '');
        $snipKey = (string) ($meta['snippet_source'] ?? '');
        if ($engine === '' && $titleKey === '' && $snipKey === '') {
            return null;
        }

        $titleLine = self::serpSnippetSourceExplain('Заголовок', $titleKey);
        $snipLine = self::serpSnippetSourceExplain('Описание (сниппет)', $snipKey);

        $html = '<div class="cabinet-sa-serp-src">';
        if ($engine !== '') {
            $html .= '<div class="cabinet-sa-serp-src__engine">' . e($engine) . '</div>';
        }
        if ($titleLine !== '') {
            $html .= '<div class="cabinet-sa-serp-src__row">' . e($titleLine) . '</div>';
        }
        if ($snipLine !== '') {
            $html .= '<div class="cabinet-sa-serp-src__row">' . e($snipLine) . '</div>';
        }
        $html .= '</div>';

        return $html;
    }

    private static function serpSnippetSourceExplain(string $what, string $sourceKey): string
    {
        $sourceKey = strtolower(trim($sourceKey));
        if ($sourceKey === '') {
            return '';
        }
        if ($sourceKey === 'title') {
            return $what . ' в выдаче совпал с тегом TITLE на сайте';
        }
        if ($sourceKey === 'h1') {
            return $what . ' в выдаче совпал с H1 на сайте (не с TITLE)';
        }
        if ($sourceKey === 'description') {
            return $what . ' в выдаче совпал с meta description';
        }
        if ($sourceKey === 'unknown') {
            return $what . ' в выдаче не совпал с TITLE / H1 / description — скорее из текста страницы или переписан ПС';
        }

        return $what . ' ≈ ' . $sourceKey;
    }

    private static function serpEngineLabelRu(string $engine): string
    {
        $e = strtolower(trim($engine));
        if ($e === 'yandex') {
            return 'Яндекс';
        }
        if ($e === 'google') {
            return 'Google';
        }

        return $engine;
    }

    private static function serpFieldSourceLabelRu(string $source): string
    {
        $s = strtolower(trim($source));
        if ($s === 'title') {
            return 'тег TITLE';
        }
        if ($s === 'h1') {
            return 'H1';
        }
        if ($s === 'description') {
            return 'meta description';
        }
        if ($s === 'unknown') {
            return 'не TITLE/H1/description (текст страницы или перепис ПС)';
        }

        return $source;
    }

    /**
     * Как страница выглядит в поиске: карточки Яндекс / Google.
     *
     * @param array<string,mixed> $meta
     */
    private static function serpSnippetsDetailsHtml(array $meta): ?string
    {
        $pageTitle = trim((string) ($meta['page_title'] ?? ''));
        $engines = isset($meta['engines']) && is_array($meta['engines']) ? $meta['engines'] : [];

        // Старое демо / урезанный meta: один engine + snippet.
        if ($engines === [] && (! empty($meta['engine']) || ! empty($meta['snippet']) || ! empty($meta['serp_title']))) {
            $eng = (string) ($meta['engine'] ?? 'yandex');
            if ($eng === '') {
                $eng = 'yandex';
            }
            $engines = [
                $eng => [
                    'indexed' => true,
                    'title' => $meta['serp_title'] ?? $meta['title'] ?? null,
                    'snippet' => $meta['snippet'] ?? null,
                ],
            ];
        }

        if ($engines === [] && $pageTitle === '') {
            return null;
        }

        $order = ['yandex', 'google'];
        $keys = array_values(array_unique(array_merge($order, array_keys($engines))));

        $html = '<div class="cabinet-sa-serp-diff cabinet-sa-serp-diff--snippets">';
        if ($pageTitle !== '') {
            $html .= '<div class="cabinet-sa-serp-diff__page">'
                . '<div class="cabinet-sa-serp-diff__label">Заголовок на сайте (TITLE)</div>'
                . '<div class="cabinet-sa-serp-diff__text">' . e($pageTitle) . '</div>'
                . '</div>';
        }

        $html .= '<div class="cabinet-sa-serp-diff__engines">';
        $any = false;
        foreach ($keys as $engine) {
            if (! isset($engines[$engine]) || ! is_array($engines[$engine])) {
                continue;
            }
            $any = true;
            $block = $engines[$engine];
            $engineLabel = $engine === 'yandex' ? 'Яндекс'
                : ($engine === 'google' ? 'Google' : $engine);
            $serpTitle = trim((string) ($block['title'] ?? ''));
            $snippet = trim((string) ($block['snippet'] ?? ''));
            $indexed = array_key_exists('indexed', $block) ? ! empty($block['indexed']) : ($serpTitle !== '' || $snippet !== '');
            $error = trim((string) ($block['error'] ?? ''));

            $statusClass = 'cabinet-sa-serp-diff__engine-card';
            $statusBadge = '';
            if ($error !== '') {
                $statusClass .= ' is-error';
                $statusBadge = '<span class="cabinet-sa-serp-diff__status is-error">ошибка</span>';
            } elseif (! $indexed) {
                $statusClass .= ' is-miss';
                $statusBadge = '<span class="cabinet-sa-serp-diff__status is-miss">нет в поиске</span>';
            }

            $html .= '<div class="' . $statusClass . '">';
            $html .= '<div class="cabinet-sa-serp-diff__engine-head">'
                . '<span class="cabinet-sa-serp-diff__engine">' . e($engineLabel) . '</span>'
                . $statusBadge
                . '</div>';

            if ($error !== '') {
                $html .= '<div class="cabinet-sa-serp-diff__text cabinet-sa-serp-diff__text--muted">'
                    . e(self::clip($error, 120)) . '</div>';
            } elseif (! $indexed) {
                $html .= '<div class="cabinet-sa-serp-diff__text cabinet-sa-serp-diff__text--muted">'
                    . 'В выдаче не нашли</div>';
            } else {
                if ($serpTitle !== '') {
                    $html .= '<div class="cabinet-sa-serp-diff__label">Заголовок в поиске</div>'
                        . '<div class="cabinet-sa-serp-diff__text">' . e($serpTitle) . '</div>';
                }
                if ($snippet !== '') {
                    $html .= '<div class="cabinet-sa-serp-diff__snippet-inline">'
                        . '<div class="cabinet-sa-serp-diff__label">Текст под ссылкой</div>'
                        . '<div class="cabinet-sa-serp-diff__text cabinet-sa-serp-diff__text--muted">'
                        . e($snippet) . '</div></div>';
                }
                if ($serpTitle === '' && $snippet === '') {
                    $html .= '<div class="cabinet-sa-serp-diff__text cabinet-sa-serp-diff__text--muted">'
                        . 'Есть в поиске, текст не получили</div>';
                }
            }
            $html .= '</div>';
        }
        $html .= '</div></div>';

        return $any || $pageTitle !== '' ? $html : null;
    }

    /**
     * Сравнение title по всем ПС: совпала / расхождение видно сразу.
     *
     * @param array<string,mixed> $meta
     */
    private static function serpTitleMismatchHtml(array $meta): ?string
    {
        $pageTitle = trim((string) ($meta['page_title'] ?? ''));
        $engines = isset($meta['engines']) && is_array($meta['engines']) ? $meta['engines'] : null;
        $titleOk = ! empty($meta['title_ok']);

        // Старые находки без engines — одна ПС как раньше.
        if ($engines === null || $engines === []) {
            $serpTitle = trim((string) ($meta['serp_title'] ?? ''));
            if ($pageTitle === '' && $serpTitle === '') {
                return null;
            }
            $engine = (string) ($meta['engine'] ?? '');
            $engines = [
                $engine !== '' ? $engine : 'ps' => [
                    'indexed' => true,
                    'title' => $serpTitle !== '' ? $serpTitle : null,
                    'snippet' => $meta['snippet'] ?? null,
                    'title_mismatch' => ! $titleOk,
                    'title_match' => $titleOk,
                ],
            ];
        }

        $order = ['yandex', 'google'];
        $keys = array_values(array_unique(array_merge($order, array_keys($engines))));

        $html = '<div class="cabinet-sa-serp-diff">';
        $hasMatchEngine = false;
        foreach ($engines as $eb) {
            if (is_array($eb) && ! empty($eb['title_match'])) {
                $hasMatchEngine = true;
                break;
            }
        }
        if ($titleOk && $hasMatchEngine) {
            $html .= '<div class="cabinet-sa-serp-diff__ok-banner">'
                . '<span class="cabinet-sa-serp-diff__status is-ok">всё ок</span>'
                . ' TITLE совпал с выдачей в снятых ПС'
                . '</div>';
        }
        if ($pageTitle !== '') {
            $html .= '<div class="cabinet-sa-serp-diff__page">'
                . '<div class="cabinet-sa-serp-diff__label">TITLE на сайте</div>'
                . '<div class="cabinet-sa-serp-diff__text">' . e($pageTitle) . '</div>'
                . '</div>';
        }

        $html .= '<div class="cabinet-sa-serp-diff__engines">';
        foreach ($keys as $engine) {
            if (! isset($engines[$engine]) || ! is_array($engines[$engine])) {
                continue;
            }
            $block = $engines[$engine];
            $engineLabel = $engine === 'yandex' ? 'Яндекс'
                : ($engine === 'google' ? 'Google' : $engine);
            $serpTitle = trim((string) ($block['title'] ?? ''));
            $snippet = trim((string) ($block['snippet'] ?? ''));
            $mismatch = ! empty($block['title_mismatch']);
            $match = ! empty($block['title_match']);
            $indexed = ! empty($block['indexed']);
            $error = trim((string) ($block['error'] ?? ''));

            $statusClass = 'cabinet-sa-serp-diff__engine-card';
            $statusBadge = '';
            if ($error !== '') {
                $statusClass .= ' is-error';
                $statusBadge = '<span class="cabinet-sa-serp-diff__status is-error">ошибка</span>';
            } elseif (! $indexed) {
                $statusClass .= ' is-miss';
                $statusBadge = '<span class="cabinet-sa-serp-diff__status is-miss">нет в выдаче</span>';
            } elseif ($match) {
                $statusClass .= ' is-ok';
                $statusBadge = '<span class="cabinet-sa-serp-diff__status is-ok">совпал</span>';
            } elseif ($mismatch) {
                $statusClass .= ' is-bad';
                $statusBadge = '<span class="cabinet-sa-serp-diff__status is-bad">≠ TITLE</span>';
            }

            $html .= '<div class="' . $statusClass . '">';
            $html .= '<div class="cabinet-sa-serp-diff__engine-head">'
                . '<span class="cabinet-sa-serp-diff__engine">' . e($engineLabel) . '</span>'
                . $statusBadge
                . '</div>';
            if ($serpTitle !== '') {
                $html .= '<div class="cabinet-sa-serp-diff__label">В выдаче</div>'
                    . '<div class="cabinet-sa-serp-diff__text">' . e($serpTitle) . '</div>';
            } elseif ($error !== '') {
                $html .= '<div class="cabinet-sa-serp-diff__text cabinet-sa-serp-diff__text--muted">'
                    . e(self::clip($error, 120)) . '</div>';
            } elseif (! $indexed) {
                $html .= '<div class="cabinet-sa-serp-diff__text cabinet-sa-serp-diff__text--muted">не найден</div>';
            }
            if ($snippet !== '') {
                $html .= '<div class="cabinet-sa-serp-diff__snippet-inline">'
                    . '<div class="cabinet-sa-serp-diff__label">Сниппет</div>'
                    . '<div class="cabinet-sa-serp-diff__text cabinet-sa-serp-diff__text--muted">'
                    . e($snippet) . '</div></div>';
            }
            $html .= '</div>';
        }
        $html .= '</div></div>';

        return $html;
    }

    private static function badLinkReasonLabel(string $reason): string
    {
        $map = [
            'missing_href' => 'Нет атрибута href',
            'empty_or_hash' => 'Пустой href или только #',
            'javascript' => 'javascript: вместо URL',
            'whitespace' => 'Пробел в href',
            'quotes' => 'Кавычки в href',
            'nested_url' => 'Два URL в одном href',
            'unresolvable' => 'Неразбираемый URL',
        ];

        return $map[$reason] ?? ($reason !== '' ? $reason : '');
    }

    private static function lostFileDetailsHtml(array $meta): string
    {
        $asset = trim((string) ($meta['asset'] ?? ''));
        $st = isset($meta['status']) ? ('HTTP ' . (int) $meta['status']) : 'unreachable';
        $pill = self::httpStatusPillHtml(isset($meta['status']) ? (int) $meta['status'] : null, $st);
        if ($asset === '') {
            return $pill;
        }

        return '<div class="cabinet-sa-details-stack">'
            . $pill
            . '<div class="cabinet-sa-details-stack__url">' . self::urlLinkHtml($asset) . '</div>'
            . '</div>';
    }

    /**
     * Глубина клика + свёрнутый путь от главной по out_links.
     *
     * @param  array<string, mixed>  $meta
     */
    private static function deepPagesDetailsHtml(array $meta): string
    {
        $depth = isset($meta['depth']) ? (int) $meta['depth'] : null;
        $threshold = isset($meta['threshold']) ? (int) $meta['threshold'] : null;
        $line = $depth !== null
            ? ('глубина: ' . $depth . ($threshold !== null ? (' (порог ' . $threshold . ')') : ''))
            : 'глубокая страница';

        $path = [];
        if (! empty($meta['path']) && is_array($meta['path'])) {
            foreach ($meta['path'] as $u) {
                $u = trim((string) $u);
                if ($u !== '') {
                    $path[] = $u;
                }
            }
        }

        $html = '<div class="cabinet-sa-depth">'
            . '<div class="cabinet-sa-depth__line">' . e($line) . '</div>';

        if ($path !== []) {
            $steps = count($path) > 0 ? (count($path) - 1) : 0;
            $html .= '<details class="cabinet-sa-depth-path">'
                . '<summary class="cabinet-sa-depth-path__sum">Показать путь'
                . ($steps > 0 ? (' · ' . $steps . ' ' . self::ruClicksWord($steps)) : '')
                . '</summary>'
                . '<ol class="cabinet-sa-depth-path__list">';
            foreach ($path as $i => $u) {
                $label = self::depthPathLabel($u, $i === 0);
                $html .= '<li class="cabinet-sa-depth-path__item">'
                    . '<a href="' . e($u) . '" target="_blank" rel="noopener" title="' . e($u) . '">'
                    . e($label)
                    . '</a></li>';
            }
            $html .= '</ol></details>';
        }

        $html .= '</div>';

        return $html;
    }

    private static function ruClicksWord(int $n): string
    {
        $n = abs($n) % 100;
        $n1 = $n % 10;
        if ($n > 10 && $n < 20) {
            return 'кликов';
        }
        if ($n1 === 1) {
            return 'клик';
        }
        if ($n1 >= 2 && $n1 <= 4) {
            return 'клика';
        }

        return 'кликов';
    }

    private static function depthPathLabel(string $url, bool $isRoot): string
    {
        if ($isRoot) {
            $host = (string) (parse_url($url, PHP_URL_HOST) ?: '');
            $path = (string) (parse_url($url, PHP_URL_PATH) ?: '/');
            if ($path === '/' || $path === '') {
                return $host !== '' ? ($host . '/') : '/';
            }
        }
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: $url);
        if ($path === '') {
            $path = '/';
        }
        if (mb_strlen($path) > 64) {
            return self::clip($path, 64);
        }

        return $path;
    }

    /**
     * Исходящие ссылки на чужие домены: хост · полный URL.
     *
     * @param  array<string, mixed>  $meta
     */
    private static function externalLinksDetailsHtml(array $meta): ?string
    {
        $samples = self::normalizeUrlSamples($meta);
        $n = (int) ($meta['count'] ?? count($samples));
        if ($samples === [] && $n <= 0) {
            return null;
        }

        $parts = [];
        if ($n > 0) {
            $parts[] = '<div class="mb-1"><strong>внешних ссылок: ' . $n . '</strong></div>';
        }
        if ($samples === []) {
            return $parts ? implode('', $parts) : null;
        }

        $parts[] = '<ul class="mb-0 ps-3 cabinet-sa-ext-assets">';
        foreach (array_slice($samples, 0, 15) as $item) {
            $host = $item['host'] !== '' ? e($item['host']) : '—';
            $parts[] = '<li class="cabinet-sa-ext-assets__item">'
                . '<span class="text-secondary">→ ' . $host . '</span>'
                . '<div class="cabinet-sa-details-stack__url">' . self::urlLinkHtml($item['url']) . '</div>'
                . '</li>';
        }
        $parts[] = '</ul>';
        if ($n > count($samples)) {
            $parts[] = '<div class="text-secondary small mt-1">показаны '
                . count($samples) . ' из ' . $n . '</div>';
        }

        return implode('', $parts);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function externalLinksPlain(array $meta): string
    {
        $samples = self::normalizeUrlSamples($meta);
        $n = (int) ($meta['count'] ?? count($samples));
        if ($n <= 0 && $samples === []) {
            return '—';
        }

        $bits = ['внешних: ' . max($n, count($samples))];
        $hosts = [];
        foreach ($samples as $item) {
            if ($item['host'] !== '' && ! isset($hosts[$item['host']])) {
                $hosts[$item['host']] = true;
            }
        }
        if ($hosts) {
            $bits[] = 'хосты: ' . implode(', ', array_slice(array_keys($hosts), 0, 5));
        }
        if ($samples) {
            $bits[] = self::clip($samples[0]['url'], 80);
        }

        return implode(' · ', $bits);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return list<array{url:string,host:string}>
     */
    private static function normalizeUrlSamples(array $meta): array
    {
        $raw = isset($meta['samples']) && is_array($meta['samples']) ? $meta['samples'] : [];
        $out = [];
        foreach ($raw as $sample) {
            $url = '';
            if (is_string($sample)) {
                $url = trim($sample);
            } elseif (is_array($sample)) {
                $url = trim((string) ($sample['url'] ?? $sample['href'] ?? $sample['src'] ?? ''));
            }
            if ($url === '') {
                continue;
            }
            $host = (string) (parse_url($url, PHP_URL_HOST) ?: '');
            $out[] = ['url' => $url, 'host' => $host];
        }

        return $out;
    }

    /**
     * Список внешних script/css/img: тип · хост · полный URL.
     *
     * @param  array<string, mixed>  $meta
     */
    private static function externalAssetsDetailsHtml(array $meta): ?string
    {
        $items = self::normalizeExternalAssetItems($meta);
        $n = (int) ($meta['count'] ?? count($items));
        if ($items === [] && $n <= 0) {
            return null;
        }

        $parts = [];
        if ($n > 0) {
            $parts[] = '<div class="mb-1"><strong>внешних файлов: ' . $n . '</strong></div>';
        }
        if ($items === []) {
            return $parts ? implode('', $parts) : null;
        }

        $parts[] = '<ul class="mb-0 ps-3 cabinet-sa-ext-assets">';
        foreach (array_slice($items, 0, 15) as $item) {
            $kindLabel = self::externalAssetKindLabel($item['kind']);
            $host = $item['host'] !== '' ? e($item['host']) : '—';
            $parts[] = '<li class="cabinet-sa-ext-assets__item">'
                . '<span class="cabinet-sa-ext-assets__kind">' . e($kindLabel) . '</span>'
                . ' <span class="text-secondary">с ' . $host . '</span>'
                . '<div class="cabinet-sa-details-stack__url">' . self::urlLinkHtml($item['url']) . '</div>'
                . '</li>';
        }
        $parts[] = '</ul>';
        if ($n > count($items)) {
            $parts[] = '<div class="text-secondary small mt-1">показаны '
                . count($items) . ' из ' . $n . '</div>';
        }

        return implode('', $parts);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function externalAssetsPlain(array $meta): string
    {
        $items = self::normalizeExternalAssetItems($meta);
        $n = (int) ($meta['count'] ?? count($items));
        if ($n <= 0 && $items === []) {
            return '—';
        }

        $bits = ['внешних: ' . max($n, count($items))];
        $hosts = [];
        foreach ($items as $item) {
            if ($item['host'] !== '' && ! isset($hosts[$item['host']])) {
                $hosts[$item['host']] = true;
            }
        }
        if ($hosts) {
            $bits[] = 'хосты: ' . implode(', ', array_slice(array_keys($hosts), 0, 5));
        }
        if ($items) {
            $first = $items[0];
            $bits[] = self::externalAssetKindLabel($first['kind']) . ' ' . self::clip($first['url'], 80);
        }

        return implode(' · ', $bits);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return list<array{url:string,kind:string,host:string}>
     */
    private static function normalizeExternalAssetItems(array $meta): array
    {
        $raw = isset($meta['samples']) && is_array($meta['samples']) ? $meta['samples'] : [];
        $out = [];
        foreach ($raw as $sample) {
            $url = '';
            $kind = 'file';
            if (is_string($sample)) {
                $url = trim($sample);
                $kind = self::guessExternalAssetKind($url);
            } elseif (is_array($sample)) {
                $url = trim((string) ($sample['url'] ?? $sample['src'] ?? $sample['href'] ?? ''));
                $kind = trim((string) ($sample['kind'] ?? $sample['type'] ?? ''));
                if ($kind === '') {
                    $kind = self::guessExternalAssetKind($url);
                }
            }
            if ($url === '') {
                continue;
            }
            $host = (string) (parse_url($url, PHP_URL_HOST) ?: '');
            $out[] = ['url' => $url, 'kind' => $kind, 'host' => $host];
        }

        return $out;
    }

    private static function guessExternalAssetKind(string $url): string
    {
        $path = strtolower((string) (parse_url($url, PHP_URL_PATH) ?: $url));
        if (preg_match('/\.(js)(\?|$)/', $path)) {
            return 'script';
        }
        if (preg_match('/\.(css)(\?|$)/', $path)) {
            return 'css';
        }
        if (preg_match('/\.(png|jpe?g|gif|webp|svg|ico|avif)(\?|$)/', $path)) {
            return 'img';
        }

        return 'file';
    }

    private static function externalAssetKindLabel(string $kind): string
    {
        switch ($kind) {
            case 'script':
                return 'JS';
            case 'css':
                return 'CSS';
            case 'img':
                return 'картинка';
            default:
                return 'файл';
        }
    }

    /**
     * Страница с битыми внутренними ссылками: список целей + HTTP.
     *
     * @param  array<string, mixed>  $meta
     */
    private static function pageHasBrokenLinksDetailsHtml(array $meta): ?string
    {
        $samples = isset($meta['samples']) && is_array($meta['samples']) ? $meta['samples'] : [];
        $n = (int) ($meta['count'] ?? count($samples));
        if ($n < 1 && $samples === []) {
            return null;
        }
        if ($n < 1) {
            $n = count($samples);
        }

        $noun = 'битых ссылок';
        if ($n % 10 === 1 && $n % 100 !== 11) {
            $noun = 'битая ссылка';
        } elseif (in_array($n % 10, [2, 3, 4], true) && ! in_array($n % 100, [12, 13, 14], true)) {
            $noun = 'битые ссылки';
        }

        $html = '<div class="cabinet-sa-broken-page">';
        $html .= '<div class="cabinet-sa-broken-page__head">'
            . '<span class="cabinet-sa-broken-page__count">' . $n . ' ' . $noun . '</span>'
            . '<span class="cabinet-sa-broken-page__hint">с этой страницы</span>'
            . '</div>';

        if ($samples !== []) {
            $html .= '<ul class="cabinet-sa-broken-page__list">';
            foreach (array_slice($samples, 0, 5) as $sample) {
                if (! is_array($sample)) {
                    continue;
                }
                $target = trim((string) ($sample['url'] ?? $sample['href'] ?? ''));
                if ($target === '') {
                    continue;
                }
                $text = trim((string) ($sample['text'] ?? ''));
                $status = isset($sample['status']) ? (int) $sample['status'] : 0;
                $html .= '<li class="cabinet-sa-broken-page__item">';
                if ($status > 0) {
                    $html .= self::httpStatusPillHtml($status, (string) $status);
                } else {
                    $html .= '<span class="cabinet-sa-status-pill">ошибка</span>';
                }
                $html .= '<div class="cabinet-sa-broken-page__body">';
                if ($text !== '') {
                    $html .= '<span class="cabinet-sa-broken-page__anchor" title="' . e($text) . '">«'
                        . e(self::clip($text, 50)) . '»</span>';
                }
                $html .= self::urlLinkHtml($target);
                $html .= '</div></li>';
            }
            $html .= '</ul>';
            if ($n > min(5, count($samples))) {
                $html .= '<div class="cabinet-sa-broken-page__more">ещё '
                    . ($n - min(5, count($samples))) . ' на странице</div>';
            }
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * @param array<string,mixed> $meta
     */
    private static function brokenInternalLinkDetailsHtml(array $meta): string
    {
        return self::availabilityStatusDetailsHtml('unreachable', $meta, null);
    }

    /**
     * Короткий ярлык URL для колонки «Страница со ссылкой» (path, без хоста).
     */
    public static function compactUrlLabel(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '—';
        }
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['host'])) {
            return self::clip($url, 72);
        }
        $path = (string) ($parts['path'] ?? '/');
        if ($path === '') {
            $path = '/';
        }
        if (! empty($parts['query'])) {
            $path .= '?' . $parts['query'];
        }
        if ($path === '/') {
            return ($parts['host'] ?? '') . '/';
        }

        return self::clip($path, 72);
    }

    /**
     * Человекочитаемая причина сбоя соединения (timeout / DNS / SSL…).
     */
    public static function connectionErrorLabelPublic(string $err): string
    {
        return self::connectionErrorLabel($err);
    }

    public static function isTimeoutFetchError(string $err): bool
    {
        $err = strtolower(trim($err));
        if ($err === '') {
            return false;
        }

        return strpos($err, 'timed out') !== false
            || strpos($err, 'timeout') !== false
            || strpos($err, 'operation timedout') !== false
            || strpos($err, 'curl error 28') !== false;
    }

    /**
     * Человекочитаемая причина сбоя соединения (timeout / DNS / SSL…).
     */
    private static function connectionErrorLabel(string $err): string
    {
        $err = strtolower(trim($err));
        if ($err === '') {
            return 'нет ответа';
        }
        if (strpos($err, 'certificate subject') !== false
            || strpos($err, 'alternative certificate') !== false
            || strpos($err, 'does not match') !== false
            || strpos($err, 'ssl certificate problem') !== false
        ) {
            return 'SSL-сертификат не подходит';
        }
        if (strpos($err, 'could not resolve') !== false || strpos($err, 'resolve host') !== false) {
            return 'DNS не резолвится';
        }
        if (self::isTimeoutFetchError($err)) {
            return 'таймаут';
        }
        if (strpos($err, 'unexpected eof') !== false
            || strpos($err, 'protocol_error') !== false
            || strpos($err, 'http/2') !== false
        ) {
            return 'обрыв TLS/HTTP (часто антибот)';
        }
        if (strpos($err, 'ssl') !== false) {
            return 'ошибка SSL';
        }

        return 'сбой соединения';
    }

    /**
     * Компактный статус ответа для битых URL / 4xx / 5xx / unreachable.
     * Счётчик «ссылаются» не дублируем — он в соседней колонке.
     *
     * @param  array<string, mixed>  $meta
     */
    private static function availabilityStatusDetailsHtml(string $code, array $meta, ?string $url): string
    {
        $status = isset($meta['status']) ? (int) $meta['status'] : 0;
        $pill = '';

        if ($status > 0) {
            $pill = self::httpStatusPillHtml($status, (string) $status);
        } else {
            $errRaw = trim((string) ($meta['error'] ?? ''));
            $label = self::connectionErrorLabel($errRaw);
            $aria = $errRaw !== ''
                ? ' aria-label="' . e(self::clip($errRaw, 160)) . '"'
                : '';
            $pill = '<span class="cabinet-sa-status-pill is-bad"' . $aria . '>' . e($label) . '</span>';
        }

        $html = '<div class="cabinet-sa-broken-status">' . $pill . '</div>';

        $attempts = isset($meta['attempts']) && is_array($meta['attempts']) ? $meta['attempts'] : [];
        if ($code === 'unreachable' && (count($attempts) >= 2 || ! empty($meta['retried']))) {
            $a1 = is_array($attempts[0] ?? null) ? $attempts[0] : [];
            $a2 = is_array($attempts[1] ?? null) ? $attempts[1] : [];
            $l1 = trim((string) ($a1['label'] ?? ''));
            $l2 = trim((string) ($a2['label'] ?? ''));
            if ($l1 === '') {
                $l1 = self::connectionErrorLabel((string) ($meta['error_first'] ?? $meta['error'] ?? ''));
            }
            if ($l2 === '') {
                $l2 = self::connectionErrorLabel((string) ($meta['error'] ?? ''));
            }
            $e1 = trim((string) ($a1['error'] ?? $meta['error_first'] ?? ''));
            $e2 = trim((string) ($a2['error'] ?? $meta['error'] ?? ''));
            $html .= '<div class="cabinet-sa-broken-status__hint">'
                . '<div><strong>1-й обход</strong> (в скане): ' . e($l1)
                . ($e1 !== '' ? ' <span class="text-muted">(' . e(self::clip($e1, 80)) . ')</span>' : '')
                . '</div>'
                . '<div><strong>2-й обход</strong> (повтор после скана): ' . e($l2)
                . ($e2 !== '' ? ' <span class="text-muted">(' . e(self::clip($e2, 80)) . ')</span>' : '')
                . '</div>'
                . '<div class="text-muted" style="margin-top:2px">Оба раза ответ не получен — URL остаётся в отчёте.</div>'
                . '</div>';
        }

        $slashUrl = trim((string) ($meta['slash_url'] ?? ''));
        $showSlash = (! empty($meta['slash_hint']) || ! empty($meta['false_404_slash']))
            && ! self::urlLooksBrokenHref((string) $url)
            && ! self::urlLooksBrokenHref($slashUrl);
        if ($showSlash) {
            $html .= '<div class="cabinet-sa-broken-status__hint">'
                . ($slashUrl !== ''
                    ? 'Часто жива со слэшем: ' . self::urlLinkHtml($slashUrl)
                    : 'Возможен ложный 404: страница жива со слэшем в конце')
                . '</div>';
        }

        return $html;
    }

    /**
     * Иерархия заголовков: тип нарушения + кусок outline.
     *
     * @param  array<string, mixed>  $meta
     */
    private static function headingHierarchyDetailsHtml(array $meta): ?string
    {
        $issues = isset($meta['issues']) && is_array($meta['issues']) ? $meta['issues'] : [];
        $outline = isset($meta['outline_sample']) && is_array($meta['outline_sample'])
            ? $meta['outline_sample']
            : [];
        if ($issues === [] && $outline === []) {
            return null;
        }

        $issueTexts = [];
        foreach ($issues as $issue) {
            if (! is_array($issue)) {
                continue;
            }
            $t = trim((string) ($issue['text'] ?? ''));
            if ($t !== '') {
                $issueTexts[] = mb_strtolower($t);
            }
        }

        $html = '<div class="cabinet-sa-heading-hi">';

        foreach (array_slice($issues, 0, 4) as $issue) {
            if (! is_array($issue)) {
                continue;
            }
            $type = (string) ($issue['type'] ?? '');
            $text = trim((string) ($issue['text'] ?? ''));
            if ($type === 'before_h1') {
                $lvl = (int) ($issue['level'] ?? 0);
                $html .= '<div class="cabinet-sa-heading-hi__issue">'
                    . '<span class="cabinet-sa-status-pill is-bad">до H1</span>'
                    . '<span class="cabinet-sa-heading-hi__jump">H' . $lvl . ' стоит раньше первого H1</span>'
                    . ($text !== ''
                        ? '<div class="cabinet-sa-heading-hi__quote">«' . e(self::clip($text, 80)) . '»</div>'
                        : '')
                    . '</div>';
            } elseif ($type === 'skip') {
                $from = (int) ($issue['from'] ?? 0);
                $to = (int) ($issue['to'] ?? 0);
                $missing = max(1, $from + 1);
                $html .= '<div class="cabinet-sa-heading-hi__issue">'
                    . '<span class="cabinet-sa-status-pill is-bad">пропуск уровня</span>'
                    . '<span class="cabinet-sa-heading-hi__jump">'
                    . '<span class="cabinet-sa-heading-hi__tag">H' . $from . '</span>'
                    . '<span class="cabinet-sa-heading-hi__arrow" aria-hidden="true">→</span>'
                    . '<span class="cabinet-sa-heading-hi__tag is-bad">H' . $to . '</span>'
                    . '<span class="cabinet-sa-heading-hi__hint">нет H' . $missing . '</span>'
                    . '</span>'
                    . ($text !== ''
                        ? '<div class="cabinet-sa-heading-hi__quote">«' . e(self::clip($text, 80)) . '»</div>'
                        : '')
                    . '</div>';
            }
        }

        if ($outline !== []) {
            $html .= '<div class="cabinet-sa-heading-hi__outline">';
            $html .= '<div class="cabinet-sa-heading-hi__outline-label">Как сейчас</div>';
            $shown = 0;
            foreach ($outline as $node) {
                if (! is_array($node) || $shown >= 8) {
                    break;
                }
                $lvl = max(1, min(6, (int) ($node['level'] ?? 0)));
                $t = trim((string) ($node['text'] ?? ''));
                if ($t === '') {
                    continue;
                }
                $isBad = false;
                $tLower = mb_strtolower($t);
                foreach ($issueTexts as $it) {
                    if ($it !== '' && (mb_strpos($tLower, $it) !== false || mb_strpos($it, $tLower) !== false)) {
                        $isBad = true;
                        break;
                    }
                }
                $pad = max(0, $lvl - 1);
                $html .= '<div class="cabinet-sa-heading-hi__line'
                    . ($isBad ? ' is-bad' : '')
                    . '" style="--hi-depth:' . $pad . '">'
                    . '<span class="cabinet-sa-heading-hi__lvl">H' . $lvl . '</span>'
                    . '<span class="cabinet-sa-heading-hi__txt">' . e(self::clip($t, 70)) . '</span>'
                    . ($isBad ? '<span class="cabinet-sa-heading-hi__flag">сюда прыжок</span>' : '')
                    . '</div>';
                $shown++;
            }
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Какое правило robots.txt закрыло URL.
     *
     * @param  array<string, mixed>  $meta
     */
    private static function robotsBlockedDetailsHtml(array $meta): ?string
    {
        $directive = trim((string) ($meta['directive'] ?? 'Disallow'));
        if ($directive === '') {
            $directive = 'Disallow';
        }
        $rule = (string) ($meta['rule'] ?? $meta['path'] ?? '');
        $agent = trim((string) ($meta['agent'] ?? '*'));
        if ($agent === '') {
            $agent = '*';
        }
        if ($rule === '') {
            return null;
        }

        $rows = [];
        $rows[] = '<div class="cabinet-sa-details-stack__row">'
            . '<span class="cabinet-sa-status-pill">robots.txt</span> '
            . '<code class="small">' . e($directive . ': ' . $rule) . '</code>'
            . '</div>';
        if ($agent !== '*') {
            $rows[] = '<div class="cabinet-sa-details-stack__row text-secondary small">'
                . 'User-agent: ' . e($agent)
                . '</div>';
        } else {
            $rows[] = '<div class="cabinet-sa-details-stack__row text-secondary small">'
                . 'User-agent: *'
                . '</div>';
        }

        return '<div class="cabinet-sa-details-stack">' . implode('', $rows) . '</div>';
    }

    /**
     * Явно: meta-тег и/или HTTP X-Robots-Tag (не robots.txt).
     *
     * @param  array<string, mixed>  $meta
     */
    private static function noindexDetailsHtml(array $meta): ?string
    {
        $rows = [];
        $robots = trim((string) ($meta['robots'] ?? ''));
        $xRobots = trim((string) ($meta['x_robots'] ?? ''));
        $metaHtml = trim((string) ($meta['meta_html'] ?? ''));

        if ($robots !== '') {
            if ($metaHtml === '') {
                $metaHtml = '<meta name="robots" content="' . $robots . '">';
            }
            $rows[] = '<div class="cabinet-sa-details-stack__row">'
                . '<span class="cabinet-sa-status-pill">meta robots</span> '
                . '<code class="small">' . e($metaHtml) . '</code>'
                . '</div>';
        }
        if ($xRobots !== '') {
            $rows[] = '<div class="cabinet-sa-details-stack__row">'
                . '<span class="cabinet-sa-status-pill">HTTP-заголовок</span> '
                . '<code class="small">X-Robots-Tag: ' . e($xRobots) . '</code>'
                . '</div>';
        }
        if ($rows === []) {
            return null;
        }

        return '<div class="cabinet-sa-details-stack">' . implode('', $rows) . '</div>';
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function textInNoindexPlain(array $meta): string
    {
        $sample = trim((string) ($meta['sample'] ?? ''));
        $links = isset($meta['links']) && is_array($meta['links']) ? $meta['links'] : [];
        $bits = [];
        if ($sample !== '') {
            $bits[] = '«' . self::clip($sample, 60) . '»';
        }
        if ($links !== []) {
            $hosts = [];
            foreach (array_slice($links, 0, 4) as $l) {
                if (! is_array($l)) {
                    continue;
                }
                $href = (string) ($l['href'] ?? '');
                $host = parse_url($href, PHP_URL_HOST);
                if (is_string($host) && $host !== '') {
                    $hosts[] = $host;
                }
            }
            $hosts = array_values(array_unique($hosts));
            if ($hosts !== []) {
                $bits[] = 'ссылки: ' . implode(', ', $hosts);
            } else {
                $bits[] = 'ссылок: ' . count($links);
            }
        }
        if ($bits === []) {
            $len = (int) ($meta['noindex_text_len'] ?? 0);

            return $len > 0 ? ('в noindex: ' . $len . ' симв.') : 'блок noindex';
        }

        return implode(' · ', $bits);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function textInNoindexDetailsHtml(array $meta): ?string
    {
        $sample = trim((string) ($meta['sample'] ?? ''));
        $links = isset($meta['links']) && is_array($meta['links']) ? $meta['links'] : [];
        $rows = [];

        if ($sample !== '') {
            $rows[] = '<div class="cabinet-sa-details-stack__row">'
                . '<span class="cabinet-sa-status-pill">в noindex</span> '
                . '<code class="small">' . e(self::clip($sample, 120)) . '</code>'
                . '</div>';
        }
        foreach (array_slice($links, 0, 6) as $l) {
            if (! is_array($l)) {
                continue;
            }
            $href = trim((string) ($l['href'] ?? ''));
            if ($href === '') {
                continue;
            }
            $label = trim((string) ($l['text'] ?? ''));
            if ($label === '' || $label === $href) {
                $label = self::clip($href, 48);
            }
            $rows[] = '<div class="cabinet-sa-details-stack__row">'
                . '<span class="cabinet-sa-status-pill">ссылка</span> '
                . '<a href="' . e($href) . '" target="_blank" rel="noopener noreferrer">' . e(self::clip($label, 40)) . '</a>'
                . ' <span class="text-secondary small">' . e(self::clip($href, 64)) . '</span>'
                . '</div>';
        }
        if ($rows === []) {
            $plain = self::textInNoindexPlain($meta);

            return $plain !== '' && $plain !== 'блок noindex'
                ? '<div class="cabinet-sa-details-stack">' . e($plain) . '</div>'
                : null;
        }

        return '<div class="cabinet-sa-details-stack">' . implode('', $rows) . '</div>';
    }

    /**
     * Несколько H1: сколько и какие тексты.
     *
     * @param  array<string, mixed>  $meta
     * @return list<string>
     */
    private static function multipleH1Samples(array $meta): array
    {
        $raw = [];
        if (! empty($meta['samples']) && is_array($meta['samples'])) {
            $raw = $meta['samples'];
        } elseif (! empty($meta['h1s']) && is_array($meta['h1s'])) {
            $raw = $meta['h1s'];
        }
        $out = [];
        foreach ($raw as $t) {
            $t = trim((string) $t);
            if ($t === '') {
                continue;
            }
            $out[] = mb_substr($t, 0, 160);
            if (count($out) >= 8) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function multipleH1Plain(array $meta): string
    {
        $count = (int) ($meta['count'] ?? 0);
        $samples = self::multipleH1Samples($meta);
        if ($count < 1) {
            $count = count($samples);
        }
        if ($count < 1) {
            return 'несколько H1';
        }

        $head = number_format($count, 0, '', ' ') . ' '
            . self::ruWordForm($count, 'заголовок H1', 'заголовка H1', 'заголовков H1');
        if ($samples === []) {
            return $head;
        }

        $quoted = [];
        foreach (array_slice($samples, 0, 4) as $t) {
            $quoted[] = '«' . self::clip($t, 70) . '»';
        }
        $more = $count > count($quoted) ? (' … ещё ' . ($count - count($quoted))) : '';

        return $head . ': ' . implode(' · ', $quoted) . $more;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function multipleH1DetailsHtml(array $meta): string
    {
        $count = (int) ($meta['count'] ?? 0);
        $samples = self::multipleH1Samples($meta);
        if ($count < 1) {
            $count = count($samples);
        }
        if ($count < 1 && $samples === []) {
            return '<div class="cabinet-sa-mh1">несколько H1</div>';
        }

        $html = '<div class="cabinet-sa-mh1">';
        $html .= '<div class="cabinet-sa-mh1__head">'
            . '<span class="cabinet-sa-mh1__count">'
            . number_format(max($count, count($samples)), 0, '', ' ')
            . '</span>'
            . '<span class="cabinet-sa-mh1__head-text">'
            . e(self::ruWordForm(max($count, 1), 'заголовок H1 на странице', 'заголовка H1 на странице', 'заголовков H1 на странице'))
            . '</span></div>';

        if ($samples !== []) {
            $html .= '<ol class="cabinet-sa-mh1__list">';
            foreach ($samples as $i => $t) {
                $html .= '<li class="cabinet-sa-mh1__item">'
                    . '<span class="cabinet-sa-mh1__n" aria-hidden="true">' . ($i + 1) . '</span>'
                    . '<span class="cabinet-sa-mh1__text">«' . e(self::clip($t, 140)) . '»</span>'
                    . '</li>';
            }
            $html .= '</ol>';
        } else {
            $html .= '<div class="cabinet-sa-mh1__more text-secondary">'
                . 'Тексты H1 появятся после нового обхода страницы.'
                . '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return list<string>
     */
    private static function metaTextSamples(array $meta, string $key): array
    {
        $raw = [];
        if (! empty($meta[$key]) && is_array($meta[$key])) {
            $raw = $meta[$key];
        } elseif ($key === 'titles' && ! empty($meta['title'])) {
            $raw = [(string) $meta['title']];
        } elseif ($key === 'descriptions' && ! empty($meta['description'])) {
            $raw = [(string) $meta['description']];
        }
        $out = [];
        foreach ($raw as $t) {
            $t = trim((string) $t);
            if ($t === '') {
                continue;
            }
            $out[] = mb_substr($t, 0, $key === 'descriptions' ? 400 : 300);
            if (count($out) >= 8) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return list<string>
     */
    private static function multipleCanonicalSamples(array $meta): array
    {
        $samples = self::metaTextSamples($meta, 'canonicals');
        if ($samples !== []) {
            return $samples;
        }
        $one = trim((string) ($meta['canonical'] ?? ''));

        return $one !== '' ? [$one] : [];
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function multipleCanonicalPlain(array $meta): string
    {
        $count = (int) ($meta['count'] ?? 0);
        $samples = self::multipleCanonicalSamples($meta);
        if ($count < 1) {
            $count = count($samples);
        }
        if ($count < 1 && $samples === []) {
            return 'несколько canonical';
        }

        $bits = [];
        foreach ($samples as $href) {
            $bits[] = self::clip($href, 96);
        }
        $head = number_format(max($count, count($samples)), 0, '', ' ') . ' canonical';
        if ($bits === []) {
            return $head;
        }

        return $head . ': ' . implode(' · ', $bits);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function multipleCanonicalDetailsHtml(array $meta): string
    {
        $count = (int) ($meta['count'] ?? 0);
        $samples = self::multipleCanonicalSamples($meta);
        if ($count < 1) {
            $count = count($samples);
        }
        if ($count < 1 && $samples === []) {
            return '<div class="cabinet-sa-mh1">несколько canonical</div>';
        }

        $n = max($count, count($samples));
        $html = '<div class="cabinet-sa-mh1">';
        $html .= '<div class="cabinet-sa-mh1__head">'
            . '<span class="cabinet-sa-mh1__count">'
            . number_format($n, 0, '', ' ')
            . '</span>'
            . '<span class="cabinet-sa-mh1__head-text">'
            . e(self::ruWordForm($n, 'тег rel=canonical', 'тега rel=canonical', 'тегов rel=canonical'))
            . '</span></div>';

        if ($samples !== []) {
            $html .= '<ol class="cabinet-sa-mh1__list">';
            foreach ($samples as $i => $href) {
                $html .= '<li class="cabinet-sa-mh1__item">'
                    . '<span class="cabinet-sa-mh1__n" aria-hidden="true">' . ($i + 1) . '</span>'
                    . '<span class="cabinet-sa-mh1__text">' . e(self::clip($href, 200)) . '</span>'
                    . '</li>';
            }
            $html .= '</ol>';
            if ($count > count($samples)) {
                $html .= '<div class="cabinet-sa-mh1__more text-secondary">'
                    . 'Показаны ' . number_format(count($samples), 0, '', ' ')
                    . ' из ' . number_format($count, 0, '', ' ')
                    . '. Полный список — после нового обхода.'
                    . '</div>';
            }
        } else {
            $html .= '<div class="cabinet-sa-mh1__more text-secondary">'
                . 'Адреса canonical появятся после нового обхода страницы.'
                . '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function multipleTitleDescriptionPlain(array $meta): string
    {
        $titleCount = (int) ($meta['title_count'] ?? 0);
        $descCount = (int) ($meta['description_count'] ?? 0);
        $titles = self::metaTextSamples($meta, 'titles');
        $descs = self::metaTextSamples($meta, 'descriptions');
        if ($titleCount < 1) {
            $titleCount = count($titles);
        }
        if ($descCount < 1) {
            $descCount = count($descs);
        }

        $bits = [];
        if ($titleCount > 1) {
            $bit = number_format($titleCount, 0, '', ' ') . ' TITLE';
            if ($titles !== []) {
                $quoted = [];
                foreach ($titles as $t) {
                    $quoted[] = '«' . self::clip($t, 72) . '»';
                }
                $bit .= ': ' . implode(' · ', $quoted);
            }
            $bits[] = $bit;
        }
        if ($descCount > 1) {
            $bit = number_format($descCount, 0, '', ' ') . ' Description';
            if ($descs !== []) {
                $quoted = [];
                foreach ($descs as $t) {
                    $quoted[] = '«' . self::clip($t, 72) . '»';
                }
                $bit .= ': ' . implode(' · ', $quoted);
            }
            $bits[] = $bit;
        }

        return $bits !== [] ? implode(' · ', $bits) : 'несколько title/description';
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function multipleTitleDescriptionDetailsHtml(array $meta): string
    {
        $titleCount = (int) ($meta['title_count'] ?? 0);
        $descCount = (int) ($meta['description_count'] ?? 0);
        $titles = self::metaTextSamples($meta, 'titles');
        $descs = self::metaTextSamples($meta, 'descriptions');
        if ($titleCount < 1) {
            $titleCount = count($titles);
        }
        if ($descCount < 1) {
            $descCount = count($descs);
        }

        $html = '<div class="cabinet-sa-mtd">';
        $showTitle = $titleCount > 1 || count($titles) > 1;
        $showDesc = $descCount > 1 || count($descs) > 1;
        if (! $showTitle && ! $showDesc) {
            $showTitle = $titleCount > 0 || $titles !== [];
            $showDesc = $descCount > 0 || $descs !== [];
        }

        if ($showTitle) {
            $html .= self::mtdSectionHtml(
                'TITLE',
                max($titleCount, count($titles)),
                $titles,
                $titleCount > 1 || count($titles) > 1
            );
        }
        if ($showDesc) {
            $html .= self::mtdSectionHtml(
                'Description',
                max($descCount, count($descs)),
                $descs,
                $descCount > 1 || count($descs) > 1
            );
        }
        if (! $showTitle && ! $showDesc) {
            $html .= '<div class="cabinet-sa-mtd__empty text-secondary">несколько title/description</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * @param  list<string>  $samples
     */
    private static function mtdSectionHtml(string $label, int $count, array $samples, bool $isProblem): string
    {
        // Показываем все сохранённые тексты; count = max(declared, samples), без «… и ещё N».
        $count = max($count, count($samples));
        $html = '<div class="cabinet-sa-mtd__section' . ($isProblem ? ' is-problem' : '') . '">';
        $html .= '<div class="cabinet-sa-mtd__head">'
            . '<span class="cabinet-sa-mtd__label">' . e($label) . '</span>'
            . '<span class="cabinet-sa-mtd__count">'
            . number_format(max($count, 0), 0, '', ' ')
            . '</span>'
            . '<span class="cabinet-sa-mtd__head-text">'
            . ($isProblem ? 'дубля на странице' : 'на странице')
            . '</span></div>';

        if ($samples !== []) {
            $html .= '<ol class="cabinet-sa-mtd__list">';
            foreach ($samples as $i => $t) {
                $html .= '<li class="cabinet-sa-mtd__item">'
                    . '<span class="cabinet-sa-mtd__n" aria-hidden="true">' . ($i + 1) . '</span>'
                    . '<span class="cabinet-sa-mtd__text">«' . e(self::clip($t, 220)) . '»</span>'
                    . '</li>';
            }
            $html .= '</ol>';
        } else {
            $html .= '<div class="cabinet-sa-mtd__more text-secondary">'
                . 'Тексты появятся после нового обхода.'
                . '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Plain для CSV/экспорта: куда уходит каждый хост и что нет склейки 301.
     *
     * @param  array<string, mixed>  $meta
     */
    private static function wwwBothAvailablePlain(array $meta): string
    {
        $apexUrl = trim((string) ($meta['apex_url'] ?? $meta['apex_final'] ?? ''));
        $wwwUrl = trim((string) ($meta['www_url'] ?? $meta['www_final'] ?? ''));
        $apexFinal = trim((string) ($meta['apex_final'] ?? $apexUrl));
        $wwwFinal = trim((string) ($meta['www_final'] ?? $wwwUrl));
        $apexStatus = isset($meta['apex_status']) ? (int) $meta['apex_status'] : null;
        $wwwStatus = isset($meta['www_status']) ? (int) $meta['www_status'] : null;

        if ($apexUrl === '' && $wwwUrl === '' && empty($meta['variants'])) {
            return 'оба зеркала доступны';
        }

        $apexBit = 'без www';
        if ($apexUrl !== '') {
            $apexBit .= ' ' . self::clip($apexUrl, 48);
        }
        $apexBit .= self::hostProbeOutcomePlain(
            ! empty($meta['apex_redirected']),
            $apexStatus,
            $apexFinal,
            (string) ($meta['apex_stays_on'] ?? 'apex')
        );

        $wwwBit = 'с www';
        if ($wwwUrl !== '') {
            $wwwBit .= ' ' . self::clip($wwwUrl, 48);
        }
        $wwwBit .= self::hostProbeOutcomePlain(
            ! empty($meta['www_redirected']),
            $wwwStatus,
            $wwwFinal,
            (string) ($meta['www_stays_on'] ?? 'www')
        );

        return $apexBit . ' · ' . $wwwBit . ' · нужен 301 на одно зеркало';
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function httpHttpsBothAvailablePlain(array $meta): string
    {
        $http = trim((string) ($meta['http_url'] ?? $meta['http_final'] ?? ''));
        $target = trim((string) ($meta['fix_301_to'] ?? $meta['https_target'] ?? ''));
        $status = isset($meta['http_status']) ? (int) $meta['http_status'] : null;
        $bits = [];
        if ($http !== '') {
            $bits[] = 'http: ' . self::clip($http, 48);
        }
        if ($status) {
            $bits[] = 'HTTP ' . $status;
        }
        $bits[] = 'без редиректа на https';
        if ($target !== '') {
            $bits[] = '301 → ' . self::clip($target, 48);
        }

        return $bits !== [] ? implode(' · ', $bits) : 'http открыт параллельно с https';
    }

    private static function hostProbeOutcomePlain(
        bool $redirected,
        ?int $status,
        string $finalUrl,
        string $staysOn
    ): string {
        $hostLabel = $staysOn === 'www' ? 'www' : 'без www';
        if ($redirected && $finalUrl !== '') {
            $code = $status ?: 301;

            return ' → ' . $code . ' на ' . self::clip($finalUrl, 40) . ' (' . $hostLabel . ')';
        }
        $code = $status ?: 200;

        return ' → ' . $code . ', остаётся на ' . $hostLabel;
    }

    /**
     * Карточка: проверка www / без www и куда ставить 301.
     *
     * @param  array<string, mixed>  $meta
     */
    private static function wwwBothAvailableDetailsHtml(array $meta): string
    {
        $apexUrl = trim((string) ($meta['apex_url'] ?? ''));
        $wwwUrl = trim((string) ($meta['www_url'] ?? ''));
        $apexFinal = trim((string) ($meta['apex_final'] ?? $apexUrl));
        $wwwFinal = trim((string) ($meta['www_final'] ?? $wwwUrl));
        $fixApex = trim((string) ($meta['fix_301_to_apex'] ?? $apexUrl));
        $fixWww = trim((string) ($meta['fix_301_to_www'] ?? $wwwUrl));

        // Старая demo-meta: только variants.
        if ($apexUrl === '' && $wwwUrl === '' && ! empty($meta['variants']) && is_array($meta['variants'])) {
            $vars = array_values(array_filter(array_map('strval', $meta['variants'])));
            if (count($vars) >= 2) {
                $apexUrl = $vars[0];
                $wwwUrl = $vars[1];
                $apexFinal = $apexUrl;
                $wwwFinal = $wwwUrl;
                $fixApex = $apexUrl;
                $fixWww = $wwwUrl;
            }
        }

        if ($apexUrl === '' && $wwwUrl === '') {
            return self::linkifyUrlsInText(self::wwwBothAvailablePlain($meta));
        }

        $html = '<div class="cabinet-sa-mirror">';
        $html .= '<div class="cabinet-sa-mirror__chips">'
            . '<span class="cabinet-sa-mirror__chip cabinet-sa-mirror__chip--bad">зеркала не склеены</span>'
            . '<span class="cabinet-sa-mirror__chip">301 нет</span>'
            . '</div>';

        $html .= self::mirrorSideHtml(
            'Без www',
            $apexUrl !== '' ? $apexUrl : $apexFinal,
            $apexFinal,
            isset($meta['apex_status']) ? (int) $meta['apex_status'] : null,
            ! empty($meta['apex_redirected']),
            is_array($meta['apex_redirect_statuses'] ?? null) ? $meta['apex_redirect_statuses'] : [],
            (string) ($meta['apex_stays_on'] ?? 'apex')
        );
        $html .= self::mirrorSideHtml(
            'С www',
            $wwwUrl !== '' ? $wwwUrl : $wwwFinal,
            $wwwFinal,
            isset($meta['www_status']) ? (int) $meta['www_status'] : null,
            ! empty($meta['www_redirected']),
            is_array($meta['www_redirect_statuses'] ?? null) ? $meta['www_redirect_statuses'] : [],
            (string) ($meta['www_stays_on'] ?? 'www')
        );

        $html .= '<div class="cabinet-sa-mirror__fix">'
            . '<div class="cabinet-sa-mirror__fix-title">Куда вести 301</div>'
            . '<div class="cabinet-sa-mirror__fix-text">'
            . 'Выберите одно каноническое зеркало и сделайте 301 со второго на него.'
            . '</div>';
        if ($fixApex !== '') {
            $html .= '<div class="cabinet-sa-mirror__opt">'
                . '<span class="cabinet-sa-mirror__opt-k">Вариант A</span>'
                . '<span class="cabinet-sa-mirror__opt-v">'
                . 'с www → без www: ' . self::urlLinkHtml($fixApex)
                . '</span></div>';
        }
        if ($fixWww !== '') {
            $html .= '<div class="cabinet-sa-mirror__opt">'
                . '<span class="cabinet-sa-mirror__opt-k">Вариант B</span>'
                . '<span class="cabinet-sa-mirror__opt-v">'
                . 'без www → с www: ' . self::urlLinkHtml($fixWww)
                . '</span></div>';
        }
        $html .= '</div></div>';

        return $html;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function httpHttpsBothAvailableDetailsHtml(array $meta): string
    {
        $httpUrl = trim((string) ($meta['http_url'] ?? $meta['http_final'] ?? ''));
        $httpFinal = trim((string) ($meta['http_final'] ?? $httpUrl));
        $target = trim((string) ($meta['fix_301_to'] ?? $meta['https_target'] ?? ''));
        $httpsApex = trim((string) ($meta['https_apex_url'] ?? ''));
        $httpsWww = trim((string) ($meta['https_www_url'] ?? ''));

        if ($httpUrl === '' && ! empty($meta['variants']) && is_array($meta['variants'])) {
            $vars = array_values(array_filter(array_map('strval', $meta['variants'])));
            if ($vars !== []) {
                $httpUrl = $vars[0];
                $httpFinal = $httpUrl;
                if (isset($vars[1])) {
                    $target = $vars[1];
                }
            }
        }

        if ($httpUrl === '' && $target === '') {
            return self::linkifyUrlsInText(self::httpHttpsBothAvailablePlain($meta));
        }

        $status = isset($meta['http_status']) ? (int) $meta['http_status'] : 200;
        $html = '<div class="cabinet-sa-mirror">';
        $html .= '<div class="cabinet-sa-mirror__chips">'
            . '<span class="cabinet-sa-mirror__chip cabinet-sa-mirror__chip--bad">http без 301 на https</span>'
            . '</div>';

        $html .= '<div class="cabinet-sa-mirror__side">'
            . '<div class="cabinet-sa-mirror__side-label">HTTP</div>'
            . '<div class="cabinet-sa-mirror__side-body">'
            . '<div class="cabinet-sa-mirror__url">' . self::urlLinkHtml($httpUrl !== '' ? $httpUrl : $httpFinal) . '</div>'
            . '<div class="cabinet-sa-mirror__outcome">'
            . self::statusPillHtml($status)
            . ' <span class="text-secondary">остаётся на http (редиректа на https нет)</span>'
            . '</div>';
        if ($httpFinal !== '' && strcasecmp(rtrim($httpFinal, '/'), rtrim($httpUrl, '/')) !== 0) {
            $html .= '<div class="cabinet-sa-mirror__final text-secondary">финал: '
                . self::urlLinkHtml($httpFinal) . '</div>';
        }
        $html .= '</div></div>';

        if ($httpsApex !== '' || $httpsWww !== '') {
            $html .= '<div class="cabinet-sa-mirror__side">'
                . '<div class="cabinet-sa-mirror__side-label">HTTPS</div>'
                . '<div class="cabinet-sa-mirror__side-body">';
            if ($httpsApex !== '') {
                $html .= '<div class="cabinet-sa-mirror__url">' . self::urlLinkHtml($httpsApex)
                    . ' <span class="text-secondary">тоже доступен</span></div>';
            }
            if ($httpsWww !== '') {
                $html .= '<div class="cabinet-sa-mirror__url">' . self::urlLinkHtml($httpsWww)
                    . ' <span class="text-secondary">тоже доступен</span></div>';
            }
            $html .= '</div></div>';
        }

        $html .= '<div class="cabinet-sa-mirror__fix">'
            . '<div class="cabinet-sa-mirror__fix-title">Куда вести 301</div>';
        if ($target !== '') {
            $html .= '<div class="cabinet-sa-mirror__opt">'
                . '<span class="cabinet-sa-mirror__opt-k">Нужно</span>'
                . '<span class="cabinet-sa-mirror__opt-v">'
                . 'http → https: ' . self::urlLinkHtml($target)
                . '</span></div>';
        } else {
            $html .= '<div class="cabinet-sa-mirror__fix-text">'
                . 'Включите принудительный 301 со всех http-адресов на https.'
                . '</div>';
        }
        $html .= '</div></div>';

        return $html;
    }

    /**
     * @param  list<int|string>  $redirectStatuses
     */
    private static function mirrorSideHtml(
        string $label,
        string $startUrl,
        string $finalUrl,
        ?int $status,
        bool $redirected,
        array $redirectStatuses,
        string $staysOn
    ): string {
        $hostLabel = $staysOn === 'www' ? 'www' : 'без www';
        $code = $status ?: ($redirected ? 301 : 200);
        $firstRedirect = null;
        foreach ($redirectStatuses as $s) {
            $n = (int) $s;
            if ($n >= 300 && $n < 400) {
                $firstRedirect = $n;
                break;
            }
        }

        $html = '<div class="cabinet-sa-mirror__side">'
            . '<div class="cabinet-sa-mirror__side-label">' . e($label) . '</div>'
            . '<div class="cabinet-sa-mirror__side-body">'
            . '<div class="cabinet-sa-mirror__url">' . self::urlLinkHtml($startUrl) . '</div>';

        if ($redirected && $finalUrl !== '' && strcasecmp(rtrim($finalUrl, '/'), rtrim($startUrl, '/')) !== 0) {
            $hop = $firstRedirect ?: 301;
            $html .= '<div class="cabinet-sa-mirror__outcome">'
                . self::statusPillHtml($hop)
                . ' → ' . self::urlLinkHtml($finalUrl)
                . ' <span class="text-secondary">(' . e($hostLabel) . ')</span>'
                . '</div>';
            if ($status && $status !== $hop) {
                $html .= '<div class="cabinet-sa-mirror__final text-secondary">финальный ответ '
                    . self::statusPillHtml($status) . '</div>';
            }
        } else {
            $html .= '<div class="cabinet-sa-mirror__outcome">'
                . self::statusPillHtml($code)
                . ' <span class="text-secondary">остаётся на ' . e($hostLabel)
                . ' — 301 на другое зеркало нет</span>'
                . '</div>';
        }

        $html .= '</div></div>';

        return $html;
    }

    private static function statusPillHtml(int $status): string
    {
        $cls = 'cabinet-sa-status-pill';
        if ($status >= 200 && $status < 300) {
            $cls .= ' cabinet-sa-status-pill--2xx';
        } elseif ($status >= 300 && $status < 400) {
            $cls .= ' cabinet-sa-status-pill--3xx';
        } elseif ($status >= 400 && $status < 500) {
            $cls .= ' cabinet-sa-status-pill--4xx';
        } elseif ($status >= 500) {
            $cls .= ' cabinet-sa-status-pill--5xx';
        }

        return '<span class="' . $cls . '">' . (int) $status . '</span>';
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function insecureFormPlain(array $meta): string
    {
        $samples = self::insecureFormSamples($meta);
        $n = max((int) ($meta['count'] ?? 0), count($samples));
        if ($n < 1 && $samples === []) {
            return 'form action=http';
        }
        $first = $samples[0] ?? null;
        $bits = ['форм: ' . max($n, 1)];
        if (is_array($first)) {
            if (! empty($first['id'])) {
                $bits[] = 'id=' . self::clip((string) $first['id'], 40);
            } elseif (! empty($first['name'])) {
                $bits[] = 'name=' . self::clip((string) $first['name'], 40);
            } elseif (! empty($first['class'])) {
                $bits[] = 'class=' . self::clip((string) $first['class'], 40);
            }
            if (! empty($first['method'])) {
                $bits[] = strtoupper((string) $first['method']);
            }
            if (! empty($first['action'])) {
                $bits[] = self::clip((string) $first['action'], 56);
            }
        }

        return implode(' · ', $bits);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return list<array{action:string, id:?string, name:?string, class:?string, method:?string, snippet:?string}>
     */
    private static function insecureFormSamples(array $meta): array
    {
        $raw = isset($meta['samples']) && is_array($meta['samples']) ? $meta['samples'] : [];
        $out = [];
        foreach ($raw as $sample) {
            if (is_string($sample) && trim($sample) !== '') {
                $out[] = [
                    'action' => trim($sample),
                    'id' => null,
                    'name' => null,
                    'class' => null,
                    'method' => null,
                    'snippet' => null,
                ];
                continue;
            }
            if (! is_array($sample)) {
                continue;
            }
            $action = trim((string) ($sample['action'] ?? $sample['url'] ?? ''));
            if ($action === '' && ! empty($sample[0]) && is_string($sample[0])) {
                $action = trim($sample[0]);
            }
            if ($action === '') {
                continue;
            }
            $out[] = [
                'action' => $action,
                'id' => isset($sample['id']) && trim((string) $sample['id']) !== ''
                    ? trim((string) $sample['id']) : null,
                'name' => isset($sample['name']) && trim((string) $sample['name']) !== ''
                    ? trim((string) $sample['name']) : null,
                'class' => isset($sample['class']) && trim((string) $sample['class']) !== ''
                    ? trim((string) $sample['class']) : null,
                'method' => isset($sample['method']) && trim((string) $sample['method']) !== ''
                    ? trim((string) $sample['method']) : null,
                'snippet' => isset($sample['snippet']) && trim((string) $sample['snippet']) !== ''
                    ? trim((string) $sample['snippet']) : null,
            ];
        }

        return $out;
    }

    /**
     * Карточка формы: id/name/class/method + http action + фрагмент тега.
     *
     * @param  array<string, mixed>  $meta
     */
    private static function insecureFormDetailsHtml(array $meta): ?string
    {
        $samples = self::insecureFormSamples($meta);
        $n = max((int) ($meta['count'] ?? 0), count($samples));
        if ($n < 1 && $samples === []) {
            return null;
        }

        $html = '<div class="cabinet-sa-iform">';
        $html .= '<div class="cabinet-sa-iform__head">'
            . '<span class="cabinet-sa-iform__count">'
            . number_format(max($n, count($samples) ?: 1), 0, '', ' ')
            . '</span>'
            . '<span class="cabinet-sa-iform__head-text">'
            . e(self::ruWordForm(max($n, 1), 'форма с action по http', 'формы с action по http', 'форм с action по http'))
            . '</span></div>';

        foreach (array_slice($samples, 0, 5) as $i => $sample) {
            $html .= '<div class="cabinet-sa-iform__card">';
            if (count($samples) > 1) {
                $html .= '<div class="cabinet-sa-iform__card-n">#' . ($i + 1) . '</div>';
            }

            $identBits = [];
            if (! empty($sample['id'])) {
                $identBits[] = ['id', (string) $sample['id']];
            }
            if (! empty($sample['name'])) {
                $identBits[] = ['name', (string) $sample['name']];
            }
            if (! empty($sample['class'])) {
                $identBits[] = ['class', (string) $sample['class']];
            }
            if (! empty($sample['method'])) {
                $identBits[] = ['method', strtoupper((string) $sample['method'])];
            }

            if ($identBits !== []) {
                $html .= '<div class="cabinet-sa-iform__idents">';
                foreach ($identBits as $bit) {
                    $html .= '<span class="cabinet-sa-iform__chip">'
                        . '<span class="cabinet-sa-iform__chip-k">' . e($bit[0]) . '</span>'
                        . '<code class="cabinet-sa-iform__chip-v">' . e(self::clip($bit[1], 64)) . '</code>'
                        . '</span>';
                }
                $html .= '</div>';
            } else {
                $html .= '<div class="cabinet-sa-iform__no-id text-secondary">'
                    . 'у формы нет id / name / class — ищите по action в HTML'
                    . '</div>';
            }

            $html .= '<div class="cabinet-sa-iform__row">'
                . '<span class="cabinet-sa-iform__label">action</span>'
                . '<span class="cabinet-sa-iform__val">'
                . self::urlLinkHtml((string) $sample['action'])
                . '</span></div>';

            $snippet = trim((string) ($sample['snippet'] ?? ''));
            if ($snippet !== '') {
                $html .= '<div class="cabinet-sa-iform__row">'
                    . '<span class="cabinet-sa-iform__label">В коде</span>'
                    . '<code class="cabinet-sa-iform__snippet">' . e(self::clip($snippet, 180)) . '</code>'
                    . '</div>';
            }

            $findHint = '';
            if (! empty($sample['id'])) {
                $findHint = 'id="' . self::clip((string) $sample['id'], 40) . '"';
            } elseif (! empty($sample['name'])) {
                $findHint = 'name="' . self::clip((string) $sample['name'], 40) . '"';
            } elseif (! empty($sample['class'])) {
                $findHint = 'class="' . self::clip((string) $sample['class'], 40) . '"';
            }
            if ($findHint !== '') {
                $html .= '<div class="cabinet-sa-iform__hint">В исходнике страницы ищите: <code>&lt;form</code> с '
                    . '<code>' . e($findHint) . '</code></div>';
            }

            $html .= '</div>';
        }

        $shown = min(5, count($samples));
        $extra = $n - $shown;
        if ($extra > 0) {
            $html .= '<div class="cabinet-sa-iform__more">и ещё '
                . number_format($extra, 0, '', ' ') . ' на этой странице</div>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * Soft 404: почему страница похожа на «не найдено» при ответе 200.
     *
     * @param  array<string, mixed>  $meta
     */
    private static function soft404Plain(array $meta): string
    {
        $reason = self::soft404ReasonKey($meta);
        $words = isset($meta['word_count']) ? (int) $meta['word_count'] : null;
        $threshold = isset($meta['threshold']) ? (int) $meta['threshold'] : null;
        $title = trim((string) ($meta['title'] ?? ''));
        $h1 = trim((string) ($meta['h1'] ?? ''));
        $pattern = trim((string) ($meta['pattern'] ?? ''));

        if ($reason === 'title_pattern' || $reason === 'h1_pattern') {
            $where = $reason === 'h1_pattern' ? 'H1' : 'TITLE';
            $sample = $reason === 'h1_pattern' ? $h1 : $title;
            $bit = 'в ' . $where . ' признак «не найдено»';
            if ($pattern !== '') {
                $bit .= ' («' . self::clip($pattern, 40) . '»)';
            }
            if ($sample !== '') {
                $bit .= ' · «' . self::clip($sample, 70) . '»';
            }

            return 'Ответ 200, но ' . $bit;
        }

        // thin / thin_200 / неизвестно с word_count
        if ($words !== null && $words >= 0) {
            $bit = 'почти нет текста: '
                . number_format($words, 0, '', ' ')
                . ' '
                . self::ruWordForm($words, 'слово', 'слова', 'слов');
            if ($threshold !== null && $threshold > 0) {
                $bit .= ' (порог soft 404 — '
                    . number_format($threshold, 0, '', ' ')
                    . ')';
            }

            return 'Ответ 200, но ' . $bit;
        }

        if ($title !== '') {
            return 'Ответ 200, похоже на «не найдено» · TITLE: «' . self::clip($title, 70) . '»';
        }

        return 'Ответ 200, страница похожа на «не найдено»';
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function soft404DetailsHtml(array $meta): string
    {
        $reason = self::soft404ReasonKey($meta);
        $words = isset($meta['word_count']) ? (int) $meta['word_count'] : null;
        $threshold = isset($meta['threshold']) ? (int) $meta['threshold'] : null;
        $title = trim((string) ($meta['title'] ?? ''));
        $h1 = trim((string) ($meta['h1'] ?? ''));
        $pattern = trim((string) ($meta['pattern'] ?? ''));

        $rows = [];
        $rows[] = '<div class="cabinet-sa-http-details__row">'
            . '<span class="cabinet-sa-http-details__k">Ответ</span>'
            . '<span class="cabinet-sa-http-details__v">'
            . '<span class="cabinet-sa-status-pill cabinet-sa-status-pill--2xx">200</span>'
            . ' <span class="text-secondary">но страница похожа на «не найдено»</span>'
            . '</span></div>';

        if ($reason === 'title_pattern' || $reason === 'h1_pattern') {
            $where = $reason === 'h1_pattern' ? 'H1' : 'TITLE';
            $sample = $reason === 'h1_pattern' ? $h1 : $title;
            $why = 'В ' . $where . ' есть признак ошибки / «не найдено»';
            if ($pattern !== '') {
                $why .= ': «' . e(self::clip($pattern, 48)) . '»';
            }
            $rows[] = '<div class="cabinet-sa-http-details__row">'
                . '<span class="cabinet-sa-http-details__k">Почему</span>'
                . '<span class="cabinet-sa-http-details__v">' . $why . '</span></div>';
            if ($sample !== '') {
                $rows[] = '<div class="cabinet-sa-http-details__row">'
                    . '<span class="cabinet-sa-http-details__k">' . e($where) . '</span>'
                    . '<span class="cabinet-sa-http-details__v">«' . e(self::clip($sample, 120)) . '»</span></div>';
            }
        } else {
            $why = 'На странице почти нет текста';
            if ($words !== null && $words >= 0) {
                $why .= ' — '
                    . number_format($words, 0, '', ' ')
                    . ' '
                    . self::ruWordForm($words, 'слово', 'слова', 'слов');
            }
            if ($threshold !== null && $threshold > 0) {
                $why .= ' (порог soft 404: меньше '
                    . number_format($threshold, 0, '', ' ')
                    . ')';
            }
            $rows[] = '<div class="cabinet-sa-http-details__row">'
                . '<span class="cabinet-sa-http-details__k">Почему</span>'
                . '<span class="cabinet-sa-http-details__v">' . e($why) . '</span></div>';
            if ($title !== '') {
                $rows[] = '<div class="cabinet-sa-http-details__row">'
                    . '<span class="cabinet-sa-http-details__k">TITLE</span>'
                    . '<span class="cabinet-sa-http-details__v">«' . e(self::clip($title, 120)) . '»</span></div>';
            }
        }

        $rows[] = '<div class="cabinet-sa-http-details__row">'
            . '<span class="cabinet-sa-http-details__k">Что сделать</span>'
            . '<span class="cabinet-sa-http-details__v text-secondary">'
            . 'Отдайте настоящий 404/410 или редирект 301 на нужную страницу — не маскируйте «не найдено» кодом 200.'
            . '</span></div>';

        return '<div class="cabinet-sa-http-details">' . implode('', $rows) . '</div>';
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private static function soft404ReasonKey(array $meta): string
    {
        $reason = trim((string) ($meta['reason'] ?? ''));
        if ($reason === 'thin_200') {
            return 'thin';
        }
        if (in_array($reason, ['thin', 'title_pattern', 'h1_pattern'], true)) {
            return $reason;
        }
        $matchedIn = trim((string) ($meta['matched_in'] ?? ''));
        if ($matchedIn === 'h1') {
            return 'h1_pattern';
        }
        if ($matchedIn === 'title') {
            return 'title_pattern';
        }
        if (isset($meta['word_count'])) {
            return 'thin';
        }

        return $reason !== '' ? $reason : 'thin';
    }

    private static function ruWordForm(int $n, string $one, string $few, string $many): string
    {
        $n = abs($n) % 100;
        $n1 = $n % 10;
        if ($n > 10 && $n < 20) {
            return $many;
        }
        if ($n1 === 1) {
            return $one;
        }
        if ($n1 >= 2 && $n1 <= 4) {
            return $few;
        }

        return $many;
    }

    /**
     * Структурированные детали для http_4xx / http_5xx / unreachable
     * (компактный статус; «кто ссылается» — в соседней колонке).
     *
     * @param  array<string, mixed>  $meta
     */
    private static function httpStatusDetailsHtml(string $code, array $meta, ?string $url): string
    {
        return self::availabilityStatusDetailsHtml($code, $meta, $url);
    }

    /** URL с кавычками/бэкслешами — почти всегда мусор из HTML, не нормальный адрес. */
    public static function urlLooksBrokenHref(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        if (strpbrk($url, "\"'\\<>") !== false) {
            return true;
        }
        // Два «https://» в одном URL — склейка href + пример из текста.
        if (preg_match('#https?://.+https?://#i', $url)) {
            return true;
        }

        return false;
    }

    /**
     * Короткий читаемый ярлык для битого URL в первой колонке.
     *
     * @return array{display:string,warn:?string}
     */
    public static function brokenUrlDisplay(string $url): array
    {
        $url = trim($url);
        if ($url === '') {
            return ['display' => '—', 'warn' => null];
        }

        // Полный URL всегда видимый — без «…». Метка «кривой href» отдельно.
        return [
            'display' => $url,
            'warn' => self::urlLooksBrokenHref($url) ? 'кривой href' : null,
        ];
    }

    private static function httpStatusPillHtml(?int $status, string $fallbackLabel): string
    {
        $cls = 'cabinet-sa-status-pill';
        if ($status !== null && $status >= 500) {
            $cls .= ' cabinet-sa-status-pill--5xx';
        } elseif ($status !== null && $status >= 400) {
            $cls .= ' cabinet-sa-status-pill--4xx';
        }

        return '<span class="' . $cls . '">' . e($fallbackLabel) . '</span>';
    }

    /**
     * @param array<string,mixed> $meta
     */
    private static function redirectDetailsHtml(array $meta, string $code, ?string $url = null): string
    {
        $chain = ! empty($meta['chain']) && is_array($meta['chain']) ? $meta['chain'] : [];
        $final = ! empty($meta['final'])
            ? (string) $meta['final']
            : (! empty($meta['to']) ? (string) $meta['to'] : null);
        $start = trim((string) ($url ?: ''));
        if ($start === '' && ! empty($meta['path'][0])) {
            $start = (string) $meta['path'][0];
        }

        // Demo/старые meta кладут старт внутрь chain — вынесем хопы после старта.
        $hops = [];
        foreach ($chain as $hop) {
            $hop = trim((string) $hop);
            if ($hop === '') {
                continue;
            }
            if ($start !== '' && SiteAuditRedirectChain::normalize($hop) === SiteAuditRedirectChain::normalize($start)) {
                continue;
            }
            $hops[] = $hop;
        }

        if ($start === '' && $hops !== []) {
            $start = (string) array_shift($hops);
        }

        $info = $start !== ''
            ? SiteAuditRedirectChain::analyze($start, $hops, $final)
            : ['path' => $hops, 'loop' => ! empty($meta['loop']), 'at' => null];
        $path = $info['path'] ?? [];
        if ($path === []) {
            $plain = self::metaLine($code, $meta, $url);

            return self::linkifyUrlsInText($plain !== '' ? $plain : '—');
        }

        $from = (string) $path[0];
        $to = (string) end($path);
        $hopCount = max(0, count($path) - 1);
        $isLoop = $code === 'redirect_loop' || ! empty($info['loop']);
        $slashOnly = $hopCount === 1 && SiteAuditRedirectChain::isSlashOnlyRedirect($from, $to);
        $baseHost = (string) (parse_url($from, PHP_URL_HOST) ?: '');

        $chips = [];
        if ($isLoop) {
            $chips[] = ['цикл', 'loop'];
        } elseif ($slashOnly) {
            $chips[] = ['только слэш', 'slash'];
        }
        if ($hopCount > 1) {
            $chips[] = [
                number_format($hopCount, 0, '', ' ') . ' '
                . self::ruWordForm($hopCount, 'редирект', 'редиректа', 'редиректов'),
                'hops',
            ];
        } elseif ($hopCount === 1 && ! $slashOnly) {
            $chips[] = ['1 редирект', 'hops'];
        }
        if (! empty($meta['status'])) {
            $chips[] = ['HTTP ' . (int) $meta['status'], 'http'];
        }

        $html = '<div class="cabinet-sa-redir' . ($isLoop ? ' cabinet-sa-redir--loop' : '') . '">';

        if ($chips !== []) {
            $html .= '<div class="cabinet-sa-redir__chips">';
            foreach ($chips as $chip) {
                $html .= '<span class="cabinet-sa-redir__chip cabinet-sa-redir__chip--' . e($chip[1]) . '">'
                    . e($chip[0]) . '</span>';
            }
            $html .= '</div>';
        }

        $html .= '<div class="cabinet-sa-redir__summary">';
        $html .= '<div class="cabinet-sa-redir__row">'
            . '<span class="cabinet-sa-redir__label">Было</span>'
            . '<span class="cabinet-sa-redir__val">'
            . self::urlLinkHtml($from, self::redirectStepLabel($from, $baseHost))
            . '</span></div>';
        $html .= '<div class="cabinet-sa-redir__row">'
            . '<span class="cabinet-sa-redir__label">Стало</span>'
            . '<span class="cabinet-sa-redir__val">'
            . self::urlLinkHtml($to, self::redirectStepLabel($to, $baseHost))
            . '</span></div>';
        $html .= '</div>';

        // Цепочку показываем, когда есть смысл смотреть шаги (2+ хопа или цикл).
        if ($hopCount > 1 || ($isLoop && count($path) > 1)) {
            $html .= '<div class="cabinet-sa-redir__chain-head">Цепочка</div>';
            $html .= '<ol class="cabinet-sa-redir__steps">';
            $lastIdx = count($path) - 1;
            foreach ($path as $i => $step) {
                $step = (string) $step;
                $isFirst = $i === 0;
                $isLast = $i === $lastIdx;
                $stepClass = 'cabinet-sa-redir__step';
                if ($isFirst) {
                    $stepClass .= ' is-start';
                }
                if ($isLast && $isLoop) {
                    $stepClass .= ' is-loop';
                } elseif ($isLast) {
                    $stepClass .= ' is-end';
                }
                $html .= '<li class="' . $stepClass . '">';
                $html .= '<span class="cabinet-sa-redir__n" aria-hidden="true">' . ($i + 1) . '</span>';
                $html .= '<span class="cabinet-sa-redir__step-body">';
                $html .= self::urlLinkHtml($step, self::redirectStepLabel($step, $baseHost));
                if ($isFirst) {
                    $html .= '<span class="cabinet-sa-redir__tag">старт</span>';
                } elseif ($isLast && $isLoop) {
                    $html .= '<span class="cabinet-sa-redir__tag cabinet-sa-redir__tag--loop">цикл</span>';
                } elseif ($isLast) {
                    $html .= '<span class="cabinet-sa-redir__tag cabinet-sa-redir__tag--end">финал</span>';
                }
                $html .= '</span></li>';
            }
            $html .= '</ol>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Короткая подпись шага редиректа: путь на том же хосте, иначе host+path.
     */
    private static function redirectStepLabel(string $url, string $baseHost = ''): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['host'])) {
            return self::clip($url, 96);
        }
        $host = (string) $parts['host'];
        $path = (string) ($parts['path'] ?? '');
        if ($path === '') {
            $path = '/';
        }
        $query = isset($parts['query']) && $parts['query'] !== '' ? ('?' . $parts['query']) : '';
        $frag = isset($parts['fragment']) && $parts['fragment'] !== '' ? ('#' . $parts['fragment']) : '';
        $tail = $path . $query . $frag;

        if ($baseHost !== '' && strcasecmp($host, $baseHost) === 0) {
            return self::clip($tail, 96);
        }

        return self::clip($host . $tail, 96);
    }

    /**
     * Полный URL как ссылка (без обрезки текста, если label не задан).
     */
    public static function urlLinkHtml(string $url, ?string $label = null): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        $text = $label !== null && trim($label) !== '' ? trim($label) : $url;
        if (! preg_match('#^https?://#i', $url)) {
            return '<span class="cabinet-sa-url-break">' . e($text) . '</span>';
        }

        return '<a class="cabinet-sa-url-break" href="' . e($url) . '" target="_blank" rel="noopener noreferrer">'
            . e($text) . '</a>';
    }

    /**
     * Экранирует текст и делает http(s) URL кликабельными (целиком, без …).
     */
    public static function linkifyUrlsInText(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $parts = preg_split('#(https?://[^\s←→·]+)#iu', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false || $parts === []) {
            return e($text);
        }

        $html = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            // хвостовая пунктуация у URL
            if (preg_match('#^(https?://\S+?)([.,;:)\]]+)?$#iu', $part, $m)) {
                $html .= self::urlLinkHtml($m[1]);
                if (! empty($m[2])) {
                    $html .= e($m[2]);
                }
            } else {
                $html .= e($part);
            }
        }

        return '<span class="cabinet-sa-details-block">' . $html . '</span>';
    }

    /** % уникальности: боевой uniqueness_pct или старый демо-ключ uniqueness. */
    private static function plagiarismExternalUniquenessPct(array $meta): ?float
    {
        if (isset($meta['uniqueness_pct']) && is_numeric($meta['uniqueness_pct'])) {
            return (float) $meta['uniqueness_pct'];
        }
        if (isset($meta['uniqueness']) && is_numeric($meta['uniqueness'])) {
            return (float) $meta['uniqueness'];
        }

        return null;
    }

    /** Главный чужой URL: sources[0].url или старый демо-ключ peer. */
    private static function plagiarismExternalTopSource(array $meta): string
    {
        if (! empty($meta['sources'][0]['url'])) {
            return trim((string) $meta['sources'][0]['url']);
        }
        if (! empty($meta['peer'])) {
            return trim((string) $meta['peer']);
        }
        if (! empty($meta['peer_url'])) {
            return trim((string) $meta['peer_url']);
        }

        return '';
    }

    /**
     * Карточка сравнения заголовков (H1=H2, TITLE=H1, …).
     */
    private static function headingPairDetailsHtml(string $code, array $meta): ?string
    {
        $leftTag = 'A';
        $rightTag = 'B';
        $left = '';
        $right = '';

        switch ($code) {
            case 'h1_equals_h2':
                $leftTag = 'H1';
                $rightTag = 'H2';
                $left = trim((string) ($meta['h1'] ?? ''));
                $right = trim((string) ($meta['h2'] ?? ''));
                break;
            case 'title_equals_h1':
                $leftTag = 'TITLE';
                $rightTag = 'H1';
                $left = trim((string) ($meta['title'] ?? ''));
                $right = trim((string) ($meta['h1'] ?? ''));
                break;
            case 'title_equals_description':
                $leftTag = 'TITLE';
                $rightTag = 'DESC';
                $left = trim((string) ($meta['title'] ?? ''));
                $right = trim((string) ($meta['description'] ?? $meta['title'] ?? ''));
                break;
            case 'description_equals_h1':
                $leftTag = 'DESC';
                $rightTag = 'H1';
                $left = trim((string) ($meta['description'] ?? $meta['h1'] ?? ''));
                $right = trim((string) ($meta['h1'] ?? ''));
                break;
        }

        if ($left === '' && $right === '') {
            return null;
        }
        if ($right === '') {
            $right = $left;
        }
        if ($left === '') {
            $left = $right;
        }

        $same = mb_strtolower($left) === mb_strtolower($right);

        $pairHint = 'Текст в обоих полях один и тот же — так на сайте и записано (это не подпись системы).';
        if ($code === 'title_equals_description') {
            $pairHint = 'В HTML страницы TITLE и meta description совпадают — ниже этот общий текст.';
        } elseif ($code === 'title_equals_h1') {
            $pairHint = 'TITLE и H1 на странице совпадают — ниже этот общий текст.';
        } elseif ($code === 'description_equals_h1') {
            $pairHint = 'Description и H1 совпадают — ниже этот общий текст.';
        } elseif ($code === 'h1_equals_h2') {
            $pairHint = 'H1 и H2 совпадают — ниже этот общий текст.';
        }

        $html = '<div class="cabinet-sa-head-pair">';
        $html .= '<div class="cabinet-sa-head-pair__flag'
            . ($same ? ' is-same' : '') . '">'
            . ($same ? 'совпадают' : 'почти одинаковые')
            . '</div>';
        $html .= '<div class="cabinet-sa-head-pair__hint">' . e($pairHint) . '</div>';

        // Всегда две строки с подписями полей — даже если текст один:
        // иначе кажется, что строка — пояснение системы, а не контент страницы.
        $html .= '<div class="cabinet-sa-head-pair__row">'
            . '<span class="cabinet-sa-head-pair__tag">' . e($leftTag) . '</span>'
            . '<span class="cabinet-sa-head-pair__text">' . e($left) . '</span>'
            . '</div>';
        $html .= '<div class="cabinet-sa-head-pair__row">'
            . '<span class="cabinet-sa-head-pair__tag cabinet-sa-head-pair__tag--alt">' . e($rightTag) . '</span>'
            . '<span class="cabinet-sa-head-pair__text">' . e($right) . '</span>'
            . '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * TITLE / Description: полный текст + длина к порогу.
     */
    private static function metaLengthDetailsHtml(string $code, array $meta): ?string
    {
        $isDesc = strpos($code, 'description_') === 0;
        $field = $isDesc ? 'description' : 'title';
        $label = $isDesc ? 'Description' : 'TITLE';
        $text = trim((string) ($meta[$field] ?? ''));
        $len = isset($meta['length']) ? (int) $meta['length'] : ($text !== '' ? mb_strlen($text) : 0);
        if ($text !== '' && (! isset($meta['length']) || (int) $meta['length'] < 1)) {
            $len = mb_strlen($text);
        }
        $min = isset($meta['min']) ? (int) $meta['min'] : null;
        $max = isset($meta['max']) ? (int) $meta['max'] : null;

        if ($text === '' && $len < 1) {
            return null;
        }

        $html = '<div class="cabinet-sa-meta-len">';

        if ($text !== '') {
            $html .= '<div class="cabinet-sa-meta-len__block">'
                . '<div class="cabinet-sa-meta-len__label">' . e($label) . '</div>'
                . '<div class="cabinet-sa-meta-len__text">' . e($text) . '</div>'
                . '</div>';
        } else {
            $html .= '<div class="cabinet-sa-meta-len__block">'
                . '<div class="cabinet-sa-meta-len__label">' . e($label) . '</div>'
                . '<div class="cabinet-sa-meta-len__text cabinet-sa-meta-len__text--muted">текст не сохранён в находке</div>'
                . '</div>';
        }

        $metaBits = [];
        if ($len > 0) {
            $metaBits[] = 'длина: ' . number_format($len, 0, '', ' ');
        }
        if ($min !== null) {
            $metaBits[] = 'мин: ' . number_format($min, 0, '', ' ');
        }
        if ($max !== null) {
            $metaBits[] = 'макс: ' . number_format($max, 0, '', ' ');
        }
        if ($metaBits !== []) {
            $html .= '<div class="cabinet-sa-meta-len__meta">' . e(implode(' · ', $metaBits)) . '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /** Фраза переспама / тошнота — читаемая «чип» вместо сырой строки. */
    private static function spamPhraseDetailsHtml(string $code, array $meta): ?string
    {
        // Несколько фраз в samples — показываем стеком (демо и будущие богатые находки).
        if (in_array($code, ['text_trigram_spam', 'text_bigram_spam'], true)
            && ! empty($meta['samples']) && is_array($meta['samples'])) {
            $rows = [];
            foreach (array_slice($meta['samples'], 0, 4) as $sample) {
                if (! is_array($sample)) {
                    continue;
                }
                $phrase = trim((string) ($sample['trigram'] ?? $sample['bigram'] ?? $sample['phrase'] ?? ''));
                if ($phrase === '') {
                    continue;
                }
                $bits = [];
                if (isset($sample['count'])) {
                    $bits[] = '×' . (int) $sample['count'];
                }
                if (isset($sample['density'])) {
                    $bits[] = $sample['density'] . '%';
                }
                $rows[] = '<div class="cabinet-sa-spam-phrase__item">'
                    . '<span class="cabinet-sa-spam-phrase__q">«' . e($phrase) . '»</span>'
                    . ($bits !== []
                        ? '<span class="cabinet-sa-spam-phrase__meta">' . e(implode(' · ', $bits)) . '</span>'
                        : '')
                    . '</div>';
            }
            if ($rows !== []) {
                return '<div class="cabinet-sa-spam-phrase cabinet-sa-spam-phrase--stack">'
                    . implode('', $rows)
                    . '</div>';
            }
        }

        $phrase = '';
        $metaBits = [];

        if ($code === 'text_trigram_spam' && ! empty($meta['trigram'])) {
            $phrase = (string) $meta['trigram'];
            $metaBits[] = '×' . (int) ($meta['count'] ?? 0);
            if (isset($meta['density'])) {
                $metaBits[] = $meta['density'] . '%';
            }
        } elseif ($code === 'text_bigram_spam' && ! empty($meta['bigram'])) {
            $phrase = (string) $meta['bigram'];
            $metaBits[] = '×' . (int) ($meta['count'] ?? 0);
            if (isset($meta['density'])) {
                $metaBits[] = $meta['density'] . '%';
            }
        } elseif ($code === 'h1_spam' && ! empty($meta['word'])) {
            $word = (string) $meta['word'];
            $count = (int) ($meta['count'] ?? 0);
            $h1 = trim((string) ($meta['h1'] ?? ''));
            // Старые находки с мусором вместо H1 — не показываем простыню CSS/JSON
            if ($h1 !== '' && (
                mb_strlen($h1) > 300
                || preg_match('/\{"@type"|position\s*:\s*relative|!important/i', $h1)
            )) {
                $h1 = '';
            }
            $html = '<div class="cabinet-sa-spam-phrase cabinet-sa-spam-phrase--h1">';
            $html .= '<span class="cabinet-sa-spam-phrase__chip">'
                . '<span class="cabinet-sa-spam-phrase__chip-w">«' . e($word) . '»</span>'
                . '<span class="cabinet-sa-spam-phrase__chip-n">×' . $count . '</span>'
                . '</span>';
            if ($h1 !== '') {
                $html .= '<div class="cabinet-sa-spam-phrase__h1">' . e($h1) . '</div>';
            }
            $html .= '</div>';

            return $html;
        } elseif ($code === 'word_repeat_in_sentence') {
            $samples = ! empty($meta['samples']) && is_array($meta['samples']) ? $meta['samples'] : [];
            if ($samples !== []) {
                $rows = [];
                foreach (array_slice($samples, 0, 3) as $sample) {
                    if (! is_array($sample) || empty($sample['word'])) {
                        continue;
                    }
                    $rows[] = '<div class="cabinet-sa-spam-phrase__item">'
                        . '<span class="cabinet-sa-spam-phrase__q">«' . e((string) $sample['word']) . '»</span>'
                        . '<span class="cabinet-sa-spam-phrase__meta">×' . (int) ($sample['count'] ?? 0) . '</span>'
                        . '</div>';
                }
                if ($rows !== []) {
                    return '<div class="cabinet-sa-spam-phrase cabinet-sa-spam-phrase--stack">'
                        . implode('', $rows) . '</div>';
                }
            }
            $w = ! empty($meta['samples'][0]['word'])
                ? (string) $meta['samples'][0]['word']
                : '';
            if ($w === '') {
                return null;
            }
            $phrase = $w;
            $metaBits[] = '×' . (int) ($meta['samples'][0]['count'] ?? $meta['count'] ?? 0);
        } elseif ($code === 'meta_spam') {
            $rows = [];
            foreach (['title' => 'TITLE', 'description' => 'Description'] as $field => $label) {
                $block = is_array($meta[$field] ?? null) ? $meta[$field] : null;
                if (! $block || empty($block['word'])) {
                    continue;
                }
                $word = self::clip((string) $block['word'], 40);
                $count = (int) ($block['count'] ?? 0);
                $rows[] = '<div class="cabinet-sa-meta-spam__row">'
                    . '<span class="cabinet-sa-meta-spam__field">' . e($label) . '</span>'
                    . '<span class="cabinet-sa-meta-spam__word" title="' . e((string) $block['word']) . '">«'
                    . e($word) . '»</span>'
                    . '<span class="cabinet-sa-meta-spam__count" title="Сколько раз слово встречается в поле">×'
                    . $count . '</span>'
                    . '</div>';
            }
            if ($rows === []) {
                return null;
            }

            return '<div class="cabinet-sa-meta-spam">'
                . '<div class="cabinet-sa-meta-spam__note">частое слово в мета</div>'
                . implode('', $rows)
                . '</div>';
        } elseif ($code === 'text_nausea') {
            $bits = [];
            if (isset($meta['nausea_classic'])) {
                $bits[] = 'класс. ' . e((string) $meta['nausea_classic']) . '%';
            }
            if (isset($meta['nausea_academic'])) {
                $bits[] = 'акад. ' . e((string) $meta['nausea_academic']) . '%';
            }
            $words = [];
            if (! empty($meta['top_words']) && is_array($meta['top_words'])) {
                foreach (array_slice($meta['top_words'], 0, 6) as $tw) {
                    if (! is_array($tw) || empty($tw['word'])) {
                        continue;
                    }
                    $words[] = $tw;
                }
            } elseif (! empty($meta['top_word'])) {
                $words[] = [
                    'word' => (string) $meta['top_word'],
                    'count' => (int) ($meta['top_word_count'] ?? 0),
                ];
            }
            $html = '<div class="cabinet-sa-spam-phrase cabinet-sa-spam-phrase--nausea">';
            if ($bits !== []) {
                $html .= '<div class="cabinet-sa-spam-phrase__stats">' . implode(' · ', $bits) . '</div>';
            }
            if ($words !== []) {
                $html .= '<div class="cabinet-sa-spam-phrase__chips">';
                foreach ($words as $tw) {
                    $html .= '<span class="cabinet-sa-spam-phrase__chip">'
                        . '<span class="cabinet-sa-spam-phrase__chip-w">«' . e((string) $tw['word']) . '»</span>'
                        . '<span class="cabinet-sa-spam-phrase__chip-n">×' . (int) ($tw['count'] ?? 0) . '</span>'
                        . '</span>';
                }
                $html .= '</div>';
            }
            $html .= '</div>';

            return $html;
        }

        if ($phrase === '') {
            return null;
        }

        return '<div class="cabinet-sa-spam-phrase">'
            . '<span class="cabinet-sa-spam-phrase__q">«' . e($phrase) . '»</span>'
            . ($metaBits !== []
                ? '<span class="cabinet-sa-spam-phrase__meta">' . e(implode(' · ', $metaBits)) . '</span>'
                : '')
            . '</div>';
    }

    /** Внешний антиплагиат: % + чужой URL. */
    private static function plagiarismExternalDetailsHtml(array $meta): ?string
    {
        $pct = self::plagiarismExternalUniquenessPct($meta);
        $src = self::plagiarismExternalTopSource($meta);
        if ($pct === null && $src === '') {
            return null;
        }

        $warn = isset($meta['warn_below']) ? (float) $meta['warn_below'] : 70.0;
        $low = $pct !== null && $pct < $warn;
        $html = '<div class="cabinet-sa-uniq">';
        if ($pct !== null) {
            $html .= '<span class="cabinet-sa-uniq__pct' . ($low ? ' is-low' : '') . '" title="Уникальность текста">'
                . e(rtrim(rtrim(number_format($pct, 1, '.', ''), '0'), '.')) . '%</span>';
        }
        if ($src !== '') {
            $html .= '<a class="cabinet-sa-uniq__src cabinet-sa-url-break" href="' . e($src)
                . '" target="_blank" rel="noopener noreferrer" title="Источник совпадения">'
                . e($src) . '</a>';
        }
        if (! empty($meta['sources'][0]['overlap_pct'])) {
            $html .= '<span class="cabinet-sa-uniq__overlap" title="Перекрытие с источником">'
                . e((string) $meta['sources'][0]['overlap_pct']) . '% совп.</span>';
        }
        $html .= '</div>';

        return $html;
    }

    private static function clip(string $text, int $len): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if ($text === '' || self::isUrlLike($text)) {
            return $text;
        }
        if (mb_strlen($text) <= $len) {
            return $text;
        }

        return mb_substr($text, 0, max(1, $len - 1)) . '…';
    }

    /** URL / путь — никогда не режем в деталях (иначе «…» и нельзя скопировать). */
    private static function isUrlLike(string $text): bool
    {
        if (preg_match('#^https?://#i', $text)) {
            return true;
        }

        return (bool) preg_match('#^/[^\s]{2,}$#', $text);
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' Б';
        }
        // Круглые пороги из конфига (500000) — показываем как 500 КБ, не 488.
        if ($bytes >= 100000 && $bytes % 1000 === 0 && $bytes < 1048576) {
            return number_format((int) round($bytes / 1000), 0, '', ' ') . ' КБ';
        }
        if ($bytes < 1048576) {
            $kb = $bytes / 1024;
            $label = $kb >= 100 ? (string) (int) round($kb) : (string) round($kb, 1);

            return str_replace('.', ',', $label) . ' КБ';
        }
        $mb = $bytes / 1048576;
        $label = $mb >= 10 ? (string) (int) round($mb) : (string) round($mb, 2);

        return str_replace('.', ',', $label) . ' МБ';
    }
}
