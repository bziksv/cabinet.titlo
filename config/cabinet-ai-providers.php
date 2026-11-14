<?php

/**
 * Провайдеры LLM и модули, которые ходят в AI.
 * Выбор провайдера на модуль — в «Управление AI» (таблица ai_provider_settings).
 */
return [
    'version' => '1.0.0',

    'default_provider' => 'plusvibeapi',

    'providers' => [
        'plusvibeapi' => [
            'title' => 'PlusVibe API',
            'hint' => 'https://plusvibeapi.ru — OpenAI-compatible, оплата в ₽',
            'config_token' => 'plusvibeapi.token',
            'base_uri' => 'plusvibeapi.base_uri',
            'model' => 'plusvibeapi.model',
            'fallback_model' => 'plusvibeapi.fallback_model',
        ],
        'deepseek' => [
            'title' => 'DeepSeek',
            'hint' => 'api.deepseek.com напрямую',
            'config_token' => 'deepseek.token',
            'base_uri' => null, // hardcoded in client
            'model' => 'deepseek.model',
            'fallback_model' => null,
        ],
    ],

    'modules' => [
        'ai_generation' => [
            'title' => 'AI-генерация',
            'hint' => 'Кабинет /ai-generation и Integration API ai/generate',
        ],
        'site_audit_action_plan' => [
            'title' => 'Аудит сайта — план действий',
            'hint' => 'AI-резюме плана работ в Site Audit',
        ],
    ],
];
