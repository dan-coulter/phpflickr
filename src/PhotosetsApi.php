<?php

namespace Samwilson\PhpFlickr;

class PhotosetsApi extends ApiMethodGroup
{

    /**
     * Add a photo to the end of an existing photoset.
     * @link https://www.flickr.com/services/api/flickr.photosets.addPhoto.html
     * @param int $photosetId The ID of the photoset to add a photo to.
     * @param int $photoId
     * @return bool
     */
    public function addPhoto($photosetId, $photoId)
    {
        $args = [
            'photoset_id' => $photosetId,
            'photo_id' => $photoId,
        ];
        $response = $this->flickr->request('flickr.photosets.addPhoto', $args, true);
        return (bool)$response;
    }

    /**
     * Create a new photoset for the calling user.
     * This method requires authentication with 'write' permission.
     * New photosets are automatically put first in the photoset ordering for the user.
     * @see PhotosetsApi::orderSets() if you don't want the new set to appear first on the user's
     * photoset list.
     * @link https://www.flickr.com/services/api/flickr.photosets.create.html
     * @param string $title A title for the photoset.
     * @param string $description A description of the photoset. May contain limited HTML.
     * @param int $primaryPhotoId The ID of the photo to represent this set. The photo must belong
     * to the calling user.
     * @return bool
     */
    public function create($title, $description, $primaryPhotoId)
    {
        $args = [
            'title' => $title,
            'primary_photo_id' => $primaryPhotoId,
            'description' => $description,
        ];
        $response = $this->flickr->request('flickr.photosets.create', $args, true);
        return isset($response['photoset']) ? $response['photoset'] : false;
    }

    /**
     * Delete a photoset. This method requires authentication with 'write' permission.
     * @link https://www.flickr.com/services/api/flickr.photosets.delete.html
     * @param int $photosetId The id of the photoset to delete. It must be owned by the calling user.
     * @return bool
     */
    public function delete($photosetId)
    {
        $args = ['photoset_id' => $photosetId];
        return (bool)$this->flickr->request('flickr.photosets.delete', $args, true);
    }

    /**
     * Modify the meta-data for a photoset.
     * @link https://www.flickr.com/services/api/flickr.photosets.editMeta.html
     * @param int $photosetId The ID of the photoset to modify.
     * @param string $title The new title for the photoset.
     * @param string|null $description A description of the photoset. May contain limited HTML.
     * @return bool
     */
    public function editMeta($photosetId, $title, $description = null)
    {
        $args = [
            'photoset_id' => $photosetId,
            'title' => $title,
            'description' => $description,
        ];
        return (bool)$this->flickr->request('flickr.photosets.editMeta', $args, true);
    }

    /**
     * Modify the photos in a photoset. Use this method to add, remove and re-order photos.
     * @link https://www.flickr.com/services/api/flickr.photosets.editPhotos.html
     * @param int $photosetId The ID of the photoset to modify. The photoset must belong to the
     * calling user.
     * @param int $primaryPhotoId The ID of the photo to use as the 'primary' photo for the set.
     * This ID must also be passed along in $photoIds parameter.
     * @param string|string[] $photoIds An array or comma-delimited list of photo IDs to include in
     * the set. They will appear in the set in the order sent. This list must contain the primary
     * photo ID. All photos must belong to the owner of the set. This list of photos replaces the
     * existing list. Call flickr.photosets.addPhoto to append a photo to a set.
     * @return bool
     */
    public function editPhotos($photosetId, $primaryPhotoId, $photoIds)
    {
        if (is_array($photoIds)) {
            $photoIds = join(',', $photoIds);
        }
        $args = [
            'photoset_id' => $photosetId,
            'primary_photo_id' => $primaryPhotoId,
            'photo_ids' => $photoIds,
        ];
        return (bool)$this->flickr->request('flickr.photosets.editPhotos', $args, true);
    }

    /**
     * Returns next and previous photos for a photo in a set.
     * @link https://www.flickr.com/services/api/flickr.photosets.getContext.html
     * @param int $photoId The ID of the photo to fetch the context for.
     * @param int $photosetId The ID of the photoset for which to fetch the photo's context.
     * @return mixed[] Array with 'prevphoto' and 'nextphoto' keys.
     */
    public function getContext($photoId, $photosetId)
    {
        $args = [
            'photo_id' => $photoId,
            'photoset_id' => $photosetId,
        ];
        return $this->flickr->request('flickr.photosets.getContext', $args);
    }

    /**
     * Gets information about a photoset.
     * @link https://www.flickr.com/services/api/flickr.photosets.getInfo.html
     * @param int $photosetId The ID of the photoset to fetch information for.
     * @param string $userId The ID of the owner of the set passed in $photosetId.
     * @return mixed[]|bool
     */
    public function getInfo($photosetId, $userId)
    {
        $args = [
            'photoset_id' => $photosetId,
            'user_id' => $userId,
        ];
        $response = $this->flickr->request('flickr.photosets.getInfo', $args);
        return isset($response['photoset']) ? $response['photoset'] : false;
    }

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
     * @param string $photoIds A comma-separated list of photo ids. If specified, each returned set
     * will include a list of these photo IDs that are present in the set as
     * "has_requested_photos".
     * @return mixed[]|bool
     */
    public function getList(
        $userId = null,
        $page = null,
        $perPage = null,
        $primaryPhotoExtras = null,
        $photoIds = null
    ) {
        $args = [
            'user_id' => $userId,
            'page' => $page,
            'per_page' => $perPage,
            'primary_photo_extras' => $primaryPhotoExtras,
            'photo_ids' => $photoIds,
        ];
        $response = $this->flickr->request('flickr.photosets.getList', $args);
        return isset($response['photosets']) ? $response['photosets'] : false;
    }

