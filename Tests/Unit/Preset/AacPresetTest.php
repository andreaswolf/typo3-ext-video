<?php

namespace Hn\HauptsacheVideo\Tests\Unit\Preset;


use Hn\HauptsacheVideo\Preset\AacPreset;

class AacPresetTest extends AbstractAudioPresetTest
{
    /** @var AacPreset */
    protected $preset;

    protected function createPreset()
    {
        return new AacPreset();
    }

    public function testParameters()
    {
        $this->assertEquals([
            '-ar',
            '48000',
            '-ac',
            '2',
            '-c:a',
            'libfdk_aac',
            '-profile:a',
            'aac_low',
            '-b:a',
            '128k',
        ], $this->preset->getParameters([]));
    }

    public function testBitrate()
    {
        $this->preset->setQuality(1.0);
        $this->assertEquals(192, $this->preset->getBitrate([]));

        $this->preset->setQuality(0.9);
        $this->assertEquals(158, $this->preset->getBitrate([]));

        $this->preset->setQuality(0.8);
        $this->assertEquals(128, $this->preset->getBitrate([]));

        $this->preset->setQuality(0.7);
        $this->assertEquals(102, $this->preset->getBitrate([]));

        $this->preset->setQuality(0.6);
        $this->assertEquals(80, $this->preset->getBitrate([]));

        $this->preset->setQuality(0.5);
        $this->assertEquals(60, $this->preset->getBitrate([]));

        $this->preset->setQuality(0.4);
        $this->assertEquals(44, $this->preset->getBitrate([]));

        $this->preset->setQuality(0.3);
        $this->assertEquals(32, $this->preset->getBitrate([]));

        $this->preset->setQuality(0.2);
        $this->assertEquals(24, $this->preset->getBitrate([]));

        $this->preset->setQuality(0.1);
        $this->assertEquals(18, $this->preset->getBitrate([]));

        $this->preset->setQuality(0.0);
        $this->assertEquals(16, $this->preset->getBitrate([]));
    }
}
