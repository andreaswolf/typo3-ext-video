<?php

namespace Hn\HauptsacheVideo\Presets;


class Mp4Preset implements FFmpegPresetInterface
{
    /**
     * Defines the limits of the different h264 levels.
     * 1. luma samples per frame
     * 2. luma samples per second
     * 3. max bitrate per second in kbit/s
     * https://en.wikipedia.org/wiki/H.264/MPEG-4_AVC#Levels
     *
     * I do not define every preset here because i am lazy
     * and you are insane for encoding 8k 120fps footage using this extension.
     * If you are serious with video encoding use a proper video platform
     * where people spent years on getting the best possible experience to every obscure device possible.
     */
    const LEVEL_DEFINITION = [
        '3.0' => [414720, 10368000, 10000], // eg. 720×480@30
        '3.1' => [921600, 27648000, 14000], // eg. 1280×720@30
        '3.2' => [1310720, 55296000, 14000], // eg. 1280×720@60
        '4.0' => [2097152, 62914560, 20000], // eg. 1920x1080@30
        '4.1' => [2097152, 62914560, 50000], // eg. 1920x1080@30 but with higher bitrate
        '4.2' => [2228224, 133693440, 50000], // eg. 1920x1080@60
        '5.0' => [5652480, 150994944, 135000], // eg. 2560×1920@30
        '5.1' => [9437184, 251658240, 240000], // eg. 4096×2048@30
        '5.2' => [9437184, 530841600, 240000], // eg. 4096×2160@60
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

    const PRESETS = [
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
    private $profile;

    /**
     * @var string
     */
    private $level;

    /**
     * @var float
     */
    private $bitrateMultiplier;

    /**
     * @var float
     */
    private $maxFramerate;

    /**
     * @var string
     */
    private $preset;

    /**
     * Allows specifying the compatibility.
     *
     * @param string $profile The feature set to use while encoding. I default to "main" here for compatibility.
     *                        https://source.android.com/compatibility/android-cdd#5_3_4_h_264
     * @param string $level The video complexity limit. Lower values might limit your resolution and framerate.
     *                      But your user will thank you for not draining their battery with a background video.
     *                      https://en.wikipedia.org/wiki/H.264/MPEG-4_AVC#Levels
     * @param float $bitrateMultiplier Defines the max bitrate that is allowed during encoding.
     *                                 This is is bits per pixel per second times quality.
     *                                 The max bitrate might also be limited by the complexity level.
     * @param float $maxFramerate The maximum framerate that is encoded.
     *                            Default 30 but in special cases you might like 60.
     *                            You framerate might also be limited by the complexity level.
     * @param string $preset How much effort the encoding process should take.
     */
    public function __construct(string $profile = 'main', string $level = '3.1', float $bitrateMultiplier = 4.0, float $maxFramerate = 30, string $preset = 'fast')
    {
        if (!isset(self::PROFILE_BITRATE_MULTIPLIER[$profile])) {
            throw new \RuntimeException("The profile $profile is not defined.");
        }

        if (!isset(self::LEVEL_DEFINITION[$level])) {
            throw new \RuntimeException("The level $level is not defined.");
        }

        if ($bitrateMultiplier <= 0.0) {
            throw new \RuntimeException("Invalid bitrate multiplier.");
        }

        if ($maxFramerate <= 0) {
            throw new \RuntimeException("Invalid framerate.");
        }

        if (!in_array($preset, self::PRESETS)) {
            throw new \RuntimeException("Unknown preset $preset.");
        }

        $this->profile = $profile;
        $this->level = $level;
        $this->bitrateMultiplier = $bitrateMultiplier;
        $this->maxFramerate = $maxFramerate;
        $this->preset = $preset;
    }

    protected function getVideoParameters(int $width, int $height, float $quality): array
    {
        if ($width < 16 || $height < 16) {
            throw new \RuntimeException("The minimum resolution is 16x16, got {$width}x{$height}");
        }

        if ($quality <= 0.0 || $quality > 1.0) {
            throw new \RuntimeException("Quality must be higher than 0.0 and lower or equal to 1.0, got $quality.");
        }

        $divisor = 2; // chroma sub-sampling requires the resolution to be divisible by 2
        $width = round($width / $divisor) * $divisor;
        $height = round($height / $divisor) * $divisor;

        $maxResolutionPerFrame = self::LEVEL_DEFINITION[$this->level][0];
        if (($width * $height) > $maxResolutionPerFrame) {
            $msg = "The desired resolution ({$width}x{$height}) exceeds the h264 level $this->level capabilities.";
            trigger_error($msg, E_USER_NOTICE);
            $scalingFactor = $maxResolutionPerFrame / ($width * $height);
            $width = floor($width * $scalingFactor / $divisor) * $divisor;
            $height = floor($height * $scalingFactor / $divisor) * $divisor;
        }

        $parameters = [];

        // limit resolution to the specified values
        array_push($parameters, '-vf', implode(',', [
            "scale=$width:$height:force_original_aspect_ratio=increase",
            "crop=$width:$height",
        ]));

        array_push($parameters, '-c:v', 'libx264');
        array_push($parameters, '-preset', $this->preset);

        $maxResolutionPerSecond = self::LEVEL_DEFINITION[$this->level][1];
        $framerate = min($this->maxFramerate, $maxResolutionPerSecond / ($width * $height));
        array_push($parameters, '-r', round($framerate, 2, PHP_ROUND_HALF_DOWN), '-vsync', 'vfr');

        // limit the capabilities to ensure max compatibility
        array_push($parameters, '-profile:v', $this->profile);
        array_push($parameters, '-level', $this->level);

        // chroma sub-sampling for higher compression and compatibility
        // this is technically already covered by the profile selection
        // but in web video there is seldom a reason to waste bandwidth with no chroma sub-sampling
        array_push($parameters, '-pix_fmt', '+yuv420p');

        // target a constant quality.
        // The idea is that a bitrate target might produce unnecessarily big files if there is little movement
        // crf will always reduce the quality to the target. There is an additional bitrate limit below.
        // for h264 the range should is 51-0 according to ffmpeg https://trac.ffmpeg.org/wiki/Encode/H.264#crf
        // The recommended range however is 18 to 28
        // quality 1.0 = crf 18
        // quality 0.9 = crf 20
        // quality 0.8 = crf 22
        // quality 0.7 = crf 24
        // quality 0.6 = crf 26
        // quality 0.5 = crf 28
        array_push($parameters, '-crf', round(38 - $quality * 20));

        // limit the bitrate to a value calculated by the resolution
        // this ensures that a video with a lot of movement does not explode in file size
        // 1280 * 720 * (1.0**2) * 4.0 / 1024 = 3600 kbit/s
        // 1280 * 720 * (0.9**2) * 4.0 / 1024 = 2916 kbit/s
        // 1280 * 720 * (0.8**2) * 4.0 / 1024 = 2304 kbit/s
        // 1280 * 720 * (0.7**2) * 4.0 / 1024 = 1763 kbit/s
        // 1280 * 720 * (0.6**2) * 4.0 / 1024 = 1296 kbit/s
        // 1280 * 720 * (0.5**2) * 4.0 / 1024 = 900 kbit/s
        // 1920 * 1080 * (1.0**2) * 4.0 / 1024 = 8100 kbit/s
        // 1920 * 1080 * (0.9**2) * 4.0 / 1024 = 6561 kbit/s
        // 1920 * 1080 * (0.8**2) * 4.0 / 1024 = 5184 kbit/s
        // 1920 * 1080 * (0.7**2) * 4.0 / 1024 = 3968 kbit/s
        // 1920 * 1080 * (0.6**2) * 4.0 / 1024 = 2916 kbit/s
        // 1920 * 1080 * (0.5**2) * 4.0 / 1024 = 2025 kbit/s
        $bitrate = floor(min(
            $width * $height * ($quality ** 2.0) * $this->bitrateMultiplier / 1024,
            self::LEVEL_DEFINITION[$this->level][2] * self::PROFILE_BITRATE_MULTIPLIER[$this->profile]
        ));
        array_push($parameters, '-maxrate', $bitrate . 'k');
        array_push($parameters, '-bufsize', round($bitrate * 0.66) . 'k');

        return $parameters;
    }

    protected function getAudioParameters(float $quality): array
    {
        $parameters = [];
        array_push($parameters, '-c:a', 'aac'); // native aac encoder ~ libfdk_aac probably is better but might not be present

        // http://wiki.hydrogenaud.io/index.php?title=Fraunhofer_FDK_AAC#Recommended_Sampling_Rate_and_Bitrate_Combinations
        // there is also an astonishing amount of incompatibility with some android devices so stick to that table.
        // https://developer.android.com/guide/topics/media/media-formats#video-encoding
        if ($quality >= 0.9) {
            array_push($parameters, '-ac', '2');
            array_push($parameters, '-af', 'aformat=sample_fmts=u8|s16:sample_rates=32000|44100|48000');
            array_push($parameters, '-b:a', '160k'); // max bitrate an 1st gen iPad can decode
        } else if ($quality >= 0.8) {
            array_push($parameters, '-ac', '2');
            array_push($parameters, '-af', 'aformat=sample_fmts=u8|s16:sample_rates=32000|44100|48000');
            array_push($parameters, '-b:a', '128k');
        } else if ($quality >= 0.7) {
            array_push($parameters, '-ac', '1');
            array_push($parameters, '-af', 'aformat=sample_fmts=u8|s16:sample_rates=32000|44100|48000');
            array_push($parameters, '-b:a', '64k');
        } else {
            array_push($parameters, '-ac', '1');
            array_push($parameters, '-af', 'aformat=sample_fmts=u8|s16:sample_rates=16000|22050|24000');
            array_push($parameters, '-b:a', '24k');
        }

        return $parameters;
    }

    public function getParameters(int $width, int $height, bool $audio, float $quality): array
    {
        $parameters = $this->getVideoParameters($width, $height, $quality);

        if ($audio) {
            array_push($parameters, ...$this->getAudioParameters($quality));
        } else {
            array_push($parameters, '-an');
        }

        array_push($parameters, '-movflags', '+faststart');
        array_push($parameters, '-f', 'mp4');
        return $parameters;
    }
}
