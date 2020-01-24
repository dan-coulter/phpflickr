<?php

namespace Samwilson\PhpFlickr\Oauth;

use OAuth\Common\Consumer\CredentialsInterface;
use OAuth\Common\Http\Client\ClientInterface;
use OAuth\Common\Http\Uri\Uri;
use OAuth\Common\Http\Uri\UriInterface;
use OAuth\Common\Storage\TokenStorageInterface;
use OAuth\OAuth1\Service\AbstractService;
use OAuth\OAuth1\Service\Flickr;
use OAuth\OAuth1\Signature\SignatureInterface;

class PhpFlickrService extends Flickr
{

    /** @var string The base URL, with no trailing slash. */
    private static $baseUrl = 'https://api.flickr.com/services';

    public function __construct(
        CredentialsInterface $credentials,
        ClientInterface $httpClient,
        TokenStorageInterface $storage,
        SignatureInterface $signature,
        UriInterface $baseApiUri = null
    ) {
        if ($baseApiUri === null) {
            $baseApiUri = new Uri(static::$baseUrl.'/rest/');
        }
        parent::__construct($credentials, $httpClient, $storage, $signature, $baseApiUri);
    }

    /**
     * @param string $baseUrl
     */
    public static function setBaseUrl($baseUrl)
    {
        static::$baseUrl = rtrim($baseUrl, '/');
    }

    public function getRequestTokenEndpoint()
    {
        return new Uri(static::$baseUrl.'/oauth/request_token');
    }

    public function getAuthorizationEndpoint()
    {
        return new Uri(static::$baseUrl.'/oauth/authorize');
    }

    public function getAccessTokenEndpoint()
    {
        return new Uri(static::$baseUrl.'/oauth/access_token');
    }

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
