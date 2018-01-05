<?php

namespace Samwilson\PhpFlickr;

class Util {

    /** @var string */
    protected $base58Alphabet = '123456789abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';

    /**
     * Encode a photo ID to Flickr's short-URL base-58 system.
     * @link https://www.flickr.com/groups/api/discuss/72157616713786392/
     * @param int $num
     * @return string
     */
    function base58encode($num) {
        $base_count = strlen($this->base58Alphabet);
        $encoded = '';
        while ($num >= $base_count) {
            $div = $num/$base_count;
            $mod = ($num-($base_count*intval($div)));
            $encoded = $this->base58Alphabet[$mod] . $encoded;
            $num = intval($div);
        }

        if ($num) {
            $encoded = $this->base58Alphabet[$num] . $encoded;
        }

        return $encoded;
    }

    /**
     * Decode a photo ID from Flickr's short-URL base-58 system.
     * @link https://www.flickr.com/groups/api/discuss/72157616713786392/
     * @param int $num
     * @return bool|int
     */
    function base58decode($num) {
        $decoded = 0;
        $multi = 1;
        while (strlen($num) > 0) {
            $digit = $num[strlen($num)-1];
            $decoded += $multi * strpos($this->base58Alphabet, $digit);
            $multi = $multi * strlen($this->base58Alphabet);
            $num = substr($num, 0, -1);
        }
        return $decoded;
    }
}