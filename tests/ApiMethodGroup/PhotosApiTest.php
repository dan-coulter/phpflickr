<?php

namespace Samwilson\PhpFlickr\Tests\ApiMethodGroup;

use Samwilson\PhpFlickr\Tests\TestCase;

class PhotosApiTest extends TestCase
{

    /**
     * @group auth
     */
    public function testAddTags()
    {
        $flickr = $this->getFlickr(true);
        $testFilename = dirname(__DIR__).'/../examples/Agateware_Example.JPG';
        $photo = $flickr->uploader()->upload($testFilename);

        // Add a string of tags.
        $tagString = 'Tag Iñtërnâtiônàlizætiøn "Third Tag"';
        $tagsAdded1 = $flickr->photos()->addTags($photo['photoid'], $tagString);
        static::assertTrue($tagsAdded1);
        $photoInfo = $flickr->photos()->getInfo($photo['photoid']);
        static::assertCount(3, $photoInfo['tags']['tag']);
        $tags1 = [];
        foreach ($photoInfo['tags']['tag'] as $tagInfo) {
            $tags1[] = $tagInfo['raw'];
        }
        static::assertEquals(['Tag', 'Iñtërnâtiônàlizætiøn', 'Third Tag'], $tags1);

        // Add an array of tags.
        $tagsAdded2 = $flickr->photos()->addTags($photo['photoid'], ['Four', '"With quotes"']);
        static::assertTrue($tagsAdded2);
        $photoInfo2 = $flickr->photos()->getInfo($photo['photoid']);
        static::assertCount(5, $photoInfo2['tags']['tag']);
        $tags2 = [];
        foreach ($photoInfo2['tags']['tag'] as $tagInfo) {
            $tags2[] = $tagInfo['raw'];
        }
        static::assertEquals(
            ['Tag', 'Iñtërnâtiônàlizætiøn', 'Third Tag', 'Four', 'With quotes'],
            $tags2
        );
    }
}
