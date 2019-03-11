<?php

namespace Hn\HauptsacheVideo\Tests\Unit\Preset;


use Hn\HauptsacheVideo\Presets\FFmpegPresetInterface;
use Nimut\TestingFramework\TestCase\UnitTestCase;

class PresetRepositoryTest extends UnitTestCase
{
    /**
     * @var \Hn\HauptsacheVideo\Presets\PresetRepository
     */
    protected $repository;

    public function setUp()
    {
        $this->repository = new \Hn\HauptsacheVideo\Presets\PresetRepository();
    }

    /**
     * @expectedException \Hn\HauptsacheVideo\Exception\FormatException
     */
    public function testUndefinedGlobal()
    {
        $this->repository->getPresetForFormat('mp4');
    }

    /**
     * @expectedException \Hn\HauptsacheVideo\Exception\FormatException
     */
    public function testNoneExistingFormat()
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['hauptsache_video']['presets'] = [];
        $this->repository->getPresetForFormat('mp4');
    }

    public function testGetArrayFormat()
    {
        $mockClass = $this->getMockClass(FFmpegPresetInterface::class);
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['hauptsache_video']['presets'] = [
            'mp4' => [$mockClass]
        ];

        $result = $this->repository->getPresetForFormat('mp4');
        $this->assertInstanceOf($mockClass, $result);
    }

    public function testGetObjectFormat()
    {
        $mock = $this->createMock(FFmpegPresetInterface ::class);
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['hauptsache_video']['presets'] = [
            'mp4' => $mock
        ];

        $result = $this->repository->getPresetForFormat('mp4');
        $this->assertSame($mock, $result);
    }
}
