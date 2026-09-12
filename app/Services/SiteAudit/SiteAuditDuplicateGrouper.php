<?php

namespace App\Services\SiteAudit;

use Illuminate\Support\Collection;

class SiteAuditDuplicateGrouper
{
    private const GROUPABLE = [
        'duplicate_title',
        'duplicate_description',
        'duplicate_content',
        'html_critical_errors',
        'external_links',
        'external_assets',
        'links_nofollow',
        'page_has_broken_external_links',
        'page_has_broken_links',
        'text_in_noindex',
        'insecure_form',
    ];

    /** Инверсия: группа = исходящая ссылка/ассет, внутри — страницы с ней. */
    private const LINK_INVERTED = [
        'external_links',
        'external_assets',
        'links_nofollow',
        'page_has_broken_external_links',
        'page_has_broken_links',
    ];

    public static function isGroupable(string $code): bool
    {
        return in_array($code, self::GROUPABLE, true);
    }

    public static function isHtmlErrors(string $code): bool
    {
        return $code === 'html_critical_errors';
    }

    public static function isLinkInverted(string $code): bool
    {
        return in_array($code, self::LINK_INVERTED, true);
    }

    public static function isTextInNoindex(string $code): bool
    {
        return $code === 'text_in_noindex';
    }

    public static function isInsecureForm(string $code): bool
    {
        return $code === 'insecure_form';
    }

    /** Лимит findings в память для режима groups (иначе fallback в list). */
    public static function groupsMemoryLimit(string $code): int
    {
        if (self::isHtmlErrors($code)
            || self::isLinkInverted($code)
            || self::isTextInNoindex($code)
            || self::isInsecureForm($code)
        ) {
            return 2500;
        }

        return 400;
    }

    /**
     * @param  Collection|iterable  $rows  SiteAuditFinding-like objects with meta_json
     * @return array<int, array{hash:string,size:int,label:string,severity:string,urls:array<int,array{url:string,severity:string}>,hint:?string,likely_template:bool,href?:string,host?:string}>
     */
    public static function group($rows, string $code): array
    {
        if (self::isHtmlErrors($code)) {
            return self::groupHtmlErrors($rows);
        }
        if (self::isLinkInverted($code)) {
            return self::groupByOutboundUrl($rows, $code);
        }
        if (self::isTextInNoindex($code)) {
            return self::groupTextInNoindex($rows);
        }
        if (self::isInsecureForm($code)) {
            return self::groupByInsecureForm($rows);
        }

        $buckets = [];

        foreach ($rows as $row) {
            $meta = is_array($row->meta_json ?? null) ? $row->meta_json : [];
            $hash = (string) ($meta['hash'] ?? '');
            if ($hash === '') {
                $hash = 'u:' . md5((string) ($row->url ?? '') . '|' . ($row->id ?? uniqid('', true)));
            }

            if (! isset($buckets[$hash])) {
                $buckets[$hash] = [
                    'hash' => $hash,
                    'size' => (int) ($meta['group_size'] ?? 0),
                    'label' => self::labelFor($code, $meta),
                    'severity' => (string) ($row->severity ?? 'other'),
                    'urls' => [],
                    'finding_ids' => [],
                    'hint' => null,
                    'likely_template' => false,
                    '_urls' => [],
                ];
            }

            $prevSize = (int) $buckets[$hash]['size'];
            self::appendUrlToBucket(
                $buckets[$hash],
                $row,
                (string) ($row->url ?? ''),
                (string) ($row->severity ?? 'other')
            );
            if ($prevSize > (int) $buckets[$hash]['size']) {
                $buckets[$hash]['size'] = $prevSize;
            }
        }

        return self::finalizeBuckets($buckets, 0, false);
    }

