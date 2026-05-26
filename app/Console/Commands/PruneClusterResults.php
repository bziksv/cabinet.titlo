<?php

namespace App\Console\Commands;

use App\Classes\Cron\ClusterCleaningResults;
use Illuminate\Console\Command;

class PruneClusterResults extends Command
{
    protected $signature = 'cluster:prune-results {--dry-run : Only count rows, do not delete}';

    protected $description = 'Delete cluster_results older than cluster_configuration.cleaning_interval (same as cron ClusterCleaningResults)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = ClusterCleaningResults::run($dryRun);

        if ($result['error'] === 'invalid_cleaning_interval') {
            $this->error($result['message']);
            $this->comment('Set cleaning_interval at /cluster-configuration (Настройка автоудаления).');

            return 1;
        }

        $this->line('Days (cleaning_interval): ' . $result['days']);
        $this->line('Cutoff created_at: ' . $result['cutoff']);
        $this->line('Candidates: ' . $result['candidates']);

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
