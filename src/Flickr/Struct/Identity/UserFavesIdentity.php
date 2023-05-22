<?php
declare(strict_types=1);

namespace App\Flickr\Struct\Identity;

use App\Flickr\Enum\MediaCollectionType;

final class UserFavesIdentity extends MediaCollectionIdentity implements OwnerAwareIdentity
{
    use OwnerAware;

    public function __construct(string $owner)
    {
        $this->owner = $owner;

        parent::__construct(MediaCollectionType::USER_FAVES);
    }
}
