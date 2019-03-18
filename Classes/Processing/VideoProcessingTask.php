<?php

namespace Hn\HauptsacheVideo\Processing;


use TYPO3\CMS\Core\Resource\Processing\AbstractTask;
use TYPO3\CMS\Core\Utility\MathUtility;

class VideoProcessingTask extends AbstractTask
{
    const TYPE = 'Video';
    const NAME = 'CropScale';

    /**
     * @var string
     */
    protected $type = self::TYPE;

    /**
     * @var string
     */
    protected $name = self::NAME;

    /**
     * Checks if the given configuration is sensible for this task, i.e. if all required parameters
     * are given, within the boundaries and don't conflict with each other.
     *
     * @param array $configuration
     *
     * @return bool
     */
    protected function isValidConfiguration(array $configuration)
    {
        return true;
    }

    /**
     * Returns TRUE if the file has to be processed at all, such as e.g. the original file does.
     *
     * Note: This does not indicate if the concrete ProcessedFile attached to this task has to be (re)processed.
     * This check is done in ProcessedFile::isOutdated(). @todo isOutdated()/needsReprocessing()?
     *
     * @return bool
     */
    public function fileNeedsProcessing()
    {
        return true;
    }

    public function getTargetFileExtension()
    {
        return preg_split('#\W#', $this->getRequestedFormat(), 2)[0];
    }

    public function getRequestedFormat(): string
    {
        return $this->getConfiguration()['format'] ?? 'mp4';
    }

    /**
     * @return float
     */
    public function getQuality(): float
    {
        $quality = $this->getConfiguration()['quality'] ?? $GLOBALS['TYPO3_CONF_VARS']['jpg_quality']['jpg_quality'] ?? 80;
        return MathUtility::forceIntegerInRange($quality, 0, 100) * 0.01;
    }

    /**
     * @return int|null
     */
    public function getWidth(): ?int
    {
        $configuration = $this->getConfiguration();
        if (isset($configuration['width']) && $configuration['width'] > 0) {
            return $configuration['width'];
        }

        if (isset($configuration['height']) && $configuration['height'] > 0) {
            return $configuration['height'] / 9 * 16;
        }

        return 1280;
    }

    /**
     * @return int|null
     */
    public function getHeight(): ?int
    {
        $configuration = $this->getConfiguration();
        if (isset($configuration['height']) && $configuration['height'] > 0) {
            return $configuration['height'];
        }

        if (isset($configuration['width']) && $configuration['width'] > 0) {
            return $configuration['width'] / 16 * 9;
        }

        return 720;
    }

    /**
     * @return bool
     */
    public function isMuted(): bool
    {
        return $this->getConfiguration()['muted'] ?? false;
    }
}
