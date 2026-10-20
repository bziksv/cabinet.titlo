<?php

namespace App\Exports;

use App\Services\SiteAudit\SiteAuditFindingExportRows;
use App\Services\SiteAudit\SiteAuditFindingNoteService;
use App\Services\SiteAudit\SiteAuditIgnoreService;
use App\Services\SiteAudit\SiteAuditReportFilter;
use App\SiteAuditCrawl;
use App\SiteAuditFinding;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class SiteAuditFindingsExport implements FromCollection, WithHeadings, WithTitle
{
    /** @var int */
    private $crawlId;

    /** @var string[] */
    private $codes;

    /** @var string */
    private $title;

    /** @var array<string,string> */
    private $filters;

    /** @var bool */
    private $includeIgnored;

    /** @var bool */
    private $includeFixed;

    public function __construct(
        int $crawlId,
        array $codes,
        string $title = 'Findings',
        array $filters = [],
        bool $includeIgnored = false,
        bool $includeFixed = false
    ) {
        $this->crawlId = $crawlId;
        $this->codes = $codes;
        $this->title = mb_substr($title, 0, 31);
        $this->filters = $filters;
        $this->includeIgnored = $includeIgnored;
        $this->includeFixed = $includeFixed;
    }

    public function title(): string
    {
        return $this->title !== '' ? $this->title : 'Findings';
    }

    public function headings(): array
    {
        return SiteAuditFindingExportRows::HEADINGS;
    }

    public function collection()
    {
        $rows = [];
        $query = SiteAuditFinding::query()
            ->where('crawl_id', $this->crawlId)
            ->whereIn('code', $this->codes)
            ->orderBy('url')
            ->orderBy('code')
            ->orderBy('id');
        SiteAuditReportFilter::applyToFindings($query, $this->crawlId, $this->filters);
        $projectId = (int) SiteAuditCrawl::query()->where('id', $this->crawlId)->value('project_id');
        if ($projectId > 0) {
            if (! $this->includeIgnored) {
                (new SiteAuditIgnoreService())->excludeIgnored($query, $projectId);
            }
            if (! $this->includeFixed) {
                (new SiteAuditFindingNoteService())->excludeFixed($query, $projectId);
            }
        }
        $query->chunk(200, function ($chunk) use (&$rows) {
            foreach ($chunk as $row) {
                foreach (SiteAuditFindingExportRows::rowsForFinding($row) as $line) {
                    $rows[] = $line;
                }
            }
        });

        return collect($rows);
    }
}
