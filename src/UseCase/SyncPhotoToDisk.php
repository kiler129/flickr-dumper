<?php
declare(strict_types=1);

namespace App\UseCase;

use App\Entity\Flickr\Photo;
use App\Filesystem\StorageProvider;

/**
 * @template TSyncCallback of callable(Photo $photo): bool
 */
class SyncPhotoToDisk
{
    /**
     * By default, we trust database photo record. When this option is disabled files will be verified for existence.
     * This is useful if someone deleted some files manually.
     * Keep in mind photos deleted properly will NOT be deleted (as their shadows still exist in the database).
     */
    public bool $trustPhotoRecords = true;

    /**
     * When set it will attempt to reset/regenerate identity of both the Flickr API client (new key, new UA, new proxy)
     * and the download client (new UA, new proxy). Naturally, if there's no or single proxy defined this option has
     * no effect on proxy; likewise the same applies to API keys. However, UA is always changed.
     * When exactly identities are swapped is deliberately opaque as it's determined to look real-ish.
     */
    public bool $switchIdentities = false;

    public function __construct(private StorageProvider $storage)
    {
    }

    public function __invoke(Photo $photo): bool
    {
        dump('INVOKED WITH PHOTO ID=' . $photo->getId());
        return true;
    }
}