    /**
     * Одинаковое содержимое <!--noindex--> (соцсети в шаблоне) → одна группа.
     *
     * @param  Collection|iterable  $rows
     * @return array<int, array{hash:string,size:int,label:string,severity:string,urls:array<int,array{url:string,severity:string}>,hint:?string,likely_template:bool}>
     */
    private static function groupTextInNoindex($rows): array
    {
        $buckets = [];
        $pageTotal = 0;

        foreach ($rows as $row) {
            $pageTotal++;
            $meta = is_array($row->meta_json ?? null) ? $row->meta_json : [];
            $hash = trim((string) ($meta['hash'] ?? ''));
            $sample = trim((string) ($meta['sample'] ?? ''));
            $links = isset($meta['links']) && is_array($meta['links']) ? $meta['links'] : [];
            if ($hash === '') {
                $linkKey = [];
                foreach ($links as $l) {
                    if (is_array($l) && ! empty($l['href'])) {
                        $linkKey[] = mb_strtolower((string) $l['href']);
                    }
                }
                sort($linkKey);
                $hash = md5(mb_strtolower($sample) . '|' . implode('|', $linkKey) . '|' . (int) ($meta['noindex_text_len'] ?? 0));
            }

            if (! isset($buckets[$hash])) {
                $buckets[$hash] = [
                    'hash' => $hash,
                    'size' => 0,
                    'label' => self::labelFor('text_in_noindex', $meta),
                    'severity' => (string) ($row->severity ?? 'warning'),
                    'urls' => [],
                    'finding_ids' => [],
                    'hint' => 'Одинаковый блок noindex на многих URL — правьте шаблон (шапка/подвал), не каждую страницу.',
                    'likely_template' => false,
                    '_urls' => [],
                ];
            }

            self::appendUrlToBucket(
                $buckets[$hash],
                $row,
                (string) ($row->url ?? ''),
                (string) ($row->severity ?? 'warning')
            );
        }

        return self::finalizeBuckets($buckets, $pageTotal);
    }

    /**
     * Группировка HTML-ошибок по тексту (без номера строки): одна правка в шаблоне → много URL.
     *
     * @param  Collection|iterable  $rows
     * @return array<int, array{hash:string,size:int,label:string,severity:string,urls:array<int,array{url:string,severity:string}>,hint:?string,likely_template:bool}>
     */
    private static function groupHtmlErrors($rows): array
    {
        $buckets = [];
        $pageTotal = 0;

        foreach ($rows as $row) {
            $pageTotal++;
            $meta = is_array($row->meta_json ?? null) ? $row->meta_json : [];
            $samples = isset($meta['samples']) && is_array($meta['samples']) ? $meta['samples'] : [];
            $url = (string) ($row->url ?? '');
            $severity = (string) ($row->severity ?? 'other');
            $seenOnPage = [];

            if ($samples === []) {
                $sig = 'empty';
                $label = 'Ошибка HTML без текста сэмпла';
                $hint = null;
                $samples = [['message' => $label]];
            }

            foreach ($samples as $sample) {
                if (! is_array($sample)) {
                    continue;
                }
                $msg = trim((string) ($sample['message'] ?? ''));
                if ($msg === '') {
                    continue;
                }
                $sig = self::normalizeHtmlSignature($msg);
                if ($sig === '' || isset($seenOnPage[$sig])) {
                    continue;
                }
                $seenOnPage[$sig] = true;

                if (! isset($buckets[$sig])) {
                    $buckets[$sig] = [
                        'hash' => $sig,
                        'size' => 0,
                        'label' => self::clipLabel($msg, 140),
                        'severity' => $severity,
                        'urls' => [],
                        'finding_ids' => [],
                        'hint' => SiteAuditFindingHelp::htmlErrorHint($msg),
                        'likely_template' => false,
                        '_urls' => [],
                    ];
                }

                self::appendUrlToBucket($buckets[$sig], $row, $url, $severity);
            }
        }

        return self::finalizeBuckets($buckets, $pageTotal);
    }

