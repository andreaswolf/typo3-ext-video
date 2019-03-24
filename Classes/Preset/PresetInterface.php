<?php


namespace Hn\HauptsacheVideo\Preset;


interface PresetInterface
{
    /**
     * This can be implemented to change options of this preset.
     * These options may be specific to the preset.
     *
     * @param array $options
     */
    public function setOptions(array $options): void;

    /**
     * This method must return you ffmpeg parameters for the stream this preset is targeting.
     *
     * The parameter is the stream configuration from the source.
     * The preset may optimize around this information but it must be able to work without it.
     *
     * @param array $sourceStream
     *
     * @return array
     */
    public function getParameters(array $sourceStream): array;
}
