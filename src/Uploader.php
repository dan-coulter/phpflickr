<?php

namespace Samwilson\PhpFlickr;

use CURLFile;
use OAuth\Common\Exception\Exception as OauthException;
use OAuth\Common\Exception\Exception;
use SimpleXMLElement;

class Uploader
{

    /** @var PhpFlickr */
    protected $flickr;

    /** @var string */
    protected $uploadEndpoint = 'https://up.flickr.com/services/upload/';

    /** @var string */
    protected $replaceEndpoint = 'https://up.flickr.com/services/replace/';

    /**
     * @param PhpFlickr $flickr
     */
    public function __construct(PhpFlickr $flickr)
    {
        $this->flickr = $flickr;
    }

    /**
     * Upload a photo.
     * @link https://www.flickr.com/services/api/upload.api.html
     * @param string $photoFilename Full filesystem path to the photo to upload.
     * @param string|null $title The title of the photo.
     * @param string|null $description A description of the photo. May contain some limited HTML.
     * @param string|null $tags A space-seperated list of tags to apply to the photo.
     * @param bool|null $isPublic Specifies who can view the photo. If omitted permissions will
     * be set to user's default.
     * @param bool|null $isFriend Specifies who can view the photo. If omitted permissions will
     * be set to user's default.
     * @param bool|null $isFamily Specifies who can view the photo. If omitted permissions will
     * be set to user's default.
     * @param int|null $contentType Set to 1 for Photo, 2 for Screenshot, or 3 for Other. If
     * omitted , will be set to user's default.
     * @param int|null $hidden Set to 1 to keep the photo in global search results, 2 to hide from
     * public searches. If omitted, will be set based to user's default.
     * @param bool $async Whether to upload the file asynchronously (in which case a 'ticketid'
     * will be returned).
     * @return string[]
     */
    public function upload(
        $photoFilename,
        $title = null,
        $description = null,
        $tags = null,
        $isPublic = null,
        $isFriend = null,
        $isFamily = null,
        $contentType = null,
        $hidden = null,
        $async = false
    ) {
        $params = [
            'title' => $title,
            'description' => $description,
            'tags' => $tags,
            'is_public' => $isPublic,
            'is_friend' => $isFriend,
            'is_family' => $isFamily,
            'content_type' => $contentType,
            'hidden' => $hidden,
        ];
        if ($async) {
            $params['async'] = 1;
        }
        return $this->sendFile($photoFilename, $params);
    }

    /**
     * @link https://www.flickr.com/services/api/replace.api.html
     * @param string $photoFilename Full filesystem path to the file to upload.
     * @param int $photoId The ID of the photo to replace.
     * @param bool $async Photos may be replaced in async mode, for applications that don't want to
     * wait around for an upload to complete, leaving a socket connection open the whole time.
     * Processing photos asynchronously is recommended.
     * @return string[]
     */
    public function replace($photoFilename, $photoId, $async = null)
    {
        return $this->sendFile($photoFilename, ['photo_id' => $photoId, 'async' => $async]);
    }

    /**
     * @param string $filename
     * @param array $params
     * @return array
     * @throws Exception If an OAuth error occurs.
     * @throws FlickrException If the file can't be read.
     */
    protected function sendFile($filename, $params)
    {
        if (!is_readable($filename)) {
            throw new FlickrException("File not readable: $filename");
        }
        $args = $this->flickr
            ->getOauthService()
            ->getAuthorizationForPostingToAlternateUrl($params, $this->uploadEndpoint);
        // The 'photo' parameter can't be part of the authorization signature, so we add it now.
        $args['photo'] = new CURLFile(realpath($filename));

        $curl = curl_init($this->uploadEndpoint);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $args);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curl);
        $this->response = $response;
        curl_close($curl);

        // Some OAuth errors are not in an XML format, but instead look
        // like e.g. oauth_problem=token_rejected
        if (1 === preg_match('/oauth_problem=(.*)/', $response, $matches)) {
            throw new OauthException($matches[1]);
        }

        // Process result.
        $xml = simplexml_load_string($response);
        $uploadResponse = ['stat' => (string)$xml['stat'][0]];
        if (isset($xml->photoid)) {
            $uploadResponse['photoid'] = (int)$xml->photoid;
        }
        if (isset($xml->photoid['secret'])) {
            $uploadResponse['secret'] = (string)$xml->photoid['secret'];
        }
        if (isset($xml->photoid['originalsecret'])) {
            $uploadResponse['originalsecret'] = (string)$xml->photoid['originalsecret'];
        }
        if (isset($xml->ticketid)) {
            $uploadResponse['ticketid'] = (int)$xml->ticketid;
        }
        if (isset($xml->err)) {
            $uploadResponse['code'] = (int)$xml->err['code'];
            $uploadResponse['message'] = (string)$xml->err['msg'];
        }
        return $uploadResponse;
    }
}
