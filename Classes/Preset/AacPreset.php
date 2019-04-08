<?php

namespace Hn\HauptsacheVideo\Preset;


class AacPreset extends AbstractAudioPreset
{
    /**
     * Weather ot not to use libfdk for audio encoding.
     * libfdk will sound better especially at lower bitrates than the native ffmpeg encoder.
     * However it is probably not present on your system unless you compiled ffmpeg yourself.
     *
     * @var bool
     */
    private $fdkAvailable = true;

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
     * Here some examples of the bitrate using different quality settings (with fdk available)
     * - 100% = 192 kbit/s the highest android recommends
     * - 80% = 128 kbit/s default ~ usually a safe bet and the quality youtube uses
     * - 60% = 80 kbit/s
     * - 56% = 72 kbit/s this is the bitrate DAB+ uses with HE-AAC
     * - 50% = 60 kbit/s
     * - 42% = 48 kbit/s the lowest recommended bitrate for HE-AAC ~ after this it'll start to sound really bad
     * - 30% = 32 kbit/s
     * - 0% = 16 kbit/s
     *
     * I also give the native ffmpeg encoder a little bitrate boost at the lower end
     * because it does not support he-aac and even with lc-aac it's noticeably worse at lower bitrates.
     * At higher bitrates the difference is negligible.
     * - 100% = 192 kbit/s since I don't want to sacrifice compatibility
     * - 80% = 140 kbit/s
     * - 50% = 84 kbit/s
     * - 30% = 60 kbit/s
     * - 00% = 48 kbit/s
     *
     * @see http://fooplot.com/#W3sidHlwZSI6MCwiZXEiOiIyKk1hdGgucm91bmQoOCsoOTYtOCkqeCoqMikiLCJjb2xvciI6IiMwMDAwMDAifSx7InR5cGUiOjAsImVxIjoiMSpNYXRoLnJvdW5kKDgrKDk2LTgpKngqKjIpIiwiY29sb3IiOiIjMDAwMDAwIn0seyJ0eXBlIjoxMDAwLCJ3aW5kb3ciOlsiMCIsIjEiLCIwIiwiMTkyIl0sImdyaWQiOlsiIiwiMTYiXX1d
     */
    protected function getBitratePerChannel(): int
    {
        $max = 96;
        $min = $this->isFdkAvailable() ? 8 : 24;
        return round($min + ($max - $min) * $this->getQuality() ** 2);
    }

    /**
     * Determines the aac profile to use.
     *
     * @param array $sourceStream
     *
     * @return string
     */
    protected function getProfile(array $sourceStream): string
    {
        // with 40 kbit/s per channel (80 kbit/s stereo) use he-aac since it'll sound better
        return $this->getBitratePerChannel() <= 40 ? 'aac_he' : 'aac_low';
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

        if ($this->isFdkAvailable()) {
            array_push($parameters, '-c:a', 'libfdk_aac');
            array_push($parameters, '-profile:a', $this->getProfile($sourceStream));
        } else {
            array_push($parameters, '-c:a', 'aac');
            // TODO experiment with a high-pass filter for lower bitrates ~ just like fdk does natively
        }

        array_push($parameters, '-b:a', $this->getBitrate($sourceStream) . 'k');

        return $parameters;
    }

    public function isFdkAvailable(): bool
    {
        return $this->fdkAvailable;
    }

    public function setFdkAvailable(bool $fdkAvailable): void
    {
        $this->fdkAvailable = $fdkAvailable;
    }
}
