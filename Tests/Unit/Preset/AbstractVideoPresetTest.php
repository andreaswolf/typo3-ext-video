<?php

namespace Hn\HauptsacheVideo\Tests\Unit\Preset;


use Hn\HauptsacheVideo\Preset\AbstractVideoPreset;
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
            [[1280, 720], [], []],
            [[1280, 720], [1280, 720], []],
            [[640, 480], [1280, 720], ['width' => 640, 'height' => 480]],
            [[900, 720], [1280, 720], ['width' => 1280, 'height' => 1024]],
            [[124, 124], [1280, 720], ['width' => 123, 'height' => 123]],
        ];
    }

    /**
     * @dataProvider dimensions
     */
    public function testDimensions($expectedDimensions, array $maxDimensions, array $sourceStream)
    {
        if (!empty($maxDimensions)) {
            $this->preset->setMaxWidth($maxDimensions[0]);
            $this->preset->setMaxHeight($maxDimensions[1]);
        }

        $dimensions = $this->preset->getDimensions($sourceStream);
        $this->assertEquals($expectedDimensions, $dimensions);
        return $dimensions;
    }

}
