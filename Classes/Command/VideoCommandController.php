<?php

namespace Hn\HauptsacheVideo\Command;


use Hn\HauptsacheVideo\Domain\Model\StoredTask;
use Hn\HauptsacheVideo\Exception\ConversionException;
use TYPO3\CMS\Extbase\Mvc\Controller\CommandController;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;

class VideoCommandController extends CommandController
{
    /**
     * @var \Hn\HauptsacheVideo\Domain\Repository\StoredTaskRepository
     * @inject
     */
    protected $repository;

    /**
     * @var \TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager
     * @inject
     */
    protected $persistenceManager;

    /**
     * @var \Hn\HauptsacheVideo\Processing\VideoProcessor
     * @inject
     */
    protected $videoProcessor;

    /**
     * @param float $timeout A timeout (in minutes) after which no further tasks are started.
     *
     * @throws UnknownObjectException
     */
    public function processCommand(float $timeout = INF)
    {
        $this->output("Search for new tasks... ");
        $storedTasks = $this->repository->findByStatus(StoredTask::STATUS_NEW);
        $count = $storedTasks->count();
        if ($count <= 0) {
            $this->outputLine("no task found.");
            return;
        }

        $this->outputLine("found <info>%s</info> tasks:", [$count]);
        $this->output->progressStart($count);
        $startTime = time();
        foreach ($storedTasks as $storedTask) {
            try {
                $task = $storedTask->getOriginalTask();
                $this->videoProcessor->doProcessTask($task);
                $storedTask->synchronize($task);
            } catch (ConversionException $e) {
                $storedTask->setStatus(StoredTask::STATUS_FAILED);
                $storedTask->appendException($e);
            }

            $this->persistenceManager->update($storedTask);
            $this->persistenceManager->persistAll();
            $this->output->progressAdvance();

            $timePassed = time() - $startTime;
            if ($timePassed > $timeout * 60) {
                $this->outputLine("Abort because of the timeout ($timeout minutes).");
                break;
            }
        }
        $this->output->progressFinish();
    }
}
