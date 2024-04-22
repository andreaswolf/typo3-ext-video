<?php

namespace Hn\Video\Tests\Unit\Preset;

use Hn\Video\Preset\AbstractVideoPreset;
use PHPUnit\Framework\MockObject\MockObject;

class AbstractVideoPresetTest extends AbstractPresetTest
{
    /** @var AbstractVideoPreset|MockObject */
    protected $preset;

    protected function createPreset()
    {
        $preset = $this->getMockForAbstractClass(AbstractVideoPreset::class);
        return $preset;
    }

    public static function framerates()
    {
        return [
            [30, []],

            // equal or lower framerates
            [30, ['avg_frame_rate' => '30']],
            [24, ['avg_frame_rate' => '24']],
            [15, ['avg_frame_rate' => '15']],

            // slightly too high framerates
            [30, ['avg_frame_rate' => '32']],
            [30, ['avg_frame_rate' => '35']],

            // massively too high framerates
            [24, ['avg_frame_rate' => '48']],
            [25, ['avg_frame_rate' => '50']],
            [30, ['avg_frame_rate' => '60']],
            [28.8, ['avg_frame_rate' => '144']],

            // stupid tv framerates
            ['24000/1001', ['avg_frame_rate' => '24000/1001']],
            [23.976, ['avg_frame_rate' => '48000/1001']],
            ['30000/1001', ['avg_frame_rate' => '30000/1001']],
            [29.97, ['avg_frame_rate' => '60000/1001']],
        ];
    }

    /**
     * @dataProvider framerates
     */
    public function testFramerate($expectedFramerate, array $sourceStream)
    {
        $this->assertEquals($expectedFramerate, $this->preset->getFramerate($sourceStream), '', 0.001);
    }

    public static function dimensions()
    {
        return [
            [[1280, 720], [1280, 720], [], []],
            [[1280, 720], [1280, 720], [1280, 720], []],
            [[640, 480], [640, 360], [1280, 720], ['width' => 640, 'height' => 480]],
            [[900, 720], [1280, 720], [1280, 720], ['width' => 1280, 'height' => 1024]],
            [[124, 124], [124, 70], [1280, 720], ['width' => 123, 'height' => 123]],
        ];
    }

    /**
     * @dataProvider dimensions
     */
    public function testDimensionsAndCropping($expectedDimensions, $expectedCroppedDimensions, array $maxDimensions, array $sourceStream)
    {
        if (!empty($maxDimensions)) {
            $this->preset->setMaxWidth($maxDimensions[0]);
            $this->preset->setMaxHeight($maxDimensions[1]);
        }

        $this->assertEquals($expectedDimensions, $this->preset->getDimensions($sourceStream), 'none cropped resolution');
        $this->preset->setCrop(true);
        $this->assertEquals($expectedCroppedDimensions, $this->preset->getDimensions($sourceStream), 'cropped resolution');
    }

    public function testBoostedQuality()
    {
        $this->preset->setMaxWidth(1280);
        $this->preset->setMaxHeight(720);
        $this->assertEquals(0.8, $this->preset->getBoostedQuality([]), '', 0.01);
        $this->assertEquals(0.8, $this->preset->getBoostedQuality(['width' => 1920, 'height' => 1080]), '', 0.01);
        $this->assertEquals(0.8, $this->preset->getBoostedQuality(['width' => 1280, 'height' => 720]), '', 0.01);
        $this->assertEquals(0.85, $this->preset->getBoostedQuality(['width' => 1100, 'height' => 600]), '', 0.01);
        $this->assertEquals(0.95, $this->preset->getBoostedQuality(['width' => 640, 'height' => 360]), '', 0.01);
        $this->assertEquals(1.0, $this->preset->getBoostedQuality(['width' => 320, 'height' => 240]), '', 0.01);
    }
}
