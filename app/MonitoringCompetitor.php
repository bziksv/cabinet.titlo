<?php

namespace App;

use App\Exceptions\MonitoringChangesDateCancelledException;
use App\Jobs\Monitoring\MonitoringChangesDateQueue;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use App\Support\HeavyDb;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MonitoringCompetitor extends Model
{
    protected $fillable = ['url'];

    public static function getCompetitors(array $request, $targetId): ?string
    {
        $project = MonitoringProject::findOrFail($request['projectId']);
        $keywordRows = MonitoringKeyword::where('monitoring_project_id', $request['projectId'])->get(['query'])->toArray();
        $allQueries = array_column($keywordRows, 'query');
        $totalKeywords = count($allQueries);
        $words = array_chunk($keywordRows, 100);
        $competitors = [];

        if ($request['region'] == '') {
            $days = MonitoringProject::getLastDates($project);
        } else {
            $days = MonitoringProject::getLastDate($project, $request['region']);
        }

        foreach ($days as $day) {
            foreach ($words as $keywords) {
                $queries = array_column($keywords, 'query');
                $results = HeavyDb::table(DB::raw('search_indices use index(search_indices_query_index, search_indices_lr_index, search_indices_position_index)'))
                    ->where('search_indices.lr', $day['engine']['lr'])
                    ->where('search_indices.position', '<=', 10)
                    ->whereDate('search_indices.created_at', $day['dateOnly'])
                    ->whereIn('search_indices.query', $queries)
                    ->orderBy('search_indices.id', 'desc')
                    ->get()
                    ->toArray();

                foreach ($results as $result) {
                    $host = parse_url(Common::domainFilter($result->url))['host'];
                    if (isset($request['targetDomain'])) {
                        if ($host === $request['targetDomain']) {
                            $competitors[$host]['urls'][$result->query][$day['engine']['engine']][] = [$day['engine']['location']['name'] => Common::domainFilter($result->url)];
                        }
                    } else {
                        $competitors[$host]['urls'][$day['engine']['engine']][$result->query][] = [$day['engine']['location']['name'] => Common::domainFilter($result->url)];
                    }
                }
            }
        }

        if (array_key_exists($project->url, $competitors)) {
            $competitors[$project->url]['mainPage'] = true;
        }

        foreach ($competitors as $key => $urls) {
            $total = 0;
            $yandex = [];
            $google = [];
            $uniqueQueries = [];

            foreach ($urls as $inf => $engines) {
                if ($inf !== 'urls') {
                    continue;
                }
                foreach ($engines as $engine => $words) {
                    foreach ($words as $k1 => $word) {
                        $uniqueQueries[$k1] = true;
                        if ($engine === 'yandex') {
                            foreach ($word as $info) {
                                $region = array_key_first($info);
                                if (isset($yandex[$region])) {
                                    $yandex[$region] += 1;
                                } else {
                                    $yandex[$region] = 1;
                                }
                            }
                        } else if ($engine === 'google') {
                            foreach ($word as $info) {
                                $region = array_key_first($info);
                                if (isset($google[$region])) {
                                    $google[$region] += 1;
                                } else {
                                    $google[$region] = 1;
                                }
                            }
                        }
                        $total += count($word);
                        $competitors[$key][$inf][$engine][$k1] = $word;
                    }
                }
            }

            $intersectionCount = count($uniqueQueries);
            $competitors[$key]['intersectionCount'] = $intersectionCount;
            $competitors[$key]['intersectionPct'] = $totalKeywords > 0
                ? round($intersectionCount / $totalKeywords * 100, 1)
                : 0;
            $competitors[$key]['visibility'] = $total;
            $competitors[$key]['visibilityYandex'] = $yandex;
            $competitors[$key]['visibilityGoogle'] = $google;
        }

        MonitoringCompetitorsResult::where('id', $targetId)->update([
            'result' => Common::compressArray($competitors, JSON_INVALID_UTF8_IGNORE),
            'state' => 'ready'
        ]);

        return json_encode($competitors, JSON_INVALID_UTF8_IGNORE);
    }

    /**
     * ТОП-% и средняя позиция — только для переданных доменов (страница таблицы).
     *
     * @param array<int, string> $domains
     * @return array<string, array<string, float|int>>
     */
    public static function computePageStats(array $request, array $domains): array
    {
        if ($domains === []) {
            return [];
        }

        $project = MonitoringProject::findOrFail($request['projectId']);
        $allQueries = MonitoringKeyword::where('monitoring_project_id', $request['projectId'])->pluck('query')->all();
        $totalKeywords = count($allQueries);

        if ($totalKeywords === 0) {
            return [];
        }

        $domainSet = array_fill_keys($domains, true);
        $positionsRaw = [];
        foreach ($domains as $domain) {
            $positionsRaw[$domain] = [];
        }

        $words = array_chunk($allQueries, 100);

        if ($request['region'] == '') {
            $days = MonitoringProject::getLastDates($project);
        } else {
            $days = MonitoringProject::getLastDate($project, $request['region']);
        }

        foreach ($days as $day) {
            foreach ($words as $keywordBatch) {
                $queries = array_slice($keywordBatch, 0);
                if ($queries === []) {
                    continue;
                }

                $results = HeavyDb::table(DB::raw('search_indices use index(search_indices_query_index, search_indices_lr_index, search_indices_position_index)'))
                    ->where('search_indices.lr', $day['engine']['lr'])
                    ->where('search_indices.position', '<=', 100)
                    ->whereDate('search_indices.created_at', $day['dateOnly'])
                    ->whereIn('search_indices.query', $queries)
                    ->orderBy('search_indices.id', 'desc')
                    ->get(['search_indices.url', 'search_indices.position', 'search_indices.query'])
                    ->toArray();

                foreach ($results as $result) {
                    try {
                        $host = parse_url(Common::domainFilter($result->url))['host'];
                    } catch (\Throwable $e) {
                        continue;
                    }

                    if (!isset($domainSet[$host])) {
                        continue;
                    }

                    $position = (int) $result->position;
                    $query = $result->query;

                    if (!isset($positionsRaw[$host][$query]) || $position < $positionsRaw[$host][$query]) {
                        $positionsRaw[$host][$query] = $position;
                    }
                }
            }
        }

        $stats = [];
        foreach ($domains as $domain) {
            $raw = $positionsRaw[$domain] ?? [];
            $positions = [];
            foreach ($allQueries as $query) {
                $positions[] = $raw[$query] ?? 101;
            }

            $intersectionCount = 0;
            foreach ($positions as $position) {
                if ($position <= 100) {
                    $intersectionCount++;
                }
            }

            $stats[$domain] = [
                'intersectionCount' => $intersectionCount,
                'intersectionPct' => round($intersectionCount / $totalKeywords * 100, 1),
                'top_3' => Common::percentHitIn(3, $positions, true),
                'top_10' => Common::percentHitIn(10, $positions, true),
                'top_100' => Common::percentHitIn(100, $positions, true),
                'avgPosition' => round(array_sum($positions) / $totalKeywords, 1),
            ];
        }

        return $stats;
    }

    public static function resolveSnapshot(int $projectId, $regionId): ?MonitoringPosition
    {
        return MonitoringPosition::query()
            ->select(DB::raw('monitoring_positions.*, DATE(monitoring_positions.created_at) as dateOnly'))
            ->join('monitoring_keywords', 'monitoring_keywords.id', '=', 'monitoring_positions.monitoring_keyword_id')
            ->where('monitoring_keywords.monitoring_project_id', $projectId)
            ->where('monitoring_positions.monitoring_searchengine_id', $regionId)
            ->orderBy('monitoring_positions.id', 'desc')
            ->with('engine')
            ->first();
    }

    public static function resolveLastDateForStatistics(array $request): ?array
    {
        if (!empty($request['dateOnly']) && !empty($request['lr'])) {
            return [
                'dateOnly' => $request['dateOnly'],
                'engine' => ['lr' => $request['lr']],
            ];
        }

        $lastDate = MonitoringProject::getLastDateByWords($request['keywords'], $request['region']);
        if (!$lastDate) {
            return null;
        }

        $lr = $lastDate->engine->lr ?? ($lastDate['engine']['lr'] ?? null);
        if ($lr === null) {
            return null;
        }

        return [
            'dateOnly' => $lastDate['dateOnly'],
            'engine' => ['lr' => $lr],
        ];
    }

    public static function calculateStatistics(array $request): array
    {
        $lastDate = self::resolveLastDateForStatistics($request);

        return self::calculateStatisticsChunk(
            $request['keywords'],
            $request['competitors'],
            $lastDate
        );
    }

    public static function calculateStatisticsBulk(array $request, ?int $batchSize = null, ?callable $onChunk = null): array
    {
        $keywords = $request['keywords'];
        $competitors = $request['competitors'];
        $lastDate = self::resolveLastDateForStatistics($request);
        $batchSize = max(100, $batchSize ?? (int) config('cabinet-monitoring.competitors_positions_bulk_chunk', 1000));
        $chunks = array_chunk($keywords, $batchSize);
        $visibilityArray = [];
        $mergedPositions = array_fill_keys($competitors, []);

        foreach ($chunks as $chunkIndex => $chunk) {
            $chunkResult = self::calculateStatisticsChunk($chunk, $competitors, $lastDate, false);
            foreach ($chunkResult['visibility'] as $query => $row) {
                $visibilityArray[$query] = $row;
            }
            foreach ($competitors as $competitor) {
                if (!empty($chunkResult['positionsByCompetitor'][$competitor])) {
                    $mergedPositions[$competitor] += $chunkResult['positionsByCompetitor'][$competitor];
                }
            }

            if ($onChunk) {
                $onChunk($chunkIndex + 1, count($chunks));
            }
        }

        $totalWords = max(1, count($keywords));
        $competitorStatistics = [];

        foreach ($competitors as $competitor) {
            $competitorStatistics[$competitor] = self::finalizeDomainStatistics(
                $mergedPositions[$competitor],
                $totalWords
            );
        }

        return [
            'visibility' => $visibilityArray,
            'statistics' => $competitorStatistics,
        ];
    }

    protected static function finalizeDomainStatistics(array $positions, int $totalWords): array
    {
        $sum = array_sum($positions);

        return [
            'sum' => $sum,
            'avg' => $sum / $totalWords,
            'top_3' => Common::percentHitIn(3, $positions, true),
            'top_10' => Common::percentHitIn(10, $positions, true),
            'top_30' => Common::percentHitIn(30, $positions, true),
            'top_50' => Common::percentHitIn(50, $positions, true),
            'top_100' => Common::percentHitIn(100, $positions, true),
        ];
    }

    protected static function calculateStatisticsChunk(
        array $keywords,
        array $competitors,
        ?array $lastDate,
        bool $computeChunkStats = true
    ): array {
        $visibilityArray = [];
        $queries = array_column($keywords, 'query');
        $competitorByDomain = [];
        foreach ($competitors as $competitor) {
            $competitorByDomain[Common::domainFilter($competitor)] = $competitor;
        }

        foreach ($queries as $keyword) {
            foreach ($competitors as $competitor) {
                $visibilityArray[$keyword][$competitor] = 0;
            }
        }

        if ($lastDate && $queries !== []) {
            $serpDepth = max(10, (int) config('cabinet-monitoring.competitors_positions_serp_depth', 100));
            $recordLimit = count($queries) * $serpDepth;
            $records = HeavyDb::table(DB::raw('search_indices use index(search_indices_query_index, search_indices_lr_index, search_indices_position_index)'))
                ->where('search_indices.lr', $lastDate['engine']['lr'])
                ->whereDate('search_indices.created_at', $lastDate['dateOnly'])
                ->whereIn('search_indices.query', $queries)
                ->orderBy('search_indices.id', 'desc')
                ->limit($recordLimit)
                ->get(['search_indices.url', 'search_indices.position', 'search_indices.query'])
                ->toArray();

            foreach ($records as $record) {
                try {
                    $host = parse_url($record->url, PHP_URL_HOST);
                    if (!$host) {
                        continue;
                    }
                    $url = Common::domainFilter($host);
                    if (!isset($competitorByDomain[$url])) {
                        continue;
                    }
                    $competitorKey = $competitorByDomain[$url];
                    if ($visibilityArray[$record->query][$competitorKey] !== 0) {
                        continue;
                    }
                    $visibilityArray[$record->query][$competitorKey] = $record->position;
                } catch (\Throwable $e) {
                }
            }
        }

        $positionsByCompetitor = [];
        foreach ($competitors as $competitor) {
            $positionsByCompetitor[$competitor] = [];
        }

        foreach ($visibilityArray as $query => $positions) {
            foreach ($competitors as $competitor) {
                $positionsByCompetitor[$competitor][$query] = $positions[$competitor] === 0 ? 101 : $positions[$competitor];
            }
        }

        if (!$computeChunkStats) {
            return [
                'visibility' => $visibilityArray,
                'positionsByCompetitor' => $positionsByCompetitor,
            ];
        }

        $competitorStatistics = [];
        foreach ($positionsByCompetitor as $competitor => $competitorPositions) {
            $competitorStatistics[$competitor] = [
                'positions' => $competitorPositions,
                'sum' => array_sum($competitorPositions),
                'top_3' => Common::percentHitIn(3, $competitorPositions),
                'top_10' => Common::percentHitIn(10, $competitorPositions),
                'top_30' => Common::percentHitIn(30, $competitorPositions),
                'top_50' => Common::percentHitIn(50, $competitorPositions),
                'top_100' => Common::percentHitIn(100, $competitorPositions),
            ];
        }

        return [
            'visibility' => $visibilityArray,
            'statistics' => $competitorStatistics,
        ];
    }

    /**
     * Диапазон «DD-MM-YYYY - DD-MM-YYYY» → [Y-m-d, Y-m-d].
     */
    public static function parseCompetitorsDateRange(string $dateRange): array
    {
        $parts = array_map('trim', explode(' - ', $dateRange, 2));
        if (count($parts) < 2 || $parts[0] === '' || $parts[1] === '') {
            throw new \InvalidArgumentException('Invalid date range: ' . $dateRange);
        }

        return [
            Carbon::createFromFormat('d-m-Y', $parts[0])->format('Y-m-d'),
            Carbon::createFromFormat('d-m-Y', $parts[1])->format('Y-m-d'),
        ];
    }

    /**
     * Даты снимков мониторинга в периоде (не каждый календарный день).
     */
    public static function snapshotDatesInRange(int $regionId, array $keywordIds, string $startDate, string $endDate): array
    {
        if ($keywordIds === []) {
            return [];
        }

        return DB::table('monitoring_positions')
            ->where('monitoring_searchengine_id', $regionId)
            ->whereIn('monitoring_keyword_id', $keywordIds)
            ->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as dateOnly')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('dateOnly')
            ->pluck('dateOnly')
            ->all();
    }

    /**
     * Оценка объёма отчёта «динамика по датам» перед запуском.
     */
    protected static function changesDateChunkCount(int $keywordCount, ?int $batchSize = null): int
    {
        $batchSize = max(100, $batchSize ?? (int) config('cabinet-monitoring.competitors_positions_bulk_chunk', 1000));

        return max(1, (int) ceil(max(1, $keywordCount) / $batchSize));
    }

    protected static function changesDateProgressTotal(int $snapshotCount, int $keywordCount): int
    {
        return max(1, $snapshotCount * self::changesDateChunkCount($keywordCount));
    }

    public static function normalizeChangesDateCompetitorInput($input): ?array
    {
        if ($input === null || $input === '') {
            return null;
        }

        if (!is_array($input)) {
            $input = [$input];
        }

        $urls = array_values(array_unique(array_filter(array_map('trim', array_map('strval', $input)))));

        return $urls === [] ? null : $urls;
    }

    public static function changesDateCompetitorsSelectionKey(?array $selected): string
    {
        if ($selected === null || $selected === []) {
            return '__all__';
        }

        $urls = array_values(array_unique($selected));
        sort($urls);

        return implode('|', $urls);
    }

    public static function resolveChangesDateCompetitors(int $projectId, ?array $selectedUrls = null): array
    {
        $project = MonitoringProject::findOrFail($projectId, ['id', 'url']);
        $allowed = self::where('monitoring_project_id', $projectId)->pluck('url')->all();

        if ($selectedUrls === null || $selectedUrls === []) {
            $competitors = $allowed;
        } else {
            $competitors = array_values(array_intersect($selectedUrls, $allowed));
        }

        array_unshift($competitors, $project->url);
        $competitors = array_values(array_unique($competitors));

        return $competitors;
    }

    public static function changesDateCompetitorsSummary(string $ownUrl, ?array $selected, int $competitorCount): string
    {
        if ($selected === null || $selected === []) {
            return __('Monitoring comp dynamics competitors all', [
                'own' => $ownUrl,
                'count' => $competitorCount,
            ]);
        }

        return __('Monitoring comp dynamics competitors selected', [
            'own' => $ownUrl,
            'count' => count($selected),
        ]);
    }

    public static function estimateChangesByDateRange(int $projectId, int $regionId, string $dateRange): array
    {
        [$startDate, $endDate] = self::parseCompetitorsDateRange($dateRange);
        $keywordIds = MonitoringKeyword::where('monitoring_project_id', $projectId)->pluck('id')->all();
        $keywordCount = count($keywordIds);
        $snapshots = count(self::snapshotDatesInRange($regionId, $keywordIds, $startDate, $endDate));
        $calendarDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;
        $secondsPerSnapshot = max(5, (int) config('cabinet-monitoring.competitors_changes_dates_seconds_per_snapshot', 25));
        $estimatedSeconds = $snapshots * $secondsPerSnapshot;
        $largeThreshold = max(5, (int) config('cabinet-monitoring.competitors_changes_dates_large_snapshots', 15));

        return [
            'snapshots' => $snapshots,
            'progressTotal' => self::changesDateProgressTotal($snapshots, $keywordCount),
            'calendarDays' => $calendarDays,
            'estimatedSeconds' => $estimatedSeconds,
            'estimatedMinutes' => max(1, (int) ceil($estimatedSeconds / 60)),
            'large' => $snapshots >= $largeThreshold,
        ];
    }

    public static function updateChangesDateProgress(MonitoringChangesDate $record, int $done, int $total): void
    {
        self::abortIfChangesDateReportCancelled((int) $record->id);

        $requestData = json_decode($record->request, true) ?: [];
        $requestData['progress_done'] = $done;
        $requestData['progress_total'] = $total;

        $record->update([
            'request' => json_encode($requestData, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * Отчёт «динамика по датам» — bulk по дням снимка, без fan-out в очередь helper.
     */
    public static function calculateChangesByDateRange(
        int $projectId,
        int $regionId,
        string $dateRange,
        ?callable $onProgress = null,
        ?array $competitors = null
    ): array {
        [$startDate, $endDate] = self::parseCompetitorsDateRange($dateRange);

        $project = MonitoringProject::findOrFail($projectId, ['id', 'url']);
        $lr = MonitoringSearchengine::where('id', $regionId)->value('lr');
        if ($lr === null) {
            return [];
        }

        $keywords = MonitoringKeyword::where('monitoring_project_id', $projectId)
            ->get(['id', 'query'])
            ->toArray();
        $keywordIds = array_column($keywords, 'id');

        if ($competitors === null) {
            $competitors = self::resolveChangesDateCompetitors($projectId, null);
        }

        $snapshotDates = self::snapshotDatesInRange($regionId, $keywordIds, $startDate, $endDate);
        $response = [];
        $totalSnapshots = count($snapshotDates);
        $chunksPerSnapshot = self::changesDateChunkCount(count($keywords));
        $totalSteps = self::changesDateProgressTotal($totalSnapshots, count($keywords));

        if ($onProgress) {
            $onProgress(0, $totalSteps);
        }

        foreach ($snapshotDates as $snapshotIndex => $dateOnly) {
            $statistics = self::calculateStatisticsBulk([
                'keywords' => $keywords,
                'competitors' => $competitors,
                'dateOnly' => $dateOnly,
                'lr' => $lr,
            ], null, function (int $chunkDone, int $chunkTotal) use ($snapshotIndex, $chunksPerSnapshot, $totalSteps, $onProgress) {
                if (!$onProgress) {
                    return;
                }

                $doneSteps = min($totalSteps, ($snapshotIndex * $chunksPerSnapshot) + $chunkDone);
                $onProgress($doneSteps, $totalSteps);
            })['statistics'];

            foreach ($statistics as $domain => $stats) {
                $response[$dateOnly][$domain] = [
                    'avg' => round($stats['avg'], 2),
                    'top_3' => $stats['top_3'],
                    'top_10' => $stats['top_10'],
                    'top_100' => $stats['top_100'],
                ];
            }
        }

        return $response;
    }

    public static function abortIfChangesDateReportCancelled(int $recordId): void
    {
        if (!MonitoringChangesDate::where('id', $recordId)->exists()) {
            throw new MonitoringChangesDateCancelledException('Report removed');
        }
    }

    public static function cancelQueuedChangesDateReport(int $recordId): int
    {
        $removed = 0;

        $jobs = DB::table('jobs')
            ->where('queue', 'monitoring_change_dates')
            ->get(['id', 'payload']);

        foreach ($jobs as $job) {
            if (!self::jobPayloadMatchesChangesDateRecord((string) $job->payload, $recordId)) {
                continue;
            }

            DB::table('jobs')->where('id', $job->id)->delete();
            $removed++;
        }

        return $removed;
    }

    protected static function jobPayloadMatchesChangesDateRecord(string $payload, int $recordId): bool
    {
        if (strpos($payload, 'MonitoringChangesDateQueue') === false) {
            return false;
        }

        return (bool) preg_match('/s:2:"id";i:' . $recordId . ';/', $payload)
            || (bool) preg_match('/"id":' . $recordId . '[,}]/', $payload);
    }

    public static function projectHasActiveChangesDateReport(int $projectId): bool
    {
        $staleMinutes = max(5, (int) config('cabinet-monitoring.competitors_changes_dates_stale_minutes', 20));

        return MonitoringChangesDate::query()
            ->where('monitoring_project_id', $projectId)
            ->where(function ($query) use ($staleMinutes) {
                $query->where('state', 'in queue')
                    ->orWhere(function ($query) use ($staleMinutes) {
                        $query->where('state', 'in process')
                            ->where('updated_at', '>=', now()->subMinutes($staleMinutes));
                    });
            })
            ->exists();
    }

    public static function pendingQueuePosition(MonitoringChangesDate $record): int
    {
        if ($record->state !== 'pending') {
            return 0;
        }

        return (int) MonitoringChangesDate::query()
            ->where('monitoring_project_id', $record->monitoring_project_id)
            ->where('state', 'pending')
            ->where('id', '<=', $record->id)
            ->count();
    }

    public static function pendingQueueTotal(int $projectId): int
    {
        return (int) MonitoringChangesDate::query()
            ->where('monitoring_project_id', $projectId)
            ->where('state', 'pending')
            ->count();
    }

    public static function dispatchChangesDateReport(MonitoringChangesDate $record, array $payload): void
    {
        $record->update(['state' => 'in queue']);

        MonitoringChangesDateQueue::dispatch($record, $payload)->onQueue('monitoring_change_dates');
    }

    public static function tryDispatchNextChangesDateReport(int $projectId): void
    {
        if (self::projectHasActiveChangesDateReport($projectId)) {
            return;
        }

        $next = MonitoringChangesDate::query()
            ->where('monitoring_project_id', $projectId)
            ->where('state', 'pending')
            ->orderBy('id')
            ->first();

        if (!$next) {
            return;
        }

        $payload = json_decode($next->request, true) ?: [];
        self::dispatchChangesDateReport($next, $payload);
    }
}
