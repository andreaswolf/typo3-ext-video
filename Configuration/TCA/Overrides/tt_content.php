<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

// TODO move that into pagets
ExtensionManagementUtility::addTcaSelectItem('tt_content', 'CType', [
    'video testing utility', 'video'
]);

ExtensionManagementUtility::addPiFlexFormValue('', 'FILE:EXT:video/Configuration/FlexForm/TestElement.xml', 'video');

$GLOBALS['TCA']['tt_content']['types']['video'] = [
    'showitem' => implode(',', [
        '--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general',
        '--palette--;;general',
        'pi_flexform,media',

        '--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access',
        '--palette--;;hidden',
        '--palette--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:palette.access;access'
    ])
];
