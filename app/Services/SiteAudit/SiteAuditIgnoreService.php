<?php

namespace App\Services\SiteAudit;

use App\SiteAuditCrawl;
use App\SiteAuditFinding;
use App\SiteAuditIgnore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SiteAuditIgnoreService
{
    /**
     * Игнор URL для кода (или всего кода, если $urlHash === null/'').
     */
    public function ignore(int $projectId, int $userId, string $code, ?string $urlHash = null, ?string $url = null, ?string $note = null): SiteAuditIgnore
    {
        $hash = $urlHash === null ? '' : (string) $urlHash;

        return SiteAuditIgnore::query()->updateOrCreate(
            [
                'project_id' => $projectId,
                'code' => $code,
                'url_hash' => $hash,
            ],
            [
                'user_id' => $userId,
                'url' => $url,
                'note' => $note,
            ]
        );
    }

    public function ignoreFinding(SiteAuditFinding $finding, int $projectId, int $userId, ?string $note = null): SiteAuditIgnore
    {
        return $this->ignore(
            $projectId,
            $userId,
            $finding->code,
            $finding->url_hash ?: '',
            $finding->url,
            $note
        );
    }

    public function restore(int $projectId, string $code, ?string $urlHash = null): int
    {
        $hash = $urlHash === null ? '' : (string) $urlHash;

        return SiteAuditIgnore::query()
            ->where('project_id', $projectId)
            ->where('code', $code)
            ->where('url_hash', $hash)
            ->delete();
    }

    public function restoreFinding(SiteAuditFinding $finding, int $projectId): int
    {
        return $this->restore($projectId, $finding->code, $finding->url_hash ?: '');
    }

    public function projectHasIgnores(int $projectId): bool
    {
        return SiteAuditIgnore::query()->where('project_id', $projectId)->exists();
    }

    /**
     * Исключить игнорируемые findings из запроса.
     */
    public function excludeIgnored(Builder $query, int $projectId, string $findingsTable = 'site_audit_findings'): Builder
    {
        return $query->whereNotExists(function ($q) use ($projectId, $findingsTable) {
            $q->select(DB::raw(1))
                ->from('site_audit_ignores as sai')
                ->whereColumn('sai.code', $findingsTable . '.code')
                ->where('sai.project_id', $projectId)
                ->where(function ($w) use ($findingsTable) {
                    $w->where('sai.url_hash', '')
                        ->orWhereColumn('sai.url_hash', $findingsTable . '.url_hash');
                });
        });
    }

    /**
     * Только игнорируемые.
     */
    public function onlyIgnored(Builder $query, int $projectId, string $findingsTable = 'site_audit_findings'): Builder
    {
        return $query->whereExists(function ($q) use ($projectId, $findingsTable) {
            $q->select(DB::raw(1))
                ->from('site_audit_ignores as sai')
                ->whereColumn('sai.code', $findingsTable . '.code')
                ->where('sai.project_id', $projectId)
                ->where(function ($w) use ($findingsTable) {
                    $w->where('sai.url_hash', '')
                        ->orWhereColumn('sai.url_hash', $findingsTable . '.url_hash');
                });
        });
    }

    /**
     * Скорректировать counts_json с учётом ignores проекта.
     *
     * @param array<string,int|float> $rawCounts
     * @return array<string,int|float>
     */
    public function applyToCounts(array $rawCounts, SiteAuditCrawl $crawl): array
    {
        $projectId = (int) $crawl->project_id;
        if ($projectId < 1 || ! $this->projectHasIgnores($projectId)) {
            return $rawCounts;
        }

        $ignoredByCode = SiteAuditFinding::query()
            ->where('crawl_id', $crawl->id)
            ->whereExists(function ($q) use ($projectId) {
                $q->select(DB::raw(1))
                    ->from('site_audit_ignores as sai')
                    ->whereColumn('sai.code', 'site_audit_findings.code')
                    ->where('sai.project_id', $projectId)
                    ->where(function ($w) {
                        $w->where('sai.url_hash', '')
                            ->orWhereColumn('sai.url_hash', 'site_audit_findings.url_hash');
                    });
            })
            ->select('code', DB::raw('count(*) as c'))
            ->groupBy('code')
            ->pluck('c', 'code')
            ->all();

        if ($ignoredByCode === []) {
            return $rawCounts;
        }

        $out = $rawCounts;
        foreach ($ignoredByCode as $code => $c) {
            if (! isset($out[$code])) {
                continue;
            }
            $out[$code] = max(0, (int) $out[$code] - (int) $c);
        }

        return $out;
    }

    /**
     * Пометить строки findings флагом ignored (для UI include_ignored).
     *
     * @param iterable $rows
     * @return array<int,bool> finding_id => ignored
     */
    public function ignoredMapForFindings(int $projectId, $rows): array
    {
        $hashesByCode = [];
        foreach ($rows as $row) {
            if (! isset($row->id)) {
                continue;
            }
            $code = (string) ($row->code ?? '');
            $hash = (string) ($row->url_hash ?? '');
            if ($code === '') {
                continue;
            }
            $hashesByCode[$code][$hash] = true;
        }
        if ($hashesByCode === []) {
            return [];
        }

        $ignores = SiteAuditIgnore::query()
            ->where('project_id', $projectId)
            ->whereIn('code', array_keys($hashesByCode))
            ->get(['code', 'url_hash']);

        $map = [];
        foreach ($rows as $row) {
            if (! isset($row->id)) {
                continue;
            }
            $code = (string) ($row->code ?? '');
            $hash = (string) ($row->url_hash ?? '');
            $ignored = false;
            foreach ($ignores as $ig) {
                if ($ig->code !== $code) {
                    continue;
                }
                if ($ig->url_hash === '' || $ig->url_hash === $hash) {
                    $ignored = true;
                    break;
                }
            }
            $map[(int) $row->id] = $ignored;
        }

        return $map;
    }

    /**
     * Сколько findings по severity скрыты (игнор или «исправлено») — для истории проверок.
     *
     * @param  array<int>  $crawlIds
     * @return array<int, array{critical:int,other:int,important:int,warning:int,info:int}>
     */
    public function hiddenBucketsByCrawlIds(array $crawlIds): array
    {
        $crawlIds = array_values(array_unique(array_filter(array_map('intval', $crawlIds))));
        $empty = [
            'critical' => 0,
            'other' => 0,
            'important' => 0,
            'warning' => 0,
            'info' => 0,
        ];
        if ($crawlIds === []) {
            return [];
        }

        $out = [];
        foreach ($crawlIds as $id) {
            $out[$id] = $empty;
        }

        $notesReady = (new SiteAuditFindingNoteService())->tableReady();

        try {
            $rows = SiteAuditFinding::query()
                ->from('site_audit_findings as f')
                ->join('site_audit_crawls as c', 'c.id', '=', 'f.crawl_id')
                ->whereIn('f.crawl_id', $crawlIds)
                ->where(function ($q) use ($notesReady) {
                    $q->whereExists(function ($iq) {
                        $iq->select(DB::raw(1))
                            ->from('site_audit_ignores as sai')
                            ->whereColumn('sai.code', 'f.code')
                            ->whereColumn('sai.project_id', 'c.project_id')
                            ->where(function ($w) {
                                $w->where('sai.url_hash', '')
                                    ->orWhereColumn('sai.url_hash', 'f.url_hash');
                            });
                    });
                    if ($notesReady) {
                        $q->orWhereExists(function ($nq) {
                            $nq->select(DB::raw(1))
                                ->from('site_audit_finding_notes as san')
                                ->whereColumn('san.code', 'f.code')
                                ->whereColumn('san.url_hash', 'f.url_hash')
                                ->whereColumn('san.project_id', 'c.project_id')
                                ->where('san.status', \App\SiteAuditFindingNote::STATUS_FIXED);
                        });
                    }
                })
                ->select('f.crawl_id', 'f.severity', DB::raw('COUNT(DISTINCT f.id) as c'))
                ->groupBy('f.crawl_id', 'f.severity')
                ->get();
        } catch (\Throwable $e) {
            return $out;
        }

        foreach ($rows as $row) {
            $cid = (int) $row->crawl_id;
            $sev = (string) $row->severity;
            if (! isset($out[$cid][$sev])) {
                continue;
            }
            $out[$cid][$sev] = (int) $row->c;
        }

        return $out;
    }

    /**
     * @return array{critical:int,other:int,important:int,warning:int,info:int}
     */
    public function hiddenBucketsForCrawl(SiteAuditCrawl $crawl): array
    {
        $map = $this->hiddenBucketsByCrawlIds([(int) $crawl->id]);

        return $map[(int) $crawl->id] ?? [
            'critical' => 0,
            'other' => 0,
            'important' => 0,
            'warning' => 0,
            'info' => 0,
        ];
    }
}
