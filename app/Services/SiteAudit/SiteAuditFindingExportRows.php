<?php

namespace App\Services\SiteAudit;

use App\SiteAuditCrawl;
use App\SiteAuditFinding;
use Generator;

/**
 * Плоские строки для CSV/XLSX: одна ошибка = одна строка (удобно сортировать по URL и по тексту ошибки).
 */
class SiteAuditFindingExportRows
{
    public const HEADINGS = ['URL', 'Код', 'Приоритет', 'Ошибка', 'Строка', 'Уровень'];

    /**
     * Потоковый генератор строк для краула (без накопления в памяти).
     *
     * @param string[] $codes пусто = все коды с count > 0, кроме служебных
     * @param array<string,string> $filters
     * @return Generator<int, list<string|int>>
     */
    public static function streamForCrawl(
        int $crawlId,
        array $codes = [],
        array $filters = [],
        bool $includeIgnored = false,
        bool $includeFixed = false
    ): Generator {
        if ($codes === []) {
            $counts = SiteAuditCrawl::query()->where('id', $crawlId)->value('counts_json') ?: [];
            if (! is_array($counts)) {
                $counts = [];
            }
            $codes = array_keys(array_filter($counts, static function ($c, $code) {
                return (int) $c > 0 && $code !== 'pages_with_canonical';
            }, ARRAY_FILTER_USE_BOTH));
        }
        if ($codes === []) {
            return;
        }

        $query = SiteAuditFinding::query()
            ->where('crawl_id', $crawlId)
            ->whereIn('code', $codes)
            ->orderBy('url')
            ->orderBy('code')
            ->orderBy('id');
        SiteAuditReportFilter::applyToFindings($query, $crawlId, $filters);
        $projectId = (int) SiteAuditCrawl::query()->where('id', $crawlId)->value('project_id');
        if ($projectId > 0) {
            if (! $includeIgnored) {
                (new SiteAuditIgnoreService())->excludeIgnored($query, $projectId);
            }
            if (! $includeFixed) {
                (new SiteAuditFindingNoteService())->excludeFixed($query, $projectId);
            }
        }

        foreach ($query->cursor() as $row) {
            foreach (self::rowsForFinding($row) as $line) {
                yield $line;
            }
        }
    }

    /**
     * @return list<list<string|int>>
     */
    public static function rowsForFinding(SiteAuditFinding $row): array
    {
        $meta = is_array($row->meta_json) ? $row->meta_json : [];
        $severity = SiteAuditFindingPresenter::severityLabel($row->severity);
        $base = [
            (string) $row->url,
            (string) $row->code,
            $severity,
        ];

        $expanded = self::expandSamples((string) $row->code, $meta);
        if ($expanded !== []) {
            $out = [];
            foreach ($expanded as $item) {
                $out[] = array_merge($base, [
                    $item['error'],
                    $item['line'] !== null ? $item['line'] : '',
                    $item['level'],
                ]);
            }

            return $out;
        }

        return [[
            $base[0],
            $base[1],
            $base[2],
            SiteAuditFindingPresenter::metaLine($row->code, $meta, $row->url) ?: '',
            '',
            '',
        ]];
    }

    /**
     * @param array<string, mixed> $meta
     * @return list<array{error:string,line:?int,level:string}>
     */
    private static function expandSamples(string $code, array $meta): array
    {
        $samples = isset($meta['samples']) && is_array($meta['samples']) ? $meta['samples'] : [];
        if ($samples === []) {
            return [];
        }

        $out = [];
        foreach ($samples as $sample) {
            if (is_string($sample)) {
                $text = trim($sample);
                if ($text !== '') {
                    $out[] = ['error' => $text, 'line' => null, 'level' => ''];
                }
                continue;
            }
            if (! is_array($sample)) {
                continue;
            }

            $msg = trim((string) ($sample['message'] ?? ''));
            if ($msg !== '') {
                $line = isset($sample['line']) && (int) $sample['line'] > 0
                    ? (int) $sample['line']
                    : null;
                $level = trim((string) ($sample['level'] ?? ''));
                $out[] = ['error' => $msg, 'line' => $line, 'level' => $level];
                continue;
            }

            $href = trim((string) ($sample['href'] ?? $sample['url'] ?? $sample['asset'] ?? ''));
            if ($href !== '') {
                $bits = [$href];
                $reason = trim((string) ($sample['reason'] ?? ''));
                if ($reason !== '') {
                    $bits[] = $reason;
                }
                $status = isset($sample['status']) ? ('HTTP ' . (int) $sample['status']) : '';
                if ($status !== '') {
                    $bits[] = $status;
                }
                $out[] = ['error' => implode(' · ', $bits), 'line' => null, 'level' => ''];
                continue;
            }

            $word = trim((string) ($sample['word'] ?? ''));
            if ($word !== '') {
                $cnt = (int) ($sample['count'] ?? 0);
                $out[] = [
                    'error' => $cnt > 0 ? ($word . ' ×' . $cnt) : $word,
                    'line' => null,
                    'level' => '',
                ];
            }
        }

        // Не разворачиваем «шумные» выборки, если ни один sample не распознан —
        // останется одна строка с metaLine.
        if ($out === []) {
            return [];
        }

        // html_critical_errors и похожие: если count > числа samples — помечаем хвост.
        $declared = (int) ($meta['count'] ?? 0);
        if ($declared > count($out) && in_array($code, ['html_critical_errors', 'page_has_bad_links'], true)) {
            $missing = $declared - count($out);
            $out[] = [
                'error' => '… ещё ошибок: ' . $missing . ' (не сохранены в снимке проверки)',
                'line' => null,
                'level' => 'info',
            ];
        }

        return $out;
    }
}
