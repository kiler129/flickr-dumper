<?php
declare(strict_types=1);

namespace App\Entity\Flickr;

use Doctrine\Common\Collections\Collection;

interface PhotoCollection
{

    /**
     * @return bool Denotes whether the sync was completed at least once
     */
    public function isSyncCompleted(): bool;

    public function isBlacklisted(): bool;

    public function isDeleted(): bool;

    public function isWriteLocked(): bool;

    public function getUserReadableId(): string;

    /** @return Photo[]|Collection<Photo> */
    public function getPhotos(): Collection;

    public function addPhoto(Photo $photo): self;

    public function getOwner(): User;

    /**
     * Whether the photo collection owner also owns all photos in the collection
     *
     * This is generally true for user-owned collections:
     *  - user photostream
     *  - albums/photosets
     * However, in other cases the same assumption doesn't work:
     *  - public groups w/pools
     *  - user favorites
     *  - galleries
     *
     * This method exists because sometimes we can take a shortcut to not do lookups.
     *
     * @see https://www.flickr.com/help/forum/en-us/72157628846743439/
     * @return bool
     */
    public static function ownerOwnsPhotos(): bool;
}
