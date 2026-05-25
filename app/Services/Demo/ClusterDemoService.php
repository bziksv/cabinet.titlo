<?php

namespace App\Services\Demo;

use App\Cluster;
use App\ClusterConfigurationClassic;
use App\ClusterQueue;
use App\ClusterResults;
use App\Jobs\Cluster\StartClusterAnalyseQueue;
use App\Support\ClusterProgress;
use App\Support\ClusterQueues;
use App\Support\YandexLrRegions;
use App\User;
use Illuminate\Support\Facades\Cache;

class ClusterDemoService
{
    public const MODULE = 'klasterizator-klyuchevykh-slov';

    private const CACHE_PREFIX = 'demo_cluster:';

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return config('cabinet-cluster.demo', []);
    }

    /**
     * @return array<int, array{id: string, label: string}>
     */
    public static function regionsForUi(): array
    {
        $cfg = self::config();
        $ids = $cfg['allowed_region_ids'] ?? ['213', '2', '193'];
        $out = [];
        foreach ($ids as $id) {
            $id = (string) $id;
            $item = YandexLrRegions::find($id);
            $out[] = [
                'id' => $id,
                'label' => $item['name'] ?? $item['text'] ?? $id,
            ];
        }

        return $out;
    }

    /**
     * @param array{phrases?: string, region_id?: string, clustering_level?: string} $input
     * @return array{ok: true, payload: array<string, mixed>}|array{ok: false, status: int, error: string, message: string}
     */
    public static function validateRun(array $input): array
    {
        $cfg = self::config();
        $raw = str_replace("\r", '', (string) ($input['phrases'] ?? ''));
        $lines = array_values(array_filter(array_map('trim', explode("\n", $raw)), static function ($line) {
            return $line !== '';
        }));
        $lines = array_values(array_unique($lines));

        $min = (int) ($cfg['min_phrases'] ?? 3);
        $max = (int) ($cfg['max_phrases'] ?? 10);

        if ($lines === []) {
            return self::fail(422, 'validation', 'Введите ключевые фразы — по одной на строку');
        }
        if (count($lines) < $min) {
            return self::fail(422, 'validation', sprintf('В демо минимум %d фразы', $min));
        }
        if (count($lines) > $max) {
            return self::fail(
                422,
                'validation',
                sprintf('В демо до %d фраз за запуск. В кабинете — сотни и тысячи.', $max)
            );
        }

        foreach ($lines as $line) {
            if (mb_strlen($line) > 200) {
                return self::fail(422, 'validation', 'Слишком длинная фраза (до 200 символов в демо)');
            }
        }

        $regionId = trim((string) ($input['region_id'] ?? ''));
        $allowedRegions = array_column(self::regionsForUi(), 'id');
        if ($regionId === '' || !in_array($regionId, $allowedRegions, true)) {
            return self::fail(422, 'validation', 'Выберите регион из списка');
        }

        $level = trim((string) ($input['clustering_level'] ?? 'soft'));
        $allowedLevels = array_column($cfg['clustering_levels'] ?? [], 'value');
        if (!in_array($level, $allowedLevels, true)) {
            $level = 'soft';
        }

        return [
            'ok' => true,
            'payload' => [
                'phrases' => $lines,
                'region_id' => $regionId,
                'clustering_level' => $level,
            ],
        ];
    }

    /**
     * @param array{phrases: string[], region_id: string, clustering_level: string} $payload
     * @return array{ok: true, progress_id: string, phrases_total: int}|array{ok: false, status: int, error: string, message: string}
     */
    public static function start(array $payload, string $guestId): array
    {
        $user = self::demoUser();
        if (!$user) {
            return self::fail(503, 'unavailable', 'Демо временно недоступно');
        }

        $progressId = md5('demo_cluster_' . microtime(true) . $guestId);
        $request = self::buildClusterRequest($payload, $progressId);

        dispatch(new StartClusterAnalyseQueue($request, $user))->onQueue(ClusterQueues::name('main'));

        Cache::put(self::CACHE_PREFIX . $progressId, [
            'guest' => $guestId,
            'started_at' => time(),
        ], 3600);

        return [
            'ok' => true,
            'progress_id' => $progressId,
            'phrases_total' => count($payload['phrases']),
        ];
    }

    /**
     * @return array{ok: true, status: string, progress?: array<string, mixed>, result?: array<string, mixed>}|array{ok: false, status: int, error: string, message: string}
     */
    public static function poll(string $progressId, string $guestId): array
    {
        $progressId = trim($progressId);
        if ($progressId === '') {
            return self::fail(422, 'validation', 'Не указан progress_id');
        }

        $meta = Cache::get(self::CACHE_PREFIX . $progressId);
        if (!is_array($meta) || ($meta['guest'] ?? '') !== $guestId) {
            return self::fail(404, 'not_found', 'Сессия демо не найдена или истекла');
        }

        $cfg = self::config();
        $startedAt = (int) ($meta['started_at'] ?? 0);
        $timeout = (int) ($cfg['poll_timeout_sec'] ?? 240);
        if ($startedAt > 0 && (time() - $startedAt) > $timeout) {
            self::cleanup($progressId);

            return self::fail(504, 'timeout', 'Превышено время ожидания. Попробуйте меньше фраз или зарегистрируйтесь в кабинете.');
        }

        $cluster = ClusterResults::where('progress_id', '=', $progressId)->first();
        if ($cluster) {
            $raw = Cluster::unpackCluster($cluster->result);
            $formatted = self::formatResult($raw);
            self::cleanup($progressId);

            return [
                'ok' => true,
                'status' => 'complete',
                'result' => $formatted,
            ];
        }

        $progress = ClusterProgress::snapshot($progressId);

        return [
            'ok' => true,
            'status' => 'pending',
            'progress' => [
                'phrases_total' => (int) ($progress['phrases_total'] ?? 0),
                'phrases_done' => (int) ($progress['phrases_done'] ?? 0),
                'phrases_pending' => (int) ($progress['phrases_pending'] ?? 0),
                'waiting_in_queue' => !empty($progress['waiting_in_queue']),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $startMeta
     */
    public static function buildRunResponse(array $startMeta, int $remaining, string $guestId): array
    {
        $cfg = self::config();
        $moduleSlug = (string) ($cfg['module_slug'] ?? self::MODULE);
        $registerBase = rtrim((string) config('app.url', 'https://lk.redbox.su'), '/');

        return [
            'demo' => true,
            'module' => self::MODULE,
            'remaining' => $remaining,
            'status' => 'pending',
            'progress_id' => $startMeta['progress_id'],
            'limits' => [
                'max_phrases' => (int) ($cfg['max_phrases'] ?? 10),
                'min_phrases' => (int) ($cfg['min_phrases'] ?? 3),
                'max_runs_per_day' => (int) ($cfg['max_runs_per_day'] ?? 2),
                'top_count' => (int) ($cfg['top_count'] ?? 10),
                'regions' => self::regionsForUi(),
                'clustering_levels' => $cfg['clustering_levels'] ?? [],
            ],
            'progress' => [
                'phrases_total' => (int) ($startMeta['phrases_total'] ?? 0),
                'phrases_done' => 0,
                'phrases_pending' => (int) ($startMeta['phrases_total'] ?? 0),
                'waiting_in_queue' => true,
            ],
            'upgrade' => [
                'register_url' => $registerBase . '/register?' . http_build_query([
                    'module' => $moduleSlug,
                    'from' => 'demo',
                    'guest' => $guestId,
                ]),
                'login_url' => $registerBase . '/login',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $result
     * @param array<string, mixed> $progress
     */
    public static function buildPollResponse(
        string $status,
        int $remaining,
        string $guestId,
        ?array $result = null,
        ?array $progress = null
    ): array {
        $cfg = self::config();
        $moduleSlug = (string) ($cfg['module_slug'] ?? self::MODULE);
        $registerBase = rtrim((string) config('app.url', 'https://lk.redbox.su'), '/');

        $payload = [
            'demo' => true,
            'module' => self::MODULE,
            'remaining' => $remaining,
            'status' => $status,
            'limits' => [
                'max_phrases' => (int) ($cfg['max_phrases'] ?? 10),
                'min_phrases' => (int) ($cfg['min_phrases'] ?? 3),
                'max_runs_per_day' => (int) ($cfg['max_runs_per_day'] ?? 2),
                'top_count' => (int) ($cfg['top_count'] ?? 10),
                'regions' => self::regionsForUi(),
                'clustering_levels' => $cfg['clustering_levels'] ?? [],
            ],
            'upgrade' => [
                'register_url' => $registerBase . '/register?' . http_build_query([
                    'module' => $moduleSlug,
                    'from' => 'demo',
                    'guest' => $guestId,
                ]),
                'login_url' => $registerBase . '/login',
            ],
        ];

        if ($progress !== null) {
            $payload['progress'] = $progress;
        }
        if ($result !== null) {
            $payload['result'] = $result;
        }

        return $payload;
    }

    /**
     * @param array<string, array<string, mixed>> $raw
     * @return array<string, mixed>
     */
    public static function formatResult(array $raw): array
    {
        $groups = [];
        $singles = [];
        $totalPhrases = 0;

        foreach ($raw as $mainKey => $cluster) {
            if (!is_array($cluster)) {
                continue;
            }

            $phrases = [];
            foreach ($cluster as $phrase => $info) {
                if ($phrase === 'finallyResult') {
                    continue;
                }
                $phrases[] = (string) $phrase;
            }

            $totalPhrases += count($phrases);

            if (count($phrases) <= 1) {
                foreach ($phrases as $p) {
                    $singles[] = $p;
                }
                continue;
            }

            $name = (string) ($cluster['finallyResult']['groupName'] ?? $mainKey);
            $groups[] = [
                'name' => $name,
                'phrases' => $phrases,
                'size' => count($phrases),
            ];
        }

        usort($groups, static function ($a, $b) {
            return ($b['size'] ?? 0) <=> ($a['size'] ?? 0);
        });

        return [
            'summary' => [
                'phrases' => $totalPhrases,
                'clusters' => count($groups),
                'singles' => count($singles),
            ],
            'groups' => $groups,
            'singles' => $singles,
            'locked' => [
                'frequency',
                'relevance',
                'export_xls',
                'manual_edit',
                'full_phrase_list',
                'pro_mode',
            ],
        ];
    }

    /**
     * @param array{phrases: string[], region_id: string, clustering_level: string} $payload
     * @return array<string, mixed>
     */
    private static function buildClusterRequest(array $payload, string $progressId): array
    {
        $cfg = self::config();
        $config = ClusterConfigurationClassic::first();

        return [
            'mode' => 'classic',
            'phrases' => implode("\n", $payload['phrases']),
            'region' => $payload['region_id'],
            'progressId' => $progressId,
            'domain' => '',
            'comment' => 'datagon demo',
            'clusteringLevel' => $payload['clustering_level'],
            'count' => (string) ($cfg['top_count'] ?? 10),
            'searchBase' => 'false',
            'searchPhrases' => 'false',
            'searchTarget' => 'false',
            'searchRelevance' => 'false',
            'save' => '0',
            'sendMessage' => '0',
            'searchEngine' => $config->search_engine ?? 'yandex',
            'gainFactor' => (string) ($config->gain_factor ?? 1),
            'brutForceCount' => (string) ($config->brut_force_count ?? 1),
            'brutForce' => !empty($config->brut_force) ? 'true' : 'false',
            'engineVersion' => $config->engine_version ?? '1501',
            'reductionRatio' => $config->reduction_ratio ?? 'soft',
            'demo' => true,
        ];
    }

    private static function demoUser(): ?User
    {
        $cfg = self::config();
        $id = (int) ($cfg['user_id'] ?? 0);
        if ($id > 0) {
            $user = User::find($id);
            if ($user) {
                return $user;
            }
        }

        return User::query()->orderBy('id')->first();
    }

    private static function cleanup(string $progressId): void
    {
        Cache::forget(self::CACHE_PREFIX . $progressId);
        ClusterQueue::where('progress_id', '=', $progressId)->delete();
        ClusterResults::where('progress_id', '=', $progressId)->where('comment', '=', 'datagon demo')->delete();
    }

    /**
     * @return array{ok: false, status: int, error: string, message: string}
     */
    private static function fail(int $status, string $error, string $message): array
    {
        return [
            'ok' => false,
            'status' => $status,
            'error' => $error,
            'message' => $message,
        ];
    }
}
