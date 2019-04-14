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
                    ['new', \Hn\HauptsacheVideo\Processing\VideoProcessingTask::STATUS_NEW],
                    ['finished', \Hn\HauptsacheVideo\Processing\VideoProcessingTask::STATUS_FINISHED],
                    ['failed', \Hn\HauptsacheVideo\Processing\VideoProcessingTask::STATUS_FAILED],
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
