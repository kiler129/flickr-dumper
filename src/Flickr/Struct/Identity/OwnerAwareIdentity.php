<?php
declare(strict_types=1);

namespace App\Flickr\Struct\Identity;

interface OwnerAwareIdentity
{
    public function getOwner(): string;
    public function hasNSID(): bool;
    public function setNSID(string $ownerNSID): void;
}
