<?php
declare(strict_types=1);

namespace App\Flickr\Struct\Identity;

use App\Flickr\Enum\MediaCollectionType;

abstract class MediaCollectionIdentity
{
    public readonly MediaCollectionType $type;

    protected function __construct(MediaCollectionType $type)
    {
        $this->type = $type;
    }
}
