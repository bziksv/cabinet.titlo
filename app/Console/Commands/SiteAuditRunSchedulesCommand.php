<?php

namespace App\Console\Commands;

use App\Services\SiteAudit\SiteAuditCrawlStarter;
use App\SiteAuditSchedule;
use App\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SiteAuditRunSchedulesCommand extends Command
{
    protected $signature = 'site-audit:run-schedules {--dry : Только показать due}';

    protected $description = 'Запускает due site audit schedules';

    public function handle(): int
    {
        $now = Carbon::now();
        $due = SiteAuditSchedule::query()
            ->where('enabled', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('next_run_at')->orWhere('next_run_at', '<=', $now);
            })
            ->orderBy('id')
            ->limit(50)
            ->get();

        if ($due->isEmpty()) {
            $this->info('No due schedules');

            return 0;
        }

        foreach ($due as $schedule) {
            $this->line('#' . $schedule->id . ' ' . $schedule->domain . ' (' . $schedule->frequency . ')');
            if ($this->option('dry')) {
                continue;
            }

            $user = User::query()->find($schedule->user_id);
            if (! $user) {
                $schedule->enabled = false;
                $schedule->save();
                continue;
            }

            if (! SiteAuditSchedule::allowedForUser($user)) {
                $schedule->enabled = false;
                $schedule->save();
                $this->warn('  disabled: not paid tariff');
                continue;
            }

            // нормализуем legacy daily → weekly
            $freq = SiteAuditSchedule::normalizeFrequency($schedule->frequency);
            if ($schedule->frequency !== $freq) {
                $schedule->frequency = $freq;
            }

            $settings = is_array($schedule->settings_json) ? $schedule->settings_json : [];
            try {
                $crawl = (new SiteAuditCrawlStarter())->start(
                    $user,
                    $schedule->domain,
                    $settings,
                    true
                );
                $this->info('  started crawl #' . $crawl->id);
            } catch (\Throwable $e) {
                $this->warn('  skip: ' . $e->getMessage());
            }

            $schedule->last_run_at = $now;
            $schedule->next_run_at = $schedule->computeNextRun($now);
            $schedule->save();
        }

        return 0;
    }
}
