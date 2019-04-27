<?php

namespace Hn\HauptsacheVideo\Preset;


use TYPO3\CMS\Core\Utility\MathUtility;

class H264Preset extends AbstractVideoPreset
{
    /**
     * Defines the limits of the different h264 levels.
     * 1. macroblocks per frame
     * 2. macroblocks per second
     * 3. max bitrate per second in kbit/s
     *
     * @see https://en.wikipedia.org/wiki/H.264/MPEG-4_AVC#Levels
     */
    const LEVEL_DEFINITION = [
        10 => [99, 1485, 64], // eg. 128×96@30 176×144@15
        11 => [396, 3000, 192], // eg. 176×144@30 352×288@7.5
        12 => [396, 6000, 384], // eg. 176×144@60 352×288@15
        13 => [396, 11880, 768], // eg. 352×288@30

        20 => [396, 11880, 2000], // eg. 352×288@30
        21 => [792, 19800, 4000], // eg. 352×480@30 352×576@25
        22 => [1620, 20250, 4000], // eg. 352×480@30 720×576@12.5

        30 => [1620, 40500, 10000], // eg. 720×480@30
        31 => [3600, 108000, 14000], // eg. 1280×720@30
        32 => [6120, 216000, 14000], // eg. 1280×720@60

        40 => [8192, 245760, 20000], // eg. 1920x1080@30
        41 => [8192, 245760, 50000], // eg. 1920x1080@30 but with higher bitrate
        42 => [8704, 522240, 50000], // eg. 1920x1080@60

        // please be *very* careful when using anything below this comment
        // and be sure to suggest youtube or vimeo over and over again before caving ...

        50 => [22080, 589824, 135000], // eg. 2560×1920@30
        51 => [36864, 983040, 240000], // eg. 4096×2048@30
        52 => [36864, 2073600, 240000], // eg. 4096×2160@60

        60 => [139264, 4177920, 240000], // eg. 8192×4320@30
        61 => [139264, 8355840, 480000], // eg. 8192×4320@60
        62 => [139264, 16711680, 800000], // eg. 8192×4320@120
    ];

    /**
     * The side length of a macroblock. (16x16)
     * This value is required to calculate the max dimension in a level.
     */
    const MACROBLOCK_SIZE = 16;

    /**
     * Defines implemented profiles.
     * The value defines how much higher the bitrate is allowed to be compared to to main/baseline.
     *
     * @see https://en.wikipedia.org/wiki/H.264/MPEG-4_AVC#Levels
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
     *
     * Baseline contains loss prevention features that are not present in the other profiles.
     * For that reason I rather reencode baseline to main or high.
     * Also web video is (mostly) distributed over tcp so there shouldn't be losses.
     *
     * @see https://en.wikipedia.org/wiki/H.264/MPEG-4_AVC#Feature_support_in_particular_profiles
     * @see https://www.vocal.com/video/profiles-and-levels-in-h-264-avc/
     */
    const PROFILES_ALLOWED_MAP = [
        'baseline' => ['baseline'],
        'main' => ['main'],
        'high' => ['high', 'main'],
    ];

    /**
     * @see H264Preset::$preset
     */
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
     * If null than the profile will be chosen based on the level.
     * Basically if the level is >= 4 than high will be used, main otherwise.
     *
     * @var string|null
     */
    private $profile = null;

    /**
     * @var int
     * @see https://en.wikipedia.org/wiki/H.264/MPEG-4_AVC#Levels
     */
    private $level = 30;

    /**
     * The performance preset.
     *
     * I decided to be boring and go with medium here.
     * Going in either direction had diminishing returns in speed or quality.
     * If encode time does not really matter to you go with slower or veryslow.
     *
     * @var string
     * @see H264Preset::PERFORMANCE_PRESETS
     * @see http://dev.beandog.org/x264_preset_reference.html
     * @see https://encodingwissen.de/codecs/x264/referenz/
     */
    private $preset = 'medium';

    public function getCodecName(): string
    {
        return 'h264';
    }

    /**
     * This method determines the macroblock limit per frame.
     * This value is influenced by the level and the _max_ framerate.
     *
     * The reason the real framerate determined by the source isn't used is for consistency.
     * I don't want someone to use a lower framerate video to squeeze more resolution out of this preset.
     * That behavior would be unexpected.
     * Besides, that would only work if the level was below 3.0 or the framerate limit above 30
     * since all level definitions starting with 3.0 are intended for 30 fps anyways.
     *
     * @return int
     */
    protected function getMaxMacroblocks(): int
    {
        $levelDefinition = self::LEVEL_DEFINITION[$this->getLevel()];
        return min($levelDefinition[0], floor($levelDefinition[1] / $this->getMaxFramerate()));
    }

    /**
     * Limit the scale factor (and therefor the resolution) by the macroblock limit.
     *
     * @param float[] $sourceDimensions
     *
     * @return float
     */
    protected function getScaleFactor(array $sourceDimensions): float
    {
        $maxMacroblocks = $this->getMaxMacroblocks();
        $macroblocks = [
            floor(sqrt($maxMacroblocks / $sourceDimensions[1] * $sourceDimensions[0])),
            floor(sqrt($maxMacroblocks / $sourceDimensions[0] * $sourceDimensions[1])),
        ];

        // i haven't found a more elegant way to distribute the macroblocks on both dimensions
        // there probably is one but i'm currently too dumb to see it
        for (; ;) {
            $scaleFactors = [
                $macroblocks[0] / $sourceDimensions[0] * self::MACROBLOCK_SIZE,
                $macroblocks[1] / $sourceDimensions[1] * self::MACROBLOCK_SIZE,
            ];

            if ($scaleFactors[0] < $scaleFactors[1]) {
                if (($macroblocks[0] + 1) * $macroblocks[1] <= $maxMacroblocks) {
                    $macroblocks[0] += 1;
                    continue;
                }
            } else {
                if (($macroblocks[1] + 1) * $macroblocks[0] <= $maxMacroblocks) {
                    $macroblocks[1] += 1;
                    continue;
                }
            }

            break;
        }

        return min(parent::getScaleFactor($sourceDimensions), ...$scaleFactors);
    }

