<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'hauptsache video',
    'description' => 'Convert videos in typo3',
    'category' => 'fe',
    'author' => 'Marco Pfeiffer',
    'author_email' => 'marco@hauptsache.net',
    'author_company' => 'hauptsache.net',
    'constraints' => [
        'depends' => [],
        'conflicts' => [],
        'suggests' => [],
    ],
    'state' => 'alpha',
    'clearCacheOnLoad' => true,
    'autoload' => [
        'psr-4' => [
            'Hn\\HauptsacheVideo\\' => 'Classes',
        ],
    ],
    'autoload-dev' => [
        'psr-4' => [
            'Hn\\HauptsacheVideo\\Tests\\' => 'Tests',
        ],
    ],
];
