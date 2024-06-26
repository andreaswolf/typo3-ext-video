<?php

namespace Hn\Video\Rendering;

use Hn\Video\FormatRepository;
use Hn\Video\TypeUtility;
use Hn\Video\ViewHelpers\ProgressViewHelper;
use TYPO3\CMS\Core\Resource;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\Rendering\FileRendererInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\SignalSlot\Dispatcher;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class VideoTagRenderer implements FileRendererInterface
{
    /**
     * Returns the priority of the renderer
     * This way it is possible to define/overrule a renderer
     * for a specific file type/context.
     *
     * For example create a video renderer for a certain storage/driver type.
     *
     * Should be between 1 and 100, 100 is more important than 1
     *
     * @return int
     */
    public function getPriority()
    {
        return 2; // +1 over the typo3 native renderer
    }

    /**
     * Check if given File(Reference) can be rendered
     *
     * @param Resource\FileInterface $file File or FileReference to render
     *
     * @return bool
     */
    public function canRender(FileInterface $file)
    {
        return TypeUtility::inList($file->getMimeType(), TypeUtility::VIDEO_MIME_TYPES)
            && $file->getProperty('width') && $file->getProperty('height');
    }

    /**
     * Render for given File(Reference) HTML output
     *
     * @param int|string $width TYPO3 known format; examples: 220, 200m or 200c
     * @param int|string $height TYPO3 known format; examples: 220, 200m or 200c
     * @param bool $usedPathsRelativeToCurrentScript See $file->getPublicUrl()
     *
     * @return string
     */
    public function render(FileInterface $file, $width, $height, array $options = [], $usedPathsRelativeToCurrentScript = false)
    {
        $attributes = [];

        $width = $width ?: $file->getProperty('width');
        $height = $height ?: $file->getProperty('height');

        if (preg_match('/m$/', $width)) {
            $width = min($width, $height * $file->getProperty('width') / $file->getProperty('height'));
        }

        if (preg_match('/m$/', $height)) {
            $height = min($height, $width * $file->getProperty('height') / $file->getProperty('width'));
        }

        $attributes['width'] = 'width="' . round($width) . '"';
        $attributes['height'] = 'height="' . round($height) . '"';

        $autoplay = intval($options['autoplay'] ?? $file->getProperty('autoplay'));
        self::dispatch('autoplay', [&$autoplay], func_get_args());

        if ($autoplay > 0) {
            $attributes['autoplay'] = 'autoplay';
        }

        if ($options['muted'] ?? $autoplay > 0) {
            $attributes['muted'] = 'muted';
        }

        if ($options['loop'] ?? $autoplay > 1) {
            $attributes['loop'] = 'loop';
        }

        if ($options['controls'] ?? $autoplay < 3) {
            $attributes['controls'] = 'controls';
        }

        if ($options['playsinline'] ?? $autoplay >= 1) {
            $attributes['playsinline'] = 'playsinline';
        }

        foreach ($this->getAttributes() as $key) {
            if (!empty($options[$key])) {
                $attributes[$key] = $key . '="' . htmlspecialchars($options[$key]) . '"';
            }
        }

        [$sources, $videos] = $this->buildSources($file, $options, $usedPathsRelativeToCurrentScript);
        self::dispatch('beforeTag', [&$attributes, &$sources], func_get_args());

        if (empty($sources) && ($options['progress'] ?? true)) {
            $sources[] = ProgressViewHelper::renderHtml($videos);
            $tag = sprintf('<div %s>%s</div>', implode(' ', $attributes), implode('', $sources));
            self::dispatch('afterProgressTag', [&$tag, $attributes, $sources], func_get_args());
        } else {
            $tag = sprintf('<video %s>%s</video>', implode(' ', $attributes), implode('', $sources));
            self::dispatch('afterTag', [&$tag, $attributes, $sources], func_get_args());
        }

        return $tag;
    }

    protected function buildSources(FileInterface $file, array $options, $usedPathsRelativeToCurrentScript): array
    {
        // do not process a processed file
        if ($file instanceof ProcessedFile) {
            if ($GLOBALS['TSFE'] instanceof TypoScriptFrontendController) {
                $GLOBALS['TSFE']->addCacheTags(["processed_video_{$file->getUid()}"]);
            }

            $source = sprintf(
                '<source src="%s" type="%s" />',
                htmlspecialchars($file->getPublicUrl($usedPathsRelativeToCurrentScript)),
                htmlspecialchars($file->getMimeType())
            );

            return [[$source], [$file]];
        }

        if ($file instanceof FileReference) {
            $file = $file->getOriginalFile();
        }

        if (!$file instanceof File) {
            $type = is_object($file) ? get_class($file) : gettype($file);
            throw new \RuntimeException('Expected ' . File::class . ", got $type");
        }

        $sources = [];
        $videos = [];

        $configurations = $this->getConfigurations($options);
        foreach ($configurations as $configuration) {
            $videos[] = $video = $file->process('Video.CropScale', $configuration);
            if (!$video->exists()) {
                continue;
            }

            if ($GLOBALS['TSFE'] instanceof TypoScriptFrontendController) {
                $GLOBALS['TSFE']->addCacheTags(["processed_video_{$video->getUid()}"]);
            }

            $sources[] = sprintf(
                '<source src="%s" type="%s" />',
                htmlspecialchars($video->getPublicUrl($usedPathsRelativeToCurrentScript)),
                htmlspecialchars($video->getMimeType())
            );
        }

        return [$sources, $videos];
    }

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

    protected function getAttributes(): array
    {
        return [
            'class',
            'dir',
            'id',
            'lang',
            'style',
            'title',
            'accesskey',
            'tabindex',
            'onclick',
            'controlsList',
            'preload'
        ];
    }

    private static function dispatch(string $name, array $arguments, array ...$furtherArguments): void
    {
        if (!empty($furtherArguments)) {
            $arguments = array_merge($arguments, ...$furtherArguments);
        }

        GeneralUtility::makeInstance(Dispatcher::class)->dispatch(self::class, $name, $arguments);
    }
}
