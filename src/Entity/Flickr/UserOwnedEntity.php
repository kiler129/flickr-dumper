<?php
declare(strict_types=1);

namespace App\Entity\Flickr;

interface UserOwnedEntity
{
    public function getOwner(): ?User;
    public function setOwner(?User $owner): self;
}
