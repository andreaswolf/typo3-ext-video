<?php

namespace Hn\HauptsacheVideo;


use Hn\HauptsacheVideo\Exception\FormatException;
use Hn\HauptsacheVideo\Preset\PresetInterface;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

class FormatRepository implements SingletonInterface
{
    public function findFormatDefinition(array $options): ?array
    {
        $formats = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['hauptsache_video']['formats'] ?? [];
        $format = $options['format'] ?? 'mp4';

        if (isset($formats[$format])) {
            return $formats[$format];
        }

        if (isset($formats["$format:default"])) {
            return $formats["$format:default"];
        }

        return null;
    }

    public function buildParameters(array $options = [], array $sourceStreams = null): array
    {
        $parameters = [];
        $options = $this->normalizeOptions($options);

        $definition = $this->findFormatDefinition($options);
        if ($definition === null) {
            throw new FormatException("No format defintion found for configuration: " . print_r($options, true));
        }

        foreach (['video' => '-vn', 'audio' => '-an', 'subtitle' => '-sn'] as $steamType => $disableParameter) {
            if (!isset($definition[$steamType])) {
                array_push($parameters, $disableParameter);
                continue;
            }

            if ($options[$steamType]['disabled'] ?? false) {
                array_push($parameters, $disableParameter);
                continue;
            }

            if ($sourceStreams !== null) {
                $sourceStreamIndex = array_search($steamType, array_column($sourceStreams, 'codec_type'));
                if ($sourceStreamIndex === false) {
                    // disable this stream type if the source does not contain it
                    array_push($parameters, $disableParameter);
                    continue;
                }

                $sourceStream = $sourceStreams[$sourceStreamIndex];
            } else {
                $sourceStream = [];
            }

            $preset = GeneralUtility::makeInstance(...$definition[$steamType]);
            if (!$preset instanceof PresetInterface) {
                $type = is_object($preset) ? get_class($preset) : gettype($preset);
                throw new \RuntimeException("Expected " . PresetInterface::class . ", got $type");
            }

            if (isset($options[$steamType])) {
                $preset->setOptions($options[$steamType]);
            }

            $streamParameters = $preset->getParameters($sourceStream);
            if (!empty($streamParameters)) {
                array_push($parameters, ...$streamParameters);
            }
        }

        if (!empty($definition['additionalParameters'])) {
            array_push($parameters, ...$definition['additionalParameters']);
        }

        return $parameters;
    }

    /**
     * @param array $options
     *
     * @return array
     */
    public static function normalizeOptions(array $options): array
    {
        if (isset($options['width']) && is_numeric($options['width'])) {
            $options['video']['maxWidth'] = intval($options['width']);
        }

        if (isset($options['height']) && is_numeric($options['height'])) {
            $options['video']['maxHeight'] = intval($options['height']);
        }

        if (isset($options['muted']) && $options['muted']) {
            $options['audio']['disabled'] = true;
        }

        return [
            'audio' => $options['audio'] ?? [],
            'video' => $options['video'] ?? [],
        ];
    }
}
