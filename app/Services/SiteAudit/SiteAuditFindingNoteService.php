<?php

namespace App\Services\SiteAudit;

use App\SiteAuditCrawl;
use App\SiteAuditFinding;
use App\SiteAuditFindingNote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SiteAuditFindingNoteService
{
    public function tableReady(): bool
    {
        static $ready = true;

        // Schema::hasTable бьёт в information_schema (~0.2–0.5s на remote MySQL).
        // Таблица есть после миграций; при отсутствии catch ниже в callers.
        return $ready;
    }

    public function upsert(
        int $projectId,
        int $userId,
        string $code,
        string $urlHash,
        ?string $url = null,
        string $status = SiteAuditFindingNote::STATUS_OPEN,
        ?string $comment = null
    ): SiteAuditFindingNote {
        if (! $this->tableReady()) {
            throw new \RuntimeException('Таблица site_audit_finding_notes не создана. Выполните php artisan migrate.');
        }

        $status = $status === SiteAuditFindingNote::STATUS_FIXED
            ? SiteAuditFindingNote::STATUS_FIXED
            : SiteAuditFindingNote::STATUS_OPEN;
        $comment = is_string($comment) ? mb_substr(trim($comment), 0, 1000) : null;
        if ($comment === '') {
            $comment = null;
        }

        return SiteAuditFindingNote::query()->updateOrCreate(
            [
                'project_id' => $projectId,
                'code' => $code,
                'url_hash' => $urlHash,
            ],
            [
                'user_id' => $userId,
                'url' => $url,
                'status' => $status,
                'comment' => $comment,
            ]
        );
    }

    /**
     * «Исправлено» для блока/паттерна (не для каждой страницы).
     */
    public function markPatternFixed(
        int $projectId,
        int $userId,
        string $code,
        string $groupHash,
        ?string $label = null
    ): ?SiteAuditFindingNote {
        $groupHash = trim($groupHash);
        if ($groupHash === '' || ! $this->tableReady()) {
            return null;
        }
        $urlHash = SiteAuditIgnoreService::patternUrlHash($code, $groupHash);
        if ($urlHash === '') {
            return null;
        }

        return $this->upsert(
            $projectId,
            $userId,
            $code,
            $urlHash,
            SiteAuditIgnoreService::PATTERN_URL_PREFIX . mb_substr($groupHash, 0, 480),
            SiteAuditFindingNote::STATUS_FIXED,
            $label
        );
    }

    /**
     * @return array<string,true> group_hash => true
     */
    public function fixedPatternHashesForCode(int $projectId, string $code): array
    {
        if ($projectId < 1 || $code === '' || ! $this->tableReady()) {
            return [];
        }
        $rows = SiteAuditFindingNote::query()
            ->where('project_id', $projectId)
            ->where('code', $code)
            ->where('status', SiteAuditFindingNote::STATUS_FIXED)
            ->where('url_hash', 'like', SiteAuditIgnoreService::PATTERN_HASH_PREFIX . '%')
            ->get(['url']);
        $out = [];
        foreach ($rows as $row) {
            $url = (string) ($row->url ?? '');
            if (strpos($url, SiteAuditIgnoreService::PATTERN_URL_PREFIX) === 0) {
                $sig = substr($url, strlen(SiteAuditIgnoreService::PATTERN_URL_PREFIX));
                if ($sig !== '') {
                    $out[$sig] = true;
                }
            }
        }

        return $out;
    }

    public function clearPatternFixed(int $projectId, string $code, string $groupHash): int
    {
        $urlHash = SiteAuditIgnoreService::patternUrlHash($code, trim($groupHash));
        if ($urlHash === '' || ! $this->tableReady()) {
            return 0;
        }

        return SiteAuditFindingNote::query()
            ->where('project_id', $projectId)
            ->where('code', $code)
            ->where('url_hash', $urlHash)
            ->delete();
    }

    public function upsertForFinding(
        SiteAuditFinding $finding,
        int $projectId,
        int $userId,
        string $status,
        ?string $comment
    ): SiteAuditFindingNote {
        return $this->upsert(
            $projectId,
            $userId,
            $finding->code,
            (string) ($finding->url_hash ?: ''),
            $finding->url,
            $status,
            $comment
        );
    }

    public function delete(int $projectId, string $code, string $urlHash): int
    {
        if (! $this->tableReady()) {
            return 0;
        }

        return SiteAuditFindingNote::query()
            ->where('project_id', $projectId)
            ->where('code', $code)
            ->where('url_hash', $urlHash)
            ->delete();
    }

    public function projectHasFixed(int $projectId): bool
    {
        if (! $this->tableReady()) {
            return false;
        }

        return SiteAuditFindingNote::query()
            ->where('project_id', $projectId)
            ->where('status', SiteAuditFindingNote::STATUS_FIXED)
            ->exists();
    }

    public function excludeFixed(Builder $query, int $projectId, string $findingsTable = 'site_audit_findings'): Builder
    {
        if (! $this->tableReady()) {
            return $query;
        }

        return $query->whereNotExists(function ($q) use ($projectId, $findingsTable) {
            $q->select(DB::raw(1))
                ->from('site_audit_finding_notes as san')
                ->whereColumn('san.code', $findingsTable . '.code')
                ->whereColumn('san.url_hash', $findingsTable . '.url_hash')
                ->where('san.project_id', $projectId)
                ->where('san.status', SiteAuditFindingNote::STATUS_FIXED);
        });
    }

    /**
     * @param array<string,int|float> $rawCounts
     * @return array<string,int|float>
     */
    public function applyFixedToCounts(array $rawCounts, SiteAuditCrawl $crawl): array
    {
        $projectId = (int) $crawl->project_id;
        if ($projectId < 1 || $rawCounts === [] || ! $this->projectHasFixed($projectId)) {
            return $rawCounts;
        }

        $notes = SiteAuditFindingNote::query()
            ->where('project_id', $projectId)
            ->where('status', SiteAuditFindingNote::STATUS_FIXED)
            ->where('url_hash', '!=', '')
            ->get(['code', 'url_hash']);

        if ($notes->isEmpty()) {
            return $rawCounts;
        }

        $urlPairs = [];
        $urlHashes = [];
        $urlCodes = [];
        foreach ($notes as $n) {
            $code = (string) $n->code;
            $hash = (string) $n->url_hash;
            if ($code === '' || $hash === '') {
                continue;
            }
            if (strpos($hash, 'g:') === 0) {
                continue;
            }
            $urlPairs[$code . '|' . $hash] = true;
            $urlHashes[$hash] = true;
            $urlCodes[$code] = true;
        }
        if ($urlPairs === []) {
            return $rawCounts;
        }

        try {
            $rows = \App\SiteAuditFinding::query()
                ->where('crawl_id', (int) $crawl->id)
                ->whereIn('code', array_keys($urlCodes))
                ->whereIn('url_hash', array_keys($urlHashes))
                ->get(['code', 'url_hash']);
        } catch (\Throwable $e) {
            return $rawCounts;
        }

        $fixedByCode = [];
        foreach ($rows as $row) {
            $key = $row->code . '|' . $row->url_hash;
            if (! isset($urlPairs[$key])) {
                continue;
            }
            $fixedByCode[$row->code] = ($fixedByCode[$row->code] ?? 0) + 1;
        }

        $out = $rawCounts;
        foreach ($fixedByCode as $code => $c) {
            if (! isset($out[$code])) {
                continue;
            }
            $out[$code] = max(0, (int) $out[$code] - (int) $c);
        }

        return $out;
    }

    /**
     * @param iterable $rows
     * @return array<int, array{status:string,comment:?string}>
     */
    public function mapForFindings(int $projectId, $rows): array
    {
        if (! $this->tableReady()) {
            return [];
        }

        $keys = [];
        foreach ($rows as $row) {
            if (! isset($row->id)) {
                continue;
            }
            $code = (string) ($row->code ?? '');
            $hash = (string) ($row->url_hash ?? '');
            if ($code === '' || $hash === '') {
                continue;
            }
            $keys[$code][$hash] = true;
        }
        if ($keys === []) {
            return [];
        }

        $notes = SiteAuditFindingNote::query()
            ->where('project_id', $projectId)
            ->whereIn('code', array_keys($keys))
            ->get(['code', 'url_hash', 'status', 'comment']);

        $byKey = [];
        foreach ($notes as $n) {
            $byKey[$n->code . "\0" . $n->url_hash] = [
                'status' => (string) $n->status,
                'comment' => $n->comment,
            ];
        }

        $map = [];
        foreach ($rows as $row) {
            if (! isset($row->id)) {
                continue;
            }
            $key = ((string) ($row->code ?? '')) . "\0" . ((string) ($row->url_hash ?? ''));
            if (isset($byKey[$key])) {
                $map[(int) $row->id] = $byKey[$key];
            }
        }

        return $map;
    }
}
