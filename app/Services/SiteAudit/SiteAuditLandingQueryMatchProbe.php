<?php

namespace App\Services\SiteAudit;

use App\SiteAuditCrawl;
use App\SiteAuditFinding;
use App\SiteAuditPage;
use App\Support\Esenin\EseninMorphology;

/**
 * Соответствие запроса посадочной (lite):
 * exact-фраза в title/h1 — strong OK;
 * иначе покрытие лемм токенов monitoring.query в title/h1/description (phpMorphy).
 * Body / proximity / TF — позже (этапы 2–3).
 */
class SiteAuditLandingQueryMatchProbe
{
    public function run(SiteAuditCrawl $crawl): void
    {
        $resolved = (new SiteAuditLandingResolver())->forCrawl($crawl);
        $byKeyword = is_array($resolved['by_keyword'] ?? null) ? $resolved['by_keyword'] : [];
        if ($byKeyword === []) {
            return;
        }

        $pages = SiteAuditPage::query()
            ->where('crawl_id', $crawl->id)
            ->where(function ($q) {
                $q->whereNull('status_code')
                    ->orWhere(function ($q2) {
                        $q2->where('status_code', '>=', 200)->where('status_code', '<', 400);
                    });
            })
            ->get(['url', 'url_hash', 'title', 'h1', 'description']);

        if ($pages->isEmpty()) {
            return;
        }

        $byHash = [];
        foreach ($pages as $page) {
            $byHash[$page->url_hash] = $page;
        }

        $max = (int) config('site_audit.landing_query_match_max', 300);
        $minTokenLen = max(3, (int) config('site_audit.cannibalization_min_token', 4));
        $minHits = max(1, (int) config('site_audit.landing_query_min_hits', 2));
        $minShare = (float) config('site_audit.landing_query_min_share', 0.5);
        $cfg = config('site_audit.findings.landing_query_mismatch', []);
        $severity = $cfg['severity'] ?? 'warning';
        $emitted = 0;
        $seen = [];

        foreach ($byKeyword as $kid => $info) {
            if ($emitted >= $max) {
                break;
            }
            $landingUrl = (string) ($info['url'] ?? '');
            $query = trim((string) ($info['query'] ?? ''));
            if ($landingUrl === '' || mb_strlen($query) < 4) {
                continue;
            }
            $landingHash = SiteAuditUrlNormalizer::hash($landingUrl);
            if (! isset($byHash[$landingHash])) {
                continue; // landing_not_crawled отдельно
            }

            $page = $byHash[$landingHash];
            $tokens = SiteAuditCannibalizationProbe::tokens($query, $minTokenLen);
            if ($tokens === []) {
                continue;
            }

            $title = mb_strtolower(trim((string) $page->title));
            $h1 = mb_strtolower(trim((string) $page->h1));
            $desc = mb_strtolower(trim((string) $page->description));
            $queryNorm = mb_strtolower($query);

            $inTitle = $title !== '' && mb_strpos($title, $queryNorm) !== false;
            $inH1 = $h1 !== '' && mb_strpos($h1, $queryNorm) !== false;
            $inDesc = $desc !== '' && mb_strpos($desc, $queryNorm) !== false;

            $titleBag = self::fieldForms($title, $minTokenLen);
            $h1Bag = self::fieldForms($h1, $minTokenLen);
            $descBag = self::fieldForms($desc, $minTokenLen);

            $queryLemmas = [];
            foreach ($tokens as $tok) {
                $lemma = EseninMorphology::lemma($tok);
                $queryLemmas[] = $lemma !== '' ? $lemma : $tok;
            }

            $hitsTitle = 0;
            $hitsH1 = 0;
            $hitsDesc = 0;
            $hitsAny = 0;
            $missing = [];
            foreach ($tokens as $i => $tok) {
                $lemma = $queryLemmas[$i];
                $t = self::formHit($tok, $lemma, $titleBag);
                $h = self::formHit($tok, $lemma, $h1Bag);
                $d = self::formHit($tok, $lemma, $descBag);
                if ($t) {
                    $hitsTitle++;
                }
                if ($h) {
                    $hitsH1++;
                }
                if ($d) {
                    $hitsDesc++;
                }
                if ($t || $h || $d) {
                    $hitsAny++;
                } else {
                    $missing[] = $tok;
                }
            }

            $tokenCount = count($tokens);
            $share = $tokenCount > 0 ? ($hitsAny / $tokenCount) : 0.0;
            $needHits = max($minHits, (int) ceil($tokenCount * $minShare));

            // OK: полный запрос в title или h1, либо достаточно лемм в meta (+ хотя бы одна в title/h1)
            $ok = $inTitle || $inH1 || ($hitsAny >= $needHits && ($hitsTitle + $hitsH1) >= 1);
            if ($ok) {
                continue;
            }

            $key = $landingHash . '|' . md5($queryNorm);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $reasons = [];
            if (! $inTitle && ! $inH1) {
                $reasons[] = 'no_full_query_in_title_h1';
            }
            if ($hitsAny < $needHits) {
                $reasons[] = 'low_lemma_coverage';
            }
            if (($hitsTitle + $hitsH1) < 1) {
                $reasons[] = 'no_lemmas_in_title_h1';
            }

            SiteAuditFinding::query()->create([
                'crawl_id' => $crawl->id,
                'code' => 'landing_query_mismatch',
                'severity' => $severity,
                'url' => $page->url,
                'url_hash' => $page->url_hash,
                'meta_json' => [
                    'query' => $query,
                    'monitoring_keyword_id' => (int) $kid,
                    'landing_url' => $landingUrl,
                    'match_mode' => 'lemmas',
                    'in_title' => $inTitle,
                    'in_h1' => $inH1,
                    'in_description' => $inDesc,
                    'token_count' => $tokenCount,
                    'hits_any' => $hitsAny,
                    'hits_title' => $hitsTitle,
                    'hits_h1' => $hitsH1,
                    'hits_description' => $hitsDesc,
                    'need_hits' => $needHits,
                    'share' => round($share, 3),
                    'query_lemmas' => array_values(array_unique($queryLemmas)),
                    'missing_tokens' => array_slice($missing, 0, 12),
                    'reasons' => $reasons,
                    'page_title' => $page->title,
                ],
            ]);
            $emitted++;
        }
    }

    /**
     * Surface + lemma sets for a meta field.
     *
     * @return array{surfaces: array<string, true>, lemmas: array<string, true>}
     */
    private static function fieldForms(string $text, int $minLen): array
    {
        $surfaces = [];
        $lemmas = [];
        if ($text === '') {
            return ['surfaces' => $surfaces, 'lemmas' => $lemmas];
        }
        if (! preg_match_all('/[\p{L}\p{N}]{' . max(2, $minLen) . ',}/u', $text, $m)) {
            return ['surfaces' => $surfaces, 'lemmas' => $lemmas];
        }
        foreach ($m[0] as $tok) {
            $tok = mb_strtolower((string) $tok);
            $surfaces[$tok] = true;
            $lemma = EseninMorphology::lemma($tok);
            if ($lemma !== '') {
                $lemmas[$lemma] = true;
            }
        }

        return ['surfaces' => $surfaces, 'lemmas' => $lemmas];
    }

    /**
     * @param array{surfaces: array<string, true>, lemmas: array<string, true>} $forms
     */
    private static function formHit(string $surface, string $lemma, array $forms): bool
    {
        if ($lemma !== '' && isset($forms['lemmas'][$lemma])) {
            return true;
        }
        if ($surface !== '' && isset($forms['surfaces'][$surface])) {
            return true;
        }

        return false;
    }
}
