<?php

namespace Hn\Video\Processing;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Error\Http\BadRequestException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use function GuzzleHttp\Psr7\build_query;

/**
 * This eid processor is meant for remote processing solutions.
 * It is limited by normal php timeouts.
 */
class VideoProcessingEid
{
    public const EID = 'tx_video_process';

    public static function getKeys(): array
    {
        $keys = [
            date('Yz', strtotime('now')),
            date('Yz', strtotime('-1 day')),
        ];

        foreach ($keys as &$key) {
            $checksum = sha1($key . $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'], true);
            $key = strtr(base64_encode($checksum), '/+=', '._-');
        }

        return $keys;
    }

    public static function process(ServerRequestInterface $request, ResponseInterface $response): void
    {
        if (!in_array($request->getQueryParams()['key'], self::getKeys())) {
            throw new BadRequestException('The key is not valid');
        }

        $repository = GeneralUtility::makeInstance(VideoTaskRepository::class);
        $videoProcessor = GeneralUtility::makeInstance(VideoTaskProcessor::class);
        $timeout = ini_get('max_execution_time') - 10;

        $storedTasks = $repository->findByStatus(VideoProcessingTask::STATUS_NEW);
        foreach ($storedTasks as $index => $storedTask) {
            $videoProcessor->doProcessTask($storedTask);

            // try to find out if running another task might fit into the timeout
            $timePassed = time() - $_SERVER['REQUEST_TIME'];
            $timePerTask = $timePassed / ($index + 1);
            if (($timePassed + $timePerTask) > $timeout) {
                break;
            }
        }
    }

    public static function getUrl()
    {
        return rtrim(GeneralUtility::getIndpEnv('TYPO3_SITE_URL'), '/')
            . '/index.php?' . build_query([
                'eID' => self::EID,
                'key' => self::getKeys()[0],
            ]);
    }
}
