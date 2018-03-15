<?php

namespace Samwilson\PhpFlickr;

class PhotosetsApi extends ApiMethodGroup
{

    /**
     * Returns the photosets belonging to the specified user.
     * @link https://www.flickr.com/services/api/flickr.photosets.getList.html
     * @param string $userId The NSID of the user to get a photoset list for. If none is
     * specified, the calling user is assumed.
     * @param int $page The page of results to get. Currently, if this is not provided, all sets are
     * returned, but this behaviour may change in future.
     * @param int $perPage The number of sets to get per page. If paging is enabled, the maximum
     * number of sets per page is 500.
     * @param string $primaryPhotoExtras A comma-delimited list of extra information to fetch for
     * the primary photo. Currently supported fields are: license, date_upload, date_taken,
     * owner_name, icon_server, original_format, last_update, geo, tags, machine_tags, o_dims,
     * views, media, path_alias, url_sq, url_t, url_s, url_m, url_o
     * @param string photoIds A comma-separated list of photo ids. If specified, each returned set
     * will include a list of these photo IDs that are present in the set as
     * "has_requested_photos".
     * @return mixed
     */
    public function getList(
        $userId = null,
        $page = null,
        $perPage = null,
        $primaryPhotoExtras = null,
        $photoIds = null
    ) {
        $response = $this->flickr->request(
            'flickr.photosets.getList',
            [
                'user_id' => $userId,
                'page' => $page,
                'per_page' => $perPage,
                'primary_photo_extras' => $primaryPhotoExtras,
                'photo_ids' => $photoIds,
            ]
        );
        return isset($response['photosets']) ? $response['photosets'] : false;
    }
}
