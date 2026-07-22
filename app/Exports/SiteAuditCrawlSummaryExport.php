<?php

namespace App\Exports;

use App\SiteAuditCrawl;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SiteAuditCrawlSummaryExport implements WithMultipleSheets
{
    use Exportable;

    /** @var SiteAuditCrawl */
    private $crawl;

    public function __construct(SiteAuditCrawl $crawl)
    {
        $this->crawl = $crawl;
    }

    public function sheets(): array
    {
        $sheets = [
            new SiteAuditSummarySheet($this->crawl),
        ];

        $counts = $this->crawl->counts_json ?: [];
        $codes = array_keys(array_filter($counts, function ($c, $code) {
            return (int) $c > 0 && $code !== 'pages_with_canonical';
        }, ARRAY_FILTER_USE_BOTH));

        if ($codes) {
            $sheets[] = new SiteAuditFindingsExport(
                $this->crawl->id,
                $codes,
                'Все находки'
            );
        }

        if (! empty($counts['pages_with_canonical'])) {
            $sheets[] = new SiteAuditCanonicalSheet($this->crawl->id);
        }

        return $sheets;
    }
}
