<?php

namespace Hn\Video\Processing;


use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Resource\ProcessedFileRepository;
use TYPO3\CMS\Core\Resource\Processing\TaskInterface;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class VideoTaskRepository implements SingletonInterface
{
    const TABLE_NAME = 'tx_video_task';

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var \SplObjectStorage
     */
    private $tasks;

    public function __construct()
    {
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $this->connection = $connectionPool->getConnectionForTable(self::TABLE_NAME);
        $this->tasks = new \SplObjectStorage();
    }

    public function store(VideoProcessingTask $task)
    {
        $values = [
            'tstamp' => time(),
            'file' => $task->getSourceFile()->getUid(),
            'configuration' => serialize($task->getConfiguration()),
            'status' => $task->getStatus(),
            'progress' => json_encode($task->getProgressSteps(), JSON_UNESCAPED_SLASHES),
            'priority' => $task->getPriority(),
        ];

        if ($this->tasks->contains($task)) {
            $id = $this->tasks->offsetGet($task);
            $this->connection->update(self::TABLE_NAME, $values, ['uid' => $id]);
        } else {
            $this->connection->insert(self::TABLE_NAME, $values + ['crdate' => $values['tstamp']]);
            $id = $this->connection->lastInsertId(self::TABLE_NAME);
            $this->tasks->attach($task, $id);
        }
    }

    /**
     * @return QueryBuilder
     */
    private function createQueryBuilder(): QueryBuilder
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->from(self::TABLE_NAME, 'task');
        $qb->select('task.uid', 'task.file', 'task.configuration', 'task.status', 'task.progress');
        return $qb;
    }

    /**
     * @param TaskInterface $task
     *
     * @return VideoProcessingTask|null
     */
    public function findByTask(TaskInterface $task): ?VideoProcessingTask
    {
        return $this->findByFile($task->getSourceFile()->getUid(), $task->getConfiguration());
    }

    /**
     * @param int $file
     * @param array $configuration
     *
     * @return VideoProcessingTask|null
     */
    public function findByFile(int $file, array $configuration): ?VideoProcessingTask
    {
        $qb = $this->createQueryBuilder();
        $qb->orderBy('task.uid', 'desc');

        $qb->setParameter('file', $file);
        $qb->andWhere($qb->expr()->eq('task.file', ':file'));

        $qb->setParameter('configuration', serialize($configuration));
        $qb->andWhere($qb->expr()->eq('task.configuration', ':configuration'));

        $qb->setMaxResults(1);
        $row = $qb->execute()->fetch();
        if (!$row) {
            return null;
        }

        return $this->serializeTask($row);
    }

    protected function serializeTask(array $row): VideoProcessingTask
    {
        foreach ($this->tasks as $task) {
            if ($this->tasks[$task] === $row['uid']) {
                return $task;
            }
        }

        $file = ResourceFactory::getInstance()->getFileObject($row['file']);
        $configuration = unserialize($row['configuration']);

        $repository = GeneralUtility::makeInstance(ProcessedFileRepository::class);
        $processedFile = $repository->findOneByOriginalFileAndTaskTypeAndConfiguration($file, 'Video.CropScale', $configuration);
        $task = $processedFile->getTask();
        if (!$task instanceof VideoProcessingTask) {
            $type = is_object($task) ? get_class($task) : gettype($task);
            throw new \RuntimeException("Expected " . VideoProcessingTask::class . ", got $type");
        }

        $task->setStatus($row['status']);
        $task->setProgressSteps(json_decode($row['progress'], true) ?: []);

        $this->tasks->attach($task, $row['uid']);
        return $task;
    }

    /**
     * Finds tasks by a specific status.
     *
     * @param string $status
     *
     * @return VideoProcessingTask[]
     */
    public function findByStatus(string $status): array
    {
        $qb = $this->createQueryBuilder();
        $qb->addOrderBy('task.priority', 'desc');
        $qb->addOrderBy('task.uid', 'asc');

        $qb->setParameter('status', $status);
        $qb->andWhere($qb->expr()->eq('task.status', ':status'));

        $rows = $qb->execute()->fetchAll();
        return array_map([$this, 'serializeTask'], $rows);
    }

}
