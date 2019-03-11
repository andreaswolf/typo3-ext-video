<?php

namespace Hn\HauptsacheVideo\Rendering;


use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\FileReference;

class VideoTagRenderer extends \TYPO3\CMS\Core\Resource\Rendering\VideoTagRenderer
{
    public function getPriority()
    {
        return parent::getPriority() + 1;
    }

    /**
     * Render for given File(Reference) HTML output
     *
     * @param FileInterface $file
     * @param int|string $width TYPO3 known format; examples: 220, 200m or 200c
     * @param int|string $height TYPO3 known format; examples: 220, 200m or 200c
     * @param array $options controls = TRUE/FALSE (default TRUE), autoplay = TRUE/FALSE (default FALSE), loop = TRUE/FALSE (default FALSE)
     * @param bool $usedPathsRelativeToCurrentScript See $file->getPublicUrl()
     *
     * @return string
     */
    public function render(FileInterface $file, $width, $height, array $options = [], $usedPathsRelativeToCurrentScript = false)
    {
        if ($file instanceof FileReference) {
            if (!isset($options['autoplay'])) {
                $options['autoplay'] = $file->getProperty('autoplay');
            }

            $file = $file->getOriginalFile();
        }

        if ($file instanceof File) {
            $file = $file->process('Video.CropScale', [
                'width' => $width,
                'height' => $height,
                'muted' => $options['muted'] ?? false,
                'quality' => $options['quality'] ?? null,
            ]);
        }

        return parent::render($file, $width, $height, $options, $usedPathsRelativeToCurrentScript);
    }
}
