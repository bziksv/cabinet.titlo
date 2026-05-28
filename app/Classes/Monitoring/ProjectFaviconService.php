<?php

namespace App\Classes\Monitoring;

use App\MonitoringProject;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Фавиконка проекта: скачать лучший вариант, сохранить в storage, привязать к monitoring_projects.
 */
class ProjectFaviconService
{
    private const STORAGE_DIR = 'monitoring-favicons';

    /** Слишком мелкий PNG — ошибка загрузки (16×16 .ico после resize ≈ 500+ B). */
    private const MIN_FAVICON_BYTES = 500;

    /** Google s2 default tile «буква на сером» после быстрого fill. */
    private const GOOGLE_LETTER_STUB_MIN_BYTES = 1290;

    private const GOOGLE_LETTER_STUB_MAX_BYTES = 1345;

    /** gstatic 16×16 → PNG 128×128 часто 600–750 B (ekb.1-diesel.ru и др.). */
    private const AGGREGATOR_MICRO_MAX_BYTES = 1100;

    /** @var ProjectFaviconFetcher */
    private $fetcher;

    public function __construct(?ProjectFaviconFetcher $fetcher = null)
    {
        $this->fetcher = $fetcher ?: new ProjectFaviconFetcher();
    }

    /**
     * Какой project id отдавать в /monitoring-v2/favicon (свой или донор с тем же хостом).
     *
     * @param array<string, MonitoringProject> $donorsByHost
     *
     * @return array{id: int, v: int}|null
     */
    public function faviconDisplayMeta(MonitoringProject $project, array $donorsByHost = []): ?array
    {
        $best = $this->bestFaviconSource($project, $donorsByHost);
        if ($best === null || $this->isWeakFavicon($best)) {
            return null;
        }

        return ['id' => (int) $best->id, 'v' => $this->faviconTimestamp($best)];
    }

    /**
     * @param array<string, MonitoringProject> $donorsByHost
     */
    public function displayUrl(MonitoringProject $project, array $donorsByHost = []): ?string
    {
        $best = $this->bestFaviconSource($project, $donorsByHost);
        if ($best === null || $this->isWeakFavicon($best)) {
            return null;
        }

        return $this->faviconRouteUrl((int) $best->id, $best);
    }

    public function listFaviconUrl(MonitoringProject $project): ?string
    {
        if ($this->absolutePath($project) === null) {
            return null;
        }

        return $this->faviconRouteUrl((int) $project->id, $project);
    }

    /**
     * @return array{id: int, favicon_src_project_id: int, favicon_v: int, favicon_url: string|null}
     */
    /**
     * @param array<string, MonitoringProject> $donorsByHost
     */
    public function faviconUpdatePayload(MonitoringProject $project, array $donorsByHost = []): array
    {
        $best = $this->bestFaviconSource($project, $donorsByHost);
        if ($best === null || $this->isWeakFavicon($best)) {
            return [
                'id' => (int) $project->id,
                'favicon_src_project_id' => null,
                'favicon_v' => null,
                'favicon_url' => null,
            ];
        }

        return [
            'id' => (int) $project->id,
            'favicon_src_project_id' => (int) $best->id,
            'favicon_v' => $this->faviconTimestamp($best),
            'favicon_url' => $this->faviconRouteUrl((int) $best->id, $best),
        ];
    }

    /**
     * URL для &lt;img&gt;: через Laravel (response-&gt;file), не прямой /storage/ —
     * на FastPanel статика из public/storage часто 403 при рабочем файле в storage/app/public.
     */
    private function faviconRouteUrl(int $projectId, MonitoringProject $cacheBustSource): string
    {
        $url = '/monitoring-v2/favicon?project=' . $projectId;
        $ts = $this->faviconTimestamp($cacheBustSource);

        return $ts > 0 ? $url . '&v=' . $ts : $url;
    }

    /**
     * Импорт PNG с legacy-кабинета (lk) — только из artisan, не из HTTP/.env.
     */
    public function importFaviconFromLegacyBase(MonitoringProject $project, string $legacyBaseUrl): bool
    {
        if ($this->absolutePath($project) !== null) {
            return true;
        }

        if (!$project->favicon_path) {
            return false;
        }

        $relative = ltrim(str_replace('\\', '/', (string) $project->favicon_path), '/');
        if ($relative === '') {
            return false;
        }

        $remote = rtrim($legacyBaseUrl, '/');
        if ($remote === '' || strpos($remote, 'http') !== 0) {
            return false;
        }

        $url = $remote . '/storage/' . $relative;
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'DatagonCabinetFaviconImport/1',
                'follow_location' => 1,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $bytes = @file_get_contents($url, false, $ctx);
        if ($bytes === false || !$this->isAcceptableFaviconBytes(strlen($bytes))) {
            return false;
        }

        Storage::disk('public')->put($relative, $bytes);

        return is_file(storage_path('app/public/' . $relative));
    }

