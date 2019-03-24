<?php

namespace Hn\HauptsacheVideo\Converter;


use Hn\HauptsacheVideo\Exception\ConversionException;
use Hn\HauptsacheVideo\FormatRepository;
use Hn\HauptsacheVideo\PresetsOld\Mp4H264Preset;
use Hn\HauptsacheVideo\Processing\VideoProcessingTask;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class LocalVideoConverter implements VideoConverterInterface
{
    /**
     * Yes, i know, the CommandUtility is ment to be used statically.
     * But creating an instance allows overwriting it for testing and flexibility reasons.
     *
     * @var \TYPO3\CMS\Core\Utility\CommandUtility
     * @inject
     */
    protected $commandUtility;

    /**
     * @var \Hn\HauptsacheVideo\FormatRepository
     * @inject
     */
    protected $formatRepository;

    /**
     * @param VideoProcessingTask $task
     */
    public function start(VideoProcessingTask $task): void
    {
        // nothing to do here.
    }

    /**
     * @param VideoProcessingTask $task
     *
     * @throws ConversionException
     */
    public function process(VideoProcessingTask $task): void
    {
        $localFile = $task->getSourceFile()->getForLocalProcessing(false);
        $streams = $this->ffprobe($localFile)['streams'] ?? [];

        $tempFilename = tempnam(sys_get_temp_dir(), 'video');
        $parameters = $this->formatRepository->buildParameters($task->getRequestedFormat(), [], $streams);
        $this->ffmpeg('-i', $localFile, ...$parameters, ...[$tempFilename]);

        $processedFile = $task->getTargetFile();
        $processedFile->setName($task->getTargetFilename());
        $processedFile->updateProperties([
            'checksum' => $task->getConfigurationChecksum(),
            'width' => $task->getWidth(),
            'height' => $task->getHeight(),
        ]);
        $processedFile->updateWithLocalFile($tempFilename);
        $task->setExecuted(true);
    }

    /**
     * @param string $file
     *
     * @return array
     * @throws ConversionException
     */
    protected function ffprobe(string $file): array
    {
        $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);

        $ffprobe = $this->commandUtility->getCommand('ffprobe');
        if (!is_string($ffprobe)) {
            throw new \RuntimeException("ffprobe not found.");
        }

        $parameters = ['-v', 'quiet', '-print_format', 'json', '-show_streams', $file];
        $commandStr = $ffprobe . ' ' . implode(' ', array_map('escapeshellarg', $parameters));
        $logger->info('run ffprobe command', ['command' => $commandStr]);
        $this->commandUtility->exec($commandStr, $output, $returnValue);
        $response = implode("\n", $output);

        if ($returnValue !== 0 && $returnValue !== null) {
            throw new ConversionException("Probing failed: $commandStr", $returnValue);
        }

        if (empty($response)) {
            throw new ConversionException("Probing result empty: $commandStr");
        }

        $json = json_decode($response, true);
        if (json_last_error()) {
            $jsonMsg = json_last_error_msg();
            $msg = strlen($response) > 32 ? substr($response, 0, 16) . '...' . substr($response, -8) : $response;
            throw new ConversionException("Probing result ($msg) could not be parsed: $jsonMsg : $commandStr");
        }

        return $json;
    }

    /**
     * @param string ...$parameters
     *
     * @throws ConversionException
     */
    protected function ffmpeg(string ...$parameters): void
    {
        $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);

        $ffmpeg = $this->commandUtility->getCommand('ffmpeg');
        if (!is_string($ffmpeg)) {
            throw new \RuntimeException("ffmpeg not found.");
        }

        $commandStr = $ffmpeg . ' ' . implode(' ', array_map('escapeshellarg', $parameters));
        $logger->notice('run ffmpeg command', ['command' => $commandStr]);
        $this->commandUtility->exec($commandStr, $output, $returnValue);

        if ($returnValue !== 0 && $returnValue !== null) {
            throw new ConversionException("Conversion failed: $commandStr", $returnValue);
        }
    }

}
