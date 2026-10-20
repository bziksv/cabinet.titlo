<?php

namespace App\Exports;

use App\SiteAuditCrawl;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * @deprecated Используйте SiteAuditStreamXlsx в контроллере — PhpSpreadsheet OOM на больших краулах.
 */
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
        // Только компактная сводка. Полный лист находок — через SiteAuditStreamXlsx.
        return [
            new SiteAuditSummarySheet($this->crawl),
        ];
    }
}
