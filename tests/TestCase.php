<?php

namespace Samwilson\PhpFlickr\Tests;

use OAuth\OAuth1\Token\StdOAuth1Token;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;
use Samwilson\PhpFlickr\PhpFlickr;

abstract class TestCase extends PhpUnitTestCase
{

    /**
     * Get an instance of PhpFlickr, configured by the config.php file in the tests directory.
     * @param bool $authenticate Whether to authenticate the user with the access token, if it's
     * available in tests/config.php.
     * @return PhpFlickr
     */
    public function getFlickr($authenticate = false)
    {
        require __DIR__.'/config.php';
        $flickr = new PhpFlickr($apiKey, $apiSecret);

        // Authenticate?
        if ($authenticate && isset($accessToken) && isset($accessTokenSecret)) {
            $token = new StdOAuth1Token();
            $token->setAccessToken($accessToken);
            $token->setAccessTokenSecret($accessTokenSecret);
            $flickr->getOauthTokenStorage()->storeAccessToken('Flickr', $token);
        }

        return $flickr;
    }
}
