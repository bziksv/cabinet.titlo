<?php

namespace App\Services\Supervisor;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class SupervisorAdminService
{
    /** @var array<string, mixed>|null */
    private $lastProbe;

    public function isEnabled(): bool
    {
        return (bool) config('cabinet-supervisor-admin.enabled');
    }

    /**
     * @return array{ok:bool, enabled:bool, message:string, supervisorctl:string, config_hint:string}
     */
    public function probe(): array
    {
        if ($this->lastProbe !== null) {
            return $this->lastProbe;
        }

        $enabled = $this->isEnabled();
        $supervisorctl = (string) config('cabinet-supervisor-admin.supervisorctl', '/usr/bin/supervisorctl');
        $configHint = (string) config('cabinet-supervisor-admin.config_hint', '');

        if (! $enabled) {
            return $this->lastProbe = [
                'ok' => false,
                'enabled' => false,
                'message' => __('Supervisor admin disabled hint'),
                'supervisorctl' => $supervisorctl,
                'config_hint' => $configHint,
            ];
        }

        try {
            $this->runSupervisorctl(['status']);
        } catch (\Throwable $e) {
            return $this->lastProbe = [
                'ok' => false,
                'enabled' => true,
                'message' => $e->getMessage(),
                'supervisorctl' => $supervisorctl,
                'config_hint' => $configHint,
            ];
        }

        return $this->lastProbe = [
            'ok' => true,
            'enabled' => true,
            'message' => '',
            'supervisorctl' => implode(' ', $this->supervisorctlArgv(['status'])),
            'config_hint' => $configHint,
        ];
    }

    /**
     * @return array<int, array{name:string, status:string, detail:string, uptime:string, pid:string, controllable:bool, module_label:string, module_url:?string}>
     */
    public function processes(): array
    {
        $probe = $this->probe();
        if (! $probe['ok']) {
            return [];
        }

        $output = $this->runSupervisorctl(['status']);
        $lines = preg_split('/\r\n|\r|\n/', trim($output)) ?: [];
        $processes = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parsed = $this->parseStatusLine($line);
            if ($parsed === null) {
                continue;
            }

            $parsed['controllable'] = $this->isProgramAllowed($parsed['name']);
            $module = $this->moduleForProgram($parsed['name']);
            $parsed['module_label'] = $module['label'];
            $parsed['module_url'] = $module['url'];
            $processes[] = $parsed;
        }

        usort($processes, static function (array $a, array $b) {
            return strcmp($a['name'], $b['name']);
        });

