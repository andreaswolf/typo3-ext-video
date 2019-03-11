<?php

namespace Hn\HauptsacheVideo\Tests\Unit\Preset;


use Hn\HauptsacheVideo\Presets\Mp4H264Preset;
use Nimut\TestingFramework\TestCase\UnitTestCase;

class Mp4H264PresetTest extends UnitTestCase
{
    public function testNormal()
    {
        $preset = new Mp4H264Preset();
        $this->assertEquals([
            '-c:v',
            'libx264',
            '-vf',
            'scale=640:480:force_original_aspect_ratio=increase,crop=640:480',
            '-preset',
            'fast',
            '-r',
            '30',
            '-vsync',
            'vfr',
            '-profile:v',
            'main',
            '-level',
            '3.1',
            '-pix_fmt',
            '+yuv420p',
            '-crf',
            '21',
            '-maxrate',
            '972k',
            '-bufsize',
            '642k',
            '-c:a',
            'aac',
            '-ac',
            '2',
            '-af',
            'aformat=sample_fmts=u8|s16:sample_rates=32000|44100|48000',
            '-b:a',
            '160k',
            '-movflags',
            '+faststart',
            '-f',
            'mp4',
        ], $preset->getParameters(640, 480, true, 0.9));
    }

    public function testResolutionLimit()
    {
        $preset = new Mp4H264Preset();

        $p360 = $preset->getParameters(640, 360, false, 0.9);
        $p720 = $preset->getParameters(1280, 720, false, 0.9);
        $p1080 = $preset->getParameters(1920, 1080, false, 0.9);
        $this->assertContains('scale=640:360:force_original_aspect_ratio=increase,crop=640:360', $p360);
        $this->assertContains('scale=1280:720:force_original_aspect_ratio=increase,crop=1280:720', $p720);
        $this->assertContains('scale=1280:720:force_original_aspect_ratio=increase,crop=1280:720', $p1080);
    }
}
