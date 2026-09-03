<?php

namespace App\Services\SiteAudit;

use App\SiteAuditCrawl;
use App\SiteAuditFinding;
use App\SiteAuditPage;

/**
 * Каннибализация рекламой (lite, без Direct API):
 * запрос из monitoring в title/h1 у promo/PPC-подобной страницы,
 * отличной от назначенной SEO-посадочной.
 *
 * Полный контур (Директ / объявления в SERP) — позже.
 *
 * Крупные краулы (десятки/сотни тысяч URL × тысячи запросов) режутся по дедлайну тика.
 */
class SiteAuditAdCannibalizationProbe
{
    /**
     * @param  array<string, mixed>  $meta
     * @return bool true — нужен ещё тик на том же stage
     */
    public function run(SiteAuditCrawl $crawl, ?float $deadline = null, array &$meta = []): bool
    {
        $resolved = (new SiteAuditLandingResolver())->forCrawl($crawl);
        $byKeyword = is_array($resolved['by_keyword'] ?? null) ? $resolved['by_keyword'] : [];
        if ($byKeyword === []) {
            $meta = [];

            return false;
        }

        $kwList = [];
        foreach ($byKeyword as $kid => $info) {
            $kwList[] = ['id' => (int) $kid, 'info' => $info];
        }

        $offset = max(0, (int) ($meta['kw_offset'] ?? 0));
        $emitted = max(0, (int) ($meta['emitted'] ?? 0));
        if ($offset === 0) {
            SiteAuditFinding::query()
                ->where('crawl_id', $crawl->id)
                ->where('code', 'ad_cannibalization')
                ->delete();
            $emitted = 0;
        }

        $max = (int) config('site_audit.ad_cannibalization_max', 200);
        if ($emitted >= $max || $offset >= count($kwList)) {
            $meta = [];

            return false;
        }

        $pages = SiteAuditPage::query()
            ->where('crawl_id', $crawl->id)
            ->where(function ($q) {
                $q->whereNull('status_code')
                    ->orWhere(function ($q2) {
                        $q2->where('status_code', '>=', 200)->where('status_code', '<', 400);
                    });
            })
            ->get(['url', 'url_hash', 'title', 'h1', 'word_count']);

        if ($pages->count() < 2) {
            $meta = [];

            return false;
        }

        $byHash = [];
        foreach ($pages as $page) {
            $byHash[$page->url_hash] = true;
        }

        $thinWords = max(20, (int) config('site_audit.ad_cannibalization_thin_words',
            (int) config('site_audit.thin_words', 150)));
        // Сначала узкий набор promo-страниц — не гоняем keywords × все 100k URL.
        $adPages = [];
        foreach ($pages as $page) {
            $hint = self::adStyleHint($page, $thinWords);
            if ($hint === null) {
                continue;
            }
            $hay = mb_strtolower(trim((string) $page->title . ' ' . (string) $page->h1));
            if ($hay === '') {
                continue;
            }
            $adPages[] = [
                'url' => (string) $page->url,
                'url_hash' => (string) $page->url_hash,
                'title' => $page->title,
                'word_count' => (int) ($page->word_count ?? 0),
                'hay' => $hay,
                'hint' => $hint,
            ];
        }
        unset($pages);

        if ($adPages === []) {
            $meta = [];

            return false;
        }

        $minTokenLen = max(3, (int) config('site_audit.cannibalization_min_token', 4));
        $minHits = max(1, (int) config('site_audit.cannibalization_min_hits', 2));
        $cfg = config('site_audit.findings.ad_cannibalization', []);
        $severity = $cfg['severity'] ?? 'warning';
        $seen = [];
        $safety = 5.0;

        for ($i = $offset; $i < count($kwList); $i++) {
            if ($emitted >= $max) {
                $meta = [];

                return false;
            }
            if ($deadline !== null && microtime(true) >= ($deadline - $safety)) {
                $meta = [
                    'kw_offset' => $i,
                    'emitted' => $emitted,
                ];

                return true;
            }

            $kid = $kwList[$i]['id'];
            $info = $kwList[$i]['info'];
            $landingUrl = (string) ($info['url'] ?? '');
            $query = trim((string) ($info['query'] ?? ''));
            if ($landingUrl === '' || mb_strlen($query) < 4) {
                continue;
            }
            $landingHash = SiteAuditUrlNormalizer::hash($landingUrl);
            if (! isset($byHash[$landingHash])) {
                continue;
            }

            $tokens = SiteAuditCannibalizationProbe::tokens($query, $minTokenLen);
            if ($tokens === []) {
                continue;
            }
            $queryNorm = mb_strtolower($query);

            foreach ($adPages as $page) {
                if ($emitted >= $max) {
                    $meta = [];

                    return false;
                }
                if ($page['url_hash'] === $landingHash) {
                    continue;
                }

                $hay = $page['hay'];
                $hits = 0;
                foreach ($tokens as $tok) {
                    if (mb_strpos($hay, $tok) !== false) {
                        $hits++;
                    }
                }
                $fullMatch = mb_strpos($hay, $queryNorm) !== false;
                if (! $fullMatch && $hits < $minHits) {
                    continue;
                }

                $key = $landingHash . '|' . $page['url_hash'] . '|' . md5($queryNorm);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                SiteAuditFinding::query()->create([
                    'crawl_id' => $crawl->id,
                    'code' => 'ad_cannibalization',
                    'severity' => $severity,
                    'url' => $page['url'],
                    'url_hash' => $page['url_hash'],
                    'meta_json' => [
                        'query' => $query,
                        'monitoring_keyword_id' => $kid,
                        'landing_url' => $landingUrl,
                        'hits' => $hits,
                        'full_match' => $fullMatch,
                        'ad_hint' => $page['hint'],
                        'competitor_title' => $page['title'],
                        'word_count' => $page['word_count'],
                    ],
                ]);
                $emitted++;
            }
        }

        $meta = [];

        return false;
    }

    /**
     * Эвристика promo/PPC-посадочной без данных Директа.
     *
     * @return string|null причина или null
     */
    public static function adStyleHint(SiteAuditPage $page, int $thinWords): ?string
    {
        $path = (string) (parse_url((string) $page->url, PHP_URL_PATH) ?: '/');
        $pathLower = mb_strtolower($path);

        if (preg_match(
            '#/(?:lp|landing|promo|offer|offers|sale|actions?|akci[iy]|cpc|ppc|adv|reklam|go|utm)(/|$)#u',
            $pathLower
        )) {
            return 'path_promo';
        }
        if (preg_match('#(?:^|/)(?:lp|promo|offer|cpc|ppc)[-_]#u', $pathLower)) {
            return 'path_promo_prefix';
        }

        $hay = mb_strtolower(trim((string) $page->title . ' ' . (string) $page->h1));
        $cta = 0;
        foreach (['заказать', 'купить', 'скидк', 'акци', 'заявк', 'оставить заявку', 'цена от', 'order now', 'buy now', 'sale'] as $w) {
            if ($hay !== '' && mb_strpos($hay, $w) !== false) {
                $cta++;
            }
        }

        $words = (int) ($page->word_count ?? 0);
        if ($words > 0 && $words <= $thinWords && $cta >= 1) {
            return 'thin_cta';
        }
        if ($cta >= 2 && $words > 0 && $words <= (int) ($thinWords * 1.5)) {
            return 'cta_heavy';
        }

        return null;
    }
}
