<?php

namespace Hn\HauptsacheVideo\Tests\Unit\Preset;


use Hn\HauptsacheVideo\Preset\VP9Preset;

class VP9PresetTest extends AbstractVideoPresetTest
{
    protected function createPreset()
    {
        return new VP9Preset();
    }

    public function testParameters()
    {
        $this->assertEquals([
            '-pix_fmt',
            'yuv420p',
            '-vf',
            'fps=30,scale=988:556',
            '-c:v',
            'libvpx-vp9',
            '-quality:v',
            'good',
            '-speed:v',
            '2',
            '-profile:v',
            '0',
            '-level:v',
            '3.0',
            '-crf:v',
            (string)$this->preset->getCrf([]),
            '-b:v',
            $this->preset->getMaxBitrate([]) . 'k',
            '-tile-columns:v',
            '1',
            '-threads:v',
            '4',
            '-g:v',
            '240'
        ], $this->preset->getParameters([]));
    }

    public function testCrf()
    {
        $mapping = [
            '0.0' => 63,
            '0.2' => 63,
            '0.4' => 55,
            '0.6' => 45,
            '0.8' => 35,
            '1.0' => 25,
        ];

        $result = [];
        foreach ($mapping as $quality => $crf) {
            $this->preset->setQuality($quality);
            $result[$quality] = $this->preset->getCrf([]);
        }

        $this->assertEquals($mapping, $result);
    }

    public function testDimensions()
    {
        $this->preset->setLevel('3.0');
        for ($x = 1444; $x < 2560; $x += 16) {
            $dimensions = $this->preset->getDimensions(['width' => $x, 'height' => '1080']);
            $this->assertLessThanOrEqual(552960, array_product($dimensions), "{$dimensions[0]}x{$dimensions[1]}");
            $this->assertGreaterThan(552960 * 0.9, array_product($dimensions), "{$dimensions[0]}x{$dimensions[1]}");
        }
    }

}
