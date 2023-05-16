<?php
declare(strict_types=1);

namespace App\Flickr\Struct\Identity;

use App\Exception\DomainException;
use App\Flickr\Enum\CollectionType;

final class AlbumIdentity extends CollectionIdentity implements OwnerAwareIdentity
{
    use OwnerAware;

    public function __construct(
        string $owner,
        public readonly string $setId,
    ) {
        parent::__construct(CollectionType::ALBUM);

        if ($owner === '' || $this->setId === '') {
            throw new DomainException(
                \sprintf(
                    '%s requires owner & setId to be non-empty (got %s)',
                    static::class,
                    \print_r(\func_get_args(), true)
                )
            );
        }

        $this->owner = $owner;
    }
}
