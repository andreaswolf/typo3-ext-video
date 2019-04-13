<?php

use Hn\HauptsacheVideo\Preset;

if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}

$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['hauptsache_video']['formats'] = [
    'mp4:default' => [
        'fileExtension' => 'mp4',
        'video' => [Preset\H264Preset::class, ['profile' => 'main', 'level' => 31, 'performance' => 'fast']],
        'audio' => [Preset\AacPreset::class],
        'additionalParameters' => ['-movflags', '+faststart', '-f', 'mp4'],
    ],
    'mp4:high' => [
        'fileExtension' => 'mp4',
        'video' => [Preset\H264Preset::class, ['profile' => 'high', 'level' => 41, 'performance' => 'fast']],
        'audio' => [Preset\AacPreset::class],
        'additionalParameters' => ['-movflags', '+faststart', '-f', 'mp4'],
    ],
    'm4a:default' => [
        'fileExtension' => 'm4a',
        'audio' => [Preset\AacPreset::class],
        'additionalParameters' => ['-movflags', '+faststart', '-f', 'm4a'],
    ],
];

if (!isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['hauptsache_video']['video_converter'])) {
    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['hauptsache_video']['video_converter'] =
        [\Hn\HauptsacheVideo\Converter\LocalVideoConverter::class];
}

$GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['processingTaskTypes']['Video.CropScale']
    = \Hn\HauptsacheVideo\Processing\VideoProcessingTask::class;

call_user_func(function () {
    $dispatcher = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\SignalSlot\Dispatcher::class);
    $dispatcher->connect(
        \TYPO3\CMS\Core\Resource\ResourceStorage::class,
        \TYPO3\CMS\Core\Resource\Service\FileProcessingService::SIGNAL_PreFileProcess,
        \Hn\HauptsacheVideo\Slot\FileProcessingServiceSlot::class,
        'preFileProcess'
    );
});

\TYPO3\CMS\Core\Resource\Rendering\RendererRegistry::getInstance()->registerRendererClass(\Hn\HauptsacheVideo\Rendering\VideoTagRenderer::class);

if (TYPO3_MODE === 'BE') {
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers'][] =
        \Hn\HauptsacheVideo\Command\VideoCommandController::class;
}

if (true/*todo enable condition*/) {
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig(<<<PageTSConfig
mod.wizards.newContentElement.wizardItems.special.elements.hauptsache_video {
    iconIdentifier = content-media
    title = hauptsache_video testing utility
    description = This elements helps you find the right options for video compression.
    tt_content_defValues {
        CType = hauptsache_video
    }
}
mod.wizards.newContentElement.wizardItems.special.show := addToList(hauptsache_video)
PageTSConfig
    );

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScriptSetup(<<<TypoScript
tt_content.hauptsache_video = USER
tt_content.hauptsache_video.userFunc = Hn\HauptsacheVideo\TestContentElement->render
tt_content.hauptsache_video.configurations.data = flexform:pi_flexform:settings.options
TypoScript
    );
}
