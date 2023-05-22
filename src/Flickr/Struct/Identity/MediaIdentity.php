<?php
declare(strict_types=1);

namespace App\Flickr\Struct\Identity;

class MediaIdentity implements OwnerAwareIdentity
{
    use OwnerAware;

    public function __construct(string $owner, public readonly int $mediaId)
    {
        $this->owner = $owner;
    }
}
