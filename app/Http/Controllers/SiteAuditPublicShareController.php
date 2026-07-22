<?php

namespace App\Http\Controllers;

use App\Exports\SiteAuditCanonicalSheet;
use App\Exports\SiteAuditCrawlSummaryExport;
use App\Exports\SiteAuditFindingsExport;
use App\Services\SiteAudit\SiteAuditReportFilter;
use App\SiteAuditCrawl;
use App\SiteAuditFinding;
use App\SiteAuditPage;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Публичный read-only просмотр shared краула (без авторизации).
 */
class SiteAuditPublicShareController extends Controller
{
    private const BUCKET_LABELS = [
        'critical' => 'Грубые',
        'other' => 'Прочие',
        'warning' => 'Предупреждения',
        'info' => 'Инфо',
    ];

    public function show(string $token): View
    {
        $crawl = $this->crawlByToken($token);
        $crawl->load('project');

        $counts = $crawl->counts_json ?: [];
        $tree = $this->buildTree($counts, 'tech');
        $treeSeo = $this->buildTree($counts, 'seo');
        $treeAll = $this->buildTree($counts, null);
        $bucketsTech = $this->bucketsFromTree($tree);
        $bucketsSeo = $this->bucketsFromTree($treeSeo);
        $bucketsAll = $this->bucketsFromTree($treeAll);

        return view('pages.site-audit-public', [
            'token' => $token,
            'crawl' => $crawl,
            'project' => $crawl->project,
            'buckets' => $bucketsTech,
            'bucketsSeo' => $bucketsSeo,
            'bucketsAll' => $bucketsAll,
            'bucketLabels' => self::BUCKET_LABELS,
            'counts' => $counts,
            'tree' => $tree,
            'treeSeo' => $treeSeo,
            'treeAll' => $treeAll,
            'findingsCatalog' => config('site_audit.findings', []),
            'isPublic' => true,
        ]);
    }

    public function showReport(Request $request, string $token, string $code): View
    {
        $crawl = $this->crawlByToken($token);
        $crawl->load('project');

        $meta = config('site_audit.findings.' . $code);
        if (! $meta) {
            abort(404);
        }

        $page = max(1, (int) $request->input('page', 1));
        $perPage = 50;
        $filterFields = SiteAuditReportFilter::fieldsForCode($code);
        $filterValues = SiteAuditReportFilter::valuesFromRequest($request, $code);

        if (($meta['source'] ?? '') === 'pages_canonical') {
            $query = SiteAuditPage::query()
                ->where('crawl_id', $crawl->id)
                ->whereNotNull('canonical')
                ->where('canonical', '!=', '')
                ->orderBy('id');
            SiteAuditReportFilter::applyToPages($query, $filterValues);
            $total = (clone $query)->count();
            $rows = $query->forPage($page, $perPage)->get()->map(function (SiteAuditPage $p) use ($meta) {
                return (object) [
                    'url' => $p->url,
                    'severity' => $meta['severity'] ?? 'info',
                    'code' => 'pages_with_canonical',
                    'meta_json' => ['canonical' => $p->canonical],
                ];
            });
        } else {
            $codes = ! empty($meta['virtual']) && ! empty($meta['codes'])
                ? array_values($meta['codes'])
                : [$code];
            $query = SiteAuditFinding::query()
                ->where('crawl_id', $crawl->id)
                ->whereIn('code', $codes)
                ->orderBy('id');
            SiteAuditReportFilter::applyToFindings($query, $crawl->id, $filterValues);
            $total = (clone $query)->count();
            $rows = $query->forPage($page, $perPage)->get();
        }

        $filterParams = SiteAuditReportFilter::queryParams($filterValues);

        return view('pages.site-audit-public-report', [
            'token' => $token,
            'crawl' => $crawl,
            'project' => $crawl->project,
            'code' => $code,
            'meta' => $meta,
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'pages' => max(1, (int) ceil($total / $perPage)),
            'bucketLabels' => self::BUCKET_LABELS,
            'isPublic' => true,
            'filterFields' => $filterFields,
            'filterValues' => $filterValues,
            'filtersActive' => SiteAuditReportFilter::hasActive($filterValues),
            'filterAction' => route('site-audit.public.share.report', [$token, $code]),
            'filterClearUrl' => route('site-audit.public.share.report', [$token, $code]),
            'filterParams' => $filterParams,
        ]);
    }