    /**
     * Одна и та же form action=http на многих страницах → группа = форма, внутри URL.
     *
     * @param  Collection|iterable  $rows
     * @return array<int, array{hash:string,size:int,label:string,severity:string,urls:array<int,array{url:string,severity:string}>,hint:?string,likely_template:bool,href:string,host:string}>
     */
    private static function groupByInsecureForm($rows): array
    {
        $buckets = [];
        $pageTotal = 0;

        foreach ($rows as $row) {
            $pageTotal++;
            $meta = is_array($row->meta_json ?? null) ? $row->meta_json : [];
            $rawSamples = isset($meta['samples']) && is_array($meta['samples']) ? $meta['samples'] : [];
            $pageUrl = (string) ($row->url ?? '');
            $severity = (string) ($row->severity ?? 'critical');
            $seenOnPage = [];

            if ($rawSamples === []) {
                $sig = 'empty';
                if (! isset($buckets[$sig])) {
                    $buckets[$sig] = [
                        'hash' => $sig,
                        'size' => 0,
                        'label' => 'Форма без деталей в сэмпле',
                        'severity' => $severity,
                        'urls' => [],
                        'finding_ids' => [],
                        'hint' => 'В finding нет samples — смотрите режим «По страницам».',
                        'likely_template' => false,
                        'href' => '',
                        'host' => '',
                        '_urls' => [],
                    ];
                }
                self::appendUrlToBucket($buckets[$sig], $row, $pageUrl, $severity);
                continue;
            }

            foreach ($rawSamples as $sample) {
                $form = self::normalizeInsecureFormSample($sample);
                if ($form === null) {
                    continue;
                }
                $sig = $form['sig'];
                if ($sig === '' || isset($seenOnPage[$sig])) {
                    continue;
                }
                $seenOnPage[$sig] = true;

                if (! isset($buckets[$sig])) {
                    $buckets[$sig] = [
                        'hash' => $sig,
                        'size' => 0,
                        'label' => $form['label'],
                        'severity' => $severity,
                        'urls' => [],
                        'finding_ids' => [],
                        'hint' => 'Одинаковая форма на многих URL — чаще всего общий блок (шапка, подвал, попап). Правьте шаблон один раз.',
                        'likely_template' => false,
                        'href' => $form['action'],
                        'host' => self::hostOf($form['action']),
                        'form_id' => $form['id'],
                        'form_name' => $form['name'],
                        'form_class' => $form['class'],
                        'form_method' => $form['method'],
                        '_urls' => [],
                    ];
                }

                self::appendUrlToBucket($buckets[$sig], $row, $pageUrl, $severity);
            }
        }

        return self::finalizeBuckets($buckets, $pageTotal);
    }

    /**
     * @param  mixed  $sample
     * @return array{sig:string,action:string,id:?string,name:?string,class:?string,method:?string,label:string}|null
     */
    private static function normalizeInsecureFormSample($sample): ?array
    {
        $action = '';
        $id = null;
        $name = null;
        $class = null;
        $method = null;

        if (is_string($sample)) {
            $action = trim($sample);
        } elseif (is_array($sample)) {
            $action = trim((string) ($sample['action'] ?? $sample['url'] ?? ''));
            $id = isset($sample['id']) && trim((string) $sample['id']) !== ''
                ? trim((string) $sample['id']) : null;
            $name = isset($sample['name']) && trim((string) $sample['name']) !== ''
                ? trim((string) $sample['name']) : null;
            $class = isset($sample['class']) && trim((string) $sample['class']) !== ''
                ? trim((string) $sample['class']) : null;
            $method = isset($sample['method']) && trim((string) $sample['method']) !== ''
                ? strtolower(trim((string) $sample['method'])) : null;
        }

        if ($action === '' || stripos($action, 'http://') !== 0) {
            return null;
        }

        $actionNorm = self::normalizeOutboundSignature($action);
        // id / name важнее action: одна подписка в шаблоне на всех страницах.
        if ($id !== null) {
            $sig = 'id:' . mb_strtolower($id) . '|' . $actionNorm;
        } elseif ($name !== null) {
            $sig = 'name:' . mb_strtolower($name) . '|' . $actionNorm;
        } else {
            $sig = 'action:' . $actionNorm
                . ($method ? ('|' . $method) : '')
                . ($class ? ('|class:' . mb_strtolower($class)) : '');
        }

        $bits = [];
        if ($id !== null) {
            $bits[] = 'id=' . self::clipLabel($id, 40);
        }
        if ($name !== null) {
            $bits[] = 'name=' . self::clipLabel($name, 40);
        }
        if ($class !== null) {
            $bits[] = 'class=' . self::clipLabel($class, 48);
        }
        if ($method !== null) {
            $bits[] = strtoupper($method);
        }
        if ($bits === []) {
            $bits[] = 'form action=http';
        }

        return [
            'sig' => $sig,
            'action' => $action,
            'id' => $id,
            'name' => $name,
            'class' => $class,
            'method' => $method,
            'label' => implode(' · ', $bits),
        ];
    }

