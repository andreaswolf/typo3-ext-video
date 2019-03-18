<?php

namespace Hn\HauptsacheVideo\Tests\Functional\Processing;


use Hn\HauptsacheVideo\Converter\VideoConverterInterface;
use Hn\HauptsacheVideo\Domain\Model\StoredTask;
use Hn\HauptsacheVideo\Domain\Repository\StoredTaskRepository;
use Hn\HauptsacheVideo\Processing\VideoProcessingTask;
use Hn\HauptsacheVideo\Processing\VideoProcessor;
use Hn\HauptsacheVideo\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\MockObject\MockObject;

class VideoProcessorTest extends FunctionalTestCase
{
    /** @var VideoConverterInterface|MockObject */
    protected $converter;

    protected function setUp()
    {
        parent::setUp();

        $this->converter = $this->createMock(VideoConverterInterface::class);
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['hauptsache_video']['video_converter'] = $this->converter;
    }

    protected function tearDown()
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['hauptsache_video']['video_converter']); // TODO reset
        parent::tearDown();
    }

    public function testProcessFile()
    {
        $this->converter->expects($this->once())->method('start');
        $this->converter->expects($this->never())->method('process');

        $this->assertEquals(0, $this->getDatabaseConnection()->selectCount('uid', 'tx_hauptsachevideo_domain_model_storedtask'));
        $this->assertEquals(0, $this->getDatabaseConnection()->selectCount('uid', 'sys_file_processedfile'));

        $this->resourceStorage->processFile($this->file, 'Video.CropScale', []);

        $this->assertEquals(1, $this->getDatabaseConnection()->selectCount('uid', 'tx_hauptsachevideo_domain_model_storedtask'));
        $this->assertEquals(0, $this->getDatabaseConnection()->selectCount('uid', 'sys_file_processedfile'));
    }

    public function testDoProcessFile()
    {
        $this->converter->expects($this->once())->method('start');
        $this->converter->expects($this->once())->method('process');

        $this->resourceStorage->processFile($this->file, 'Video.CropScale', []);

        /** @var StoredTask $storedTask */
        $storedTask = $this->objectManager->get(StoredTaskRepository::class)->findAll()->getFirst();
        $this->assertInstanceOf(StoredTask::class, $storedTask);

        $videoProcessor = $this->objectManager->get(VideoProcessor::class);
        $task = $storedTask->getOriginalTask();
        $videoProcessor->doProcessTask($task);
        $this->assertFalse($task->isExecuted());
    }

    public function testActuallyDoProcessFile()
    {
        $this->converter->expects($this->once())->method('start');
        $this->converter->expects($this->once())->method('process')->willReturnCallback(function (VideoProcessingTask $task) {
            $processedFile = $task->getTargetFile();
            $processedFile->setName($task->getTargetFilename());
            $processedFile->updateProperties([
                'checksum' => $task->getConfigurationChecksum(),
                'width' => 1280,
                'height' => 720,
            ]);
            $task->setExecuted(true);
        });

        $this->resourceStorage->processFile($this->file, 'Video.CropScale', []);

        /** @var StoredTask $storedTask */
        $storedTask = $this->objectManager->get(StoredTaskRepository::class)->findAll()->getFirst();
        $this->assertInstanceOf(StoredTask::class, $storedTask);

        $videoProcessor = $this->objectManager->get(VideoProcessor::class);
        $task = $storedTask->getOriginalTask();
        $videoProcessor->doProcessTask($task);

        $this->assertTrue($task->isExecuted());
        $this->assertTrue($task->isSuccessful());
        $this->assertTrue($task->getTargetFile()->isProcessed());
        $this->assertTrue($task->getTargetFile()->isPersisted());
    }
}
