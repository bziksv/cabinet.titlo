<?php

namespace App\Jobs\Cluster;

use App\Cluster;
use App\ClusterResults;
use App\Support\ClusterAnalysisDebugLog;
use App\Support\ClusterQueues;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class WaitClusterAnalyseQueue implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $cluster;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Cluster $cluster)
    {
        $this->cluster = $cluster;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $progressId = $this->cluster->getProgressId();
        $result = ClusterResults::where('progress_id', '=', $progressId)->first();
        if (isset($result)) {
            ClusterAnalysisDebugLog::info($progressId, 'job.wait.already_saved');
            exit();
        }

        $done = $this->cluster->getProgressTotal();
        $current = $this->cluster->getProgressCurrentCount();
        if ($done !== $current) {
            ClusterAnalysisDebugLog::info($progressId, 'job.wait.retry', [
                'done' => $done,
                'current' => $current,
            ]);
            try {
                dispatch(new WaitClusterAnalyseQueue($this->cluster))->onQueue(ClusterQueues::name('wait'))->delay(Carbon::now()->addSeconds(10));
            } catch (\Throwable $e) {
                ClusterAnalysisDebugLog::error($progressId, 'job.wait.redispatch_failed', [
                    'message' => $e->getMessage(),
                ]);
            }
        } else {
            ClusterAnalysisDebugLog::info($progressId, 'job.wait.calculate');
            $this->cluster->calculate();
        }
    }
}
