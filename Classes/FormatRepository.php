<?php

namespace Hn\HauptsacheVideo;


use Hn\HauptsacheVideo\Exception\FormatException;
use Hn\HauptsacheVideo\Preset\PresetInterface;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

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

    public function buildParameters(?string $input, ?string $output, array $options = [], array $sourceStreams = null): array
    {
        $parameters = [];
        $options = $this->normalizeOptions($options);

        $definition = $this->findFormatDefinition($options);
        if ($definition === null) {
            throw new FormatException("No format defintion found for configuration: " . print_r($options, true));
        }

        if ($input !== null) {
            if (isset($options['start'])) {
                array_push($parameters, '-ss', $options['start']);
            }

            if (isset($options['duration'])) {
                array_push($parameters, '-t', $options['duration']);
            }

            array_push($parameters, '-i', $input);
        }

        foreach (['video' => '-vn', 'audio' => '-an', 'subtitle' => '-sn', 'data' => '-dn'] as $steamType => $disableParameter) {
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

        if ($output !== null) {
            array_push($parameters, '-y', $output);
        }

        return $parameters;
    }

    public function buildParameterString(?string $input, ?string $output, array $options = [], array $sourceStreams = null): string
    {
        $parameters = $this->buildParameters($input, $output, $options, $sourceStreams);
        $parameters = array_map('escapeshellarg', $parameters);
        return implode(' ', $parameters);
    }

    /**
     * @param array $options
     *
     * @return array
     */
    public static function normalizeOptions(array $options): array
    {
        $result = [
            'format' => $options['format'] ?? 'mp4',
            'audio' => $options['audio'] ?? [],
            'video' => $options['video'] ?? [],
        ];

        if (isset($options['width']) && is_numeric($options['width'])) {
            $result['video']['maxWidth'] = intval($options['width']);
        }

        if (isset($options['height']) && is_numeric($options['height'])) {
            $result['video']['maxHeight'] = intval($options['height']);
        }

        if (!empty($options['muted'])) {
            $result['audio']['disabled'] = true;
        }

        if (!empty($options['start'])) {
            $result['start'] = $options['start'];
        }

        if (!empty($options['duration'])) {
            $result['duration'] = $options['duration'];
        }

        return $result;
    }
}
