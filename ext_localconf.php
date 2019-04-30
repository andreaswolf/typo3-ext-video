<?php

use Hn\HauptsacheVideo\Preset;
use Hn\HauptsacheVideo\Converter;
use TYPO3\CMS\Core\Utility\GeneralUtility;

if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}

call_user_func(function () {
    $conf = class_exists(\TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class)
        ? GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class)->get('hauptsache_video')
        : unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['hauptsache_video']);

    // a great chart comparing encoding speeds vs quality can be found here:
    // https://blogs.gnome.org/rbultje/2015/09/28/vp9-encodingdecoding-performance-vs-hevch-264/
    $h264Defaults = ['preset' => ['veryslow', 'slow', 'medium', 'ultrafast'][$conf['performance'] ?? 2]];
    $vp9Defaults = ['speed' => ([0, 1, 2, 5][$conf['performance'] ?? 2])];
    $aacDefaults = ['fdkAvailable' => !empty($conf['fdkAvailable']) || ($conf['converter'] ?? '') === 'CloudConvert'];
    $opusDefaults = [];
    $mp4Defaults = ['-movflags', '+faststart', '-map_metadata', '-1', '-f', 'mp4'];
    $webmDefaults = ['-map_metadata', '-1', '-f', 'webm'];
    if (($conf['performance'] ?? 1) >= 4) {
        array_push($mp4Defaults, '-sws_flags', 'neighbor');
    }

    // mp4 general
    // it should work almost anywhere ~ except maybe old low-cost android devices and feature phones
    // the level supports ~720p
    if (empty($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['hauptsache_video']['formats']['mp4:default'])) {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['hauptsache_video']['formats']['mp4:default'] = [
            'fileExtension' => 'mp4',
            'video' => [Preset\H264Preset::class, $h264Defaults],
            'audio' => [Preset\AacPreset::class, $aacDefaults],
            'additionalParameters' => $mp4Defaults,
        ];
    }

    // webm video
    // higher efficiency than h264 but lacks support in safari
    if (empty($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['hauptsache_video']['formats']['webm:default'])) {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['hauptsache_video']['formats']['webm:default'] = [
            'fileExtension' => 'webm',
            'video' => [Preset\VP9Preset::class, $vp9Defaults],
            'audio' => [Preset\OpusPreset::class, $opusDefaults],
            'additionalParameters' => $webmDefaults,
        ];
    }

    // m4a audio
    // this should be your choice for audio files
    // ~ it is more efficient than mp3 and has nearly the same browser support
    if (empty($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['hauptsache_video']['formats']['m4a:default'])) {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['hauptsache_video']['formats']['m4a:default'] = [
            'fileExtension' => 'mp4',
            'audio' => [Preset\AacPreset::class, $aacDefaults],
            'additionalParameters' => $mp4Defaults,
        ];
    }

    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['hauptsache_video']['video_converters'] = [
        'LocalFFmpeg' => [Converter\LocalFFmpegConverter::class],
        'CloudConvert' => [Converter\CloudConvertConverter::class, $conf['cloudconvertApiKey'] ?? ''],
    ];

    if (!isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['hauptsache_video']['video_converter'])) {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['hauptsache_video']['video_converter']
            = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['hauptsache_video']['video_converters'][$conf['converter']];
    }

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['processingTaskTypes']['Video.CropScale']
        = \Hn\HauptsacheVideo\Processing\VideoProcessingTask::class;
    $GLOBALS['TYPO3_CONF_VARS']['FE']['eID_include']['hauptsache_video']
        = \Hn\HauptsacheVideo\Processing\VideoProcessingEid::class . '::process';

    $dispatcher = GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\SignalSlot\Dispatcher::class);
    $dispatcher->connect(
        \TYPO3\CMS\Core\Resource\ResourceStorage::class,
        \TYPO3\CMS\Core\Resource\Service\FileProcessingService::SIGNAL_PreFileProcess,
        \Hn\HauptsacheVideo\Slot\FileProcessingServiceSlot::class,
        'preFileProcess'
    );

    $dispatcher->connect(
        \TYPO3\CMS\Core\Resource\Index\MetaDataRepository::class,
        'recordPostRetrieval',
        \Hn\HauptsacheVideo\Slot\MetaDataRepositorySlot::class,
        'recordPostRetrieval'
    );

    \TYPO3\CMS\Core\Resource\Rendering\RendererRegistry::getInstance()
        ->registerRendererClass(\Hn\HauptsacheVideo\Rendering\VideoTagRenderer::class);

    \TYPO3\CMS\Core\Resource\Index\ExtractorRegistry::getInstance()
        ->registerExtractionService(\Hn\HauptsacheVideo\VideoMetadataExtractor::class);

    if (TYPO3_MODE === 'BE') {
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers'][] =
            \Hn\HauptsacheVideo\Command\VideoCommandController::class;
    }

    if (empty($GLOBALS['TYPO3_CONF_VARS']['LOG']['Hn']['HauptsacheVideo'])) {
        $isDev = GeneralUtility::getApplicationContext()->isDevelopment();
        $GLOBALS['TYPO3_CONF_VARS']['LOG']['Hn']['HauptsacheVideo']['writerConfiguration'] = [
            $isDev ? \TYPO3\CMS\Core\Log\LogLevel::DEBUG : \TYPO3\CMS\Core\Log\LogLevel::INFO => [
                \TYPO3\CMS\Core\Log\Writer\FileWriter::class => [
                    'logFile' => 'typo3temp/logs/hauptsache_video.log',
                ],
            ],
        ];
    }

    if (!empty($conf['testElement'])) {
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
    };
});
