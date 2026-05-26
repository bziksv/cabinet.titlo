<?php

return [
    'version' => '1.5.4s',

    'limits' => [
        'max_projects' => 20,
        'max_texts_per_project' => 30,
        'max_user_presets' => 20,
    ],

    'presets' => [
        'builtin' => require __DIR__ . '/html-editor-builtin-presets.php',
    ],

    'demo' => [
        'max_chars' => 10_000,
    ],
];
