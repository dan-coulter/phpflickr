<?php

namespace Samwilson\PhpFlickr;

class Util
{

    const BASE58_ALPHABET = '123456789abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
    const PRIVACY_PUBLIC = 1;
    const PRIVACY_FRIENDS = 2;
    const PRIVACY_FAMILY = 3;
    const PRIVACY_FRIENDS_FAMILY = 4;
    const PRIVACY_PRIVATE = 5;
    const DATE_GRANULARITY_EXACT = 0;
    const DATE_GRANULARITY_MONTH = 4;
    const DATE_GRANULARITY_YEAR = 6;
    const DATE_GRANULARITY_CIRCA = 8;

    /**
     * Encode a photo ID to Flickr's short-URL base-58 system.
     * @link https://www.flickr.com/groups/api/discuss/72157616713786392/
     * @param int $num
     * @return string
     */
    public static function base58encode($num)
    {
        $base_count = strlen(static::BASE58_ALPHABET);
        $encoded = '';
        while ($num >= $base_count) {
            $div = $num/$base_count;
            $mod = ($num-($base_count*intval($div)));
            $encoded = static::BASE58_ALPHABET[$mod].$encoded;
            $num = intval($div);
        }

        if ($num) {
            $encoded = static::BASE58_ALPHABET[$num].$encoded;
        }

        return $encoded;
    }

    /**
     * Decode a photo ID from Flickr's short-URL base-58 system.
     * @link https://www.flickr.com/groups/api/discuss/72157616713786392/
     * @param int $num
     * @return bool|int
     */
    public static function base58decode($num)
    {
        $decoded = 0;
        $multi = 1;
        while (strlen($num) > 0) {
            $digit = $num[strlen($num)-1];
            $decoded += $multi * strpos(static::BASE58_ALPHABET, $digit);
            $multi = $multi * strlen(static::BASE58_ALPHABET);
            $num = substr($num, 0, -1);
        }
        return $decoded;
    }

    /**
     * Get the privacy integer given the three privacy categories.
     * @param bool $isPublic
     * @param bool $isFriend
     * @param bool $isFamily
     * @return int
     */
    public static function privacyLevel($isPublic, $isFriend, $isFamily)
    {
        if ($isPublic) {
            return static::PRIVACY_PUBLIC;
        }
        if ($isFriend && $isFamily) {
            return static::PRIVACY_FRIENDS_FAMILY;
        }
        if ($isFriend) {
            return static::PRIVACY_FRIENDS;
        }
        if ($isFamily) {
            return static::PRIVACY_FAMILY;
        }
        return static::PRIVACY_PRIVATE;
    }

    /**
     * Get all privacy levels.
     * @return array
     */
    public static function getPrivacyLevels()
    {
        return [
            'public' => static::PRIVACY_PUBLIC,
            'friends_family' => static::PRIVACY_FRIENDS_FAMILY,
            'friends' => static::PRIVACY_FRIENDS,
            'family' => static::PRIVACY_FAMILY,
            'private' => static::PRIVACY_PRIVATE,
        ];
    }

    /**
     * Get the name of a given privacy level.
     * @param int $id The value of one of the PRIVACY_* constants.
     * @return string|bool The name of the privacy leve, or false if it doesn't exist.
     */
    public static function getPrivacyLevelById($id)
    {
        $privacyLevels = array_flip(static::getPrivacyLevels());
        if (!isset($privacyLevels[$id])) {
            return false;
        }
        return $privacyLevels[$id];
    }
}
