<?php

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTcaSelectItem('tt_content', 'CType', [
    'hauptsache_video testing utility', 'hauptsache_video'
]);

$GLOBALS['TCA']['tt_content']['types']['hauptsache_video'] = [
    'showitem' => implode(',', [
        '--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general',
        '--palette--;;general',
        'bodytext,media',

        '--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access',
        '--palette--;;hidden',
        '--palette--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:palette.access;access'
    ]),
    'columnsOverrides' => [
        'bodytext' => [
            'default' => json_encode([
                ['format' => 'mp4:default', 'video' => ['quality' => 1.0], 'audio' => ['quality' => 1.0]],
                ['format' => 'mp4:default', 'video' => ['quality' => 0.9], 'audio' => ['quality' => 0.9]],
                ['format' => 'mp4:default', 'video' => ['quality' => 0.8], 'audio' => ['quality' => 0.8]],
                ['format' => 'mp4:default', 'video' => ['quality' => 0.7], 'audio' => ['quality' => 0.7]],
                ['format' => 'mp4:default', 'video' => ['quality' => 0.6], 'audio' => ['quality' => 0.6]],
                ['format' => 'mp4:default', 'video' => ['quality' => 0.5], 'audio' => ['quality' => 0.5]],
            ], JSON_PRETTY_PRINT),
        ]
    ]
];
