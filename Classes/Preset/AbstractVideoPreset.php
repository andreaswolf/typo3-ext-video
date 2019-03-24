<?php

namespace Hn\HauptsacheVideo\Preset;


use TYPO3\CMS\Core\Utility\MathUtility;

/**
 */
abstract class AbstractVideoPreset extends AbstractPreset
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
     * This final resolution will be divisible by this value.
     * This is required to get chroma sub-sampling to work.
     *
     * You may want to try adjusting it to match the macroblock size.
     * h264 uses 4x4 and 8x8 macroblocks therefor 4 or 8 would probably be a good idea for experiments.
     *
     * @var int
     */
    private $dimensionDivisor = 2;

    /**
     * If true than the video will be cropped.
     *
     * @var bool
     */
    private $crop = false;

    public function getCodecType(): string
    {
        return 'video';
    }

    public function getPixelFormat(): string
    {
        // most players don't support other pixel formats
        // and if they do than hardware support is probably also missing
        // just encode everything with 4:2:0 chroma ~ it saves space too
        return 'yuv420p';
    }

    /**
     * The parameters specific to this encoder like bitrate.
     *
     * @param array $sourceStream
     *
     * @return array
     */
    public abstract function getEncoderParameters(array $sourceStream): array;

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
    public function getMaxResolution(): int
    {
        return 3840 * 2160;
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

    public function requiresTranscoding(array $sourceStream): bool
    {
        if (parent::requiresTranscoding($sourceStream)) {
            return true;
        }

        $hasCorrectPixelFormat = strcasecmp($sourceStream['pix_fmt'], $this->getPixelFormat()) === 0;
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

        return false;
    }

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

    private static function parseFramerate(string $framerate): float
    {
        return MathUtility::calculateWithParentheses($framerate);
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

    public function getDimensionDivisor(): int
    {
        return $this->dimensionDivisor;
    }

    public function setDimensionDivisor(int $dimensionDivisor): void
    {
        if ($dimensionDivisor < 1) {
            throw new \RuntimeException("dimension divisor must be 1 or higher");
        }

        $this->dimensionDivisor = $dimensionDivisor;
    }

    public function isCrop(): bool
    {
        return $this->crop;
    }

    public function setCrop(bool $crop): void
    {
        $this->crop = $crop;
    }
}
