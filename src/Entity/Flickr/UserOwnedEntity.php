<?php
declare(strict_types=1);

namespace App\Entity\Flickr;

interface UserOwnedEntity
{
    public function getOwner(): User;
}
