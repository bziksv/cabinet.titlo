<?php

namespace App\Console\Commands;

use App\Classes\Monitoring\ProjectFaviconService;
use App\MonitoringProject;
use Illuminate\Console\Command;

/**
 * Одноразовый перенос PNG с legacy-хоста (lk) на диск cabinet — без CABINET_STORAGE_URL в .env.
 */
class MonitoringImportFaviconsFromLegacy extends Command
{
    protected $signature = 'monitoring:import-favicons-from-legacy
                            {--from= : базовый URL legacy-кабинета, напр. https://lk.redbox.su (обязательно)}
                            {--dry-run : только показать, сколько файлов не хватает}
                            {--limit=0 : макс. импортов за запуск (0 = без лимита)}';

    protected $description = 'Скопировать monitoring-favicons/*.png с legacy-кабинета на локальный storage (один раз после cutover)';

    public function handle(ProjectFaviconService $service): int
    {
        $from = rtrim((string) $this->option('from'), '/');
        if ($from === '' || strpos($from, 'http') !== 0) {
            $this->error('Укажите --from=https://lk.redbox.su (один раз при миграции, в .env не нужно).');

            return 1;
        }

        $dryRun = (bool) $this->option('dry-run');
        $limit = max(0, (int) $this->option('limit'));

        $missing = MonitoringProject::query()
            ->whereNotNull('favicon_path')
            ->orderBy('id')
            ->get()
            ->filter(function (MonitoringProject $project) use ($service) {
                return $service->absolutePath($project) === null;
            });

        $total = $missing->count();
        if ($total === 0) {
            $this->info('Все фавиконки с favicon_path уже есть на диске.');

            return 0;
        }

        $this->line("Нет файла на диске: {$total} проект(ов). Источник: {$from}/storage/…");

        if ($dryRun) {
            $this->warn('Dry-run — импорт не выполнялся.');

            return 0;
        }

        $imported = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($missing as $project) {
            if ($limit > 0 && $imported + $failed >= $limit) {
                $this->warn('Достигнут --limit, остановка.');
                break;
            }

            $this->line('#' . $project->id . ' ' . $project->url);
            if ($service->importFaviconFromLegacyBase($project, $from)) {
                $imported++;
                $this->info('  imported');
            } else {
                $failed++;
                $this->warn('  failed');
            }
        }

        $remaining = $total - $imported - $failed - $skipped;
        $this->info("Готово: imported={$imported}, failed={$failed}, осталось≈{$remaining}.");
        $this->line('Дальше: php artisan monitoring:refresh-favicons --missing');

        return $failed > 0 ? 2 : 0;
    }
}
