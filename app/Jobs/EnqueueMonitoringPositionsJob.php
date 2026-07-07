<?php

namespace App\Jobs;

use App\MonitoringProject;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Фоновая постановка съёма позиций (в HTTP не делаем сотни dispatch() подряд).
 */
class EnqueueMonitoringPositionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int */
    private $projectId;

    /** @var int[] */
    private $regionIds;

    /** @var string */
    private $targetQueue;

    public $timeout = 600;

    /**
     * @param int[] $regionIds
     */
    public function __construct(int $projectId, array $regionIds, string $targetQueue = 'position_high')
    {
        $this->projectId = $projectId;
        $this->regionIds = array_values(array_map('intval', $regionIds));
        $this->targetQueue = $targetQueue;
    }

    public function handle(): void
    {
        $project = MonitoringProject::query()->find($this->projectId);
        if ($project === null || $this->regionIds === []) {
            return;
        }

        $engines = $project->searchengines()
            ->whereIn('id', $this->regionIds)
            ->with('location')
            ->get();

        if ($engines->isEmpty()) {
            return;
        }

        $project->keywords()->orderBy('id')->chunkById(100, function ($keywords) use ($engines) {
            foreach ($engines as $engine) {
                foreach ($keywords as $keyword) {
                    dispatch((new AutoUpdatePositionQueue($keyword, $engine))->onQueue($this->targetQueue));
                }
            }
        });
    }
}
