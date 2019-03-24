<?php

namespace Hn\HauptsacheVideo;


use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\Rendering\RendererRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Resource\FileCollector;

class DebugContentElement
{
    /**
     * Reference to the parent (calling) cObject set from TypoScript
     * @var ContentObjectRenderer
     */
    public $cObj;

    public function render(string $content, array $config)
    {
        $configStr = $this->cObj->stdWrapValue('configurations', $config);
        $configurations = json_decode($configStr, true);
        if (json_last_error()) {
            return json_last_error_msg() . ':' . $configStr;
        }

        $fileCollector = GeneralUtility::makeInstance(FileCollector::class);
        $fileCollector->addFilesFromRelation($this->cObj->getCurrentTable(), $config['field'] ?? 'media', $this->cObj->data);

        /** @var FileInterface $file */
        foreach ($fileCollector->getFiles() as $file) {
            foreach ($configurations as $configuration) {
                $content .= '<figure>';
                if ($file instanceof FileReference) {
                    $file = $file->getOriginalFile();
                }

                $configuration = FormatRepository::normalizeOptions($configuration);
                $processedFile = $file->process('Video.CropScale', $configuration);

                $json = json_encode($configuration, JSON_UNESCAPED_SLASHES);
                $content .= '<pre>' . htmlspecialchars($json) . '</pre>';

                if ($processedFile->exists()) {
                    if ($processedFile->hasProperty('ffmpeg')) {
                        $command = $processedFile->getProperty('ffmpeg');
                        $content .= "<pre>ffmpeg -i {input-file} $command {output-file}</pre>";
                    }

                    $size = round($processedFile->getSize() / 1024) . ' kB';
                    $content .= "<div>size: $size</div>";
                }

                $renderer = RendererRegistry::getInstance()->getRenderer($file);
                $content .= $renderer->render($file, 1280, 720, $configuration);
                $content .= '</figure>';
            }
        }

        return $content;
    }
}
