<?php
/**
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

$flickr = new \Samwilson\PhpFlickr\PhpFlickr($apiKey, $apiSecret);

// Send some requests without caching.
echo "No caching:\n";
sendRequests($flickr);

// Now add a cache.
$driver = new Stash\Driver\FileSystem([ 'path' => __DIR__.'/cache' ]);
$pool = new Stash\Pool($driver);
$flickr->setCache($pool);

// Now send the same requests, and the second of them should be much faster.
echo "With caching:\n";
sendRequests($flickr);

/**
 * Send three test requests and output their execution time.
 * @param \Samwilson\PhpFlickr\PhpFlickr $flickr
 */
function sendRequests(\Samwilson\PhpFlickr\PhpFlickr $flickr)
{
    for ($i=1; $i<=3; $i++) {
        $start = microtime(true);
        $flickr->test_echo([]);
        echo "  $i.  ".number_format(microtime(true) - $start, 3)."\n";
    }
}
