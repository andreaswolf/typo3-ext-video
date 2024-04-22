<?php

namespace Hn\Video\Tests\Unit\Rendering;

use Hn\Video\Tests\Unit\UnitTestCase;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class VideoTagRendererTest extends UnitTestCase
{
    /**
     * @var \Hn\Video\Rendering\VideoTagRenderer
     */
    protected $renderer;

    protected function setUp()
    {
        parent::setUp();
        $this->renderer = new \Hn\Video\Rendering\VideoTagRenderer();
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['video']['default_video_formats'] = ['mp4' => []];
    }

    protected function tearDown()
    {
        unset($GLOBALS['TYPO3_CONF_VARS']);
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }

    public static function attributeSets()
    {
        return [
            [
                [],
                'controls',
            ],
            [
                ['autoplay' => false],
                'controls',
            ],
            [
                ['autoplay' => true],
                'autoplay muted controls playsinline',
            ],
            [
                ['autoplay' => 0],
                'controls',
            ],
            [
                ['autoplay' => 1],
                'autoplay muted controls playsinline',
            ],
            [
                ['autoplay' => 1, 'muted' => false],
                'autoplay controls playsinline',
            ],
            [
                ['autoplay' => 2],
                'autoplay muted loop controls playsinline',
            ],
            [
                ['autoplay' => 2, 'controls' => false],
                'autoplay muted loop playsinline',
            ],
            [
                ['autoplay' => 3],
                'autoplay muted loop playsinline',
            ],
            [
                ['autoplay' => 3, 'muted' => false],
                'autoplay loop playsinline',
            ],
            [
                ['autoplay' => 3, 'loop' => false],
                'autoplay muted playsinline',
            ],
            [
                ['autoplay' => 3, 'playsinline' => false],
                'autoplay muted loop',
            ],
            [
                ['autoplay' => 3, 'controls' => true],
                'autoplay muted loop controls playsinline',
            ],
            [
                ['muted' => true],
                'muted controls',
            ],
            [
                ['loop' => true],
                'loop controls',
            ],
            [
                ['controls' => true],
                'controls',
            ],
            [
                ['controls' => false],
                null,
            ],
        ];
    }

    /**
     * @dataProvider attributeSets
     */
    public function testSimpleRendering($options, $expectedAttributes)
    {
        $processedFile = $this->createMock(ProcessedFile::class);
        $processedFile->expects($this->atLeastOnce())->method('exists')->willReturn(true);
        $processedFile->expects($this->atLeastOnce())->method('getPublicUrl')->willReturn('http://example.com/video.mp4');
        $processedFile->expects($this->atLeastOnce())->method('getMimeType')->willReturn('video/mp4');

        $file = $this->createMock(File::class);
        $file->expects($this->atLeastOnce())->method('process')->with('Video.CropScale', ['format' => 'mp4'])->willReturn($processedFile);

        $result = $this->renderer->render($file, 200, 200, $options);
        $videoTag = '<video width="200" height="200"' . ($expectedAttributes ? " $expectedAttributes" : '') . '>';
        $this->assertEquals($videoTag . '<source src="http://example.com/video.mp4" type="video/mp4" /></video>', $result);
    }
}
