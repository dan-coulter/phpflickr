<?php

namespace Samwilson\PhpFlickr\Tests;

use Samwilson\PhpFlickr\Util;

class UtilTest extends TestCase
{

    public function testPrivacyLevels()
    {
        $this->assertEquals('public', Util::getPrivacyLevelById(Util::PRIVACY_PUBLIC));
        $this->assertEquals('friends', Util::getPrivacyLevelById(Util::PRIVACY_FRIENDS));
        $this->assertEquals(false, Util::getPrivacyLevelById(-12));
    }
}
