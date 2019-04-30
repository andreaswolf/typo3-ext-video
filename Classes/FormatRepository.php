<?php

namespace Hn\HauptsacheVideo;


use Hn\HauptsacheVideo\Exception\FormatException;
use Hn\HauptsacheVideo\Preset\AbstractVideoPreset;
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

    protected function getPresets(array $options = [], array $sourceStreams = null): array
    {
        $result = [];

        $options = $this->normalizeOptions($options);
        $definition = $this->findFormatDefinition($options);

        foreach (['video', 'audio', 'subtitle', 'data'] as $steamType) {
            if (empty($definition[$steamType])) {
                continue;
            }

            if ($options[$steamType]['disabled'] ?? false) {
                continue;
            }

            $sourceStream = [];
            if ($sourceStreams !== null) {
                $sourceStreamIndex = array_search($steamType, array_column($sourceStreams, 'codec_type'));
                if ($sourceStreamIndex === false) {
                    continue;
                } else {
                    $sourceStream = $sourceStreams[$sourceStreamIndex];
                }
            }

            $preset = GeneralUtility::makeInstance(...$definition[$steamType]);
            if (!$preset instanceof PresetInterface) {
                $type = is_object($preset) ? get_class($preset) : gettype($preset);
                throw new \RuntimeException("Expected " . PresetInterface::class . ", got $type");
            }

            if (isset($options[$steamType])) {
                $preset->setOptions($options[$steamType]);
            }

            $result[$steamType] = [
                'preset' => $preset,
                'stream' => $sourceStream,
            ];
        }

        return $result;
    }

    public function getProperties(array $options = [], array $sourceStreams = null): array
    {
        $properties = [];
        $presets = $this->getPresets($options, $sourceStreams);
        foreach ($presets as $preset) {
            if ($preset['preset'] instanceof AbstractVideoPreset) {
                list($properties['width'], $properties['height']) = $preset['preset']->getDimensions($preset['stream']);
            }
        }
        return $properties;
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

        $presets = $this->getPresets($options, $sourceStreams);
        $streamTypes = ['video' => '-vn', 'audio' => '-an', 'subtitle' => '-sn', 'data' => '-dn'];
        foreach ($streamTypes as $steamType => $disableParameter) {
            if (!isset($presets[$steamType])) {
                array_push($parameters, $disableParameter);
                continue;
            }

            $preset = $presets[$steamType];
            if (!$preset['preset'] instanceof PresetInterface) {
                $type = is_object($preset['preset']) ? get_class($preset['preset']) : gettype($preset['preset']);
                throw new \RuntimeException("Expected PresetInterface, got $type");
            }

            array_push($parameters, ...$preset['preset']->getParameters($preset['stream']));
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
        $escapeShellArg = static function ($parameter) {
            return preg_match('#^[\w:.-]+$#', $parameter) ? $parameter : escapeshellarg($parameter);
        };

        $parameters = $this->buildParameters($input, $output, $options, $sourceStreams);
        $parameters = array_map($escapeShellArg, $parameters);
        return implode(' ', $parameters);
    }

    /**
     * This method normalizes the given options. This is important to prevent unnecessary reencodes.
     *
     * It is currently not possible to hook into the typo3 processing pipeline before it searches for a processed file.
     * That means that you must do the normalization yourself before asking typo3 for a processed video.
     *
     * @param array $options
     *
     * @return array
     * @todo this method must take much more effort to normalize the parameters because unnecessary encodes are horrible
     */
    public static function normalizeOptions(array $options): array
    {
        $result = [
            'format' => $options['format'] ?? 'mp4',
            'audio' => $options['audio'] ?? [],
            'video' => $options['video'] ?? [],
        ];

        if (!empty($options['width'])) {
            $result['video']['maxWidth'] = intval($options['width']);
            if (substr($options['width'], -1)) {
                $result['video']['crop'] = true;
            }
        }

        if (!empty($options['height'])) {
            $result['video']['maxHeight'] = intval($options['height']);
            if (substr($options['height'], -1)) {
                $result['video']['crop'] = true;
            }
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
