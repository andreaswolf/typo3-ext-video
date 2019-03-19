<?php

namespace Hn\HauptsacheVideo\Tests\Functional\Domain\Repository;


use Hn\HauptsacheVideo\Domain\Model\StoredTask;
use Hn\HauptsacheVideo\Domain\Repository\StoredTaskRepository;
use Hn\HauptsacheVideo\Tests\Functional\FunctionalTestCase;
use TYPO3\CMS\Core\Resource\ProcessedFile;

class StoredTaskRepositoryTest extends FunctionalTestCase
{
    /** @var StoredTaskRepository */
    protected $repository;

    protected function setUp()
    {
        parent::setUp();
        $this->repository = $this->objectManager->get(StoredTaskRepository::class);
    }

    public function testFindByStatus()
    {
        $new = new StoredTask((new ProcessedFile($this->file, 'Video.CropScale', []))->getTask());
        $new->setStatus(StoredTask::STATUS_NEW);
        $this->assertEquals('new', $new->getStatus());

        $finished = new StoredTask((new ProcessedFile($this->file, 'Video.CropScale', []))->getTask());
        $finished->setStatus(StoredTask::STATUS_FINISHED);
        $this->assertEquals('finished', $finished->getStatus());

        $failed = new StoredTask((new ProcessedFile($this->file, 'Video.CropScale', []))->getTask());
        $failed->setStatus(StoredTask::STATUS_FAILED);
        $this->assertEquals('failed', $failed->getStatus());

        $this->assertCount(0, $this->repository->findAll());
        $this->persistAndFlush($new, $finished, $failed);

        $this->assertEquals([$new], $this->repository->findByStatus(StoredTask::STATUS_NEW)->toArray());
        $this->assertEquals([$finished], $this->repository->findByStatus(StoredTask::STATUS_FINISHED)->toArray());
        $this->assertEquals([$failed], $this->repository->findByStatus(StoredTask::STATUS_FAILED)->toArray());
    }

    public function testFindByTask()
    {
        $task = (new ProcessedFile($this->file, 'Video.CropScale', ['foo' => 'bar']))->getTask();
        $storedTask = new StoredTask($task);
        $this->persistAndFlush($storedTask);

        $foundTasks = $this->repository->findByTask($task);
        $this->assertCount(1, $foundTasks);
        $foundTask = $foundTasks[0];

        $this->assertInstanceOf(StoredTask::class, $foundTask);
        $this->assertEquals($task->getConfiguration(), $foundTask->getOriginalTask()->getConfiguration());
    }

    public function testFindByTaskOrder()
    {
        $task = (new ProcessedFile($this->file, 'Video.CropScale', ['foo' => 'bar']))->getTask();

        $storedTask1 = new StoredTask($task);
        $storedTask2 = new StoredTask($task);
        $this->persistAndFlush($storedTask1, $storedTask2);

        $this->assertEquals(
            [
                $storedTask2->getUid(),
                $storedTask1->getUid(),
            ],
            array_map(
                [$this->persistenceManager, 'getIdentifierByObject'],
                $this->repository->findByTask($task)->toArray()
            )
        );
    }
}
