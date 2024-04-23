<?php

namespace Hn\Video\Tests\Unit\Processing;

use Hn\Video\Processing\VideoProcessingTask;
use Hn\Video\Tests\Unit\UnitTestCase;
use TYPO3\CMS\Core\Resource\ProcessedFile;

class VideoProcessingTaskTest extends UnitTestCase
{
    public function testProgress(): void
    {
        $task = new VideoProcessingTask($this->createMock(ProcessedFile::class), []);

        $this->assertEquals([], $task->getProgressSteps());

        $this->assertEquals(0, $task->addProgressStep(0, 5));
        $this->assertEquals([
            ['timestamp' => 5, 'progress' => 0.0],
        ], $task->getProgressSteps());
        $this->assertEquals(0.0, $task->getLastProgress());
        $this->assertEquals(60 * 60 * 24, $task->getEstimatedRemainingTime());

        $this->assertEquals(1, $task->addProgressStep(0.05, 6));
        $this->assertEquals([
            ['timestamp' => 5, 'progress' => 0.00],
            ['timestamp' => 6, 'progress' => 0.05],
        ], $task->getProgressSteps());
        $this->assertEquals(0.05, $task->getLastProgress());
        $this->assertEquals(95 / 5 * 1.05, $task->getEstimatedRemainingTime());

        $this->assertEquals(2, $task->addProgressStep(0.10, 6));
        $this->assertEquals([
            ['timestamp' => 5, 'progress' => 0.00],
            ['timestamp' => 6, 'progress' => 0.05],
            ['timestamp' => 6, 'progress' => 0.10],
        ], $task->getProgressSteps());
        $this->assertEquals(0.10, $task->getLastProgress());
        $this->assertEquals(0, $task->getEstimatedRemainingTime());

        $this->assertEquals(0, $task->addProgressStep(0.03, 4));
        $this->assertEquals([
            ['timestamp' => 4, 'progress' => 0.03],
            ['timestamp' => 5, 'progress' => 0.00],
            ['timestamp' => 6, 'progress' => 0.05],
            ['timestamp' => 6, 'progress' => 0.10],
        ], $task->getProgressSteps());
        $this->assertEquals(0.10, $task->getLastProgress());
        $this->assertEquals(0, $task->getEstimatedRemainingTime());

        $this->assertEquals(4, $task->addProgressStep(1.0, 10));
        $this->assertEquals([
            ['timestamp' => 4, 'progress' => 0.03],
            ['timestamp' => 5, 'progress' => 0.00],
            ['timestamp' => 6, 'progress' => 0.05],
            ['timestamp' => 6, 'progress' => 0.10],
            ['timestamp' => 10, 'progress' => 1.0],
        ], $task->getProgressSteps());
        $this->assertEquals(1.0, $task->getLastProgress());
        $this->assertEquals(0, $task->getEstimatedRemainingTime());
    }

    public function testOutOfBounds(): void
    {
        $this->expectException(\OutOfRangeException::class);
        $task = new VideoProcessingTask($this->createMock(ProcessedFile::class), []);
        $task->addProgressStep(-1, 5);
    }
}
