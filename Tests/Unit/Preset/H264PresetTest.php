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
            'fps=30,scale=1280:720',
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
            $this->preset->getMaxBitrate([]) * 5 . 'k',
        ], $this->preset->getParameters([]));
    }

    public function testCrf()
    {
        $mapping = [
            '0.0' => 48,
            '0.2' => 42,
            '0.4' => 36,
            '0.6' => 30,
            '0.8' => 24,
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
            '0.0' => 350,
            '0.3' => 700,
            '0.5' => 1250,
            '0.8' => 2600,
            '1.0' => 3800,
        ];

        $result = [];
        foreach ($mapping as $quality => $bitrate) {
            $this->preset->setQuality($quality);
            $result[$quality] = $this->preset->getMaxBitrate([]);
        }

        $this->assertEquals($mapping, $result, '', 50);
    }

    public function testDimensions()
    {
        for ($x = 1444; $x < 2560; $x += 16) {
            $dimensions = $this->preset->getDimensions(['width' => $x, 'height' => '1080']);
            $this->assertLessThanOrEqual(
                3600,
                ceil($dimensions[0] / 16) * ceil($dimensions[1] / 16),
                "macroblocks must be below this limit ({$x}x1080 ~ {$dimensions[0]}x{$dimensions[1]})"
            );
            $this->assertGreaterThan(
                3600,
                ceil($dimensions[0] / 16 + 1) * ceil($dimensions[1] / 16 + 1),
                "there should be no room to add more macroblocks ({$x}x1080 ~ {$dimensions[0]}x{$dimensions[1]})"
            );
        }
    }

}
