<?php

namespace Samwilson\PhpFlickr;

class PeopleApi extends ApiMethodGroup
{

    /**
     * Return a user's NSID, given their email address
     * @link https://www.flickr.com/services/api/flickr.people.findByEmail.html
     * @param string $findEmail The email address of the user to find (may be primary or secondary).
     * @return bool
     */
    public function findByEmail($findEmail)
    {
        $response = $this->flickr->request(
            'flickr.people.findByEmail',
            ['find_email' => $findEmail]
        );
        return isset($response['user']) ? $response['user'] : false;
    }

    /**
     * Return a user's NSID, given their username.
     * @link https://www.flickr.com/services/api/flickr.people.findByUsername.html
     * @param string $username The username of the user to lookup.
     * @return bool
     */
    public function findByUsername($username)
    {
        $response = $this->flickr->request(
            'flickr.people.findByUsername',
            ['username' => $username]
        );
        return isset($response['user']) ? $response['user'] : false;
    }

    public function getGroups()
    {
    }

    public function getInfo()
    {
    }

    public function getLimits()
    {
    }

    /**
     * Return photos from the given user's photostream.
     * Only photos visible to the calling user will be returned.
     * This method must be authenticated;
     * to return public photos for a user, use self::getPublicPhotos().
     * @link https://www.flickr.com/services/api/flickr.people.getPhotos.html
     * @param string $userId The NSID of the user who's photos to return. A value of
     * "me" will return the calling user's photos.
     * @param int $safeSearch Safe search setting: 1 for safe. 2 for moderate. 3 for
     * restricted. (Please note: Un-authed calls can only see Safe content.)
     * @param string $minUploadDate Minimum upload date. Photos with an upload date greater than or
     * equal to this value will be returned. The date should be in the form of a unix timestamp.
     * @param string $maxUploadDate Maximum upload date. Photos with an upload date less than or
     * equal to this value will be returned. The date should be in the form of a unix timestamp.
     * @param string $minTakenDate Minimum taken date. Photos with an taken date greater than or
     * equal to this value will be returned. The date should be in the form of a mysql datetime.
     * @param string $maxTakenDate Maximum taken date. Photos with an taken date less than or equal
     * to this value will be returned. The date should be in the form of a mysql datetime.
     * @param int $contentType Content Type setting:
     * 1 for photos only.
     * 2 for screenshots only.
     * 3 for 'other' only.
     * 4 for photos and screenshots.
     * 5 for screenshots and 'other'.
     * 6 for photos and 'other'.
     * 7 for photos, screenshots, and 'other' (all).
     * @param int $privacyFilter Return photos only matching a certain privacy level. This only
     * applies when making an authenticated call to view photos you own. Valid values are:
     * 1 public photos
     * 2 private photos visible to friends
     * 3 private photos visible to family
     * 4 private photos visible to friends & family
     * 5 completely private photos
     * @param string $extras A comma-delimited list of extra information to fetch for each
     * returned record. Currently supported fields are: description, license, date_upload,
     * date_taken, owner_name, icon_server, original_format, last_update, geo, tags, machine_tags,
     * o_dims, views, media, path_alias, url_sq, url_t, url_s, url_q, url_m, url_n, url_z, url_c,
     * url_l, url_o
     * @param int $perPage Number of photos to return per page. The maximum allowed value is 500.
     * @param int $page The page of results to return.
     * @return string[]|bool Photo information, or false if none.
     */
    public function getPhotos(
        $userId = 'me',
        $safeSearch = null,
        $minUploadDate = null,
        $maxUploadDate = null,
        $minTakenDate = null,
        $maxTakenDate = null,
        $contentType = null,
        $privacyFilter = null,
        $extras = null,
        $perPage = 100,
        $page = 1
    ) {
        $params = [
            'user_id' => $userId,
            'safe_search' => $safeSearch,
            'min_upload_date' => $minUploadDate,
            'max_upload_date' => $maxUploadDate,
            'min_taken_date' => $minTakenDate,
            'max_taken_date' => $maxTakenDate,
            'content_type' => $contentType,
            'privacy_filter' => $privacyFilter,
            'extras' => $extras,
            'per_page' => $perPage,
            'page' => $page,
        ];
        $photos = $this->flickr->request('flickr.people.getPhotos', $params);
        return isset($photos['photos']) ? $photos['photos'] : false;
    }

    public function getPhotosOf()
    {
    }

    public function getPublicGroups()
    {
    }

    public function getPublicPhotos()
    {
    }

    public function getUploadStatus()
    {
    }
}
