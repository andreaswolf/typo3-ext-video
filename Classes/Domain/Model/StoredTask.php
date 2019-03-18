<?php

namespace Hn\HauptsacheVideo\Domain\Model;


use Hn\HauptsacheVideo\Processing\VideoProcessingTask;
use TYPO3\CMS\Core\Resource\Exception\ResourceDoesNotExistException;
use TYPO3\CMS\Core\Resource\ProcessedFileRepository;
use TYPO3\CMS\Core\Resource\Processing\TaskInterface;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class StoredTask extends AbstractEntity
{
    const STATUS_NEW = 'new';
    const STATUS_FINISHED = 'finished';
    const STATUS_FAILED = 'failed';

    /**
     * @var int
     */
    protected $file;

    /**
     * @var string
     */
    protected $configuration;

    /**
     * @var VideoProcessingTask|null
     */
    private $task;

    /**
     * @var string
     */
    protected $status;

    /**
     * @var string
     */
    protected $log = '';

    public function __construct(VideoProcessingTask $task)
    {
        $this->file = $task->getTargetFile()->getOriginalFile()->getUid();
        $this->configuration = serialize($task->getTargetFile()->getProcessingConfiguration());
        $this->task = $task;
        $this->synchronize($task);
    }

    /**
     * @return VideoProcessingTask
     */
    public function getOriginalTask(): VideoProcessingTask
    {
        if ($this->task !== null) {
            return $this->task;
        }

        try {
            $file = ResourceFactory::getInstance()->getFileObject($this->file);
            $repository = GeneralUtility::makeInstance(ProcessedFileRepository::class);
            $configuration = unserialize($this->configuration);
            $processedFile = $repository->findOneByOriginalFileAndTaskTypeAndConfiguration($file, 'Video.CropScale', $configuration);
            $task = $processedFile->getTask();
            if (!$task instanceof VideoProcessingTask) {
                $type = is_object($task) ? get_class($task) : gettype($task);
                throw new \RuntimeException("Expected " . VideoProcessingTask::class . ", got $type");
            }

            if ($this->status !== self::STATUS_NEW) {
                $task->setExecuted($this->status === self::STATUS_FINISHED);
            }

            return $this->task = $task;
        } catch (ResourceDoesNotExistException $e) {
            $this->appendException($e);
            throw new \LogicException("File for the Task not found", 1552862815, $e);
        }
    }

    public function synchronize(TaskInterface $task)
    {
        if ($task !== $this->getOriginalTask()) {
            throw new \LogicException("Wrong task");
        }

        $this->status = static::taskToStatus($task);
    }

    public static function taskToStatus(TaskInterface $task)
    {
        if (!$task->isExecuted()) {
            return self::STATUS_NEW;
        }

        if ($task->isSuccessful()) {
            return self::STATUS_FINISHED;
        }

        return self::STATUS_FAILED;
    }

    public function setStatus(string $status)
    {
        switch ($status) {
            case self::STATUS_NEW:
                if ($this->status !== $status) {
                    throw new \LogicException("The task was already executed.");
                }
                break;
            case self::STATUS_FINISHED:
            case self::STATUS_FAILED:
                $this->getOriginalTask()->setExecuted($status === self::STATUS_FINISHED);
                $this->status = $status;
                break;
            default:
                throw new \LogicException("Status $status does not exist.");
        }
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getLog(): string
    {
        return $this->log ?? '';
    }

    public function appendLog(string $log): void
    {
        $date = date('r');
        $this->log .= "[$date] $log\n";
    }

    public function appendException(\Exception $exception)
    {
        $this->appendLog((string)$exception);
    }
}
