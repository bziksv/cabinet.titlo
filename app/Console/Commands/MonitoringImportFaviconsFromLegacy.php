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
                            {--from= : базовый URL legacy, напр. https://lk.redbox.su (HTTP, часто 403)}
                            {--from-disk= : путь к storage/app/public на lk после rsync, напр. /mnt/lk-storage}
                            {--probe : проверить HTTP к одному проекту и выйти}
                            {--dry-run : только показать, сколько файлов не хватает}
                            {--limit=0 : макс. импортов за запуск (0 = без лимита)}
                            {--verbose : при failed — код HTTP и размер ответа}';

    protected $description = 'Скопировать monitoring-favicons/*.png с legacy на локальный storage (HTTP или --from-disk)';

    public function handle(ProjectFaviconService $service): int
    {
        $fromDisk = rtrim((string) $this->option('from-disk'), '/');
        $from = rtrim((string) $this->option('from'), '/');

        if ($fromDisk === '' && $from === '') {
            $this->error('Укажите --from-disk=/path/to/lk/storage/app/public (предпочтительно) или --from=https://lk.redbox.su');

            return 1;
        }

        if ($fromDisk !== '' && !is_dir($fromDisk)) {
            $this->error('--from-disk: каталог не найден: ' . $fromDisk);

            return 1;
        }

        if ($from !== '' && strpos($from, 'http') !== 0) {
            $this->error('--from: нужен URL с https://');

            return 1;
        }

        $dryRun = (bool) $this->option('dry-run');
        $limit = max(0, (int) $this->option('limit'));
        $verbose = (bool) $this->option('verbose');

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

        if ($fromDisk !== '') {
            $this->line("Нет файла на диске: {$total}. Копирование с диска: {$fromDisk}/");
        } else {
            $this->line("Нет файла на диске: {$total}. HTTP: {$from}/storage/…");
            $this->warn('Если все failed с http_403 — на lk тоже закрыт /storage/; используйте rsync + --from-disk.');
        }

        if ((bool) $this->option('probe')) {
            $sample = $missing->first();
            if ($sample === null) {
                return 0;
            }
            if ($fromDisk !== '') {
                $rel = ltrim((string) $sample->favicon_path, '/');
                $path = $fromDisk . '/' . $rel;
                $this->line('Probe disk #' . $sample->id . ': ' . $path);
                $this->line(is_file($path) ? '  file exists' : '  file missing');

                return is_file($path) ? 0 : 2;
            }
            $report = $service->importFaviconFromLegacyBaseReport($sample, $from);
            $this->line('Probe HTTP #' . $sample->id . ': ' . $report['url']);
            $this->line('  http=' . ($report['http_code'] ?? '—') . ' bytes=' . $report['bytes'] . ' reason=' . $report['reason']);

            return $report['ok'] ? 0 : 2;
        }

        if ($dryRun) {
            $this->warn('Dry-run — импорт не выполнялся.');

            return 0;
        }

        $imported = 0;
        $failed = 0;

        foreach ($missing as $project) {
            if ($limit > 0 && $imported + $failed >= $limit) {
                $this->warn('Достигнут --limit, остановка.');
                break;
            }

            $this->line('#' . $project->id . ' ' . $project->url);
            $ok = $fromDisk !== ''
                ? $service->importFaviconFromLegacyDisk($project, $fromDisk)
                : $service->importFaviconFromLegacyBase($project, $from);

            if ($ok) {
                $imported++;
                $this->info('  imported');
                continue;
            }

            $failed++;
            if ($verbose && $from !== '') {
                $report = $service->importFaviconFromLegacyBaseReport($project, $from);
                $this->warn(
                    '  failed: ' . $report['reason']
                    . ' http=' . ($report['http_code'] ?? '—')
                    . ' bytes=' . $report['bytes']
                );
            } else {
                $this->warn('  failed');
            }
        }

        $this->info("Готово: imported={$imported}, failed={$failed}.");
        if ($failed > 0 && $from !== '' && $fromDisk === '') {
            $this->line('HTTP с lk не сработал → rsync каталог monitoring-favicons на s3 и:');
            $this->line('  --from-disk=/path/to/storage/app/public');
        }
        $this->line('Для 211 без файла: /opt/php74/bin/php artisan monitoring:refresh-favicons --no-file');

        return $failed > 0 ? 2 : 0;
    }
}
