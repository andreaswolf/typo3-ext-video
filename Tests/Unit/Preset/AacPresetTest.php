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
            '-b:a',
            '128k',
            '-profile:a',
            'aac_low'
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
        $this->assertEquals(104, $this->preset->getBitrate([]));

        $this->preset->setQuality(0.6);
        $this->assertEquals(86, $this->preset->getBitrate([]));

        $this->preset->setQuality(0.5);
        $this->assertEquals(72, $this->preset->getBitrate([]));
    }
}
