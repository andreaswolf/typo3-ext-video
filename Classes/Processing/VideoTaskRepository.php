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
    public const TABLE_NAME = 'tx_video_task';

    private Connection $connection;

    private ProcessedFileRepository $processedFileRepository;

    /**
     * @var VideoProcessingTask[]
     */
    private array $tasks = [];

    public function __construct()
    {
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $this->connection = $connectionPool->getConnectionForTable(self::TABLE_NAME);
        $this->processedFileRepository = GeneralUtility::makeInstance(ProcessedFileRepository::class);
    }

    public function store(VideoProcessingTask $task): void
    {
        $values = [
            'tstamp' => time(),
            'file' => $task->getSourceFile()->getUid(),
            'configuration' => serialize($task->getConfiguration()),
            'status' => $task->getStatus(),
            'progress' => json_encode($task->getProgressSteps(), JSON_UNESCAPED_SLASHES),
            'priority' => $task->getPriority(),
        ];

        if ($task->getUid() !== null && $this->tasks[$task->getUid()] === $task) {
            $this->connection->update(self::TABLE_NAME, $values, ['uid' => $task->getUid()]);
        } else {
            $this->connection->insert(self::TABLE_NAME, $values + ['crdate' => $values['tstamp']]);
            $id = $this->connection->lastInsertId(self::TABLE_NAME);
            $task->setDatabaseRow($values + ['uid' => $id]);
            $this->tasks[$id] = $task;
        }
    }

    private function createQueryBuilder(): QueryBuilder
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->from(self::TABLE_NAME, 'task');
        $qb->select('task.uid', 'task.file', 'task.configuration', 'task.status', 'task.progress');
        return $qb;
    }

    public function findByTask(TaskInterface $task): ?VideoProcessingTask
    {
        if ($task instanceof VideoProcessingTask && $task->getUid() && isset($this->tasks[$task->getUid()])) {
            return $this->tasks[$task->getUid()];
        }

        return $this->findByFile($task->getSourceFile()->getUid(), $task->getConfiguration());
    }

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

    public function findByUid(int $uid): ?VideoProcessingTask
    {
        $qb = $this->createQueryBuilder();
        $qb->andWhere($qb->expr()->eq('task.uid', $qb->createNamedParameter($uid, Connection::PARAM_INT)));

        $qb->setMaxResults(1);
        $row = $qb->execute()->fetch();
        if (!$row) {
            return null;
        }

        return $this->serializeTask($row);
    }

    /**
     * Finds tasks by a specific status.
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

    protected function serializeTask(array $row): VideoProcessingTask
    {
        if (isset($this->tasks[$row['uid']])) {
            $this->tasks[$row['uid']]->setDatabaseRow($row);
            return $this->tasks[$row['uid']];
        }

        $file = ResourceFactory::getInstance()->getFileObject($row['file']);
        $configuration = unserialize($row['configuration']);

        $processedFile = $this->processedFileRepository->findOneByOriginalFileAndTaskTypeAndConfiguration($file, 'Video.CropScale', $configuration);
        $task = $processedFile->getTask();
        if (!$task instanceof VideoProcessingTask) {
            $type = is_object($task) ? get_class($task) : gettype($task);
            throw new \RuntimeException('Expected ' . VideoProcessingTask::class . ", got $type");
        }

        $task->setDatabaseRow($row);
        $this->tasks[$row['uid']] = $task;
        return $task;
    }

    public function delete(VideoProcessingTask $task): bool
    {
        if ($task->getUid() === null || !isset($this->tasks[$task->getUid()])) {
            return false;
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->delete(self::TABLE_NAME);
        $qb->where($qb->expr()->eq('uid', $qb->createNamedParameter($task->getUid(), Connection::PARAM_INT)));

        $success = $qb->execute() > 0;
        if ($success) {
            unset($this->tasks[$task->getUid()]);
            return true;
        } else {
            return false;
        }
    }
}
