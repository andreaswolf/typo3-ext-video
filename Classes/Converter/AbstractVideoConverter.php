<?php

namespace Hn\Video\Converter;

use Hn\Video\FormatRepository;
use Hn\Video\Processing\VideoProcessingTask;
use TYPO3\CMS\Core\Utility\GeneralUtility;

abstract class AbstractVideoConverter implements VideoConverterInterface
{
    protected FormatRepository $formatRepository;

    public function __construct()
    {
        $this->formatRepository = GeneralUtility::makeInstance(FormatRepository::class);
    }

    public function start(VideoProcessingTask $task): void
    {
    }

    protected function finishTask(VideoProcessingTask $task, string $tempFilename, array $streams): void
    {
        // the name has to be set before anything else or else random errors
        $processedFile = $task->getTargetFile();
        $processedFile->setName($task->getTargetFilename());

        // the properties also have to be set before writing the file or else... guess
        $properties = $this->formatRepository->getProperties($task->getConfiguration(), $streams);
        $processedFile->updateProperties($properties + ['checksum' => $task->getConfigurationChecksum()]);

        // now actually update the file
        $processedFile->updateWithLocalFile($tempFilename);
        $task->setExecuted(true);
    }

    public function update(VideoProcessingTask $task): void
    {
    }
}
