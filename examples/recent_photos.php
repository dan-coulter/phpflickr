<?php
/* Last updated with phpFlickr 1.3.2
 *
 * This example file shows you how to call the 100 most recent public
 * photos.  It parses through them and prints out a link to each of them
 * along with the owner's name.
 *
 * Most of the processing time in this file comes from the 100 calls to
 * flickr.people.getInfo.  Enabling caching will help a whole lot with
 * this as there are many people who post multiple photos at once.
 */

require_once __DIR__.'/../vendor/autoload.php';

// Load the API key from the .env file.
$dotenv = new \Dotenv\Dotenv(__DIR__);
$dotenv->load();

// Create the PhpFlickr object with your API key.
$phpFlickr = new \Samwilson\PhpFlickr\PhpFlickr(getenv('API_KEY'));

// Make a request.
$recent = $phpFlickr->photosGetRecent([], 10);

// Display a list of photo titles.
foreach ($recent['photos']['photo'] as $photo) {
    $owner = $phpFlickr->people_getInfo($photo['owner']);
    echo "<a href='http://www.flickr.com/photos/" . $photo['owner'] . "/" . $photo['id'] . "/'>";
    echo $photo['title'];
    echo "</a> Owner: ";
    echo "<a href='http://www.flickr.com/people/" . $photo['owner'] . "/'>";
    echo $owner['username']['_content'];
    echo "</a><br>";
}
