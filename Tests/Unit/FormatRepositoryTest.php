<?php

namespace Hn\HauptsacheVideo\Tests\Unit;


use Hn\HauptsacheVideo\FormatRepository;
use Nimut\TestingFramework\TestCase\UnitTestCase;

class FormatRepositoryTest extends UnitTestCase
{
    /** @var FormatRepository */
    private $repository;

    protected function setUp()
    {
        parent::setUp();
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['hauptsache_video'] = [];
        $this->repository = new FormatRepository();
    }

    protected function tearDown()
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['hauptsache_video']);
        parent::tearDown();
    }

    public function testFindDefinition()
    {
        $this->assertNull($this->repository->findFormatDefinition('mp4'));
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['hauptsache_video']['formats'] = [];
        $this->assertNull($this->repository->findFormatDefinition('mp4'));
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['hauptsache_video']['formats']['mp4:default'] = ['foo' => 'bar'];
        $this->assertEquals(['foo' => 'bar'], $this->repository->findFormatDefinition('mp4'));
        $this->assertEquals(['foo' => 'bar'], $this->repository->findFormatDefinition('mp4:default'));
        $this->assertNull(null, $this->repository->findFormatDefinition('mp4:high'));
    }

    /**
     * @expectedException \Hn\HauptsacheVideo\Exception\FormatException
     */
    public function testBuildUnknown()
    {
        $this->repository->buildParameters('mp4');
    }

    public function testBuildEmpty()
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['hauptsache_video']['formats']['mp4:default'] = [];
        $this->assertEquals(['-vn', '-an', '-sn'], $this->repository->buildParameters('mp4'));
    }

}