    /**
     * Get the list of photos in a set.
     *
     * @param int $photosetId The photoset ID.
     * @param string $userId The owner of the photo set.
     * @param string|string[] $extras Extra information to fetch for each photo. Comma-delimited string or array of
     * strings. Possible values: license, date_upload, date_taken, owner_name, icon_server, original_format,
     * last_update, geo, tags, machine_tags, o_dims, views, media, path_alias, url_sq, url_t, url_s, url_m, and url_o.
     * @param int $perPage The number of results per page. Default and maximum are 500.
     * @param int $page Which page of results to return.
     * @param int $privacyFilter Return photos matching one of the following privacy levels:
     *   1 public photos;
     *   2 private photos visible to friends;
     *   3 private photos visible to family;
     *   4 private photos visible to friends & family;
     *   5 completely private photos.
     * @param string $media Filter results by media type. One of 'all', 'photos', or 'videos'.
     * @return array[]|bool
     */
    public function getPhotos(
        $photosetId,
        $userId = null,
        $extras = null,
        $perPage = null,
        $page = null,
        $privacyFilter = null,
        $media = null
    ) {
        if (is_array($extras)) {
            $extras = join(',', $extras);
        }
        $args= [
            'photoset_id' => $photosetId,
            'user_id' => $userId,
            'extras' => $extras,
            'per_page' => $perPage,
            'page' => $page,
            'privacy_filter' => $privacyFilter,
            'media' => $media,
        ];
        $response = $this->flickr->request('flickr.photosets.getPhotos', $args);
        return isset($response['photoset']) ? $response['photoset'] : false;
    }

    /**
     * Set the order of photosets for the calling user.
     *
     * @link https://www.flickr.com/services/api/flickr.photosets.orderSets.html
     * @param $photosetIds string|string[] An array or comma-delimited list of photoset IDs, ordered with the set to
     * show first, first in the list. Any set IDs not given in the list will be set to appear at the end of the list,
     * ordered by their IDs.
     * @return bool
     */
    public function orderSets($photosetIds)
    {
        if (is_array($photosetIds)) {
            $photosetIds = implode(",", $photosetIds);
        }
        $response = $this->flickr->request("flickr.photosets.orderSets", ["photoset_ids" => $photosetIds], true);
        return isset($response['stat']) && $response['stat'] === 'ok';
    }

    /**
     * Remove a photo from a photoset.
     *
     * @link https://www.flickr.com/services/api/flickr.photosets.removePhoto.html
     * @param $photosetId string The ID of the photoset to remove a photo from.
     * @param $photoId string The ID of the photo to remove from the set.
     * @return bool
     */
    public function removePhoto($photosetId, $photoId)
    {
        $params = ["photoset_id" => $photosetId, "photo_id" => $photoId];
        $response = $this->flickr->request("flickr.photosets.removePhoto", $params, true);
        return isset($response['stat']) && $response['stat'] === 'ok';
    }

    /**
     * Remove multiple photos from a photoset.
     *
     * @link https://www.flickr.com/services/api/flickr.photosets.removePhotos.html
     * @param $photosetId string The ID of the photoset to remove photos from.
     * @param $photoIds string|string[] Array or comma-delimited list of photo IDs to remove from the photoset.
     * @return bool
     */
    public function removePhotos($photosetId, $photoIds)
    {
        if (is_array($photoIds)) {
            $photoIds = implode(",", $photoIds);
        }
        $params = ['photoset_id' => $photosetId, 'photo_ids' => $photoIds];
        $response = $this->flickr->request('flickr.photosets.removePhotos', $params, true);
        return isset($response['stat']) && $response['stat'] === 'ok';
    }

    /**
     * Reorder some or all of the photos in a set.
     *
     * @link https://www.flickr.com/services/api/flickr.photosets.reorderPhotos.html
     * @param $photosetId string The ID of the photoset to reorder. The photoset must belong to the calling user.
     * @param $photoIds string|string[] Ordered, comma-delimited list or array of photo IDs. Photos that are not in the
     * list will keep their original order.
     * @return bool
     */
    public function reorderPhotos($photosetId, $photoIds)
    {
        if (is_array($photoIds)) {
            $photoIds = implode(",", $photoIds);
        }
        $params = ['photoset_id' => $photosetId, 'photo_ids' => $photoIds];
        $response = $this->flickr->request('flickr.photosets.reorderPhotos', $params, true);
        return isset($response['stat']) && $response['stat'] === 'ok';
    }

    /**
     * Set the primary photo of a photoset.
     *
     * @link https://www.flickr.com/services/api/flickr.photosets.setPrimaryPhoto.html
     * @param $photosetId string The ID of the photoset to set primary photo to.
     * @param $photoId string The ID of the photo to set as primary.
     * @return bool
     */
    public function setPrimaryPhoto($photosetId, $photoId)
    {
        $response = $this->flickr->request(
            'flickr.photosets.setPrimaryPhoto',
            ['photoset_id' => $photosetId, 'photo_id' => $photoId],
            true
        );
        return isset($response['stat']) && $response['stat'] === 'ok';
    }
}