    /**
     * Группировка исходящих ссылок/ассетов: одна цель → список страниц.
     * Удобно, когда vk/t.me/иконка в шапке повторяется на всём сайте.
     *
     * @param  Collection|iterable  $rows
     * @return array<int, array{hash:string,size:int,label:string,severity:string,urls:array<int,array{url:string,severity:string}>,hint:?string,likely_template:bool,href:string,host:string}>
     */
    private static function groupByOutboundUrl($rows, string $code): array
    {
        $buckets = [];
        $pageTotal = 0;
        $isBrokenTarget = in_array($code, ['page_has_broken_links', 'page_has_broken_external_links'], true);
        $kindNoun = $code === 'external_assets'
            ? 'файл'
            : ($isBrokenTarget ? 'битая цель' : 'ссылка');

        foreach ($rows as $row) {
            $pageTotal++;
            $meta = is_array($row->meta_json ?? null) ? $row->meta_json : [];
            $rawSamples = isset($meta['samples']) && is_array($meta['samples']) ? $meta['samples'] : [];
            $pageUrl = (string) ($row->url ?? '');
            $severity = (string) ($row->severity ?? 'info');
            $seenOnPage = [];

            if ($rawSamples === []) {
                $sig = 'empty';
                if (! isset($buckets[$sig])) {
                    $buckets[$sig] = [
                        'hash' => $sig,
                        'size' => 0,
                        'label' => 'Без списка целей в сэмпле',
                        'severity' => $severity,
                        'urls' => [],
                        'finding_ids' => [],
                        'hint' => 'В finding нет samples — смотрите режим «По страницам».',
                        'likely_template' => false,
                        'href' => '',
                        'host' => '',
                        '_urls' => [],
                    ];
                }
                self::appendUrlToBucket($buckets[$sig], $row, $pageUrl, $severity);
                continue;
            }

            foreach ($rawSamples as $sample) {
                $target = '';
                $kind = '';
                $scope = '';
                $status = null;
                if (is_string($sample)) {
                    $target = trim($sample);
                } elseif (is_array($sample)) {
                    $target = trim((string) ($sample['url'] ?? $sample['href'] ?? $sample['src'] ?? ''));
                    $kind = trim((string) ($sample['kind'] ?? ''));
                    $scope = trim((string) ($sample['scope'] ?? ''));
                    if (isset($sample['status']) && $sample['status'] !== '' && $sample['status'] !== null) {
                        $status = (int) $sample['status'];
                    }
                }
                if ($target === '') {
                    continue;
                }

                $sig = self::normalizeOutboundSignature($target);
                if ($sig === '' || isset($seenOnPage[$sig])) {
                    continue;
                }
                $seenOnPage[$sig] = true;

                if (! isset($buckets[$sig])) {
                    $host = self::hostOf($target);
                    $label = $host !== '' ? ($host . ' · ' . self::clipLabel($target, 100)) : self::clipLabel($target, 120);
                    if ($kind !== '') {
                        $label = '[' . $kind . '] ' . $label;
                    }
                    if (is_array($sample)) {
                        $anchor = trim((string) ($sample['text'] ?? ''));
                        if ($anchor !== '') {
                            $label = '«' . self::clipLabel($anchor, 48) . '» → ' . $label;
                        }
                    }
                    if ($scope === '' && $code === 'external_assets') {
                        $scope = 'external';
                    }
                    if ($scope === '' && in_array($code, ['broken_external_link', 'page_has_broken_external_links'], true)) {
                        $scope = 'external';
                    }
                    if ($scope === '' && $code === 'page_has_broken_links') {
                        $scope = 'internal';
                    }
                    $hint = $isBrokenTarget
                        ? 'Одна и та же битая цель на многих страницах — чаще всего мёртвая ссылка в общем блоке (шапка, подвал, меню).'
                        : ('Одинаковая исходящая ' . $kindNoun
                            . ' на многих URL — чаще всего общий блок (шапка, подвал, сайдбар).');
                    $buckets[$sig] = [
                        'hash' => $sig,
                        'size' => 0,
                        'label' => $label,
                        'severity' => $severity,
                        'urls' => [],
                        'finding_ids' => [],
                        'hint' => $hint,
                        'likely_template' => false,
                        'href' => $target,
                        'host' => $host,
                        'scope' => $scope,
                        'status' => $status,
                        '_urls' => [],
                    ];
                } else {
                    if ($scope !== '' && ($buckets[$sig]['scope'] ?? '') === '') {
                        $buckets[$sig]['scope'] = $scope;
                    }
                    if ($status && empty($buckets[$sig]['status'])) {
                        $buckets[$sig]['status'] = $status;
                    }
                }

                self::appendUrlToBucket($buckets[$sig], $row, $pageUrl, $severity);
            }
        }

        return self::finalizeBuckets($buckets, $pageTotal);
    }

