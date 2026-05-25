<?php

namespace App\Support;

class ClusterQueues
{
  /** @var array<string, string> */
  protected const NAMES = [
    'main' => 'main_cluster',
    'child' => 'child_cluster',
    'wait' => 'cluster_wait',
  ];

  public static function name(string $key): string
  {
    $base = self::NAMES[$key] ?? $key;

    return (string) config('cabinet-cluster.queue_prefix', '') . $base;
  }

  /** @return string[] */
  public static function all(): array
  {
    return array_map([self::class, 'name'], array_keys(self::NAMES));
  }
}
