<?php
/**
 * If you need your app to always login with the same user (to see your private
 * photos or photosets, for example), you can use this file to login and get a
 * token assigned so that you can hard code the token to be used.  To use this
 * use the phpFlickr::setToken() function whenever you create an instance of
 * the class.
 */

require __DIR__ . '/../vendor/autoload.php';

$flickr = new \Samwilson\PhpFlickr\PhpFlickr("<api key>", "<secret>");
$storage = new \OAuth\Common\Storage\Session();

if (!isset($_GET['oauth_token'])) {
    $callbackHere = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
    $url = $flickr->getAuthUrl($storage, 'read', $callbackHere);
    echo "<a href='$url'>$url</a>";
}

if (isset($_GET['oauth_token'])) {
    $accessToken = $flickr->getAccessToken( $storage, $_GET['oauth_token'], $_GET['oauth_verifier'] );
    var_dump($accessToken);
}
// oauth_token=72157689713894975-04ef3ada05b5f5be&oauth_verifier=3f0e774133c93624

//if () {
//    
//}

//$flickrService->requestAccessToken($token, $verifier, $accessTokenSecret);
