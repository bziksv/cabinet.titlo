<?php

namespace App\Exports;

use App\Services\SiteAudit\SiteAuditReportFilter;
use App\SiteAuditPage;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class SiteAuditCanonicalSheet implements FromCollection, WithHeadings, WithTitle
{
    /** @var int */
    private $crawlId;

    /** @var array<string,string> */
    private $filters;

    public function __construct(int $crawlId, array $filters = [])
    {
        $this->crawlId = $crawlId;
        $this->filters = $filters;
    }

    public function title(): string
    {
        return 'Canonical';
    }

    public function headings(): array
    {
        return ['URL', 'Canonical'];
    }

    public function collection()
    {
        $query = SiteAuditPage::query()
            ->where('crawl_id', $this->crawlId)
            ->whereNotNull('canonical')
            ->where('canonical', '!=', '')
            ->orderBy('id');
        SiteAuditReportFilter::applyToPages($query, $this->filters);

        return $query->get(['url', 'canonical'])->map(function ($p) {
            return [$p->url, $p->canonical];
        });
    }
}
