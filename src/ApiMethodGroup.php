<?php

namespace Samwilson\PhpFlickr;

abstract class ApiMethodGroup {

    /** @var PhpFlickr */
    protected $flickr;

    public function __construct( PhpFlickr $flickr )
    {
        $this->flickr = $flickr;
    }
}
