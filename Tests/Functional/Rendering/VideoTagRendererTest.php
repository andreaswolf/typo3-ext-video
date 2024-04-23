<?php

namespace Hn\Video\Tests\Functional\Rendering;

use Hn\Video\Processing\VideoProcessingTask;
use Hn\Video\Processing\VideoProcessor;
use Hn\Video\Processing\VideoTaskRepository;
use Hn\Video\Tests\Functional\FunctionalTestCase;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Fluid\View\StandaloneView;

class VideoTagRendererTest extends FunctionalTestCase
{
    public static function options()
    {
        return [
            ['<video width="1280" height="720" controls>', []],
            ['<video width="1280" height="720" autoplay muted controls playsinline>', ['autoplay' => 1]],
            ['<video width="1280" height="720" autoplay muted loop controls playsinline>', ['autoplay' => 2]],
            ['<video width="1280" height="720" autoplay muted loop playsinline>', ['autoplay' => 3]],
        ];
    }

    /**
     * @dataProvider options
     */
    public function testVideoTag($videoTag, $options): void
    {
        $view = new StandaloneView();
        $view->setTemplatePathAndFilename(__DIR__ . '/../../Fixtures/Media.html');
        $view->assign('file', $this->file);
        $view->assign('options', $options + ['progress' => false]);
        $this->assertEquals(
            $videoTag . '</video>',
            trim($view->render())
        );

        $this->assertEquals(1, $this->getDatabaseConnection()->selectCount('uid', 'tx_video_task'));

        $taskRepository = GeneralUtility::makeInstance(ObjectManager::class)->get(VideoTaskRepository::class);
        $videoProcessor = GeneralUtility::makeInstance(ObjectManager::class)->get(VideoProcessor::class);
        [$task] = $taskRepository->findByStatus(VideoProcessingTask::STATUS_NEW);
        $videoProcessor->doProcessTask($task);
        $this->assertTrue($task->getTargetFile()->exists());

        $src = htmlspecialchars($task->getTargetFile()->getPublicUrl());
        $type = htmlspecialchars($task->getTargetFile()->getMimeType());
        $this->assertEquals(
            $videoTag . "<source src=\"$src\" type=\"$type\" /></video>",
            trim($view->render())
        );
    }
}
