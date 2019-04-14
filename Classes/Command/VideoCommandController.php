<?php

namespace Hn\HauptsacheVideo\Command;


use Hn\HauptsacheVideo\Processing\VideoProcessingTask;
use TYPO3\CMS\Extbase\Mvc\Controller\CommandController;

class VideoCommandController extends CommandController
{
    /**
     * @var \Hn\HauptsacheVideo\Processing\VideoTaskRepository
     * @inject
     */
    protected $repository;

    /**
     * @var \Hn\HauptsacheVideo\Processing\VideoProcessor
     * @inject
     */
    protected $videoProcessor;

    /**
     * @param float $timeout A timeout (in minutes) after which no further tasks are started.
     */
    public function processCommand(float $timeout = INF)
    {
        $this->output("Search for new tasks... ");
        $storedTasks = $this->repository->findByStatus(VideoProcessingTask::STATUS_NEW);
        $count = count($storedTasks);
        if ($count <= 0) {
            $this->outputLine("no task found.");
            return;
        }

        $this->outputLine("found <info>%s</info> tasks:", [$count]);
        $this->output->progressStart($count);
        $startTime = time();
        foreach ($storedTasks as $storedTask) {
            $this->videoProcessor->doProcessTask($storedTask);

            $timePassed = time() - $startTime;
            if ($timePassed > $timeout * 60) {
                $this->outputLine("Abort because of the timeout ($timeout minutes).");
                break;
            }
        }
        $this->output->progressFinish();
    }
}
