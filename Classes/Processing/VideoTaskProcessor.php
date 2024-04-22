<?php

namespace Hn\Video\Processing;

use Hn\Video\Converter\VideoConverterInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\ProcessedFileRepository;
use TYPO3\CMS\Core\Resource\Processing\ProcessorInterface;
use TYPO3\CMS\Core\Resource\Processing\TaskInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Processor for Video.CropScale tasks in the File Abstraction Layer.
 */
class VideoTaskProcessor implements ProcessorInterface
{
    private LoggerInterface $logger;

    private VideoTaskRepository $taskRepository;

    private ProcessedFileRepository $processedFileRepository;

    private CacheManager $cacheManager;

    public function __construct()
    {
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(self::class);
        $this->taskRepository = GeneralUtility::makeInstance(VideoTaskRepository::class);
        $this->processedFileRepository = GeneralUtility::makeInstance(ProcessedFileRepository::class);
        $this->cacheManager = GeneralUtility::makeInstance(CacheManager::class);
    }

    /**
     * Returns TRUE if this processor can process the given task.
     */
    public function canProcessTask(TaskInterface $task): bool
    {
        return $task instanceof VideoProcessingTask;
    }

    /**
     * Processes the given task and sets the processing result in the task object.
     *
     * For some reason the image processing is hardcoded into the core.
     * @see \TYPO3\CMS\Core\Resource\Service\FileProcessingService::processFile
     * @see \Hn\Video\Slot\FileProcessingServiceSlot::preFileProcess
     */
    public function processTask(TaskInterface $task): void
    {
        if (!$task instanceof VideoProcessingTask) {
            $type = is_object($task) ? get_class($task) : gettype($task);
            throw new \InvalidArgumentException('Expected ' . VideoProcessingTask::class . ", got $type");
        }

        if ($task->getTargetFile()->isProcessed()) {
            return;
        }

        $storedTask = $this->taskRepository->findByTask($task);

        // if there wasn't a task before ~ this is the first time someone wants that video with that configuration
        // or if there was one successfully executed ~ the processed file was deleted and we have to do it again
        if ($storedTask === null || $storedTask->getStatus() === VideoProcessingTask::STATUS_FINISHED) {
            try {
                $task->setStatus(VideoProcessingTask::STATUS_NEW);
                static::getConverter()->start($task);
                $this->handleTaskIfDone($task);
            } catch (\Exception $e) {
                $task->setExecuted(false);
                $this->logger->error($e->getMessage(), ['exception' => $e]);
                if (GeneralUtility::getApplicationContext()->isDevelopment()) {
                    throw new \RuntimeException('processTask failed', 0, $e); // let them know
                }
            }
            $this->taskRepository->store($task);
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
     */
    public function doProcessTask(TaskInterface $task): void
    {
        if (!$task instanceof VideoProcessingTask) {
            $type = is_object($task) ? get_class($task) : gettype($task);
            throw new \InvalidArgumentException('Expected ' . VideoProcessingTask::class . ", got $type");
        }

        if ($task->getStatus() !== VideoProcessingTask::STATUS_NEW) {
            throw new \RuntimeException('This task is not new.');
        }

        try {
            $converter = static::getConverter();
            $converter->process($task);
            $this->handleTaskIfDone($task);
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());

            $task->setExecuted(false);
            if (!GeneralUtility::getApplicationContext()->isProduction()) {
                throw new \RuntimeException('doProcessTask failed', 0, $e); // let them know
            }
        }

        $this->taskRepository->store($task);
    }

    public static function getConverter(): VideoConverterInterface
    {
        $videoConverter = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['video']['video_converter'];
        if ($videoConverter instanceof VideoConverterInterface) {
            return $videoConverter;
        }

        return GeneralUtility::makeInstance(ObjectManager::class)->get(...$videoConverter);
    }

    /**
     * @throws \TYPO3\CMS\Core\Cache\Exception\NoSuchCacheGroupException
     */
    protected function handleTaskIfDone(TaskInterface $task): void
    {
        if ($task->isExecuted() && $task->isSuccessful() && $task->getTargetFile()->isProcessed()) {
            $this->processedFileRepository->add($task->getTargetFile());

            $this->cacheManager->flushCachesInGroupByTag('pages', $task->getConfigurationChecksum());
        }
    }
}
