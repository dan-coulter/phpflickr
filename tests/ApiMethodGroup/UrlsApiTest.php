<?php

namespace Samwilson\PhpFlickr\Tests\ApiMethodGroup;

use Samwilson\PhpFlickr\PhotosApi;
use Samwilson\PhpFlickr\Tests\TestCase;

class UrlsApiTest extends TestCase
{

    /**
     * @dataProvider provideGetImageUrl
     */
    public function testGetImageUrl($photoInfo, $size, $url)
    {
        $flickr = $this->getFlickr();
        static::assertEquals($url, $flickr->urls()->getImageUrl($photoInfo, $size));
    }

    public function provideGetImageUrl()
    {
        // URL forms:
        // https://farm{farm-id}.staticflickr.com/{server-id}/{id}_{secret}.jpg
        // https://farm{farm-id}.staticflickr.com/{server-id}/{id}_{secret}_[mstzb].jpg
        // https://farm{farm-id}.staticflickr.com/{server-id}/{id}_{o-secret}_o.(jpg|gif|png)
        return [
            'medium is default' => [
                ['farm' => 'F', 'server' => 'S', 'id' => 'I', 'secret' => 'Q'],
                '',
                'https://farmF.staticflickr.com/S/I_Q.jpg',
            ],
            'old size name for medium' => [
                ['farm' => 'F', 'server' => 'S', 'id' => 'I', 'secret' => 'Q'],
                'medium',
                'https://farmF.staticflickr.com/S/I_Q.jpg',
            ],
            'old size name for square' => [
                ['farm' => 'F', 'server' => 'S', 'id' => 'I', 'secret' => 'Q'],
                'square',
                'https://farmF.staticflickr.com/S/I_Q_s.jpg',
            ],
            'original' => [
                [
                    'farm' => 'F', 'server' => 'S', 'id' => 'I', 'secret' => 'Q',
                    'originalsecret' => 'OQ', 'originalformat' => 'OF'
                ],
                PhotosApi::SIZE_ORIGINAL,
                'https://farmF.staticflickr.com/S/I_OQ_o.OF',
            ],
            'large size' => [
                ['farm' => 'F', 'server' => 'S', 'id' => 'I', 'secret' => 'Q'],
                PhotosApi::SIZE_LARGE_1600,
                'https://farmF.staticflickr.com/S/I_Q_h.jpg',
            ],
        ];
    }
}
