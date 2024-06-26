<?php

namespace Hn\Video;

use TYPO3\CMS\Core\Utility\GeneralUtility;

class TypeUtility
{
    public const AUDIO_MIME_TYPES = [
        'audio/aac',
        'audio/mpeg',
        'audio/ogg',
        'application/ogg',
        'audio/wav',
        'audio/webm',
        'audio/3gpp',
        'audio/3gpp2',
    ];

    public const VIDEO_MIME_TYPES = [
        'video/mp4',
        'video/x-matroska',
        'video/quicktime',
        'video/x-msvideo',
        'video/mpeg',
        'video/ogg',
        'application/ogg',
        'video/webm',
        'video/3gpp',
        'video/3gpp2',
    ];

    public static function getBaseMimeType(string$originalMimeType): string
    {
        [$mimeType] = GeneralUtility::trimExplode(';', $originalMimeType, true);
        return strtolower($mimeType);
    }

    /**
     * This utility compares mime types.
     *
     * It respects case-insensitivity and also mime type extensions.
     */
    public static function inList(string $mimeType, array $list): bool
    {
        return in_array(self::getBaseMimeType($mimeType), array_map('static::getBaseMimeType', $list), true);
    }
}
