<?php

namespace App\Console\Commands;

use App\Classes\Cron\DeleteUnverifiedUsers;
use Illuminate\Console\Command;

class PruneUnverifiedUsers extends Command
{
    protected $signature = 'users:prune-unverified {--dry-run : Only count accounts, do not delete}';

    protected $description = 'Delete users without email verification older than DELETE_UNVERIFIED_USERS_DAYS (same as cron DeleteUnverifiedUsers)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = DeleteUnverifiedUsers::run($dryRun);

        if (!$result['enabled']) {
            $this->warn('DELETE_UNVERIFIED_USERS=false — task is disabled in .env');

            return 0;
        }

        $this->line('Days: ' . $result['days']);
        $this->line('Candidates (no verify, older than period): ' . $result['candidates']);

        if ($dryRun) {
            $this->info('Dry run — nothing deleted.');

            return 0;
        }

        $this->info('Deleted: ' . $result['deleted']);

        if ($result['deleted'] > 0) {
            $this->comment('See storage/logs/laravel-*.log');
        }

        return 0;
    }
}
