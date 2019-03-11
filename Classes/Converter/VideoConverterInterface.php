<?php

namespace Hn\HauptsacheVideo\Converter;

use Hn\HauptsacheVideo\Exception\ConversionException;
use Hn\HauptsacheVideo\Processing\VideoProcessingTask;

interface VideoConverterInterface
{
    /**
     * This method will start the conversion process using the provided options.
     *
     * It must not block the process. If the process can't run async, than it must not run here.
     *
     * @param VideoProcessingTask $task
     *
     * @throws ConversionException
     */
    public function start(VideoProcessingTask $task): void;
}
