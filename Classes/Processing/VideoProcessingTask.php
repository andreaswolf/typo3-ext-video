<?php

namespace Hn\HauptsacheVideo\Processing;


use Hn\HauptsacheVideo\FormatRepository;
use TYPO3\CMS\Core\Resource\Processing\AbstractTask;
use TYPO3\CMS\Core\Utility\GeneralUtility;

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

    public function getTargetFileExtension(): string
    {
        $formatRepository = GeneralUtility::makeInstance(FormatRepository::class);
        $definition = $formatRepository->findFormatDefinition($this->getConfiguration());
        return $definition['fileExtension'];
    }
}
