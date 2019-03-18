<?php

namespace Hn\HauptsacheVideo\Tests\Functional\Domain\Model;


use Hn\HauptsacheVideo\Domain\Model\StoredTask;
use Hn\HauptsacheVideo\Domain\Repository\StoredTaskRepository;
use Hn\HauptsacheVideo\Tests\Functional\FunctionalTestCase;
use TYPO3\CMS\Core\Resource\ProcessedFile;

class StoredTaskTest extends FunctionalTestCase
{
    public function testStore()
    {
        $processedFile = new ProcessedFile($this->file, 'Video.CropScale', ['foo' => 'bar']);
        $task = new StoredTask($processedFile->getTask());
        $this->persistAndFlush($task);
        $this->persistenceManager->clearState();

        $storedTaskRepository = $this->objectManager->get(StoredTaskRepository::class);
        $storedTasks = $storedTaskRepository->findAll()->toArray();
        $this->assertCount(1, $storedTasks);

        /** @var StoredTask $storedTask */
        $storedTask = reset($storedTasks);
        $this->assertSame($this->file, $storedTask->getOriginalTask()->getSourceFile());
        $this->assertEquals(['foo' => 'bar'], $storedTask->getOriginalTask()->getConfiguration());
    }

}
