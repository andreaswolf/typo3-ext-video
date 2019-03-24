<?php

namespace Hn\HauptsacheVideo\Preset;


class AacPreset extends AbstractAudioPreset
{
    /**
     * This is the base for the bitrate calculation.
     * this * (0.25 + quality ** 5 * 0.75) * channel
     *
     * @see http://fooplot.com/#W3sidHlwZSI6MCwiZXEiOiIxMjgqKDAuMjUreCoqNSowLjc1KSIsImNvbG9yIjoiIzAwMDAwMCJ9LHsidHlwZSI6MTAwMCwid2luZG93IjpbIjAiLCIxIiwiMCIsIjEyOCJdLCJncmlkIjpbIiIsIjE2Il19XQ--
     * @see AacPreset::getBitrate()
     * @var int
     */
    private $maxBitratePerChannel = 128 * 1024;

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

        if (!isset($sourceStream['bit_rate']) || $sourceStream['bit_rate'] > $this->getBitrate($sourceStream)) {
            return true;
        }

        return false;
    }

    public function getBitrate(array $sourceStream): int
    {
        $channels = $this->getChannels($sourceStream);
        $maxBitratePerChannel = $this->getMaxBitratePerChannel();
        $quality = $this->getQuality();
        $maxBitrate = $maxBitratePerChannel * (0.25 + $quality ** 5 * 0.75) * $channels;

        if (!isset($sourceStream['bit_rate'])) {
            return $maxBitrate;
        }

        return min($sourceStream['bit_rate'], $maxBitrate);
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

    public function getMaxBitratePerChannel(): int
    {
        return $this->maxBitratePerChannel;
    }

    public function setMaxBitratePerChannel(int $maxBitratePerChannel): void
    {
        if ($maxBitratePerChannel < 16000) {
            throw new \RuntimeException("Bitrate must be at least 16000.");
        }

        $this->maxBitratePerChannel = $maxBitratePerChannel;
    }
}
