<?php

namespace Hn\Video\Tests\Unit\Preset;

use Hn\Video\Preset\AbstractCompressiblePreset;
use Hn\Video\Tests\Unit\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

class AbstractPresetTest extends UnitTestCase
{
    /**
     * @var AbstractCompressiblePreset|MockObject
     */
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

    public function testQuality(): void
    {
        foreach (range(0.0, 1.0, 0.1) as $quality) {
            $this->preset->setQuality($quality);
            $this->assertEqualsWithDelta($quality, $this->preset->getQuality(), 0.001);
        }
    }

    public function testQualityTooHigh(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->preset->setQuality(1.1);
    }

    public function testQualityTooLow(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->preset->setQuality(-0.1);
    }

    public function testRequiresTranscoding(): void
    {
        $this->assertTrue($this->preset->requiresTranscoding([]));
    }
}
