<?php

namespace App\Services\SiteAudit;

use App\SiteAuditCrawl;
use App\SiteAuditFinding;
use App\SiteAuditPage;

/**
 * Обратный индекс: целевой URL → страницы проверки, у которых он в out_links.
 * Плюс происхождение URL из sitemap (проверка ходит не только по ссылкам).
 */
class SiteAuditLinkReferrers
{
    /** Сколько «откуда» держим на цель в UI-отчёте. */
    private const MAX_REFS_PER_TARGET = 12;

    /**
     * Сколько страниц-кандидатов тянем одним SQL (LIKE по out_links) на страницу отчёта.
     */
    private const TARGETED_SQL_LIMIT = 600;

    /**
     * @param string[]|null $targetUrls если задано — только эти цели (SQL LIKE, без полной выгрузки проверки)
     * @return array<string, list<string>> targetUrl => [referrerUrl, ...]
     */
    public static function forCrawl(int $crawlId, ?array $targetUrls = null): array
    {
        // outUrl / slash-вариант → канонический target из запроса отчёта
        $lookup = null;
        $pending = null;
        if ($targetUrls !== null) {
            $lookup = [];
            $pending = [];
            foreach ($targetUrls as $u) {
                $u = trim((string) $u);
                if ($u === '') {
                    continue;
                }
                $lookup[$u] = $u;
                $pending[$u] = true;
                $alt = self::slashVariant($u);
                if ($alt !== null) {
                    $lookup[$alt] = $u;
                }
            }
            if ($pending === []) {
                return [];
            }
        }

        $map = [];
        $maxRefs = self::MAX_REFS_PER_TARGET;

        $addRef = static function (string $to, string $from) use (&$map, &$pending, $maxRefs): void {
            if ($to === '' || $from === '' || $from === $to) {
                return;
            }
            if (! isset($map[$to])) {
                $map[$to] = [];
            }
            if (count($map[$to]) >= $maxRefs) {
                return;
            }
            if (! in_array($from, $map[$to], true)) {
                $map[$to][] = $from;
            }
            if ($pending !== null && isset($map[$to][0])) {
                unset($pending[$to]);
            }
        };

        $pagesFetched = (int) (SiteAuditCrawl::query()->whereKey($crawlId)->value('pages_fetched') ?? 0);

        if ($lookup !== null && $pagesFetched > 0 && $pagesFetched <= 12000) {
            // Точечный поиск на умеренных проверких: SQL LIKE по URL, без полной выгрузки.
            // На 40k+ это слишком тяжело, а out_links всё равно часто обрезаны (лимит store_max).
            // С 2026-10: лимит 2500 + приоритет того же раздела пути (см. selectOutLinksForStorage).
            $needles = array_keys($lookup);
            $query = SiteAuditPage::query()
                ->where('crawl_id', $crawlId)
                ->whereNotNull('out_links_json')
                ->where(function ($w) use ($needles) {
                    foreach ($needles as $needle) {
                        $w->orWhere('out_links_json', 'like', '%' . self::escapeLike($needle) . '%');
                    }
                })
                ->orderBy('id')
                ->limit(self::TARGETED_SQL_LIMIT)
                ->get(['url', 'out_links_json']);

            foreach ($query as $page) {
                $from = (string) $page->url;
                $outs = is_array($page->out_links_json) ? $page->out_links_json : [];
                foreach ($outs as $out) {
                    $out = (string) $out;
                    if ($out === '' || (strlen($out) === 64 && ctype_xdigit($out))) {
                        continue;
                    }
                    if (! isset($lookup[$out])) {
                        continue;
                    }
                    $addRef($lookup[$out], $from);
                }
                if ($pending === []) {
                    break;
                }
            }
        } elseif ($lookup === null) {
            // Полный индекс (редко): только чанками, без одного гигантского get().
            SiteAuditPage::query()
                ->where('crawl_id', $crawlId)
                ->whereNotNull('out_links_json')
                ->orderBy('id')
                ->select(['id', 'url', 'out_links_json'])
                ->chunkById(250, function ($pages) use ($addRef) {
                    foreach ($pages as $page) {
                        $from = (string) $page->url;
                        $outs = is_array($page->out_links_json) ? $page->out_links_json : [];
                        foreach ($outs as $out) {
                            $out = (string) $out;
                            if ($out === '' || (strlen($out) === 64 && ctype_xdigit($out))) {
                                continue;
                            }
                            $addRef($out, $from);
                        }
                    }

                    return true;
                });
        }
        // else: крупный проверка + точечные цели — referrer'ы из out_links не сканируем
        // (память/таймаут). Источник покажет originMeta (sitemap seed и т.п.).

        // Дополняем из findings «битая внутренняя ссылка» (meta.from).
        $q = SiteAuditFinding::query()
            ->where('crawl_id', $crawlId)
            ->where('code', 'broken_internal_link');
        if ($lookup !== null) {
            $q->whereIn('url', array_values(array_unique(array_values($lookup))));
        }
        foreach ($q->get(['url', 'meta_json']) as $row) {
            $to = (string) $row->url;
            if ($lookup !== null) {
                $to = $lookup[$to] ?? $to;
            }
            $meta = is_array($row->meta_json) ? $row->meta_json : [];
            $from = trim((string) ($meta['from'] ?? ''));
            $addRef($to, $from);
        }

        return $map;
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * URL из sitemap текущей проверки (с учётом trailing slash / canonical key).
     *
     * @param string[] $targetUrls
     * @return array<string, bool> targetUrl => true если есть в sitemap
     */
    public static function inSitemapFlags(SiteAuditCrawl $crawl, array $targetUrls): array
    {
        $flags = [];
        foreach ($targetUrls as $u) {
            $u = trim((string) $u);
            if ($u !== '') {
                $flags[$u] = false;
            }
        }
        if ($flags === []) {
            return [];
        }

        $sitemapUrls = SiteAuditSitemapProbe::urlsFromProgress($crawl);
        if ($sitemapUrls === []) {
            return $flags;
        }

        $byExact = [];
        $byKey = [];
        foreach ($sitemapUrls as $su) {
            $su = (string) $su;
            $byExact[$su] = true;
            $key = SiteAuditUrlNormalizer::canonicalKey($su);
            if ($key) {
                $byKey[$key] = true;
            }
            // sitemap часто со слэшем, проверка — без (strip_trailing_slash)
            $alt = self::slashVariant($su);
            if ($alt !== null) {
                $byExact[$alt] = true;
            }
        }

        foreach ($flags as $url => $_) {
            if (self::urlMatchesSitemapSets($url, $byExact, $byKey)) {
                $flags[$url] = true;
            }
        }

        return $flags;
    }

    /**
     * @param array<string, true> $byExact
     * @param array<string, true> $byKey
     */
    private static function urlMatchesSitemapSets(string $url, array $byExact, array $byKey): bool
    {
        $candidates = [$url];
        $alt = self::slashVariant($url);
        if ($alt !== null) {
            $candidates[] = $alt;
        }
        // http ↔ https
        if (stripos($url, 'https://') === 0) {
            $candidates[] = 'http://' . substr($url, 8);
            if ($alt !== null) {
                $candidates[] = 'http://' . substr($alt, 8);
            }
        } elseif (stripos($url, 'http://') === 0) {
            $candidates[] = 'https://' . substr($url, 7);
            if ($alt !== null) {
                $candidates[] = 'https://' . substr($alt, 7);
            }
        }
        foreach ($candidates as $c) {
            if (isset($byExact[$c])) {
                return true;
            }
            $key = SiteAuditUrlNormalizer::canonicalKey($c);
            if ($key && isset($byKey[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Источник постановки URL в очередь с страниц проверки.
     *
     * @param string[] $targetUrls
     * @return array<string, array{via:string,from:?string}>
     */
    public static function pageDiscoveryMap(int $crawlId, array $targetUrls): array
    {
        $urls = [];
        foreach ($targetUrls as $u) {
            $u = trim((string) $u);
            if ($u !== '') {
                $urls[$u] = true;
            }
        }
        if ($urls === []) {
            return [];
        }
        try {
            if (! \Illuminate\Support\Facades\Schema::hasColumn('site_audit_pages', 'discovered_via')) {
                return [];
            }
        } catch (\Throwable $e) {
            return [];
        }

        $out = [];
        $rows = SiteAuditPage::query()
            ->where('crawl_id', $crawlId)
            ->whereIn('url', array_keys($urls))
            ->get(['url', 'discovered_via', 'discovered_from']);
        foreach ($rows as $row) {
            $via = trim((string) ($row->discovered_via ?? ''));
            if ($via === '') {
                continue;
            }
            $from = trim((string) ($row->discovered_from ?? ''));
            $out[(string) $row->url] = [
                'via' => $via,
                'from' => $from !== '' ? $from : null,
            ];
        }

        return $out;
    }

    /**
     * Короткая подпись источника без советов «что делать».
     *
     * @return array{label:string,via:string,from:?string}
     */
    public static function formatDiscoverySource(string $via, ?string $from = null): array
    {
        $via = trim($via);
        $from = $from !== null ? trim($from) : '';
        if ($via === 'sitemap') {
            $href = ($from !== '' && preg_match('#^https?://#i', $from)) ? $from : null;

            return ['label' => 'sitemap.xml', 'via' => $via, 'from' => $href];
        }
        if ($via === 'seed') {
            return ['label' => 'посев (список URL)', 'via' => $via, 'from' => null];
        }
        if ($via === 'home') {
            return ['label' => 'главная', 'via' => $via, 'from' => null];
        }
        if ($via === 'link' && $from !== '') {
            return ['label' => $from, 'via' => $via, 'from' => $from];
        }
        if ($via === 'link') {
            return ['label' => 'внутренняя ссылка', 'via' => $via, 'from' => null];
        }

        return ['label' => '', 'via' => '', 'from' => null];
    }

    /**
     * Откуда URL попал в проверка, если HTML-referrer'ов нет (старые проверки без discovered_*).
     *
     * @param string[] $targetUrls
     * @return array<string, array{label:string,hint:string,from_sitemap:bool,from_seed:bool,from_home:bool,orphan:bool}>
     */
    public static function originMeta(SiteAuditCrawl $crawl, array $targetUrls): array
    {
        $out = [];
        foreach ($targetUrls as $u) {
            $u = trim((string) $u);
            if ($u === '') {
                continue;
            }
            $out[$u] = [
                'label' => '',
                'hint' => '',
                'from_sitemap' => false,
                'from_seed' => false,
                'from_home' => false,
                'orphan' => false,
            ];
        }
        if ($out === []) {
            return [];
        }

        $inSitemap = self::inSitemapFlags($crawl, array_keys($out));
        $project = $crawl->project;
        $domain = $project ? (string) $project->domain : '';
        $settings = is_array($crawl->progress_json['settings'] ?? null)
            ? $crawl->progress_json['settings']
            : [];
        $urlOpts = SiteAuditUrlNormalizer::optionsFromSettings($settings, $domain);

        $seedSet = [];
        if ($project) {
            $manual = $project->setting('seed_urls', []);
            if (is_array($manual)) {
                foreach ($manual as $su) {
                    $norm = SiteAuditUrlNormalizer::normalize((string) $su, $domain, $urlOpts);
                    if ($norm) {
                        $seedSet[$norm] = true;
                        $seedSet[(string) $su] = true;
                    }
                }
            }
        }

        $home = $domain !== ''
            ? (SiteAuditUrlNormalizer::normalize('https://' . $domain . '/', $domain, $urlOpts) ?: null)
            : null;
        $homeKey = $home ? SiteAuditUrlNormalizer::canonicalKey($home) : null;

        $pagesOnly = ! empty($crawl->progress_json['pages_only'])
            || ! empty($settings['pages_only']);

        $depths = SiteAuditPage::query()
            ->where('crawl_id', (int) $crawl->id)
            ->whereIn('url', array_keys($out))
            ->pluck('click_depth', 'url');

        $sitemapMeta = is_array($crawl->progress_json['sitemap'] ?? null)
            ? $crawl->progress_json['sitemap']
            : [];
        $sitemapSeedCount = (int) ($sitemapMeta['seed_count'] ?? $sitemapMeta['url_count'] ?? 0);
        $sitemapSeeded = ! empty($sitemapMeta['found'])
            || $sitemapSeedCount > 0
            || ! empty($sitemapMeta['urls_gz_file'])
            || ! empty($sitemapMeta['urls_gz']);
        $sitemapListLoaded = SiteAuditSitemapProbe::urlsFromProgress($crawl) !== [];

        foreach ($out as $url => $_) {
            $fromSitemap = ! empty($inSitemap[$url]);
            // Список sitemap стёрт с диска, но посев из карты был большим — типичный источник.
            $bulkSitemap = $sitemapSeeded && $sitemapSeedCount >= 100 && ! $sitemapListLoaded;
            $norm = $domain !== ''
                ? (SiteAuditUrlNormalizer::normalize($url, $domain, $urlOpts) ?: $url)
                : $url;
            $fromSeed = isset($seedSet[$url]) || isset($seedSet[$norm]);
            $key = SiteAuditUrlNormalizer::canonicalKey($url);
            $fromHome = $home !== null && (
                $url === $home
                || $norm === $home
                || ($homeKey && $key && $key === $homeKey)
            );
            $depth = $depths[$url] ?? null;
            $orphan = $depth === null;
            $slashAlt = self::slashVariant($url);
            $slashTip = $slashAlt
                ? (' В меню часто уже правильный адрес: ' . $slashAlt . ' — в sitemap/старых ссылках уберите вариант без нужного слэша.')
                : '';

            // Короткие тексты для SEO-шника: откуда взять и что править. Без «out_links»/«глубины».
            if ($fromSitemap || $bulkSitemap) {
                $label = 'из sitemap.xml';
                $hint = $fromSitemap
                    ? ('Этот URL есть в карте сайта. Исправьте запись в sitemap на финальный адрес без редиректа.' . $slashTip)
                    : ('В проверке подхватили '
                        . number_format($sitemapSeedCount, 0, '', ' ')
                        . ' URL из sitemap — таких адресов нет среди сохранённых HTML-ссылок меню. Проверьте sitemap.xml и старые ссылки на этот URL.'
                        . $slashTip);
            } elseif ($pagesOnly && $fromSeed) {
                $label = 'из вашего списка URL';
                $hint = 'URL задали вручную при запуске. Уберите или замените на финальный адрес в списке посева.';
            } elseif ($fromSeed) {
                $label = 'из посева (ваш список)';
                $hint = 'URL добавили в seed при запуске. Замените на финальный адрес без редиректа.';
            } elseif ($fromHome) {
                $label = 'главная сайта';
                $hint = 'Стартовый URL проекта. Настройте редирект/canonical на канонический адрес главной.';
            } elseif ($sitemapSeeded) {
                // HTML-ссылку не нашли, но sitemap в проверке был — не вводим «по внутренним ссылкам».
                $label = 'скорее всего sitemap.xml';
                $hint = 'Среди сохранённых ссылок со страниц этого URL нет. Обычно такие адреса приходят из карты сайта или закладок. Проверьте sitemap и поиск по сайту на этот URL.'
                    . $slashTip;
            } else {
                $label = 'страницу со ссылкой не нашли';
                $hint = 'В сохранённых HTML-ссылках проверки этого URL нет. Ищите в sitemap.xml, во внешних ссылках и через поиск по коду/сайту.'
                    . $slashTip;
            }

            $out[$url] = [
                'label' => $label,
                'hint' => $hint,
                'from_sitemap' => $fromSitemap || $bulkSitemap || ($sitemapSeeded && $orphan),
                'from_seed' => $fromSeed,
                'from_home' => $fromHome,
                'orphan' => $orphan,
                'fix' => $hint,
            ];
        }

        return $out;
    }

    private static function slashVariant(string $url): ?string
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            return null;
        }
        $path = $parts['path'] ?? '/';
        if ($path === '/' || $path === '') {
            return null;
        }
        if (substr($path, -1) === '/') {
            $path = rtrim($path, '/');
        } else {
            $path .= '/';
        }
        $scheme = $parts['scheme'] ?? 'https';
        $out = $scheme . '://' . $parts['host'] . $path;
        if (! empty($parts['query'])) {
            $out .= '?' . $parts['query'];
        }

        return $out;
    }

    /** Публичная обёртка для слэш-варианта (отчёт редиректов /about ↔ /about/). */
    public static function slashVariantPublic(string $url): ?string
    {
        return self::slashVariant($url);
    }

    /**
     * URL карты сайта для клика в колонке «Откуда» (из источников проверки или /sitemap.xml домена).
     */
    public static function sitemapViewUrl(SiteAuditCrawl $crawl): ?string
    {
        $sources = is_array($crawl->progress_json['sitemap']['sources'] ?? null)
            ? $crawl->progress_json['sitemap']['sources']
            : [];
        $fallback = null;
        foreach ($sources as $src) {
            if (! is_array($src)) {
                continue;
            }
            $url = trim((string) ($src['url'] ?? ''));
            if ($url === '' || ! preg_match('#^https?://#i', $url)) {
                continue;
            }
            if (! empty($src['ok'])) {
                return $url;
            }
            if ($fallback === null) {
                $fallback = $url;
            }
        }
        if ($fallback !== null) {
            return $fallback;
        }

        $domain = trim((string) optional($crawl->project)->domain);
        if ($domain === '') {
            return null;
        }

        return 'https://' . $domain . '/sitemap.xml';
    }

    /**
     * Коды отчётов, где нужна колонка «Откуда»: кто ссылается / sitemap / посев.
     *
     * @return string[]
     */
    public static function targetCodes(): array
    {
        return [
            'http_4xx',
            'http_5xx',
            'unreachable',
            'broken_internal_link',
            'broken_external_link',
            'redirect',
            'redirect_chain_long',
            'redirect_loop',
        ];
    }
}
