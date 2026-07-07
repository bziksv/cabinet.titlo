<?php

/**
 * Админ-страница /admin/supervisor — статус воркеров supervisord.
 *
 * @see App\Services\Supervisor\SupervisorAdminService
 */
return [
    'version' => '1.4.0s',

    /** Порог «очередь растёт»: pending jobs на один RUNNING-воркер */
    'capacity_backlog_per_worker' => (int) env('SUPERVISOR_CAPACITY_BACKLOG_PER_WORKER', 3),

    /** Занятость воркеров (% reserved/running), выше — «все заняты» */
    'capacity_busy_percent' => (int) env('SUPERVISOR_CAPACITY_BUSY_PERCENT', 75),

    /** false — страница только для чтения с подсказкой по установке */
    'enabled' => filter_var(env('SUPERVISOR_ADMIN_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    /** Команда supervisorctl (при необходимости: "sudo /usr/bin/supervisorctl") */
    'supervisorctl' => env('SUPERVISORCTL_BIN', '/usr/bin/supervisorctl'),

    /**
     * Разрешённые имена процессов (glob). Только они — start/stop/restart.
     * Пример: cabinet-titlo-* — все программы из deploy/supervisor/cabinet-titlo.conf.example
     */
    'allowed_programs' => array_filter(array_map('trim', explode(',', env(
        'SUPERVISOR_ADMIN_ALLOWED',
        'cabinet-titlo-*'
    )))),

    /** Подсказка в UI — путь к нашему conf (не трогаем FastPanel/nginx) */
    'config_hint' => env(
        'SUPERVISOR_CONFIG_HINT',
        '/etc/supervisor/conf.d/cabinet-titlo.conf'
    ),

    /**
     * Модуль кабинета для программы supervisord (label — ключ __(), route — имя route()).
     *
     * @see App\Services\Supervisor\SupervisorAdminService::moduleForProgram()
     */
    'program_modules' => [
        'cabinet-titlo-default' => ['label' => 'Queue management', 'route' => 'admin.queue.index'],
        'cabinet-titlo-cluster-child' => ['label' => 'Cluster', 'route' => 'cluster'],
        'cabinet-titlo-cluster-main' => ['label' => 'Cluster', 'route' => 'cluster'],
        'cabinet-titlo-cluster-wait' => ['label' => 'Cluster', 'route' => 'cluster'],
        'cabinet-titlo-position' => ['label' => 'Position monitoring', 'route' => 'monitoring.v2'],
        'cabinet-titlo-relevance' => ['label' => 'Relevance', 'route' => 'relevance-analysis'],
        'cabinet-titlo-monitoring-helper' => ['label' => 'Position monitoring', 'route' => 'monitoring.v2'],
        'cabinet-titlo-monitoring-change-dates' => ['label' => 'Position monitoring', 'route' => 'monitoring.v2'],
        'cabinet-titlo-monitoring-wait' => ['label' => 'Position monitoring', 'route' => 'monitoring.v2'],
        'cabinet-titlo-monitoring-competitors-stat' => ['label' => 'Position monitoring', 'route' => 'monitoring.v2'],
        'cabinet-titlo-competitor-analyse' => ['label' => 'Competitor analysis', 'route' => 'competitor.analysis'],
        'cabinet-titlo-ai-generation' => ['label' => 'Supervisor module ai generation', 'route' => 'ai.generation.story'],
        'cabinet-titlo-websockets' => ['label' => 'Supervisor module websockets', 'route' => null],
    ],

    /**
     * Очереди Laravel, которые слушает каждая программа (для сопоставления с jobs).
     * numprocs_lk — эталон с lk.redbox.su для сравнения.
     */
    'program_capacity' => [
        'cabinet-titlo-default' => [
            'queues' => ['default', 'cluster_high', 'high', 'medium'],
            'numprocs_lk' => 6,
        ],
        'cabinet-titlo-cluster-child' => [
            'queues' => ['child_cluster'],
            'numprocs_lk' => 6,
        ],
        'cabinet-titlo-cluster-main' => [
            'queues' => ['main_cluster'],
            'numprocs_lk' => 3,
        ],
        'cabinet-titlo-cluster-wait' => [
            'queues' => ['cluster_wait'],
            'numprocs_lk' => 6,
        ],
        'cabinet-titlo-position' => [
            'queues' => ['position_high', 'position_low'],
            'numprocs_lk' => 6,
        ],
        'cabinet-titlo-relevance' => [
            'queues' => ['relevance_high_priority', 'relevance_medium_priority', 'relevance_normal_priority'],
            'numprocs_lk' => 6,
        ],
        'cabinet-titlo-monitoring-helper' => [
            'queues' => ['monitoring_helper'],
            'numprocs_lk' => 5,
        ],
        'cabinet-titlo-monitoring-change-dates' => [
            'queues' => ['monitoring_change_dates'],
            'numprocs_lk' => 2,
        ],
        'cabinet-titlo-monitoring-wait' => [
            'queues' => ['monitoring_wait'],
            'numprocs_lk' => 2,
        ],
        'cabinet-titlo-monitoring-competitors-stat' => [
            'queues' => ['monitoring_competitors_stat'],
            'numprocs_lk' => 1,
        ],
        'cabinet-titlo-competitor-analyse' => [
            'queues' => ['competitor_analyse'],
            'numprocs_lk' => 5,
        ],
        'cabinet-titlo-ai-generation' => [
            'queues' => ['ai_generation'],
            'numprocs_lk' => 5,
        ],
        'cabinet-titlo-websockets' => [
            'queues' => [],
            'numprocs_lk' => 1,
        ],
    ],

    /** Логи воркеров относительно корня проекта (storage/logs/...) */
    'log_files' => [
        'cabinet-titlo-default' => 'storage/logs/supervisor-default.log',
        'cabinet-titlo-cluster-child' => 'storage/logs/supervisor-cluster-child.log',
        'cabinet-titlo-cluster-main' => 'storage/logs/supervisor-cluster-main.log',
        'cabinet-titlo-cluster-wait' => 'storage/logs/supervisor-cluster-wait.log',
        'cabinet-titlo-position' => 'storage/logs/supervisor-position.log',
        'cabinet-titlo-relevance' => 'storage/logs/supervisor-relevance.log',
        'cabinet-titlo-monitoring-helper' => 'storage/logs/supervisor-monitoring-helper.log',
        'cabinet-titlo-monitoring-change-dates' => 'storage/logs/supervisor-monitoring-change-dates.log',
        'cabinet-titlo-monitoring-wait' => 'storage/logs/supervisor-monitoring-wait.log',
        'cabinet-titlo-monitoring-competitors-stat' => 'storage/logs/supervisor-monitoring-competitors-stat.log',
        'cabinet-titlo-competitor-analyse' => 'storage/logs/supervisor-competitor-analyse.log',
        'cabinet-titlo-ai-generation' => 'storage/logs/supervisor-ai-generation.log',
        'cabinet-titlo-websockets' => 'storage/logs/supervisor-websockets.log',
    ],
];