    /**
     * @param iterable<MonitoringProject> $projects
     *
     * @return array<string, MonitoringProject>
     */
    public function buildHostDonorMap(iterable $projects): array
    {
        $donorsByHost = [];
        foreach ($projects as $project) {
            if (!$project instanceof MonitoringProject) {
                continue;
            }
            $host = $this->fetcher->normalizeHost((string) $project->url);
            if ($host === null || $this->absolutePath($project) === null) {
                continue;
            }
            if (
                !isset($donorsByHost[$host])
                || $this->faviconQualityRank($project) > $this->faviconQualityRank($donorsByHost[$host])
            ) {
                $donorsByHost[$host] = $project;
            }
        }

        return $donorsByHost;
    }

    public function faviconFileBytes(MonitoringProject $project): int
    {
        $path = $this->absolutePath($project);

        return $path ? (int) filesize($path) : 0;
    }

    public function isWeakFavicon(MonitoringProject $project): bool
    {
        $bytes = $this->faviconFileBytes($project);

        return $bytes === 0 || !$this->isAcceptableFaviconBytes($bytes);
    }

    public function isAcceptableFaviconBytes(int $bytes): bool
    {
        if ($bytes < self::MIN_FAVICON_BYTES || $bytes > 24_000) {
            return false;
        }

        if ($bytes < self::AGGREGATOR_MICRO_MAX_BYTES) {
            return false;
        }

        return !($bytes >= self::GOOGLE_LETTER_STUB_MIN_BYTES && $bytes <= self::GOOGLE_LETTER_STUB_MAX_BYTES);
    }

    public function faviconQualityRank(MonitoringProject $project): int
    {
        $bytes = $this->faviconFileBytes($project);
        if ($bytes === 0 || $this->isWeakFavicon($project)) {
            return 0;
        }
        if ($bytes > 20_000) {
            return 500;
        }

        return 10_000 + $bytes;
    }

    /**
     * Лучший PNG для хоста: свой или донор из портфеля (по размеру файла, не по дате).
     *
     * @param array<string, MonitoringProject> $donorsByHost
     */
    private function bestFaviconSource(MonitoringProject $project, array $donorsByHost): ?MonitoringProject
    {
        $host = $this->fetcher->normalizeHost((string) $project->url);
        $best = null;
        $bestRank = 0;

        foreach ([$project, $host && isset($donorsByHost[$host]) ? $donorsByHost[$host] : null] as $candidate) {
            if (!$candidate instanceof MonitoringProject || $this->absolutePath($candidate) === null) {
                continue;
            }
            $rank = $this->faviconQualityRank($candidate);
            if ($rank > $bestRank) {
                $bestRank = $rank;
                $best = $candidate;
            }
        }

        return $best;
    }

    public function publicUrl(MonitoringProject $project): ?string
    {
        if (!$project->favicon_path) {
            return null;
        }

        $url = cabinet_storage_url($project->favicon_path);
        if ($url === null) {
            return null;
        }

        if ($project->favicon_updated_at) {
            $ts = $project->favicon_updated_at instanceof Carbon
                ? $project->favicon_updated_at->timestamp
                : strtotime((string) $project->favicon_updated_at);
            if ($ts) {
                return $url . (strpos($url, '?') === false ? '?' : '&') . 'v=' . $ts;
            }
        }

        return $url;
    }

    public function absolutePath(MonitoringProject $project): ?string
    {
        if (!$project->favicon_path) {
            return null;
        }

        $path = storage_path('app/public/' . ltrim($project->favicon_path, '/'));

        return is_file($path) ? $path : null;
    }

    public function needsRefresh(MonitoringProject $project): bool
    {
        $host = $this->fetcher->normalizeHost((string) $project->url);
        if ($host === null) {
            return false;
        }

        if (!$project->favicon_path || $project->favicon_host !== $host) {
            return true;
        }

        if ($this->absolutePath($project) === null) {
            return true;
        }

        return $this->isWeakFavicon($project);
    }

