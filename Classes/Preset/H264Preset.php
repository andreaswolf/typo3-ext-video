<?php

namespace Hn\HauptsacheVideo\Preset;


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

    /**
     * Some profiles are subsets of others.
     * main is a subset of high.
     *
     * If high was requested and i get a low bitrate main than no transcoding is required.
     */
    const PROFILES_ALLOWED_MAP = [
        'baseline' => ['baseline'],
        'main' => ['main'],
        'high' => ['high', 'main'],
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

    public function getCodecName(): string
    {
        return 'h264';
    }

    protected function getMaxResolution(): int
    {
        $levelDefinition = self::LEVEL_DEFINITION[$this->getLevel()];
        return min($levelDefinition[0], $levelDefinition[1] / $this->getMaxFramerate());
    }

    protected function getBitsPerPixel(): float
    {
        // http://fooplot.com/#W3sidHlwZSI6MCwiZXEiOiIoKHgqKjIqMC44KzAuMikqMC42KSoxMjgwKjcyMCooMzAqKjAuNSkvMTAyNCIsImNvbG9yIjoiIzAwMDAwMCJ9LHsidHlwZSI6MTAwMCwid2luZG93IjpbIjAiLCIxIiwiMCIsIjUwMDAiXX1d
        $qualityFactor = $this->getQuality() ** 2 * 0.8 + 0.2;
        return 0.6 * $qualityFactor;
    }

    protected function getBitrateLimit(): int
    {
        $profileModifier = self::PROFILE_BITRATE_MULTIPLIER[$this->getProfile()];
        $levelLimit = self::LEVEL_DEFINITION[$this->getLevel()][2] * $profileModifier;
        return $levelLimit / 2; // stay well below the absolute limit since there will be spikes over this rate
    }

    /**
     * Constant Rate factor.
     *
     * The idea is that a bitrate target might produce unnecessarily big files if there is little movement
     * crf will always reduce the quality to the target.
     *
     * The quality reduction compared to the quality parameter is chosen to be modest on purpose.
     * If you reduce the quality you probably want to reduce the size more than you want to reduce the actual quality.
     * Moving scenes are way worse for the bitrate than still scenes and a high crf would make both look bad.
     * A low crf combined with a low limit will vary in quality more but will overall look better.
     *
     * for h264 the range should is 51-0 according to ffmpeg https://trac.ffmpeg.org/wiki/Encode/H.264#crf
     * The recommended range however is 18 to 28
     * quality 1.0 = crf 18
     * quality 0.9 = crf 21
     * quality 0.8 = crf 23
     * quality 0.7 = crf 25
     * quality 0.6 = crf 27
     * quality 0.5 = crf 28
     *
     * @see http://fooplot.com/#W3sidHlwZSI6MCwiZXEiOiJNYXRoLnJvdW5kKDI4LXgqKjMqMTApIiwiY29sb3IiOiIjMDAwMDAwIn0seyJ0eXBlIjoxMDAwLCJ3aW5kb3ciOlsiMCIsIjEiLCIwIiwiNTAiXX1d
     * @return int
     */
    protected function getCrf(): int
    {
        return round(30 - $this->getQuality() ** 2.5 * 12);
    }

    public function requiresTranscoding(array $sourceStream): bool
    {
        if (parent::requiresTranscoding($sourceStream)) {
            return true;
        }

        $allowedProfiles = self::PROFILES_ALLOWED_MAP[$this->getProfile()];
        if (!isset($sourceStream['profile']) || !in_array(strtolower($sourceStream['profile']), $allowedProfiles)) {
            return true;
        }

        if (!isset($sourceStream['level']) || $sourceStream['level'] > $this->getLevel()) {
            return true;
        }

        return false;
    }

    protected function getEncoderParameters(array $sourceStream): array
    {
        $parameters = [];

        array_push($parameters, '-c:v', 'libx264');
        array_push($parameters, '-preset:v', $this->getPerformance());
        array_push($parameters, '-profile:v', $this->getProfile());
        array_push($parameters, '-level:v', $this->getLevel());
        array_push($parameters, '-crf:v', (string)$this->getCrf());

        $bitrate = round($this->getMaxBitrate($sourceStream) / 8);
        array_push($parameters, '-maxrate:v', $bitrate * 8 . 'k');
        array_push($parameters, '-bufsize:v', $bitrate * 10 . 'k');

        return $parameters;
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
