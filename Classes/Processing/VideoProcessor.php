<?php

namespace Hn\HauptsacheVideo\Processing;


use Hn\HauptsacheVideo\Converter\VideoConverterInterface;
use Hn\HauptsacheVideo\Exception\ConversionException;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\ProcessedFileRepository;
use TYPO3\CMS\Core\Resource\Processing\ProcessorInterface;
use TYPO3\CMS\Core\Resource\Processing\TaskInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class VideoProcessor implements ProcessorInterface
{
    protected function getLogger(): LoggerInterface
    {
        return GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
    }

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

        $taskRepository = GeneralUtility::makeInstance(VideoTaskRepository::class);
        $storedTask = $taskRepository->findByTask($task);

        // if there wasn't a task before ~ this is the first time someone wants that video with that configuration
        // or if there was one successfully executed ~ the processed file was deleted and we have to do it again
        if ($storedTask === null || $storedTask->getStatus() === VideoProcessingTask::STATUS_FINISHED) {
            try {
                $task->setStatus(VideoProcessingTask::STATUS_NEW);
                $this->getConverter()->start($task);
            } catch (ConversionException $e) {
                $task->setExecuted(false);
                $this->getLogger()->error($e->getMessage(), ['exception' => $e]);
                if (GeneralUtility::getApplicationContext()->isDevelopment()) {
                    throw new \RuntimeException('processTask failed', 0, $e); // let them know
                }
            }
            $taskRepository->store($task);
        }

        // the video should never be done processing here ...

        // add a cache tag to the current page that the video can be displayed as soon as it's done
        if (!$task->isExecuted() && $GLOBALS['TSFE'] instanceof TypoScriptFrontendController) {
            $GLOBALS['TSFE']->addCacheTags([$task->getConfigurationChecksum()]);
            $GLOBALS['TSFE']->config['config']['sendCacheHeaders'] = false;
        }
    }

    /**
     * This method actually does process the task.
     *
     * It may take long and should therefor not be called in a frontend process.
     *
     * @param TaskInterface $task
     */
    public function doProcessTask(TaskInterface $task)
    {
        if (!$task instanceof VideoProcessingTask) {
            $type = is_object($task) ? get_class($task) : gettype($task);
            throw new \InvalidArgumentException("Expected " . VideoProcessingTask::class . ", got $type");
        }

        if ($task->getStatus() !== VideoProcessingTask::STATUS_NEW) {
            throw new \RuntimeException("This task is not new.");
        }

        try {
            $converter = $this->getConverter();
            $converter->process($task);

            if ($task->isExecuted() && $task->isSuccessful() && $task->getTargetFile()->isProcessed()) {
                $processedFileRepository = GeneralUtility::makeInstance(ProcessedFileRepository::class);
                $processedFileRepository->add($task->getTargetFile());

                $cacheManager = GeneralUtility::makeInstance(CacheManager::class);
                $cacheManager->flushCachesInGroupByTag('pages', $task->getConfigurationChecksum());
            }
        } catch (\Exception $e) {
            $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
            $logger->critical($e->getMessage());

            $task->setExecuted(false);
            if (GeneralUtility::getApplicationContext()->isDevelopment()) {
                throw new \RuntimeException('doProcessTask failed', 0, $e); // let them know
            }
        }

        GeneralUtility::makeInstance(VideoTaskRepository::class)->store($task);
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
