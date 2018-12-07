<?php

require_once __DIR__.'/../vendor/autoload.php';

// Make sure we have the required configuration values.
$configFile = __DIR__.'/config.php';
require_once $configFile;
if (empty($apiKey) || empty($apiSecret) || empty($accessToken) || empty($accessTokenSecret)) {
    echo 'Please set $apiKey, $apiSecret, $accessToken, and $accessTokenSecret in '.$configFile;
    exit(1);
}

// Add your access token to the storage.
$token = new \OAuth\OAuth1\Token\StdOAuth1Token();
$token->setAccessToken($accessToken);
$token->setAccessTokenSecret($accessTokenSecret);
$storage = new \OAuth\Common\Storage\Memory();
$storage->storeAccessToken('Flickr', $token);

// Create PhpFlickr.
$phpFlickr = new \Samwilson\PhpFlickr\PhpFlickr($apiKey, $apiSecret);

// Give PhpFlickr the storage containing the access token.
$phpFlickr->setOauthStorage($storage);

// Make a request.
$description = 'An example of agate pottery. By Anonymouse512.
Via Wikimedia Commons: https://commons.wikimedia.org/wiki/File:Agateware_Example.JPG';
$result = $phpFlickr->uploader()->upload(
    __DIR__.'/Agateware_Example.JPG',
    'Test photo',
    $description,
    'Agateware pots',
    true,
    true,
    true
);
$info = $phpFlickr->photos()->getInfo($result['photoid']);
echo "The new photo is: ".$info['urls']['url'][0]['_content']."\n";
