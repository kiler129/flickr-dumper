<?php
declare(strict_types=1);

namespace App\Entity\Flickr;

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
}
