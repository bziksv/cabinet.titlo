<?php

namespace App\Support;

use App\ClusterQueue;
use Illuminate\Support\Facades\DB;

class ClusterProgress
{
  /**
   * @return array{queue_count:int,phrases_done:int,phrases_pending:int,phrases_total:int,waiting_in_queue:bool}
   */
  public static function snapshot(string $progressId): array
  {
    $done = (int) ClusterQueue::where('progress_id', $progressId)->count();
    $pending = (int) DB::table('jobs')
      ->where('queue', ClusterQueues::name('child'))
      ->where('payload', 'like', '%' . $progressId . '%')
      ->count();
    $total = max($done + $pending, $done);

    return [
      'queue_count' => $done,
      'phrases_done' => $done,
      'phrases_pending' => $pending,
      'phrases_total' => $total,
      'waiting_in_queue' => $done === 0 && $pending > 0,
    ];
  }
}
