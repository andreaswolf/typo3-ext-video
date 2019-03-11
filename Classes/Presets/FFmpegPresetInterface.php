<?php


namespace Hn\HauptsacheVideo\Presets;


interface FFmpegPresetInterface
{
    /**
     * @param int $width in pixel; May be rounded to the nearest 2.
     * @param int $height in pixel; May be rounded to the nearest 2.
     * @param bool $audio
     * @param float $quality
     *
     * @return array
     */
    public function getParameters(int $width, int $height, bool $audio, float $quality): array;
}
