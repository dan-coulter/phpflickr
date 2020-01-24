<?php
/**
 * This file demonstrates the authentication workflows for both browser and CLI.
 *
 * It is an example only, and you should understand what it's doing and adapt it for your uses.
 * However, it is a fully working example, and so can also be used to obtain an access token that
 * you can save for further use within your application.
 *
 * @file
 */

require_once __DIR__.'/../vendor/autoload.php';

$configFile = __DIR__.'/config.php';
require_once $configFile;
if (empty($apiKey) || empty($apiSecret)) {
    echo 'Please set $apiKey and $apiSecret in '.$configFile;
    exit(1);
}
$flickr = new \Samwilson\PhpFlickr\PhpFlickr($apiKey, $apiSecret);

if (isset($_SERVER['SERVER_NAME'])) {
    /*
     * The web-browser workflow.
     */
    $storage = new \OAuth\Common\Storage\Session();
    $flickr->setOauthStorage($storage);

    if (!isset($_GET['oauth_token'])) {
        $callbackHere = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'];
        $url = $flickr->getAuthUrl('delete', $callbackHere);
        echo "<a href='$url'>$url</a>";
    }

    if (isset($_GET['oauth_token'])) {
        $accessToken = $flickr->retrieveAccessToken($_GET['oauth_verifier'], $_GET['oauth_token']);
    }
} else {
    /*
     * The CLI workflow.
     */
    $storage = new \OAuth\Common\Storage\Memory();
    $flickr->setOauthStorage($storage);

    $url = $flickr->getAuthUrl('delete');
    echo "Go to $url\nEnter access code: ";
    $code = fgets(STDIN);
    $verifier = preg_replace('/[^0-9]/', '', $code);
    $accessToken = $flickr->retrieveAccessToken($verifier);
}

if (isset($accessToken) && $accessToken instanceof \OAuth\Common\Token\TokenInterface) {
    /*
     * You should save the access token and its secret somewhere safe.
     */
    echo '$accessToken = "'.$accessToken->getAccessToken().'";'.PHP_EOL;
    echo '$accessTokenSecret = "'.$accessToken->getAccessTokenSecret().'";'.PHP_EOL;

    /*
     * Any methods can now be called.
     */
    $login = $flickr->test()->login();
    echo "You are authenticated as: {$login['username']}\n";
}
