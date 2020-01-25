<?php

namespace Samwilson\PhpFlickr;

use DateTime;

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
     * Add tags to a photo.
     * @link https://www.flickr.com/services/api/flickr.photos.addTags.html
     * @param string $photoId The photo to add tags to.
     * @param string|string[] $tags A space-separated string of tags (double-quoted, where
     * a tag contains a space), or an array of strings (no quoting necessary). Any double quotes
     * within tag names will be removed.
     * @return bool True if no error occured.
     */
    public function addTags($photoId, $tags)
    {
        $tagString = $tags;
        if (is_array($tags)) {
            $quotedTags = array_map(function ($tag) {
                // It's not possible to have double quotes in a tag.
                $cleanTag = str_replace('"', '', $tag);
                // Wrap any tag with spaces in it inside double quotes.
                return strpos($cleanTag, ' ') ? '"'.$cleanTag.'"' : $cleanTag;
            }, $tags);
            $tagString = implode(' ', $quotedTags);
        }
        return (bool)$this->flickr->request('flickr.photos.addTags', [
            'photo_id' => $photoId,
            'tags' => $tagString,
        ], true);
    }

    /**
     * Delete a photo from flickr.
     *
     * @link https://www.flickr.com/services/api/flickr.photos.delete.html
     * @param $photoId string The ID of the photo to delete.
     * @return bool
     */
    public function delete($photoId)
    {
        return (bool)$this->flickr->request('flickr.photos.delete', ['photo_id'=>$photoId], true);
    }

    /**
     * Returns all visible sets and pools the photo belongs to.
     *
     * @link https://www.flickr.com/services/api/flickr.photos.getAllContexts.html
     * @param $photoId string The photo to return information for.
     * @return bool
     */
    public function getAllContexts($photoId)
    {
        return $this->request('flickr.photos.getAllContexts', ['photo_id'=>$photoId]);
    }

    /**
     * Fetch a list of recent photos from the calling users' contacts.
     *
     * @link https://www.flickr.com/services/api/flickr.photos.getContactsPhotos.html
     * @param $count int|null Number of photos to return. Defaults to 10, maximum 50. This is only used if single_photo
     * is not passed.
     * @param $justFriends bool|null Set as 1 to only show photos from friends and family (excluding regular contacts).
     * @param $singlePhoto bool|null Only fetch one photo (the latest) per contact, instead of all photos in
     * chronological order.
     * @param $includeSelf bool|null Whether to include photos from the calling user.
     * @param $extras string|string[] An array or comma-delimited list of extra information to fetch for each returned
     * record. Currently supported fields include: license, date_upload, date_taken, owner_name, icon_server,
     * original_format, last_update. For more information see extras under flickr.photos.search.
     * @return mixed
     */
    public function getContactsPhotos(
        $count = null,
        $justFriends = null,
        $singlePhoto = null,
        $includeSelf = null,
        $extras = null
    ) {
        if (is_array($extras)) {
            $extras = join(',', $extras);
        }
        $params = [
            'count' => $count,
            'just_friends' => $justFriends,
            'single_photo' => $singlePhoto,
            'include_self'=> $includeSelf,
            'extras' => $extras
        ];
        $response = $this->flickr->request('flickr.photos.getContactsPhotos', $params);
        return isset($response['photos']['photo']) ? $response['photos']['photo'] : false;
    }

    /**
     * Fetch a list of recent public photos from a users' contacts.
     *
     * @link https://www.flickr.com/services/api/flickr.photos.getContactsPublicPhotos.html
     * @param $userId string The NSID of the user to fetch photos for.
     * @param $count int|null Number of photos to return. Defaults to 10, maximum 50. This is only used if $singlePhoto
     * is false.
     * @param $justFriends bool|null Whether to only show photos from friends and family (excluding regular contacts).
     * @param $singlePhoto bool|null Only fetch one photo (the latest) per contact, instead of all photos in
     * chronological order.
     * @param $includeSelf bool|null Whether to include photos from the user specified by $userId.
     * @param $extras string|string[]|null Array or comma-delimited list of extra information to fetch for each returned
     * record. Currently supported fields are: license, date_upload, date_taken, owner_name, icon_server,
     * original_format, last_update.
     * @return mixed
     */
    public function getContactsPublicPhotos(
        $userId,
        $count = null,
        $justFriends = null,
        $singlePhoto = null,
        $includeSelf = null,
        $extras = null
    ) {
        if (is_array($extras)) {
            $extras = join(',', $extras);
        }
        $params = [
            'user_id' => $userId,
            'count' => $count,
            'just_friends' => $justFriends,
            'single_photo' => $singlePhoto,
            'include_self'=> $includeSelf,
            'extras' => $extras
        ];
        $response = $this->flickr->request('flickr.photos.getContactsPublicPhotos', $params);
        return isset($response['photos']['photo']) ? $response['photos']['photo'] : false;
    }

    /**
     * Returns next and previous photos for a photo in a photostream.
     *
     * @link https://www.flickr.com/services/api/flickr.photos.getContext.html
     * @param $photoId string The ID of the photo to fetch the context for.
     * @return mixed
     */
    public function getContext($photoId)
    {
        return $this->flickr->request('flickr.photos.getContext', ['photo_id' => $photoId]);
    }

    /**
     * Gets a list of photo counts for the given date ranges for the calling user.
     *
     * @link https://www.flickr.com/services/api/flickr.photos.getCounts.html
     * @param $dates string|string[]|null Array or comma-delimited list of unix timestamps, denoting the periods to
     * return counts for. They should be specified smallest first.
     * @param $takenDates string|string[]|null Array or comma-delimited list of MySQL datetimes, denoting the periods to
     * return counts for. They should be specified smallest first.
     * @return bool
     */
    public function getCounts($dates = null, $takenDates = null)
    {
        if (is_array($dates)) {
            $dates = join(',', $dates);
        }
        if (is_array($takenDates)) {
            $takenDates = join(',', $takenDates);
        }
        $params = ['dates' => $dates, 'taken_dates' => $takenDates];
        $response = $this->flickr->request('flickr.photos.getCounts', $params);
        return isset($response['photocounts']['photocount']) ? $response['photocounts']['photocount'] : false;
    }

    /**
     * Retrieve a list of EXIF/TIFF/GPS tags for a given photo. The calling user must have permission to view the photo.
     *
     * @link https://www.flickr.com/services/api/flickr.photos.getExif.html
     * @param $photoId string The ID of the photo to fetch information for.
     * @param $secret string|null The secret for the photo. If the correct secret is passed then permissions-checking is
     * skipped. This enables the 'sharing' of individual photos by passing around the ID and secret.
     * @return mixed
     */
    public function getExif($photoId, $secret = null)
    {
        $response = $this->flickr->request('flickr.photos.getExif', ['photo_id'=>$photoId, 'secret'=>$secret]);
        return isset($response['photo']) ? $response['photo'] : false;
    }

    /**
     * Returns the list of people who have favorited a given photo.
     *
     * @link https://www.flickr.com/services/api/flickr.photos.getFavorites.html
     * @param $photoId string The ID of the photo to fetch the favoriters list for.
     * @param $page int|null The page of results to return. If this argument is omitted, it defaults to 1.
     * @param $perPage int|null Number of users to return per page. If this argument is omitted, it defaults to 10. The
     * maximum allowed value is 50.
     * @return mixed
     */
    public function getFavorites($photoId, $page = null, $perPage = null)
    {
        $params = ['photo_id'=>$photoId, 'page'=>$page, 'per_page'=>$perPage];
        $response = $this->flickr->request('flickr.photos.getFavorites', $params);
        return isset($response['photo']) ? $response['photo'] : false;
    }

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

    //flickr.photos.getInfo
    //flickr.photos.getNotInSet
    //flickr.photos.getPerms
    //flickr.photos.getPopular

    /**
     * Get information about the sets to which the given photos belong.
     * @param int[] $photoIds The photo IDs to look for.
     * @param string $userId The user who owns the photos (if not set, will default to the
     * current calling user).
     * @return string[][]|bool Set information, or false if none found (or an error occured).
     */
    public function getSets($photoIds, $userId = null)
    {
        $out = [];
        $photoIdsString = join(',', $photoIds);
        $sets = $this->flickr->photosets()->getList(
            $userId,
            null,
            null,
            null,
            $photoIdsString
        );
        if (!isset($sets['photoset'])) {
            return false;
        }

        // for users with more than 500 albums, we must search the photoId page by page (thanks, Flickr...)
        foreach (range(1, $sets['pages']) as $pageNum) {
            if ($pageNum > 1) {
                // download the next page of photosets to search
                $sets = $this->flickr->photosets()->getList(
                    $userId,
                    $pageNum,
                    null,
                    null,
                    $photoIdsString
                );
            }
            foreach ($sets['photoset'] as $photoset) {
                foreach ($photoIds as $photoId) {
                    if (in_array($photoId, $photoset['has_requested_photos'])) {
                        $out[] = $photoset;
                    }
                }
            }
        }
        return $out;
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
        $response = $this->flickr->request(
            'flickr.photos.getSizes',
            ['photo_id' => $photoId]
        );
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

    /**
     * Returns a list of the latest public photos uploaded to flickr.
     * This method does not require authentication.
     * @link https://www.flickr.com/services/api/flickr.photos.getRecent.html
     * @param string[]|string $extras An array or comma-separated list of extra information to
     * fetch for each returned record. Currently supported fields are: description, license,
     * date_upload, date_taken, owner_name, icon_server, original_format, last_update, geo, tags,
     * machine_tags, o_dims, views, media, path_alias, url_sq, url_t, url_s, url_q, url_m, url_n,
     * url_z, url_c, url_l, and url_o. For details of the size suffixes,
     * see https://www.flickr.com/services/api/misc.urls.html
     * @param int $perPage Number of photos to return per page. If this argument is omitted,
     * it defaults to 100. The maximum allowed value is 500.
     * @param integer $page The page of results to return. If this argument is omitted, it defaults
     * to 1.
     * @return string[][]|bool
     */
    public function getRecent($extras = [], $perPage = null, $page = null)
    {
        if (is_array($extras)) {
            $extras = implode(",", $extras);
        }
        $args = ['extras' => $extras, 'per_page' => $perPage, 'page' => $page ];
        $result = $this->flickr->request('flickr.photos.getRecent', $args);
        return isset($result['photos']['photo']) ? $result['photos']['photo'] : false;
    }

    //flickr.photos.getUntagged
    //flickr.photos.getWithGeoData
    //flickr.photos.getWithoutGeoData
    //flickr.photos.recentlyUpdated
    //flickr.photos.removeTag

    /**
     * Return a list of photos matching some criteria. Only photos visible to the calling user will be returned. To
     * return private or semi-private photos, the caller must be authenticated with 'read' permissions, and have
     * permission to view the photos. Unauthenticated calls will only return public photos.
     * @link https://www.flickr.com/services/api/flickr.photos.search.html
     * @param array $args See the Flickr API link above for details of the permitted keys of this array.
     * @return array|bool
     */
    public function search($args)
    {
        $result = $this->flickr->request('flickr.photos.search', $args);
        return isset($result['photos']) ? $result['photos'] : false;
    }

    //flickr.photos.setContentType

    /**
     * Set one or both of the dates for a photo.
     * @link https://www.flickr.com/services/api/flickr.photos.setDates.html
     * $param int $photoId The ID of the photo to edit dates for.
     * @param DateTime|null $dateTaken The date the photo was taken.
     * @param int $dateTakenGranularity The granularity of the $dateTaken parameter.
     * One of the Util::DATE_GRANULARITY_* constants.
     * @param DateTime|null $datePosted The date the photo was uploaded to Flickr.
     * @return bool True on success.
     */
    public function setDates(
        $photoId,
        DateTime $dateTaken = null,
        $dateTakenGranularity = null,
        DateTime $datePosted = null
    ) {
        $args = ['photo_id' => $photoId];
        if (!empty($dateTaken)) {
            $args['date_taken'] = $dateTaken->format('Y-m-d H:i:s');
        }
        if (!empty($dateTakenGranularity)) {
            $args['date_taken_granularity'] = $dateTakenGranularity;
        }
        if (!empty($datePosted)) {
            $args['date_posted'] = $datePosted->format('U');
        }
        $result = $this->flickr->request('flickr.photos.setDates', $args, true);
        return isset($result['stat']) && $result['stat'] === 'ok';
    }

    /**
     * Set the main metadata for a photo.
     * @link https://www.flickr.com/services/api/flickr.photos.setMeta.html
     * @param int $photoId The ID of the photo to set information for.
     * @param string $title The title for the photo. At least one of title or description must be set.
     * @param string $description The description for the photo. At least one of title or description must be set.
     * @return bool True on success.
     * @throws FlickrException If neither $title or $description is set.
     */
    public function setMeta($photoId, $title = null, $description = null)
    {
        if (empty($title) && empty($description)) {
            throw new FlickrException('$title or $description must be set');
        }
        $args = ['photo_id' => $photoId];
        if (!empty($title)) {
            $args['title'] = $title;
        }
        if (!empty($description)) {
            $args['description'] = $description;
        }
        $result = $this->flickr->request('flickr.photos.setMeta', $args, true);
        return isset($result['stat']) && $result['stat'] === 'ok';
    }

    //flickr.photos.setPerms
    //flickr.photos.setSafetyLevel

    /**
     * Set all of the tags for a photo, replacing any that are already there.
     * @link https://www.flickr.com/services/api/flickr.photos.setTags.html
     * @param int $photoId The photo ID.
     * @param string $tags All tags for the photo (as a single space-delimited string; tags with spaces in them should
     * be quoted).
     * @return bool
     */
    public function setTags($photoId, $tags)
    {
        $result = $this->flickr->request('flickr.photos.setTags', ['photo_id' => $photoId, 'tags' => $tags], true);
        return isset($result['stat']) && $result['stat'] === 'ok';
    }
}
