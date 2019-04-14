<?php

namespace Hn\HauptsacheVideo\Tests\Functional\Processing;


use Hn\HauptsacheVideo\Processing\VideoProcessingTask;
use Hn\HauptsacheVideo\Processing\VideoTaskRepository;
use Hn\HauptsacheVideo\Tests\Functional\FunctionalTestCase;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class VideoTaskRepositoryTest extends FunctionalTestCase
{
    /**
     * @var VideoTaskRepository
     */
    private $repository;

    public function testStore()
    {
        $this->assertTaskCount(0);

        $processedFile = new ProcessedFile($this->file, 'Video.CropScale', []);
        $task = $processedFile->getTask();

        $this->repository->store($task);
        $this->assertTaskCount(1);

        $this->repository->store($task);
        $this->assertTaskCount(1);

        $tasks = $this->repository->findByStatus(VideoProcessingTask::STATUS_NEW);
        $this->assertEquals([$task], $tasks);

        $this->assertNotNull($this->repository->findByTask($task));
    }

    protected function assertTaskCount(int $expected)
    {
        $this->assertEquals($expected, $this->getDatabaseConnection()->selectCount('uid', VideoTaskRepository::TABLE_NAME));
    }

    public function testRestoreState()
    {
        $processedFile = new ProcessedFile($this->file, 'Video.CropScale', []);
        /** @var VideoProcessingTask $task */
        $task = $processedFile->getTask();

        $this->assertNull($this->repository->findByTask($task));

        $task->setStatus(VideoProcessingTask::STATUS_FINISHED);
        $this->repository->store($task);

        $this->assertNotNull($storedTask = $this->repository->findByTask($task));
        $this->assertEquals(VideoProcessingTask::STATUS_FINISHED, $storedTask->getStatus());

        $storedTask2 = $this->repository->findByTask($task);
        $this->assertSame($storedTask2, $storedTask);
    }

    protected function setUp()
    {
        parent::setUp();
        $this->repository = GeneralUtility::makeInstance(VideoTaskRepository::class);
    }
}