    public static function normalizeOutboundSignature(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        // Схлопываем только очевидный шум: хвостовой слэш и регистр схемы/хоста.
        $parts = @parse_url($url);
        if (! is_array($parts) || empty($parts['host'])) {
            return mb_strtolower(rtrim($url, '/'));
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = strtolower((string) $parts['host']);
        $port = isset($parts['port']) ? (':' . (int) $parts['port']) : '';
        $path = (string) ($parts['path'] ?? '');
        if ($path === '' || $path === '/') {
            $path = '';
        } else {
            $path = rtrim($path, '/');
        }
        $query = isset($parts['query']) && $parts['query'] !== '' ? ('?' . $parts['query']) : '';
        $fragment = isset($parts['fragment']) && $parts['fragment'] !== ''
            ? ('#' . $parts['fragment'])
            : '';

        return $scheme . '://' . $host . $port . $path . $query . $fragment;
    }

    private static function hostOf(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) ? strtolower($host) : '';
    }

    /**
     * Нормализация текста ошибки: без строки, без лишних пробелов.
     */
    public static function normalizeHtmlSignature(string $message): string
    {
        $m = mb_strtolower(trim($message));
        if ($m === '') {
            return '';
        }

        $m = preg_replace('/\s+/u', ' ', $m) ?: $m;
        // libxml иногда тащит " in Entity", номера строк в тексте
        $m = preg_replace('/\s+in entity$/u', '', $m) ?: $m;
        $m = preg_replace('/\b(line|стр\.?|строка)\s*\d+\b/u', '', $m) ?: $m;
        $m = preg_replace('/\s+/u', ' ', $m) ?: $m;

        return trim($m);
    }

    /**
     * Подсказка «сквозной шаблон», если один паттерн доминирует.
     *
     * @param  array<int, array{size:int,label:string,likely_template?:bool}>  $groups
     * @return array{pages:int,total:int,label:string,pct:int}|null
     */
    public static function sitewideSummary(array $groups, int $pageTotal): ?array
    {
        if ($groups === [] || $pageTotal < 3) {
            return null;
        }

        $top = $groups[0];
        $size = (int) ($top['size'] ?? 0);
        if ($size < 3) {
            return null;
        }
        if (! self::isLikelyTemplate($size, $pageTotal)) {
            return null;
        }

        $pct = (int) round(100 * $size / max(1, $pageTotal));

        return [
            'pages' => $size,
            'total' => $pageTotal,
            'label' => (string) ($top['label'] ?? ''),
            'pct' => $pct,
        ];
    }

