<?php

namespace Hn\HauptsacheVideo\Tests\Unit\Processing;


use function GuzzleHttp\Psr7\parse_query;
use Hn\HauptsacheVideo\Processing\VideoProcessingEid;
use Hn\HauptsacheVideo\Tests\Unit\UnitTestCase;

class VideoProcessingEidTest extends UnitTestCase
{
    protected function setUp()
    {
        parent::setUp();
    }

    public function testKeys()
    {
        $url = VideoProcessingEid::getUrl();
        $query = parse_url($url, PHP_URL_QUERY);
        parse_str($query, $result);
        $keys = VideoProcessingEid::getKeys();
        $this->assertEquals(28, strlen($keys[0]));
        $this->assertEquals(28, strlen($keys[1]));
        $this->assertEquals(['eID' => 'hauptsache_video', 'key' => $keys[0]], $result);
    }
}
