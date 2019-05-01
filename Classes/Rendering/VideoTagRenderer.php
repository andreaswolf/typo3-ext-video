<?php

namespace Hn\HauptsacheVideo\Rendering;


use Hn\HauptsacheVideo\FormatRepository;
use Hn\HauptsacheVideo\VideoMetadataExtractor;
use TYPO3\CMS\Core\Resource;

class VideoTagRenderer implements Resource\Rendering\FileRendererInterface
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
    public function canRender(Resource\FileInterface $file)
    {
        return in_array($file->getMimeType(), VideoMetadataExtractor::VIDEO_MIME_TYPES, true)
            && !$file instanceof Resource\ProcessedFile // i only handle the unprocessed files to avoid reprocessing
            && $file->getProperty('width') && $file->getProperty('height');
    }

    /**
     * Render for given File(Reference) HTML output
     *
     * @param Resource\FileInterface $file
     * @param int|string $width TYPO3 known format; examples: 220, 200m or 200c
     * @param int|string $height TYPO3 known format; examples: 220, 200m or 200c
     * @param array $options
     * @param bool $usedPathsRelativeToCurrentScript See $file->getPublicUrl()
     *
     * @return string
     */
    public function render(Resource\FileInterface $file, $width, $height, array $options = [], $usedPathsRelativeToCurrentScript = false)
    {
        $attributes = [];

        $width = $width ?: $file->getProperty('width');
        $height = $height ?: $file->getProperty('height');

        if (preg_match('/[cm]$/', $width)) {
            $width = (int)min($width, $height * $file->getProperty('width') / $file->getProperty('height'));
        }

        if (preg_match('/[cm]$/', $height)) {
            $height = (int)min($height, $width * $file->getProperty('height') / $file->getProperty('width'));
        }

        $attributes[] = 'width="' . $width . '"';
        $attributes[] = 'height="' . $height . '"';

        if ($options['passive'] ?? false) {
            $options += [
                'controls' => false,
                'autoplay' => true,
                'muted' => true,
                'loop' => true,
            ];
        }

        if ($options['controls'] ?? true) {
            $attributes[] = 'controls';
        }

        if ($options['autoplay'] ?? false) {
            $attributes[] = 'autoplay';
        }

        if ($options['muted'] ?? $options['autoplay'] ?? false) {
            $attributes[] = 'muted';
        }

        if ($options['loop'] ?? false) {
            $attributes[] = 'loop';
        }

        if ($options['inline'] ?? $options['playsinline'] ?? false) {
            $attributes[] = 'webkit-playsinline';
            $attributes[] = 'playsinline';
        }

        $sources = $this->buildSources($file, $options, $usedPathsRelativeToCurrentScript);
        return sprintf('<video %s>%s</video>', implode(' ', $attributes), implode('', $sources));
    }

    protected function buildSources(Resource\FileInterface $file, array $options, $usedPathsRelativeToCurrentScript): array
    {
        if ($file instanceof Resource\FileReference) {
            $file = $file->getOriginalFile();
        }

        if (!$file instanceof Resource\File) {
            $type = is_object($file) ? get_class($file) : gettype($file);
            throw new \RuntimeException("Expected " . Resource\File::class . ", got $type");
        }

        $sources = [];

        // TODO make this more configurable
        $formats = (array)($options['formats'] ?? $options['format'] ?? ['webm', 'mp4']);
        foreach ($formats as $format) {
            $sourceOptions = FormatRepository::normalizeOptions(array_replace(
                $options,
                $options[$format] ?? [],
                ['format' => $format]
            ));

            $video = $file->process('Video.CropScale', $sourceOptions);
            if (!$video->exists()) {
                continue;
            }

            $sources[] = sprintf(
                '<source src="%s" type="%s" />',
                htmlspecialchars($video->getPublicUrl($usedPathsRelativeToCurrentScript)),
                htmlspecialchars($video->getMimeType())
            );
        }

        return $sources;
    }
}
