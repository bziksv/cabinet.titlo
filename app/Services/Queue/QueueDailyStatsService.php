<?php

namespace App\Services\Queue;

use App\Services\Supervisor\SupervisorAdminService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QueueDailyStatsService
{
    public const GLOBAL_PROGRAM = '__global__';

    /** @var array<string, string>|null queue => program */
    private $queueProgramMap;

    public function isEnabled(): bool
    {
        return (bool) config('cabinet-queue-admin.stats_enabled', true);
    }

    public function sampleIntervalSeconds(): int
    {
        return max(60, (int) config('cabinet-queue-admin.stats_sample_interval', 300));
    }

    /**
     * Снимок воркеров и очереди — вызывается cron каждые N минут.
     */
    public function recordSample(): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $queues = app(QueueInventoryService::class);
        $supervisor = app(SupervisorAdminService::class);

        if (! $supervisor->isEnabled() || ! $supervisor->probe()['ok']) {
            return;
        }

        $snapshot = $queues->getSnapshot(true);
        $capacity = $supervisor->capacityOverview($snapshot);
        $now = Carbon::now();
        $rows = [];

        $globalPending = 0;
        $globalReserved = 0;
        $globalRunning = 0;
        $globalTotal = 0;
        $globalLoad = 'ok';

        foreach ((array) ($capacity['programs'] ?? []) as $programRow) {
            if (! is_array($programRow)) {
                continue;
            }

            $program = (string) ($programRow['program'] ?? '');
            if ($program === '') {
                continue;
            }

            $pending = (int) ($programRow['jobs_pending'] ?? 0);
            $reserved = (int) ($programRow['jobs_reserved'] ?? 0);
            $running = (int) ($programRow['workers_running'] ?? 0);
            $total = (int) ($programRow['workers_total'] ?? 0);
            $load = (string) ($programRow['load'] ?? 'ok');

            $rows[] = [
                'sampled_at' => $now,
                'program' => $program,
                'jobs_pending' => $pending,
                'jobs_reserved' => $reserved,
                'workers_running' => $running,
                'workers_total' => $total,
                'load' => $load,
            ];

            $globalPending += $pending;
            $globalReserved += $reserved;
            $globalRunning += $running;
            $globalTotal += $total;
        }

        if ($globalRunning === 0) {
            $globalLoad = 'stopped';
        } elseif ($globalPending === 0 && $globalReserved === 0) {
            $globalLoad = 'idle';
        } elseif ($globalPending > 0) {
            $globalLoad = 'backlog';
        } else {
            $globalLoad = 'ok';
        }

        $rows[] = [
            'sampled_at' => $now,
            'program' => self::GLOBAL_PROGRAM,
            'jobs_pending' => $globalPending,
            'jobs_reserved' => $globalReserved,
            'workers_running' => $globalRunning,
            'workers_total' => $globalTotal,
            'load' => $globalLoad,
        ];

        if ($rows !== []) {
            DB::table('queue_stats_samples')->insert($rows);
        }
    }

    public function incrementProcessed(string $queue): void
    {
        if (! $this->isEnabled() || $queue === '') {
            return;
        }

        $this->incrementHourly($queue, 'processed');
    }

    public function incrementFailed(string $queue): void
    {
        if (! $this->isEnabled() || $queue === '') {
            return;
        }

        $this->incrementHourly($queue, 'failed');
    }

    /**
     * Агрегация за календарные сутки (обычно вчера, 00:05).
     */
    public function rollupDate(Carbon $date): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();
        $interval = $this->sampleIntervalSeconds();
        $programs = $this->programKeys();

        foreach (array_merge([self::GLOBAL_PROGRAM], $programs) as $program) {
            $samples = DB::table('queue_stats_samples')
                ->where('program', $program)
                ->whereBetween('sampled_at', [$start, $end])
                ->orderBy('sampled_at')
                ->get();

            if ($samples->isEmpty() && $program !== self::GLOBAL_PROGRAM) {
                continue;
            }

            $peakPending = 0;
            $peakReserved = 0;
            $peakTotal = 0;
            $idleSeconds = 0;
            $stoppedSeconds = 0;
            $backlogSeconds = 0;
            $runningSum = 0;
            $runningMin = null;
            $runningMax = 0;

            foreach ($samples as $sample) {
                $pending = (int) $sample->jobs_pending;
                $reserved = (int) $sample->jobs_reserved;
                $total = $pending + $reserved;
                $running = (int) $sample->workers_running;
                $load = (string) $sample->load;

                $peakPending = max($peakPending, $pending);
                $peakReserved = max($peakReserved, $reserved);
                $peakTotal = max($peakTotal, $total);
                $runningSum += $running;
                $runningMax = max($runningMax, $running);
                $runningMin = $runningMin === null ? $running : min($runningMin, $running);

                if ($load === 'idle') {
                    $idleSeconds += $interval;
                } elseif ($load === 'stopped' && $total > 0) {
                    $stoppedSeconds += $interval;
                } elseif ($load === 'backlog') {
                    $backlogSeconds += $interval;
                }
            }

            $processed = 0;
            $failed = 0;

            if ($program === self::GLOBAL_PROGRAM) {
                $hourly = DB::table('queue_job_hourly')
                    ->where('stat_date', $date->toDateString())
                    ->selectRaw('COALESCE(SUM(processed), 0) AS processed, COALESCE(SUM(failed), 0) AS failed')
                    ->first();
                $processed = (int) ($hourly->processed ?? 0);
                $failed = (int) ($hourly->failed ?? 0);
            } else {
                $queues = $this->queuesForProgram($program);
                if ($queues !== []) {
                    $hourly = DB::table('queue_job_hourly')
                        ->where('stat_date', $date->toDateString())
                        ->whereIn('queue', $queues)
                        ->selectRaw('COALESCE(SUM(processed), 0) AS processed, COALESCE(SUM(failed), 0) AS failed')
                        ->first();
                    $processed = (int) ($hourly->processed ?? 0);
                    $failed = (int) ($hourly->failed ?? 0);
                }
            }

            $sampleCount = $samples->count();
            $avgRunning = $sampleCount > 0 ? round($runningSum / $sampleCount, 2) : 0;

            $payload = [
                'jobs_processed' => $processed,
                'jobs_failed' => $failed,
                'peak_pending' => $peakPending,
                'peak_reserved' => $peakReserved,
                'peak_total' => $peakTotal,
                'idle_seconds' => $idleSeconds,
                'stopped_seconds' => $stoppedSeconds,
                'backlog_seconds' => $backlogSeconds,
                'workers_running_avg' => $avgRunning,
                'workers_running_min' => (int) ($runningMin ?? 0),
                'workers_running_max' => $runningMax,
                'samples_count' => $sampleCount,
            ];

            $where = [
                'stat_date' => $date->toDateString(),
                'program' => $program,
            ];

            if (DB::table('queue_daily_stats')->where($where)->exists()) {
                DB::table('queue_daily_stats')->where($where)->update(array_merge($payload, [
                    'updated_at' => Carbon::now(),
                ]));
            } else {
                DB::table('queue_daily_stats')->insert(array_merge($where, $payload, [
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]));
            }
        }
    }

    public function purgeOldSamples(): void
    {
        $days = max(1, (int) config('cabinet-queue-admin.stats_sample_retention_days', 8));
        DB::table('queue_stats_samples')
            ->where('sampled_at', '<', Carbon::now()->subDays($days))
            ->delete();
    }

    public function purgeOldHourly(): void
    {
        $days = max(1, (int) config('cabinet-queue-admin.stats_hourly_retention_days', 14));
        DB::table('queue_job_hourly')
            ->where('stat_date', '<', Carbon::now()->subDays($days)->toDateString())
            ->delete();
    }

    public function purgeOldDaily(): void
    {
        $days = max(30, (int) config('cabinet-queue-admin.stats_daily_retention_days', 90));
        DB::table('queue_daily_stats')
            ->where('stat_date', '<', Carbon::now()->subDays($days)->toDateString())
            ->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function getReportForDate(Carbon $date): array
    {
        $dateStr = $date->toDateString();
        $isToday = $date->isToday();
        $stored = DB::table('queue_daily_stats')
            ->where('stat_date', $dateStr)
            ->get()
            ->keyBy('program');

        $global = $this->normalizeDailyRow(
            $stored->get(self::GLOBAL_PROGRAM),
            $this->resolveLiveDaily(self::GLOBAL_PROGRAM, $date, $isToday, $stored),
            null,
            $isToday
        );

        $programs = [];
        foreach ($this->programKeys() as $program) {
            $programs[] = $this->normalizeDailyRow(
                $stored->get($program),
                $this->resolveLiveDaily($program, $date, $isToday, $stored),
                $program,
                $isToday
            );
        }

        usort($programs, static function (array $a, array $b) {
            return ($b['jobs_processed'] ?? 0) <=> ($a['jobs_processed'] ?? 0);
        });

        return [
            'date' => $dateStr,
            'is_today' => $isToday,
            'is_partial' => $isToday || ! $stored->has(self::GLOBAL_PROGRAM),
            'global' => $global,
            'programs' => $programs,
            'sample_interval_seconds' => $this->sampleIntervalSeconds(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRecentDays(int $days): array
    {
        $days = max(1, min(30, $days));
        $out = [];

        for ($i = 0; $i < $days; $i++) {
            $date = Carbon::today()->subDays($i);
            $report = $this->getReportForDate($date);
            $global = $report['global'] ?? [];
            $out[] = [
                'date' => $report['date'],
                'is_today' => $report['is_today'],
                'is_partial' => $report['is_partial'],
                'jobs_processed' => (int) ($global['jobs_processed'] ?? 0),
                'jobs_failed' => (int) ($global['jobs_failed'] ?? 0),
                'peak_total' => (int) ($global['peak_total'] ?? 0),
                'idle_seconds' => (int) ($global['idle_seconds'] ?? 0),
                'stopped_seconds' => (int) ($global['stopped_seconds'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function computeLiveDaily(string $program, Carbon $date): array
    {
        $start = $date->copy()->startOfDay();
        $end = Carbon::now();
        $interval = $this->sampleIntervalSeconds();

        $samples = DB::table('queue_stats_samples')
            ->where('program', $program)
            ->whereBetween('sampled_at', [$start, $end])
            ->get();

        $peakPending = 0;
        $peakReserved = 0;
        $peakTotal = 0;
        $idleSeconds = 0;
        $stoppedSeconds = 0;
        $backlogSeconds = 0;
        $runningSum = 0;
        $runningMin = null;
        $runningMax = 0;

        foreach ($samples as $sample) {
            $pending = (int) $sample->jobs_pending;
            $reserved = (int) $sample->jobs_reserved;
            $total = $pending + $reserved;
            $running = (int) $sample->workers_running;
            $load = (string) $sample->load;

            $peakPending = max($peakPending, $pending);
            $peakReserved = max($peakReserved, $reserved);
            $peakTotal = max($peakTotal, $total);
            $runningSum += $running;
            $runningMax = max($runningMax, $running);
            $runningMin = $runningMin === null ? $running : min($runningMin, $running);

            if ($load === 'idle') {
                $idleSeconds += $interval;
            } elseif ($load === 'stopped' && $total > 0) {
                $stoppedSeconds += $interval;
            } elseif ($load === 'backlog') {
                $backlogSeconds += $interval;
            }
        }

        $processed = 0;
        $failed = 0;

        if ($program === self::GLOBAL_PROGRAM) {
            $hourly = DB::table('queue_job_hourly')
                ->where('stat_date', $date->toDateString())
                ->selectRaw('COALESCE(SUM(processed), 0) AS processed, COALESCE(SUM(failed), 0) AS failed')
                ->first();
            $processed = (int) ($hourly->processed ?? 0);
            $failed = (int) ($hourly->failed ?? 0);
        } else {
            $queues = $this->queuesForProgram($program);
            if ($queues !== []) {
                $hourly = DB::table('queue_job_hourly')
                    ->where('stat_date', $date->toDateString())
                    ->whereIn('queue', $queues)
                    ->selectRaw('COALESCE(SUM(processed), 0) AS processed, COALESCE(SUM(failed), 0) AS failed')
                    ->first();
                $processed = (int) ($hourly->processed ?? 0);
                $failed = (int) ($hourly->failed ?? 0);
            }
        }

        $sampleCount = $samples->count();

        return [
            'jobs_processed' => $processed,
            'jobs_failed' => $failed,
            'peak_pending' => $peakPending,
            'peak_reserved' => $peakReserved,
            'peak_total' => $peakTotal,
            'idle_seconds' => $idleSeconds,
            'stopped_seconds' => $stoppedSeconds,
            'backlog_seconds' => $backlogSeconds,
            'workers_running_avg' => $sampleCount > 0 ? round($runningSum / $sampleCount, 2) : 0,
            'workers_running_min' => (int) ($runningMin ?? 0),
            'workers_running_max' => $runningMax,
            'samples_count' => $sampleCount,
        ];
    }

    /**
     * @param \Illuminate\Support\Collection $stored
     * @return array<string, mixed>|null
     */
    private function resolveLiveDaily(string $program, Carbon $date, bool $isToday, $stored): ?array
    {
        if ($isToday || ! $stored->has($program)) {
            return $this->computeLiveDaily($program, $date);
        }

        return null;
    }

    /**
     * @param object|null $stored
     * @param array<string, mixed>|null $live
     * @return array<string, mixed>
     */
    private function normalizeDailyRow($stored, ?array $live, ?string $program = null, bool $isToday = false): array
    {
        $source = ($live !== null && (($live['samples_count'] ?? 0) > 0 || $isToday)) ? $live : $stored;
        $programKey = $program ?? self::GLOBAL_PROGRAM;
        $module = $programKey !== self::GLOBAL_PROGRAM
            ? app(SupervisorAdminService::class)->moduleForProgram($programKey)
            : ['label' => __('Supervisor stats all programs'), 'url' => null];

        if ($source === null) {
            return [
                'program' => $programKey,
                'module_label' => $module['label'] ?? '—',
                'module_url' => $module['url'] ?? null,
                'jobs_processed' => 0,
                'jobs_failed' => 0,
                'peak_pending' => 0,
                'peak_reserved' => 0,
                'peak_total' => 0,
                'idle_seconds' => 0,
                'stopped_seconds' => 0,
                'backlog_seconds' => 0,
                'workers_running_avg' => 0,
                'workers_running_min' => 0,
                'workers_running_max' => 0,
                'samples_count' => 0,
            ];
        }

        $data = is_array($source) ? $source : (array) $source;

        return [
            'program' => $programKey,
            'module_label' => $module['label'] ?? '—',
            'module_url' => $module['url'] ?? null,
            'jobs_processed' => (int) ($data['jobs_processed'] ?? 0),
            'jobs_failed' => (int) ($data['jobs_failed'] ?? 0),
            'peak_pending' => (int) ($data['peak_pending'] ?? 0),
            'peak_reserved' => (int) ($data['peak_reserved'] ?? 0),
            'peak_total' => (int) ($data['peak_total'] ?? 0),
            'idle_seconds' => (int) ($data['idle_seconds'] ?? 0),
            'stopped_seconds' => (int) ($data['stopped_seconds'] ?? 0),
            'backlog_seconds' => (int) ($data['backlog_seconds'] ?? 0),
            'workers_running_avg' => (float) ($data['workers_running_avg'] ?? 0),
            'workers_running_min' => (int) ($data['workers_running_min'] ?? 0),
            'workers_running_max' => (int) ($data['workers_running_max'] ?? 0),
            'samples_count' => (int) ($data['samples_count'] ?? 0),
        ];
    }

    private function incrementHourly(string $queue, string $column): void
    {
        $queue = $this->normalizeQueueName($queue);
        $now = Carbon::now();
        $date = $now->toDateString();
        $hour = (int) $now->format('G');

        $updated = DB::table('queue_job_hourly')
            ->where('stat_date', $date)
            ->where('stat_hour', $hour)
            ->where('queue', $queue)
            ->increment($column);

        if ($updated === 0) {
            DB::table('queue_job_hourly')->insert([
                'stat_date' => $date,
                'stat_hour' => $hour,
                'queue' => $queue,
                'processed' => $column === 'processed' ? 1 : 0,
                'failed' => $column === 'failed' ? 1 : 0,
            ]);
        }
    }

    private function normalizeQueueName(string $queue): string
    {
        $prefix = (string) config('cabinet-cluster.queue_prefix', '');
        if ($prefix !== '' && Str::startsWith($queue, $prefix)) {
            return substr($queue, strlen($prefix)) ?: $queue;
        }

        return $queue;
    }

    /**
     * @return string[]
     */
    private function programKeys(): array
    {
        $programs = config('cabinet-supervisor-admin.program_capacity', []);

        return is_array($programs) ? array_keys($programs) : [];
    }

    /**
     * @return string[]
     */
    private function queuesForProgram(string $program): array
    {
        $programs = config('cabinet-supervisor-admin.program_capacity', []);
        $queues = (array) ($programs[$program]['queues'] ?? []);
        $prefix = (string) config('cabinet-cluster.queue_prefix', '');
        $names = [];

        foreach ($queues as $queue) {
            $queue = (string) $queue;
            $names[] = $queue;
            if ($prefix !== '') {
                $names[] = $prefix . $queue;
            }
        }

        return array_values(array_unique($names));
    }

    public static function formatDuration(int $seconds): string
    {
        if ($seconds <= 0) {
            return '—';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($hours > 0) {
            return sprintf('%d:%02d', $hours, $minutes);
        }

        return sprintf('%d %s', max(1, $minutes), __('Supervisor stats min'));
    }
}