    public function exportCsv(Request $request, string $token, string $code): StreamedResponse
    {
        $crawl = $this->crawlByToken($token);
        $meta = config('site_audit.findings.' . $code, []);
        if (! $meta) {
            abort(404);
        }

        $filename = 'site-audit-' . $crawl->id . '-' . $code . '.csv';
        $filterValues = SiteAuditReportFilter::valuesFromRequest($request, $code);

        if (($meta['source'] ?? '') === 'pages_canonical') {
            return response()->streamDownload(function () use ($crawl, $filterValues) {
                $out = fopen('php://output', 'w');
                fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
                fputcsv($out, ['url', 'canonical'], ';');
                $query = SiteAuditPage::query()
                    ->where('crawl_id', $crawl->id)
                    ->whereNotNull('canonical')
                    ->where('canonical', '!=', '')
                    ->orderBy('id');
                SiteAuditReportFilter::applyToPages($query, $filterValues);
                $query->chunk(200, function ($rows) use ($out) {
                    foreach ($rows as $row) {
                        fputcsv($out, [$row->url, $row->canonical], ';');
                    }
                });
                fclose($out);
            }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $codes = ! empty($meta['virtual']) && ! empty($meta['codes'])
            ? array_values($meta['codes'])
            : [$code];

        return response()->streamDownload(function () use ($crawl, $codes, $filterValues) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['url', 'code', 'severity', 'meta'], ';');
            $query = SiteAuditFinding::query()
                ->where('crawl_id', $crawl->id)
                ->whereIn('code', $codes)
                ->orderBy('id');
            SiteAuditReportFilter::applyToFindings($query, $crawl->id, $filterValues);
            $query->chunk(200, function ($rows) use ($out) {
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row->url,
                        $row->code,
                        $row->severity,
                        $row->meta_json ? json_encode($row->meta_json, JSON_UNESCAPED_UNICODE) : '',
                    ], ';');
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportReportXlsx(Request $request, string $token, string $code): BinaryFileResponse
    {
        $crawl = $this->crawlByToken($token);
        $meta = config('site_audit.findings.' . $code, []);
        if (! $meta) {
            abort(404);
        }

        $filename = 'site-audit-' . $crawl->id . '-' . $code . '.xlsx';
        $filterValues = SiteAuditReportFilter::valuesFromRequest($request, $code);
        if (($meta['source'] ?? '') === 'pages_canonical') {
            return Excel::download(new SiteAuditCanonicalSheet($crawl->id, $filterValues), $filename);
        }

        $codes = ! empty($meta['virtual']) && ! empty($meta['codes'])
            ? array_values($meta['codes'])
            : [$code];

        return Excel::download(
            new SiteAuditFindingsExport($crawl->id, $codes, (string) ($meta['title'] ?? $code), $filterValues),
            $filename
        );
    }

    public function exportCrawlXlsx(string $token): BinaryFileResponse
    {
        $crawl = $this->crawlByToken($token);

        return Excel::download(
            new SiteAuditCrawlSummaryExport($crawl),
            'site-audit-' . $crawl->id . '-summary.xlsx'
        );
    }

    public function exportCrawlDocx(string $token)
    {
        $crawl = $this->crawlByToken($token);
        $path = (new \App\Services\SiteAudit\SiteAuditDocxBuilder())->buildToTemp($crawl);

        return response()->download(
            $path,
            'site-audit-' . $crawl->id . '-summary.docx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
        )->deleteFileAfterSend(true);
    }

    private function crawlByToken(string $token): SiteAuditCrawl
    {
        $token = trim($token);
        abort_if($token === '', 404);

        $crawl = SiteAuditCrawl::query()
            ->where('share_token', $token)
            ->whereNotNull('share_enabled_at')
            ->first();

        abort_unless($crawl, 404);
        abort_unless($crawl->status === SiteAuditCrawl::STATUS_DONE, 404);

        return $crawl;
    }

    private function buildTree($counts, ?string $group = null): array
    {
        $counts = (array) $counts;
        $catalog = config('site_audit.findings', []);
        $bySeverity = [
            'critical' => [], 'other' => [], 'warning' => [], 'info' => [],
        ];

        foreach ($catalog as $code => $meta) {
            $phase = $meta['phase'] ?? '';
            if (! in_array($phase, ['A', 'B'], true)) {
                continue;
            }
            $itemGroup = $meta['group'] ?? (in_array($code, config('site_audit.seo_codes', []), true) ? 'seo' : 'tech');
            if ($group !== null && $itemGroup !== $group) {
                continue;
            }
            $severity = $meta['severity'] ?? 'info';
            if (! isset($bySeverity[$severity])) {
                $severity = 'info';
            }

            if (! empty($meta['virtual']) && ! empty($meta['codes'])) {
                $count = 0;
                foreach ($meta['codes'] as $c) {
                    $count += (int) ($counts[$c] ?? 0);
                }
            } else {
                $count = (int) ($counts[$code] ?? 0);
            }

            $bySeverity[$severity][] = [
                'code' => $code,
                'title' => $meta['title'] ?? $code,
                'description' => $meta['description'] ?? '',
                'count' => $count,
                'phase' => $phase,
                'group' => $itemGroup,
            ];
        }

        foreach ($bySeverity as $sev => $items) {
            usort($items, function ($a, $b) {
                if ($a['count'] === $b['count']) {
                    return strcmp($a['title'], $b['title']);
                }

                return $b['count'] <=> $a['count'];
            });
            $bySeverity[$sev] = $items;
        }

        return $bySeverity;
    }

    private function bucketsFromTree(array $tree): array
    {
        $out = ['critical' => 0, 'other' => 0, 'warning' => 0, 'info' => 0];
        foreach ($tree as $sev => $items) {
            foreach ($items as $item) {
                $out[$sev] = ($out[$sev] ?? 0) + (int) ($item['count'] ?? 0);
            }
        }

        return $out;
    }
}
