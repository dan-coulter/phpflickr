<?php
/* phpFlickr
 * Written by Dan Coulter (dan@dancoulter.com).
 * Forked by Sam Wilson, 2017.
 * Project Home Page: https://github.com/samwilson/phpflickr
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Samwilson\PhpFlickr;

use DateInterval;
use DateTime;
use Exception;
use OAuth\Common\Consumer\Credentials;
use OAuth\Common\Http\Client\CurlClient;
use OAuth\Common\Http\Exception\TokenResponseException;
use OAuth\Common\Http\Uri\Uri;
use OAuth\Common\Storage\Memory;
use OAuth\Common\Storage\TokenStorageInterface;
use OAuth\OAuth1\Service\Flickr;
use OAuth\OAuth1\Token\StdOAuth1Token;
use OAuth\OAuth2\Token\TokenInterface;
use OAuth\ServiceFactory;
use Psr\Cache\CacheItemPoolInterface;
use Samwilson\PhpFlickr\Oauth\PhpFlickrService;

class PhpFlickr
{
    protected $api_key;
    protected $secret;
    protected $rest_endpoint = 'https://api.flickr.com/services/rest/';

    /** @var string The base URL of a Flickr API proxy service. */
    protected $proxyBaseUrl;

    protected $req;
    protected $response;

    /** @var string[]|bool */
    protected $parsed_response;

    /** @var CacheItemPoolInterface */
    protected $cachePool;

    /** @var int|DateInterval */
    protected $cacheDefaultExpiry = 600;

    protected $cache = false;
    protected $cache_db = null;
    protected $cache_table = null;
    protected $cache_dir = null;
    protected $cache_expire = null;

    protected $token;

    protected $custom_post = null;
    protected $custom_cache_get = null;
    protected $custom_cache_set = null;

    /** @var string The Flickr-API service to connect to; must be either 'flickr' or '23'. */
    protected $service;

    /** @var PhpFlickrService */
    protected $oauthService;

    /** @var TokenInterface */
    protected $oauthRequestToken;

    /** @var TokenStorageInterface */
    protected $oauthTokenStorage;

    /**
     * When your database cache table hits this many rows, a cleanup
     * will occur to get rid of all of the old rows and cleanup the
     * garbage in the table.  For most personal apps, 1000 rows should
     * be more than enough.  If your site gets hit by a lot of traffic
     * or you have a lot of disk space to spare, bump this number up.
     * You should try to set it high enough that the cleanup only
     * happens every once in a while, so this will depend on the growth
     * of your table.
     * @var integer
     */
    protected $max_cache_rows = 1000;

    /**
     * PhpFlickr constructor.
     * @param $apiKey
     * @param null $secret
     * @param bool $dieOnError Deprecated, does nothing.
     */
    public function __construct($apiKey, $secret = null, $dieOnError = false)
    {
        $this->api_key = $apiKey;
        $this->secret = $secret;
    }

    /**
     * Set the cache pool (and in doing so, enable caching).
     * @param CacheItemPoolInterface $pool
     */
    public function setCache(CacheItemPoolInterface $pool)
    {
        $this->cachePool = $pool;
    }

    /**
     * Set the cache time-to-live. This value is used for all cache items. Defaults to 10 minutes.
     * @param int|DateInterval|null $time
     */
    public function setCacheDefaultExpiry($time)
    {
        $this->cacheDefaultExpiry = $time;
    }

    /**
     * @deprecated Use $this->setCache() instead.
     */
    public function enableCache($type, $connection, $cache_expire = 600, $table = 'flickr_cache')
    {
        // Turns on caching.  $type must be either "db" (for database caching) or "fs" (for filesystem).
        // When using db, $connection must be a PEAR::DB connection string. Example:
        //	  "mysql://user:password@server/database"
        // If the $table, doesn't exist, it will attempt to create it.
        // When using file system, caching, the $connection is the folder that the web server has write
        // access to. Use absolute paths for best results.  Relative paths may have unexpected behavior
        // when you include this.  They'll usually work, you'll just want to test them.
        if ($type == 'db') {
            if (preg_match('|mysql://([^:]*):([^@]*)@([^/]*)/(.*)|', $connection, $matches)) {
                //Array ( [0] => mysql://user:password@server/database [1] => user [2] => password [3] => server [4] => database )
                $db = mysqli_connect($matches[3], $matches[1], $matches[2]);
                mysqli_query($db, "USE $matches[4]");

                /*
                 * If high performance is crucial, you can easily comment
                 * out this query once you've created your database table.
                 */
                mysqli_query($db, "
						CREATE TABLE IF NOT EXISTS `$table` (
							`request` varchar(128) NOT NULL,
							`response` mediumtext NOT NULL,
							`expiration` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
							UNIQUE KEY `request` (`request`)
						)
					");

                $result = mysqli_query($db, "SELECT COUNT(*) 'count' FROM $table");
                if ($result) {
                    $result = mysqli_fetch_assoc($result);
                }

                if ($result && $result['count'] > $this->max_cache_rows) {
                    mysqli_query($db, "DELETE FROM $table WHERE CURRENT_TIMESTAMP > expiration");
                    mysqli_query($db, 'OPTIMIZE TABLE ' . $this->cache_table);
                }
                $this->cache = 'db';
                $this->cache_db = $db;
                $this->cache_table = $table;
            }
        } elseif ($type == 'fs') {
            $this->cache = 'fs';
            $connection = realpath($connection);
            $this->cache_dir = $connection;
            if ($dir = opendir($this->cache_dir)) {
                while ($file = readdir($dir)) {
                    if (substr($file, -6) == '.cache' && ((filemtime($this->cache_dir . '/' . $file) + $cache_expire) < time())) {
                        unlink($this->cache_dir . '/' . $file);
                    }
                }
            }
        } elseif ($type == 'custom') {
            $this->cache = "custom";
            $this->custom_cache_get = $connection[0];
            $this->custom_cache_set = $connection[1];
        }
        $this->cache_expire = $cache_expire;
    }

    /**
     * Get a cached request.
     * @param string[] Array of request parameters ('api_sig' will be discarded).
     * @return string[]
     */
    public function getCached($request)
    {
        //Checks the database or filesystem for a cached result to the request.
        //If there is no cache result, it returns a value of false. If it finds one,
        //it returns the unparsed XML.
        unset($request['api_sig']);
        foreach ($request as $key => $value) {
            if (empty($value)) {
                unset($request[$key]);
            } else {
                $request[$key] = (string) $request[$key];
            }
        }
        $cacheKey = md5(serialize($request));

        if ($this->cachePool instanceof CacheItemPoolInterface) {
            $item = $this->cachePool->getItem($cacheKey);
            if ($item->isHit()) {
                return $item->get();
            } else {
                return false;
            }
        } elseif ($this->cache == 'db') {
            $result = mysqli_query($this->cache_db, "SELECT response FROM " . $this->cache_table . " WHERE request = '" . $cacheKey . "' AND CURRENT_TIMESTAMP < expiration");
            if ($result && mysqli_num_rows($result)) {
                $result = mysqli_fetch_assoc($result);
                return urldecode($result['response']);
            } else {
                return false;
            }
        } elseif ($this->cache == 'fs') {
            $file = $this->cache_dir . '/' . $cacheKey . '.cache';
            if (file_exists($file)) {
                return file_get_contents($file);
            }
        } elseif ($this->cache == 'custom') {
            return call_user_func_array($this->custom_cache_get, array($cacheKey));
        }
        return false;
    }

    /**
     * Cache a request's response.
     * @param string[] $request API request parameters.
     * @param mixed $response The value to cache.
     * @return bool|int|mixed|\mysqli_result
     */
    public function cache($request, $response)
    {
        //Caches the unparsed response of a request.
        unset($request['api_sig']);
        foreach ($request as $key => $value) {
            if (empty($value)) {
                unset($request[$key]);
            } else {
                $request[$key] = (string) $request[$key];
            }
        }
        $cacheKey = md5(serialize($request));
        if ($this->cachePool instanceof CacheItemPoolInterface) {
            $item = $this->cachePool->getItem($cacheKey);
            $item->set($response);
            $item->expiresAfter($this->cacheDefaultExpiry);
            return $this->cachePool->save($item);
        } elseif ($this->cache == 'db') {
            $response = urlencode($response);
            $sql = 'INSERT INTO '.$this->cache_table.' (request, response, expiration) 
						VALUES (\''.$cacheKey.'\', \''.$response.'\', TIMESTAMPADD(SECOND,'.$this->cache_expire.',CURRENT_TIMESTAMP))
						ON DUPLICATE KEY UPDATE response=\''.$response.'\', 
						expiration=TIMESTAMPADD(SECOND,'.$this->cache_expire.',CURRENT_TIMESTAMP) ';

            $result = mysqli_query($this->cache_db, $sql);
            if (!$result) {
                echo mysqli_error($this->cache_db);
            }

            return $result;
        } elseif ($this->cache == "fs") {
            $file = $this->cache_dir . "/" . $cacheKey . ".cache";
            $fstream = fopen($file, "w");
            $result = fwrite($fstream, $response);
            fclose($fstream);
            return $result;
        } elseif ($this->cache == "custom") {
            return call_user_func_array($this->custom_cache_set, array($cacheKey, $response, $this->cache_expire));
        }
        return false;
    }

    /**
     * Set a custom post() callback.
     * @deprecated since 4.1.0
     * @param callback $function
     */
    public function setCustomPost($function)
    {
        $this->custom_post = $function;
    }

    /**
     * Submit a POST request to Flickr. If a custom POST callback is set, that will be used.
     * @deprecated since 4.1.0
     * @param string[] $data The request parameters, with a 'method' element.
     * @param mixed $type If null, the Flickr REST endpoint will be passed to a custom post()
     * method (if one is defined; see PhpFlickr::setCustomPast()). Must be non-null for use without
     * a custom POST callback.
     * @return string The response body.
     * @throws Exception
     */
    public function post($data, $type = null) {
        if (is_null($type)) {
            $url = $this->rest_endpoint;
        }

        if (!is_null($this->custom_post)) {
            return call_user_func($this->custom_post, $url, $data);
        }

        if (!preg_match("|https://(.*?)(/.*)|", $url, $matches)) {
            throw new Exception('There was some problem figuring out your endpoint');
        }

        if (!isset($data['method'])) {
            throw new Exception('The $data array must have a "method" parameter');
        }
        $path = $data['method'];
        unset($data['method']);

        $oauthService = $this->getOauthService();
        $response = $oauthService->request($path, 'POST', $data);
        return $response;
    }

    /**
     * Send a POST request to the Flickr API.
     * @param string $command The API endpoint to call.
     * @param string[] $args The API request arguments.
     * @param bool $nocache Whether to cache the response or not.
     * @return bool|mixed[]
     * @throws FlickrException If the request fails.
     */
    public function request($command, $args = array(), $nocache = false)
    {
        // Make sure the API method begins with 'flickr.'.
        if (substr($command, 0, 7) !== "flickr.") {
            $command = "flickr." . $command;
        }

        // See if there's a cached response.
        $cacheKey = array_merge([$command], $args);
        $this->response = $this->getCached($cacheKey);
        if (!($this->response) || $nocache) {
            $args = array_filter($args);
            $oauthService = $this->getOauthService();
            $this->response = $oauthService->requestJson($command, 'POST', $args);
            if (!$nocache) {
                $this->cache($cacheKey, $this->response);
            }
        }

        $jsonResponse = json_decode($this->response, true);
        if (null === $jsonResponse) {
            throw new FlickrException("Unable to decode Flickr response to $command request: ".$this->response);
        }
        $this->parsed_response = $this->cleanTextNodes($jsonResponse);
        if ($this->parsed_response['stat'] === 'fail') {
             throw new FlickrException($this->parsed_response['message'], $this->parsed_response['code']);
        }
        return $this->parsed_response;
    }

    public function cleanTextNodes($arr)
    {
        if (!is_array($arr)) {
            return $arr;
        } elseif (count($arr) == 0) {
            return $arr;
        } elseif (count($arr) == 1 && array_key_exists('_content', $arr)) {
            return $arr['_content'];
        } else {
            foreach ($arr as $key => $element) {
                $arr[$key] = $this->cleanTextNodes($element);
            }
            return($arr);
        }
    }

    public function setToken($token)
    {
        // Sets an authentication token to use instead of the session variable
        $this->token = $token;
    }

    /**
     * @deprecated Use $this->>setProxyBaseUrl() instead.
     */
    public function setProxy($server, $port)
    {
    }

    /**
     * Set a proxy server through which all requests will be made.
     * @param string $baseUrl The base URL.
     */
    public function setProxyBaseUrl($baseUrl)
    {
        $this->proxyBaseUrl = rtrim($baseUrl, '/');
    }

    /**
     * @deprecated Requests throw exceptions now.
     * @return int|bool
     */
    public function getErrorCode()
    {
        return false;
    }

    /**
     * @deprecated Requests throw exceptions now.
     * @return string|bool
     */
    public function getErrorMsg()
    {
        return false;
    }

    /**
     * @deprecated
     */
    public function buildPhotoURL($photoInfo, $size = "medium")
    {
        return $this->urls()->getImageUrl($photoInfo, $size);
    }

    /**
     * Get an uploader with which to upload photos to (or replace photos on) Flickr.
     * @return Uploader
     */
    public function uploader()
    {
        return new Uploader($this);
    }

    /**
     * @deprecated use $this->uploader()->synchronous() instead.
     */
    public function sync_upload(
        $photo,
        $title = null,
        $description = null,
        $tags = null,
        $is_public = null,
        $is_friend = null,
        $is_family = null
    ) {
        return $this->uploader()->upload(
            $photo,
            $title,
            $description,
            $tags,
            $is_public,
            $is_friend,
            $is_family
        );
    }

    /**
     * @deprecated use $this->uploader()->asynchronous() instead.
     */
    public function async_upload(
        $photo,
        $title = null,
        $description = null,
        $tags = null,
        $is_public = null,
        $is_friend = null,
        $is_family = null
    ) {
        return $this->uploader()->upload(
            $photo,
            $title,
            $description,
            $tags,
            $is_public,
            $is_friend,
            $is_family,
            null,
            null,
            true
        );
    }

    /**
     * @deprecated use $this->uploader()->replace() instead.
     */
    public function replace($photo, $photo_id, $async = null) {
        return $this->uploader()->replace($photo, $photo_id, $async);
    }

    /**
     * @deprecated Use PhpFlickr::getAuthUrl() instead, and do your own redirecting.
     * @param string $perms
     * @param bool $remember_uri
     * @return mixed
     */
    public function auth($perms = "read", $remember_uri = true)
    {
        // Do basic redirection. This method used to also check the session and request token.
        $url = $this->getAuthUrl($perms);
        header("Location: $url");
        exit();
    }

    /**
     * @deprecated since 4.1.0; use PhpFlickr::getAuthUrl() instead.
     * @param string $frob
     * @param string $perms
     * @return string
     */
    public function auth_url($frob, $perms = 'read')
    {
        $sig = md5(sprintf('%sapi_key%sfrob%sperms%s', $this->secret, $this->api_key, $frob, $perms));
        return sprintf('https://flickr.com/services/auth/?api_key=%s&perms=%s&frob=%s&api_sig=%s', $this->api_key, $perms, $frob, $sig);
    }

    /**
     * @param string $callbackUrl The URL to return to when authenticating with Flickr. Only
     * required if you're going to be retrieving an access token.
     * @return PhpFlickrService
     */
    public function getOauthService($callbackUrl = 'oob')
    {
        if ($this->oauthService instanceof Flickr) {
            return $this->oauthService;
        }
        $credentials = new Credentials($this->api_key, $this->secret, $callbackUrl);
        $factory = new ServiceFactory();
        // Replace the Flickr service with our own (of the same name), using the proxy URL if it's set.
        if ($this->proxyBaseUrl) {
            PhpFlickrService::setBaseUrl($this->proxyBaseUrl);
        }
        $factory->registerService('Flickr', PhpFlickrService::class);
        $factory->setHttpClient(new CurlClient());
        $storage = $this->getOauthTokenStorage();
        /** @var PhpFlickrService $flickrService */
        $this->oauthService = $factory->createService('Flickr', $credentials, $storage);
        return $this->oauthService;
    }

    /**
     * Get the initial authorization URL to which to redirect users.
     *
     * This method submits a request to Flickr, so only use it at the request of the user
     * so as to not slow things down or perform unexpected actions.
     *
     * @param string $perm One of 'read', 'write', or 'delete'.
     * @param string $callbackUrl Defaults to 'oob' ('out-of-band') for when no callback is
     * required, for example for console usage.
     * @return Uri
     */
    public function getAuthUrl($perm = 'read', $callbackUrl = 'oob')
    {
        $service = $this->getOauthService($callbackUrl);
        $this->oauthRequestToken = $service->requestRequestToken();
        $url = $service->getAuthorizationUri([
            'oauth_token' => $this->oauthRequestToken->getAccessToken(),
            'perms' => $perm,
        ]);
        return $url;
    }

    /**
     * Get an access token for the current user, that you can store in order to authenticate as
     * for this user in the future.
     *
     * @param string $verifier The verification code.
     * @param string $requestToken The request token. Can be left out if this is being called on
     * the same object that started the authentication (i.e. it already has access to the request
     * token).
     * @return \OAuth\Common\Token\TokenInterface|\OAuth\OAuth1\Token\TokenInterface|string
     */
    public function retrieveAccessToken($verifier, $requestToken = null)
    {
        $service = $this->getOauthService('oob');
        $storage = $this->getOauthTokenStorage();
        /** @var \OAuth\OAuth1\Token\TokenInterface $token */
        $token = $storage->retrieveAccessToken('Flickr');

        // If no request token is provided, try to get it from this object.
        if (is_null($requestToken) && $this->oauthRequestToken instanceof TokenInterface) {
            $requestToken = $this->oauthRequestToken->getAccessToken();
        }

        $secret = $token->getAccessTokenSecret();
        $accessToken = $service->requestAccessToken($requestToken, $verifier, $secret);
        $storage->storeAccessToken('Flickr', $accessToken);
        return $accessToken;
    }

    /**
     * @param TokenStorageInterface $tokenStorage The storage object to use.
     */
    public function setOauthStorage(TokenStorageInterface $tokenStorage)
    {
        $this->oauthTokenStorage = $tokenStorage;
    }

    /**
     * @return TokenStorageInterface
     * @throws FlickrException If the token storage has not been set yet.
     */
    public function getOauthTokenStorage() {
        if (!$this->oauthTokenStorage instanceof TokenStorageInterface) {
            // If no storage has yet been set, create an in-memory one with an empty token.
            // This will be suitable for un-authenticated API calls.
            $this->oauthTokenStorage = new Memory();
            $this->oauthTokenStorage->storeAccessToken('Flickr', new StdOAuth1Token());
        }
        return $this->oauthTokenStorage;
    }

    /**
     * Make a call to the Flickr API. This method allows you to call API methods that have not
     * yet been implemented in PhpFlickr. You should use other more specific methods of this
     * class if possible.
     * @param string $method The API method name.
     * @param string[] $arguments The API method arguments.
     * @return string[]|bool The results of the call, or false if there were none.
     */
    public function call($method, $arguments)
    {
        foreach ($arguments as $key => $value) {
            if (is_null($value)) {
                unset($arguments[$key]);
            }
        }
        $this->request($method, $arguments);
        return $this->parsed_response ? $this->parsed_response : false;
    }

    /*
        These functions are the direct implementations of flickr calls.
        For method documentation, including arguments, visit the address
        included in a comment in the function.
    */

    /* Activity methods */
    public function activity_userComments($per_page = null, $page = null)
    {
        /* https://www.flickr.com/services/api/flickr.activity.userComments.html */
        $this->request('flickr.activity.userComments', array("per_page" => $per_page, "page" => $page));
        return $this->parsed_response ? $this->parsed_response['items']['item'] : false;
    }

    public function activity_userPhotos($timeframe = null, $per_page = null, $page = null)
    {
        /* https://www.flickr.com/services/api/flickr.activity.userPhotos.html */
        $this->request('flickr.activity.userPhotos', array("timeframe" => $timeframe, "per_page" => $per_page, "page" => $page));
        return $this->parsed_response ? $this->parsed_response['items']['item'] : false;
    }

    /**
     * @link https://www.flickr.com/services/api/flickr.auth.checkToken.html
     * @deprecated Use OAuth instead.
     * @return bool|string
     */
    public function auth_checkToken()
    {
        $this->request('flickr.auth.checkToken');
        return $this->parsed_response ? $this->parsed_response['auth'] : false;
    }

    /**
     * @link https://www.flickr.com/services/api/flickr.auth.getFrob.html
     * @deprecated Use OAuth instead.
     * @return bool|string
     */
    public function auth_getFrob()
    {
        $this->request('flickr.auth.getFrob');
        return $this->parsed_response ? $this->parsed_response['frob'] : false;
    }

    /**
     * @link https://www.flickr.com/services/api/flickr.auth.getFullToken.html
     * @deprecated Use OAuth instead.
     * @param $mini_token
     * @return bool|string
     */
    public function auth_getFullToken($mini_token)
    {
        $this->request('flickr.auth.getFullToken', array('mini_token'=>$mini_token));
        return $this->parsed_response ? $this->parsed_response['auth'] : false;
    }

    /**
     * @link https://www.flickr.com/services/api/flickr.auth.getToken.html
     * @deprecated Use OAuth instead.
     * @param $frob
     * @return bool|string
     */
    public function auth_getToken($frob)
    {
        $this->request('flickr.auth.getToken', array('frob'=>$frob));
        $_SESSION['phpFlickr_auth_token'] = $this->parsed_response['auth']['token'];
        return $this->parsed_response ? $this->parsed_response['auth'] : false;
    }

    /* Blogs methods */
    public function blogs_getList($service = null)
    {
        /* https://www.flickr.com/services/api/flickr.blogs.getList.html */
        $rsp = $this->call('flickr.blogs.getList', array('service' => $service));
        return $rsp['blogs']['blog'];
    }

    public function blogs_getServices()
    {
        /* https://www.flickr.com/services/api/flickr.blogs.getServices.html */
        return $this->call('flickr.blogs.getServices', array());
    }

    public function blogs_postPhoto($blog_id = null, $photo_id, $title, $description, $blog_password = null, $service = null)
    {
        /* https://www.flickr.com/services/api/flickr.blogs.postPhoto.html */
        return $this->call('flickr.blogs.postPhoto', array('blog_id' => $blog_id, 'photo_id' => $photo_id, 'title' => $title, 'description' => $description, 'blog_password' => $blog_password, 'service' => $service));
    }

    /* Collections Methods */
    public function collections_getInfo($collection_id)
    {
        /* https://www.flickr.com/services/api/flickr.collections.getInfo.html */
        return $this->call('flickr.collections.getInfo', array('collection_id' => $collection_id));
    }

    public function collections_getTree($collection_id = null, $user_id = null)
    {
        /* https://www.flickr.com/services/api/flickr.collections.getTree.html */
        return $this->call('flickr.collections.getTree', array('collection_id' => $collection_id, 'user_id' => $user_id));
    }

    /* Commons Methods */
    public function commons_getInstitutions()
    {
        /* https://www.flickr.com/services/api/flickr.commons.getInstitutions.html */
        return $this->call('flickr.commons.getInstitutions', array());
    }

    /* Contacts Methods */
    public function contacts_getList($filter = null, $page = null, $per_page = null)
    {
        /* https://www.flickr.com/services/api/flickr.contacts.getList.html */
        $this->request('flickr.contacts.getList', array('filter'=>$filter, 'page'=>$page, 'per_page'=>$per_page));
        return $this->parsed_response ? $this->parsed_response['contacts'] : false;
    }

    public function contacts_getPublicList($user_id, $page = null, $per_page = null)
    {
        /* https://www.flickr.com/services/api/flickr.contacts.getPublicList.html */
        $this->request('flickr.contacts.getPublicList', array('user_id'=>$user_id, 'page'=>$page, 'per_page'=>$per_page));
        return $this->parsed_response ? $this->parsed_response['contacts'] : false;
    }

    public function contacts_getListRecentlyUploaded($date_lastupload = null, $filter = null)
    {
        /* https://www.flickr.com/services/api/flickr.contacts.getListRecentlyUploaded.html */
        return $this->call('flickr.contacts.getListRecentlyUploaded', array('date_lastupload' => $date_lastupload, 'filter' => $filter));
    }

    /* Favorites Methods */
    public function favorites_add($photo_id)
    {
        /* https://www.flickr.com/services/api/flickr.favorites.add.html */
        $this->request('flickr.favorites.add', array('photo_id'=>$photo_id), true);
        return $this->parsed_response ? true : false;
    }

    public function favorites_getList($user_id = null, $jump_to = null, $min_fave_date = null, $max_fave_date = null, $extras = null, $per_page = null, $page = null)
    {
        /* https://www.flickr.com/services/api/flickr.favorites.getList.html */
        return $this->call('flickr.favorites.getList', array('user_id' => $user_id, 'jump_to' => $jump_to, 'min_fave_date' => $min_fave_date, 'max_fave_date' => $max_fave_date, 'extras' => $extras, 'per_page' => $per_page, 'page' => $page));
    }

    public function favorites_getPublicList($user_id, $jump_to = null, $min_fave_date = null, $max_fave_date = null, $extras = null, $per_page = null, $page = null)
    {
        /* https://www.flickr.com/services/api/flickr.favorites.getPublicList.html */
        return $this->call('flickr.favorites.getPublicList', array('user_id' => $user_id, 'jump_to' => $jump_to, 'min_fave_date' => $min_fave_date, 'max_fave_date' => $max_fave_date, 'extras' => $extras, 'per_page' => $per_page, 'page' => $page));
    }

    public function favorites_remove($photo_id, $user_id = null)
    {
        /* https://www.flickr.com/services/api/flickr.favorites.remove.html */
        $this->request("flickr.favorites.remove", array('photo_id' => $photo_id, 'user_id' => $user_id), true);
        return $this->parsed_response ? true : false;
    }

    /* Galleries Methods */
    public function galleries_addPhoto($gallery_id, $photo_id, $comment = null)
    {
        /* https://www.flickr.com/services/api/flickr.galleries.addPhoto.html */
        return $this->call('flickr.galleries.addPhoto', array('gallery_id' => $gallery_id, 'photo_id' => $photo_id, 'comment' => $comment));
    }

    public function galleries_create($title, $description, $primary_photo_id = null)
    {
        /* https://www.flickr.com/services/api/flickr.galleries.create.html */
        return $this->call('flickr.galleries.create', array('title' => $title, 'description' => $description, 'primary_photo_id' => $primary_photo_id));
    }

    public function galleries_editMeta($gallery_id, $title, $description = null)
    {
        /* https://www.flickr.com/services/api/flickr.galleries.editMeta.html */
        return $this->call('flickr.galleries.editMeta', array('gallery_id' => $gallery_id, 'title' => $title, 'description' => $description));
    }

    public function galleries_editPhoto($gallery_id, $photo_id, $comment)
    {
        /* https://www.flickr.com/services/api/flickr.galleries.editPhoto.html */
        return $this->call('flickr.galleries.editPhoto', array('gallery_id' => $gallery_id, 'photo_id' => $photo_id, 'comment' => $comment));
    }

    public function galleries_editPhotos($gallery_id, $primary_photo_id, $photo_ids)
    {
        /* https://www.flickr.com/services/api/flickr.galleries.editPhotos.html */
        return $this->call('flickr.galleries.editPhotos', array('gallery_id' => $gallery_id, 'primary_photo_id' => $primary_photo_id, 'photo_ids' => $photo_ids));
    }

    public function galleries_getInfo($gallery_id)
    {
        /* https://www.flickr.com/services/api/flickr.galleries.getInfo.html */
        return $this->call('flickr.galleries.getInfo', array('gallery_id' => $gallery_id));
    }

    public function galleries_getList($user_id, $per_page = null, $page = null)
    {
        /* https://www.flickr.com/services/api/flickr.galleries.getList.html */
        return $this->call('flickr.galleries.getList', array('user_id' => $user_id, 'per_page' => $per_page, 'page' => $page));
    }

    public function galleries_getListForPhoto($photo_id, $per_page = null, $page = null)
    {
        /* https://www.flickr.com/services/api/flickr.galleries.getListForPhoto.html */
        return $this->call('flickr.galleries.getListForPhoto', array('photo_id' => $photo_id, 'per_page' => $per_page, 'page' => $page));
    }

    public function galleries_getPhotos($gallery_id, $extras = null, $per_page = null, $page = null)
    {
        /* https://www.flickr.com/services/api/flickr.galleries.getPhotos.html */
        return $this->call('flickr.galleries.getPhotos', array('gallery_id' => $gallery_id, 'extras' => $extras, 'per_page' => $per_page, 'page' => $page));
    }

    /* Groups Methods */
    public function groups_browse($cat_id = null)
    {
        /* https://www.flickr.com/services/api/flickr.groups.browse.html */
        $this->request("flickr.groups.browse", array("cat_id"=>$cat_id));
        return $this->parsed_response ? $this->parsed_response['category'] : false;
    }

    public function groups_getInfo($group_id, $lang = null)
    {
        /* https://www.flickr.com/services/api/flickr.groups.getInfo.html */
        return $this->call('flickr.groups.getInfo', array('group_id' => $group_id, 'lang' => $lang));
    }

    public function groups_search($text, $per_page = null, $page = null)
    {
        /* https://www.flickr.com/services/api/flickr.groups.search.html */
        $this->request("flickr.groups.search", array("text"=>$text,"per_page"=>$per_page,"page"=>$page));
        return $this->parsed_response ? $this->parsed_response['groups'] : false;
    }

    /* Groups Members Methods */
    public function groups_members_getList($group_id, $membertypes = null, $per_page = null, $page = null)
    {
        /* https://www.flickr.com/services/api/flickr.groups.members.getList.html */
        return $this->call('flickr.groups.members.getList', array('group_id' => $group_id, 'membertypes' => $membertypes, 'per_page' => $per_page, 'page' => $page));
    }

    /* Groups Pools Methods */
    public function groups_pools_add($photo_id, $group_id)
    {
        /* https://www.flickr.com/services/api/flickr.groups.pools.add.html */
        $this->request("flickr.groups.pools.add", array("photo_id"=>$photo_id, "group_id"=>$group_id), true);
        return $this->parsed_response ? true : false;
    }

    public function groups_pools_getContext($photo_id, $group_id, $num_prev = null, $num_next = null)
    {
        /* https://www.flickr.com/services/api/flickr.groups.pools.getContext.html */
        return $this->call('flickr.groups.pools.getContext', array('photo_id' => $photo_id, 'group_id' => $group_id, 'num_prev' => $num_prev, 'num_next' => $num_next));
    }

    public function groups_pools_getGroups($page = null, $per_page = null)
    {
        /* https://www.flickr.com/services/api/flickr.groups.pools.getGroups.html */
        $this->request("flickr.groups.pools.getGroups", array('page'=>$page, 'per_page'=>$per_page));
        return $this->parsed_response ? $this->parsed_response['groups'] : false;
    }

    public function groups_pools_getPhotos($group_id, $tags = null, $user_id = null, $jump_to = null, $extras = null, $per_page = null, $page = null)
    {
        /* https://www.flickr.com/services/api/flickr.groups.pools.getPhotos.html */
        if (is_array($extras)) {
            $extras = implode(",", $extras);
        }
        return $this->call('flickr.groups.pools.getPhotos', array('group_id' => $group_id, 'tags' => $tags, 'user_id' => $user_id, 'jump_to' => $jump_to, 'extras' => $extras, 'per_page' => $per_page, 'page' => $page));
    }

    public function groups_pools_remove($photo_id, $group_id)
    {
        /* https://www.flickr.com/services/api/flickr.groups.pools.remove.html */
        $this->request("flickr.groups.pools.remove", array("photo_id"=>$photo_id, "group_id"=>$group_id), true);
        return $this->parsed_response ? true : false;
    }

    /* Interestingness methods */
    public function interestingness_getList($date = null, $use_panda = null, $extras = null, $per_page = null, $page = null)
    {
        /* https://www.flickr.com/services/api/flickr.interestingness.getList.html */
        if (is_array($extras)) {
            $extras = implode(",", $extras);
        }

        return $this->call('flickr.interestingness.getList', array('date' => $date, 'use_panda' => $use_panda, 'extras' => $extras, 'per_page' => $per_page, 'page' => $page));
    }

    /* Machine Tag methods */
    public function machinetags_getNamespaces($predicate = null, $per_page = null, $page = null)
    {
        /* https://www.flickr.com/services/api/flickr.machinetags.getNamespaces.html */
        return $this->call('flickr.machinetags.getNamespaces', array('predicate' => $predicate, 'per_page' => $per_page, 'page' => $page));
    }

    public function machinetags_getPairs($namespace = null, $predicate = null, $per_page = null, $page = null)
    {
        /* https://www.flickr.com/services/api/flickr.machinetags.getPairs.html */
        return $this->call('flickr.machinetags.getPairs', array('namespace' => $namespace, 'predicate' => $predicate, 'per_page' => $per_page, 'page' => $page));
    }

    public function machinetags_getPredicates($namespace = null, $per_page = null, $page = null)
    {
        /* https://www.flickr.com/services/api/flickr.machinetags.getPredicates.html */
        return $this->call('flickr.machinetags.getPredicates', array('namespace' => $namespace, 'per_page' => $per_page, 'page' => $page));
    }

    public function machinetags_getRecentValues($namespace = null, $predicate = null, $added_since = null)
    {
        /* https://www.flickr.com/services/api/flickr.machinetags.getRecentValues.html */
        return $this->call('flickr.machinetags.getRecentValues', array('namespace' => $namespace, 'predicate' => $predicate, 'added_since' => $added_since));
    }

    public function machinetags_getValues($namespace, $predicate, $per_page = null, $page = null, $usage = null)
    {
        /* https://www.flickr.com/services/api/flickr.machinetags.getValues.html */
        return $this->call('flickr.machinetags.getValues', array('namespace' => $namespace, 'predicate' => $predicate, 'per_page' => $per_page, 'page' => $page, 'usage' => $usage));
    }

    /* Panda methods */
    public function panda_getList()
    {
        /* https://www.flickr.com/services/api/flickr.panda.getList.html */
        return $this->call('flickr.panda.getList', array());
    }

    public function panda_getPhotos($panda_name, $extras = null, $per_page = null, $page = null)
    {
        /* https://www.flickr.com/services/api/flickr.panda.getPhotos.html */
        return $this->call('flickr.panda.getPhotos', array('panda_name' => $panda_name, 'extras' => $extras, 'per_page' => $per_page, 'page' => $page));
    }

    /**
     * Get the API methods for people.
     * @return PeopleApi
     */
    public function people()
    {
        return new PeopleApi( $this );
    }

    /**
     * @deprecated
     * @param $find_email
     * @return bool|string
     */
    public function people_findByEmail($find_email)
    {
        return $this->people()->findByEmail($find_email);
    }

    /**
     * @deprecated
     * @param $username
     * @return bool
     */
    public function people_findByUsername($username)
    {
        return $this->people()->findByUsername($username);
    }

    public function people_getInfo($user_id)
    {
        /* https://www.flickr.com/services/api/flickr.people.getInfo.html */
        $this->request("flickr.people.getInfo", array("user_id"=>$user_id));
        return $this->parsed_response ? $this->parsed_response['person'] : false;
    }

    /**
     * @deprecated
     * @param $user_id
     * @param array $args
     * @return bool|string[]
     */
    public function people_getPhotos($user_id, $args = array())
    {
        /* This function strays from the method of arguments that I've
         * used in the other functions for the fact that there are just
         * so many arguments to this API method. What you'll need to do
         * is pass an associative array to the function containing the
         * arguments you want to pass to the API.  For example:
         *   $photos = $f->photos_search(array("tags"=>"brown,cow", "tag_mode"=>"any"));
         * This will return photos tagged with either "brown" or "cow"
         * or both. See the API documentation (link below) for a full
         * list of arguments.
         */

         /* https://www.flickr.com/services/api/flickr.people.getPhotos.html */
        return $this->call('flickr.people.getPhotos', array_merge(array('user_id' => $user_id), $args));
    }

    public function people_getPhotosOf($user_id, $extras = null, $per_page = null, $page = null)
    {
        /* https://www.flickr.com/services/api/flickr.people.getPhotosOf.html */
        return $this->call('flickr.people.getPhotosOf', array('user_id' => $user_id, 'extras' => $extras, 'per_page' => $per_page, 'page' => $page));
    }

    public function people_getPublicGroups($user_id)
    {
        /* https://www.flickr.com/services/api/flickr.people.getPublicGroups.html */
        $this->request("flickr.people.getPublicGroups", array("user_id"=>$user_id));
        return $this->parsed_response ? $this->parsed_response['groups']['group'] : false;
    }

    public function people_getPublicPhotos($user_id, $safe_search = null, $extras = null, $per_page = null, $page = null)
    {
        /* https://www.flickr.com/services/api/flickr.people.getPublicPhotos.html */
        return $this->call('flickr.people.getPublicPhotos', array('user_id' => $user_id, 'safe_search' => $safe_search, 'extras' => $extras, 'per_page' => $per_page, 'page' => $page));
    }

    public function people_getUploadStatus()
    {
        /* https://www.flickr.com/services/api/flickr.people.getUploadStatus.html */
        /* Requires Authentication */
        $this->request("flickr.people.getUploadStatus");
        return $this->parsed_response ? $this->parsed_response['user'] : false;
    }

    /**
     * @return PhotosApi
     */
    public function photos()
    {
        return new PhotosApi($this);
    }

    /**
     * @deprecated Use $this->photos()->addTags() instead.
     * @param string $photoId
     * @param string|string[] $tags
     * @return bool
     * @throws FlickrException
     */
    public function photos_addTags($photoId, $tags)
    {
        return $this->photos()->addTags($photoId, $tags);
    }

    /**
     * @deprecated
     */
    public function photos_delete($photo_id)
    {
        return $this->photos()->delete($photo_id);
    }

    /**
     * @deprecated
     */
    public function photos_getAllContexts($photo_id)
    {
        return $this->photos()->getAllContexts($photo_id);
    }

    /**
     * @deprecated
     */
    public function photos_getContactsPhotos($count = null, $just_friends = null, $single_photo = null, $include_self = null, $extras = null)
    {
        return $this->photos()->getContactsPhotos($count, $just_friends, $single_photo, $include_self, $extras);
    }

    /**
     * @deprecated
     */
    public function photos_getContactsPublicPhotos($user_id, $count = null, $just_friends = null, $single_photo = null, $include_self = null, $extras = null)
    {
        return $this->photos()->getContactsPublicPhotos($user_id, $count, $just_friends, $single_photo, $include_self, $extras);
    }

    /**
     * @deprecated
     */
    public function photos_getContext($photo_id, $num_prev = null, $num_next = null, $extras = null, $order_by = null)
    {
        return $this->photos()->getContext($photo_id);
    }

    /**
     * @deprecated
     */
    public function photos_getCounts($dates = null, $taken_dates = null)
    {
        return $this->photos()->getCounts($dates, $taken_dates);
    }

    /**
     * @deprecated
     */
    public function photos_getExif($photo_id, $secret = null)
    {
        return $this->photos()->getExif($photo_id, $secret);
    }

    /**
     * @deprecated
     */
    public function photos_getFavorites($photo_id, $page = null, $per_page = null)
    {
        return $this->photos()->getFavorites($photo_id, $page, $per_page);
    }

    /**
     * @deprecated Use $this->photos()->getInfo() instead.
     * @param $photo_id
     * @param null $secret
     * @param null $humandates
     * @param null $privacy_filter
     * @param null $get_contexts
     * @return bool|string[]
     */
    public function photos_getInfo($photo_id, $secret = null, $humandates = null, $privacy_filter = null, $get_contexts = null)
    {
        return $this->photos()->getInfo($photo_id, $secret);
    }

    public function photos_getNotInSet($max_upload_date = null, $min_taken_date = null, $max_taken_date = null, $privacy_filter = null, $media = null, $min_upload_date = null, $extras = null, $per_page = null, $page = null)
    {
        /* https://www.flickr.com/services/api/flickr.photos.getNotInSet.html */
        return $this->call('flickr.photos.getNotInSet', array('max_upload_date' => $max_upload_date, 'min_taken_date' => $min_taken_date, 'max_taken_date' => $max_taken_date, 'privacy_filter' => $privacy_filter, 'media' => $media, 'min_upload_date' => $min_upload_date, 'extras' => $extras, 'per_page' => $per_page, 'page' => $page));
    }

    public function photos_getPerms($photo_id)
    {
        /* https://www.flickr.com/services/api/flickr.photos.getPerms.html */
        $this->request("flickr.photos.getPerms", array("photo_id"=>$photo_id));
        return $this->parsed_response ? $this->parsed_response['perms'] : false;
    }

    /**
     * @deprecated use $this->photos()->getRecent() instead.
     */
    public function photosGetRecent($extras = [], $perPage = null, $page = null)
    {
        return $this->photos()->getRecent($extras, $perPage, $page);
    }

    /**
     * @deprecated use $this->photos()->getRecent() instead.
     */
    public function photos_getRecent($extras = [], $perPage = null, $page = null)
    {
        return $this->photos()->getRecent($extras, $perPage, $page);
    }

    /**
     * @deprecated Use $this->photos()->getSizes() instead.
     * @param int $photoId The photo ID.
     * @return bool|string[]
     */
    public function photos_getSizes($photoId)
    {
        return $this->photos()->getSizes($photoId);
    }

    public function photos_getUntagged($min_upload_date = null, $max_upload_date = null, $min_taken_date = null, $max_taken_date = null, $privacy_filter = null, $media = null, $extras = null, $per_page = null, $page = null)
    {
        /* https://www.flickr.com/services/api/flickr.photos.getUntagged.html */
        return $this->call('flickr.photos.getUntagged', array('min_upload_date' => $min_upload_date, 'max_upload_date' => $max_upload_date, 'min_taken_date' => $min_taken_date, 'max_taken_date' => $max_taken_date, 'privacy_filter' => $privacy_filter, 'media' => $media, 'extras' => $extras, 'per_page' => $per_page, 'page' => $page));
    }

    public function photos_getWithGeoData($args = array())
    {
        /* See the documentation included with the photos_search() function.
         * I'm using the same style of arguments for this function. The only
         * difference here is that this doesn't require any arguments. The
         * flickr.photos.search method requires at least one search parameter.
         */
        /* https://www.flickr.com/services/api/flickr.photos.getWithGeoData.html */
        $this->request("flickr.photos.getWithGeoData", $args);
        return $this->parsed_response ? $this->parsed_response['photos'] : false;
    }

    public function photos_getWithoutGeoData($args = array())
    {
        /* See the documentation included with the photos_search() function.
         * I'm using the same style of arguments for this function. The only
         * difference here is that this doesn't require any arguments. The
         * flickr.photos.search method requires at least one search parameter.
         */
        /* https://www.flickr.com/services/api/flickr.photos.getWithoutGeoData.html */
        $this->request("flickr.photos.getWithoutGeoData", $args);
        return $this->parsed_response ? $this->parsed_response['photos'] : false;
    }

    public function photos_recentlyUpdated($min_date, $extras = null, $per_page = null, $page = null)
    {
        /* https://www.flickr.com/services/api/flickr.photos.recentlyUpdated.html */
        return $this->call('flickr.photos.recentlyUpdated', array('min_date' => $min_date, 'extras' => $extras, 'per_page' => $per_page, 'page' => $page));
    }

    public function photos_removeTag($tag_id)
    {
        /* https://www.flickr.com/services/api/flickr.photos.removeTag.html */
        $this->request("flickr.photos.removeTag", array("tag_id"=>$tag_id), true);
        return $this->parsed_response ? true : false;
    }

    /**
     * @deprecated
     */
    public function photos_search($args = [])
    {
        return $this->photos()->search();
    }

    public function photos_setContentType($photo_id, $content_type)
    {
        /* https://www.flickr.com/services/api/flickr.photos.setContentType.html */
        return $this->call('flickr.photos.setContentType', array('photo_id' => $photo_id, 'content_type' => $content_type));
    }

    /**
     * @deprecated
     */
    public function photos_setDates($photo_id, $date_posted = null, $date_taken = null, $date_taken_granularity = null)
    {
        return $this->photos()->setDates($photo_id, new DateTime($date_taken), $date_taken_granularity, new DateTime($date_posted));
    }

    /**
     * @deprecated
     */
    public function photos_setMeta($photo_id, $title, $description)
    {
        return $this->photos()->setMeta($photo_id, $title, $description);
    }

    public function photos_setPerms($photo_id, $is_public, $is_friend, $is_family, $perm_comment, $perm_addmeta)
    {
        /* https://www.flickr.com/services/api/flickr.photos.setPerms.html */
        $this->request("flickr.photos.setPerms", array("photo_id"=>$photo_id, "is_public"=>$is_public, "is_friend"=>$is_friend, "is_family"=>$is_family, "perm_comment"=>$perm_comment, "perm_addmeta"=>$perm_addmeta), true);
        return $this->parsed_response ? true : false;
    }

    public function photos_setSafetyLevel($photo_id, $safety_level = null, $hidden = null)
    {
        /* https://www.flickr.com/services/api/flickr.photos.setSafetyLevel.html */
        return $this->call('flickr.photos.setSafetyLevel', array('photo_id' => $photo_id, 'safety_level' => $safety_level, 'hidden' => $hidden));
    }

    public function photos_setTags($photo_id, $tags)
    {

    }

    /* Photos - Comments Methods */
    public function photos_comments_addComment($photo_id, $comment_text)
    {
        /* https://www.flickr.com/services/api/flickr.photos.comments.addComment.html */
        $this->request("flickr.photos.comments.addComment", array("photo_id" => $photo_id, "comment_text"=>$comment_text), true);
        return $this->parsed_response ? $this->parsed_response['comment'] : false;
    }

    public function photos_comments_deleteComment($comment_id)
    {
        /* https://www.flickr.com/services/api/flickr.photos.comments.deleteComment.html */
        $this->request("flickr.photos.comments.deleteComment", array("comment_id" => $comment_id), true);
        return $this->parsed_response ? true : false;
    }

    public function photos_comments_editComment($comment_id, $comment_text)
    {
        /* https://www.flickr.com/services/api/flickr.photos.comments.editComment.html */
        $this->request("flickr.photos.comments.editComment", array("comment_id" => $comment_id, "comment_text"=>$comment_text), true);
        return $this->parsed_response ? true : false;
    }

    public function photos_comments_getList($photo_id, $min_comment_date = null, $max_comment_date = null, $page = null, $per_page = null, $include_faves = null)
    {
        /* https://www.flickr.com/services/api/flickr.photos.comments.getList.html */
        return $this->call('flickr.photos.comments.getList', array('photo_id' => $photo_id, 'min_comment_date' => $min_comment_date, 'max_comment_date' => $max_comment_date, 'page' => $page, 'per_page' => $per_page, 'include_faves' => $include_faves));
    }

    public function photos_comments_getRecentForContacts($date_lastcomment = null, $contacts_filter = null, $extras = null, $per_page = null, $page = null)
    {
        /* https://www.flickr.com/services/api/flickr.photos.comments.getRecentForContacts.html */
        return $this->call('flickr.photos.comments.getRecentForContacts', array('date_lastcomment' => $date_lastcomment, 'contacts_filter' => $contacts_filter, 'extras' => $extras, 'per_page' => $per_page, 'page' => $page));
    }

    /* Photos - Geo Methods */
    public function photos_geo_batchCorrectLocation($lat, $lon, $accuracy, $place_id = null, $woe_id = null)
    {
        /* https://www.flickr.com/services/api/flickr.photos.geo.batchCorrectLocation.html */
        return $this->call('flickr.photos.geo.batchCorrectLocation', array('lat' => $lat, 'lon' => $lon, 'accuracy' => $accuracy, 'place_id' => $place_id, 'woe_id' => $woe_id));
    }

    public function photos_geo_correctLocation($photo_id, $place_id = null, $woe_id = null)
    {
        /* https://www.flickr.com/services/api/flickr.photos.geo.correctLocation.html */
        return $this->call('flickr.photos.geo.correctLocation', array('photo_id' => $photo_id, 'place_id' => $place_id, 'woe_id' => $woe_id));
    }

    public function photos_geo_getLocation($photo_id)
    {
        /* https://www.flickr.com/services/api/flickr.photos.geo.getLocation.html */
        $this->request("flickr.photos.geo.getLocation", array("photo_id"=>$photo_id));
        return $this->parsed_response ? $this->parsed_response['photo'] : false;
    }

    public function photos_geo_getPerms($photo_id)
    {
        /* https://www.flickr.com/services/api/flickr.photos.geo.getPerms.html */
        $this->request("flickr.photos.geo.getPerms", array("photo_id"=>$photo_id));
        return $this->parsed_response ? $this->parsed_response['perms'] : false;
    }

    public function photos_geo_photosForLocation($lat, $lon, $accuracy = null, $extras = null, $per_page = null, $page = null)
    {
        /* https://www.flickr.com/services/api/flickr.photos.geo.photosForLocation.html */
        return $this->call('flickr.photos.geo.photosForLocation', array('lat' => $lat, 'lon' => $lon, 'accuracy' => $accuracy, 'extras' => $extras, 'per_page' => $per_page, 'page' => $page));
    }

    public function photos_geo_removeLocation($photo_id)
    {
        /* https://www.flickr.com/services/api/flickr.photos.geo.removeLocation.html */
        $this->request("flickr.photos.geo.removeLocation", array("photo_id"=>$photo_id), true);
        return $this->parsed_response ? true : false;
    }

    public function photos_geo_setContext($photo_id, $context)
    {
        /* https://www.flickr.com/services/api/flickr.photos.geo.setContext.html */
        return $this->call('flickr.photos.geo.setContext', array('photo_id' => $photo_id, 'context' => $context));
    }

    public function photos_geo_setLocation($photo_id, $lat, $lon, $accuracy = null, $context = null, $bookmark_id = null)
    {
        /* https://www.flickr.com/services/api/flickr.photos.geo.setLocation.html */
        return $this->call('flickr.photos.geo.setLocation', array('photo_id' => $photo_id, 'lat' => $lat, 'lon' => $lon, 'accuracy' => $accuracy, 'context' => $context, 'bookmark_id' => $bookmark_id));
    }

    public function photos_geo_setPerms($is_public, $is_contact, $is_friend, $is_family, $photo_id)
    {
        /* https://www.flickr.com/services/api/flickr.photos.geo.setPerms.html */
        return $this->call('flickr.photos.geo.setPerms', array('is_public' => $is_public, 'is_contact' => $is_contact, 'is_friend' => $is_friend, 'is_family' => $is_family, 'photo_id' => $photo_id));
    }

    /**
     * @return PhotosLicensesApi
     */
    public function photosLicenses()
    {
        return new PhotosLicensesApi($this);
    }

    /**
     * @deprecated
     */
    public function photos_licenses_getInfo()
    {
        return $this->photosLicenses()->getInfo();
    }

    /**
     * @deprecated
     */
    public function photos_licenses_setLicense($photo_id, $license_id)
    {
        return $this->photosLicenses()->setLicense($photo_id, $license_id);
    }

    /* Photos - Notes Methods */
    public function photos_notes_add($photo_id, $note_x, $note_y, $note_w, $note_h, $note_text)
    {
        /* https://www.flickr.com/services/api/flickr.photos.notes.add.html */
        $this->request("flickr.photos.notes.add", array("photo_id" => $photo_id, "note_x" => $note_x, "note_y" => $note_y, "note_w" => $note_w, "note_h" => $note_h, "note_text" => $note_text), true);
        return $this->parsed_response ? $this->parsed_response['note'] : false;
    }

    public function photos_notes_delete($note_id)
    {
        /* https://www.flickr.com/services/api/flickr.photos.notes.delete.html */
        $this->request("flickr.photos.notes.delete", array("note_id" => $note_id), true);
        return $this->parsed_response ? true : false;
    }

    public function photos_notes_edit($note_id, $note_x, $note_y, $note_w, $note_h, $note_text)
    {
        /* https://www.flickr.com/services/api/flickr.photos.notes.edit.html */
        $this->request("flickr.photos.notes.edit", array("note_id" => $note_id, "note_x" => $note_x, "note_y" => $note_y, "note_w" => $note_w, "note_h" => $note_h, "note_text" => $note_text), true);
        return $this->parsed_response ? true : false;
    }

    /* Photos - Transform Methods */
    public function photos_transform_rotate($photo_id, $degrees)
    {
        /* https://www.flickr.com/services/api/flickr.photos.transform.rotate.html */
        $this->request("flickr.photos.transform.rotate", array("photo_id" => $photo_id, "degrees" => $degrees), true);
        return $this->parsed_response ? true : false;
    }

    /* Photos - People Methods */
    public function photos_people_add($photo_id, $user_id, $person_x = null, $person_y = null, $person_w = null, $person_h = null)
    {
        /* https://www.flickr.com/services/api/flickr.photos.people.add.html */
        return $this->call('flickr.photos.people.add', array('photo_id' => $photo_id, 'user_id' => $user_id, 'person_x' => $person_x, 'person_y' => $person_y, 'person_w' => $person_w, 'person_h' => $person_h));
    }

    public function photos_people_delete($photo_id, $user_id, $email = null)
    {
        /* https://www.flickr.com/services/api/flickr.photos.people.delete.html */
        return $this->call('flickr.photos.people.delete', array('photo_id' => $photo_id, 'user_id' => $user_id, 'email' => $email));
    }

    public function photos_people_deleteCoords($photo_id, $user_id)
    {
        /* https://www.flickr.com/services/api/flickr.photos.people.deleteCoords.html */
        return $this->call('flickr.photos.people.deleteCoords', array('photo_id' => $photo_id, 'user_id' => $user_id));
    }

    public function photos_people_editCoords($photo_id, $user_id, $person_x, $person_y, $person_w, $person_h, $email = null)
    {
        /* https://www.flickr.com/services/api/flickr.photos.people.editCoords.html */
        return $this->call('flickr.photos.people.editCoords', array('photo_id' => $photo_id, 'user_id' => $user_id, 'person_x' => $person_x, 'person_y' => $person_y, 'person_w' => $person_w, 'person_h' => $person_h, 'email' => $email));
    }

    public function photos_people_getList($photo_id)
    {
        /* https://www.flickr.com/services/api/flickr.photos.people.getList.html */
        return $this->call('flickr.photos.people.getList', array('photo_id' => $photo_id));
    }

    /* Photos - Upload Methods */
    public function photos_upload_checkTickets($tickets)
    {
        /* https://www.flickr.com/services/api/flickr.photos.upload.checkTickets.html */
        if (is_array($tickets)) {
            $tickets = implode(",", $tickets);
        }
        $this->request("flickr.photos.upload.checkTickets", array("tickets" => $tickets), true);
        return $this->parsed_response ? $this->parsed_response['uploader']['ticket'] : false;
    }

    /**
     * @return PhotosetsApi
     */
    public function photosets()
    {
        return new PhotosetsApi($this);
    }

    /**
     * @deprecated
     */
    public function photosets_addPhoto($photoset_id, $photo_id)
    {
        return $this->photosets()->addPhoto($photoset_id, $photo_id);
    }

    /**
     * @deprecated
     */
    public function photosets_create($title, $description, $primary_photo_id)
    {
        return $this->photosets()->create($title, $description, $primary_photo_id);
    }

    /**
     * @deprecated
     */
    public function photosets_delete($photoset_id)
    {
        return $this->photosets()->delete($photoset_id);
    }

    /**
     * @deprecated
     */
    public function photosets_editMeta($photoset_id, $title, $description = null)
    {
        return $this->photosets()->editMeta($photoset_id, $title, $description);
    }

    /**
     * @deprecated
     */
    public function photosets_editPhotos($photoset_id, $primary_photo_id, $photo_ids)
    {
        return $this->photosets()->editPhotos($photoset_id, $primary_photo_id, $photo_ids);
    }

    /**
     * @deprecated
     */
    public function photosets_getContext($photo_id, $photoset_id, $num_prev = null, $num_next = null)
    {
        return $this->photosets()->getContext($photo_id, $photoset_id);
    }

    /**
     * @deprecated
     */
    public function photosets_getInfo($photoset_id, $user_id)
    {
        return $this->photosets()->getInfo($photoset_id, $user_id);
    }

    /**
     * @deprecated
     */
    public function photosets_getList($user_id = null, $page = null, $per_page = null, $primary_photo_extras = null)
    {
        return $this->photosets()->getList($user_id, $page, $per_page, $primary_photo_extras);
    }

    /**
     * @deprecated
     */
    public function photosets_getPhotos($photoset_id, $extras = null, $privacy_filter = null, $per_page = null, $page = null, $media = null)
    {
        return $this->photosets()->getPhotos($photoset_id, null, $extras, $per_page, $page, $privacy_filter, $media);
    }

    /**
     * @deprecated
     */
    public function photosets_orderSets($photoset_ids)
    {
        return $this->photosets()->orderSets($photoset_ids);
    }

    /**
     * @deprecated
     */
    public function photosets_removePhoto($photoset_id, $photo_id)
    {
        return $this->photosets()->removePhoto($photoset_id, $photo_id);
    }

    /**
     * @deprecated
     */
    public function photosets_removePhotos($photoset_id, $photo_ids)
    {
        return $this->photosets()->removePhotos($photoset_id, $photo_ids);
    }

    /**
     * @deprecated
     */
    public function photosets_reorderPhotos($photoset_id, $photo_ids)
    {
        return $this->photosets()->reorderPhotos($photoset_id, $photo_ids);
    }

    /**
     * @deprecated
     */
    public function photosets_setPrimaryPhoto($photoset_id, $photo_id)
    {
        return $this->photosets()->setPrimaryPhoto($photoset_id, $photo_id);
    }

    /* Photosets Comments Methods */
    public function photosets_comments_addComment($photoset_id, $comment_text)
    {
        /* https://www.flickr.com/services/api/flickr.photosets.comments.addComment.html */
        $this->request("flickr.photosets.comments.addComment", array("photoset_id" => $photoset_id, "comment_text"=>$comment_text), true);
        return $this->parsed_response ? $this->parsed_response['comment'] : false;
    }

    public function photosets_comments_deleteComment($comment_id)
    {
        /* https://www.flickr.com/services/api/flickr.photosets.comments.deleteComment.html */
        $this->request("flickr.photosets.comments.deleteComment", array("comment_id" => $comment_id), true);
        return $this->parsed_response ? true : false;
    }

    public function photosets_comments_editComment($comment_id, $comment_text)
    {
        /* https://www.flickr.com/services/api/flickr.photosets.comments.editComment.html */
        $this->request("flickr.photosets.comments.editComment", array("comment_id" => $comment_id, "comment_text"=>$comment_text), true);
        return $this->parsed_response ? true : false;
    }

    public function photosets_comments_getList($photoset_id)
    {
        /* https://www.flickr.com/services/api/flickr.photosets.comments.getList.html */
        $this->request("flickr.photosets.comments.getList", array("photoset_id"=>$photoset_id));
        return $this->parsed_response ? $this->parsed_response['comments'] : false;
    }

    /* Places Methods */
    public function places_find($query)
    {
        /* https://www.flickr.com/services/api/flickr.places.find.html */
        return $this->call('flickr.places.find', array('query' => $query));
    }

    public function places_findByLatLon($lat, $lon, $accuracy = null)
    {
        /* https://www.flickr.com/services/api/flickr.places.findByLatLon.html */
        return $this->call('flickr.places.findByLatLon', array('lat' => $lat, 'lon' => $lon, 'accuracy' => $accuracy));
    }

    public function places_getChildrenWithPhotosPublic($place_id = null, $woe_id = null)
    {
        /* https://www.flickr.com/services/api/flickr.places.getChildrenWithPhotosPublic.html */
        return $this->call('flickr.places.getChildrenWithPhotosPublic', array('place_id' => $place_id, 'woe_id' => $woe_id));
    }

    public function places_getInfo($place_id = null, $woe_id = null)
    {
        /* https://www.flickr.com/services/api/flickr.places.getInfo.html */
        return $this->call('flickr.places.getInfo', array('place_id' => $place_id, 'woe_id' => $woe_id));
    }

    public function places_getInfoByUrl($url)
    {
        /* https://www.flickr.com/services/api/flickr.places.getInfoByUrl.html */
        return $this->call('flickr.places.getInfoByUrl', array('url' => $url));
    }

    public function places_getPlaceTypes()
    {
        /* https://www.flickr.com/services/api/flickr.places.getPlaceTypes.html */
        return $this->call('flickr.places.getPlaceTypes', array());
    }

    public function places_getShapeHistory($place_id = null, $woe_id = null)
    {
        /* https://www.flickr.com/services/api/flickr.places.getShapeHistory.html */
        return $this->call('flickr.places.getShapeHistory', array('place_id' => $place_id, 'woe_id' => $woe_id));
    }

    public function places_getTopPlacesList($place_type_id, $date = null, $woe_id = null, $place_id = null)
    {
        /* https://www.flickr.com/services/api/flickr.places.getTopPlacesList.html */
        return $this->call('flickr.places.getTopPlacesList', array('place_type_id' => $place_type_id, 'date' => $date, 'woe_id' => $woe_id, 'place_id' => $place_id));
    }

    public function places_placesForBoundingBox($bbox, $place_type = null, $place_type_id = null, $recursive = null)
    {
        /* https://www.flickr.com/services/api/flickr.places.placesForBoundingBox.html */
        return $this->call('flickr.places.placesForBoundingBox', array('bbox' => $bbox, 'place_type' => $place_type, 'place_type_id' => $place_type_id, 'recursive' => $recursive));
    }

    public function places_placesForContacts($place_type = null, $place_type_id = null, $woe_id = null, $place_id = null, $threshold = null, $contacts = null, $min_upload_date = null, $max_upload_date = null, $min_taken_date = null, $max_taken_date = null)
    {
        /* https://www.flickr.com/services/api/flickr.places.placesForContacts.html */
        return $this->call('flickr.places.placesForContacts', array('place_type' => $place_type, 'place_type_id' => $place_type_id, 'woe_id' => $woe_id, 'place_id' => $place_id, 'threshold' => $threshold, 'contacts' => $contacts, 'min_upload_date' => $min_upload_date, 'max_upload_date' => $max_upload_date, 'min_taken_date' => $min_taken_date, 'max_taken_date' => $max_taken_date));
    }

    public function places_placesForTags($place_type_id, $woe_id = null, $place_id = null, $threshold = null, $tags = null, $tag_mode = null, $machine_tags = null, $machine_tag_mode = null, $min_upload_date = null, $max_upload_date = null, $min_taken_date = null, $max_taken_date = null)
    {
        /* https://www.flickr.com/services/api/flickr.places.placesForTags.html */
        return $this->call('flickr.places.placesForTags', array('place_type_id' => $place_type_id, 'woe_id' => $woe_id, 'place_id' => $place_id, 'threshold' => $threshold, 'tags' => $tags, 'tag_mode' => $tag_mode, 'machine_tags' => $machine_tags, 'machine_tag_mode' => $machine_tag_mode, 'min_upload_date' => $min_upload_date, 'max_upload_date' => $max_upload_date, 'min_taken_date' => $min_taken_date, 'max_taken_date' => $max_taken_date));
    }

    public function places_placesForUser($place_type_id = null, $place_type = null, $woe_id = null, $place_id = null, $threshold = null, $min_upload_date = null, $max_upload_date = null, $min_taken_date = null, $max_taken_date = null)
    {
        /* https://www.flickr.com/services/api/flickr.places.placesForUser.html */
        return $this->call('flickr.places.placesForUser', array('place_type_id' => $place_type_id, 'place_type' => $place_type, 'woe_id' => $woe_id, 'place_id' => $place_id, 'threshold' => $threshold, 'min_upload_date' => $min_upload_date, 'max_upload_date' => $max_upload_date, 'min_taken_date' => $min_taken_date, 'max_taken_date' => $max_taken_date));
    }

    public function places_resolvePlaceId($place_id)
    {
        /* https://www.flickr.com/services/api/flickr.places.resolvePlaceId.html */
        $rsp = $this->call('flickr.places.resolvePlaceId', array('place_id' => $place_id));
        return $rsp ? $rsp['location'] : $rsp;
    }

    public function places_resolvePlaceURL($url)
    {
        /* https://www.flickr.com/services/api/flickr.places.resolvePlaceURL.html */
        $rsp = $this->call('flickr.places.resolvePlaceURL', array('url' => $url));
        return $rsp ? $rsp['location'] : $rsp;
    }

    public function places_tagsForPlace($woe_id = null, $place_id = null, $min_upload_date = null, $max_upload_date = null, $min_taken_date = null, $max_taken_date = null)
    {
        /* https://www.flickr.com/services/api/flickr.places.tagsForPlace.html */
        return $this->call('flickr.places.tagsForPlace', array('woe_id' => $woe_id, 'place_id' => $place_id, 'min_upload_date' => $min_upload_date, 'max_upload_date' => $max_upload_date, 'min_taken_date' => $min_taken_date, 'max_taken_date' => $max_taken_date));
    }

    /* Prefs Methods */
    public function prefs_getContentType()
    {
        /* https://www.flickr.com/services/api/flickr.prefs.getContentType.html */
        $rsp = $this->call('flickr.prefs.getContentType', array());
        return $rsp ? $rsp['person'] : $rsp;
    }

    public function prefs_getGeoPerms()
    {
        /* https://www.flickr.com/services/api/flickr.prefs.getGeoPerms.html */
        return $this->call('flickr.prefs.getGeoPerms', array());
    }

    public function prefs_getHidden()
    {
        /* https://www.flickr.com/services/api/flickr.prefs.getHidden.html */
        $rsp = $this->call('flickr.prefs.getHidden', array());
        return $rsp ? $rsp['person'] : $rsp;
    }

    public function prefs_getPrivacy()
    {
        /* https://www.flickr.com/services/api/flickr.prefs.getPrivacy.html */
        $rsp = $this->call('flickr.prefs.getPrivacy', array());
        return $rsp ? $rsp['person'] : $rsp;
    }

    public function prefs_getSafetyLevel()
    {
        /* https://www.flickr.com/services/api/flickr.prefs.getSafetyLevel.html */
        $rsp = $this->call('flickr.prefs.getSafetyLevel', array());
        return $rsp ? $rsp['person'] : $rsp;
    }

    /* Reflection Methods */
    public function reflection_getMethodInfo($method_name)
    {
        /* https://www.flickr.com/services/api/flickr.reflection.getMethodInfo.html */
        $this->request("flickr.reflection.getMethodInfo", array("method_name" => $method_name));
        return $this->parsed_response ? $this->parsed_response : false;
    }

    public function reflection_getMethods()
    {
        /* https://www.flickr.com/services/api/flickr.reflection.getMethods.html */
        $this->request("flickr.reflection.getMethods");
        return $this->parsed_response ? $this->parsed_response['methods']['method'] : false;
    }

    /* Stats Methods */
    public function stats_getCollectionDomains($date, $collection_id = null, $per_page = null, $page = null)
    {
        /* https://www.flickr.com/services/api/flickr.stats.getCollectionDomains.html */
        return $this->call('flickr.stats.getCollectionDomains', array('date' => $date, 'collection_id' => $collection_id, 'per_page' => $per_page, 'page' => $page));
    }

    public function stats_getCollectionReferrers($date, $domain, $collection_id = null, $per_page = null, $page = null)
    {
        /* https://www.flickr.com/services/api/flickr.stats.getCollectionReferrers.html */
        return $this->call('flickr.stats.getCollectionReferrers', array('date' => $date, 'domain' => $domain, 'collection_id' => $collection_id, 'per_page' => $per_page, 'page' => $page));
    }

    public function stats_getCollectionStats($date, $collection_id)
    {
        /* https://www.flickr.com/services/api/flickr.stats.getCollectionStats.html */
        return $this->call('flickr.stats.getCollectionStats', array('date' => $date, 'collection_id' => $collection_id));
    }

    public function stats_getCSVFiles()
    {
        /* https://www.flickr.com/services/api/flickr.stats.getCSVFiles.html */
        return $this->call('flickr.stats.getCSVFiles', array());
    }

    public function stats_getPhotoDomains($date, $photo_id = null, $per_page = null, $page = null)
    {
        /* https://www.flickr.com/services/api/flickr.stats.getPhotoDomains.html */
        return $this->call('flickr.stats.getPhotoDomains', array('date' => $date, 'photo_id' => $photo_id, 'per_page' => $per_page, 'page' => $page));
    }

    public function stats_getPhotoReferrers($date, $domain, $photo_id = null, $per_page = null, $page = null)
    {
        /* https://www.flickr.com/services/api/flickr.stats.getPhotoReferrers.html */
        return $this->call('flickr.stats.getPhotoReferrers', array('date' => $date, 'domain' => $domain, 'photo_id' => $photo_id, 'per_page' => $per_page, 'page' => $page));
    }

    public function stats_getPhotosetDomains($date, $photoset_id = null, $per_page = null, $page = null)
    {
        /* https://www.flickr.com/services/api/flickr.stats.getPhotosetDomains.html */
        return $this->call('flickr.stats.getPhotosetDomains', array('date' => $date, 'photoset_id' => $photoset_id, 'per_page' => $per_page, 'page' => $page));
    }

    public function stats_getPhotosetReferrers($date, $domain, $photoset_id = null, $per_page = null, $page = null)
    {
        /* https://www.flickr.com/services/api/flickr.stats.getPhotosetReferrers.html */
        return $this->call('flickr.stats.getPhotosetReferrers', array('date' => $date, 'domain' => $domain, 'photoset_id' => $photoset_id, 'per_page' => $per_page, 'page' => $page));
    }

    public function stats_getPhotosetStats($date, $photoset_id)
    {
        /* https://www.flickr.com/services/api/flickr.stats.getPhotosetStats.html */
        return $this->call('flickr.stats.getPhotosetStats', array('date' => $date, 'photoset_id' => $photoset_id));
    }

    public function stats_getPhotoStats($date, $photo_id)
    {
        /* https://www.flickr.com/services/api/flickr.stats.getPhotoStats.html */
        return $this->call('flickr.stats.getPhotoStats', array('date' => $date, 'photo_id' => $photo_id));
    }

    public function stats_getPhotostreamDomains($date, $per_page = null, $page = null)
    {
        /* https://www.flickr.com/services/api/flickr.stats.getPhotostreamDomains.html */
        return $this->call('flickr.stats.getPhotostreamDomains', array('date' => $date, 'per_page' => $per_page, 'page' => $page));
    }

    public function stats_getPhotostreamReferrers($date, $domain, $per_page = null, $page = null)
    {
        /* https://www.flickr.com/services/api/flickr.stats.getPhotostreamReferrers.html */
        return $this->call('flickr.stats.getPhotostreamReferrers', array('date' => $date, 'domain' => $domain, 'per_page' => $per_page, 'page' => $page));
    }

    public function stats_getPhotostreamStats($date)
    {
        /* https://www.flickr.com/services/api/flickr.stats.getPhotostreamStats.html */
        return $this->call('flickr.stats.getPhotostreamStats', array('date' => $date));
    }

    public function stats_getPopularPhotos($date = null, $sort = null, $per_page = null, $page = null)
    {
        /* https://www.flickr.com/services/api/flickr.stats.getPopularPhotos.html */
        return $this->call('flickr.stats.getPopularPhotos', array('date' => $date, 'sort' => $sort, 'per_page' => $per_page, 'page' => $page));
    }

    public function stats_getTotalViews($date = null)
    {
        /* https://www.flickr.com/services/api/flickr.stats.getTotalViews.html */
        return $this->call('flickr.stats.getTotalViews', array('date' => $date));
    }

    /* Tags Methods */
    public function tags_getClusterPhotos($tag, $cluster_id)
    {
        /* https://www.flickr.com/services/api/flickr.tags.getClusterPhotos.html */
        return $this->call('flickr.tags.getClusterPhotos', array('tag' => $tag, 'cluster_id' => $cluster_id));
    }

    public function tags_getClusters($tag)
    {
        /* https://www.flickr.com/services/api/flickr.tags.getClusters.html */
        return $this->call('flickr.tags.getClusters', array('tag' => $tag));
    }

    public function tags_getHotList($period = null, $count = null)
    {
        /* https://www.flickr.com/services/api/flickr.tags.getHotList.html */
        $this->request("flickr.tags.getHotList", array("period" => $period, "count" => $count));
        return $this->parsed_response ? $this->parsed_response['hottags'] : false;
    }

    public function tags_getListPhoto($photo_id)
    {
        /* https://www.flickr.com/services/api/flickr.tags.getListPhoto.html */
        $this->request("flickr.tags.getListPhoto", array("photo_id" => $photo_id));
        return $this->parsed_response ? $this->parsed_response['photo']['tags']['tag'] : false;
    }

    public function tags_getListUser($user_id = null)
    {
        /* https://www.flickr.com/services/api/flickr.tags.getListUser.html */
        $this->request("flickr.tags.getListUser", array("user_id" => $user_id));
        return $this->parsed_response ? $this->parsed_response['who']['tags']['tag'] : false;
    }

    public function tags_getListUserPopular($user_id = null, $count = null)
    {
        /* https://www.flickr.com/services/api/flickr.tags.getListUserPopular.html */
        $this->request("flickr.tags.getListUserPopular", array("user_id" => $user_id, "count" => $count));
        return $this->parsed_response ? $this->parsed_response['who']['tags']['tag'] : false;
    }

    public function tags_getListUserRaw($tag = null)
    {
        /* https://www.flickr.com/services/api/flickr.tags.getListUserRaw.html */
        return $this->call('flickr.tags.getListUserRaw', array('tag' => $tag));
    }

    public function tags_getRelated($tag)
    {
        /* https://www.flickr.com/services/api/flickr.tags.getRelated.html */
        $this->request("flickr.tags.getRelated", array("tag" => $tag));
        return $this->parsed_response ? $this->parsed_response['tags'] : false;
    }

    /**
     * @return TestApi
     */
    public function test()
    {
        return new TestApi($this);
    }

    /**
     * @deprecated Use $this->test()->testEcho() instead.
     */
    public function test_echo($args = [])
    {
        return $this->test()->testEcho($args);
    }

    /**
     * @deprecated Use $this->test()->login() instead.
     */
    public function test_login()
    {
        return $this->test()->login();
    }

	/**
	 * @return UrlsApi
	 */
	public function urls()
	{
		return new UrlsApi($this);
	}

	/**
	 * @deprecated
	 */
	public function urls_getGroup($group_id)
	{
		return $this->urls()->getGroup($group_id);
	}

	/**
	 * @deprecated
	 */
	public function urls_getUserPhotos($user_id = null)
	{
		return $this->urls()->getUserPhotos($user_id);
	}

	/**
	 * @deprecated
	 */
	public function urls_getUserProfile($user_id = null)
	{
		return $this->urls()->getUserProfile($user_id);
	}

	/**
	 * @deprecated
	 */
	public function urls_lookupGallery($url)
	{
		return $this->urls()->lookupGallery($url);
	}

	/**
	 * @deprecated
	 */
	public function urls_lookupGroup($url)
	{
		return $this->urls()->lookupGroup($url);
	}

	/**
	 * @deprecated
	 */
	public function urls_lookupUser($url)
	{
		return $this->urls()->lookupUser($url);
	}
}
