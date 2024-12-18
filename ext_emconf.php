<?php
$EM_CONF[$_EXTKEY] = [
    'title' => 'Video',
    'description' => 'Video processing in typo3',
    'category' => 'plugin',
    'author' => 'Marco Pfeiffer',
    'author_email' => 'marco@hauptsache.net',
    'author_company' => 'hauptsache.net',
    'state' => 'stable',
    'version' => '2.0.0',
    'constraints' => [
        'depends' => [
            'php' => '8.0.0-8.99.99',
            'typo3' => '12.4.0-13.4.99',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
];