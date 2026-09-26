<?php

namespace App\Services\SiteAudit;

use App\SiteAuditCrawl;
use App\SiteAuditFinding;
use App\SiteAuditFindingNote;
use App\SiteAuditIgnore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SiteAuditIgnoreService
{
    public const PATTERN_URL_PREFIX = 'pattern:';
    public const PATTERN_HASH_PREFIX = 'g:';

    /**
     * Ключ игнора паттерна (блока в режиме «По ошибкам»), не URL страницы.
     */
    public static function patternUrlHash(string $code, string $groupHash): string
    {
        $groupHash = trim($groupHash);
        if ($groupHash === '') {
            return '';
        }

        return self::PATTERN_HASH_PREFIX . substr(hash('sha256', $code . "\0" . $groupHash), 0, 40);
    }

    public static function isPatternUrlHash(string $urlHash): bool
    {
        return strpos($urlHash, self::PATTERN_HASH_PREFIX) === 0;
    }

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

    /**
     * Игнор одного блока/паттерна (html-ошибка, битая цель, форма…) на всём сайте.
     */
    public function ignorePattern(
        int $projectId,
        int $userId,
        string $code,
        string $groupHash,
        ?string $label = null
    ): ?SiteAuditIgnore {
        $groupHash = trim($groupHash);
        if ($groupHash === '') {
            return null;
        }
        $urlHash = self::patternUrlHash($code, $groupHash);
        if ($urlHash === '') {
            return null;
        }

        return $this->ignore(
            $projectId,
            $userId,
            $code,
            $urlHash,
            self::PATTERN_URL_PREFIX . mb_substr($groupHash, 0, 480),
            $label !== null && $label !== '' ? mb_substr($label, 0, 255) : null
        );
    }

    public function restorePattern(int $projectId, string $code, string $groupHash): int
    {
        $urlHash = self::patternUrlHash($code, trim($groupHash));
        if ($urlHash === '') {
            return 0;
        }

        return $this->restore($projectId, $code, $urlHash);
    }

    /**
     * @return array<string,true> group_hash => true
     */
    public function patternHashesForCode(int $projectId, string $code): array
    {
        if ($projectId < 1 || $code === '') {
            return [];
        }
        $rows = SiteAuditIgnore::query()
            ->where('project_id', $projectId)
            ->where('code', $code)
            ->where('url_hash', 'like', self::PATTERN_HASH_PREFIX . '%')
            ->get(['url']);
        $out = [];
        foreach ($rows as $row) {
            $url = (string) ($row->url ?? '');
            if (strpos($url, self::PATTERN_URL_PREFIX) === 0) {
                $sig = substr($url, strlen(self::PATTERN_URL_PREFIX));
                if ($sig !== '') {
                    $out[$sig] = true;
                }
            }
        }

        return $out;
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
     * Не сканируем все findings краула: code-wide игнор берём из counts_json,
     * тяжёлый COUNT — только по кодам с URL-игнором / fixed-заметками.
     *
     * @param  array<int>  $crawlIds
     * @return array<int, array{critical:int,other:int,important:int,warning:int,info:int}>
     */
    /**
     * Сколько findings «скрыто» игнором/«исправлено» по бакетам severity — для списка краулов.
     *
     * @param  list<int>  $crawlIds
     * @param  bool  $includeUrlLevel  URL-level ignores требуют join к findings;
     *                                 на /site-audit (20 краулов) выключаем — иначе 10–20 с.
     * @return array<int, array{critical:int,other:int,important:int,warning:int,info:int}>
     */
    public function hiddenBucketsByCrawlIds(array $crawlIds, bool $includeUrlLevel = true): array
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

        try {
            $crawlRows = DB::table('site_audit_crawls')
                ->whereIn('id', $crawlIds)
                ->get(['id', 'project_id', 'counts_json']);
        } catch (\Throwable $e) {
            return $out;
        }

        $projectIds = [];
        $crawlProject = [];
        foreach ($crawlRows as $row) {
            $pid = (int) ($row->project_id ?? 0);
            $cid = (int) $row->id;
            if ($pid > 0) {
                $projectIds[$pid] = $pid;
                $crawlProject[$cid] = $pid;
            }
        }
        $projectIds = array_values($projectIds);
        if ($projectIds === []) {
            return $out;
        }

        $ignores = SiteAuditIgnore::query()
            ->whereIn('project_id', $projectIds)
            ->get(['project_id', 'code', 'url_hash']);

        $notesReady = (new SiteAuditFindingNoteService())->tableReady();
        $notes = collect();
        if ($notesReady) {
            try {
                $notes = SiteAuditFindingNote::query()
                    ->whereIn('project_id', $projectIds)
                    ->where('status', SiteAuditFindingNote::STATUS_FIXED)
                    ->get(['project_id', 'code', 'url_hash']);
            } catch (\Throwable $e) {
                $notes = collect();
            }
        }

        $codeWide = []; // project_id => [code => true]
        $urlPairs = []; // "pid|code|hash" => true
        $urlHashes = [];
        $urlCodes = [];
        foreach ($ignores as $ig) {
            $hash = (string) ($ig->url_hash ?? '');
            if (self::isPatternUrlHash($hash)) {
                continue;
            }
            $pid = (int) $ig->project_id;
            $code = (string) $ig->code;
            if ($code === '') {
                continue;
            }
            if ($hash === '') {
                $codeWide[$pid][$code] = true;
            } else {
                $urlPairs[$pid . '|' . $code . '|' . $hash] = true;
                $urlHashes[$hash] = true;
                $urlCodes[$code] = true;
            }
        }
        foreach ($notes as $note) {
            $hash = (string) ($note->url_hash ?? '');
            if (self::isPatternUrlHash($hash) || $hash === '') {
                continue;
            }
            $code = (string) $note->code;
            if ($code === '') {
                continue;
            }
            $pid = (int) $note->project_id;
            $urlPairs[$pid . '|' . $code . '|' . $hash] = true;
            $urlHashes[$hash] = true;
            $urlCodes[$code] = true;
        }

        if ($codeWide === [] && $urlPairs === []) {
            return $out;
        }

        $severityByCode = [];
        foreach (config('site_audit.findings', []) as $code => $cfg) {
            if (! is_array($cfg)) {
                continue;
            }
            $sev = (string) ($cfg['severity'] ?? 'warning');
            if (isset($empty[$sev])) {
                $severityByCode[$code] = $sev;
            }
        }

        foreach ($crawlRows as $row) {
            $cid = (int) $row->id;
            $pid = (int) $row->project_id;
            $cw = $codeWide[$pid] ?? [];
            if ($cw === []) {
                continue;
            }
            $counts = json_decode((string) ($row->counts_json ?? ''), true);
            if (! is_array($counts)) {
                continue;
            }
            foreach ($cw as $code => $_) {
                $n = (int) ($counts[$code] ?? 0);
                if ($n < 1) {
                    continue;
                }
                $sev = $severityByCode[$code] ?? 'warning';
                if (! isset($out[$cid][$sev])) {
                    continue;
                }
                $out[$cid][$sev] += $n;
            }
        }

        if (! $includeUrlLevel || $urlPairs === []) {
            return $out;
        }

        // Lookup по url_hash (индекс), без EXISTS на всю таблицу findings.
        try {
            $rows = DB::table('site_audit_findings')
                ->whereIn('crawl_id', $crawlIds)
                ->whereIn('code', array_keys($urlCodes))
                ->whereIn('url_hash', array_keys($urlHashes))
                ->get(['crawl_id', 'code', 'url_hash', 'severity']);
        } catch (\Throwable $e) {
            return $out;
        }

        foreach ($rows as $row) {
            $cid = (int) $row->crawl_id;
            $pid = $crawlProject[$cid] ?? 0;
            $key = $pid . '|' . $row->code . '|' . $row->url_hash;
            if (! isset($urlPairs[$key])) {
                continue;
            }
            $sev = (string) $row->severity;
            if (! isset($out[$cid][$sev])) {
                continue;
            }
            $out[$cid][$sev]++;
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
