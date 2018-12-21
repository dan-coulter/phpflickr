PhpFlickr
=========

A PHP wrapper for the Flickr API.

https://github.com/samwilson/phpflickr

[![Packagist](https://img.shields.io/packagist/v/samwilson/phpflickr.svg)](https://packagist.org/packages/samwilson/phpflickr)

[![Build Status](https://travis-ci.com/samwilson/phpflickr.svg?branch=master)](https://travis-ci.com/samwilson/phpflickr)

Table of contents:

* [Installation](#installation)
* [Usage](#usage)
* [Examples](#examples)
* [Authentication](#authentication)
* [Making authenticated requests](#making-authenticated-requests)
* [Caching](#caching)
* [Uploading](#uploading)
* [Kudos](#kudos)

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

The constructor takes two arguments:

1. `$apiKey` — This is the API key given to you by Flickr
   when you [register an app](https://www.flickr.com/services/api/keys/).

2. `$secret` — The API secret is optional because it is only required to
   make authenticated requests ([see below](#making-authenticated-requests)).
   It is given to you along with your API key when you register an app.

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

## Examples

There are a few example files in the `examples/` directory.
To use these, first copy `examples/config.dist.php` to `examples/config.php`
and run `php examples/get_auth_token.php` to get the access token.
Add this access token to your `examples/config.php`
and then you can run any of the examples that require authentication
(note that not all of them do).

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

PhpFlickr can be used with any PSR-6 compatible cache, such as
[symfony/cache](https://packagist.org/packages/symfony/cache)
or [tedivm/stash](https://packagist.org/packages/tedivm/stash).

To enable caching, pass a configured cache object to `PhpFlickr::setCache($cacheItemPool)`.

All requests are cached for the same time duration, which by default is 10 minutes.
This can be changed with the `PhpFlickr::setCacheDefaultExpiry()`.

## Uploading

### Uploading new photos

Uploading is pretty simple. Aside from being authenticated
(see the [Authentication](#Authentication) section above)
the very minimum that you'll have to pass is a path to an image file.
You can upload a file as follows:

```php
$flickr->uploader()->upload('/path/to/photo.jpg');
```

The other upload parameters are documented in the method's docblock.
One useful one is the `$async` flag, which permits *asyncronous* uploading,
which means that, rather than uploading the file immediately and before returning,
a 'ticket ID' is returned, with which you can subsequently fetch the upload's status.
You can read more about asynchronous uploading
in [Flickr's API documentation](https://www.flickr.com/services/api/upload.async.html).

### Replacing existing photos

You can also upload a photo as a replacement to an existing photo.

```php
$flickr->uploader()->replace('/path/to/photo.jpg', 44333812150);
```

This method doesn't allow for setting any photo metadata,
but you can do the replacement asynchronously
(in which case a 'ticket ID' will be returned).

## Proxy server

Some people will need to ues phpFlickr from behind a proxy server.  I've
implemented a method that will allow you to use an HTTP proxy for all of your
traffic.  Let's say that you have a proxy server on your local server running
at port 8181.  This is the code you would use:

    $f = new phpFlickr("[api key]");
    $f->setProxy("localhost", "8181");

After that, all of your calls will be automatically made through your proxy server.

This can also be used to target services that mimic Flickr's API,
such as [23 Photo Sharing](http://www.23hq.com).

## Kudos

This is a fork of Dan Coulter's original [phpFlickr](https://github.com/dan-coulter/phpflickr)
library, maintained by Sam Wilson. All the hard work was done by Dan!

Thanks also is greatly due to the many other
[contributors](https://github.com/samwilson/phpflickr/graphs/contributors).

The [Agateware_Example.JPG](https://commons.wikimedia.org/wiki/File:Agateware_Example.JPG)
used for the upload examples is [CC-BY-SA](https://creativecommons.org/licenses/by-sa/4.0)
by User:Anonymouse512, via Wikimedia Commons.
