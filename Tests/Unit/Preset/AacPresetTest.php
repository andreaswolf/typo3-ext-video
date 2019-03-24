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
            'aac',
            '-b:a',
            '128k',
        ], $this->preset->getParameters([]));
    }

    public function testBitrate()
    {
        $this->preset->setQuality(1.0);
        $this->assertEquals(256, round($this->preset->getBitrate([]) / 1024 / 16) * 16);

        $this->preset->setQuality(0.9);
        $this->assertEquals(176, round($this->preset->getBitrate([]) / 1024 / 16) * 16);

        $this->preset->setQuality(0.8);
        $this->assertEquals(128, round($this->preset->getBitrate([]) / 1024 / 16) * 16);

        $this->preset->setQuality(0.7);
        $this->assertEquals(96, round($this->preset->getBitrate([]) / 1024 / 16) * 16);

        $this->preset->setQuality(0.6);
        $this->assertEquals(80, round($this->preset->getBitrate([]) / 1024 / 16) * 16);
    }
}
