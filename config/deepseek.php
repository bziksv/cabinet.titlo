<?php

return [
    'token' => env('DEEPSEEK_TOKEN', null),
    'base_uri' => env('DEEPSEEK_BASE_URI', 'https://api.deepseek.com'),
    'model' => env('DEEPSEEK_MODEL', 'deepseek-chat'),
];
