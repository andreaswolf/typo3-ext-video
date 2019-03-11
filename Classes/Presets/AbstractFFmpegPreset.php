<?php

namespace Hn\HauptsacheVideo\Presets;


abstract class AbstractFFmpegPreset implements FFmpegPresetInterface
{
    public function getParameters(int $width, int $height, bool $audio, float $quality): array
    {
        if ($width < 16 || $height < 16) {
            throw new \RuntimeException("The minimum resolution is 16x16, got {$width}x{$height}");
        }

        if ($quality <= 0.0 || $quality > 1.0) {
            throw new \RuntimeException("Quality must be higher than 0.0 and lower or equal to 1.0, got $quality.");
        }

        $parameters = $this->getVideoParameters($width, $height, $quality);

        if ($audio) {
            array_push($parameters, ...$this->getAudioParameters($quality));
        } else {
            array_push($parameters, '-an');
        }

        array_push($parameters, ...$this->getContainerParameters());
        return $parameters;
    }

    abstract protected function getVideoParameters(int $width, int $height, float $quality): array;
    abstract protected function getAudioParameters(float $quality): array;
    abstract protected function getContainerParameters(): array;

}
