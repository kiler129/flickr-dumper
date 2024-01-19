<?php
declare(strict_types=1);

namespace App\Entity\Flickr;

interface Syncable
{
    public function getDateSyncCompleted(): \DateTimeImmutable | null;
    public function setDateSyncCompleted(?\DateTimeInterface $dateSyncCompleted): self;
    public function isSyncCompleted(): bool;
}
