<?php

namespace App\Console\Commands;

use App\Services\SiteAudit\SiteAuditPruner;
use Illuminate\Console\Command;

class SiteAuditPruneCommand extends Command
{
    protected $signature = 'site-audit:prune
                            {--user= : Только краулы пользователя}
                            {--project= : Только один project_id}
                            {--keep= : Сколько последних краулов оставить (default из config)}';

    protected $description = 'Удаляет старые site audit краулы сверх лимита хранения';

    public function handle(): int
    {
        $pruner = new SiteAuditPruner();
        $keep = $this->option('keep') !== null ? (int) $this->option('keep') : null;

        if ($this->option('project')) {
            $n = $pruner->pruneProject((int) $this->option('project'), $keep);
            $this->info("Deleted {$n} crawl(s) for project " . $this->option('project'));

            return 0;
        }

        $userId = $this->option('user') !== null ? (int) $this->option('user') : null;
        $n = $pruner->pruneAll($userId, $keep);
        $this->info("Deleted {$n} crawl(s)");

        return 0;
    }
}
