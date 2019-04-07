<?php

namespace Hn\HauptsacheVideo\Preset;


use TYPO3\CMS\Core\Utility\MathUtility;

/**
 */
abstract class AbstractVideoPreset extends AbstractCompressiblePreset
{
    /**
     * The maximum framerate allowed within this video.
     * Videos must always be encoded with a constant framerate
     * but be sure to reference the source stream to avoid frame duplication.
     *
     * @var float
     */
    private $maxFramerate = 30.0;

    /**
     * @var int
     */
    private $maxWidth = 1280;

    /**
     * @var int
     */
    private $maxHeight = 720;

    /**
     * If true than the video will be cropped.
     *
     * @var bool
     */
    private $crop = false;

    protected function getPixelFormat(): string
    {
        // most players don't support other pixel formats
        // and if they do than hardware support is probably also missing
        // just encode everything with 4:2:0 chroma ~ it saves space too
        return 'yuv420p';
    }

    public function getMaxFramerate(): float
    {
        return $this->maxFramerate;
    }

    public function setMaxFramerate(float $maxFramerate): void
    {
        if ($maxFramerate <= 0.0) {
            throw new \RuntimeException("Framerate must be higher than 0.0");
        }

        $this->maxFramerate = $maxFramerate;
    }

    public function getFramerate(array $sourceStream): string
    {
        $maxFramerate = $this->getMaxFramerate();
        if (!isset($sourceStream['avg_frame_rate'])) {
            return $maxFramerate;
        }

        $avgFrameRate = MathUtility::calculateWithParentheses($sourceStream['avg_frame_rate']);
        if ($avgFrameRate <= $maxFramerate) {
            // return the source string so that the ffmpeg fraction is preserved
            return $sourceStream['avg_frame_rate'];
        }

        // if the framerate is more than 50% over our target than start dividing it evenly
        // this should result in less stutter, here a few examples with a 30 fps limit:
        // 32 fps will be ignored and result in 30 fps and probably stuttering but dropping it to 16 fps would be insane
        // 48 fps will result in 24 fps
        // 50 fps will result in 25 fps
        // 144 fps will result in 28,8 fps
        $targetFrameRate = $avgFrameRate;
        for ($divisor = 1; $targetFrameRate > $maxFramerate * (1.0 + 0.5 / $divisor);) {
            $targetFrameRate = $avgFrameRate / ++$divisor;
        }

        return min($targetFrameRate, $maxFramerate);
    }

    /**
     * Returns the maximum number of luma samples (or the max dimensions) allowed in the video.
     * This is most likely a technical limit, the method should probably be overridden by your codec.
     *
     * @return int
     */
    protected abstract function getMaxResolution(): int;

    public function getMaxWidth(): int
    {
        return $this->maxWidth;
    }

    public function setMaxWidth(int $maxWidth): void
    {
        if ($maxWidth < 8) {
            throw new \RuntimeException("width must be 8 or higher0");
        }

        $this->maxWidth = $maxWidth;
    }

    public function getMaxHeight(): int
    {
        return $this->maxHeight;
    }

    public function setMaxHeight(int $maxHeight): void
    {
        if ($maxHeight < 8) {
            throw new \RuntimeException("height must be 8 or higher");
        }

        $this->maxHeight = $maxHeight;
    }

    public function isCrop(): bool
    {
        return $this->crop;
    }

    public function setCrop(bool $crop): void
    {
        $this->crop = $crop;
    }

    /**
     * This final resolution will be divisible by this value.
     * This is required to get chroma sub-sampling to work.
     *
     * It'll probably be 2 or 8.
     *
     * @return int
     */
    protected function getDimensionDivisor(): int
    {
        return 2;
    }

    public function getDimensions(array $sourceStream): array
    {
        $divisor = $this->getDimensionDivisor();

        if (!isset($sourceStream['width']) || !isset($sourceStream['width'])) {
            return [
                (int)(round($this->getMaxWidth() / $divisor) * $divisor),
                (int)(round($this->getMaxHeight() / $divisor) * $divisor),
            ];
        }

        $scaleFactor = min(
            1.0,
            $this->getMaxWidth() / $sourceStream['width'],
            $this->getMaxHeight() / $sourceStream['height'],
            sqrt($this->getMaxResolution() / ($sourceStream['width'] * $sourceStream['height']))
        );

        return [
            (int)(round($sourceStream['width'] * $scaleFactor / $divisor) * $divisor),
            (int)(round($sourceStream['height'] * $scaleFactor / $divisor) * $divisor),
        ];
    }

