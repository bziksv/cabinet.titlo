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
        return self::rowsForCrawl($this->crawl);
    }

    /**
     * @return list<list<string|int|null>>
     */
    public static function rowsForCrawl(SiteAuditCrawl $crawl): array
    {
        $buckets = $crawl->buckets_json ?: [];
        $counts = $crawl->counts_json ?: [];
        $catalog = config('site_audit.findings', []);

        $rows = [
            ['Краул ID', $crawl->id],
            ['Статус', $crawl->statusLabelRu()],
            ['URL всего', $crawl->pages_total],
            ['URL обработано', $crawl->pages_fetched],
            ['Грубые', (int) ($buckets['critical'] ?? 0)],
            ['Прочие', (int) ($buckets['other'] ?? 0)],
            ['Важные замечания', (int) ($buckets['important'] ?? 0)],
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
