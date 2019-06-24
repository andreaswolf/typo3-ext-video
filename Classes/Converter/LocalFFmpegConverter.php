<?php

namespace Hn\Video\Converter;


use Hn\Video\Exception\ConversionException;
use Hn\Video\FormatRepository;
use Hn\Video\Processing\VideoProcessingTask;
use Hn\Video\Processing\VideoTaskRepository;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\CommandUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class LocalFFmpegConverter extends AbstractVideoConverter
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
     *
     * @throws ConversionException
     */
    public function process(VideoProcessingTask $task): void
    {
        $localFile = $task->getSourceFile()->getForLocalProcessing(false);
        $info = $this->ffprobe($localFile);
        $streams = $info['streams'] ?? [];
        $duration = $info['format']['duration'] ?? 3600.0;

        $tempFilename = GeneralUtility::tempnam('video');
        try {
            $videoTaskRepository = GeneralUtility::makeInstance(VideoTaskRepository::class);
            $formatRepository = GeneralUtility::makeInstance(FormatRepository::class);

            $ffmpegCommand = $formatRepository->buildParameterString($localFile, $tempFilename, $task->getConfiguration(), $streams);
            $progress = $this->ffmpeg($ffmpegCommand);
            foreach ($progress as $time) {
                $task->addProgressStep($time / $duration);
                $videoTaskRepository->store($task);
            }
            // make the progress bar end
            $task->addProgressStep(1.0);
            $videoTaskRepository->store($task);

            $this->finishTask($task, $tempFilename, $streams);
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

        $parameters = ['-v', 'quiet', '-print_format', 'json', '-show_streams', '-show_format', $file];
        $commandStr = $ffprobe . ' ' . implode(' ', array_map('escapeshellarg', $parameters));
        $logger->info('run ffprobe command', ['command' => $commandStr]);
        $returnResponse = $this->commandUtility->exec($commandStr, $output, $returnValue);
        $logger->debug('ffprobe result', ['output' => preg_replace('#\s{2,}#', ' ', $output)]);
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
     * @return \Iterator
     * @throws ConversionException
     */
    protected function ffmpeg(string $parameters): \Iterator
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

        $commandStr = "$ffmpeg -loglevel warning -stats $parameters";
        $logger->notice("run ffmpeg command", ['command' => $commandStr]);
        $process = $this->execAsync($commandStr);
        $output = [];
        foreach ($process as $line) {
            if (preg_match('#time=(\d+):(\d{2}):(\d{2}).(\d{2})#', $line, $matches)) {
                yield $matches[1] * 3600 + $matches[2] * 60 + $matches[3] + $matches[4] / 100;
            } else {
                $output[] = $line;
            }
        }
        $logger->debug('ffprobe result', ['output' => $output]);

        // because updating referenced values in unit tests is hard, null is also checked here
        $returnValue = $process->getReturn();
        if ($returnValue !== 0) {
            throw new ConversionException("Bad return value ($returnValue): $commandStr");
        }
    }

    protected function execAsync($command): \Generator
    {
        $process = proc_open("$command 2>&1", [1 => ['pipe', 'w']], $pipes);
        stream_set_blocking($pipes[1], false);

        try {
            do {
                sleep(1);
                while ($string = fgets($pipes[1])) {
                    yield $string;
                }
                $status = proc_get_status($process);
            } while ($status['running']);
        } finally {
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            proc_close($process);
            return $status['exitcode'] ?? -1;
        }
    }
}
