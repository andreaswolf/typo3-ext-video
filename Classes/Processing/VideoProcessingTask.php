<?php

namespace Hn\Video\Processing;

use Hn\Video\FormatRepository;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\Processing\AbstractTask;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class VideoProcessingTask extends AbstractTask
{
    public const TYPE = 'Video';
    public const NAME = 'CropScale';

    public const STATUS_NEW = 'new';
    public const STATUS_FINISHED = 'finished';
    public const STATUS_FAILED = 'failed';

    private FormatRepository $formatRepository;

    /**
     * @var int|null
     */
    protected $uid;

    /**
     * @var string
     */
    protected $type = self::TYPE;

    /**
     * @var string
     */
    protected $name = self::NAME;

    /**
     * @var array
     */
    protected $progress = [];

    public function __construct(ProcessedFile $targetFile, array $configuration)
    {
        parent::__construct($targetFile, $configuration);
        $this->formatRepository = GeneralUtility::makeInstance(FormatRepository::class);
    }

    /**
     * Checks if the given configuration is sensible for this task, i.e. if all required parameters
     * are given, within the boundaries and don't conflict with each other.
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
        $definition = $this->formatRepository->findFormatDefinition($this->getConfiguration());
        return $definition['fileExtension'];
    }

    public function getStatus(): string
    {
        if (!$this->isExecuted()) {
            return self::STATUS_NEW;
        }

        if ($this->isSuccessful()) {
            return self::STATUS_FINISHED;
        }

        return self::STATUS_FAILED;
    }

    public function setStatus(string $status): void
    {
        switch ($status) {
            case self::STATUS_NEW:
                $this->executed = false;
                $this->successful = false;
                break;
            case self::STATUS_FAILED:
                $this->setExecuted(false);
                break;
            case self::STATUS_FINISHED:
                $this->setExecuted(true);
                break;
            default:
                throw new \RuntimeException("Status $status does not exist");
        }
    }

    public function getPriority(): int
    {
        if (isset($this->getConfiguration()['priority'])) {
            return $this->getConfiguration()['priority'];
        }

        $formatRepository = GeneralUtility::makeInstance(FormatRepository::class);
        $definition = $formatRepository->findFormatDefinition($this->getConfiguration());

        return $definition['priority'] ?? 0;
    }

    public function addProgressStep(float $progress, float $timestamp = null): int
    {
        if ($progress < 0.0 || $progress > 1.0) {
            throw new \OutOfRangeException("Progress must be between 0 and 1, got $progress.");
        }

        if ($timestamp === null) {
            $timestamp = microtime(true);
        }

        $newEntry = [
            'timestamp' => $timestamp,
            'progress' => round($progress, 5),
        ];

        // put the new entry at the position
        $i = count($this->progress);
        while (true) {
            if (--$i < 0 || $this->progress[$i]['timestamp'] <= $timestamp) {
                $insertionIndex = $i + 1;
                array_splice($this->progress, $insertionIndex, 0, [$newEntry]);
                return $insertionIndex;
            }
        }

        throw new \LogicException("This shouldn't be reached");
    }

    public function getProgressSteps(): array
    {
        return $this->progress;
    }

    public function getLastProgress(): float
    {
        if (empty($this->progress)) {
            return 0.0;
        }

        return end($this->progress)['progress'];
    }

    public function getEstimatedRemainingTime(): float
    {
        if (count($this->progress) < 2) {
            return 60 * 60 * 24;
        }

        // TODO more steps should be taken into consideration to reduce variance
        $steps = array_slice($this->progress, -2);
        $timespan = $steps[1]['timestamp'] - $steps[0]['timestamp'];
        $progressSpan = $steps[1]['progress'] - $steps[0]['progress'];
        $remainingProgress = 1 - $steps[1]['progress'];
        if ($remainingProgress <= 0.0) {
            return 0.0;
        }

        $remainingTime = $timespan / ($progressSpan / $remainingProgress);
        // secretly add a bit so that the estimate is actually too high ~ better correct down than up
        return $remainingTime * 1.05;
    }

    public function getLastUpdate(): int
    {
        if (empty($this->progress)) {
            return 0;
        }

        return end($this->progress)['timestamp'];
    }

    public function getProcessingDuration(): float
    {
        $progressSteps = $this->getProgressSteps();
        if (count($progressSteps) < 2) {
            return 0;
        }

        return end($progressSteps)['timestamp'] - reset($progressSteps)['timestamp'];
    }

    public function getUid(): ?int
    {
        return $this->uid;
    }

    /**
     * @internal this method is meant for deserialization
     */
    public function setDatabaseRow(array $row): void
    {
        $this->uid = $row['uid'];
        $this->setStatus($row['status']);
        $this->progress = json_decode($row['progress'], true, 512, JSON_THROW_ON_ERROR) ?: [];
    }
}
