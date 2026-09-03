<?php

namespace App\Services\SiteAudit;

use App\SiteAuditCrawl;
use App\SiteAuditFinding;
use App\SiteAuditPage;
use Illuminate\Support\Facades\Cache;

/**
 * Каннибализация lite: запрос из monitoring встречается в title/h1
 * у страниц, отличных от назначенной посадочной.
 *
 * На больших краулах — инвертированный индекс токенов + нарезка по дедлайну тика
 * (иначе keywords × pages = сотни миллионов итераций в одном job).
 */
class SiteAuditCannibalizationProbe
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
                ->where('code', 'keyword_cannibalization')
                ->delete();
            $emitted = 0;
            Cache::forget($this->indexCacheKey((int) $crawl->id));
        }

        $max = (int) config('site_audit.cannibalization_max', 200);
        if ($emitted >= $max || $offset >= count($kwList)) {
            Cache::forget($this->indexCacheKey((int) $crawl->id));
            $meta = [];

            return false;
        }

        $pack = $this->pageIndex($crawl, $deadline);
        if ($pack === null) {
            // Индекс ещё строится — продолжим на следующем тике с того же offset.
            $meta = [
                'kw_offset' => $offset,
                'emitted' => $emitted,
                'building_index' => true,
            ];

            return true;
        }
        if (($pack['pages'] ?? []) === [] || count($pack['pages']) < 2) {
            Cache::forget($this->indexCacheKey((int) $crawl->id));
            $meta = [];

            return false;
        }

        /** @var array<int, array{url:string,url_hash:string,title:?string,hay:string}> $pageRows */
        $pageRows = $pack['pages'];
        /** @var array<string, true> $byHash */
        $byHash = $pack['by_hash'];
        /** @var array<string, int[]> $inv */
        $inv = $pack['inv'];

        $minTokenLen = max(3, (int) config('site_audit.cannibalization_min_token', 4));
        $minHits = max(1, (int) config('site_audit.cannibalization_min_hits', 2));
        $cfg = config('site_audit.findings.keyword_cannibalization', []);
        $severity = $cfg['severity'] ?? 'warning';
        $seen = [];
        $safety = 5.0;

        for ($i = $offset; $i < count($kwList); $i++) {
            if ($emitted >= $max) {
                Cache::forget($this->indexCacheKey((int) $crawl->id));
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

            $tokens = self::tokens($query, $minTokenLen);
            if ($tokens === []) {
                continue;
            }
            $queryNorm = mb_strtolower($query);

            $candidateIdx = [];
            foreach ($tokens as $tok) {
                if (! isset($inv[$tok])) {
                    continue;
                }
                foreach ($inv[$tok] as $pi) {
                    $candidateIdx[$pi] = true;
                }
            }
            if ($candidateIdx === []) {
                continue;
            }

            foreach (array_keys($candidateIdx) as $pi) {
                if ($emitted >= $max) {
                    Cache::forget($this->indexCacheKey((int) $crawl->id));
                    $meta = [];

                    return false;
                }
                $page = $pageRows[$pi];
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
                    'code' => 'keyword_cannibalization',
                    'severity' => $severity,
                    'url' => $page['url'],
                    'url_hash' => $page['url_hash'],
                    'meta_json' => [
                        'query' => $query,
                        'monitoring_keyword_id' => $kid,
                        'landing_url' => $landingUrl,
                        'hits' => $hits,
                        'full_match' => $fullMatch,
                        'competitor_title' => $page['title'],
                    ],
                ]);
                $emitted++;
            }
        }

        Cache::forget($this->indexCacheKey((int) $crawl->id));
        $meta = [];

        return false;
    }

    /**
     * Страницы + инвертированный индекс токенов title/h1 (кэш между тиками).
     *
     * @return array{pages: array<int, array{url:string,url_hash:string,title:?string,hay:string}>, by_hash: array<string, true>, inv: array<string, int[]>}|null
     */
    private function pageIndex(SiteAuditCrawl $crawl, ?float $deadline): ?array
    {
        $cacheKey = $this->indexCacheKey((int) $crawl->id);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)
            && isset($cached['pages'], $cached['by_hash'], $cached['inv'])
            && empty($cached['_next_id'])
        ) {
            return $cached;
        }

        $minTokenLen = max(3, (int) config('site_audit.cannibalization_min_token', 4));
        $safety = 8.0;
        $pages = [];
        $byHash = [];
        $inv = [];

        $query = SiteAuditPage::query()
            ->where('crawl_id', $crawl->id)
            ->where(function ($q) {
                $q->whereNull('status_code')
                    ->orWhere(function ($q2) {
                        $q2->where('status_code', '>=', 200)->where('status_code', '<', 400);
                    });
            })
            ->orderBy('id')
            ->select(['id', 'url', 'url_hash', 'title', 'h1']);

        $builtFrom = (int) (is_array($cached) ? ($cached['_next_id'] ?? 0) : 0);
        if ($builtFrom > 0 && is_array($cached)) {
            $pages = is_array($cached['pages'] ?? null) ? $cached['pages'] : [];
            $byHash = is_array($cached['by_hash'] ?? null) ? $cached['by_hash'] : [];
            $inv = is_array($cached['inv'] ?? null) ? $cached['inv'] : [];
            $query->where('id', '>', $builtFrom);
        }

        $lastId = $builtFrom;
        $done = true;
        $query->chunkById(500, function ($chunk) use (
            &$pages,
            &$byHash,
            &$inv,
            &$lastId,
            &$done,
            $minTokenLen,
            $deadline,
            $safety
        ) {
            if ($deadline !== null && microtime(true) >= ($deadline - $safety)) {
                $done = false;

                return false;
            }
            foreach ($chunk as $page) {
                $lastId = (int) $page->id;
                $hay = mb_strtolower(trim((string) $page->title . ' ' . (string) $page->h1));
                if ($hay === '') {
                    continue;
                }
                $idx = count($pages);
                $hash = (string) $page->url_hash;
                $pages[] = [
                    'url' => (string) $page->url,
                    'url_hash' => $hash,
                    'title' => $page->title,
                    'hay' => $hay,
                ];
                $byHash[$hash] = true;
                foreach (self::tokens($hay, $minTokenLen) as $tok) {
                    $inv[$tok][] = $idx;
                }
            }

            return true;
        });

        $pack = [
            'pages' => $pages,
            'by_hash' => $byHash,
            'inv' => $inv,
        ];
        if (! $done) {
            $pack['_next_id'] = $lastId;
            Cache::put($cacheKey, $pack, now()->addHours(6));

            return null;
        }

        unset($pack['_next_id']);
        Cache::put($cacheKey, $pack, now()->addHours(6));

        return $pack;
    }

    private function indexCacheKey(int $crawlId): string
    {
        return 'site_audit_cannibal_idx_' . $crawlId;
    }

    /**
     * @return string[]
     */
    public static function tokens(string $query, int $minLen): array
    {
        $q = mb_strtolower($query);
        if (! preg_match_all('/[\p{L}\p{N}]{' . $minLen . ',}/u', $q, $m)) {
            return [];
        }
        $stop = [
            'для', 'или', 'как', 'это', 'the', 'and', 'with', 'from', 'http', 'https',
            'купить', 'цена', 'цены', // слишком общие коммерческие — оставляем в query full_match
        ];
        $out = [];
        foreach ($m[0] as $tok) {
            if (in_array($tok, $stop, true)) {
                continue;
            }
            $out[$tok] = true;
        }

        return array_keys($out);
    }
}
