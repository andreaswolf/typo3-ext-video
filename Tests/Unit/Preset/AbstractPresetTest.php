<?php

namespace Hn\HauptsacheVideo\Tests\Unit\Preset;


use Hn\HauptsacheVideo\Preset\AbstractCompressiblePreset;
use Nimut\TestingFramework\TestCase\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

class AbstractPresetTest extends UnitTestCase
{
    /** @var AbstractCompressiblePreset|MockObject */
    protected $preset;

    public function __sleep()
    {
        return [];
    }

    protected function createPreset()
    {
        return $this->getMockForAbstractClass(AbstractCompressiblePreset::class);
    }

    protected function setUp()
    {
        parent::setUp();
        $this->preset = $this->createPreset();
    }

    public function testQuality()
    {
        $this->preset->setQuality(0.1);
        $this->assertEquals(0.1, $this->preset->getQuality());
        $this->preset->setQuality(1.0);
        $this->assertEquals(1.0, $this->preset->getQuality());
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testQualityTooHigh()
    {
        $this->preset->setQuality(1.1);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testQualityTooLow()
    {
        $this->preset->setQuality(0.0);
    }

    public function testRequiresTranscoding()
    {
        $this->assertTrue($this->preset->requiresTranscoding([]));
    }
}
