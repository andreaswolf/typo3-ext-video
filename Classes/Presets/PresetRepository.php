<?php

namespace Hn\HauptsacheVideo\Presets;


use Hn\HauptsacheVideo\Exception\FormatException;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class PresetRepository implements SingletonInterface
{
    public function getPresetForFormat(string $format): FFmpegPresetInterface
    {
        $presets = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['hauptsache_video']['presets'] ?? [];
        if (!isset($presets[$format])) {
            throw new FormatException("Format '$format' not found.");
        }

        $preset = $presets[$format];
        if (is_array($preset)) {
            $preset = GeneralUtility::makeInstance(...$preset);
        }

        return $preset;
    }
}
