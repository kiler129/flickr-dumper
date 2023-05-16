<?php
declare(strict_types=1);

namespace App\Flickr\Struct\Identity;

use App\Flickr\Enum\CollectionType;

final class UserPhotostreamIdentity extends CollectionIdentity implements OwnerAwareIdentity
{
    use OwnerAware;

    public function __construct(string $owner)
    {
        $this->owner = $owner;

        parent::__construct(CollectionType::USER_PHOTOSTREAM);
    }
}
