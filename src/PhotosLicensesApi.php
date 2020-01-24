<?php

namespace Samwilson\PhpFlickr;

class PhotosLicensesApi extends ApiMethodGroup
{

    /**
     * Fetches a list of available photo licenses for Flickr.
     * This method does not require authentication.
     * @link https://www.flickr.com/services/api/flickr.photos.licenses.getInfo.html
     * @return string[][] Each item has 'id', 'name', and 'url' elements, and the top level array
     * keys are also the license IDs.
     */
    public function getInfo()
    {
        $licenses = [];
        $response = $this->flickr->request('flickr.photos.licenses.getInfo');
        $licenseData = $response ? $response['licenses']['license'] : [];
        foreach ($licenseData as $license) {
            $licenses[$license['id']] = $license;
        }
        return $licenses;
    }

    /**
     * Sets the license for a photo. This method requires authentication with 'write' permission.
     * @link https://www.flickr.com/services/api/flickr.photos.licenses.setLicense.html
     * @param int $photoId The photo to update the license for.
     * @param int $licenseId The license to apply, or 0 (zero) to remove the current license. Note
     * that the "no known copyright restrictions" license (7) is not a valid argument.
     * @return bool
     */
    public function setLicense($photoId, $licenseId)
    {
        $method = 'flickr.photos.licenses.setLicense';
        $params = ['photo_id'=>$photoId, 'license_id'=>$licenseId];
        return $this->flickr->request($method, $params, true);
    }
}
