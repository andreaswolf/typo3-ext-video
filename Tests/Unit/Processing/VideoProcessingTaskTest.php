<?php

namespace Hn\Video\Tests\Unit\Processing;


use Hn\Video\Processing\VideoProcessingTask;
use Hn\Video\Tests\Unit\UnitTestCase;
use TYPO3\CMS\Core\Resource\ProcessedFile;

class VideoProcessingTaskTest extends UnitTestCase
{
    public function testProgress()
    {
        $task = new VideoProcessingTask($this->createMock(ProcessedFile::class), []);

        $this->assertEquals([], $task->getProgressSteps());

        $this->assertEquals(0, $task->addProgressStep(0, 5));
        $this->assertEquals([
            ['timestamp' => 5, 'progress' => 0.0],
        ], $task->getProgressSteps());
        $this->assertEquals(0.0, $task->getLastProgress());

        $this->assertEquals(1, $task->addProgressStep(5, 6));
        $this->assertEquals([
            ['timestamp' => 5, 'progress' => 0.0],
            ['timestamp' => 6, 'progress' => 5.0],
        ], $task->getProgressSteps());
        $this->assertEquals(5.0, $task->getLastProgress());

        $this->assertEquals(2, $task->addProgressStep(10, 6));
        $this->assertEquals([
            ['timestamp' => 5, 'progress' => 0.0],
            ['timestamp' => 6, 'progress' => 5.0],
            ['timestamp' => 6, 'progress' => 10.0],
        ], $task->getProgressSteps());
        $this->assertEquals(10.0, $task->getLastProgress());

        $this->assertEquals(0, $task->addProgressStep(3, 4));
        $this->assertEquals([
            ['timestamp' => 4, 'progress' => 3.0],
            ['timestamp' => 5, 'progress' => 0.0],
            ['timestamp' => 6, 'progress' => 5.0],
            ['timestamp' => 6, 'progress' => 10.0],
        ], $task->getProgressSteps());
        $this->assertEquals(10.0, $task->getLastProgress());
    }
}
