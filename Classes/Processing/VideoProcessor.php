<?php

namespace Hn\HauptsacheVideo\Processing;


use Hn\HauptsacheVideo\Converter\VideoConverterInterface;
use Hn\HauptsacheVideo\Domain\Model\StoredTask;
use Hn\HauptsacheVideo\Domain\Repository\StoredTaskRepository;
use Hn\HauptsacheVideo\Exception\ConversionException;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\ProcessedFileRepository;
use TYPO3\CMS\Core\Resource\Processing\ProcessorInterface;
use TYPO3\CMS\Core\Resource\Processing\TaskInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

class VideoProcessor implements ProcessorInterface
{
    /**
     * Returns TRUE if this processor can process the given task.
     *
     * @param TaskInterface $task
     *
     * @return bool
     */
    public function canProcessTask(TaskInterface $task)
    {
        return $task instanceof VideoProcessingTask;
    }

    /**
     * Processes the given task and sets the processing result in the task object.
     *
     * For some reason the image processing is hardcoded into the core.
     * @see \TYPO3\CMS\Core\Resource\Service\FileProcessingService::processFile
     * @see \Hn\HauptsacheVideo\Slot\FileProcessingServiceSlot::preFileProcess
     *
     * @param TaskInterface $task
     *
     * @throws IllegalObjectTypeException If extbase is being drunk again.
     * @todo manipulate cache times
     */
    public function processTask(TaskInterface $task)
    {
        if (!$task instanceof VideoProcessingTask) {
            $type = is_object($task) ? get_class($task) : gettype($task);
            throw new \InvalidArgumentException("Expected " . VideoProcessingTask::class . ", got $type");
        }

        if ($task->getTargetFile()->isProcessed()) {
            return;
        }

        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $storedTaskRepository = $objectManager->get(StoredTaskRepository::class);
        $storedTask = $storedTaskRepository->findLastByTask($task);

        // if there wasn't a task before ~ this is the first time someone wants that video with that configuration
        // or if there was one successfully executed ~ the processed file was deleted and we have to do it again
        if ($storedTask === null || $storedTask->getStatus() === StoredTask::STATUS_FINISHED) {
            $storedTask = GeneralUtility::makeInstance(StoredTask::class, $task);
            try {
                $this->getConverter()->start($task);
                $storedTask->synchronize($task);
            } catch (ConversionException $e) {
                $storedTask->setStatus(StoredTask::STATUS_FAILED);
                $storedTask->appendException($e);
            }
            $storedTaskRepository->add($storedTask);
            $objectManager->get(PersistenceManager::class)->persistAll();
        }
    }

    /**
     * This method actually does process the task.
     *
     * It may take long and should therefor not be called in a frontend process.
     *
     * @param TaskInterface $task
     *
     * @throws ConversionException
     */
    public function doProcessTask(TaskInterface $task)
    {
        if (!$task instanceof VideoProcessingTask) {
            $type = is_object($task) ? get_class($task) : gettype($task);
            throw new \InvalidArgumentException("Expected " . VideoProcessingTask::class . ", got $type");
        }

        try {
            $converter = $this->getConverter();
            $converter->process($task);

            if ($task->isExecuted() && $task->isSuccessful() && $task->getTargetFile()->isProcessed()) {
                $processedFileRepository = GeneralUtility::makeInstance(ProcessedFileRepository::class);
                $processedFileRepository->add($task->getTargetFile());
            }
        } catch (\Exception $e) {
            $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
            $logger->critical($e->getMessage());
            $task->setExecuted(false);
        }
    }

    protected function getConverter(): VideoConverterInterface
    {
        $videoConverter = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['hauptsache_video']['video_converter'];
        if ($videoConverter instanceof VideoConverterInterface) {
            return $videoConverter;
        }

        return GeneralUtility::makeInstance(ObjectManager::class)->get(...$videoConverter);
    }
}
