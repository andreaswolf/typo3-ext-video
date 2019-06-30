<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'video',
    'description' => 'videos in typo3',
    'category' => 'fe',
    'author' => 'Marco Pfeiffer',
    'author_email' => 'marco@hauptsache.net',
    'author_company' => 'hauptsache.net',
    'constraints' => [
        'depends' => [
            'typo3' => '8.7.10-9.5.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
    'state' => 'alpha',
    'clearCacheOnLoad' => true,
    'autoload' => [
        'psr-4' => [
            'Hn\\Video\\' => 'Classes',
        ],
    ],
    'autoload-dev' => [
        'psr-4' => [
            'Hn\\Video\\Tests\\' => 'Tests',
        ],
    ],
];
