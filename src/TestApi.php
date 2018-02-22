<?php

namespace Samwilson\PhpFlickr;

use OAuth\Common\Http\Exception\TokenResponseException;

class TestApi extends ApiMethodGroup {

    /**
     * A testing method which echos all request parameters back in the response.
     * (Note that this method name does not follow normal PhpFlickr nomenclature,
     * due to 'echo' being a reserved word in PHP.)
     * @link https://www.flickr.com/services/api/flickr.test.echo.html
     * @param array $args
     * @return string[]|bool
     */
    public function testEcho($args = [])
    {
        return $this->flickr->request('flickr.test.echo', $args, true);
    }

    /**
     * A testing method which checks if the caller is logged in then returns their details.
     * @link https://www.flickr.com/services/api/flickr.test.login.html
     * @return string[]|bool An array with 'id', 'username' and 'path_alias' keys,
     * or false if unable to log in.
     */
    public function login()
    {
        try {
            $response = $this->flickr->request('flickr.test.login', [], true);
            return isset($response['user']) ? $response['user'] : false;
        } catch (TokenResponseException $exception) {
            return false;
        }
    }
}