<?php

namespace App\Http\Controllers;

use App\Exports\SiteAuditInventorySheet;
use App\Services\SiteAudit\SiteAuditBatchUrlLookup;
use App\Services\SiteAudit\SiteAuditDuplicateGrouper;
use App\Services\SiteAudit\SiteAuditInventory;
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
        'important' => 'Важные замечания',
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
            'crawlScale' => $crawl->scaleStats(),
            'counts' => $counts,
            'tree' => $tree,
            'treeSeo' => $treeSeo,
            'treeAll' => $treeAll,
            'findingsCatalog' => config('site_audit.findings', []),
            'isPublic' => true,
            'whiteLabel' => $crawl->isWhiteLabelShare(),
            'whiteLabelBrand' => $crawl->whiteLabelMeta(),
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
        abort_if(! empty($meta['external']), 404);

        $page = max(1, (int) $request->input('page', 1));
        $perPage = SiteAuditInventory::normalizePerPage($request->input('per_page', SiteAuditInventory::PER_PAGE_DEFAULT));
        $listPerPage = $perPage;
        $filterFields = SiteAuditReportFilter::fieldsForCode($code, (int) $crawl->id);
        $filterValues = SiteAuditReportFilter::valuesFromRequest($request, $code, (int) $crawl->id);
        $isCrawlImages = $code === 'crawl_images';
        $groupable = SiteAuditDuplicateGrouper::isGroupable($code) || $isCrawlImages;
        $viewMode = $request->input('view', $groupable ? 'groups' : 'list');
        if (! in_array($viewMode, ['groups', 'list'], true) || ! $groupable) {
            $viewMode = $groupable ? 'groups' : 'list';
        }

        $invSort = 'url';
        $invDir = 'asc';
        $batchStats = null;
        $groups = [];
        $groupTotal = 0;
        $htmlSitewide = null;
        $rows = collect();
        $total = 0;
        if (SiteAuditInventory::isInventorySource($meta['source'] ?? null)) {
            [$invSort, $invDir] = \App\Services\SiteAudit\SiteAuditCrawlPagesColumns::normalizeSort(
                $request->input('sort'),
                $request->input('dir')
            );
            if ((string) $meta['source'] === SiteAuditInventory::SOURCE_IMAGES) {
                [$invSort, $invDir] = \App\Services\SiteAudit\SiteAuditCrawlImagesColumns::normalizeSort(
                    $request->input('sort'),
                    $request->input('dir')
                );
            }
            if ((string) $meta['source'] === SiteAuditInventory::SOURCE_PAGES
                && ! empty($filterValues['batch'])
            ) {
                [$total, $rows, $batchStats] = SiteAuditBatchUrlLookup::paginate(
                    $crawl,
                    (string) $filterValues['batch'],
                    $page,
                    $perPage,
                    $invSort,
                    $invDir
                );
            } elseif ($isCrawlImages && $viewMode === 'groups') {
                $perPage = 20;
                [$total, $groups, $groupTotal, $htmlSitewide] = SiteAuditInventory::paginateImageGroups(
                    $crawl,
                    $page,
                    $perPage,
                    $filterValues,
                    $invSort,
                    $invDir
                );
                $rows = collect();
            } else {
                [$total, $rows] = SiteAuditInventory::paginate(
                    $crawl,
                    (string) $meta['source'],
                    $page,
                    $perPage,
                    $filterValues,
                    $invSort,
                    $invDir
                );
            }
        } else {
            $codes = ! empty($meta['virtual']) && ! empty($meta['codes'])
                ? array_values($meta['codes'])
                : [$code];
            $query = SiteAuditFinding::query()
                ->where('crawl_id', $crawl->id)
                ->whereIn('code', $codes);
            if ($code === 'serp_title_mismatch') {
                $query->orderByRaw(
                    "CASE
                        WHEN JSON_LENGTH(COALESCE(JSON_EXTRACT(meta_json, '$.engines_mismatch'), JSON_ARRAY())) > 0 THEN 0
                        WHEN JSON_EXTRACT(meta_json, '$.engines.yandex.indexed') = false
                          OR JSON_EXTRACT(meta_json, '$.engines.google.indexed') = false THEN 1
                        ELSE 2
                     END"
                )->orderBy('id');
            } else {
                $query->orderBy('id');
            }
            SiteAuditReportFilter::applyToFindings($query, $crawl->id, $filterValues);
            $total = (clone $query)->count();
            $rows = $query->forPage($page, $perPage)->get();
        }

        $filterParams = SiteAuditReportFilter::queryParams($filterValues);
        if ($groupable) {
            $filterParams['view'] = $viewMode;
        }
        if (SiteAuditInventory::isInventorySource($meta['source'] ?? null)) {
            $filterParams['sort'] = $invSort;
            $filterParams['dir'] = $invDir;
            $filterParams['per_page'] = $listPerPage;
        } elseif ($request->filled('per_page')) {
            $filterParams['per_page'] = $listPerPage;
        }

        $pagesCount = $isCrawlImages && $viewMode === 'groups'
            ? max(1, (int) ceil(max(1, $groupTotal) / $perPage))
            : max(1, (int) ceil($total / $perPage));

        return view('pages.site-audit-public-report', [
            'token' => $token,
            'crawl' => $crawl,
            'project' => $crawl->project,
            'code' => $code,
            'meta' => $meta,
            'rows' => $rows,
            'groups' => $groups,
            'groupable' => $groupable,
            'viewMode' => $viewMode,
            'groupTotal' => $groupTotal,
            'htmlSitewide' => $htmlSitewide,
            'isCrawlImagesReport' => $isCrawlImages,
            'isLinkInvertedReport' => SiteAuditDuplicateGrouper::isLinkInverted($code) || $isCrawlImages,
            'isHtmlErrorReport' => SiteAuditDuplicateGrouper::isHtmlErrors($code),
            'isInsecureFormReport' => SiteAuditDuplicateGrouper::isInsecureForm($code),
            'isTextInNoindexReport' => SiteAuditDuplicateGrouper::isTextInNoindex($code),
            'total' => $total,
            'page' => min($page, $pagesCount),
            'perPage' => $listPerPage,
            'pages' => $pagesCount,
            'bucketLabels' => self::BUCKET_LABELS,
            'isPublic' => true,
            'filterFields' => $filterFields,
            'filterValues' => $filterValues,
            'filtersActive' => SiteAuditReportFilter::hasActive($filterValues),
            'filterAction' => route('site-audit.public.share.report', [$token, $code]),
            'crawlPagesSort' => SiteAuditInventory::isInventorySource($meta['source'] ?? null) ? $invSort : null,
            'crawlPagesDir' => SiteAuditInventory::isInventorySource($meta['source'] ?? null) ? $invDir : null,
            'batchStats' => $batchStats,
            'filterClearUrl' => route('site-audit.public.share.report', [$token, $code]),
            'filterParams' => $filterParams,
            'whiteLabel' => $crawl->isWhiteLabelShare(),
            'whiteLabelBrand' => $crawl->whiteLabelMeta(),
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
        $filterValues = SiteAuditReportFilter::valuesFromRequest($request, $code, (int) $crawl->id);

        if (SiteAuditInventory::isInventorySource($meta['source'] ?? null)) {
            $source = (string) $meta['source'];
            [$invSort, $invDir] = \App\Services\SiteAudit\SiteAuditCrawlPagesColumns::normalizeSort(
                $request->input('sort'),
                $request->input('dir')
            );
            if ($source === SiteAuditInventory::SOURCE_IMAGES) {
                [$invSort, $invDir] = \App\Services\SiteAudit\SiteAuditCrawlImagesColumns::normalizeSort(
                    $request->input('sort'),
                    $request->input('dir')
                );
            }

            return response()->streamDownload(function () use ($crawl, $filterValues, $source, $invSort, $invDir) {
                $out = fopen('php://output', 'w');
                fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
                if ($source === SiteAuditInventory::SOURCE_IMAGES) {
                    fputcsv($out, [
                        'img_src', 'page_url', 'status', 'ok', 'size_bytes', 'width', 'height',
                        'ext', 'https', 'external', 'alt', 'has_alt', 'loading', 'content_type', 'url_len', 'file',
                    ], ';');
                } elseif ($source === SiteAuditInventory::SOURCE_CANONICAL) {
                    fputcsv($out, ['url', 'canonical'], ';');
                } else {
                    fputcsv($out, [
                        'url', 'status', 'title', 'description', 'h1', 'h1_count', 'h2_count',
                        'word_count', 'internal_links', 'external_links', 'img_count',
                        'canonical', 'noindex', 'click_depth', 'size_bytes',
                    ], ';');
                }
                $page = 1;
                $perPage = 200;
                do {
                    [$total, $rows] = SiteAuditInventory::paginate(
                        $crawl,
                        $source,
                        $page,
                        $perPage,
                        $filterValues,
                        $invSort,
                        $invDir
                    );
                    foreach ($rows as $row) {
                        $m = is_array($row->meta_json ?? null) ? $row->meta_json : [];
                        if ($source === SiteAuditInventory::SOURCE_IMAGES) {
                            fputcsv($out, [
                                $row->url,
                                $m['page_url'] ?? '',
                                $m['status'] ?? '',
                                isset($m['ok']) ? ($m['ok'] ? 1 : 0) : '',
                                $m['size_bytes'] ?? '',
                                $m['width'] ?? '',
                                $m['height'] ?? '',
                                $m['ext'] ?? '',
                                ! empty($m['https']) ? 1 : 0,
                                ! empty($m['external']) ? 1 : 0,
                                $m['alt'] ?? '',
                                isset($m['has_alt']) ? ($m['has_alt'] ? 1 : 0) : '',
                                $m['loading'] ?? '',
                                $m['content_type'] ?? '',
                                $m['url_len'] ?? '',
                                $m['file'] ?? '',
                            ], ';');
                        } elseif ($source === SiteAuditInventory::SOURCE_CANONICAL) {
                            fputcsv($out, [$row->url, $m['canonical'] ?? ''], ';');
                        } else {
                            fputcsv($out, [
                                $row->url,
                                $m['status_code'] ?? '',
                                $m['title'] ?? '',
                                $m['description'] ?? '',
                                $m['h1'] ?? '',
                                $m['h1_count'] ?? '',
                                $m['h2_count'] ?? '',
                                $m['word_count'] ?? '',
                                $m['out_links'] ?? '',
                                $m['ext_links'] ?? '',
                                $m['img_count'] ?? '',
                                $m['canonical'] ?? '',
                                ! empty($m['noindex']) ? 1 : 0,
                                $m['click_depth'] ?? '',
                                $m['size_bytes'] ?? '',
                            ], ';');
                        }
                    }
                    $page++;
                } while (($page - 1) * $perPage < $total);
                fclose($out);
            }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $codes = ! empty($meta['virtual']) && ! empty($meta['codes'])
            ? array_values($meta['codes'])
            : [$code];

        return response()->streamDownload(function () use ($crawl, $codes, $filterValues) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, \App\Services\SiteAudit\SiteAuditFindingExportRows::HEADINGS, ';');
            $query = SiteAuditFinding::query()
                ->where('crawl_id', $crawl->id)
                ->whereIn('code', $codes)
                ->orderBy('url')
                ->orderBy('code')
                ->orderBy('id');
            SiteAuditReportFilter::applyToFindings($query, $crawl->id, $filterValues);
            $query->chunk(200, function ($rows) use ($out) {
                foreach ($rows as $row) {
                    foreach (\App\Services\SiteAudit\SiteAuditFindingExportRows::rowsForFinding($row) as $line) {
                        fputcsv($out, $line, ';');
                    }
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportReportXlsx(Request $request, string $token, string $code)
    {
        $crawl = $this->crawlByToken($token);
        $meta = config('site_audit.findings.' . $code, []);
        if (! $meta) {
            abort(404);
        }

        $filename = 'site-audit-' . $crawl->id . '-' . $code . '.xlsx';
        $filterValues = SiteAuditReportFilter::valuesFromRequest($request, $code, (int) $crawl->id);
        if (SiteAuditInventory::isInventorySource($meta['source'] ?? null)) {
            [$invSort, $invDir] = \App\Services\SiteAudit\SiteAuditCrawlPagesColumns::normalizeSort(
                $request->input('sort'),
                $request->input('dir')
            );
            if ((string) $meta['source'] === SiteAuditInventory::SOURCE_IMAGES) {
                [$invSort, $invDir] = \App\Services\SiteAudit\SiteAuditCrawlImagesColumns::normalizeSort(
                    $request->input('sort'),
                    $request->input('dir')
                );
            }

            return Excel::download(
                new SiteAuditInventorySheet(
                    $crawl->id,
                    (string) $meta['source'],
                    $filterValues,
                    (string) ($meta['title'] ?? $code),
                    $invSort,
                    $invDir
                ),
                $filename
            );
        }

        $codes = ! empty($meta['virtual']) && ! empty($meta['codes'])
            ? array_values($meta['codes'])
            : [$code];

        return \App\Services\SiteAudit\SiteAuditStreamXlsx::download($filename, [[
            'title' => (string) ($meta['title'] ?? $code),
            'headings' => \App\Services\SiteAudit\SiteAuditFindingExportRows::HEADINGS,
            'rows' => \App\Services\SiteAudit\SiteAuditFindingExportRows::streamForCrawl(
                (int) $crawl->id,
                $codes,
                $filterValues
            ),
        ]]);
    }

    public function exportCrawlXlsx(string $token)
    {
        $crawl = $this->crawlByToken($token);
        $sheets = [[
            'title' => 'Сводка',
            'headings' => [],
            'rows' => \App\Exports\SiteAuditSummarySheet::rowsForCrawl($crawl),
        ]];

        $counts = is_array($crawl->counts_json) ? $crawl->counts_json : [];
        $hasFindings = (bool) array_filter($counts, static function ($c, $code) {
            return (int) $c > 0 && $code !== 'pages_with_canonical';
        }, ARRAY_FILTER_USE_BOTH);
        if ($hasFindings) {
            $sheets[] = [
                'title' => 'Все находки',
                'headings' => \App\Services\SiteAudit\SiteAuditFindingExportRows::HEADINGS,
                'rows' => \App\Services\SiteAudit\SiteAuditFindingExportRows::streamForCrawl((int) $crawl->id),
            ];
        }
        if (! empty($counts['pages_with_canonical'])) {
            $sheets[] = [
                'title' => 'Canonical',
                'headings' => ['URL', 'Canonical'],
                'rows' => (static function () use ($crawl) {
                    $q = \App\SiteAuditPage::query()
                        ->where('crawl_id', $crawl->id)
                        ->whereNotNull('canonical')
                        ->where('canonical', '!=', '')
                        ->orderBy('id')
                        ->select(['url', 'canonical']);
                    foreach ($q->cursor() as $p) {
                        yield [$p->url, $p->canonical];
                    }
                })(),
            ];
        }

        return \App\Services\SiteAudit\SiteAuditStreamXlsx::download(
            'site-audit-' . $crawl->id . '-summary.xlsx',
            $sheets
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
            'critical' => [], 'other' => [], 'important' => [], 'warning' => [], 'info' => [],
        ];

        foreach ($catalog as $code => $meta) {
            $phase = $meta['phase'] ?? '';
            if (! in_array($phase, ['A', 'B', 'C', 'D'], true)) {
                continue;
            }
            // Публичный share: без логина в другие модули не ведём.
            if (! empty($meta['external'])) {
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
                'external' => false,
                'href' => null,
            ];
        }

        foreach ($bySeverity as $sev => $items) {
            usort($items, function ($a, $b) use ($sev) {
                if ($sev === 'important') {
                    $pin = static function (string $code): int {
                        if ($code === 'similar_pages') {
                            return 0;
                        }
                        if ($code === 'sitemap_missing') {
                            return 1;
                        }
                        if ($code === 'broken_external_link') {
                            return 2;
                        }

                        return 3;
                    };
                    $aPin = $pin((string) ($a['code'] ?? ''));
                    $bPin = $pin((string) ($b['code'] ?? ''));
                    if ($aPin !== $bPin) {
                        return $aPin <=> $bPin;
                    }
                }
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
        $catalog = config('site_audit.findings', []);
        $out = ['critical' => 0, 'other' => 0, 'important' => 0, 'warning' => 0, 'info' => 0];
        foreach ($tree as $sev => $items) {
            foreach ($items as $item) {
                if (! empty($item['external'])) {
                    continue;
                }
                $code = (string) ($item['code'] ?? '');
                if (! empty($catalog[$code]['virtual'])) {
                    continue;
                }
                $out[$sev] = ($out[$sev] ?? 0) + (int) ($item['count'] ?? 0);
            }
        }

        return $out;
    }
}
