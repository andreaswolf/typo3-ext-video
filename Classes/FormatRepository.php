<?php

namespace Hn\HauptsacheVideo;


use Hn\HauptsacheVideo\Exception\FormatException;
use Hn\HauptsacheVideo\Preset\PresetInterface;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FormatRepository implements SingletonInterface
{
    public function findFormatDefinition(string $format): ?array
    {
        $formats = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['hauptsache_video']['formats'] ?? [];

        if (isset($formats[$format])) {
            return $formats[$format];
        }

        if (isset($formats["$format:default"])) {
            return $formats["$format:default"];
        }

        return null;
    }

    public function buildParameters(string $format, array $options = [], array $sourceStreams = null)
    {
        $parameters = [];
        $definition = $this->findFormatDefinition($format);

        if ($definition === null) {
            throw new FormatException("Format '$format' not found.");
        }

        foreach (['video' => '-vn', 'audio' => '-an', 'subtitle' => '-sn'] as $steamType => $disableParameter) {
            if (!isset($definition[$steamType])) {
                array_push($parameters, $disableParameter);
                continue;
            }

            $sourceStream = [];
            if ($sourceStreams !== null) {
                $sourceStreamIndex = array_search($steamType, array_column($sourceStreams, 'codec_type'));

                // disable this stream type if the source does not contain it
                if ($sourceStreamIndex === false) {
                    array_push($parameters, $disableParameter);
                    continue;
                }

                $sourceStream = $sourceStreams[$sourceStreamIndex];
            }

            $videoPreset = GeneralUtility::makeInstance(...$definition[$steamType]);
            if (!$videoPreset instanceof PresetInterface) {
                $type = is_object($videoPreset) ? get_class($videoPreset) : gettype($videoPreset);
                throw new \RuntimeException("Expected " . PresetInterface::class . ", got $type");
            }

            if (isset($options[$steamType])) {
                $videoPreset->setOptions($options[$steamType]);
            }

            array_push($parameters, ...$videoPreset->getParameters($sourceStream));
        }

        if (isset($definition['additionalParameters'])) {
            array_push($parameters, ...$definition['additionalParameters']);
        }

        return $parameters;
    }
}
