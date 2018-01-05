<?php

namespace Samwilson\PhpFlickr;

class PeopleApi extends ApiMethodGroup {

    /**
     * Return a user's NSID, given their email address
     * @link https://www.flickr.com/services/api/flickr.people.findByEmail.html
     * @param string $findEmail The email address of the user to find (may be primary or secondary).
     * @return bool
     */
    public function findByEmail( $findEmail ) {
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
    public function findByUsername($username) {
        $response = $this->flickr->request(
            'flickr.people.findByUsername',
            ['username' => $username]
        );
        return isset($response['user']) ? $response['user'] : false;
    }

    public function getGroups() {

    }

    public function getInfo() {

    }

    public function getLimits() {

    }

    public function getPhotos() {

    }

    public function getPhotosOf() {

    }

    public function getPublicGroups() {

    }

    public function getPublicPhotos() {

    }

    public function getUploadStatus() {

    }
}