<?php

if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}

$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['hauptsache_video']['presets'] = [
    'mp4' => [\Hn\HauptsacheVideo\Presets\Mp4H264Preset::class, 'main', '3.1', 4.0, 30, 'fast'],
];

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
