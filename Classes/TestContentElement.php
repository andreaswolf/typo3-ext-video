<?php

namespace Hn\HauptsacheVideo;


use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\Rendering\RendererRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Resource\FileCollector;

class TestContentElement
{
    /**
     * Reference to the parent (calling) cObject set from TypoScript
     * @var ContentObjectRenderer
     */
    public $cObj;

    public function render(string $content, array $config)
    {
        $configStr = $this->cObj->stdWrapValue('configurations', $config);

        $configurations = [];
        $limit = 1;
        for ($i = 0; $i < $limit; ++$i) {
            $replace = function ($match) use ($i, &$limit) {
                $variants = GeneralUtility::trimExplode(',', $match[1]);
                $limit = max($limit, count($variants));
                return $variants[$i % count($variants)];
            };
            $parsedConfig = preg_replace_callback('#%([^%]+)%#', $replace, $configStr);
            $configurations[] = json_decode($parsedConfig, true);
            if (json_last_error()) {
                return json_last_error_msg() . ':' . $parsedConfig;
            }
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
