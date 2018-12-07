<?php

namespace Samwilson\PhpFlickr\Oauth;

use OAuth\Common\Http\Uri\Uri;
use OAuth\OAuth1\Service\AbstractService;
use OAuth\OAuth1\Service\Flickr;

class PhpFlickrService extends Flickr
{

    /**
     * @return string
     */
    public function service()
    {
        // This is required because this service class isn't named 'Flickr' like its parent.
        return 'Flickr';
    }

    /**
     * A slightly modified method for getting the authorization parameters for uploading.
     * @see AbstractService::buildAuthorizationHeaderForAPIRequest()
     * @link https://github.com/samwilson/phpflickr/issues/6
     * @param string[] $args
     * @param string $uri
     * @return string[]
     */
    public function getAuthorizationForPostingToAlternateUrl($args, $uri)
    {
        $token = $this->storage->retrieveAccessToken($this->service());
        $this->signature->setTokenSecret($token->getAccessTokenSecret());
        $authParameters = $this->getBasicAuthorizationHeaderInfo();
        if (isset($authParameters['oauth_callback'])) {
            unset($authParameters['oauth_callback']);
        }
        $authParameters = array_merge($authParameters, ['oauth_token' => $token->getAccessToken()]);
        $signatureParams = array_merge($authParameters, $args);
        $authParameters['oauth_signature'] = $this->signature->getSignature(
            new Uri($uri),
            $signatureParams
        );
        return array_merge($authParameters, $args);
    }
}
