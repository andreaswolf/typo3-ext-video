<?php

namespace Hn\Video\Tests\Unit\Processing;


use Hn\Video\Processing\VideoProcessingEid;
use Hn\Video\Tests\Unit\UnitTestCase;

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
        $this->assertEquals(['eID' => 'video', 'key' => $keys[0]], $result);
    }
}
