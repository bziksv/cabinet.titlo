<?php

namespace App\Jobs\Cluster;

use App\Classes\Xml\RiverFacade;
use App\Cluster;
use App\ClusterLimit;
use App\Support\ClusterAnalysisDebugLog;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ClusterQueue implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $progress;

    protected $link;

    protected $key;

    protected $phrase;

    protected $type;

    protected $cluster;

    public function __construct(Cluster $cluster, $key, $phrase)
    {
        $this->cluster = $cluster;
        $this->key = $key;
        $this->phrase = $phrase;
    }

    public function handle()
    {
        $progressId = $this->cluster->getProgressId();
        RiverFacade::setClusterDebugProgressId($progressId);
        ClusterAnalysisDebugLog::info($progressId, 'job.phrase.start', [
            'key' => $this->key,
            'phrase' => $this->phrase,
        ]);

        $clusterArrays = new \App\ClusterQueue();
        $river = new RiverFacade($this->cluster->getRegion());

        $this->cluster->getXml()->setQuery($this->phrase);
        $sites = $this->cluster->getXml()->getXMLResponse();

        if ($this->cluster->getSearchPhrases()) {
            $river->setQuery('"' . $this->phrase . '"');
            $phrase = $river->riverRequest(false);
            usleep(250000);
        }

        if ($this->cluster->getSearchTarget()) {
            $river->setQuery('"!' . implode(' !', explode(' ', $this->phrase)) . '"');
            $target = $river->riverRequest(false);
            usleep(250000);
        }

        if ($this->cluster->getSearchBase()) {
            $river->setQuery($this->phrase);
            $based = $river->riverRequest();
            $baseFormEq = ($based['phrase'] ?? $this->phrase) === $this->phrase;
            usleep(250000);
        } else {
            $baseFormEq = true;
        }

        if ($this->cluster->getSearchRelevance()) {
            $this->cluster->getXml()->setQuery("$this->phrase site:" . $this->cluster->getHost());
            $this->cluster->getXml()->setCount(10);
            $relevance = $this->cluster->getXml()->getXMLResponse($this->cluster->getSearchEngine());
            if (count($relevance) > 3) {
                $relevance = array_slice($relevance, 0, 3);
            }
        }

        $clusterArrays->json = json_encode([
            $this->key => [
                $this->phrase => [
                    'based' => $based ?? 0,
                    'phrased' => $phrase ?? 0,
                    'target' => $target ?? 0,
                    'relevance' => $relevance ?? 0,
                    'basedNormal' => $baseFormEq,
                    'sites' => $sites,
                ]
            ]
        ]);

        $clusterArrays->progress_id = $progressId;
        $clusterArrays->save();

        ClusterAnalysisDebugLog::info($progressId, 'job.phrase.done', [
            'key' => $this->key,
            'based' => is_array($based ?? null) ? ($based['number'] ?? 0) : ($based ?? 0),
            'phrased' => is_array($phrase ?? null) ? ($phrase['number'] ?? 0) : ($phrase ?? 0),
            'target' => is_array($target ?? null) ? ($target['number'] ?? 0) : ($target ?? 0),
            'sites' => is_array($sites) ? count($sites) : 0,
        ]);
    }

    public function failed(\Throwable $exception)
    {
        ClusterAnalysisDebugLog::error($this->cluster->getProgressId(), 'job.phrase.failed', [
            'key' => $this->key,
            'phrase' => $this->phrase,
            'message' => $exception->getMessage(),
        ]);

        Log::debug('cluster queue bug report', [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);
    }
}