    /**
     * Calculates a rough estimate of how much bitrate is necessary to encode the video at the given quality.
     * This is based on a per pixel definition.
     *
     * Here are a few examples:
     * 0.6 * 1280*720*(30**0.5)/1024 = 3943.6024140372
     *
     * @return float
     */
    protected abstract function getBitsPerPixel(): float;

    /**
     * The maximum bitrate allowed by the codec or the codec configuration.
     * Most of the time this is configured using a level.
     *
     * @return int
     */
    protected abstract function getBitrateLimit(): int;

    /**
     * Calculates the bitrate in kbit/s
     *
     * @param array $sourceStream
     *
     * @return int
     */
    public function getMaxBitrate(array $sourceStream): int
    {
        list($width, $height) = $this->getDimensions($sourceStream);
        $framerate = MathUtility::calculateWithParentheses($this->getFramerate($sourceStream));
        $bitrate = round($width * $height * ($framerate ** 0.5) * $this->getBitsPerPixel() / 1024);
        return min($bitrate, $this->getBitrateLimit());
    }

    public function requiresTranscoding(array $sourceStream): bool
    {
        if (parent::requiresTranscoding($sourceStream)) {
            return true;
        }

        $hasCorrectPixelFormat = isset($sourceStream['pix_fmt']) && strcasecmp($sourceStream['pix_fmt'], $this->getPixelFormat()) === 0;
        if (!$hasCorrectPixelFormat) {
            return true;
        }

        $hasFramerateInformation = isset($sourceStream['avg_frame_rate']) && isset($sourceStream['r_frame_rate']);
        if (!$hasFramerateInformation) {
            return true;
        }

        $isConstantFramerate = $sourceStream['avg_frame_rate'] === $sourceStream['r_frame_rate'];
        if (!$isConstantFramerate) {
            return true;
        }

        $hasTargetedFramerate = $this->getFramerate($sourceStream) === $sourceStream['avg_frame_rate'];
        if (!$hasTargetedFramerate) {
            return true;
        }

        $hasDimensions = isset($sourceStream['width']) && isset($sourceStream['height']);
        if (!$hasDimensions) {
            return true;
        }

        $dimensions = $this->getDimensions($sourceStream);
        $hasTargetedSize = (int)$sourceStream['width'] !== $dimensions[0] && (int)$sourceStream['height'] === $dimensions[1];
        if (!$hasTargetedSize) {
            return true;
        }

        if (!isset($sourceStream['bit_rate']) || $sourceStream['bit_rate'] > $this->getMaxBitrate($sourceStream)) {
            return true;
        }

        return false;
    }

    /**
     * The parameters specific to this encoder like bitrate.
     *
     * @param array $sourceStream
     *
     * @return array
     */
    protected abstract function getEncoderParameters(array $sourceStream): array;

    protected function getTranscodingParameters(array $sourceStream): array
    {
        $parameters = [];

        array_push($parameters, '-pix_fmt', $this->getPixelFormat());

        $filters = $this->getFilters($sourceStream);
        if (!empty($filters)) {
            array_push($parameters, '-vf', implode(',', $filters));
        }

        array_push($parameters, ...$this->getEncoderParameters($sourceStream));

        // I have todo some experimentation if there is a difference with the fps filter to the -r option
        //$framerate = $this->getFramerate($sourceStream);
        //array_push($parameters, '-r', $framerate);
        //array_push($parameters, '-vsync', 'cfr');

        return $parameters;
    }

    public function getFilters(array $sourceStream): array
    {
        $filters = [];

        // specifying fps here will prevent ffmpeg from scaling a lot of frames which are than dropped.
        $framerate = $this->getFramerate($sourceStream);
        $filters[] = "fps=${framerate}";

        $dimensions = $this->getDimensions($sourceStream);
        $filters[] = "scale=${dimensions[0]}:${dimensions[1]}";

        return $filters;
    }

    protected function getRemuxingParameters(array $sourceStream): array
    {
        return ['-c:v', 'copy'];
    }
}
