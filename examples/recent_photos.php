<?php
/**
 * This example file shows you how to retrieve information about your contacts' ten most recent
 * photos. Before using this, you should use examples/get_auth_token.php to retrieve an access
 * token and add it to examples/config.php.
 *
 * Most of the processing time in this file comes from the ten calls to flickr.people.getInfo.
 * Enabling caching will help a whole lot with this as there are many people who post multiple
 * photos at once.
 *
 * @file
 */

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
$recent = $phpFlickr->photos()->getRecent([], 10);

// Display a list of photo titles.
echo '<ul>';
foreach ($recent as $photo) {
    $owner = $phpFlickr->people_getInfo($photo['owner']);
    $url = 'https://flic.kr/p/'.\Samwilson\PhpFlickr\Util::base58encode($photo['id']);
    echo "<li> Photo: <a href='$url'>".$photo['title']."</a>";
    echo "; Owner: ";
    echo "<a href='https://www.flickr.com/people/".$photo['owner']."/'>";
    echo $owner['username'];
    echo "</a>.</li>";
}
echo '</ul>';
