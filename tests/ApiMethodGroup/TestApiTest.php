<?php

namespace Samwilson\PhpFlickr\Tests\ApiMethodGroup;

use Samwilson\PhpFlickr\FlickrException;
use Samwilson\PhpFlickr\PhpFlickr;
use Samwilson\PhpFlickr\Tests\TestCase;

class TestApiTest extends TestCase
{

    /**
     * Echoing with an invalid key should return an error.
     */
    public function testEchoWithInvalidKey()
    {
        $flickr = new PhpFlickr('a', 'b');
        static::expectException(FlickrException::class);
        static::expectExceptionMessage('Invalid API Key');
        static::expectExceptionCode(100);
        $flickr->test()->testEcho();
    }

    /**
     * Echo returns whatever you send it.
     */
    public function testEcho()
    {
        $flickr = $this->getFlickr();
        $echo = $flickr->test()->testEcho(['foo' => 'bar']);
        static::assertArraySubset(['foo' => 'bar', 'method' => 'flickr.test.echo'], $echo);
    }
}
