<?php

namespace Hn\HauptsacheVideo\Tests\Unit\Preset;


use Hn\HauptsacheVideo\Preset\H264Preset;
use Nimut\TestingFramework\TestCase\UnitTestCase;

class H264PresetTest extends UnitTestCase
{
    /** @var H264Preset */
    private $preset;

    protected function setUp()
    {
        parent::setUp();
        $this->preset = new H264Preset();
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
            '24',
            '-maxrate:v',
            '2488k',
            '-bufsize:v',
            '3110k',
        ], $this->preset->getParameters([]));
    }
}
