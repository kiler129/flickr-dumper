<?php
declare(strict_types=1);

namespace App\Flickr\Struct\Identity;

use App\Flickr\Enum\CollectionType;

abstract class CollectionIdentity
{
    public readonly CollectionType $type;

    protected function __construct(CollectionType $type)
    {
        $this->type = $type;
    }
}