    /**
     * The maximum bitrate allowed by the configured level.
     *
     * @return int
     */
    protected function getBitrateLimit(): int
    {
        $profileModifier = self::PROFILE_BITRATE_MULTIPLIER[$this->getProfile()];
        return self::LEVEL_DEFINITION[$this->getLevel()][2] * $profileModifier;
    }

    /**
     * Calculates the bitrate in kbit/s.
     * The equation is somewhat arbitrary and build by try&error to target specific bitrates at 80% quality.
     *
     * @param array $sourceStream
     *
     * @return int
     * @see http://fooplot.com/#W3sidHlwZSI6MCwiZXEiOiIoeCoqMiowLjkrMC4xKSooKDM4NDAqMjE2MCkqKjAuOSkqKDMwKiowLjUpKjAuMDAzIiwiY29sb3IiOiIjMDAwMDAwIn0seyJ0eXBlIjowLCJlcSI6Iih4KioyKjAuOSswLjEpKigoMTkyMCoxMDgwKSoqMC45KSooMzAqKjAuNSkqMC4wMDMiLCJjb2xvciI6IiMwMDAwMDAifSx7InR5cGUiOjAsImVxIjoiKHgqKjIqMC45KzAuMSkqKCgxMjgwKjcyMCkqKjAuOSkqKDMwKiowLjUpKjAuMDAzIiwiY29sb3IiOiIjMDAwMDAwIn0seyJ0eXBlIjowLCJlcSI6Iih4KioyKjAuOSswLjEpKigoNjQwKjM2MCkqKjAuOSkqKDMwKiowLjUpKjAuMDAzIiwiY29sb3IiOiIjMDAwMDAwIn0seyJ0eXBlIjoxMDAwLCJ3aW5kb3ciOlsiMCIsIjEuMCIsIjAiLCIxNTAwMCJdfV0-
     */
    public function getMaxBitrate(array $sourceStream): int
    {
        $pixels = array_product($this->getDimensions($sourceStream));
        $framerate = MathUtility::calculateWithParentheses($this->getFramerate($sourceStream));
        $quality = $this->getBoostedQuality($sourceStream) ** 2 * 0.9 + 0.1;
        $bitrate = round($pixels ** 0.9 * $framerate ** 0.5 * $quality * 0.003);
        return min($bitrate, $this->getBitrateLimit());
    }

    /**
     * Constant Rate factor.
     *
     * The idea is that a bitrate target might produce unnecessarily big files if there is little movement
     * crf will always reduce the quality to the target.
     *
     * for h264 the range should is 51-0 according to ffmpeg https://trac.ffmpeg.org/wiki/Encode/H.264#crf
     * The recommended range is 18 to 28
     * Every 6 points = halfing of the size which i mapped to every 0.2 quality percent
     * quality 1.0 = crf 18
     * quality 0.8 = crf 24
     * quality 0.6 = crf 30
     *
     * @param array $sourceStream
     *
     * @return float
     * @see http://fooplot.com/#W3sidHlwZSI6MCwiZXEiOiI0MysoMTgtNDMpKngiLCJjb2xvciI6IiMwMDAwMDAifSx7InR5cGUiOjEwMDAsIndpbmRvdyI6WyIwIiwiMSIsIjAiLCI1MCJdfV0-
     */
    public function getCrf(array $sourceStream): float
    {
        $max = 18;
        $min = $max + 6 / 0.2; // +6 every 0.2 for half the bitrate
        return $min + ($max - $min) * $this->getBoostedQuality($sourceStream);
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

        if (!isset($sourceStream['level']) || $sourceStream['level'] > $this->getLevel() || $sourceStream['level'] < 10) {
            return true;
        }

        return false;
    }

    protected function getEncoderParameters(array $sourceStream): array
    {
        $parameters = [];

        array_push($parameters, '-c:v', 'libx264');
        array_push($parameters, '-preset:v', $this->getPreset());
        array_push($parameters, '-profile:v', $this->getProfile());
        array_push($parameters, '-level:v', $this->getLevel());
        array_push($parameters, '-crf:v', (string)round($this->getCrf($sourceStream), 2));

        $bitrate = $this->getMaxBitrate($sourceStream);
        $bufsize = min($bitrate * 5, $this->getBitrateLimit());
        array_push($parameters, '-maxrate:v', $bitrate . 'k');
        array_push($parameters, '-bufsize:v', $bufsize . 'k');

        return $parameters;
    }

    public function getProfile(): string
    {
        if ($this->profile === null) {
            return $this->getLevel() >= 40 ? 'high' : 'main';
        }

        return $this->profile;
    }

    public function setProfile(?string $profile): void
    {
        if ($profile !== null && !isset(self::PROFILE_BITRATE_MULTIPLIER[$profile])) {
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

    public function getPreset(): string
    {
        return $this->preset;
    }

    public function setPreset(string $preset): void
    {
        if (!in_array($preset, self::PERFORMANCE_PRESETS, true)) {
            $possibleSpeeds = implode(', ', self::PERFORMANCE_PRESETS);
            throw new \RuntimeException("Speed setting $preset is not defined. Possible levels are: $possibleSpeeds");
        }

        $this->preset = $preset;
    }
}
