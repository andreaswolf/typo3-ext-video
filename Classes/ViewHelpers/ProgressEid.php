<?php

namespace Hn\Video\ViewHelpers;


use function GuzzleHttp\Psr7\build_query;
use function GuzzleHttp\Psr7\stream_for;
use Hn\Video\Processing\VideoProcessingTask;
use Hn\Video\Processing\VideoProcessor;
use Hn\Video\Processing\VideoTaskRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ProgressEid
{
    const EID = 'tx_video_progress';

    public static function render(ServerRequestInterface $request, ResponseInterface $response)
    {
        $queryParams = $request->getQueryParams();

        $file = $queryParams['file'];
        $configuration = unserialize($queryParams['configuration']);
        $task = GeneralUtility::makeInstance(VideoTaskRepository::class)->findByFile($file, $configuration);

        // get the newest information
        VideoProcessor::getConverter()->update($task);

        $content = json_encode(self::parameters($task), JSON_UNESCAPED_SLASHES);
        return $response->withBody(stream_for($content));
    }

    public static function parameters(VideoProcessingTask $task)
    {
        return [
            'progress' => round($task->getLastProgress(), 5),
            'remaining' => round($task->getEstimatedRemainingTime() * 1000),
            // TODO don't transfer an exact timestamp as the client may have a wrong clock
            'lastUpdate' => $task->getLastUpdate() * 1000,
        ];
    }

    public static function getUrl(int $file, array $configuration)
    {
        return rtrim(GeneralUtility::getIndpEnv('TYPO3_SITE_URL'), '/')
            . '/index.php?' . build_query([
                'eID' => self::EID,
                'file' => $file,
                'configuration' => serialize($configuration),
            ]);
    }
}
