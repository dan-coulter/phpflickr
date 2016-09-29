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

//change this to the permissions you will need
$flickr->auth("read");

echo "Copy this token into your code: " . $_SESSION['phpFlickr_auth_token'];
