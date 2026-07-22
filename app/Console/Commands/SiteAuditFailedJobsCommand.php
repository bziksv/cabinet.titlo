<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Мониторинг failed_jobs для очереди site_audit (волна 2 / 5a).
 * Не тянет полный payload в память.
 */
class SiteAuditFailedJobsCommand extends Command
{
    protected $signature = 'site-audit:failed
                            {--limit=20 : Сколько записей показать}
                            {--json : JSON-вывод}';

    protected $description = 'Показать failed_jobs очереди site_audit';

    public function handle(): int
    {
        if (! Schema::hasTable('failed_jobs')) {
            $this->warn('Таблица failed_jobs отсутствует.');

            return 0;
        }

        $limit = max(1, min(100, (int) $this->option('limit')));
        $queue = (string) config('site_audit.queue', 'site_audit');

        $rows = DB::table('failed_jobs')
            ->select([
                'id',
                'queue',
                'failed_at',
                DB::raw('LEFT(exception, 300) as exception_clip'),
                DB::raw("LEFT(payload, 400) as payload_clip"),
            ])
            ->where(function ($q) use ($queue) {
                $q->where('queue', $queue)
                    ->orWhere('payload', 'like', '%SiteAudit%')
                    ->orWhere('payload', 'like', '%site_audit%');
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $matched = [];
        foreach ($rows as $row) {
            $matched[] = [
                'id' => (int) $row->id,
                'queue' => (string) $row->queue,
                'failed_at' => (string) $row->failed_at,
                'exception' => $this->clip((string) $row->exception_clip, 240),
                'job' => $this->jobNameFromClip((string) $row->payload_clip),
            ];
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'queue' => $queue,
                'count' => count($matched),
                'items' => $matched,
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return 0;
        }

        if ($matched === []) {
            $this->info('failed_jobs по site_audit: 0');

            return 0;
        }

        $this->warn('failed_jobs по site_audit: ' . count($matched) . ' (лимит ' . $limit . ')');
        $this->table(['id', 'queue', 'job', 'failed_at', 'exception'], array_map(function ($r) {
            return [$r['id'], $r['queue'], $r['job'], $r['failed_at'], $r['exception']];
        }, $matched));

        return 0;
    }

    private function jobNameFromClip(string $clip): string
    {
        if (preg_match('/"displayName"\s*:\s*"([^"]+)"/', $clip, $m)) {
            return class_basename(str_replace('\\\\', '\\', $m[1]));
        }
        if (preg_match('/"commandName"\s*:\s*"([^"]+)"/', $clip, $m)) {
            return class_basename(str_replace('\\\\', '\\', $m[1]));
        }
        if (stripos($clip, 'DiscoverSiteAudit') !== false) {
            return 'DiscoverSiteAuditUrlsJob';
        }
        if (stripos($clip, 'AggregateSiteAudit') !== false) {
            return 'AggregateSiteAuditCrawlJob';
        }
        if (stripos($clip, 'FetchParse') !== false) {
            return 'FetchParsePageJob';
        }

        return 'SiteAudit*';
    }

    private function clip(string $s, int $max): string
    {
        $s = preg_replace('/\s+/', ' ', trim($s));
        if (mb_strlen($s) <= $max) {
            return $s;
        }

        return mb_substr($s, 0, $max - 1) . '…';
    }
}
