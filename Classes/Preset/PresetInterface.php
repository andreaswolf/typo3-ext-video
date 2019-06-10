<?php


namespace Hn\Video\Preset;


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

    /**
     * Has to build the current codec mime parameter necessary.
     * This is necessary for the html type parameter.
     *
     * @return string
     * @see https://www.ietf.org/rfc/rfc4281.txt
     * @see https://wiki.whatwg.org/wiki/video_type_parameters
     * @see https://chromium.googlesource.com/chromium/src/media/+/master/base/mime_util_internal.cc
     */
    public function getMimeCodecParameter(array $sourceStream): ?string;
}
