<?php

namespace Hn\Video\Command;

use Hn\Video\Processing\VideoProcessingTask;
use TYPO3\CMS\Extbase\Mvc\Controller\CommandController;

class VideoCommandController extends CommandController
{
    /**
     * @var \Hn\Video\Processing\VideoTaskRepository
     * @inject
     */
    protected $repository;

    /**
     * @var \Hn\Video\Processing\VideoProcessor
     * @inject
     */
    protected $videoProcessor;

    /**
     * @param float $timeout A timeout (in minutes) after which no further tasks are started.
     */
    public function processCommand(float $timeout = INF): void
    {
        $this->output('Search for new tasks... ');
        $storedTasks = $this->repository->findByStatus(VideoProcessingTask::STATUS_NEW);
        $count = count($storedTasks);
        if ($count <= 0) {
            $this->outputLine('no task found.');
            return;
        }

        $this->outputLine('found <info>%s</info> tasks:', [$count]);
        $this->output->progressStart($count);
        foreach ($storedTasks as $storedTask) {
            $this->videoProcessor->doProcessTask($storedTask);

            $timePassed = time() - $_SERVER['REQUEST_TIME'];
            if ($timePassed > $timeout * 60) {
                $this->outputLine("Abort because of the timeout ($timeout minutes).");
                break;
            }
            $this->output->progressAdvance();
        }
        $this->output->progressFinish();
    }
}
