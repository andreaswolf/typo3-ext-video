<?php

namespace Hn\HauptsacheVideo\Converter;


use Hn\HauptsacheVideo\Exception\ConversionException;
use Hn\HauptsacheVideo\Presets\Mp4Preset;
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
     * @var \Hn\HauptsacheVideo\Presets\PresetRepository
     * @inject
     */
    protected $presetRepository;

    /**
     * This method will start the conversion process using the provided options.
     *
     * It must not block the process. If the process can't run async, than it must not run here.
     * However this method must check if the conversion is possible and throw a ConversionException if it can't.
     *
     * @param VideoProcessingTask $task
     *
     * @throws ConversionException
     */
    public function start(VideoProcessingTask $task): void
    {
        $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);

        $executable = $this->commandUtility->getCommand('ffmpeg');
        if (!is_string($executable)) {
            throw new \RuntimeException("ffmpeg not found.");
        }

        $command = [$executable];
        array_push($command, '-i', $task->getSourceFile()->getForLocalProcessing(false));

        $preset = $this->presetRepository->getPresetForFormat($task->getRequestedFormat()) ?? new Mp4Preset();
        array_push($command, ...$preset->getParameters(
            $task->getWidth(),
            $task->getHeight(),
            !$task->isMuted(),
            $task->getQuality()
        ));

        $tempFilename = tempnam(sys_get_temp_dir(), 'video');
        array_push($command, '-y');
        array_push($command, $tempFilename);
        $commandStr = implode(' ', array_map('escapeshellcmd', $command));

        $string = $this->commandUtility->exec($commandStr, $output, $returnValue);

        if ($returnValue === 0) {
            $logger->notice($commandStr . $string . implode("\n", $output), ['returnValue' => $returnValue]);
            $task->getTargetFile()->updateWithLocalFile($tempFilename);
        } else {
            throw new ConversionException($commandStr . $string . implode("\n", $output), $returnValue);
        }
    }
}
