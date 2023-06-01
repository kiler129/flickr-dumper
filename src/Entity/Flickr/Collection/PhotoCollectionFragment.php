<?php
declare(strict_types=1);

namespace App\Entity\Flickr\Collection;

use App\Entity\Flickr\Photo;
use App\Entity\Flickr\Status\CollectionStatus;
use App\Exception\LogicException;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

trait PhotoCollectionFragment
{
    #[ORM\Embedded]
    protected CollectionStatus $status;

    /**
     * @return Collection<int, Photo>
     */
    public function getPhotos(): Collection
    {
        return $this->photos;
    }

    public function addPhoto(Photo $photo): self
    {
        if (!$this->photos->contains($photo)) {
            $this->photos->add($photo);
        }

        return $this;
    }

    public function isBlacklisted(): bool
    {
        return $this->status->blacklisted;
    }

    public function setBlacklisted(bool $isBlacklisted = true): self
    {
        $this->status->blacklisted = $isBlacklisted;

        return $this;
    }

    public function isDeleted(): bool
    {
        return $this->status->deleted;
    }

    public function setDeleted(bool $isDeleted = true): self
    {
        $this->status->deleted = $isDeleted;

        return $this;
    }

    public function lockForWrite(): void
    {
        if ($this->status->writeLockedAt !== null) {
            throw new LogicException(
                \sprintf(
                    'Collection %s:%d is already write-locked since %s - cannot re-lock',
                    static::class,
                    $this->id,
                    $this->status->writeLockedAt->format('Y-mt-d H:i:s')
                )
            );
        }

        $this->status->writeLockedAt = new \DateTimeImmutable();
    }

    public function unlockForWrite(): self
    {
        $this->status->writeLockedAt = null;

        return $this;
    }

    public function isWriteLocked(): bool
    {
        return $this->status->writeLockedAt !== null;
    }
}
