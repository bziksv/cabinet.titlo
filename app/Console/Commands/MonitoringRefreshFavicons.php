<?php

namespace App\Console\Commands;

use App\Classes\Monitoring\ProjectFaviconService;
use App\MonitoringProject;
use Illuminate\Console\Command;

class MonitoringRefreshFavicons extends Command
{
    protected $signature = 'monitoring:refresh-favicons
                            {--missing : в БД нет favicon_path (скачать с сайта)}
                            {--no-file : favicon_path есть, файла на диске нет}
                            {--project= : id одного проекта}
                            {--force : перекачать даже если файл уже есть}
                            {--limit=0 : макс. проектов за запуск (0 = все)}
                            {--fast : быстрый режим (агрегаторы), если файл уже есть но слабый}';

    protected $description = 'Скачать и сохранить фавиконки проектов мониторинга (PNG 128×128)';

    public function handle(ProjectFaviconService $service): int
    {
        set_time_limit(0);

        $force = (bool) $this->option('force');
        $limit = max(0, (int) $this->option('limit'));
        $fastDefault = (bool) $this->option('fast');
        $ok = 0;
        $fail = 0;
        $processed = 0;

        $query = MonitoringProject::query()->orderBy('id');

        if ($projectId = $this->option('project')) {
            $query->where('id', (int) $projectId);
        } elseif ($this->option('missing')) {
            $query->whereNull('favicon_path');
        } elseif ($this->option('no-file')) {
            $query->whereNotNull('favicon_path');
        }

        $useNoFileFilter = (bool) $this->option('no-file') && !$this->option('project');

        $query->chunkById(25, function ($projects) use (
            $service,
            $force,
            $limit,
            $fastDefault,
            $useNoFileFilter,
            &$ok,
            &$fail,
            &$processed
        ) {
            foreach ($projects as $project) {
                if ($useNoFileFilter && $service->absolutePath($project) !== null) {
                    continue;
                }

                if ($limit > 0 && $processed >= $limit) {
                    return false;
                }

                $processed++;
                $started = microtime(true);
                $this->line('Project #' . $project->id . ' ' . $project->url);

                $useFast = $fastDefault || ($project->favicon_path && !$service->isWeakFavicon($project));
                if ($service->refresh($project, $force, $useFast)) {
                    $ok++;
                    $this->info('  OK (' . (int) round((microtime(true) - $started) * 1000) . ' ms)');
                } else {
                    $fail++;
                    $this->warn('  skip (' . (int) round((microtime(true) - $started) * 1000) . ' ms)');
                }
            }
        });

        $this->info("Done: {$ok} saved, {$fail} skipped, processed={$processed}.");

        return 0;
    }
}
