<?php

namespace Hn\HauptsacheVideo\Preset;


use TYPO3\CMS\Core\Utility\MathUtility;

class H264Preset extends AbstractVideoPreset
{
    /**
     * Defines the limits of the different h264 levels.
     * 1. luma samples per frame
     * 2. luma samples per second
     * 3. max bitrate per second in kbit/s
     * https://en.wikipedia.org/wiki/H.264/MPEG-4_AVC#Levels
     */
    const LEVEL_DEFINITION = [
        10 => [25344, 380160, 64], // eg. 128×96@30 176×144@15
        //'1b' => [25344, 380160, 128], // eg. 128×96@30 176×144@15
        11 => [101376, 768000, 192], // eg. 176×144@30 352×288@7.5
        12 => [101376, 1536000, 384], // eg. 176×144@60 352×288@15
        13 => [101376, 3041280, 768], // eg. 352×288@30

        20 => [414720, 10368000, 2000], // eg. 352×288@30
        21 => [414720, 10368000, 4000], // eg. 352×480@30 352×576@25
        22 => [414720, 10368000, 4000], // eg. 352×480@30 720×576@12.5

        30 => [921600, 27648000, 10000], // eg. 1280×720@30
        31 => [921600, 27648000, 14000], // eg. 1280×720@30
        32 => [1310720, 55296000, 14000], // eg. 1280×720@60

        40 => [2097152, 62914560, 20000], // eg. 1920x1080@30
        41 => [2097152, 62914560, 50000], // eg. 1920x1080@30 but with higher bitrate
        42 => [2228224, 133693440, 50000], // eg. 1920x1080@60

        // please be *very* careful when using anything below this comment
        // and be sure to suggest youtube or vimeo over and over again before caving ...

        50 => [5652480, 150994944, 135000], // eg. 2560×1920@30
        51 => [9437184, 251658240, 240000], // eg. 4096×2048@30
        52 => [9437184, 530841600, 240000], // eg. 4096×2160@60

        60 => [35651584, 1069547520, 240000], // eg. 8192×4320@30
        61 => [35651584, 2139095040, 480000], // eg. 8192×4320@60
        62 => [35651584, 4278190080, 800000], // eg. 8192×4320@120
    ];

    /**
     * Defines implemented profiles.
     * The value defines how much higher the bitrate is allowed to be compared to to main/baseline.
     */
    const PROFILE_BITRATE_MULTIPLIER = [
        'baseline' => 1.0,
        'main' => 1.0,
        'high' => 1.25,
    ];

    const PERFORMANCE_PRESETS = [
        'ultrafast',
        'veryfast',
        'faster',
        'fast',
        'medium',
        'slow',
        'slower',
        'veryslow',
        'placebo',
    ];

    /**
     * @var string
     */
    private $profile = 'main';

    /**
     * @var int
     */
    private $level = 31;

    /**
     * @var string
     */
    private $performance = 'fast';

    public function requiresTranscoding(array $sourceStream): bool
    {
        if (parent::requiresTranscoding($sourceStream)) {
            return true;
        }

        if (!isset($sourceStream['profile']) || strcasecmp($sourceStream['profile'], $this->getProfile()) === 0) {
            return true;
        }

        if (!isset($sourceStream['level']) || $sourceStream['level'] > $this->getLevel()) {
            return true;
        }

        if (!isset($sourceStream['bit_rate']) || $sourceStream['bit_rate'] > $this->getBitrateLimit($sourceStream)) {
            return true;
        }

        return false;
    }

    public function getEncoderParameters(array $sourceStream): array
    {
        $parameters = [];

        array_push($parameters, '-c:v', 'libx264');
        array_push($parameters, '-preset:v', $this->getPerformance());
        array_push($parameters, '-profile:v', $this->getProfile());
        array_push($parameters, '-level:v', $this->getLevel());
        array_push($parameters, '-crf:v', (string)$this->getCrf());

        $bitrate = round($this->getBitrateLimit($sourceStream) / 1024 / 8);
        array_push($parameters, '-maxrate:v', $bitrate * 8 . 'k');
        array_push($parameters, '-bufsize:v', $bitrate * 10 . 'k');

        return $parameters;
    }

    public function getCodecName(): string
    {
        return 'h264';
    }

    public function getEncoderName(): string
    {
        return 'libx264';
    }

