<?php

namespace Samwilson\PhpFlickr;

class UrlsApi extends ApiMethodGroup
{

    /**
     * Get the URL for a photo's image at a given size.
     * @link https://www.flickr.com/services/api/misc.urls.html
     * @param string[] $photoInfo With keys 'farm', 'server', 'id',
     * and either 'secret' or both 'originalsecret' and 'originalformat'.
     * @param string $size The size to retrieve: one of the PhotosApi::SIZE_* constants.
     * @return string
     */
    public function getImageUrl($photoInfo, $size = '')
    {
        $size = strtolower($size);

        $sizeSuffixes = [
            "square" => "_s",
            "square_75" => "_s",
            "square_150" => "_q",
            "thumbnail" => "_t",
            "small" => "_m",
            "small_240" => "_m",
            "small_320" => "_n",
            "medium" => "",
            "medium_500" => "",
            "medium_640" => "_z",
            "medium_800" => "_c",
            "large" => "_b",
            "large_1024" => "_b",
            "large_1600" => "_h",
            "large_2048" => "_k",
            "original" => "_o",
        ];

        // Default to medium size.
        $sizeSuffix = '';

        // Backwards compatibility.
        if (isset($sizeSuffixes[$size])) {
            $sizeSuffix = $sizeSuffixes[$size];
        }

        // Create non-medium suffix.
        if (in_array('_'.$size, $sizeSuffixes)) {
            $sizeSuffix = '_'.$size;
        }

        $url = 'https://farm'.$photoInfo['farm'].'.staticflickr.com/'.$photoInfo['server'].'/'.$photoInfo['id'];
        if ($size === PhotosApi::SIZE_ORIGINAL) {
            $url .= '_'.$photoInfo['originalsecret'].'_o.'.$photoInfo['originalformat'];
        } else {
            $url .= '_'.$photoInfo['secret'].$sizeSuffix.'.jpg';
        }
        return $url;
    }

    /**
     * Get the short URL for a single photo (using the 'flic.kr' domain name).
     * @param int $photoId
     * @return string
     */
    public function getShortUrl($photoId)
    {
        return 'https://flic.kr/p/'.Util::base58encode($photoId);
    }

    /**
     * Returns the URL to a group's page.
     * @link https://www.flickr.com/services/api/flickr.urls.getGroup.html
     * @param string $groupId
     * @return string|bool
     */
    public function getGroup($groupId)
    {
        $response = $this->flickr->request('flickr.urls.getGroup', ['group_id' => $groupId]);
        return isset($response['group']['url']) ? $response['group']['url'] : false;
    }

    /**
     * Returns the URL to a user's photos.
     * @link https://www.flickr.com/services/api/flickr.urls.getUserPhotos.html
     * @param string $userId
     * @return string|bool
     */
    public function getUserPhotos($userId = null)
    {
        $response = $this->flickr->request('flickr.urls.getUserPhotos', ['user_id' => $userId]);
        return isset($response['user']['url']) ? $response['user']['url'] : false;
    }

    /**
     * Returns the URL of a user's profile.
     * @link https://www.flickr.com/services/api/flickr.urls.getUserProfile.html
     * @param null $userId
     * @return string|bool
     */
    public function getUserProfile($userId = null)
    {
        $response = $this->flickr->request('flickr.urls.getUserProfile', ['user_id' => $userId]);
        return isset($response['user']['url']) ? $response['user']['url'] : false;
    }

    /**
     * Returns gallery info given a gallery's URL.
     * @link https://www.flickr.com/services/api/flickr.urls.lookupGallery.html
     * @param string $url
     * @return string|bool
     */
    public function lookupGallery($url)
    {
        return $this->flickr->request('flickr.urls.lookupGallery', ['url' => $url]);
    }

    /**
     * Returns a group NSID, given the URL to a group's page or photo pool.
     * @link https://www.flickr.com/services/api/flickr.urls.lookupGroup.html
     * @param string $url The URL to the group's page or photo pool.
     * @return string|bool
     */
    public function lookupGroup($url)
    {
        $response = $this->flickr->request('flickr.urls.lookupGroup', ['url' => $url]);
        return isset($response['group']) ? $response['group'] : false;
    }

    /**
     * Returns a user NSID, given the url to a user's photos or profile.
     * @link https://www.flickr.com/services/api/flickr.urls.lookupUser.html
     * @param string $url The url to the user's profile or photos page.
     * @return string|bool
     */
    public function lookupUser($url)
    {
        $response = $this->flickr->request('flickr.urls.lookupUser', ['url' => $url]);
        return isset($response['user']) ? $response['user'] : false;
    }
}
