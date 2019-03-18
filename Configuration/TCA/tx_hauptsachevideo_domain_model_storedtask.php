<?php

return [
    'ctrl' => [
        'title' => 'Video transcoding task',
        'label' => 'file',
        'crdate' => 'crdate',
        'tstamp' => 'tstamp',
        'adminOnly' => true,
        'rootLevel' => true,
        //'hideTable' => true,
        'default_sortby' => 'uid DESC',
    ],
    'columns' => [
        'file' => [
            'label' => 'File',
            'config' => [
                'type' => 'input',
                'eval' => 'int',
            ],
        ],
        'configuration' => [
            'label' => 'Configuration',
            'config' => [
                'type' => 'text',
            ],
        ],
        'status' => [
            'label' => 'Status',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['new', \Hn\HauptsacheVideo\Domain\Model\StoredTask::STATUS_NEW],
                    ['finished', \Hn\HauptsacheVideo\Domain\Model\StoredTask::STATUS_FINISHED],
                    ['failed', \Hn\HauptsacheVideo\Domain\Model\StoredTask::STATUS_FAILED],
                ],
            ],
        ],
        'log' => [
            'label' => 'Status',
            'config' => [
                'type' => 'text',
            ],
        ],
    ],
    'types' => [
        '0' => [
            'showitem' => 'file, configuration, status, log',
        ],
    ],
];