    /**
     * Calculates a bitrate limit based on the video dimensions, the framerate and the target quality.
     *
     * @param array $sourceStream
     *
     * @return int
     */
    public function getBitrateLimit(array $sourceStream): int
    {
        list($width, $height) = $this->getDimensions($sourceStream);
        $framerate = MathUtility::calculateWithParentheses($this->getFramerate($sourceStream));
        $quality = $this->getQuality();

        // here is the effect that quality has on a 720p and 1080p video with 30fps
        // http://fooplot.com/?lang=de#W3sidHlwZSI6MCwiZXEiOiIxMjgwKjcyMCooMzAqKjAuNSkqKDAuMit4KioyKSowLjYvMTAyNCIsImNvbG9yIjoiIzAwMDAwMCJ9LHsidHlwZSI6MCwiZXEiOiIxOTIwKjEwODAqKDMwKiowLjUpKigwLjIreCoqMikqMC42LzEwMjQiLCJjb2xvciI6IiMwMDAwMDAifSx7InR5cGUiOjEwMDAsIndpbmRvdyI6WyIwIiwiMSIsIjAiLCI4MDAwIl19XQ--
        // here is the effect on framerate while at 720p and 1080p at 80% quality
        // http://fooplot.com/?lang=de#W3sidHlwZSI6MCwiZXEiOiIxMjgwKjcyMCooeCoqMC41KSooMC4yKzAuOCoqMikqMC42LzEwMjQiLCJjb2xvciI6IiMwMDAwMDAifSx7InR5cGUiOjAsImVxIjoiMTkyMCoxMDgwKih4KiowLjUpKigwLjIrMC44KioyKSowLjYvMTAyNCIsImNvbG9yIjoiIzAwMDAwMCJ9LHsidHlwZSI6MTAwMCwid2luZG93IjpbIjEwIiwiNjAiLCIwIiwiODAwMCJdfV0-
        $bitrateMultiplier = 0.6;
        return floor(min(
            $width * $height * ($framerate ** 0.5) * (0.2 + $quality ** 2.0) * $bitrateMultiplier,
            self::LEVEL_DEFINITION[$this->getLevel()][2] * self::PROFILE_BITRATE_MULTIPLIER[$this->getProfile()] * 1000
        ));
    }

    /**
     * Calculate the maximum resolution allowed by the current level within the maximum framerate.
     *
     * @return int
     */
    public function getMaxResolution(): int
    {
        $levelDefinition = self::LEVEL_DEFINITION[$this->getLevel()];
        return min($levelDefinition[0], $levelDefinition[1] / $this->getMaxFramerate());
    }

    /**
     * target a constant quality.
     * The idea is that a bitrate target might produce unnecessarily big files if there is little movement
     * crf will always reduce the quality to the target. There is an additional bitrate limit below.
     * for h264 the range should is 51-0 according to ffmpeg https://trac.ffmpeg.org/wiki/Encode/H.264#crf
     * The recommended range however is 18 to 28
     * quality 1.0 = crf 18
     * quality 0.9 = crf 21
     * quality 0.8 = crf 24
     * quality 0.7 = crf 27
     * quality 0.6 = crf 30
     * quality 0.5 = crf 33
     * quality 0.4 = crf 36
     * quality 0.3 = crf 39
     * quality 0.2 = crf 42
     * quality 0.1 = crf 45
     *
     * @return int
     */
    protected function getCrf(): int
    {
        return round(48 - $this->getQuality() * 30);
    }

    public function getProfile(): string
    {
        return $this->profile;
    }

    public function setProfile(string $profile): void
    {
        if (!isset(self::PROFILE_BITRATE_MULTIPLIER[$profile])) {
            $possibleProfiles = implode(', ', array_keys(self::PROFILE_BITRATE_MULTIPLIER));
            throw new \RuntimeException("Profile $profile does not exist. Possible profiles are: $possibleProfiles");
        }

        $this->profile = $profile;
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function setLevel(string $level): void
    {
        $level = strtr($level, ['.' => '']);
        if (!isset(self::LEVEL_DEFINITION[$level])) {
            $possibleLevels = implode(', ', array_keys(self::LEVEL_DEFINITION));
            throw new \RuntimeException("Level $level is not defined. Possible levels are: $possibleLevels");
        }

        $this->level = $level;
    }

    public function getPerformance(): string
    {
        return $this->performance;
    }

    public function setPerformance(string $performance): void
    {
        if (!in_array($performance, self::PERFORMANCE_PRESETS, true)) {
            $possibleSpeeds = implode(', ', self::PERFORMANCE_PRESETS);
            throw new \RuntimeException("Speed setting $performance is not defined. Possible levels are: $possibleSpeeds");
        }

        $this->performance = $performance;
    }
}
