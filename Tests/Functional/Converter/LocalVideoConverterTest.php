<?php

namespace Hn\Video\Tests\Functional\Converter;

use Hn\Video\Converter\LocalCommandRunner;
use Hn\Video\Converter\LocalFFmpegConverter;
use Hn\Video\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\CMS\Core\Resource\ProcessedFile;

class LocalVideoConverterTest extends FunctionalTestCase
{
    /** @var LocalFFmpegConverter */
    protected $videoConverter;
    /** @var LocalCommandRunner|MockObject */
    protected $commandRunner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->videoConverter = $this->objectManager->get(LocalFFmpegConverter::class);
        $this->commandRunner = $this->createMock(LocalCommandRunner::class);
        $this->inject($this->videoConverter, 'runner', $this->commandRunner);
    }

    public function testProcess()
    {
        $calls = 0;
        $this->commandRunner->expects($this->exactly(3))->method('getCommand')
            ->withConsecutive('ffprobe', 'ffmpeg', 'nice')
            ->willReturnOnConsecutiveCalls('/usr/local/bin/ffprobe', '/usr/local/bin/ffmpeg', '/usr/bin/nice');
        $this->commandRunner->expects($this->exactly(2))->method('run')
            ->willReturnCallback(function ($command) use (&$calls) {
                $parameters = str_getcsv($command, ' ', "'");
                switch ($calls++) {
                    case 0:
                        $this->assertEquals('/usr/local/bin/ffprobe', reset($parameters));
                        yield json_encode([
                            'streams' => [
                                ['codec_type' => 'audio'],
                                ['codec_type' => 'video'],
                            ],
                        ]);
                        return 0;
                    case 1:
                        $tmpFile = end($parameters); // technically escaped but probably fine
                        $this->assertEquals('/usr/bin/nice', $parameters[0]);
                        $this->assertEquals('/usr/local/bin/ffmpeg', $parameters[1]);
                        $this->assertStringStartsWith(PATH_site . 'typo3temp/var/transient/', $tmpFile, $command);
                        file_put_contents($tmpFile, 'hi');
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
