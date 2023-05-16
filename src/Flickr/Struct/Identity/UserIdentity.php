<?php
declare(strict_types=1);

namespace App\Flickr\Struct\Identity;

class UserIdentity
{
    public function __construct(
        public readonly string $nsid,
        public readonly ?string $userName,
        public readonly ?string $screenName
    ) {
    }
}
