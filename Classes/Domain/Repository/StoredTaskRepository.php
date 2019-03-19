<?php

namespace Hn\HauptsacheVideo\Domain\Repository;


use Hn\HauptsacheVideo\Domain\Model\StoredTask;
use Hn\HauptsacheVideo\Processing\VideoProcessingTask;
use TYPO3\CMS\Core\Resource\Processing\TaskInterface;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

class StoredTaskRepository extends Repository
{
    /**
     * @param TaskInterface $task
     *
     * @return StoredTask|null
     */
    public function findLastByTask(TaskInterface $task): ?StoredTask
    {
        return $this->findByTask($task)->getFirst() ?: null;
    }

    /**
     * @param TaskInterface $task
     *
     * @return StoredTask[]|QueryResultInterface
     */
    public function findByTask(TaskInterface $task): QueryResultInterface
    {
        if ($task->getType() !== VideoProcessingTask::TYPE || $task->getName() !== VideoProcessingTask::NAME) {
            return null;
        }

        $query = $this->createQuery();
        $query->setOrderings(['tstamp' => QueryInterface::ORDER_DESCENDING]);
        $query->matching($query->logicalAnd([
            $query->equals('file', $task->getSourceFile()->getUid()),
            $query->equals('configuration', serialize($task->getConfiguration())),
        ]));

        return $query->execute();
    }

    /**
     * Finds tasks by a specific status.
     *
     * @param string $status
     * @param string $order
     *
     * @return StoredTask[]|QueryResultInterface
     */
    public function findByStatus(string $status, $order = QueryInterface::ORDER_ASCENDING): QueryResultInterface
    {
        $query = $this->createQuery();
        $query->matching($query->equals('status', $status));
        $query->setOrderings(['tstamp' => $order]);

        return $query->execute();
    }
}
