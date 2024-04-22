<?php

namespace Hn\Video\Tests\Unit;

use Hn\Video\Exception\FormatException;
use Hn\Video\FormatRepository;
use Hn\Video\Preset\PresetInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FormatRepositoryTest extends UnitTestCase
{
    /** @var FormatRepository */
    private $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['video'] = [];
        $this->repository = new FormatRepository();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['video']);
        parent::tearDown();
    }

    public function testFindDefinition()
    {
        $this->assertNull($this->repository->findFormatDefinition(['format' => 'mp4']));

        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['video']['formats'] = [];
        $this->assertNull($this->repository->findFormatDefinition(['format' => 'mp4']));

        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['video']['formats']['mp4:default'] = ['foo' => 'bar'];
        $this->assertEquals(['foo' => 'bar'], $this->repository->findFormatDefinition(['format' => 'mp4']));
        $this->assertEquals(['foo' => 'bar'], $this->repository->findFormatDefinition(['format' => 'mp4:default']));

        $this->assertNull($this->repository->findFormatDefinition(['format' => 'mp4:high']));
    }

    public function testBuildUnknown()
    {
        $this->expectException(FormatException::class);
        $this->repository->buildParameters(null, null, ['format' => 'mp4']);
    }

    public function testBuildEmpty()
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['video']['formats']['mp4:default'] = [];
        $this->assertEquals(['-vn', '-an', '-sn', '-dn'], $this->repository->buildParameters(null, null, ['format' => 'mp4']));
    }

    public function testBuildVideo()
    {
        $videoPreset = $this->createMock(PresetInterface::class);
        GeneralUtility::addInstance(get_class($videoPreset), $videoPreset);
        $videoPreset->expects($this->once())->method('getParameters')->with([])->willReturn(['-c:v', 'libx264']);

        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['video']['formats']['mp4:default'] = [
            'video' => [get_class($videoPreset)],
        ];

        $this->assertEquals(['-c:v', 'libx264', '-an', '-sn', '-dn'], $this->repository->buildParameters(null, null, ['format' => 'mp4', 'video' => ['x' => 'y']]));
    }
}
