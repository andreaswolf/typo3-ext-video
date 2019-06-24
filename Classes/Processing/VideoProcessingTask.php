<?php

namespace Hn\Video\Processing;


use Hn\Video\FormatRepository;
use PhpParser\Node\Expr\AssignOp\Mod;
use TYPO3\CMS\Core\Resource\Processing\AbstractTask;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class VideoProcessingTask extends AbstractTask
{
    const TYPE = 'Video';
    const NAME = 'CropScale';

    const STATUS_NEW = 'new';
    const STATUS_FINISHED = 'finished';
    const STATUS_FAILED = 'failed';

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

    public function setStatus(string $status)
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
        return $this->getConfiguration()['priority'] ?? 0;
    }

    public function addProgressStep(float $progress, int $timestamp = null): int
    {
        if ($timestamp === null) {
            $timestamp = time();
        }

        // put the new entry at the position
        $i = count($this->progress);
        while (true) {
            if (--$i < 0 || $this->progress[$i]['timestamp'] <= $timestamp) {
                $insertionIndex = $i + 1;
                array_splice($this->progress, $insertionIndex, 0, [
                    [
                        'timestamp' => $timestamp,
                        'progress' => round($progress, 5),
                    ],
                ]);
                return $insertionIndex;
            }
        }

        throw new \LogicException("This shouldn't be reached");
    }

    public function getProgressSteps(): array
    {
        return $this->progress;
    }

    /**
     * @param array $progress
     *
     * @internal this method does no validation and is meant for deserialization.
     */
    public function setProgressSteps(array $progress): void
    {
        $this->progress = $progress;
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
            return 0;
        }

        // TODO more steps should be taken into consideration to reduce variance
        $steps = array_slice($this->progress, -2);
        $timespan = $steps[1]['timestamp'] - $steps[0]['timestamp'];
        $progressSpan = $steps[1]['progress'] - $steps[0]['progress'];
        $remainingProgress = 1 - $steps[1]['progress'];

        $remainingTime = $timespan / ($progressSpan / $remainingProgress);
        // secretly add a bit so that the estimate is actually too high ~ better correct down than up
        return $remainingTime * 1.05;
    }

    public function getLastUpdate(): int
    {
        if (empty($this->progress)) {
            return 0.0;
        }

        return end($this->progress)['timestamp'];
    }
}
