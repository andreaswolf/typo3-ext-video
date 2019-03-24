<?php

namespace Hn\HauptsacheVideo\Preset;


abstract class AbstractAudioPreset extends AbstractPreset
{
    /**
     * The sample rates allowed.
     *
     * If there is an exact match with the source than it will be used.
     * If there isn't than a check is run which tries to find the sample rate which is a multiple of the source.
     * If that fails than the next higher sample rate will be chosen
     * and if that also fails the highest one will be chosen.
     *
     * Make sure the first item is your preferred sample rate since it will be used if the source is unknown.
     *
     * @var int[]
     */
    private $sampleRates = [48000, 44100, 32000];

    /**
     * @var int
     */
    private $maxChannels = 2;

    public function getCodecType(): string
    {
        return 'audio';
    }

    /**
     * The parameters specific to this encoder like bitrate.
     *
     * @param array $sourceStream
     *
     * @return array
     */
    public abstract function getEncoderParameters(array $sourceStream): array;

    public function getSampleRate(array $sourceStream): int
    {
        $sampleRates = $this->getSampleRates();
        if (!isset($sourceStream['sample_rate']) || !is_numeric($sourceStream['sample_rate'])) {
            return reset($sampleRates);
        }

        $sourceSampleRate = (int)$sourceStream['sample_rate'];

        // try to find an exactly matching sample rate
        // use a sample rate that is a multiple of the source
        sort($sampleRates); // make sure to check from the lowest to the highest
        foreach ($sampleRates as $sampleRate) {
            if ($sampleRate % $sourceSampleRate === 0) {
                return $sampleRate;
            }
        }

        // use the next higher sample rate
        foreach ($sampleRates as $sampleRate) {
            if ($sampleRate > $sourceSampleRate) {
                return $sampleRate;
            }
        }

        // use the highest allowed sample rate
        return end($sampleRates);
    }

    public function getChannels(array $sourceStream): int
    {
        $maxChannels = $this->getMaxChannels();
        if (!isset($sourceStream['channels'])) {
            return $maxChannels;
        }

        return min($sourceStream['channels'], $maxChannels);
    }

    /**
     * Creates the parameters used to transcode the stream into the desired format.
     *
     * If you create a codec specific implementation than this is the method you want to override to add your parameters.
     * Be sure that ::requiresTranscoding does check your codec and conditions.
     *
     * @param array $sourceStream
     *
     * @return array
     * @see AbstractPreset::requiresTranscoding
     */
    protected function getTranscodingParameters(array $sourceStream): array
    {
        $parameters = [];

        array_push($parameters, '-ar', (string)$this->getSampleRate($sourceStream));
        array_push($parameters, '-ac', (string)$this->getChannels($sourceStream));
        array_push($parameters, ...$this->getEncoderParameters($sourceStream));

        return $parameters;
    }

    protected function getRemuxingParameters(array $sourceStream): array
    {
        return ['-c:a', 'copy'];
    }

    public function requiresTranscoding(array $sourceStream): bool
    {
        if (parent::requiresTranscoding($sourceStream)) {
            return true;
        }

        if (!isset($sourceStream['sample_rate']) || $sourceStream['sample_rate'] !== $this->getSampleRate($sourceStream)) {
            return true;
        }

        // TODO check sample format

        if (!isset($sourceStream['channels']) || $sourceStream['channels'] > $this->getMaxChannels()) {
            return true;
        }

        // TODO maybe check channel layout? it should not matter up until stereo

        return false;
    }

    public function getSampleRates(): array
    {
        return $this->sampleRates;
    }

    public function setSampleRates(array $sampleRates): void
    {
        foreach ($sampleRates as $sampleRate) {
            if (!is_int($sampleRate)) {
                $type = is_object($sampleRate) ? get_class($sampleRate) : gettype($sampleRate);
                throw new \RuntimeException("Sample rates must be an int, got $type");
            }

            if ($sampleRate < 8000) {
                throw new \RuntimeException("Sample rate must be equal or above 8000.");
            }
        }

        $this->sampleRates = array_values($sampleRates);
    }

    public function getMaxChannels(): int
    {
        return $this->maxChannels;
    }

    public function setMaxChannels(int $maxChannels): void
    {
        if ($maxChannels < 1) {
            throw new \RuntimeException("Channel count must be at least 1.");
        }

        $this->maxChannels = $maxChannels;
    }
}
