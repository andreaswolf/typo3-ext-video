<?php

namespace Hn\HauptsacheVideo\Slot;


use TYPO3\CMS\Core\Resource\Driver\DriverInterface;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ProcessedFileRepository;
use TYPO3\CMS\Core\Resource\Service\FileProcessingService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FileProcessingServiceSlot
{
    /**
     * @var \Hn\HauptsacheVideo\Processing\VideoProcessor
     * @inject
     */
    protected $videoProcessor;

    /**
     * @param FileProcessingService $fileProcessingService
     * @param DriverInterface $driver
     * @param ProcessedFile $processedFile
     * @param FileInterface $file
     * @param $context
     * @param array $configuration
     *
     * @see \TYPO3\CMS\Core\Resource\Service\FileProcessingService::processFile
     */
    public function preFileProcess(FileProcessingService $fileProcessingService, DriverInterface $driver, ProcessedFile $processedFile, FileInterface $file, $context, array $configuration)
    {
        $needsProcessing = $processedFile->isNew()
            || (!$processedFile->usesOriginalFile() && !$processedFile->exists()) || $processedFile->isOutdated();
        if (!$needsProcessing) {
            return;
        }

        $task = $processedFile->getTask();
        if (!$this->videoProcessor->canProcessTask($task)) {
            return;
        }

        $this->videoProcessor->processTask($task);

        if ($task->isExecuted() && $task->isSuccessful() && $task->getTargetFile()->isProcessed()) {
            $processedFileRepository = GeneralUtility::makeInstance(ProcessedFileRepository::class);
            $processedFileRepository->add($task->getTargetFile());
        }
    }
}
