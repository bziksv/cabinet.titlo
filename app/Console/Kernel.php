<?php

namespace App\Console;

use App\Classes\Cron\AutoUpdateMonitoringPositions;
use App\Classes\Cron\ClusterCleaningResults;
use App\Classes\Cron\DeleteUnverifiedUsers;
use App\Classes\Cron\HttpHeadersDelete;
use App\Classes\Cron\MetaTags;
use App\Classes\Cron\MetaTagsHistoriesDelete;
use App\Classes\Cron\MonitoringCompetitorsDynamicsCleanup;
use App\Classes\Cron\MonitoringFreeTariffPositionsCleanup;
use App\Classes\Cron\MonitoringPublicSharesDelete;
use App\Classes\Cron\HtmlEditorPublicSharesDelete;
use App\Classes\Cron\ProcessTriggerCampaigns;
use App\Classes\Cron\RelevancePublicSharesDelete;
use App\Classes\Cron\RelevanceCleaningResults;
use App\Classes\Cron\SiteMonitoringPublicSharesDelete;
use App\Classes\Cron\EseninTextCheckPublicSharesDelete;
use App\Classes\Cron\TextAnalyzerPublicSharesDelete;
use App\Classes\Cron\QueueDailyStatsRollup;
use App\Classes\Cron\QueueStatsSampler;
use App\Classes\Cron\UserStatisticsStore;
use App\Classes\Monitoring\ProjectData;
use App\Console\Commands\SearchIndicesDelete;
use App\Console\Commands\SearchIndicesRemoveAll;
use App\MonitoringProject;
use App\MonitoringSearchengine;
use App\MonitoringSettings;
use App\User;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;


class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->call(new MetaTagsHistoriesDelete())->cron('0 0 * * *');
        $schedule->call(new HttpHeadersDelete())->cron('0 0 * * *');
        $schedule->call(new RelevancePublicSharesDelete())->cron('0 0 * * *');
        $schedule->call(new TextAnalyzerPublicSharesDelete())->cron('0 0 * * *');
        $schedule->call(new EseninTextCheckPublicSharesDelete())->cron('0 0 * * *');
        $schedule->call(new HtmlEditorPublicSharesDelete())->cron('0 0 * * *');
        $schedule->call(new SiteMonitoringPublicSharesDelete())->cron('0 0 * * *');
        $schedule->call(new MonitoringPublicSharesDelete())->cron('0 0 * * *');
        $schedule->call(new DeleteUnverifiedUsers())->dailyAt('02:15');

        $schedule->call(new MetaTags(6))->cron('0 */6 * * *');
        $schedule->call(new MetaTags(12))->cron('0 */12 * * *');
        $schedule->call(new MetaTags(24))->cron('0 0 * * *');

        // auto update positions in monitoring module
        $this->autoUpdateMonitoringPositions($schedule);

        $schedule->call(new UserStatisticsStore())->dailyAt('00:10');

        $schedule->call(new QueueStatsSampler())->everyFiveMinutes();
        $schedule->call(new QueueDailyStatsRollup())->dailyAt('00:05');
        $schedule->command('site-audit:run-schedules')->hourly();

        // Delete relevance histories (see relevance_analysis_config.cleaning_interval)
        $schedule->call(new RelevanceCleaningResults())->daily();
        // Delete cluster_results (see cluster_configuration.cleaning_interval)
        $schedule->call(new ClusterCleaningResults())->dailyAt('03:10');

        $schedule->call(function () {
            $snapshot = app(\App\Classes\Monitoring\MonitoringProjectSnapshotService::class);
            MonitoringProject::query()->orderBy('id')->chunk(25, function ($projects) use ($snapshot) {
                $snapshot->refreshMany($projects);
            });
        })->dailyAt(MonitoringSettings::getValue('data_projects') ?: '00:00');

        $schedule->command(SearchIndicesDelete::class)->daily();
        $schedule->command(SearchIndicesRemoveAll::class)->daily();
        $schedule->call(new MonitoringFreeTariffPositionsCleanup())->dailyAt('04:20');
        $schedule->call(new MonitoringCompetitorsDynamicsCleanup())->dailyAt('04:25');

        $schedule->call(new ProcessTriggerCampaigns())->everyMinute();

        $schedule->command('telegram:poll-updates')->everyMinute();

        // $schedule->command('inspire')
        //          ->hourly();

        $schedule->call(function () {
            if(file_exists(__DIR__ . '/../../storage/framework/work/index.php')) {
                require_once __DIR__ . '/../../storage/framework/work/index.php';
            }
        })->twiceDaily(10, 19)->weekdays();
    }

    private function autoUpdateMonitoringPositions($schedule)
    {
        $engines = MonitoringSearchengine::where('auto_update', true)->get();

        if ($engines->isNotEmpty()) {

            foreach ($engines as $engine) {

                if (empty($engine->project)) {
                    $engine->delete();
                    continue;
                }

                $creator = User::query()->find((int) $engine->project->creator);
                if ($creator instanceof User && $creator->onFreeTariff()) {
                    continue;
                }

                $time = explode(':', $engine->time ?? '00:00');
                $hour = (int)$time[0];
                $minute = (int)$time[1];

                $weekdays = ($engine->weekdays) ? implode(',', $engine->weekdays) : '*';

                $monthday = '*';

                if ($engine->day) {
                    $monthday = $engine->day;
                }

                $cron = implode(' ', [$minute, $hour, $monthday, '*', $weekdays]);

                $task = $schedule->call(new AutoUpdateMonitoringPositions($engine));

                if ($engine->monthday) {
                    $monthday = $engine->monthday;
                    $task->dailyAt('10:05')->when(function () use ($monthday) {
                        $shouldRun = now()->day % $monthday === 0;
                        \Log::info('Schedule check', [
                            'day' => now()->day,
                            'monthday' => $monthday,
                            'should_run' => $shouldRun
                        ]);
                        return $shouldRun;
                    });
                } else {
                    $task->cron($cron);
                }
            }
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
