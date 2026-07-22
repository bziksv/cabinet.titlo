<?php

namespace App\Services\SiteAudit;

use App\SiteAuditCrawl;
use App\SiteAuditFinding;
use Illuminate\Support\Facades\DB;

/**
 * Сравнение двух краулов одного проекта: counts + URL-выборки по изменившимся кодам.
 */
class SiteAuditCrawlDiff
{
    /** Макс. URL в выборке «появилось» / «ушло» на код */
    private const URL_SAMPLE = 30;

    /**
     * @param SiteAuditCrawl $current текущий (обычно новее)
     * @param SiteAuditCrawl $baseline с чем сравниваем (обычно старше)
     * @return array{
     *   current: array, baseline: array,
     *   pages_delta: int,
     *   buckets: array<string,array{before:int,after:int,delta:int}>,
     *   codes: list<array>,
     *   summary: array{improved:int,worsened:int,new:int,gone:int,same:int}
     * }
     */
    public function compare(SiteAuditCrawl $current, SiteAuditCrawl $baseline): array
    {
        $countsAfter = $this->countsOf($current);
        $countsBefore = $this->countsOf($baseline);

        $catalog = config('site_audit.findings', []);
        $allCodes = array_values(array_unique(array_merge(
            array_keys($countsBefore),
            array_keys($countsAfter),
            array_keys($catalog)
        )));
        sort($allCodes);

        $summary = ['improved' => 0, 'worsened' => 0, 'new' => 0, 'gone' => 0, 'same' => 0];
        $codes = [];
        $changedCodes = [];

        foreach ($allCodes as $code) {
            // служебные ключи counts_json не findings
            if (in_array($code, ['pages_with_canonical', 'click_depth_max'], true)) {
                continue;
            }
            if (! isset($catalog[$code]) && ! isset($countsBefore[$code]) && ! isset($countsAfter[$code])) {
                continue;
            }
            // virtual без собственных rows — пропускаем, если нет count
            if (! empty($catalog[$code]['virtual']) && ! isset($countsBefore[$code]) && ! isset($countsAfter[$code])) {
                continue;
            }

            $before = (int) ($countsBefore[$code] ?? 0);
            $after = (int) ($countsAfter[$code] ?? 0);
            if ($before === 0 && $after === 0) {
                continue;
            }

            $delta = $after - $before;
            if ($before === 0 && $after > 0) {
                $status = 'new';
            } elseif ($after === 0 && $before > 0) {
                $status = 'gone';
            } elseif ($delta < 0) {
                $status = 'improved';
            } elseif ($delta > 0) {
                $status = 'worsened';
            } else {
                $status = 'same';
            }
            $summary[$status]++;

            $row = [
                'code' => $code,
                'title' => $catalog[$code]['title'] ?? $code,
                'severity' => $catalog[$code]['severity'] ?? 'info',
                'before' => $before,
                'after' => $after,
                'delta' => $delta,
                'status' => $status,
                'appeared' => [],
                'fixed' => [],
            ];

            if ($delta !== 0) {
                $changedCodes[] = $code;
            }

            $codes[] = $row;
        }

        usort($codes, function ($a, $b) {
            $rank = ['worsened' => 0, 'new' => 1, 'improved' => 2, 'gone' => 3, 'same' => 4];
            $ra = $rank[$a['status']] ?? 9;
            $rb = $rank[$b['status']] ?? 9;
            if ($ra !== $rb) {
                return $ra <=> $rb;
            }
            $da = abs($a['delta']);
            $db = abs($b['delta']);
            if ($da !== $db) {
                return $db <=> $da;
            }

            return strcmp($a['title'], $b['title']);
        });

        $urlDiffs = $this->urlDiffByCodes($baseline->id, $current->id, $changedCodes);
        foreach ($codes as &$row) {
            if (isset($urlDiffs[$row['code']])) {
                $row['appeared'] = $urlDiffs[$row['code']]['appeared'];
                $row['fixed'] = $urlDiffs[$row['code']]['fixed'];
            }
        }
        unset($row);

        $bucketsAfter = is_array($current->buckets_json) ? $current->buckets_json : [];
        $bucketsBefore = is_array($baseline->buckets_json) ? $baseline->buckets_json : [];
        $bucketDiff = [];
        foreach (['critical', 'other', 'warning', 'info'] as $b) {
            $before = (int) ($bucketsBefore[$b] ?? 0);
            $after = (int) ($bucketsAfter[$b] ?? 0);
            $bucketDiff[$b] = [
                'before' => $before,
                'after' => $after,
                'delta' => $after - $before,
            ];
        }

        return [
            'current' => [
                'id' => $current->id,
                'pages_total' => (int) $current->pages_total,
                'finished_at' => optional($current->finished_at ?: $current->created_at)->toDateTimeString(),
            ],
            'baseline' => [
                'id' => $baseline->id,
                'pages_total' => (int) $baseline->pages_total,
                'finished_at' => optional($baseline->finished_at ?: $baseline->created_at)->toDateTimeString(),
            ],
            'pages_delta' => (int) $current->pages_total - (int) $baseline->pages_total,
            'buckets' => $bucketDiff,
            'codes' => $codes,
            'summary' => $summary,
        ];
    }

    /**
     * @return array<string,int>
     */
    private function countsOf(SiteAuditCrawl $crawl): array
    {
        if (is_array($crawl->counts_json) && $crawl->counts_json !== []) {
            return $crawl->counts_json;
        }

        return SiteAuditFinding::query()
            ->where('crawl_id', $crawl->id)
            ->select('code', DB::raw('count(*) as c'))
            ->groupBy('code')
            ->pluck('c', 'code')
            ->map(function ($c) {
                return (int) $c;
            })
            ->all();
    }

    /**
     * @param string[] $codes
     * @return array<string,array{appeared:list<string>,fixed:list<string>}>
     */
    private function urlDiffByCodes(int $baselineId, int $currentId, array $codes): array
    {
        $out = [];
        if ($codes === []) {
            return $out;
        }

        // ограничиваем число кодов с URL-выборкой
        $codes = array_slice($codes, 0, 40);

        $before = SiteAuditFinding::query()
            ->where('crawl_id', $baselineId)
            ->whereIn('code', $codes)
            ->select('code', 'url', 'url_hash')
            ->get()
            ->groupBy('code');

        $after = SiteAuditFinding::query()
            ->where('crawl_id', $currentId)
            ->whereIn('code', $codes)
            ->select('code', 'url', 'url_hash')
            ->get()
            ->groupBy('code');

        foreach ($codes as $code) {
            $beforeMap = [];
            foreach ($before->get($code, collect()) as $f) {
                $beforeMap[$f->url_hash ?: $f->url] = $f->url;
            }
            $afterMap = [];
            foreach ($after->get($code, collect()) as $f) {
                $afterMap[$f->url_hash ?: $f->url] = $f->url;
            }

            $appeared = [];
            foreach ($afterMap as $hash => $url) {
                if (! isset($beforeMap[$hash])) {
                    $appeared[] = $url;
                    if (count($appeared) >= self::URL_SAMPLE) {
                        break;
                    }
                }
            }

            $fixed = [];
            foreach ($beforeMap as $hash => $url) {
                if (! isset($afterMap[$hash])) {
                    $fixed[] = $url;
                    if (count($fixed) >= self::URL_SAMPLE) {
                        break;
                    }
                }
            }

            $out[$code] = [
                'appeared' => $appeared,
                'fixed' => $fixed,
            ];
        }

        return $out;
    }
}