    public function refresh(MonitoringProject $project, bool $force = false, bool $fast = false): bool
    {
        $host = $this->fetcher->normalizeHost((string) $project->url);
        if ($host === null) {
            return false;
        }

        if (!$force && !$this->needsRefresh($project)) {
            return $this->absolutePath($project) !== null;
        }

        if ($this->copyFaviconFromSibling($project, $host)) {
            return true;
        }

        $png = $this->fetchPngAvoidingPlaceholders($host, $fast);
        if ($png === null) {
            return false;
        }

        $relative = self::STORAGE_DIR . '/' . $project->id . '.png';
        Storage::disk('public')->put($relative, $png);

        $project->favicon_path = $relative;
        $project->favicon_host = $host;
        $project->favicon_updated_at = Carbon::now();
        $project->save();

        return true;
    }

    /**
     * Порционная догрузка фавиконок для списка v2.
     *
     * @return array{rebuilt: int, pending: int, updates: array<int, array{id: int, favicon_url: string|null}>}
     */
    /**
     * Скопировать иконки внутри набора проектов (один аккаунт / один list).
     *
     * @param iterable<MonitoringProject>|Collection<MonitoringProject> $projects
     */
    /**
     * @param iterable<MonitoringProject> $projects
     *
     * @return array{count: int, updates: array<int, array{id: int, favicon_url: string|null}>}
     */
    public function propagateMissingFromBatch(iterable $projects): array
    {
        $list = [];
        foreach ($projects as $project) {
            if ($project instanceof MonitoringProject) {
                $list[] = $project;
            }
        }

        if ($list === []) {
            return ['count' => 0, 'updates' => []];
        }

        $donorsByHost = $this->buildHostDonorMap($list);

        $copied = 0;
        $updates = [];
        foreach ($list as $project) {
            $host = $this->fetcher->normalizeHost((string) $project->url);
            if ($host === null || !isset($donorsByHost[$host])) {
                continue;
            }
            $donor = $donorsByHost[$host];
            if ((int) $donor->id === (int) $project->id) {
                continue;
            }
            $shouldCopy = $this->needsRefresh($project)
                || (
                    $this->isWeakFavicon($project)
                    && $this->faviconQualityRank($donor) > $this->faviconQualityRank($project)
                );
            if (!$shouldCopy) {
                continue;
            }
            if ($this->copyFaviconFromDonor($project, $donor, $host)) {
                $copied++;
                $updates[] = $this->faviconUpdatePayload($project, $donorsByHost);
            }
        }

        return ['count' => $copied, 'updates' => $updates];
    }

    public function fillMissingBatch(iterable $projects, int $limit, bool $force = false): array
    {
        $limit = max(1, min(3, $limit));
        $projectIds = [];
        $all = [];
        foreach ($projects as $project) {
            if ($project instanceof MonitoringProject) {
                $all[] = $project;
            }
        }
        $propagateResult = $this->propagateMissingFromBatch($all);
        $propagated = $propagateResult['count'];
        $updates = $propagateResult['updates'];

        $toRefresh = [];
        foreach ($all as $project) {
            $projectIds[] = (int) $project->id;
            if ($force || $this->needsRefresh($project)) {
                $toRefresh[] = $project;
            }
        }

        if ($toRefresh === []) {
            return [
                'rebuilt' => 0,
                'pending' => 0,
                'updates' => [],
                'propagated' => $propagated,
            ];
        }

        $rebuilt = 0;
        $timedOut = false;
        $wallStart = microtime(true);
        $wallMs = 12000;
        $donorsByHost = $this->buildHostDonorMap($all);

        foreach (array_slice($toRefresh, 0, $limit) as $project) {
            if ((microtime(true) - $wallStart) * 1000 >= $wallMs) {
                $timedOut = true;
                break;
            }
            $useFast = !$this->isWeakFavicon($project);
            if ($this->refresh($project, true, $useFast)) {
                $updates[] = $this->faviconUpdatePayload($project, $donorsByHost);
                $rebuilt++;
            }
        }

        return [
            'rebuilt' => $rebuilt,
            'pending' => $this->countPendingInIds($projectIds),
            'updates' => $updates,
            'propagated' => $propagated,
            'timed_out' => $timedOut,
            'wall_ms' => (int) round((microtime(true) - $wallStart) * 1000),
        ];
    }

