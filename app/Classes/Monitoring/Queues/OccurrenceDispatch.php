<?php


namespace App\Classes\Monitoring\Queues;

use App\Classes\Monitoring\PositionLimit;
use App\Jobs\OccurrenceQueue;
use App\User;

class OccurrenceDispatch extends QueueDispatcher
{
    private $typeYW = 3;

    public function __construct(int $user, string $queue)
    {
        $this->user = User::find($user);
        $this->queue = $queue;
    }

    public function dispatch()
    {
        $queries = $this->getData();
        $this->countOff = count($queries) * $this->typeYW;

        if (!$this->reserveLimitsInternal()) {
            return;
        }

        foreach ($queries as $ar) {
            dispatch((new OccurrenceQueue($ar['query'], $ar['region']))->onQueue($this->queue));
        }
    }

    public function reserveForPairs(int $pairs): bool
    {
        $this->countOff = max(0, $pairs) * $this->typeYW;

        return $this->reserveLimitsInternal();
    }

    public function wasReserved(): bool
    {
        return (bool) $this->status;
    }

    private function reserveLimitsInternal(): bool
    {
        $limit = new PositionLimit($this->user['id']);
        if ($this->status = $limit->check($this->countOff)) {
            $this->msg = __('Job added to queue');

            return true;
        }

        $this->error = __('Limit exhausted');

        return false;
    }
}
