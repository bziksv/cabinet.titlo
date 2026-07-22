<?php

namespace App\Console\Commands;

use App\Services\SiteAudit\SiteAuditCrawlStarter;
use App\Services\SiteAudit\SiteAuditSyncRunner;
use App\User;
use Illuminate\Console\Command;

/**
 * Локальный smoke: php artisan site-audit:crawl example.com --user=1 --sync --limit=20
 */
class SiteAuditCrawlCommand extends Command
{
    protected $signature = 'site-audit:crawl
        {domain : Домен без схемы}
        {--user= : user_id}
        {--limit=50 : Обрезка pages_limit для теста}
        {--speed=normal : slow|normal|fast|turbo}
        {--sync : Выполнить в этом процессе без очереди}
        {--force : Игнорировать лимиты тарифа (только для локальных прогонов)}';

    protected $description = 'Запуск Site Audit crawl (локальная волна 2)';

    public function handle(): int
    {
        $userId = (int) ($this->option('user') ?: 0);
        if ($userId < 1) {
            $this->error('Укажите --user=ID');

            return 1;
        }

        $user = User::query()->find($userId);
        if (! $user) {
            $this->error('User not found');

            return 1;
        }

        $domain = (string) $this->argument('domain');
        $limit = max(1, (int) $this->option('limit'));
        $sync = (bool) $this->option('sync');
        $force = (bool) $this->option('force');

        $crawl = (new SiteAuditCrawlStarter())->start($user, $domain, [
            'save_html' => 'off',
            'pages_limit' => $limit,
            'crawl_speed' => (string) $this->option('speed'),
        ], ! $sync, $force || app()->environment('local'));

        $crawl->pages_limit = $limit;
        $crawl->save();

        $this->info('Crawl #' . $crawl->id . ' for ' . $domain . ' (limit ' . $limit . ')');

        if (! $sync) {
            $this->warn('В очереди. Локально: php artisan queue:work --queue=site_audit');

            return 0;
        }

        $this->info('SYNC…');
        $crawl = (new SiteAuditSyncRunner())->run($crawl);
        $this->info('Status: ' . $crawl->statusLabelRu());
        $this->info('Buckets: ' . json_encode($crawl->buckets_json, JSON_UNESCAPED_UNICODE));
        $this->info('Counts: ' . json_encode($crawl->counts_json, JSON_UNESCAPED_UNICODE));

        return $crawl->status === 'done' ? 0 : 1;
    }
}
