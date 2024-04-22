<?php

if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}


if (TYPO3_MODE === 'BE') {
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
        'Hn.Video',
        'system',       // Main area
        'mod1',         // Name of the module
        '',             // Position of the module
        [          // Allowed controller action combinations
            'Task' => 'list, delete',
        ],
        [          // Additional configuration
            'access' => 'user,group',
            'icon' => 'EXT:recordlist/Resources/Public/Icons/module-list.svg',
            'labels' => 'LLL:EXT:video/Resources/Private/Language/locallang_mod1.xlf',
        ]
    );
}
