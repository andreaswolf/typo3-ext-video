<?php

namespace Hn\HauptsacheVideo\Processing;


use Hn\HauptsacheVideo\Converter\VideoConverterInterface;
use Hn\HauptsacheVideo\Converter\LocalVideoConverter;
use Hn\HauptsacheVideo\Exception\ConversionException;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Resource\ProcessedFileRepository;
use TYPO3\CMS\Core\Resource\Processing\ProcessorInterface;
use TYPO3\CMS\Core\Resource\Processing\TaskInterface;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

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

        try {
            $task->getTargetFile()->setName($task->getTargetFileName());
            $task->getTargetFile()->updateProperties([
                'width' => $task->getWidth(),
                'height' => $task->getHeight(),
                'checksum' => $task->getConfigurationChecksum()
            ]);

            $this->getConverter()->start($task);

            if ($task->getTargetFile()->isProcessed() !== true) {
                // video conversion runs async. To prevent other tasks from taking over, a placeholder video is used.
                // this should mark the target file as #isProcessed and therefor prevent typo3 from trying to scale
                // a video using it's \TYPO3\CMS\Core\Resource\Processing\LocalImageProcessor
                // @see \TYPO3\CMS\Core\Resource\Service\FileProcessingService::processFile
                $placeholder = ExtensionManagementUtility::extPath('hauptsache_video', 'Resources/Private/Placeholder.mp4');
                $tmpfile = tempnam(sys_get_temp_dir(), 'placeholder_video');
                copy($placeholder, $tmpfile);
                $task->getTargetFile()->updateWithLocalFile($tmpfile);
                if ($task->getTargetFile()->isProcessed() !== true) {
                    throw new \LogicException("The file was replaced but somehow isn't marked as processed.", 1551953787);
                }
            }

            $task->setExecuted(true);
        } catch (ConversionException $e) {
            $task->setExecuted(false);
            $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
            $logger->error("video could not be converted", ['exception' => $e]);
        }
    }

    protected function getConverter(): VideoConverterInterface
    {
        $videoConverter = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['hauptsache_video']['video_converter'] ?? [LocalVideoConverter::class];
        return GeneralUtility::makeInstance(ObjectManager::class)->get(...$videoConverter);
    }
}
