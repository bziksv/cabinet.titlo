<?php

namespace App\Exports;

use App\Services\SiteAudit\SiteAuditFindingPresenter;
use App\SiteAuditCrawl;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class SiteAuditSummarySheet implements FromArray, WithTitle
{
    /** @var SiteAuditCrawl */
    private $crawl;

    public function __construct(SiteAuditCrawl $crawl)
    {
        $this->crawl = $crawl;
    }

    public function title(): string
    {
        return 'Сводка';
    }

    public function array(): array
    {
        $buckets = $this->crawl->buckets_json ?: [];
        $counts = $this->crawl->counts_json ?: [];
        $catalog = config('site_audit.findings', []);

        $rows = [
            ['Краул ID', $this->crawl->id],
            ['Статус', $this->crawl->statusLabelRu()],
            ['URL всего', $this->crawl->pages_total],
            ['URL обработано', $this->crawl->pages_fetched],
            ['Грубые', (int) ($buckets['critical'] ?? 0)],
            ['Прочие', (int) ($buckets['other'] ?? 0)],
            ['Предупреждения', (int) ($buckets['warning'] ?? 0)],
            ['Инфо', (int) ($buckets['info'] ?? 0)],
            [],
            ['Код', 'Отчёт', 'Приоритет', 'Находок'],
        ];

        arsort($counts);
        foreach ($counts as $code => $cnt) {
            if ((int) $cnt <= 0) {
                continue;
            }
            $meta = $catalog[$code] ?? [];
            $rows[] = [
                $code,
                $meta['title'] ?? $code,
                SiteAuditFindingPresenter::severityLabel($meta['severity'] ?? 'info'),
                (int) $cnt,
            ];
        }

        return $rows;
    }
}
