phpFlickr
=========

A PHP wrapper for the Flickr API.

https://github.com/samwilson/phpflickr

[![Packagist](https://img.shields.io/packagist/v/samwilson/phpflickr.svg?style=flat-square)](https://packagist.org/packages/samwilson/phpflickr)

[![Build Status](https://scrutinizer-ci.com/g/samwilson/phpflickr/badges/build.png?b=master)](https://scrutinizer-ci.com/g/samwilson/phpflickr/build-status/master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/samwilson/phpflickr/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/samwilson/phpflickr/?branch=master)

## Installation

Install with [Composer](https://getcomposer.org/):

    composer require samwilson/phpflickr

## Usage

Once you've included Composer's autoloader, create a PhpFlickr object.
For example:

```php
require_once 'vendor/autoload.php';
$flickr = new \Samwilson\PhpFlickr\PhpFlickr($apiKey, $apiSecret);
```

The constructor takes three arguments:

1. `$api_key` — This is the API key given to you by Flickr
   when you [register an app](https://www.flickr.com/services/api/keys/).

2. `$secret` — The API secret is optional because it is only required to
   make authenticated requests ([see below](#making-authenticated-requests)).
   It is given to you along with your API key when you register an app.

3. `$die_on_error` - This takes a boolean value and determines 
   whether the class will die (aka cease operation) if the API 
   returns an error statement.  It defaults to false.  Every method 
   will return false if the API returns an error.  You can access 
   error messages using the getErrorCode() and getErrorMsg() 
   methods.

All of the API methods have been implemented in phpFlickr.  You can 
see a full list and documentation here: 
    http://www.flickr.com/services/api/

To call a method, remove the "flickr." part of the name and replace 
any periods with underscores. For example, instead of 
flickr.photos.search, you would call $f->photos_search() or instead 
of flickr.photos.licenses.getInfo, you would call 
$f->photos_licenses_getInfo() (yes, it is case sensitive).

All functions have their arguments implemented in the list order on 
their documentation page (a link to which is included with each 
method in the phpFlickr clasS). The only exceptions to this are 
photos_search(), photos_getWithoutGeodata() and 
photos_getWithoutGeodata() which have so many optional arguments
that it's easier for everyone if you just have to pass an 
associative array of arguments.  See the comment in the 
photos_search() definition in phpFlickr.php for more information.

## Authentication

There is only one user authentication method available to the API, and that is OAuth 1.0.
You only need to use this if you're performing operations that require it,
such as uploading or accessing private photos.

This authentication method is somewhat complex,
but is secure and allows your users to feel a little safer authenticating to your application.
You don't have to ask for their username and password.

☛ *Read more about the [Flickr Authentication API](https://www.flickr.com/services/api/auth.oauth.html).*

We know how difficult this API looks at first glance,
so we've tried to make it as transparent as possible for users of phpFlickr.
We'll go through all of the steps you'll need to do to use this.

To have end users authenticate their accounts:

1. Create an object in which to temporarily store the authentication token,
   and give it to PhpFlickr.
   This must be an implementation of TokenStorageInterface,
   and will usually be of type `Session` (for browser-based workflows)
   or `Memory` (for command-line workflows)
   — or you can create your own implementation.

   ```php
   $storage = new \OAuth\Common\Storage\Memory();
   $flickr->setOauthStorage($storage);
   ```

2. Send your user to a Flickr URL (by redirecting them, or just telling them to click a link),
   where they'll confirm that they want your application to have the permission you specify
   (which is either `read`, `write`, or `delete`).

   ```php
   $perm = 'read';
   $url = $flickr->getAuthUrl($perm, $callbackUrl);
   ```

3. Once the user has authorized your application, they'll
   either be redirected back to a URL on your site (that you specified as the callback URL above)
   or be given a nine-digit code that they'll need to copy and paste into your application.

   1. For the browser-based workflow, your callback URL will now have
      two new query-string parameters: `oauth_token` and `oauth_verifier`.
   2. For CLI workflow, you'll need to strip anything other than digits from the string that the user gives you
      (e.g. leading and trailing spaces, and the hyphens in the code).

4. You can now request the final 'access token':

   1. For the browser-based workflow:
      ```php
      $accessToken = $flickr->retrieveAccessToken($_GET['oauth_verifier'], $_GET['oauth_token']);
      ```
   2. For the CLI workflow, it's much the same,
      but because you've still got access to the request token
      you can leave it out when you run this request:
      ```php
      $verifier = '<9-digit code stripped of hyphens and spaces>';
      $accessToken = $flickr->retrieveAccessToken($verifier);
      ```

5. Now you can save the two string parts of the access token
   (which you can get via
   the `$accessToken->getAccessToken()` and `$accessToken->getAccessTokenSecret()` methods)
   and use this for future requests.
   The access token doesn't expire, and must be stored securely
   (the details of doing that are outside the scope of PhpFlickr).

## Making authenticated requests

Once you have an access token (see [above](#authentication)),
you can store it somewhere secure and use it to make authenticated requests at a later time.
To do this, first create a storage object
(again, as for the initial authentication process, you can choose between different storage types,
but for many situations the in-memory storage is sufficient),
and then store your access token in that object:

```php
// Create storage.
$storage = new \OAuth\Common\Storage\Memory();
// Create the access token from the strings you acquired before.
$token = new \OAuth\OAuth1\Token\StdOAuth1Token();
$token->setAccessToken($accessToken);
$token->setAccessTokenSecret($accessTokenSecret);
// Add the token to the storage.
$storage->storeAccessToken('Flickr', $token);
```

Now, you can pass the storage into PhpFlickr, and start making requests:

```php
$flickr->setOauthStorage($storage);
$recent = $phpFlickr->photos_getContactsPhotos();
```

See the [Usage section](#usage) above for more details on the request methods,
and the `examples/recent_photos.php` file for a working example.

## Caching

Caching can be very important to a project.  Just a few calls to the Flickr API
can take long enough to bore your average web user (depending on the calls you
are making).  I've built in caching that will access either a database or files
in your filesystem.  To enable caching, use the phpFlickr::enableCache() function.
This function requires at least two arguments. The first will be the type of
cache you're using (either "db" or "fs")
    
1.  If you're using database caching, you'll need to supply a PEAR::DB style connection
    string. For example: 

        $flickr->enableCache("db", "mysql://user:password@server/database");
        
    The third (optional) argument is expiration of the cache in seconds (defaults 
    to 600).  The fourth (optional) argument is the table where you want to store
    the cache.  This defaults to flickr_cache and will attempt to create the table
    if it does not already exist.
    
2.  If you're using filesystem caching, you'll need to supply a folder where the
    web server has write access. For example: 
    
        $flickr->enableCache("fs", "/var/www/phpFlickrCache");
    
    The third (optional) argument is, the same as in the Database caching, an
    expiration in seconds for the cache.

    Note: filesystem caching will probably be slower than database caching. I
    haven't done any tests of this, but if you get large amounts of calls, the
    process of cleaning out old calls may get hard on your server.
        
    You may not want to allow the world to view the files that are created during
    caching.  If you want to hide this information, either make sure that your
    permissions are set correctly, or disable the webserver from displaying 
    *.cache files.  In Apache, you can specify this in the configuration files
    or in a .htaccess file with the following directives:
        
        <FilesMatch "\.cache$">
            Deny from all
        </FilesMatch>
    
    Alternatively, you can specify a directory that is outside of the web server's
    document root.

## Uploading

Uploading is pretty simple. Aside from being authenticated (see Authentication 
section) the very minimum that you'll have to pass is a path to an image file on 
your php server. You can do either synchronous or asynchronous uploading as follows:

    synchronous:    sync_upload("photo.jpg");
    asynchronous:   async_upload("photo.jpg");
    
The basic difference is that synchronous uploading waits around until Flickr
processes the photo and returns a PhotoID.  Asynchronous just uploads the
picture and gets a "ticketid" that you can use to check on the status of your 
upload. Asynchronous is much faster, though the photoid won't be instantly
available for you. You can read more about asynchronous uploading here:

    http://www.flickr.com/services/api/upload.async.html
        
Both of the functions take the same arguments which are:

> Photo: The path of the file to upload.  
> Title: The title of the photo.  
> Description: A description of the photo. May contain some limited HTML.  
> Tags: A space-separated list of tags to apply to the photo.  
> is_public: Set to 0 for no, 1 for yes.  
> is_friend: Set to 0 for no, 1 for yes.  
> is_family: Set to 0 for no, 1 for yes.

## Replacing Photos

Flickr has released API support for uploading a replacement photo.  To use this
new method, just use the "replace" function in phpFlickr.  You'll be required
to pass the file name and Flickr's photo ID.  You need to authenticate your script
with "write" permissions before you can replace a photo.  The arguments are:

> Photo: The path of the file to upload.  
> Photo ID: The numeric Flickr ID of the photo you want to replace.  
> Async (optional): Set to 0 for a synchronous call, 1 for asynchronous.  
    
If you use the asynchronous call, it will return a ticketid instead
of photoid.

Other Notes:
1.  Many of the methods have optional arguments.  For these, I have implemented 
    them in the same order that the Flickr API documentation lists them. PHP
    allows for optional arguments in function calls, but if you want to use the
    third optional argument, you have to fill in the others to the left first.
    You can use the "NULL" value (without quotes) in the place of an actual
    argument.  For example:
    
        $f->groups_pools_getPhotos($group_id, NULL, NULL, 10);

    This will get the first ten photos from a specific group's pool.  If you look
    at the documentation, you will see that there is another argument, "page". I've
    left it off because it appears after "per_page".

2.  Some people will need to ues phpFlickr from behind a proxy server.  I've
    implemented a method that will allow you to use an HTTP proxy for all of your
    traffic.  Let's say that you have a proxy server on your local server running
    at port 8181.  This is the code you would use:

        $f = new phpFlickr("[api key]");
        $f->setProxy("localhost", "8181");

    After that, all of your calls will be automatically made through your proxy server.
 
## Kudos

This is a fork of Dan Coulter's original [phpFlickr](https://github.com/dan-coulter/phpflickr)
library, maintained by Sam Wilson. All the hard work was done by Dan!

Thanks also is greatly due to the many other
[contributors](https://github.com/samwilson/phpflickr/graphs/contributors).
