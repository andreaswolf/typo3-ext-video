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
        $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);

        $executable = $this->commandUtility->getCommand('ffmpeg');
        if (!is_string($executable)) {
            throw new \RuntimeException("ffmpeg not found.");
        }

        $command = [$executable];
        array_push($command, '-i', $task->getSourceFile()->getForLocalProcessing(false));

        $parameters = $this->formatRepository->buildParameters($task->getRequestedFormat());
        array_push($command, ...$parameters);

        $tempFilename = tempnam(sys_get_temp_dir(), 'video');
        array_push($command, '-y');
        array_push($command, $tempFilename);
        $commandStr = implode(' ', array_map('escapeshellarg', $command));

        $string = $this->commandUtility->exec($commandStr, $output, $returnValue);

        if ($returnValue === 0 || $returnValue === null) {
            $logger->notice($commandStr . $string . implode("\n", $output), ['returnValue' => $returnValue]);

            $processedFile = $task->getTargetFile();
            $processedFile->setName($task->getTargetFilename());
            $processedFile->updateProperties([
                'checksum' => $task->getConfigurationChecksum(),
                'width' => $task->getWidth(),
                'height' => $task->getHeight(),
            ]);
            $processedFile->updateWithLocalFile($tempFilename);
            $task->setExecuted(true);

        } else {
            throw new ConversionException($commandStr . $string . implode("\n", $output), $returnValue);
        }
    }

}
