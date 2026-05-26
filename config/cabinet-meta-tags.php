<?php

return [
    'version' => '1.2.17s',

    /** Поля «мета» при сравнении снимков — красная подсветка; остальное (h1, h2, a…) — жёлтая */
    'compare_meta_fields' => ['title', 'description', 'keywords', 'canonical', 'noindex', 'robots'],

    /** Демо на datagon.ru — POST api/demo/proverka-meta-tegov-online/run */
    'demo' => [
        'module' => 'proverka-meta-tegov-online',
        'max_runs_per_day' => 5,
        'default_tags' => ['title', 'description', 'h1', 'canonical', 'noindex', 'robots'],
        'length' => [
            ['id' => 'title', 'input' => ['min' => 70, 'max' => 80]],
            ['id' => 'description', 'input' => ['min' => 180, 'max' => 300]],
        ],
        'locked_tags' => ['h2', 'h3', 'a', 'keywords'],
    ],
];
