<?php

use Hn\HauptsacheVideo\Converter;
use Hn\HauptsacheVideo\Preset;

if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}

call_user_func(function () {
    $conf = class_exists(\TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class)
        ? \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class)->get('hauptsache_video')
        : unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['hauptsache_video']);

    // parse performance syntax
    // a great chart comparing encoding speeds vs quality can be found here:
    // https://blogs.gnome.org/rbultje/2015/09/28/vp9-encodingdecoding-performance-vs-hevch-264/
    $performanceOptions = [
        // @formatter:off
        'h264' => ['ultrafast', 'medium', 'slow', 'veryslow', 'slow', 'veryslow'][$conf['preset'] ?? 2],
        'vp0' =>  [ null      ,  null   ,  null ,  null     ,  2    ,  1        ][$conf['preset'] ?? 2],
        // @formatter:on
    ];

    $h264Defaults = [Preset\H264Preset::class, ['preset' => $performanceOptions['h264']]];
    $aacDefaults = ['fdkAvailable' => !empty($conf['fdkAvailable']) || ($conf['converter'] ?? '') === 'CloudConvert'];
    $mp4Defaults = ['-movflags', '+faststart', '-map_metadata', '-1', '-f', 'mp4'];
    $vp9Defaults = ['speed' => $performanceOptions['vp9']];
    $webmDefaults = ['-map_metadata', '-1', '-f', 'webm'];

    // mp4 general
    // it should work almost anywhere ~ except maybe old low-cost android devices and feature phones
    if (empty($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['hauptsache_video']['formats']['mp4:default'])) {
        if (isset($performanceOptions['h264'])) {
            $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['hauptsache_video']['formats']['mp4:default'] = [
                'fileExtension' => 'mp4',
                'mimeType' => 'video/mp4',
                'video' => $h264Defaults,
                'audio' => [Preset\AacPreset::class, $aacDefaults],
                'additionalParameters' => $mp4Defaults,
            ];
        }
    }

    // webm video
    // higher efficiency than h264 but lacks support in safari
    if (empty($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['hauptsache_video']['formats']['webm:default'])) {
        if (isset($performanceOptions['vp9'])) {
            $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['hauptsache_video']['formats']['webm:default'] = [
                'fileExtension' => 'webm',
                'mimeType' => 'video/webm',
                'video' => [Preset\VP9Preset::class, $vp9Defaults],
                'audio' => [Preset\OpusPreset::class],
                'additionalParameters' => $webmDefaults,
            ];
        }
    }

    // m4a audio
    // this should be your choice for audio files
    // ~ it is more efficient than mp3 and has nearly the same browser support
    if (empty($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['hauptsache_video']['formats']['m4a:default'])) {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['hauptsache_video']['formats']['m4a:default'] = [
            'fileExtension' => 'm4a',
            'mimeType' => 'audio/mp4',
            'audio' => [Preset\AacPreset::class, $aacDefaults],
            'additionalParameters' => $mp4Defaults,
        ];
    }

    // this is the default format list used for video
    // the order will be the same as in the final source definition
    // it should reflect which format the browser should choose
    if (empty($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['hauptsache_video']['default_video_formats'])) {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['hauptsache_video']['default_video_formats'] = [];

        if (isset($performanceOptions['vp9'])) {
            $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['hauptsache_video']['default_video_formats']['webm'] = ['priority' => -1];
        }

        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['hauptsache_video']['default_video_formats']['mp4'] = [];
    }

    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['hauptsache_video']['video_converters'] = [
        'LocalFFmpeg' => [Converter\LocalFFmpegConverter::class],
        'CloudConvert' => [Converter\CloudConvertConverter::class, $conf['cloudConvertApiKey'] ?? ''],
    ];

    if (!isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['hauptsache_video']['video_converter'])) {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['hauptsache_video']['video_converter']
            = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['hauptsache_video']['video_converters'][$conf['converter']];
    }

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['processingTaskTypes']['Video.CropScale']
        = \Hn\HauptsacheVideo\Processing\VideoProcessingTask::class;
    $GLOBALS['TYPO3_CONF_VARS']['FE']['eID_include']['hauptsache_video']
        = \Hn\HauptsacheVideo\Processing\VideoProcessingEid::class . '::process';

    $dispatcher = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\SignalSlot\Dispatcher::class);

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
        $isDev = \TYPO3\CMS\Core\Utility\GeneralUtility::getApplicationContext()->isDevelopment();
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
