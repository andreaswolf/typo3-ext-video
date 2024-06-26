<?php

namespace Hn\Video\Tests\Functional\Processing;

use Hn\Video\Converter\VideoConverterInterface;
use Hn\Video\Processing\VideoProcessingTask;
use Hn\Video\Processing\VideoProcessor;
use Hn\Video\Processing\VideoTaskRepository;
use Hn\Video\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class VideoProcessorTest extends FunctionalTestCase
{
    /**
     * @var VideoConverterInterface|MockObject
     */
    protected $converter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->converter = $this->createMock(VideoConverterInterface::class);
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['video']['video_converter'] = $this->converter;

        $this->assertTasksAndProcessedFiles(0, 0);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['video']['video_converter']); // TODO reset
        parent::tearDown();
    }

    protected function assertTasksAndProcessedFiles(int $expectedTasks, int $expectedProcessedFiles): void
    {
        $storedTasks = $this->getDatabaseConnection()->selectCount('uid', 'tx_video_task');
        $this->assertEquals($expectedTasks, $storedTasks, 'tx_video_task');
        $processedFiles = $this->getDatabaseConnection()->selectCount('uid', 'sys_file_processedfile');
        $this->assertEquals($expectedProcessedFiles, $processedFiles, 'sys_file_processedfile');
    }

    public function testProcessFile(): void
    {
        $this->converter->expects($this->once())->method('start');
        $this->converter->expects($this->never())->method('process');

        $GLOBALS['TSFE'] = $this->createMock(TypoScriptFrontendController::class);
        $GLOBALS['TSFE']->expects($this->once())->method('addCacheTags')->withAnyParameters();

        $this->resourceStorage->processFile($this->file, 'Video.CropScale', []);
        $this->assertTasksAndProcessedFiles(1, 0);
    }

    public function testDoProcessFile(): void
    {
        $this->converter->expects($this->once())->method('start');
        $this->converter->expects($this->once())->method('process');

        $processedFile = $this->resourceStorage->processFile($this->file, 'Video.CropScale', []);
        $this->assertTasksAndProcessedFiles(1, 0);

        $cacheManager = $this->createMock(CacheManager::class);
        GeneralUtility::setSingletonInstance(CacheManager::class, $cacheManager);
        $cacheManager->expects($this->never())->method('flushCachesInGroupByTag')->withAnyParameters();

        $videoProcessor = $this->objectManager->get(VideoProcessor::class);
        $task = $processedFile->getTask();
        $videoProcessor->doProcessTask($task);
        $this->assertFalse($task->isExecuted());
        $this->assertTasksAndProcessedFiles(1, 0);
    }

    public function testActuallyDoProcessFile(): void
    {
        $this->converter->expects($this->once())->method('start');
        $this->converter->expects($this->once())->method('process')->willReturnCallback(function (VideoProcessingTask $task): void {
            $processedFile = $task->getTargetFile();
            $processedFile->setName($task->getTargetFilename());
            $processedFile->updateProperties([
                'checksum' => $task->getConfigurationChecksum(),
                'width' => 1280,
                'height' => 720,
            ]);
            $task->setExecuted(true);
        });

        $processedFile = $this->resourceStorage->processFile($this->file, 'Video.CropScale', []);
        $this->assertTasksAndProcessedFiles(1, 0);

        $cacheManager = $this->createMock(CacheManager::class);
        GeneralUtility::setSingletonInstance(CacheManager::class, $cacheManager);
        $cacheManager->expects($this->once())->method('flushCachesInGroupByTag')->withAnyParameters();

        $videoProcessor = $this->objectManager->get(VideoProcessor::class);
        $task = $processedFile->getTask();
        $videoProcessor->doProcessTask($task);

        $this->assertTrue($task->isExecuted());
        $this->assertTrue($task->isSuccessful());
        $this->assertTrue($task->getTargetFile()->isProcessed());
        $this->assertTrue($task->getTargetFile()->isPersisted());
        $this->assertTasksAndProcessedFiles(1, 1);
    }

    public function testDoNothingIfAlreadyRunning(): void
    {
        $this->converter->expects($this->once())->method('start');

        $this->resourceStorage->processFile($this->file, 'Video.CropScale', []);
        $this->resourceStorage->processFile($this->file, 'Video.CropScale', []);
        $this->assertTasksAndProcessedFiles(1, 0);
    }

    public function testRedoIfFileIsMissing(): void
    {
        $this->converter->expects($this->once())->method('start');

        /** @var VideoProcessingTask $storedTask */
        $storedTask = (new ProcessedFile($this->file, 'Video.CropScale', []))->getTask();
        $storedTask->setStatus(VideoProcessingTask::STATUS_FINISHED);
        GeneralUtility::makeInstance(VideoTaskRepository::class)->store($storedTask);
        $this->assertTasksAndProcessedFiles(1, 0);

        $processedFile = $this->resourceStorage->processFile($this->file, 'Video.CropScale', []);
        $this->assertTrue($processedFile->isProcessed());
        $this->assertFalse($processedFile->isPersisted());
        $this->assertTasksAndProcessedFiles(2, 0);
    }

    public function testNoRedoIfFailed(): void
    {
        $this->converter->expects($this->never())->method('start');

        /** @var VideoProcessingTask $storedTask */
        $storedTask = (new ProcessedFile($this->file, 'Video.CropScale', []))->getTask();
        $storedTask->setStatus(VideoProcessingTask::STATUS_FAILED);
        GeneralUtility::makeInstance(VideoTaskRepository::class)->store($storedTask);
        $this->assertTasksAndProcessedFiles(1, 0);

        $processedFile = $this->resourceStorage->processFile($this->file, 'Video.CropScale', []);
        $this->assertTrue($processedFile->isProcessed());
        $this->assertFalse($processedFile->isPersisted());
        $this->assertTasksAndProcessedFiles(1, 0);
    }

    public function testNoRedoIfRunning(): void
    {
        $this->converter->expects($this->never())->method('start');

        /** @var VideoProcessingTask $storedTask */
        $storedTask = (new ProcessedFile($this->file, 'Video.CropScale', []))->getTask();
        $storedTask->setStatus(VideoProcessingTask::STATUS_NEW);
        GeneralUtility::makeInstance(VideoTaskRepository::class)->store($storedTask);
        $this->assertTasksAndProcessedFiles(1, 0);

        $processedFile = $this->resourceStorage->processFile($this->file, 'Video.CropScale', []);
        $this->assertTrue($processedFile->isProcessed());
        $this->assertFalse($processedFile->isPersisted());
        $this->assertTasksAndProcessedFiles(1, 0);
    }
}
