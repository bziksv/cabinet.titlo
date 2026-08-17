<?php

namespace App\Console\Commands;

use App\Jobs\GenerateSeoReportJob;
use App\Mail\SeoReportShareMail;
use App\SeoReports\SeoReport;
use App\SeoReports\SeoReportPeriodResolver;
use App\SeoReports\SeoReportProject;
use App\SeoReports\SeoReportSectionRegistry;
use App\Services\SeoReports\SeoReportGeneratorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SeoReportsGenerateMonthlyCommand extends Command
{
    protected $signature = 'seo-reports:generate-monthly {--project=} {--force}';

    protected $description = 'Auto-generate previous-month SEO reports for projects with schedule enabled';

    public function handle(SeoReportGeneratorService $generator): int
    {
        $q = SeoReportProject::query()->where('status', 'active');
        if ($this->option('project')) {
            $q->where('id', (int) $this->option('project'));
        }

        $count = 0;
        $q->orderBy('id')->chunkById(50, function ($projects) use ($generator, &$count) {
            foreach ($projects as $project) {
                $settings = method_exists($project, 'reportSettings')
                    ? $project->reportSettings()
                    : (is_array($project->settings_json) ? $project->settings_json : []);
                if (empty($settings['auto_generate']) && !$this->option('force')) {
                    continue;
                }

                // Monthly cron: report = previous calendar month; compare follows template settings.
                [$from, $to, $cFrom, $cTo] = SeoReportPeriodResolver::resolve($settings, [
                    'period_preset' => SeoReportPeriodResolver::PERIOD_PREV_MONTH,
                ]);

                $exists = SeoReport::query()
                    ->where('project_id', $project->id)
                    ->whereDate('period_from', $from->toDateString())
                    ->whereDate('period_to', $to->toDateString())
                    ->whereNull('archived_from_report_id')
                    ->whereIn('status', [SeoReport::STATUS_READY, SeoReport::STATUS_APPROVED, SeoReport::STATUS_GENERATING])
                    ->exists();
                if ($exists && !$this->option('force')) {
                    continue;
                }

                $report = SeoReport::query()->create([
                    'project_id' => $project->id,
                    'user_id' => (int) $project->user_id,
                    'status' => SeoReport::STATUS_GENERATING,
                    'period_from' => $from,
                    'period_to' => $to,
                    'compare_from' => $cFrom,
                    'compare_to' => $cTo,
                    'section_states' => $this->initialStates($project),
                    'public_pin' => isset($settings['default_pin']) ? (string) $settings['default_pin'] : null,
                ]);
                $report->ensurePublicToken();

                if (config('seo-reports.sync')) {
                    $generator->generate($report);
                    $report->refresh();
                } else {
                    GenerateSeoReportJob::dispatch((int) $report->id);
                }

                $this->maybeEmail($project, $report, $settings);
                $count++;
                $this->info('Scheduled report #' . $report->id . ' for ' . $project->domain);
            }
        });

        $this->info('Done: ' . $count . ' report(s)');

        return 0;
    }

    /**
     * @param array<string,mixed> $settings
     */
    private function maybeEmail(SeoReportProject $project, SeoReport $report, array $settings): void
    {
        if (empty($settings['auto_email'])) {
            return;
        }
        $emails = preg_split('/[\s,;]+/', (string) ($settings['auto_email_to'] ?? '')) ?: [];
        $emails = array_values(array_filter(array_map(static function ($e) {
            $e = trim(mb_strtolower((string) $e));

            return filter_var($e, FILTER_VALIDATE_EMAIL) ? $e : null;
        }, $emails)));
        if ($emails === []) {
            return;
        }

        // Email after sync generate; for async queue, email is best-effort with current token.
        $report->ensurePublicToken();
        $publicUrl = route('seo-reports.public', ['token' => $report->public_token]);
        $message = trim((string) ($settings['auto_email_message'] ?? '')) ?: null;
        $ccManager = !empty($settings['auto_email_cc_manager']);

        foreach ($emails as $email) {
            Mail::to($email)->send(new SeoReportShareMail($project, $report, $publicUrl, $message, null));
        }
        $managerEmail = method_exists($project, 'brandingManagerEmail')
            ? $project->brandingManagerEmail()
            : $project->manager_email;
        if ($ccManager && $managerEmail && filter_var($managerEmail, FILTER_VALIDATE_EMAIL)) {
            Mail::to($managerEmail)->send(new SeoReportShareMail(
                $project,
                $report,
                $publicUrl,
                $message,
                'Titlo'
            ));
        }
    }

    /**
     * @return array<string,array{enabled:bool,source_status:string}>
     */
    private function initialStates(SeoReportProject $project): array
    {
        $toggles = $project->resolvedSectionToggles();
        $out = [];
        foreach (SeoReportSectionRegistry::all() as $key => $meta) {
            $out[$key] = [
                'enabled' => !empty($toggles[$key]),
                'source_status' => SeoReportSectionRegistry::SOURCE_STATUS_NOT_CONNECTED,
            ];
        }

        return $out;
    }
}
