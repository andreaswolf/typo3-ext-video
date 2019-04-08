<?php

namespace Hn\HauptsacheVideo\Preset;


class AacPreset extends AbstractAudioPreset
{
    public function getCodecName(): string
    {
        return 'aac';
    }

    protected function getMaxChannels(): int
    {
        return 2;
    }

    protected function getSampleRates(): array
    {
        return [48000, 44100, 32000];
    }

    /**
     * Here are some considerations:
     * - Apple claims to only support 160 kbit/s aac-lc audio within video (although this spec seems to have never changed ~ and are probably outdated)
     * - Youtube used to use 152 kbit/s back when they combined video and audio streams which is what this preset is for
     * - Youtube now (since ~ 2013) uses 128 kbit/s audio next to the video stream
     * - Spotify uses 96/160 and 320 kbit/s vorbis depending on your quality setting and platform
     * - Android recommends between between 128 kbit/s and 192 kbit/s and in my experience can completely fail to decode audio otherwise
     *
     * Here some examples of the bitrate using different quality settings
     * - 100% = 192 kbit/s the highest android recommends
     * - 80% = 128 kbit/s default ~ usually a safe bet and the quality youtube uses
     * - 60% = 80 kbit/s
     * - 56% = 72 kbit/s this is the bitrate DAB+ uses with HE-AAC
     * - 50% = 60 kbit/s
     * - 42% = 48 kbit/s the lowest recommended bitrate for HE-AAC ~ after this it'll start to sound really bad
     * - 30% = 32 kbit/s
     * - 0% = 16 kbit/s
     *
     * @see http://fooplot.com/#W3sidHlwZSI6MCwiZXEiOiIyKk1hdGgucm91bmQoOCsoOTYtOCkqeCoqMikiLCJjb2xvciI6IiMwMDAwMDAifSx7InR5cGUiOjAsImVxIjoiMSpNYXRoLnJvdW5kKDgrKDk2LTgpKngqKjIuNikiLCJjb2xvciI6IiMwMDAwMDAifSx7InR5cGUiOjEwMDAsIndpbmRvdyI6WyIwIiwiMSIsIjAiLCIxOTIiXSwiZ3JpZCI6WyIiLCIxNiJdfV0-
     */
    protected function getBitratePerChannel(): int
    {
        $max = 96;
        $min = 8;
        return round($min + ($max - $min) * $this->getQuality() ** 2);
    }

    protected function getProfile(array $sourceStream): string
    {
        // with less than 40 kbit/s per channel (80 kbit/s stereo) use he-aac since it'll sound better
        return $this->getBitratePerChannel() < 40 ? 'aac_he' : 'aac_low';
    }

    public function requiresTranscoding(array $sourceStream): bool
    {
        if (parent::requiresTranscoding($sourceStream)) {
            return true;
        }

        // I allow LC and HE
        if (!in_array(strtoupper($sourceStream['profile']), ['HE-AAC', 'LC'], true)) {
            return true;
        }

        return false;
    }

    protected function getEncoderParameters(array $sourceStream): array
    {
        $parameters = [];

        array_push($parameters, '-c:a', 'libfdk_aac');
        array_push($parameters, '-b:a', $this->getBitrate($sourceStream) . 'k');
        array_push($parameters, '-profile:a', $this->getProfile($sourceStream));

        return $parameters;
    }
}