    /**
     * @param  array<string,mixed>  $bucket
     * @param  object|array  $row
     */
    private static function appendUrlToBucket(array &$bucket, $row, string $url, string $severity): void
    {
        if ($url === '' || isset($bucket['_urls'][$url])) {
            return;
        }
        $bucket['_urls'][$url] = true;
        $fid = (int) (is_object($row) ? ($row->id ?? 0) : ($row['id'] ?? 0));
        $entry = [
            'url' => $url,
            'severity' => $severity,
        ];
        if ($fid > 0) {
            $entry['id'] = $fid;
            if (! isset($bucket['finding_ids']) || ! is_array($bucket['finding_ids'])) {
                $bucket['finding_ids'] = [];
            }
            $bucket['finding_ids'][$fid] = $fid;
        }
        $bucket['urls'][] = $entry;
        $bucket['size'] = count($bucket['urls']);
    }

    /**
     * @param  array<string,array<string,mixed>>  $buckets
     * @return list<array<string,mixed>>
     */
    private static function finalizeBuckets(array $buckets, int $pageTotal, bool $computeTemplate = true): array
    {
        $groups = [];
        foreach ($buckets as $bucket) {
            unset($bucket['_urls']);
            $bucket['finding_ids'] = array_values($bucket['finding_ids'] ?? []);
            if ($computeTemplate) {
                $bucket['likely_template'] = self::isLikelyTemplate((int) ($bucket['size'] ?? 0), $pageTotal);
            }
            $groups[] = $bucket;
        }

        return self::sortGroups($groups);
    }

    private static function isLikelyTemplate(int $size, int $pageTotal): bool
    {
        if ($size < 5) {
            return false;
        }
        if ($pageTotal <= 0) {
            return $size >= 10;
        }

        return ($size / $pageTotal) >= 0.4;
    }

    /**
     * @param  array<int, array{size:int,label:string}>  $groups
     * @return array<int, array{size:int,label:string}>
     */
    private static function sortGroups(array $groups): array
    {
        usort($groups, static function (array $a, array $b) {
            if ($a['size'] === $b['size']) {
                return strcmp($a['label'], $b['label']);
            }

            return $b['size'] <=> $a['size'];
        });

        return $groups;
    }

    private static function labelFor(string $code, array $meta): string
    {
        if ($code === 'text_in_noindex') {
            $sample = trim((string) ($meta['sample'] ?? ''));
            $links = isset($meta['links']) && is_array($meta['links']) ? $meta['links'] : [];
            $hosts = [];
            foreach ($links as $l) {
                if (! is_array($l)) {
                    continue;
                }
                $host = parse_url((string) ($l['href'] ?? ''), PHP_URL_HOST);
                if (is_string($host) && $host !== '') {
                    $hosts[] = $host;
                }
            }
            $hosts = array_values(array_unique($hosts));
            if ($sample !== '' && $hosts !== []) {
                return '«' . self::clipLabel($sample, 40) . '» · ' . implode(', ', array_slice($hosts, 0, 3));
            }
            if ($sample !== '') {
                return '«' . self::clipLabel($sample, 80) . '»';
            }
            if ($hosts !== []) {
                return 'ссылки: ' . implode(', ', array_slice($hosts, 0, 4));
            }

            return 'блок noindex';
        }
        if ($code === 'duplicate_content') {
            if (! empty($meta['label'])) {
                return 'Текст ≈ «' . self::clipLabel((string) $meta['label'], 100) . '»';
            }
            if (! empty($meta['title'])) {
                return 'Текст ≈ title «' . self::clipLabel((string) $meta['title'], 100) . '»';
            }

            return 'Одинаковый текст страниц';
        }
        if ($code === 'duplicate_description' && ! empty($meta['description'])) {
            return (string) $meta['description'];
        }
        if (! empty($meta['title'])) {
            return (string) $meta['title'];
        }
        if (! empty($meta['description'])) {
            return (string) $meta['description'];
        }

        return 'Совпадение без текста';
    }

    private static function clipLabel(string $text, int $max): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?: $text);
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $max - 1)) . '…';
    }
}
