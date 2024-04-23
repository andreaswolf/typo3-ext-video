<?php

namespace Hn\Video\Processing;

use Hn\Video\FormatRepository;
use Hn\Video\Rendering\VideoTagRenderer;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\SignalSlot\Dispatcher;

final class VideoProcessor
{
    /**
     * Creates file variants for the given file, according to the options passed in {@see $options}.
     *
     * @param array{formats?: list<string>} $options
     * @return list<ProcessedFile>
     */
    public function createVideoVariants(File $file, array $options)
    {
        $videos = [];
        $configurations = $this->getConfigurations($options);
        foreach ($configurations as $configuration) {
            $video = $file->process('Video.CropScale', $configuration);
            if (!$video->exists()) {
                continue;
            }

            $videos[] = $video;
        }

        return $videos;
    }

    /**
     * TODO refine the return type
     *
     * @param array{formats?: list<string>} $options
     * @return list<array{format: string}>
     */
    protected function getConfigurations(array $options): array
    {
        $formats = $options['formats'] ?? $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['video']['default_video_formats'];
        self::dispatch('formats', [&$formats], func_get_args());

        $configurations = [];
        foreach ($formats as $formatKey => $formatOptions) {
            $configurations[] = FormatRepository::normalizeOptions(array_replace(
                $options,
                ['format' => $formatKey],
                $formatOptions
            ));
        }

        return $configurations;
    }

    private static function dispatch(string $name, array $arguments, array ...$furtherArguments): void
    {
        if (!empty($furtherArguments)) {
            $arguments = array_merge($arguments, ...$furtherArguments);
        }

        GeneralUtility::makeInstance(Dispatcher::class)->dispatch(VideoTagRenderer::class, $name, $arguments);
    }
}
