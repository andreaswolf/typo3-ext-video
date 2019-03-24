<?php

namespace Hn\HauptsacheVideo\Preset;


class AacPreset extends AbstractAudioPreset
{
    /**
     * This is a tolerance for a higher bitrate.
     * If the source bitrate is below the target bitrate*1.5 than no transcode will happen.
     * If a transcode is necessary than the bitrate will be *1.5 higher than the source if that is lower than the target.
     *
     * Examples with a 128 kbit/s target (2 channels with 64 kbit/s each):
     * - If the source is a 192 kbit/s aac than no transcode will happen.
     * - If the source is a 128 kbit/s aac than no transcode will happen.
     * - If the source is a 96 kbit/s aac than no transcode will happen.
     * - If the source is a 192 kbit/s mp3 than the aac stream will have 128 kbit/s.
     * - If the source is a 128 kbit/s mp3 than the aac stream will have 128 kbit/s.
     * - If the source is a 64 kbit/s mp3 than the aac stream will have 92 kbit/s.
     */
    const BITRATE_TOLERANCE = 1.5;

    /**
     * This is the base for the bitrate calculation.
     * this * (0.25 + quality ** 5 * 0.75) * channel
     *
     * @see http://fooplot.com/#W3sidHlwZSI6MCwiZXEiOiIxMjgqKDAuMjUreCoqNSowLjc1KSIsImNvbG9yIjoiIzAwMDAwMCJ9LHsidHlwZSI6MTAwMCwid2luZG93IjpbIjAiLCIxIiwiMCIsIjEyOCJdLCJncmlkIjpbIiIsIjE2Il19XQ--
     * @see AacPreset::getBitrate()
     * @var int
     */
    private $bitratePerChannel = 128 * 1024;

    public function getCodecName(): string
    {
        return 'aac';
    }

    public function requiresTranscoding(array $sourceStream): bool
    {
        if (parent::requiresTranscoding($sourceStream)) {
            return true;
        }

        if (!isset($sourceStream['profile']) || $sourceStream['profile'] !== 'LC') {
            return true;
        }

        if (!isset($sourceStream['bit_rate']) || $sourceStream['bit_rate'] > $this->getBitrate($sourceStream) * self::BITRATE_TOLERANCE) {
            return true;
        }

        return false;
    }

    public function getBitrate(array $sourceStream): int
    {
        $channels = $this->getChannels($sourceStream);
        $maxBitratePerChannel = $this->getBitratePerChannel();
        $quality = $this->getQuality();
        $maxBitrate = $maxBitratePerChannel * (0.25 + $quality ** 5 * 0.75) * $channels;

        if (!isset($sourceStream['bit_rate'])) {
            return $maxBitrate;
        }

        return min($sourceStream['bit_rate'] * self::BITRATE_TOLERANCE, $maxBitrate);
    }

    /**
     * The parameters specific to this encoder like bitrate.
     *
     * @param array $sourceStream
     *
     * @return array
     */
    public function getEncoderParameters(array $sourceStream): array
    {
        $parameters = [];

        array_push($parameters, '-c:a', 'aac');

        $bitrate = round($this->getBitrate($sourceStream) / 1024 / 16);
        array_push($parameters, '-b:a', $bitrate * 16 . 'k');

        return $parameters;
    }

    public function getBitratePerChannel(): int
    {
        return $this->bitratePerChannel;
    }

    public function setBitratePerChannel(int $bitratePerChannel): void
    {
        if ($bitratePerChannel < 16000) {
            throw new \RuntimeException("Bitrate must be at least 16000.");
        }

        $this->bitratePerChannel = $bitratePerChannel;
    }
}
