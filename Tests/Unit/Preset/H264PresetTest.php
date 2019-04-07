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
            'fast',
            '-profile:v',
            'main',
            '-level:v',
            '31',
            '-crf:v',
            '23',
            '-maxrate:v',
            '2104k',
            '-bufsize:v',
            '2630k',
        ], $this->preset->getParameters([]));
    }
}
