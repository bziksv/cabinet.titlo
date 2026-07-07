<?php

namespace App\Jobs;

use App\Jobs\OccurrenceQueue;
use App\MonitoringProject;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EnqueueMonitoringOccurrenceKeysJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int */
    private $projectId;

    /** @var int */
    private $regionId;

    /** @var int[] */
    private $keywordIds;

    /** @var string */
    private $targetQueue;

    public $timeout = 300;

    /**
     * @param int[] $keywordIds
     */
    public function __construct(int $projectId, int $regionId, array $keywordIds, string $targetQueue = 'high')
    {
        $this->projectId = $projectId;
        $this->regionId = $regionId;
        $this->keywordIds = array_values(array_map('intval', $keywordIds));
        $this->targetQueue = $targetQueue;
    }

    public function handle(): void
    {
        if ($this->keywordIds === []) {
            return;
        }

        $project = MonitoringProject::query()->find($this->projectId);
        if ($project === null) {
            return;
        }

        $engine = $project->searchengines()->with('location')->find($this->regionId);
        if ($engine === null || $engine->engine !== 'yandex') {
            return;
        }

        $project->keywords()
            ->whereIn('id', $this->keywordIds)
            ->orderBy('id')
            ->chunkById(100, function ($keywords) use ($engine) {
                foreach ($keywords as $keyword) {
                    dispatch((new OccurrenceQueue($keyword, $engine))->onQueue($this->targetQueue));
                }
            });
    }
}
