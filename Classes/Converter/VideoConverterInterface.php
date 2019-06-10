<?php

namespace Hn\Video\Converter;

use Hn\Video\Exception\ConversionException;
use Hn\Video\Processing\VideoProcessingTask;

interface VideoConverterInterface
{
    /**
     * This method will start the conversion process using the provided options.
     *
     * It must not block the process. If the process can't run async, than it must not run here.
     *
     * @param VideoProcessingTask $task
     * @throws ConversionException if something went wrong while starting the process. The task will be marked as failed.
     */
    public function start(VideoProcessingTask $task): void;

    /**
     * This method is called new tasks to update their status and potentially process them in a blocking fashion.
     *
     * If you process files in a local process do that here.
     * You can repeatably persist the task object with a new status update for feedback.
     *
     * If you use an api or external service to process the file you can ask for a status update here.
     * This method will be called every time the process command is executed until the task is finished or failed.
     *
     * @param VideoProcessingTask $task
     * @throws ConversionException
     */
    public function process(VideoProcessingTask $task): void;
}
