<?php

namespace Hn\Video\Tests\Unit\Preset;

use Hn\Video\Preset\H264Preset;

class H264PresetTest extends AbstractVideoPresetTest
{
    /**
     * @var H264Preset
     */
    protected $preset;

    protected function createPreset()
    {
        return new H264Preset();
    }

    public function testParameters(): void
    {
        $this->assertEquals([
            '-pix_fmt',
            'yuv420p',
            '-sws_flags',
            'bicubic',
            '-vf',
            'fps=30,scale=768:432',
            '-c:v',
            'libx264',
            '-preset:v',
            'medium',
            '-profile:v',
            'main',
            '-level:v',
            '30',
            '-crf:v',
            '24',
            '-maxrate:v',
            '2067k',
            '-bufsize:v',
            '10000k',
        ], $this->preset->getParameters([]));
    }

    public function testCrf(): void
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
            $result[$quality] = $this->preset->getCrf([]);
        }

        $this->assertEquals($mapping, $result);
    }

    public function testMaxBitrate(): void
    {
        $this->preset->setLevel('3.1');
        $mapping = [
            '0.0' => 750,
            '0.3' => 1400,
            '0.5' => 2500,
            '0.8' => 5100,
            '1.0' => 7600,
        ];

        $result = [];
        foreach ($mapping as $quality => $bitrate) {
            $this->preset->setQuality($quality);
            $result[$quality] = $this->preset->getTargetBitrate([]);
        }

        $this->assertEqualsWithDelta($mapping, $result, 100);
    }

    public function testIntLevel(): void
    {
        $this->preset->setLevel('4.0');
        $this->assertSame('4.0', $this->preset->getLevel());
        $this->assertSame(40, $this->preset->getIntLevel());
        $this->preset->setLevel('1.0');
        $this->assertSame('1.0', $this->preset->getLevel());
        $this->assertSame(10, $this->preset->getIntLevel());
    }

    public function testDimensions(): void
    {
        $this->preset->setLevel('3.1');
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

    public function test16by9Dimensions(): void
    {
        $source = ['width' => 3840, 'height' => 2160];

        $this->preset->setLevel('2.0');
        $this->assertEquals([416, 234], $this->preset->getDimensions($source));
        $this->preset->setLevel('2.1');
        $this->assertEquals([540, 304], $this->preset->getDimensions($source));
        $this->preset->setLevel('3.0');
        $this->assertEquals([768, 432], $this->preset->getDimensions($source));
        $this->preset->setLevel('3.1');
        $this->assertEquals([1280, 720], $this->preset->getDimensions($source));
        $this->preset->setLevel('4.0');
        $this->assertEquals([1920, 1080], $this->preset->getDimensions($source));
    }

    public function testGetMimeCodecParameter(): void
    {
        $this->assertEquals('avc1.4D401E', $this->preset->getMimeCodecParameter([]));

        $this->preset->setProfile('high');
        $this->assertEquals('avc1.64001E', $this->preset->getMimeCodecParameter([]));

        $this->preset->setProfile('baseline');
        $this->assertEquals('avc1.42E01E', $this->preset->getMimeCodecParameter([]));

        $this->preset->setProfile('high');
        $this->preset->setLevel('4.0');
        $this->assertEquals('avc1.640028', $this->preset->getMimeCodecParameter([]));
    }

    public function testRequiresTranscoding(): void
    {
        parent::testRequiresTranscoding();
        $this->assertTrue($this->preset->requiresTranscoding(['width' => 320, 'height' => 240]));
        $this->assertFalse($this->preset->requiresTranscoding([
            'codec_name' => $this->preset->getCodecName(),
            'width' => 320,
            'height' => 240,
            'pix_fmt' => 'yuv420p',
            'avg_frame_rate' => 30,
            'r_frame_rate' => 30,
            'bit_rate' => 32,
            'level' => 30,
            'profile' => 'main'
        ]));
    }
}
