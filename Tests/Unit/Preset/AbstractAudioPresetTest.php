<?php

namespace Hn\HauptsacheVideo\Tests\Unit\Preset;


use Hn\HauptsacheVideo\Preset\AbstractAudioPreset;
use PHPUnit\Framework\MockObject\MockObject;

class AbstractAudioPresetTest extends AbstractPresetTest
{
    /** @var AbstractAudioPreset|MockObject */
    protected $preset;

    protected function createPreset()
    {
        return $this->getMockForAbstractClass(AbstractAudioPreset::class);
    }

    public function testSampleRate()
    {
        $this->assertEquals(48000, $this->preset->getSampleRate([]));

        $this->assertEquals(32000, $this->preset->getSampleRate(['sample_rate' => '32000']));
        $this->assertEquals(44100, $this->preset->getSampleRate(['sample_rate' => '44100']));
        $this->assertEquals(48000, $this->preset->getSampleRate(['sample_rate' => '48000']));

        // i actually don't know if this is a good idea or if i should just resample everything to 32000
        $this->assertEquals(32000, $this->preset->getSampleRate(['sample_rate' => '8000']));
        $this->assertEquals(32000, $this->preset->getSampleRate(['sample_rate' => '16000']));
        $this->assertEquals(44100, $this->preset->getSampleRate(['sample_rate' => '11025']));
        $this->assertEquals(44100, $this->preset->getSampleRate(['sample_rate' => '22050']));
        $this->assertEquals(48000, $this->preset->getSampleRate(['sample_rate' => '12000']));
        $this->assertEquals(48000, $this->preset->getSampleRate(['sample_rate' => '24000']));

        $this->assertEquals(48000, $this->preset->getSampleRate(['sample_rate' => '64000']));
        $this->assertEquals(48000, $this->preset->getSampleRate(['sample_rate' => '88200']));
        $this->assertEquals(48000, $this->preset->getSampleRate(['sample_rate' => '96000']));

        $this->assertEquals(44100, $this->preset->getSampleRate(['sample_rate' => '44056']));
        $this->assertEquals(48000, $this->preset->getSampleRate(['sample_rate' => '47250']));

        $this->assertEquals(48000, $this->preset->getSampleRate(['sample_rate' => '64000']));
        $this->assertEquals(48000, $this->preset->getSampleRate(['sample_rate' => '96000']));

        $this->preset->setSampleRates([48000, 24000]);
        $this->assertEquals(24000, $this->preset->getSampleRate(['sample_rate' => '12000']));
    }
}