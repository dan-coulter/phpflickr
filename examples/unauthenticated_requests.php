<?php
/**
 * The simplest example of PhpFlickr: an API call that doesn't require authentication.
 * In this situation, you don't need to provide the API secret.
 * @file
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Get the configuration information; in this case, only the API key.
$configFile = __DIR__ . '/config.php';
require_once $configFile;
if (empty($apiKey)) {
    echo 'Please set $apiKey in '.$configFile;
    exit(1);
}

// Create a PhpFlickr object, leaving out the API secret (the 2nd parameter)
$flickr = new \Samwilson\PhpFlickr\PhpFlickr($apiKey);

// Get the 10 most recent photos, with URLs for their 'small' size.
// For details of the size suffixes, see https://www.flickr.com/services/api/misc.urls.html
$recent = $flickr->photosGetRecent(['url_s'], 10);

print_r($recent);