        return $processes;
    }

    /**
     * Сводка «воркеры vs очередь jobs» — снимок, не история.
     *
     * @param array<string, mixed> $queueSnapshot QueueInventoryService::getSnapshot()
     * @return array<string, mixed>
     */
    public function capacityOverview(array $queueSnapshot): array
    {
        $processes = $this->processes();
        $queueRows = is_array($queueSnapshot['queues'] ?? null) ? $queueSnapshot['queues'] : [];
        $prefix = (string) config('cabinet-cluster.queue_prefix', '');
        $programs = config('cabinet-supervisor-admin.program_capacity', []);
        $backlogPerWorker = max(1, (int) config('cabinet-supervisor-admin.capacity_backlog_per_worker', 3));
        $busyPercent = max(1, min(100, (int) config('cabinet-supervisor-admin.capacity_busy_percent', 75)));

        $workersByProgram = [];
        foreach ($processes as $proc) {
            $base = preg_replace('/:.*$/', '', (string) ($proc['name'] ?? '')) ?: '';
            if ($base === '') {
                continue;
            }
            if (! isset($workersByProgram[$base])) {
                $workersByProgram[$base] = ['total' => 0, 'running' => 0];
            }
            $workersByProgram[$base]['total']++;
            if (strtoupper((string) ($proc['status'] ?? '')) === 'RUNNING') {
                $workersByProgram[$base]['running']++;
            }
        }

        $queueStats = [];
        foreach ($queueRows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $queueName = (string) ($row['queue'] ?? '');
            $baseQueue = (string) ($row['base_queue'] ?? $queueName);
            if ($prefix !== '' && Str::startsWith($queueName, $prefix)) {
                $baseQueue = substr($queueName, strlen($prefix)) ?: $baseQueue;
            }
            $stat = [
                'pending' => (int) ($row['available'] ?? 0),
                'reserved' => (int) ($row['reserved'] ?? 0),
                'total' => (int) ($row['total'] ?? 0),
            ];
            $queueStats[$queueName] = $stat;
            if ($baseQueue !== '' && $baseQueue !== $queueName) {
                $queueStats[$baseQueue] = $stat;
            }
        }

        $out = [];
        $totals = [
            'workers_total' => 0,
            'workers_running' => 0,
            'jobs_pending' => 0,
            'jobs_reserved' => 0,
            'programs_backlog' => 0,
            'programs_idle' => 0,
        ];

        if (! is_array($programs)) {
            $programs = [];
        }

        foreach ($programs as $program => $def) {
            if (! is_array($def)) {
                continue;
            }

            $workers = $workersByProgram[$program] ?? ['total' => 0, 'running' => 0];
            $running = (int) $workers['running'];
            $total = (int) $workers['total'];
            $pending = 0;
            $reserved = 0;

            foreach ((array) ($def['queues'] ?? []) as $queueName) {
                $queueName = (string) $queueName;
                $stats = $queueStats[$queueName]
                    ?? ($prefix !== '' ? ($queueStats[$prefix . $queueName] ?? null) : null);
                if ($stats === null) {
                    continue;
                }
                $pending += (int) $stats['pending'];
                $reserved += (int) $stats['reserved'];
            }

            $utilization = $running > 0 ? (int) min(100, round(($reserved / $running) * 100)) : 0;
            $pendingPerWorker = $running > 0 ? $pending / $running : (float) $pending;

            if ($running === 0) {
                $load = 'stopped';
            } elseif ($pending === 0 && $reserved === 0 && $running > 0) {
                $load = 'idle';
            } elseif ($pendingPerWorker >= $backlogPerWorker) {
                $load = 'backlog';
            } elseif ($utilization >= $busyPercent) {
                $load = 'busy';
            } else {
                $load = 'ok';
            }

            $module = $this->moduleForProgram($program);

            $out[] = [
                'program' => $program,
                'module_label' => $module['label'],
                'module_url' => $module['url'],
                'queues' => array_values((array) ($def['queues'] ?? [])),
                'workers_total' => $total,
                'workers_running' => $running,
                'numprocs_lk' => (int) ($def['numprocs_lk'] ?? 0),
                'jobs_pending' => $pending,
                'jobs_reserved' => $reserved,
                'utilization' => $utilization,
                'pending_per_worker' => round($pendingPerWorker, 1),
                'load' => $load,
                'hint' => $this->capacityHint($load, $running, $total, (int) ($def['numprocs_lk'] ?? 0)),
            ];

            $totals['workers_total'] += $total;
            $totals['workers_running'] += $running;
            $totals['jobs_pending'] += $pending;
            $totals['jobs_reserved'] += $reserved;
            if ($load === 'backlog') {
                $totals['programs_backlog']++;
            }
            if ($load === 'idle') {
                $totals['programs_idle']++;
            }
        }

        usort($out, static function (array $a, array $b) {
            $order = ['backlog' => 0, 'stopped' => 1, 'busy' => 2, 'ok' => 3, 'idle' => 4];
            $la = $order[$a['load'] ?? 'ok'] ?? 5;
            $lb = $order[$b['load'] ?? 'ok'] ?? 5;
            if ($la !== $lb) {
                return $la <=> $lb;
            }

            return strcmp($a['program'] ?? '', $b['program'] ?? '');
        });

        return [
            'generated_at' => (string) ($queueSnapshot['generated_at'] ?? now()->toDateTimeString()),
            'programs' => $out,
            'totals' => $totals,
        ];
    }

    private function capacityHint(string $load, int $running, int $total, int $numprocsLk): string
    {
        switch ($load) {
            case 'backlog':
                return __('Supervisor capacity hint backlog');
            case 'busy':
                return __('Supervisor capacity hint busy');
            case 'idle':
                return $total > 1
                    ? __('Supervisor capacity hint idle reduce', ['count' => max(0, $total - 1)])
                    : __('Supervisor capacity hint idle ok');
            case 'stopped':
                return __('Supervisor capacity hint stopped');
            default:
                if ($numprocsLk > 0 && $total > $numprocsLk) {
                    return __('Supervisor capacity hint over lk', ['lk' => $numprocsLk, 'now' => $total]);
                }
                if ($numprocsLk > 0 && $total < $numprocsLk && $running > 0) {
                    return __('Supervisor capacity hint under lk', ['lk' => $numprocsLk, 'now' => $total]);
                }

                return __('Supervisor capacity hint ok');
        }
    }

    /**
     * @return array{ok:bool, message:string}
     */
    public function control(string $program, string $action): array
    {
        $program = trim($program);
        $action = strtolower(trim($action));

        if ($program === '' || ! $this->isProgramAllowed($program)) {
            return ['ok' => false, 'message' => __('Supervisor program not allowed')];
        }

        if (! in_array($action, ['start', 'stop', 'restart', 'status'], true)) {
            return ['ok' => false, 'message' => __('Supervisor invalid action')];
        }

        $probe = $this->probe();
        if (! $probe['ok']) {
            return ['ok' => false, 'message' => $probe['message']];
        }

        try {
            $this->runSupervisorctl([$action, $program]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        $this->lastProbe = null;

        return [
            'ok' => true,
            'message' => __('Supervisor action done', [
                'action' => $action,
                'program' => $program,
            ]),
        ];
    }

    /**
     * start/stop/restart всех программ cabinet-titlo-* (группы program:*).
     *
     * @return array{ok:bool, message:string}
     */
    public function controlAll(string $action): array
    {
        $action = strtolower(trim($action));

        if (! in_array($action, ['start', 'stop', 'restart'], true)) {
            return ['ok' => false, 'message' => __('Supervisor invalid action')];
        }

        $probe = $this->probe();
        if (! $probe['ok']) {
            return ['ok' => false, 'message' => $probe['message']];
        }

        $programs = array_keys(config('cabinet-supervisor-admin.program_capacity', []));
        if (! is_array($programs) || $programs === []) {
            return ['ok' => false, 'message' => __('Supervisor no processes')];
        }

        $this->lastProbe = null;

        $okCount = 0;
        $failed = [];

        foreach ($programs as $program) {
            $program = trim((string) $program);
            if ($program === '' || ! Str::startsWith($program, 'cabinet-titlo-')) {
                continue;
            }

            $target = $program . ':*';
            if (! $this->isProgramAllowed($target)) {
                $failed[$program] = __('Supervisor program not allowed');

                continue;
            }

            try {
                $this->runSupervisorctl([$action, $target], 120);
                $okCount++;
            } catch (\Throwable $e) {
                $failed[$program] = $e->getMessage();
            }
        }

        if ($failed === []) {
            return [
                'ok' => true,
                'message' => __('Supervisor action all done', [
                    'action' => $action,
                    'count' => $okCount,
                ]),
            ];
        }

        if ($okCount > 0) {
            return [
                'ok' => false,
                'message' => __('Supervisor action all partial', [
                    'action' => $action,
                    'ok' => $okCount,
                    'fail' => count($failed),
                    'detail' => implode('; ', array_slice(array_values($failed), 0, 2)),
                ]),
            ];
        }

        return [
            'ok' => false,
            'message' => __('Supervisor action all failed', [
                'action' => $action,
                'detail' => implode('; ', array_slice(array_values($failed), 0, 2)),
            ]),
        ];
    }

    /**
     * @return array{name:string, program:string, exists:bool, empty:bool, tail:string, path:string, size_bytes:int}
     */
    public function tailLog(string $program, int $lines = 80): array
    {
        $program = trim($program);
        $programBase = $this->programBaseName($program);
        $relative = $this->logFileRelative($programBase);
        $path = $relative !== '' ? base_path($relative) : '';

        if ($path === '' || ! is_readable($path)) {
            return [
                'name' => $program,
                'program' => $programBase,
                'exists' => false,
                'empty' => true,
                'tail' => '',
                'path' => $relative,
                'size_bytes' => 0,
            ];
        }

        $size = @filesize($path);
        $size = $size === false ? 0 : (int) $size;
        $content = $this->readTail($path, max(10, min($lines, 400)));

        return [
            'name' => $program,
            'program' => $programBase,
            'exists' => true,
            'empty' => trim($content) === '',
            'tail' => $content,
            'path' => $relative,
            'size_bytes' => $size,
        ];
    }

    public function programBaseName(string $program): string
    {
        $base = preg_replace('/:.*$/', '', trim($program)) ?: trim($program);

        return $base;
    }

    private function logFileRelative(string $programBase): string
    {
        $logFiles = config('cabinet-supervisor-admin.log_files', []);
        if (! is_array($logFiles)) {
            return '';
        }

        if (isset($logFiles[$programBase])) {
            return (string) $logFiles[$programBase];
        }

        foreach ($logFiles as $key => $path) {
            if (Str::startsWith($programBase, (string) $key)) {
                return (string) $path;
            }
        }

        return '';
    }

    public function isProgramAllowed(string $program): bool
    {
        $patterns = config('cabinet-supervisor-admin.allowed_programs', ['cabinet-titlo-*']);
        if (! is_array($patterns) || $patterns === []) {
            return false;
        }

        foreach ($patterns as $pattern) {
            if (Str::is($pattern, $program)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{label:string, url:?string}
     */
    public function moduleForProgram(string $programName): array
    {
        $base = preg_replace('/:.*$/', '', $programName) ?: $programName;
        $modules = config('cabinet-supervisor-admin.program_modules', []);

        if (! is_array($modules)) {
            return ['label' => '—', 'url' => null];
        }

        $def = $modules[$base] ?? null;
        if (! is_array($def)) {
            return ['label' => '—', 'url' => null];
        }

        $routeName = isset($def['route']) ? trim((string) $def['route']) : '';
        $url = ($routeName !== '' && Route::has($routeName)) ? route($routeName) : null;

        return [
            'label' => __((string) ($def['label'] ?? '—')),
            'url' => $url,
        ];
    }

    /**
     * @param array<int, string> $args
     */
    private function runSupervisorctl(array $args, int $timeoutSeconds = 15): string
    {
        $command = $this->supervisorctlArgv($args);

        $process = new Process($command, base_path(), null, null, max(5, $timeoutSeconds));
        $process->run();

        if (! $process->isSuccessful()) {
            $stderr = trim($process->getErrorOutput());
            $stdout = trim($process->getOutput());
            $message = $stderr !== '' ? $stderr : $stdout;
            if ($message === '') {
                $message = __('Supervisor command failed');
            }

            throw new \RuntimeException($message);
        }

        return trim($process->getOutput());
    }

    /**
     * SUPERVISORCTL_BIN может быть "sudo /usr/bin/supervisorctl" — разбиваем на argv.
     *
     * @param array<int, string> $args
     * @return array<int, string>
     */
    private function supervisorctlArgv(array $args): array
    {
        $bin = trim((string) config('cabinet-supervisor-admin.supervisorctl', '/usr/bin/supervisorctl'));
        $bin = trim($bin, " \t\n\r\0\x0B\"'");

        $prefix = preg_split('/\s+/', $bin, -1, PREG_SPLIT_NO_EMPTY) ?: ['/usr/bin/supervisorctl'];

        if (($prefix[0] ?? '') === 'sudo' && ($prefix[1] ?? '') !== '-n') {
            array_splice($prefix, 1, 0, ['-n']);
        }

        return array_merge($prefix, $args);
    }

    /**
     * @return array{name:string, status:string, detail:string, uptime:string, pid:string}|null
     */
    private function parseStatusLine(string $line): ?array
    {
        if (! preg_match('/^(\S+)\s+(\S+)\s*(.*)$/', $line, $m)) {
            return null;
        }

        $name = $m[1];
        $status = strtoupper($m[2]);
        $detail = trim($m[3]);

        $pid = '';
        $uptime = '';
        if (preg_match('/pid\s+(\d+)/i', $detail, $pidMatch)) {
            $pid = $pidMatch[1];
        }
        if (preg_match('/uptime\s+([^,]+)/i', $detail, $upMatch)) {
            $uptime = trim($upMatch[1]);
        }

        return [
            'name' => $name,
            'status' => $status,
            'detail' => $detail,
            'uptime' => $uptime,
            'pid' => $pid,
        ];
    }

    private function readTail(string $path, int $lines): string
    {
        if (! is_readable($path)) {
            return '';
        }

        $file = new \SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $lastLine = (int) $file->key();
        $start = max(0, $lastLine - $lines);
        $buffer = [];

        $file->seek($start);
        while (! $file->eof()) {
            $buffer[] = rtrim((string) $file->current(), "\r\n");
            $file->next();
        }

        return implode("\n", $buffer);
    }
}
