<?php

namespace Hn\Video\Tests\Unit\Processing;

use Hn\Video\Processing\VideoProcessingEid;
use Hn\Video\Tests\Unit\UnitTestCase;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class VideoProcessingEidTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (function_exists('GeneralUtility::setIndpEnv')) {
            GeneralUtility::setIndpEnv('TYPO3_SITE_URL', '/');
        } else {
            $indpEnvCacheProperty = new \ReflectionProperty(GeneralUtility::class, 'indpEnvCache');
            $indpEnvCacheProperty->setAccessible(true);
            $indpEnvCache = $indpEnvCacheProperty->getValue(null);
            $indpEnvCache['TYPO3_SITE_URL'] = '/';
            $indpEnvCacheProperty->setValue(null, $indpEnvCache);
        }
    }

    protected function tearDown(): void
    {
        GeneralUtility::flushInternalRuntimeCaches();
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }

    public function testKeys(): void
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
