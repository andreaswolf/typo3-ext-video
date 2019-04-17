<?php

namespace Hn\HauptsacheVideo\Converter;


use Hn\HauptsacheVideo\Exception\ConversionException;
use Hn\HauptsacheVideo\FormatRepository;
use Hn\HauptsacheVideo\Processing\VideoProcessingTask;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\CommandUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class LocalFFmpegConverter implements VideoConverterInterface
{
    /**
     * Yes, i know, the CommandUtility is ment to be used statically.
     * But creating an instance allows overwriting it for testing and flexibility reasons.
     *
     * @var \TYPO3\CMS\Core\Utility\CommandUtility
     */
    protected $commandUtility;

    public function __construct()
    {
        $this->commandUtility = GeneralUtility::makeInstance(CommandUtility::class);
    }

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

        $tempFilename = GeneralUtility::tempnam('video');
        try {
            $formatRepository = GeneralUtility::makeInstance(FormatRepository::class);
            $this->ffmpeg($formatRepository->buildParameterString($localFile, $tempFilename, $task->getConfiguration(), $streams));

            $processedFile = $task->getTargetFile();
            $processedFile->setName($task->getTargetFilename());
            $processedFile->updateProperties([
                'checksum' => $task->getConfigurationChecksum(),

                // TODO figure out the real resolution
                'width' => intval($task->getConfiguration()['width']),
                'height' => intval($task->getConfiguration()['height']),
            ]);

            $processedFile->updateWithLocalFile($tempFilename);
            $task->setExecuted(true);
        } finally {
            GeneralUtility::unlink_tempfile($tempFilename);
        }
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
        $returnResponse = $this->commandUtility->exec($commandStr, $output, $returnValue);
        $logger->debug('ffprobe result', ['output' => $output, 'returnValue' => $returnValue]);
        $response = implode("\n", $output);

        if ($returnValue !== 0 && $returnValue !== null) {
            throw new ConversionException("Probing failed: $commandStr", $returnValue);
        }

        // this case is for unit testing as it is hard to pass references in a mock
        if (empty($response)) {
            $response = $returnResponse;
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
     * @param string $parameters
     *
     * @throws ConversionException
     */
    protected function ffmpeg(string $parameters): void
    {
        $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);

        $ffmpeg = $this->commandUtility->getCommand('ffmpeg');
        if (!is_string($ffmpeg)) {
            throw new \RuntimeException("ffmpeg not found.");
        }

        // if possible run ffmpeg with lower priority
        // this is because i assume you are using it on the webserver
        // which should care more about delivering pages than about converting the video
        // if the utility is not found than just ignore this priority shift
        $nice = $this->commandUtility->getCommand('nice');
        if (is_string($nice)) {
            $ffmpeg = "$nice $ffmpeg";
        }

        $commandStr = "$ffmpeg $parameters";
        $logger->notice("run ffmpeg command", ['command' => $commandStr]);
        $this->commandUtility->exec($commandStr, $output, $returnValue);
        $logger->debug('ffprobe result', ['output' => $output, 'returnValue' => $returnValue]);

        // because updating referenced values in unit tests is hard, null is also checked here
        if ($returnValue !== 0 && $returnValue !== null) {
            throw new ConversionException("Conversion failed: $commandStr", $returnValue);
        }
    }

}
