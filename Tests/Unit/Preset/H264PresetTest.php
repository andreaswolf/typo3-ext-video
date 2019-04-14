<?php

namespace Hn\HauptsacheVideo\Tests\Unit\Preset;


use Hn\HauptsacheVideo\Preset\H264Preset;

class H264PresetTest extends AbstractVideoPresetTest
{
    /** @var H264Preset */
    protected $preset;

    protected function createPreset()
    {
        return new H264Preset();
    }

    public function testParameters()
    {
        $this->assertEquals([
            '-pix_fmt',
            'yuv420p',
            '-vf',
            'fps=30,scale=1280:720:flags=bicubic',
            '-c:v',
            'libx264',
            '-preset:v',
            'medium',
            '-profile:v',
            'main',
            '-level:v',
            '31',
            '-crf:v',
            (string)$this->preset->getCrf(),
            '-maxrate:v',
            $this->preset->getMaxBitrate([]) . 'k',
            '-bufsize:v',
            $this->preset->getMaxBitrate([]) * 2 . 'k',
        ], $this->preset->getParameters([]));
    }

    public function testCrf()
    {
        $mapping = [
            '0.0' => 38,
            '0.1' => 36,
            '0.2' => 34,
            '0.3' => 32,
            '0.4' => 30,
            '0.5' => 28,
            '0.6' => 26,
            '0.7' => 24,
            '0.8' => 22,
            '0.9' => 20,
            '1.0' => 18,
        ];

        $result = [];
        foreach ($mapping as $quality => $crf) {
            $this->preset->setQuality($quality);
            $result[$quality] = $this->preset->getCrf();
        }

        $this->assertEquals($mapping, $result);
    }

    public function testMaxBitrate()
    {
        $mapping = [
            '0.0' => 300,
            '0.3' => 500,
            '0.5' => 1000,
            '0.8' => 2000,
            '1.0' => 3000,
        ];

        $result = [];
        foreach ($mapping as $quality => $bitrate) {
            $this->preset->setQuality($quality);
            $result[$quality] = $this->preset->getMaxBitrate([]);
        }

        $this->assertEquals($mapping, $result, '', 50);
    }
}
