<?php

namespace Hn\Video\Tests\Unit\Preset;

use Hn\Video\Preset\AbstractCompressiblePreset;
use Hn\Video\Tests\Unit\UnitTestCase;
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->preset = $this->createPreset();
    }

    public function testQuality()
    {
        foreach (range(0.0, 1.0, 0.1) as $quality) {
            $this->preset->setQuality($quality);
            $this->assertEqualsWithDelta($quality, $this->preset->getQuality(), 0.001);
        }
    }

    public function testQualityTooHigh()
    {
        $this->expectException(\RuntimeException::class);
        $this->preset->setQuality(1.1);
    }

    public function testQualityTooLow()
    {
        $this->expectException(\RuntimeException::class);
        $this->preset->setQuality(-0.1);
    }

    public function testRequiresTranscoding()
    {
        $this->assertTrue($this->preset->requiresTranscoding([]));
    }
}
