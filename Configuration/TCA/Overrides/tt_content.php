<?php

// TODO move that into pagets
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTcaSelectItem('tt_content', 'CType', [
    'hauptsache_video testing utility', 'hauptsache_video'
]);

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPiFlexFormValue('', 'FILE:EXT:hauptsache_video/Configuration/FlexForm/TestElement.xml', 'hauptsache_video');

$GLOBALS['TCA']['tt_content']['types']['hauptsache_video'] = [
    'showitem' => implode(',', [
        '--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general',
        '--palette--;;general',
        'pi_flexform,media',

        '--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access',
        '--palette--;;hidden',
        '--palette--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:palette.access;access'
    ])
];
