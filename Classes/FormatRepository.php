<?php

namespace Hn\HauptsacheVideo;


use Hn\HauptsacheVideo\Exception\FormatException;
use Hn\HauptsacheVideo\Preset\PresetInterface;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

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
        $definition = $this->findFormatDefinition($format);
        if ($definition === null) {
            throw new FormatException("Format '$format' not found.");
        }

        $parameters = [];
        $options = $this->normalizeOptions($options);

        foreach (['video' => '-vn', 'audio' => '-an', 'subtitle' => '-sn'] as $steamType => $disableParameter) {
            if (!isset($definition[$steamType]) || $options[$steamType]['disabled'] ?? false) {
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

        if (isset($options['quality']) && is_numeric($options['quality'])) {
            $quality = MathUtility::forceIntegerInRange($options['quality'], 1, 100);
            $options['video']['quality'] = (float)($quality * 0.01);
            $options['audio']['quality'] = (float)($quality * 0.01);
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
