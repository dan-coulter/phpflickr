<?php

namespace Samwilson\PhpFlickr;

use OAuth\OAuth1\Signature\SignatureInterface;
use OAuth\OAuth1\Token\StdOAuth1Token;
use OAuth\Common\Http\Client\ClientInterface;
use OAuth\Common\Http\Exception\TokenResponseException;
use OAuth\Common\Http\Uri\Uri;
use OAuth\Common\Http\Uri\UriInterface;
use OAuth\Common\Consumer\CredentialsInterface;
use OAuth\Common\Storage\TokenStorageInterface;
use OAuth\OAuth1\Service\AbstractService;

class FlickrOauthService extends AbstractService
{

    /**
     * {@inheritDoc}
     */
    public function __construct(
        CredentialsInterface $credentials,
        ClientInterface $httpClient,
        TokenStorageInterface $storage,
        SignatureInterface $signature,
        UriInterface $baseApiUri = null
    ) {
        parent::__construct($credentials, $httpClient, $storage, $signature, $baseApiUri);
        if ($baseApiUri === null) {
            $this->baseApiUri = new Uri('https://api.flickr.com/services/rest/?');
        }
    }

    /**
     * @return Uri
     */
    public function getRequestTokenEndpoint()
    {
        return new Uri('https://www.flickr.com/services/oauth/request_token');
    }

    /**
     * @return Uri
     */
    public function getAuthorizationEndpoint()
    {
        return new Uri('https://www.flickr.com/services/oauth/authorize');
    }

    /**
     * @return Uri
     */
    public function getAccessTokenEndpoint()
    {
        return new Uri('https://www.flickr.com/services/oauth/access_token');
    }

    /**
     * @param string $responseBody
     * @return StdOAuth1Token
     * @throws TokenResponseException
     */
    protected function parseRequestTokenResponse($responseBody)
    {
        parse_str($responseBody, $data);
        if (null === $data || !is_array($data)) {
            throw new TokenResponseException('Unable to parse response.');
        } elseif (!isset($data['oauth_callback_confirmed']) || $data['oauth_callback_confirmed'] != 'true') {
            $err = isset($data['oauth_problem']) ? $data['oauth_problem'] : '';
            throw new TokenResponseException('Error in retrieving token. '.$err);
        }
        return $this->parseAccessTokenResponse($responseBody);
    }

    /**
     * @param string $responseBody
     * @return StdOAuth1Token
     * @throws TokenResponseException
     */
    protected function parseAccessTokenResponse($responseBody)
    {
        #print "parseAccessTokenResponse\n";

        parse_str($responseBody, $data);
        if ($data === null || !is_array($data)) {
            throw new TokenResponseException('Unable to parse response.');
        } elseif (isset($data['error'])) {
            throw new TokenResponseException('Error in retrieving token: "' . $data['error'] . '"');
        }

        $token = new StdOAuth1Token();
        $token->setRequestToken($data['oauth_token']);
        $token->setRequestTokenSecret($data['oauth_token_secret']);
        $token->setAccessToken($data['oauth_token']);
        $token->setAccessTokenSecret($data['oauth_token_secret']);
        $token->setEndOfLife(StdOAuth1Token::EOL_NEVER_EXPIRES);
        unset($data['oauth_token'], $data['oauth_token_secret']);
        $token->setExtraParams($data);

        return $token;
    }
}