    /**
     * Скопировать PNG с другого проекта с тем же хостом (дубли URL в портфеле).
     */
    private function copyFaviconFromSibling(MonitoringProject $project, string $host): bool
    {
        $donor = $this->findDonorProject((int) $project->id, $host);

        return $donor !== null && $this->copyFaviconFromDonor($project, $donor, $host);
    }

    private function findDonorProject(int $excludeId, string $host): ?MonitoringProject
    {
        $candidates = MonitoringProject::query()
            ->where('id', '!=', $excludeId)
            ->whereNotNull('favicon_path')
            ->where(function ($query) use ($host) {
                $query->where('favicon_host', $host)
                    ->orWhere('url', $host)
                    ->orWhere('url', 'like', $host . '/%')
                    ->orWhere('url', 'like', 'http://' . $host . '%')
                    ->orWhere('url', 'like', 'https://' . $host . '%');
            })
            ->limit(40)
            ->get();

        $best = null;
        $bestRank = 0;
        foreach ($candidates as $candidate) {
            if ($this->fetcher->normalizeHost((string) $candidate->url) !== $host) {
                continue;
            }
            if ($this->absolutePath($candidate) === null || $this->isWeakFavicon($candidate)) {
                continue;
            }
            $rank = $this->faviconQualityRank($candidate);
            if ($rank > $bestRank) {
                $bestRank = $rank;
                $best = $candidate;
            }
        }

        return $best;
    }

    private function copyFaviconFromDonor(MonitoringProject $project, MonitoringProject $donor, string $host): bool
    {
        $srcPath = $this->absolutePath($donor);
        if ($srcPath === null) {
            return false;
        }

        $bytes = @file_get_contents($srcPath);
        if ($bytes === false || !$this->isAcceptableFaviconBytes(strlen($bytes))) {
            return false;
        }

        $relative = self::STORAGE_DIR . '/' . $project->id . '.png';
        Storage::disk('public')->put($relative, $bytes);

        $project->favicon_path = $relative;
        $project->favicon_host = $host;
        $project->favicon_updated_at = Carbon::now();
        $project->save();

        return true;
    }

    private function faviconTimestamp(MonitoringProject $project): int
    {
        if (!$project->favicon_updated_at) {
            return 0;
        }
        if ($project->favicon_updated_at instanceof Carbon) {
            return (int) $project->favicon_updated_at->timestamp;
        }

        $ts = strtotime((string) $project->favicon_updated_at);

        return $ts ? (int) $ts : 0;
    }

    /**
     * Не сохранять «буквенные» PNG из быстрого Google; при слабом результате — полный обход.
     */
    private function fetchPngAvoidingPlaceholders(string $host, bool $fast): ?string
    {
        $png = $this->fetcher->fetchBestPng($host, $fast);
        if ($png !== null && $this->isAcceptableFaviconBytes(strlen($png))) {
            return $png;
        }

        if ($fast) {
            $png = $this->fetcher->fetchBestPng($host, false);
            if ($png !== null && $this->isAcceptableFaviconBytes(strlen($png))) {
                return $png;
            }
        }

        return null;
    }

    /**
     * Проекты портфеля с тем же хостом, что и у $project (для копирования иконки).
     *
     * @param iterable<MonitoringProject> $portfolio
     *
     * @return MonitoringProject[]
     */
    public function projectsWithSameHost(MonitoringProject $project, iterable $portfolio): array
    {
        $host = $this->fetcher->normalizeHost((string) $project->url);
        if ($host === null) {
            return [];
        }

        $out = [];
        foreach ($portfolio as $item) {
            if (
                $item instanceof MonitoringProject
                && $this->fetcher->normalizeHost((string) $item->url) === $host
            ) {
                $out[] = $item;
            }
        }

        return $out;
    }

    /**
     * @param int[] $projectIds
     */
    private function countPendingInIds(array $projectIds): int
    {
        if ($projectIds === []) {
            return 0;
        }

        $pending = 0;
        foreach (MonitoringProject::query()->whereIn('id', $projectIds)->get([
            'id',
            'url',
            'favicon_path',
            'favicon_host',
            'favicon_updated_at',
        ]) as $project) {
            if ($this->needsRefresh($project)) {
                $pending++;
            }
        }

        return $pending;
    }

    public function clear(MonitoringProject $project): void
    {
        if ($project->favicon_path) {
            Storage::disk('public')->delete($project->favicon_path);
        }

        $project->favicon_path = null;
        $project->favicon_host = null;
        $project->favicon_updated_at = null;
        $project->save();
    }
}
