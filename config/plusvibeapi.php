<?php

return [
    'token' => env('PLUSVIBEAPI_TOKEN', null),
    'base_uri' => env('PLUSVIBEAPI_BASE_URI', 'https://plusvibeapi.ru/v1'),
    'model' => env('PLUSVIBEAPI_MODEL', 'deepseek-v4.1-flash-1017'),
    'fallback_model' => env('PLUSVIBEAPI_FALLBACK_MODEL', 'deepseek-v4.1-flash'),
];
