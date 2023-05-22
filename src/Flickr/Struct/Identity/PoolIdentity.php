<?php
declare(strict_types=1);

namespace App\Flickr\Struct\Identity;

use App\Exception\DomainException;
use App\Flickr\Enum\MediaCollectionType;

final class PoolIdentity extends MediaCollectionIdentity
{
    public function __construct(
        public readonly string $poolId,
    ) {
        parent::__construct(MediaCollectionType::POOL);

        if ($this->poolId === '') {
            throw new DomainException(
                \sprintf(
                    '%s requires owner & setId to be non-empty (got %s)',
                    static::class,
                    \print_r(\func_get_args(), true)
                )
            );
        }
    }
}
