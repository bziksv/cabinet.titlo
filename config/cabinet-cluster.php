<?php

return [
  /**
   * Видимая версия модуля /cluster (badge в шапке карточки).
   * Журнал: datagon.ru/docs/cabinet-cluster-changelog.md
   */
  'version' => '2.27',

  /** Локально: параллельные воркеры child_cluster (scripts/dev-cluster-queue.sh). */
  'queue_workers' => (int) env('CLUSTER_QUEUE_WORKERS', env('APP_ENV', 'production') === 'local' ? 4 : 1),

  /**
   * Префикс очередей кластера. На local по умолчанию local_ — jobs не забирает прод-воркер lk.redbox.su.
   * Переопределение: CLUSTER_QUEUE_PREFIX= в .env
   */
  'queue_prefix' => env(
    'CLUSTER_QUEUE_PREFIX',
    env('APP_ENV', 'production') === 'local' ? 'local_' : ''
  ),

  /** Расширенный лог прогресса для admin / Super Admin (UI как competitor-analysis) */
  'debug_log' => env('CLUSTER_DEBUG_LOG', true),
  'debug_log_ttl_minutes' => (int) env('CLUSTER_DEBUG_LOG_TTL', 120),
  'debug_log_max_entries' => (int) env('CLUSTER_DEBUG_LOG_MAX', 250),

  /** Пресеты формы /cluster-v2 (фразы — resources/data/) */
  'presets' => [
    'kawe' => [
      'phrases_file' => resource_path('data/cluster-v2-preset-kawe.txt'),
      'domain' => 'kawe.su',
      'search_base' => true,
      'search_phrases' => true,
      'search_target' => true,
      'search_relevance' => true,
      'save' => '1',
      'send_message' => '1',
    ],
  ],

  /**
   * Демо на datagon.ru/klasterizator-klyuchevykh-slov/
   * POST /api/demo/klasterizator-klyuchevykh-slov/run|poll
   */
  'demo' => [
    'module_slug' => 'klasterizator-klyuchevykh-slov',
    'max_phrases' => 10,
    'min_phrases' => 3,
    'max_runs_per_day' => 2,
    'top_count' => 10,
    'poll_timeout_sec' => 240,
    'allowed_region_ids' => ['213', '2', '193', '65', '54'],
    'clustering_levels' => [
      ['value' => 'soft', 'label' => 'Soft (рекомендуемый)'],
      ['value' => 'light', 'label' => 'Light'],
    ],
    'user_id' => (int) env('CLUSTER_DEMO_USER_ID', 1),
  ],
];
