<?php

namespace Samwilson\PhpFlickr\Tests;

use OAuth\OAuth1\Token\StdOAuth1Token;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;
use Samwilson\PhpFlickr\PhpFlickr;

abstract class TestCase extends PhpUnitTestCase
{

    /** @var PhpFlickr */
    private $flickr;

    /**
     * Get an instance of PhpFlickr, configured by the config.php file in the tests directory.
     * @param bool $authenticate Whether to authenticate the user with the access token, if it's
     * available in tests/config.php.
     * @return PhpFlickr
     */
    public function getFlickr($authenticate = false)
    {
        if ($this->flickr instanceof PhpFlickr) {
            return $this->flickr;
        }

        require __DIR__.'/config.php';
        if (empty($apiKey)) {
            // Skip if no key, so PRs from forks can still be run on Travis.
            static::markTestSkipped('No Flickr API key set.');
        }
        $this->flickr = new PhpFlickr($apiKey, $apiSecret);

        // Authenticate?
        if ($authenticate && !empty($accessToken) && !empty($accessTokenSecret)) {
            $token = new StdOAuth1Token();
            $token->setAccessToken($accessToken);
            $token->setAccessTokenSecret($accessTokenSecret);
            $this->flickr->getOauthTokenStorage()->storeAccessToken('Flickr', $token);
        }

        return $this->flickr;
    }
}
