<?php

namespace Hn\Video\Tests\Unit\Processing;


use Hn\Video\Processing\VideoProcessingEid;
use Hn\Video\Tests\Unit\UnitTestCase;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class VideoProcessingEidTest extends UnitTestCase
{
    protected function setUp()
    {
        parent::setUp();
        GeneralUtility::setIndpEnv('TYPO3_SITE_URL', '/');
    }

    protected function tearDown()
    {
        GeneralUtility::flushInternalRuntimeCaches();
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }

    public function testKeys()
    {
        $url = VideoProcessingEid::getUrl();
        $query = parse_url($url, PHP_URL_QUERY);
        parse_str($query, $result);
        $keys = VideoProcessingEid::getKeys();
        $this->assertEquals(28, strlen($keys[0]));
        $this->assertEquals(28, strlen($keys[1]));
        $this->assertEquals(['eID' => 'tx_video_process', 'key' => $keys[0]], $result, $url);
    }
}
