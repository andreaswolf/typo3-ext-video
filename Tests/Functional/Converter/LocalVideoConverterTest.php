<?php

namespace Hn\HauptsacheVideo\Tests\Functional\Converter;


use Hn\HauptsacheVideo\Converter\LocalVideoConverter;
use Hn\HauptsacheVideo\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Resource\ProcessedFile;

class LocalVideoConverterTest extends FunctionalTestCase
{
    /** @var LocalVideoConverter */
    protected $videoConverter;
    /** @var MockObject */
    protected $commandUtility;

    protected function setUp()
    {
        parent::setUp();

        $this->videoConverter = $this->objectManager->get(LocalVideoConverter::class);
        $this->commandUtility = $this->getMockBuilder(\stdClass::class)->setMethods(['exec', 'getCommand'])->getMock();
        $this->inject($this->videoConverter, 'commandUtility', $this->commandUtility);
    }

    public function testProcess()
    {
        $calls = 0;
        $this->commandUtility->expects($this->exactly(2))->method('getCommand')->willReturnOnConsecutiveCalls(
            '/usr/local/bin/ffprobe',
            '/usr/local/bin/ffmpeg'
        );
        $this->commandUtility->expects($this->exactly(2))->method('exec')->willReturnCallback(function ($command) use (&$calls) {
            switch ($calls++) {
                case 0:
                    return json_encode([
                        'streams' => [
                            ['codec_type' => 'audio'],
                            ['codec_type' => 'video'],
                        ],
                    ]);
                    break;
                case 1:
                    $parameters = str_getcsv($command, " ", "'");
                    $tmpFile = end($parameters); // technically escaped but probably fine
                    $this->assertStringStartsWith(realpath(sys_get_temp_dir()), $tmpFile, $command);
                    file_put_contents($tmpFile, "hi");
                    return 0;
            }
        });

        $file = new ProcessedFile($this->file, 'Video.CropScale', []);
        $task = $file->getTask();
        $this->videoConverter->process($task);

        $this->assertTrue($file->isProcessed());
        $this->assertTrue($task->isExecuted());
        $this->assertTrue($task->isSuccessful());
        $this->assertEquals('hi', $file->getContents());
    }
}
