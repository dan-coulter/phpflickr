<?php

namespace Samwilson\PhpFlickr;

class PhotosApi extends ApiMethodGroup
{

    /** Size s: small square 75x75 */
    const SIZE_SMALL_SQUARE = 's';

    /** Size q: large square 150x150 */
    const SIZE_LARGE_SQUARE = 'q';

    /** Size t: 100 on longest side */
    const SIZE_THUMBNAIL = 't';

    /** Size m: 240 on longest side */
    const SIZE_SMALL_240 = 'm';

    /** Size n: 320 on longest side */
    const SIZE_SMALL_320 = 'n';

    /** Size -: 500 on longest side */
    const SIZE_MEDIUM_500 = '-';

    /** Size z: 640 on longest side */
    const SIZE_MEDIUM_640 = 'z';

    /** Size c: 800 on longest side. Only exist after 1 March 2012. */
    const SIZE_MEDIUM_800 = 'c';

    /**
     * Size b: 1024 on longest side. Before May 25th 2010 large photos only exist for very large
     * original images.
     */
    const SIZE_LARGE_1024 = 'b';

    /** Size h: 1600 on longest side. Only exist after 1 March 2012. */
    const SIZE_LARGE_1600 = 'h';

    /** Size k: 2048 on longest side. Only exist after 1 March 2012. */
    const SIZE_LARGE_2048 = 'k';

    /** Size o: original image, either a jpg, gif or png, depending on source format. */
    const SIZE_ORIGINAL = 'o';

    /**
     * Get information about a photo. The calling user must have permission to view the photo.
     * @link https://www.flickr.com/services/api/flickr.photos.getInfo.html
     * @param string $photoId The ID of the photo to get information for.
     * @param string $secret The secret for the photo. If the correct secret is passed then
     * permissions checking is skipped. This enables the 'sharing' of individual photos by passing
     * around the id and secret.
     * @return string[]|bool
     */
    public function getInfo($photoId, $secret = null)
    {
        $params = ['photo_id' => $photoId, 'secret' => $secret];
        $response = $this->flickr->request('flickr.photos.getInfo', $params);
        return isset($response['photo']) ? $response['photo'] : false;
    }

    /**
     * Returns the available sizes for a photo. The calling user must have permission to view the photo.
     * @link https://www.flickr.com/services/api/flickr.photos.getSizes.html
     * @link https://www.flickr.com/services/api/misc.urls.html
     * @param int $photoId The ID of the photo to fetch size information for.
     * @return string[]|bool
     */
    public function getSizes($photoId)
    {
        $response = $this->flickr->request('flickr.photos.getSizes', ['photo_id'=>$photoId]);
        return isset($response['sizes']) ? $response['sizes'] : false;
    }

    /**
     * A convenience wrapper for self::getSizes() to get information about largest available size.
     * @link https://www.flickr.com/services/api/flickr.photos.getSizes.html
     * @link https://www.flickr.com/services/api/misc.urls.html
     * @param int $photoId The ID of the photo to fetch size information for.
     * @return string[]|bool
     */
    public function getLargestSize($photoId)
    {
        $sizes = $this->getSizes($photoId);
        if (!$sizes) {
            return false;
        }
        $areas = [];
        foreach ($sizes['size'] as $size) {
            // Use original if available.
            if ($size['label'] === 'Original') {
                return $size;
            }
            // Otherwise record the area for later calculation of maximum.
            $areas[$size['label']] = $size['width'] * $size['height'];
        }
        // Now find the largest.
        $largestAreaLabel = array_search(max($areas), $areas);
        foreach ($sizes['size'] as $size) {
            if ($size['label'] === $largestAreaLabel) {
                return $size;
            }
        }
        return false;
    }
}
