<?php

namespace App\Classes\Position;

use App\MonitoringSettings;
use Illuminate\Support\Facades\Cache;

/**
 * Общий потолок запросов к выдаче для всех воркеров съёма позиций.
 * Считается в одном файле с flock, чтобы лимит держался и на file-кэше.
 */
class PositionCollectRateLimiter
{
    public const SETTING = 'position_collect_rps';

    public const DEFAULT_RPS = 30;

    private const CACHE_KEY = 'monitoring.position.collect_rps';

    public static function limit(): int
    {
        $cached = Cache::get(self::CACHE_KEY);
        if ($cached !== null) {
            return (int) $cached;
        }

        $raw = (new MonitoringSettings())->getValue(self::SETTING);
        $limit = self::normalize($raw === false ? null : $raw);
        Cache::put(self::CACHE_KEY, $limit, 15);

        return $limit;
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Дождаться свободного слота в текущей секунде.
     */
    public static function acquire(): void
    {
        $rps = self::limit();
        if ($rps < 1) {
            return;
        }

        $path = storage_path('framework/monitoring-position-rps.json');
        $deadline = microtime(true) + 180;

        while (microtime(true) < $deadline) {
            $fp = fopen($path, 'c+');
            if ($fp === false) {
                usleep(50000);
                continue;
            }

            try {
                if (!flock($fp, LOCK_EX)) {
                    usleep(20000);
                    continue;
                }

                $raw = stream_get_contents($fp);
                $state = json_decode($raw ?: '', true);
                if (!is_array($state)) {
                    $state = ['window' => 0, 'count' => 0];
                }

                $window = time();
                if ((int) ($state['window'] ?? 0) !== $window) {
                    $state = ['window' => $window, 'count' => 0];
                }

                if ((int) $state['count'] < $rps) {
                    $state['count'] = (int) $state['count'] + 1;
                    self::writeState($fp, $state);

                    return;
                }
            } finally {
                flock($fp, LOCK_UN);
                fclose($fp);
            }

            $remain = 1 - (microtime(true) - floor(microtime(true)));
            usleep((int) max(20000, min(300000, $remain * 1000000)));
        }
    }

    /**
     * @param mixed $raw
     */
    private static function normalize($raw): int
    {
        if ($raw === null || $raw === '' || $raw === false) {
            return self::DEFAULT_RPS;
        }

        $n = (int) $raw;
        if ($n < 1) {
            return self::DEFAULT_RPS;
        }

        return min($n, 120);
    }

    /**
     * @param resource $fp
     * @param array{window:int,count:int} $state
     */
    private static function writeState($fp, array $state): void
    {
        rewind($fp);
        ftruncate($fp, 0);
        fwrite($fp, json_encode($state));
        fflush($fp);
    }
}
