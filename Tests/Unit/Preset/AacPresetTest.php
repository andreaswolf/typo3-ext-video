<?php

namespace Hn\Video\Tests\Unit\Preset;

use Hn\Video\Preset\AacPreset;

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
            '-vbr:a',
            '4',
        ], $this->preset->getParameters([]));
    }

    public function testBitrate()
    {
        $mapping = [
            '1.0' => 192,
            '0.9' => 160,
            '0.8' => 128,
            '0.7' => 96,
            '0.6' => 80,
            '0.5' => 64,
            '0.3' => 32,
            '0.2' => 24,
            '0.0' => 16,
        ];

        $result = [];
        foreach ($mapping as $quality => $crf) {
            $this->preset->setQuality($quality);
            $result[$quality] = $this->preset->getBitrate([]);
        }

        $this->assertEquals($mapping, $result, '', 8);
    }

    public function testBitrateWithoutFdk()
    {
        $this->preset->setFdkAvailable(false);

        $mapping = [
            '1.0' => 192,
            '0.9' => 160,
            '0.8' => 136,
            '0.7' => 112,
            '0.6' => 96,
            '0.5' => 72,
            '0.3' => 48,
            '0.2' => 40,
            '0.0' => 32,
        ];

        $result = [];
        foreach ($mapping as $quality => $crf) {
            $this->preset->setQuality($quality);
            $result[$quality] = $this->preset->getBitrate([]);
        }

        $this->assertEquals($mapping, $result, '', 8);
    }

    public function testGetMimeCodecParameter()
    {
        $this->assertEquals('mp4a.40.2', $this->preset->getMimeCodecParameter([]));
        $this->preset->setQuality(0.0);
        $this->assertEquals('mp4a.40.5', $this->preset->getMimeCodecParameter([]));
    }
}
